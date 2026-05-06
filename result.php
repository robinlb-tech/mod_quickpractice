<?php
/**
 * Result / Review page for a completed attempt
 *
 * @package    mod_quickpractice
 * @copyright  2026 刘兵（青海师范大学附属中学信息科技教师）robinpcy@126.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/quickpractice/lib.php');
require_once($CFG->dirroot . '/mod/quickpractice/classes/local/question_renderer.php');

$attemptid = required_param('attemptid', PARAM_INT);

$attempt = $DB->get_record('quickpractice_attempts', ['id' => $attemptid], '*', MUST_EXIST);
$quickpractice = $DB->get_record('quickpractice', ['id' => $attempt->quickpracticeid], '*', MUST_EXIST);
list($course, $cm) = get_course_and_cm_from_instance($quickpractice->id, 'quickpractice');

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

// Permission: own result or teacher.
if ($attempt->userid !== $USER->id) {
    require_capability('mod/quickpractice:viewreport', $context);
}
require_capability('mod/quickpractice:viewownresult', $context);

// ── 数据准备 ──────────────────────────────────────────────
$responses = $DB->get_records('quickpractice_responses', ['attemptid' => $attemptid], 'id');
$questions = [];
foreach ($responses as $resp) {
    $q = $DB->get_record('quickpractice_questions', ['id' => $resp->questionid]);
    if ($q) {
        $q->my_response = json_decode($resp->response, true);
        $q->my_score    = $resp->score;
        $q->my_maxscore = $resp->maxscore;
        $q->my_correct  = $resp->correct;
        $q->my_feedback = $resp->feedback;
        $q->timetaken   = $resp->timetaken;
        $questions[]    = $q;
    }
}

$pct    = $attempt->maxscore > 0 ? round($attempt->score / $attempt->maxscore * 100, 1) : 0;
$passed = $pct >= $quickpractice->passscore;

// ── 班级排名 ──────────────────────────────────────────────
$rankdata = null;
if ($quickpractice->showranking) {
    $sql = "SELECT COUNT(DISTINCT a.userid) + 1 AS myrank
              FROM {quickpractice_attempts} a
             WHERE a.quickpracticeid = :qpid
               AND a.state = 'finished'
               AND (a.score / NULLIF(a.maxscore,0)) > :mypct
               AND a.classname = :cls";
    $rankdata = $DB->get_record_sql($sql, [
        'qpid'  => $quickpractice->id,
        'mypct' => $attempt->maxscore > 0 ? $attempt->score / $attempt->maxscore : 0,
        'cls'   => $attempt->classname,
    ]);
}

// ── 页面 ──────────────────────────────────────────────────
$PAGE->set_url('/mod/quickpractice/result.php', ['attemptid' => $attemptid]);
$PAGE->set_title(get_string('resultview', 'quickpractice'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->requires->css('/mod/quickpractice/styles.css');

$renderer = new \mod_quickpractice\local\question_renderer();

echo $OUTPUT->header();
echo '<div class="qp-result-page">';

// ── 总分卡片 ──────────────────────────────────────────────
$passClass = $passed ? 'qp-pass' : 'qp-fail';
echo '<div class="qp-score-card ' . $passClass . '">';
echo '<div class="qp-score-big">' . $pct . '<span class="qp-score-unit">%</span></div>';
echo '<div class="qp-score-label">' . ($passed
    ? get_string('passed', 'quickpractice')
    : get_string('failed', 'quickpractice')) . '</div>';
echo '<div class="qp-score-meta">';
echo get_string('yourscore', 'quickpractice') . ': ' . $attempt->score . '/' . $attempt->maxscore . ' | ';
echo get_string('timespent', 'quickpractice') . ': ' . ($attempt->duration ? gmdate('i:s', $attempt->duration) : '-');
if ($rankdata) {
    echo ' | ' . get_string('yourrank', 'quickpractice') . ': ' . $rankdata->myrank;
}
echo '</div>';
echo '</div>'; // score-card

// ── 各题反馈 ─────────────────────────────────────────────
if ($quickpractice->showfeedback) {
    echo '<div class="qp-review-section">';
    echo '<h3>📋 ' . get_string('yourfeedback', 'quickpractice') . '</h3>';

    foreach ($questions as $idx => $q) {
        $num    = $idx + 1;
        $correctVal = (int)$q->my_correct;
        if ($correctVal === 1) {
            $status = 'qp-correct';
            $icon   = '✅';
        } elseif ($correctVal === 0) {
            $status = 'qp-wrong';
            $icon   = '❌';
        } else {
            $status = 'qp-manual';
            $icon   = '📝';
        }

        echo '<div class="qp-review-item ' . $status . '">';
        echo '<div class="qp-review-num">' . $icon . ' Q' . $num . '</div>';
        echo '<div class="qp-review-text">' . format_text($q->questiontext, $q->questionformat) . '</div>';

        // 显示学生答案
        $myAns = is_array($q->my_response) ? implode(', ', $q->my_response) : ($q->my_response ?? '—');
        echo '<div class="qp-review-answer">';
        echo '<strong>' . get_string('correctanswer', 'quickpractice') . '：</strong>';
        $correctAnswers = json_decode($q->answers, true);
        $correctStr = is_array($correctAnswers) ? implode(' / ', $correctAnswers) : $q->answers;
        echo htmlspecialchars($correctStr);
        echo ' &nbsp;&nbsp; <strong>你的答案：</strong>' . htmlspecialchars($myAns);
        echo '</div>';

        // 题目反馈
        if (!empty($q->my_feedback)) {
            echo '<div class="qp-review-feedback">💡 ' . format_text($q->my_feedback, FORMAT_HTML) . '</div>';
        } elseif (!empty($q->feedback)) {
            echo '<div class="qp-review-feedback">💡 ' . format_text($q->feedback, FORMAT_HTML) . '</div>';
        }

        echo '<div class="qp-review-score">' . $q->my_score . '/' . $q->my_maxscore . ' 分</div>';
        echo '</div>'; // review-item
    }
    echo '</div>'; // review-section
}

// ── 底部按钮 ─────────────────────────────────────────────
echo '<div class="qp-result-actions">';
$backurl = new moodle_url('/mod/quickpractice/view.php', ['id' => $cm->id]);
echo html_writer::link($backurl, '← ' . get_string('back', 'quickpractice'), ['class' => 'btn btn-secondary']);

if ($quickpractice->showranking) {
    $rankurl = new moodle_url('/mod/quickpractice/ranking.php', ['id' => $cm->id]);
    echo ' ' . html_writer::link($rankurl, '🏆 ' . get_string('rankingview', 'quickpractice'), ['class' => 'btn btn-outline-primary']);
}
echo '</div>';

echo '</div>'; // result-page
echo $OUTPUT->footer();
