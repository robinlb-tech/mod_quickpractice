<?php
/**
 * Assessment Report — generate and view teaching evaluation reports
 *
 * @package    mod_quickpractice
 * @copyright  2026 刘兵（青海师范大学附属中学信息科技教师）robinpcy@126.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/quickpractice/lib.php');
require_once($CFG->dirroot . '/mod/quickpractice/classes/local/report_generator.php');

$id         = required_param('id', PARAM_INT);
$classname  = optional_param('classname', '', PARAM_TEXT);
$action     = optional_param('action', 'view', PARAM_ALPHA);   // view|generate|show|delete
$reportid   = optional_param('reportid', 0, PARAM_INT);

list($course, $cm) = get_course_and_cm_from_cmid($id, 'quickpractice');
$quickpractice = $DB->get_record('quickpractice', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/quickpractice:viewreport', $context);

$PAGE->set_url('/mod/quickpractice/assessment.php', ['id' => $id]);
$PAGE->set_title(get_string('reportview', 'quickpractice'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->requires->css('/mod/quickpractice/styles.css');

$generator = new \mod_quickpractice\local\report_generator($quickpractice, $DB);

// ── 生成报告 ──────────────────────────────────────────────
if ($action === 'generate' && has_capability('mod/quickpractice:generatereport', $context) && confirm_sesskey()) {
    $title = optional_param('title', '', PARAM_TEXT) ?: (format_string($quickpractice->name) . ' — 评估报告 ' . date('Y-m-d'));
    $rtype = optional_param('rtype', 'class', PARAM_ALPHA);
    $data  = $generator->generate($classname, $rtype);
    $record = (object)[
        'quickpracticeid' => $quickpractice->id,
        'createdby'       => $USER->id,
        'classname'       => $classname,
        'reporttype'      => $rtype,
        'reportdata'      => json_encode($data),
        'timecreated'     => time(),
        'title'           => $title,
    ];
    $reportid = $DB->insert_record('quickpractice_reports', $record);
    redirect(new moodle_url('/mod/quickpractice/assessment.php',
        ['id' => $id, 'action' => 'show', 'reportid' => $reportid]));
}

// ── 删除报告 ──────────────────────────────────────────────
if ($action === 'delete' && $reportid && has_capability('mod/quickpractice:generatereport', $context) && confirm_sesskey()) {
    $DB->delete_records('quickpractice_reports', ['id' => $reportid, 'quickpracticeid' => $quickpractice->id]);
    redirect(new moodle_url('/mod/quickpractice/assessment.php', ['id' => $id]),
        get_string('reportsaved', 'quickpractice'));
}

echo $OUTPUT->header();
echo '<div class="qp-assessment-page">';
echo '<h2>📝 ' . get_string('reportview', 'quickpractice') . ' — ' . format_string($quickpractice->name) . '</h2>';

// ── 显示某份报告 ──────────────────────────────────────────
if ($action === 'show' && $reportid) {
    $report = $DB->get_record('quickpractice_reports',
        ['id' => $reportid, 'quickpracticeid' => $quickpractice->id], '*', MUST_EXIST);
    $data   = json_decode($report->reportdata, true);

    echo '<div class="qp-report-header">';
    echo '<h3>' . htmlspecialchars($report->title) . '</h3>';
    echo '<p>班级：' . htmlspecialchars($report->classname ?: '全部') . ' | 生成时间：' . userdate($report->timecreated) . '</p>';
    echo '</div>';

    // 1. 总体概况
    $sum = $data['summary'] ?? [];
    echo '<div class="qp-report-section">';
    echo '<h4>📊 总体概况</h4>';
    echo '<div class="qp-stat-row qp-report-stats">';
    $sumItems = [
        ['参与人数', $sum['student_count'] ?? 0],
        ['平均分', round($sum['avg_score'] ?? 0, 1) . '%'],
        ['及格率', round($sum['pass_rate'] ?? 0, 1) . '%'],
        ['最高分', round($sum['max_score'] ?? 0, 1) . '%'],
        ['最低分', round($sum['min_score'] ?? 0, 1) . '%'],
        ['平均用时', isset($sum['avg_duration']) ? gmdate('i:s', (int)$sum['avg_duration']) : '-'],
    ];
    foreach ($sumItems as $item) {
        list($label, $val) = $item;
        echo '<div class="qp-stat-card"><div class="qp-stat-value">' . $val . '</div><div class="qp-stat-label">' . $label . '</div></div>';
    }
    echo '</div></div>';

    // 2. 题目分析
    $qanalysis = $data['question_analysis'] ?? [];
    if (!empty($qanalysis)) {
        echo '<div class="qp-report-section">';
        echo '<h4>🔍 ' . get_string('questionanalysis', 'quickpractice') . '</h4>';
        echo '<table class="qp-table generaltable">';
        echo '<thead><tr><th>#</th><th>题目</th><th>正确率</th><th>难度</th><th>状态</th></tr></thead><tbody>';
        foreach ($qanalysis as $i => $qa) {
            $rate = $qa['correct_rate'];
            $status = $rate >= 80 ? '✅ 良好' : ($rate >= 50 ? '⚠️ 中等' : '❌ 薄弱');
            $barColor = $rate >= 80 ? 'qp-bar-good' : ($rate >= 50 ? 'qp-bar-mid' : 'qp-bar-bad');
            echo '<tr>
                <td>' . ($i + 1) . '</td>
                <td>' . htmlspecialchars($qa['label']) . '</td>
                <td><div class="qp-score-bar ' . $barColor . '"><div style="width:' . $rate . '%"></div></div>' . $rate . '%</td>
                <td>' . ['', '简单', '中等', '困难'][$qa['difficulty'] ?? 2] . '</td>
                <td>' . $status . '</td>
            </tr>';
        }
        echo '</tbody></table></div>';
    }

    // 3. 薄弱知识点
    $weak = $data['weak_points'] ?? [];
    if (!empty($weak)) {
        echo '<div class="qp-report-section qp-weak">';
        echo '<h4>⚠️ ' . get_string('weakpoints', 'quickpractice') . '</h4>';
        echo '<ul>';
        foreach ($weak as $w) {
            echo '<li>' . htmlspecialchars($w['category'] ?? '') . '：正确率 ' . $w['rate'] . '%，涉及 ' . $w['count'] . ' 道题</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    // 4. 教学建议
    $suggestions = $data['suggestions'] ?? [];
    if (!empty($suggestions)) {
        echo '<div class="qp-report-section qp-suggestions">';
        echo '<h4>💡 ' . get_string('suggestions', 'quickpractice') . '</h4>';
        echo '<ol>';
        foreach ($suggestions as $s) {
            echo '<li>' . htmlspecialchars($s) . '</li>';
        }
        echo '</ol>';
        echo '</div>';
    }

    // 操作按钮
    echo '<div class="qp-report-actions">';
    $backurl = new moodle_url('/mod/quickpractice/assessment.php', ['id' => $id]);
    echo html_writer::link($backurl, '← ' . get_string('back', 'quickpractice'), ['class' => 'btn btn-secondary']);

    $delurl = new moodle_url('/mod/quickpractice/assessment.php', [
        'id' => $id, 'action' => 'delete', 'reportid' => $reportid, 'sesskey' => sesskey()]);
    echo ' ' . html_writer::link($delurl, '🗑 删除', ['class' => 'btn btn-danger',
        'onclick' => 'return confirm("' . get_string('deleteconfirm', 'quickpractice') . '")']);
    echo '</div>';

} else {
    // ── 列出历史报告 & 生成新报告 ────────────────────────

    // 生成表单
    if (has_capability('mod/quickpractice:generatereport', $context)) {
        $classes = $DB->get_fieldset_select('quickpractice_attempts', 'DISTINCT classname',
            'quickpracticeid = ? AND classname IS NOT NULL AND classname <> ?',
            [$quickpractice->id, '']);
        sort($classes);

        echo '<div class="qp-gen-form card p-3 mb-4">';
        echo '<h4>🔧 ' . get_string('generatereport', 'quickpractice') . '</h4>';
        $genurl = new moodle_url('/mod/quickpractice/assessment.php',
            ['id' => $id, 'action' => 'generate', 'sesskey' => sesskey()]);
        echo '<form method="post" action="' . $genurl . '">';
        echo '<div class="form-group"><label>报告标题</label>'
            . '<input type="text" name="title" class="form-control" value="' . htmlspecialchars(format_string($quickpractice->name) . ' 评估报告 ' . date('Y-m-d')) . '"></div>';
        echo '<div class="form-group"><label>班级</label><select name="classname" class="form-control">'
            . '<option value="">全部班级</option>';
        foreach ($classes as $cls) {
            echo '<option value="' . htmlspecialchars($cls) . '"' . ($cls == $classname ? ' selected' : '') . '>' . htmlspecialchars($cls) . '</option>';
        }
        echo '</select></div>';
        echo '<button type="submit" class="btn btn-warning">📝 生成报告</button>';
        echo '</form></div>';
    }

    // 历史报告列表
    $reports = $DB->get_records('quickpractice_reports',
        ['quickpracticeid' => $quickpractice->id], 'timecreated DESC', '*', 0, 20);
    if (!empty($reports)) {
        echo '<h4>' . get_string('reportlist', 'quickpractice') . '</h4>';
        echo '<table class="qp-table generaltable">';
        echo '<thead><tr><th>报告名称</th><th>班级</th><th>生成时间</th><th></th></tr></thead><tbody>';
        foreach ($reports as $r) {
            $showurl = new moodle_url('/mod/quickpractice/assessment.php',
                ['id' => $id, 'action' => 'show', 'reportid' => $r->id]);
            echo '<tr>
                <td>' . htmlspecialchars($r->title) . '</td>
                <td>' . htmlspecialchars($r->classname ?: '全部') . '</td>
                <td>' . userdate($r->timecreated) . '</td>
                <td>' . html_writer::link($showurl, '查看', ['class' => 'btn btn-sm btn-primary']) . '</td>
            </tr>';
        }
        echo '</tbody></table>';
    } else {
        echo $OUTPUT->notification(get_string('nodatayet', 'quickpractice'), 'info');
    }
}

echo '</div>';
echo $OUTPUT->footer();
