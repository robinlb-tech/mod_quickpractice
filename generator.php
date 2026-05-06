<?php
/**
 * Practice Generator — auto-generate a new quickpractice from the question bank
 *
 * @package    mod_quickpractice
 * @copyright  2026 刘兵（青海师范大学附属中学信息科技教师）robinpcy@126.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/quickpractice/lib.php');

$id     = required_param('id', PARAM_INT);
$action = optional_param('action', 'form', PARAM_ALPHA);   // form | create

list($course, $cm) = get_course_and_cm_from_cmid($id, 'quickpractice');
$quickpractice = $DB->get_record('quickpractice', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/quickpractice:managequestions', $context);

$PAGE->set_url('/mod/quickpractice/generator.php', ['id' => $id]);
$PAGE->set_title(get_string('generator', 'quickpractice'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->requires->css('/mod/quickpractice/styles.css');

// ── 获取题库元信息 ─────────────────────────────────────────
$categories = $DB->get_fieldset_select('quickpractice_questions',
    'DISTINCT category', 'quickpracticeid = ? AND category IS NOT NULL AND category <> ?',
    [$quickpractice->id, '']);
sort($categories);

$uncategorizedCount = $DB->count_records_select('quickpractice_questions',
    'quickpracticeid = ? AND (category IS NULL OR category = ?)',
    [$quickpractice->id, '']);

$qtypes = $DB->get_fieldset_select('quickpractice_questions',
    'DISTINCT qtype', 'quickpracticeid = ?', [$quickpractice->id]);

// ── 创建新练习 ────────────────────────────────────────────
if ($action === 'create' && confirm_sesskey()) {

    $title      = required_param('title', PARAM_TEXT);
    $count      = (int)required_param('count', PARAM_INT);
    $difficulty = optional_param('difficulty', 0, PARAM_INT);
    $category   = optional_param('category', '', PARAM_TEXT);
    $qtype      = optional_param('qtype', '', PARAM_ALPHA);

    // Build query to pick questions
    $where  = 'quickpracticeid = :qpid';
    $params = ['qpid' => $quickpractice->id];
    if ($difficulty) {
        $where .= ' AND difficulty = :diff';
        $params['diff'] = $difficulty;
    }
    if ($category && $category !== '__uncategorized__') {
        $where .= ' AND category = :cat';
        $params['cat'] = $category;
    }
    if ($category === '__uncategorized__') {
        $where .= ' AND (category IS NULL OR category = :emptycat)';
        $params['emptycat'] = '';
    }
    if ($qtype) {
        $where .= ' AND qtype = :qt';
        $params['qt'] = $qtype;
    }

    $available = $DB->get_records_select('quickpractice_questions', $where, $params);
    if (count($available) < $count) {
        redirect(new moodle_url('/mod/quickpractice/generator.php', ['id' => $id]),
            get_string('gen_notenough', 'quickpractice', $count), null,
            \core\output\notification::NOTIFY_WARNING);
    }

    // ── Step 1: Create new quickpractice instance ──────────
    $newQP = new stdClass();
    $newQP->course        = $quickpractice->course;
    $newQP->name          = $title;
    $newQP->intro         = '';
    $newQP->introformat   = FORMAT_HTML;
    $newQP->timeopen      = 0;
    $newQP->timeclose     = 0;
    $newQP->timelimit     = $quickpractice->timelimit;
    $newQP->maxattempts   = $quickpractice->maxattempts;
    $newQP->shuffle       = $quickpractice->shuffle;
    $newQP->showfeedback  = $quickpractice->showfeedback;
    $newQP->showranking   = $quickpractice->showranking;
    $newQP->passscore     = $quickpractice->passscore;
    $newQP->grademethod   = $quickpractice->grademethod;
    $newQP->questioncount = $count;
    $newQP->timecreated   = time();
    $newQP->timemodified  = time();

    $newqpid = $DB->insert_record('quickpractice', $newQP, true);

    // ── Step 2: Copy selected questions ────────────────────
    $selected = array_slice($available, 0, $count, true);
    $sortorder = 0;
    foreach ($selected as $q) {
        $newQ = new stdClass();
        $newQ->quickpracticeid = $newqpid;
        $newQ->qtype           = $q->qtype;
        $newQ->questiontext    = $q->questiontext;
        $newQ->questionformat  = isset($q->questionformat) ? $q->questionformat : FORMAT_HTML;
        $newQ->options         = $q->options;
        $newQ->answers         = $q->answers;
        $newQ->feedback        = $q->feedback;
        $newQ->score           = $q->score;
        $newQ->difficulty      = $q->difficulty;
        $newQ->category        = isset($q->category) ? $q->category : '';
        $newQ->sortorder       = $sortorder++;
        $newQ->source          = 'generator';
        $newQ->timecreated     = time();
        $DB->insert_record('quickpractice_questions', $newQ);
    }

    // ── Step 3: Create course_module and add to section ────
    $module = $DB->get_record('modules', array('name' => 'quickpractice'));
    if (!$module) {
        // Rollback
        $DB->delete_records('quickpractice_questions', array('quickpracticeid' => $newqpid));
        $DB->delete_records('quickpractice', array('id' => $newqpid));
        print_error('error', 'quickpractice', '', 'Module quickpractice not found in modules table');
    }

    // Determine section: cm->section is the course_sections.id
    $parentsectionid = !empty($cm->section) ? (int)$cm->section : 0;
    if (!$parentsectionid) {
        $sec0 = $DB->get_record('course_sections', array('course' => $course->id, 'section' => 0));
        if ($sec0) {
            $parentsectionid = $sec0->id;
        }
    }

    // Insert into course_modules
    $cmrec = new stdClass();
    $cmrec->course      = $course->id;
    $cmrec->module      = $module->id;
    $cmrec->instance    = $newqpid;
    $cmrec->section     = $parentsectionid;
    $cmrec->idnumber    = '';
    $cmrec->added       = time();
    $cmrec->visible     = 1;
    $cmrec->visibleold  = 1;
    $cmrec->groupmode   = 0;
    $cmrec->groupingid  = 0;

    $newcmid = $DB->insert_record('course_modules', $cmrec, true);

    // Append new cm to the section sequence
    if ($parentsectionid > 0) {
        $sectionrec = $DB->get_record('course_sections', array('id' => $parentsectionid));
        if ($sectionrec && isset($sectionrec->sequence)) {
            $oldseq = trim($sectionrec->sequence, ',');
            $newseq = ($oldseq !== '') ? $oldseq . ',' . $newcmid : (string)$newcmid;
            $DB->set_field('course_sections', 'sequence', $newseq, array('id' => $parentsectionid));
        }
    }

    // Create context for the new module
    try {
        context_module::instance($newcmid);
    } catch (Exception $e) {
        // Context creation failure is not critical
        error_log('quickpractice generator: context creation failed - ' . $e->getMessage());
    }

    // Rebuild course cache
    try {
        if (function_exists('rebuild_course_cache')) {
            rebuild_course_cache($course->id, true);
        }
    } catch (Exception $e) {
        error_log('quickpractice generator: cache rebuild failed - ' . $e->getMessage());
    }

    // Redirect to the newly created activity
    redirect(new moodle_url('/mod/quickpractice/view.php', array('id' => $newcmid)));
}

// ── OUTPUT ────────────────────────────────────────────────
echo $OUTPUT->header();
echo '<div class="qp-generator-page">';
echo '<h2>⚡ ' . get_string('generator', 'quickpractice') . '</h2>';
echo '<p class="text-muted">' . get_string('generator_help', 'quickpractice') . '</p>';

$totalQ = $DB->count_records('quickpractice_questions', ['quickpracticeid' => $quickpractice->id]);
echo '<div class="qp-stat-card qp-inline-card"><strong>题库总量：' . $totalQ . ' 道</strong></div>';

$formurl = new moodle_url('/mod/quickpractice/generator.php',
    ['id' => $id, 'action' => 'create', 'sesskey' => sesskey()]);
echo '<form method="post" action="' . $formurl . '" class="qp-gen-form card p-4 mt-3">';

echo '<div class="form-row">';
echo '<div class="form-group col-md-6"><label>✏️ ' . get_string('gen_title', 'quickpractice') . '</label>'
    . '<input type="text" name="title" class="form-control" required value="'
    . htmlspecialchars(format_string($quickpractice->name) . ' — 生成版 ' . date('m-d H:i')) . '"></div>';
echo '<div class="form-group col-md-2"><label>📊 ' . get_string('gen_count', 'quickpractice') . '</label>'
    . '<input type="number" name="count" min="1" max="' . $totalQ . '" value="10" class="form-control" required></div>';
echo '</div>';

echo '<div class="form-row">';
// 难度
echo '<div class="form-group col-md-3"><label>' . get_string('gen_difficulty', 'quickpractice') . '</label>'
    . '<select name="difficulty" class="form-control">'
    . '<option value="0">全部难度</option>'
    . '<option value="1">简单</option><option value="2">中等</option><option value="3">困难</option>'
    . '</select></div>';

// 分类
echo '<div class="form-group col-md-3"><label>' . get_string('gen_category', 'quickpractice') . '</label>'
    . '<select name="category" class="form-control"><option value="">全部分类</option>';
if ($uncategorizedCount > 0) {
    echo '<option value="__uncategorized__">未分类 (' . $uncategorizedCount . ')</option>';
}
foreach ($categories as $cat) {
    echo '<option value="' . htmlspecialchars($cat) . '">' . htmlspecialchars($cat) . '</option>';
}
echo '</select></div>';

// 题型
$qtypeNames = ['multichoice' => '单选', 'multianswer' => '多选', 'truefalse' => '判断',
    'shortanswer' => '简答', 'matching' => '配对', 'essay' => '论述', 'numerical' => '计算'];
echo '<div class="form-group col-md-3"><label>' . get_string('gen_qtype', 'quickpractice') . '</label>'
    . '<select name="qtype" class="form-control"><option value="">全部题型</option>';
foreach ($qtypes as $qt) {
    echo '<option value="' . $qt . '">' . ($qtypeNames[$qt] ?? $qt) . '</option>';
}
echo '</select></div>';
echo '</div>';

echo '<button type="submit" class="btn btn-primary btn-lg">' . get_string('gen_create', 'quickpractice') . '</button>';
echo '</form>';

// ── 题库结构一览 ──────────────────────────────────────────
echo '<div class="qp-qbank-overview mt-4">';
echo '<h4>📂 题库结构</h4>';
$overview = $DB->get_records_sql(
    "SELECT category, qtype, difficulty, COUNT(*) AS cnt
       FROM {quickpractice_questions}
      WHERE quickpracticeid = :qpid
      GROUP BY category, qtype, difficulty
      ORDER BY category, qtype, difficulty",
    ['qpid' => $quickpractice->id]);

$catSummary = [];
foreach ($overview as $row) {
    $cat = $row->category ?: '未分类';
    if (!isset($catSummary[$cat])) {
        $catSummary[$cat] = 0;
    }
    $catSummary[$cat] += (int)$row->cnt;
}
if (!empty($catSummary)) {
    echo '<table class="qp-table generaltable" style="margin-bottom:15px"><thead><tr><th>分类</th><th>题目数</th></tr></thead><tbody>';
    foreach ($catSummary as $cat => $cnt) {
        echo '<tr><td>' . htmlspecialchars($cat) . '</td><td>' . $cnt . '</td></tr>';
    }
    echo '</tbody></table>';
}

echo '<table class="qp-table generaltable"><thead><tr><th>分类</th><th>题型</th><th>简单</th><th>中等</th><th>困难</th><th>合计</th></tr></thead><tbody>';
$byType = [];
foreach ($overview as $row) {
    $cat = $row->category ?: '未分类';
    $key = $cat . '|' . $row->qtype;
    if (!isset($byType[$key])) {
        $byType[$key] = ['cat' => $cat, 'qtype' => $row->qtype, 1 => 0, 2 => 0, 3 => 0];
    }
    $byType[$key][$row->difficulty] = (int)$row->cnt;
}
foreach ($byType as $key => $info) {
    $easy   = $info[1];
    $medium = $info[2];
    $hard   = $info[3];
    echo '<tr><td>' . htmlspecialchars($info['cat']) . '</td><td>' . ($qtypeNames[$info['qtype']] ?? $info['qtype']) . '</td><td>' . $easy . '</td><td>' . $medium . '</td><td>' . $hard . '</td><td>' . ($easy + $medium + $hard) . '</td></tr>';
}
echo '</tbody></table></div>';

$backurl = new moodle_url('/mod/quickpractice/view.php', ['id' => $id]);
echo html_writer::link($backurl, '← ' . get_string('back', 'quickpractice'), ['class' => 'btn btn-secondary mt-3']);
echo '</div>';
echo $OUTPUT->footer();
