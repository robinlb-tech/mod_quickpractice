<?php
/**
 * Teacher Dashboard — overview of all student attempts
 *
 * @package    mod_quickpractice
 * @copyright  2026 刘兵（青海师范大学附属中学信息科技教师）robinpcy@126.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/quickpractice/lib.php');

$id        = required_param('id', PARAM_INT);          // cm id
$classname = optional_param('classname', '', PARAM_TEXT);
$search    = optional_param('search', '', PARAM_TEXT);
$page      = optional_param('page', 0, PARAM_INT);
$perpage   = 30;

list($course, $cm) = get_course_and_cm_from_cmid($id, 'quickpractice');
$quickpractice = $DB->get_record('quickpractice', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/quickpractice:viewreport', $context);

$PAGE->set_url('/mod/quickpractice/report.php', ['id' => $id]);
$PAGE->set_title(get_string('teacherdashboard', 'quickpractice'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->requires->css('/mod/quickpractice/styles.css');
$PAGE->requires->js_call_amd('mod_quickpractice/report_charts', 'init');

echo $OUTPUT->header();
echo $OUTPUT->heading('📊 ' . get_string('teacherdashboard', 'quickpractice'));

// ── 班级列表 ──────────────────────────────────────────────
$classes = $DB->get_fieldset_select('quickpractice_attempts', 'DISTINCT classname',
    'quickpracticeid = ? AND classname IS NOT NULL AND classname <> ?',
    [$quickpractice->id, '']);
sort($classes);

// ── 汇总统计 ──────────────────────────────────────────────
$statsql = "SELECT COUNT(DISTINCT userid) AS ustudents,
                   COUNT(*) AS tattempts,
                   AVG(score / NULLIF(maxscore,0) * 100) AS avgscore,
                   AVG(duration) AS avgdur,
                   SUM(CASE WHEN score / NULLIF(maxscore,0) * 100 >= :pass THEN 1 ELSE 0 END) AS passcount
              FROM {quickpractice_attempts}
             WHERE quickpracticeid = :qpid
               AND state = 'finished'
               " . ($classname ? " AND classname = :cls" : "");
$sparams = ['qpid' => $quickpractice->id, 'pass' => $quickpractice->passscore];
if ($classname) {
    $sparams['cls'] = $classname;
}
$stats = $DB->get_record_sql($statsql, $sparams);

echo '<div class="qp-stat-row">';
$cards = [
    ['🎓', get_string('totalstudents', 'quickpractice'), (int)$stats->ustudents],
    ['📝', get_string('totalattempts',  'quickpractice'), (int)$stats->tattempts],
    ['📈', get_string('avgscoreall',    'quickpractice'), round((float)$stats->avgscore, 1) . '%'],
    ['✅', get_string('passrate',       'quickpractice'),
        $stats->tattempts ? round($stats->passcount / $stats->tattempts * 100, 1) . '%' : '-'],
    ['⏱', get_string('avgduration',    'quickpractice'),
        $stats->avgdur ? gmdate('i:s', (int)$stats->avgdur) : '-'],
];
foreach ($cards as $card) {
    list($icon, $label, $value) = $card;
    echo '<div class="qp-stat-card"><div class="qp-stat-icon">' . $icon . '</div>'
        . '<div class="qp-stat-value">' . $value . '</div>'
        . '<div class="qp-stat-label">' . $label . '</div></div>';
}
echo '</div>';

// ── 成绩分布图（Canvas — 由 AMD 渲染） ───────────────────
$distData = _quickpractice_score_distribution($quickpractice->id, $classname, $DB);
echo '<div class="qp-chart-row">';
echo '<div class="qp-chart-box"><canvas id="qp-dist-chart" data-dist=\'' . json_encode($distData) . '\'></canvas></div>';

// 题目正确率图
$qData = _quickpractice_question_correctrate($quickpractice->id, $classname, $DB);
echo '<div class="qp-chart-box"><canvas id="qp-qcorrect-chart" data-qdata=\'' . json_encode($qData) . '\'></canvas></div>';
echo '</div>';

// ── 班级筛选条 ────────────────────────────────────────────
echo '<form method="get" class="qp-filter-form">';
echo '<input type="hidden" name="id" value="' . $id . '">';
echo '<select name="classname" onchange="this.form.submit()">';
echo '<option value="">' . get_string('allclasses', 'quickpractice') . '</option>';
foreach ($classes as $cls) {
    $sel = ($cls === $classname) ? ' selected' : '';
    echo '<option value="' . htmlspecialchars($cls) . '"' . $sel . '>' . htmlspecialchars($cls) . '</option>';
}
echo '</select>';
echo '<input type="text" name="search" value="' . htmlspecialchars($search) . '" placeholder="' . get_string('searchstudent', 'quickpractice') . '">';
echo '<button type="submit" class="btn btn-sm btn-primary">搜索</button>';
echo '</form>';

// ── 学生明细表 ────────────────────────────────────────────
$where  = 'a.quickpracticeid = :qpid AND a.state = :st';
$params = ['qpid' => $quickpractice->id, 'st' => 'finished'];
if ($classname) {
    $where .= ' AND a.classname = :cls';
    $params['cls'] = $classname;
}
if ($search) {
    $where .= ' AND (' . $DB->sql_like('u.firstname', ':s1', false)
        . ' OR ' . $DB->sql_like('u.lastname', ':s2', false)
        . ' OR ' . $DB->sql_like('u.username', ':s3', false) . ')';
    $params['s1'] = '%' . $DB->sql_like_escape($search) . '%';
    $params['s2'] = $params['s1'];
    $params['s3'] = $params['s1'];
}

$sql = "SELECT a.id, a.userid, a.attemptnum, a.score, a.maxscore, a.duration,
               a.timefinished, a.classname,
               u.firstname, u.lastname, u.username
          FROM {quickpractice_attempts} a
          JOIN {user} u ON u.id = a.userid
         WHERE {$where}
         ORDER BY (a.score / NULLIF(a.maxscore,0)) DESC, a.timefinished ASC";

$total   = $DB->count_records_sql("SELECT COUNT(*) FROM ({$sql}) sub", $params);
$records = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

echo '<div class="qp-table-header">';
echo '<span>' . $total . ' 条记录</span>';
// CSV 导出
$csvurl = new moodle_url('/mod/quickpractice/export.php', ['id' => $id, 'format' => 'csv', 'classname' => $classname]);
echo ' ' . html_writer::link($csvurl, get_string('exportcsv', 'quickpractice'), ['class' => 'btn btn-sm btn-outline-secondary']);
echo '</div>';

echo '<table class="qp-table generaltable">';
echo '<thead><tr>
    <th>' . get_string('rank', 'quickpractice') . '</th>
    <th>' . get_string('studentname', 'quickpractice') . '</th>
    <th>' . get_string('classname', 'quickpractice') . '</th>
    <th>' . get_string('score', 'quickpractice') . '</th>
    <th>' . get_string('duration', 'quickpractice') . '</th>
    <th>' . get_string('submittime', 'quickpractice') . '</th>
    <th>' . get_string('attempts', 'quickpractice') . '</th>
    <th></th>
</tr></thead><tbody>';

$rank = $page * $perpage + 1;
foreach ($records as $row) {
    $pct      = $row->maxscore > 0 ? round($row->score / $row->maxscore * 100, 1) : 0;
    $passed   = $pct >= $quickpractice->passscore;
    $badge    = $passed
        ? '<span class="qp-badge qp-pass">' . get_string('passed', 'quickpractice') . '</span>'
        : '<span class="qp-badge qp-fail">' . get_string('failed', 'quickpractice') . '</span>';
    $fullname = fullname($row);
    $dur      = $row->duration ? gmdate('i:s', $row->duration) : '-';

    $detailurl = new moodle_url('/mod/quickpractice/result.php', ['attemptid' => $row->id]);
    echo "<tr>
        <td>#{$rank}</td>
        <td>" . htmlspecialchars($fullname) . "</td>
        <td>" . htmlspecialchars($row->classname ?? '-') . "</td>
        <td>{$pct}% {$badge}</td>
        <td>{$dur}</td>
        <td>" . userdate($row->timefinished) . "</td>
        <td>{$row->attemptnum}</td>
        <td>" . html_writer::link($detailurl, '详情', ['class' => 'btn btn-xs btn-link']) . "</td>
    </tr>";
    $rank++;
}
echo '</tbody></table>';

// 分页
echo $OUTPUT->paging_bar($total, $page, $perpage,
    new moodle_url('/mod/quickpractice/report.php', ['id' => $id, 'classname' => $classname, 'search' => $search]));

// 生成评估报告按钮
if (has_capability('mod/quickpractice:generatereport', $context)) {
    $assessurl = new moodle_url('/mod/quickpractice/assessment.php', ['id' => $id, 'classname' => $classname]);
    echo '<div class="mt-3">' . html_writer::link($assessurl, '📝 ' . get_string('generatereport', 'quickpractice'),
        ['class' => 'btn btn-warning']) . '</div>';
}

echo $OUTPUT->footer();

// ── 私有辅助函数 ─────────────────────────────────────────

function _quickpractice_score_distribution($qpid, $cls, $DB) {
    $where = 'quickpracticeid = :qpid AND state = :st';
    $params = ['qpid' => $qpid, 'st' => 'finished'];
    if ($cls) { $where .= ' AND classname = :cls'; $params['cls'] = $cls; }
    $rows = $DB->get_records_select('quickpractice_attempts', $where, $params, '', 'score,maxscore');
    $dist = ['0-59' => 0, '60-69' => 0, '70-79' => 0, '80-89' => 0, '90-100' => 0];
    foreach ($rows as $r) {
        $pct = $r->maxscore > 0 ? $r->score / $r->maxscore * 100 : 0;
        if ($pct < 60)       $dist['0-59']++;
        elseif ($pct < 70)   $dist['60-69']++;
        elseif ($pct < 80)   $dist['70-79']++;
        elseif ($pct < 90)   $dist['80-89']++;
        else                 $dist['90-100']++;
    }
    return $dist;
}

function _quickpractice_question_correctrate($qpid, $cls, $DB) {
    $sql = "SELECT q.questiontext, q.id,
                   COUNT(r.id) AS total,
                   SUM(CASE WHEN r.correct = 1 THEN 1 ELSE 0 END) AS correct
              FROM {quickpractice_questions} q
              JOIN {quickpractice_responses} r ON r.questionid = q.id
              JOIN {quickpractice_attempts} a   ON a.id = r.attemptid
             WHERE q.quickpracticeid = :qpid
               AND a.state = 'finished'
               " . ($cls ? " AND a.classname = :cls" : "") . "
          GROUP BY q.id, q.questiontext
          ORDER BY q.sortorder";
    $params = ['qpid' => $qpid];
    if ($cls) { $params['cls'] = $cls; }
    $rows = $DB->get_records_sql($sql, $params);
    $result = [];
    foreach ($rows as $r) {
        $result[] = [
            'label'       => mb_strimwidth(strip_tags($r->questiontext), 0, 20, '…'),
            'correctrate' => $r->total > 0 ? round($r->correct / $r->total * 100, 1) : 0,
        ];
    }
    return $result;
}
