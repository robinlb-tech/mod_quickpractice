<?php
/**
 * Attempt page — students answer questions here
 *
 * @package    mod_quickpractice
 * @copyright  2026 刘兵（青海师范大学附属中学信息科技教师）robinpcy@126.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/quickpractice/lib.php');
require_once($CFG->dirroot . '/mod/quickpractice/classes/local/attempt_manager.php');
require_once($CFG->dirroot . '/mod/quickpractice/classes/local/question_renderer.php');
require_once($CFG->dirroot . '/mod/quickpractice/classes/local/grader.php');

$id        = required_param('id', PARAM_INT);
$attemptid = optional_param('attemptid', 0, PARAM_INT);
$action    = optional_param('action', 'view', PARAM_ALPHA);  // view | submit

list($course, $cm) = get_course_and_cm_from_cmid($id, 'quickpractice');
$quickpractice = $DB->get_record('quickpractice', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/quickpractice:attempt', $context);

$manager  = new \mod_quickpractice\local\attempt_manager($quickpractice, $USER, $context);
$renderer = new \mod_quickpractice\local\question_renderer();
$grader   = new \mod_quickpractice\local\grader($quickpractice);

// ── 处理提交 ──────────────────────────────────────────────
if ($action === 'submit' && confirm_sesskey()) {
    $attempt = $manager->get_attempt($attemptid, $USER->id);
    if (!$attempt || $attempt->state !== 'inprogress') {
        redirect(new moodle_url('/mod/quickpractice/view.php', ['id' => $id]));
    }

    $questions = $manager->get_attempt_questions($attempt);
    foreach ($questions as $q) {
        $key      = 'q_' . $q->id;
        $response = optional_param_array($key, null, PARAM_RAW) ?? optional_param($key, null, PARAM_RAW);
        $grader->save_response($attempt->id, $q, $response);
    }

    $manager->finish_attempt($attempt, $grader);
    redirect(new moodle_url('/mod/quickpractice/result.php', ['attemptid' => $attempt->id]));
}

// ── 获取或创建 attempt ────────────────────────────────────
if ($attemptid) {
    $attempt = $manager->get_attempt($attemptid, $USER->id);
    if (!$attempt) {
        redirect(new moodle_url('/mod/quickpractice/view.php', ['id' => $id]));
    }
} else {
    $canstart = $manager->can_start_new_attempt();
    if ($canstart !== true) {
        redirect(new moodle_url('/mod/quickpractice/view.php', ['id' => $id]),
            get_string('nomoreattempts', 'quickpractice'), null, \core\output\notification::NOTIFY_WARNING);
    }
    $attempt = $manager->create_attempt();
}

$questions = $manager->get_attempt_questions($attempt);

// ── 页面输出 ──────────────────────────────────────────────
$PAGE->set_url('/mod/quickpractice/attempt.php', ['id' => $id, 'attemptid' => $attempt->id]);
$PAGE->set_title(format_string($quickpractice->name) . ' — ' . get_string('attemptview', 'quickpractice'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->requires->css('/mod/quickpractice/styles.css');
$PAGE->requires->js_call_amd('mod_quickpractice/attempt_timer', 'init', [
    'timelimit'  => (int)$quickpractice->timelimit,
    'startedAt'  => (int)$attempt->timecreated,
    'submitUrl'  => (new moodle_url('/mod/quickpractice/attempt.php', [
        'id'        => $id,
        'attemptid' => $attempt->id,
        'action'    => 'submit',
        'sesskey'   => sesskey(),
    ]))->out(false),
]);

echo $OUTPUT->header();

// ── 倒计时条 ─────────────────────────────────────────────
if ($quickpractice->timelimit) {
    echo '<div class="qp-timer-bar">
        <span id="qp-timer-display">--:--</span>
        <div class="qp-timer-progress"><div id="qp-timer-fill"></div></div>
    </div>';
}

echo '<h2 class="qp-attempt-title">' . format_string($quickpractice->name) . '</h2>';
echo '<p class="qp-attempt-info">' . get_string('attemptno', 'quickpractice', $attempt->attemptnum) . ' | ' . count($questions) . ' ' . get_string('totalquestions', 'quickpractice', '') . '</p>';

// ── 答题表单 ──────────────────────────────────────────────
$submiturl = new moodle_url('/mod/quickpractice/attempt.php', [
    'id'        => $id,
    'attemptid' => $attempt->id,
    'action'    => 'submit',
    'sesskey'   => sesskey(),
]);

echo '<form id="qp-attempt-form" method="post" action="' . $submiturl . '">';
echo '<div class="qp-questions-container">';

foreach ($questions as $idx => $q) {
    $num = $idx + 1;
    echo '<div class="qp-question-block" id="qp-q-' . $q->id . '" data-qid="' . $q->id . '">';
    echo '<div class="qp-q-number">' . $num . ' / ' . count($questions) . '</div>';
    echo '<div class="qp-q-body">';
    echo $renderer->render_question($q, $num);
    echo '</div></div>';
}

echo '</div>';
echo '<div class="qp-submit-area">';
echo '<button type="button" onclick="qpConfirmSubmit()" class="btn btn-lg btn-success qp-submit-btn">'
    . get_string('submitattempt', 'quickpractice') . '</button>';
echo '</div>';
echo '</form>';

// ── 确认提交对话框 JS ─────────────────────────────────────
echo '<script>
function qpConfirmSubmit() {
    if (confirm("' . get_string('confirm', 'quickpractice') . '：' . get_string('submitattempt', 'quickpractice') . '？")) {
        document.getElementById("qp-attempt-form").submit();
    }
}
</script>';

echo $OUTPUT->footer();
