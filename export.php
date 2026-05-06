<?php
/**
 * CSV export for teacher report
 *
 * @package    mod_quickpractice
 * @copyright  2026 刘兵（青海师范大学附属中学信息科技教师）robinpcy@126.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/quickpractice/lib.php');

$id        = required_param('id', PARAM_INT);
$classname = optional_param('classname', '', PARAM_TEXT);

list($course, $cm) = get_course_and_cm_from_cmid($id, 'quickpractice');
$quickpractice = $DB->get_record('quickpractice', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/quickpractice:viewreport', $context);

// ── 查询数据 ──────────────────────────────────────────────
$where  = 'a.quickpracticeid = :qpid AND a.state = :st';
$params = ['qpid' => $quickpractice->id, 'st' => 'finished'];
if ($classname) {
    $where .= ' AND a.classname = :cls';
    $params['cls'] = $classname;
}

$sql = "SELECT u.lastname, u.firstname, u.username,
               a.classname, a.attemptnum,
               a.score, a.maxscore,
               ROUND(a.score / NULLIF(a.maxscore,0) * 100, 1) AS pct,
               a.duration, a.timefinished
          FROM {quickpractice_attempts} a
          JOIN {user} u ON u.id = a.userid
         WHERE {$where}
         ORDER BY pct DESC, a.timefinished ASC";

$rows = $DB->get_records_sql($sql, $params);

// ── 输出 CSV ──────────────────────────────────────────────
$filename = clean_filename($quickpractice->name . '_' . ($classname ?: '全班') . '_' . date('Ymd') . '.csv');

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache');

// UTF-8 BOM for Excel compatibility
echo "\xEF\xBB\xBF";

$fp = fopen('php://output', 'w');
fputcsv($fp, ['姓名', '用户名', '班级', '作答次数', '得分', '满分', '百分比', '用时(秒)', '提交时间']);

foreach ($rows as $r) {
    fputcsv($fp, [
        $r->lastname . $r->firstname,
        $r->username,
        $r->classname ?? '未知',
        $r->attemptnum,
        $r->score,
        $r->maxscore,
        $r->pct . '%',
        $r->duration ?? 0,
        $r->timefinished ? date('Y-m-d H:i:s', $r->timefinished) : '',
    ]);
}
fclose($fp);
exit;
