<?php
/**
 * Student-facing entry page for mod_quickpractice
 *
 * @package    mod_quickpractice
 * @copyright  2026 刘兵（青海师范大学附属中学信息科技教师）robinpcy@126.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/quickpractice/lib.php');
require_once($CFG->dirroot . '/mod/quickpractice/classes/local/attempt_manager.php');

$id = required_param('id', PARAM_INT);           // course module id
list($course, $cm) = get_course_and_cm_from_cmid($id, 'quickpractice');
$quickpractice = $DB->get_record('quickpractice', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/quickpractice:view', $context);

// Trigger course_module_viewed event.
$event = \mod_quickpractice\event\course_module_viewed::create([
    'objectid' => $quickpractice->id,
    'context'  => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('quickpractice', $quickpractice);
$event->trigger();

$PAGE->set_url('/mod/quickpractice/view.php', ['id' => $id]);
$PAGE->set_title(format_string($quickpractice->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->requires->css('/mod/quickpractice/styles.css');

// ── 设置侧边栏导航（教师可用功能） ─────────────────────────
if (has_capability('mod/quickpractice:viewreport', $context)) {
    $node = $PAGE->navigation->find($cm->id, navigation_node::TYPE_ACTIVITY);
    if ($node) {
        $node->add(get_string('teacherdashboard', 'quickpractice'),
            new moodle_url('/mod/quickpractice/report.php', ['id' => $id]),
            navigation_node::TYPE_SETTING, null, 'report');
        $node->add(get_string('questionbank', 'quickpractice'),
            new moodle_url('/mod/quickpractice/questionbank.php', ['id' => $id]),
            navigation_node::TYPE_SETTING, null, 'qbank');
    }
}
if (has_capability('mod/quickpractice:managequestions', $context)) {
    $node = $PAGE->navigation->find($cm->id, navigation_node::TYPE_ACTIVITY);
    if ($node) {
        $node->add(get_string('generator', 'quickpractice'),
            new moodle_url('/mod/quickpractice/generator.php', ['id' => $id]),
            navigation_node::TYPE_SETTING, null, 'generator');
        $node->add(get_string('reportview', 'quickpractice'),
            new moodle_url('/mod/quickpractice/assessment.php', ['id' => $id]),
            navigation_node::TYPE_SETTING, null, 'assessment');
    }
}

$manager = new \mod_quickpractice\local\attempt_manager($quickpractice, $USER, $context);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($quickpractice->name));

// ── 教师管理面板（顶部醒目显示） ────────────────────────────
$isTeacher = has_capability('mod/quickpractice:viewreport', $context);
$isManager = has_capability('mod/quickpractice:managequestions', $context);
if ($isTeacher || $isManager) {
    echo '<div class="qp-teacher-panel" style="background:#f0f6ff;border:2px solid #369;padding:12px 18px;margin:10px 0 20px;border-radius:8px;">';
    echo '<h4 style="margin:0 0 10px;color:#369;">🔧 教师管理</h4>';
    echo '<div style="display:flex;flex-wrap:wrap;gap:8px;">';
    if ($isTeacher) {
        echo '<a href="' . new moodle_url('/mod/quickpractice/report.php', ['id' => $id]) . '" class="btn btn-primary">📊 ' . get_string('teacherdashboard', 'quickpractice') . '</a> ';
        echo '<a href="' . new moodle_url('/mod/quickpractice/assessment.php', ['id' => $id]) . '" class="btn btn-info">📝 ' . get_string('reportview', 'quickpractice') . '</a> ';
    }
    if ($isManager) {
        echo '<a href="' . new moodle_url('/mod/quickpractice/questionbank.php', ['id' => $id]) . '" class="btn btn-success">📚 ' . get_string('questionbank', 'quickpractice') . '</a> ';
        echo '<a href="' . new moodle_url('/mod/quickpractice/generator.php', ['id' => $id]) . '" class="btn btn-warning">⚡ ' . get_string('generator', 'quickpractice') . '</a> ';
    }
    echo '</div></div>';
}

// ── 练习说明 ──────────────────────────────────────────────
if ($quickpractice->intro) {
    echo $OUTPUT->box(format_module_intro('quickpractice', $quickpractice, $cm->id), 'generalbox');
}

// ── 时间/状态提示 ─────────────────────────────────────────
$now = time();
if ($quickpractice->timeopen && $now < $quickpractice->timeopen) {
    echo $OUTPUT->notification(get_string('notopen', 'quickpractice'), 'info');
} elseif ($quickpractice->timeclose && $now > $quickpractice->timeclose) {
    echo $OUTPUT->notification(get_string('closed', 'quickpractice'), 'warning');
}

// ── 练习信息卡片 ──────────────────────────────────────────
$totalq = $DB->count_records('quickpractice_questions', ['quickpracticeid' => $quickpractice->id]);
$myattempts = $manager->get_my_attempts();
$finished = array_filter($myattempts, function($a) { return $a->state === 'finished'; });

echo '<div class="qp-info-card">';
echo '<div class="qp-info-grid">';
// 题目数
echo '<div class="qp-info-item"><span class="qp-label">' . get_string('totalquestions', 'quickpractice', $totalq) . '</span></div>';
// 时限
if ($quickpractice->timelimit) {
    $tl = format_time($quickpractice->timelimit);
    echo '<div class="qp-info-item"><span class="qp-label">⏱ ' . $tl . '</span></div>';
}
// 及格分
echo '<div class="qp-info-item"><span class="qp-label">' . get_string('passmark', 'quickpractice', $quickpractice->passscore) . '</span></div>';
// 允许次数
$maxatt = $quickpractice->maxattempts ?: get_string('unlimited');
echo '<div class="qp-info-item"><span class="qp-label">' . get_string('attemptsallowed', 'quickpractice', $maxatt) . '</span></div>';
echo '</div></div>';

// ── 历史作答记录 ──────────────────────────────────────────
if (!empty($myattempts)) {
    echo '<h3>' . get_string('attempts', 'quickpractice') . '</h3>';
    echo '<table class="qp-table generaltable">';
    echo '<thead><tr>
        <th>' . get_string('attemptno', 'quickpractice', '#') . '</th>
        <th>' . get_string('score', 'quickpractice') . '</th>
        <th>' . get_string('timespent', 'quickpractice') . '</th>
        <th>' . get_string('submittime', 'quickpractice') . '</th>
        <th></th>
    </tr></thead><tbody>';

    foreach ($myattempts as $att) {
        $scorepct = $att->maxscore > 0 ? round($att->score / $att->maxscore * 100, 1) : 0;
        $passed   = $scorepct >= $quickpractice->passscore;
        $badge    = $passed
            ? '<span class="qp-badge qp-pass">' . get_string('passed', 'quickpractice') . '</span>'
            : '<span class="qp-badge qp-fail">' . get_string('failed', 'quickpractice') . '</span>';
        $dur = $att->duration ? gmdate('i:s', $att->duration) : '-';
        $link = '';
        if ($att->state === 'finished') {
            $url = new moodle_url('/mod/quickpractice/result.php', ['attemptid' => $att->id]);
            $link = html_writer::link($url, get_string('reviewattempt', 'quickpractice'), ['class' => 'btn btn-sm btn-outline-secondary']);
        } elseif ($att->state === 'inprogress') {
            $url = new moodle_url('/mod/quickpractice/attempt.php', ['id' => $id, 'attemptid' => $att->id]);
            $link = html_writer::link($url, get_string('continueattempt', 'quickpractice'), ['class' => 'btn btn-sm btn-primary']);
        }
        echo "<tr>
            <td>{$att->attemptnum}</td>
            <td>{$scorepct}% {$badge}</td>
            <td>{$dur}</td>
            <td>" . ($att->timefinished ? userdate($att->timefinished) : '-') . "</td>
            <td>{$link}</td>
        </tr>";
    }
    echo '</tbody></table>';
}

// ── 开始/禁止作答按钮 ─────────────────────────────────────
$canstart = $manager->can_start_new_attempt();
if ($canstart === true && has_capability('mod/quickpractice:attempt', $context)) {
    $starturl = new moodle_url('/mod/quickpractice/attempt.php', ['id' => $id]);
    echo '<div class="qp-start-btn">';
    echo '<a href="' . $starturl . '" class="btn btn-lg btn-primary">' . get_string('startattempt', 'quickpractice') . '</a>';
    echo '</div>';
} elseif ($canstart === 'maxattempts') {
    echo $OUTPUT->notification(get_string('nomoreattempts', 'quickpractice'), 'warning');
}

// ── 排行榜预览（学生可见前5名） ──────────────────────────
if ($quickpractice->showranking && !empty($finished)) {
    echo '<div class="qp-section">';
    echo '<h3>🏆 ' . get_string('rankingview', 'quickpractice') . '</h3>';
    $rankurl = new moodle_url('/mod/quickpractice/ranking.php', ['id' => $id]);
    echo html_writer::link($rankurl, get_string('rankingview', 'quickpractice') . ' →', ['class' => 'btn btn-sm btn-outline-primary']);
    echo '</div>';
}

echo $OUTPUT->footer();
