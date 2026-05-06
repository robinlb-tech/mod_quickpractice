<?php
/**
 * Ranking page — class leaderboard
 *
 * @package    mod_quickpractice
 * @copyright  2026 刘兵（青海师范大学附属中学信息科技教师）robinpcy@126.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/quickpractice/lib.php');

$id        = required_param('id', PARAM_INT);
$classname = optional_param('classname', '', PARAM_TEXT);
$limit     = optional_param('limit', 20, PARAM_INT);

list($course, $cm) = get_course_and_cm_from_cmid($id, 'quickpractice');
$quickpractice = $DB->get_record('quickpractice', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/quickpractice:view', $context);

$PAGE->set_url('/mod/quickpractice/ranking.php', ['id' => $id]);
$PAGE->set_title(get_string('rankingview', 'quickpractice'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->requires->css('/mod/quickpractice/styles.css');

// ── 获取排行数据 ──────────────────────────────────────────
// 取每人最高分次
$sql = "SELECT a.userid,
               u.firstname, u.lastname,
               a.classname,
               MAX(a.score / NULLIF(a.maxscore,0) * 100) AS bestscore,
               MIN(a.duration) AS bestduration
          FROM {quickpractice_attempts} a
          JOIN {user} u ON u.id = a.userid
         WHERE a.quickpracticeid = :qpid
           AND a.state = 'finished'
           " . ($classname ? " AND a.classname = :cls" : "") . "
         GROUP BY a.userid, u.firstname, u.lastname, a.classname
         ORDER BY bestscore DESC, bestduration ASC";
$params = ['qpid' => $quickpractice->id];
if ($classname) { $params['cls'] = $classname; }
$rows = $DB->get_records_sql($sql, $params, 0, $limit);

// 班级列表（筛选）
$classes = $DB->get_fieldset_select('quickpractice_attempts', 'DISTINCT classname',
    'quickpracticeid = ? AND state = ? AND classname IS NOT NULL AND classname <> ?',
    [$quickpractice->id, 'finished', '']);
sort($classes);

echo $OUTPUT->header();
echo '<div class="qp-ranking-page">';
echo '<h2>🏆 ' . get_string('rankingview', 'quickpractice') . ' — ' . format_string($quickpractice->name) . '</h2>';

// 班级筛选
echo '<form method="get" class="qp-filter-form">';
echo '<input type="hidden" name="id" value="' . $id . '">';
echo '<select name="classname" onchange="this.form.submit()">';
echo '<option value="">' . get_string('allclasses', 'quickpractice') . '</option>';
foreach ($classes as $cls) {
    $sel = ($cls === $classname) ? ' selected' : '';
    echo '<option value="' . htmlspecialchars($cls) . '"' . $sel . '>' . htmlspecialchars($cls) . '</option>';
}
echo '</select></form>';

if (empty($rows)) {
    echo $OUTPUT->notification(get_string('nodatayet', 'quickpractice'), 'info');
} else {
    echo '<table class="qp-table qp-ranking-table generaltable">';
    echo '<thead><tr>
        <th width="60">名次</th>
        <th>' . get_string('studentname', 'quickpractice') . '</th>
        <th>' . get_string('classname', 'quickpractice') . '</th>
        <th>' . get_string('score', 'quickpractice') . '</th>
        <th>' . get_string('duration', 'quickpractice') . '</th>
    </tr></thead><tbody>';

    $rank = 1;
    $myRankRow = null;
    foreach ($rows as $row) {
        if ($rank === 1) {
            $medal = '🥇';
        } elseif ($rank === 2) {
            $medal = '🥈';
        } elseif ($rank === 3) {
            $medal = '🥉';
        } else {
            $medal = "#{$rank}";
        }
        $isMe     = ($row->userid == $USER->id);
        $rowClass = $isMe ? ' class="qp-my-row"' : '';
        $dur      = $row->bestduration ? gmdate('i:s', (int)$row->bestduration) : '-';
        $pct      = round((float)$row->bestscore, 1);
        $bar      = '<div class="qp-score-bar"><div style="width:' . $pct . '%"></div></div>';

        echo "<tr{$rowClass}>
            <td>{$medal}</td>
            <td>" . ($isMe ? '<strong>' : '') . htmlspecialchars(fullname($row)) . ($isMe ? '</strong>' : '') . "</td>
            <td>" . htmlspecialchars($row->classname ?? '-') . "</td>
            <td>{$pct}% {$bar}</td>
            <td>{$dur}</td>
        </tr>";

        if ($isMe) { $myRankRow = $rank; }
        $rank++;
    }
    echo '</tbody></table>';

    if ($myRankRow) {
        echo '<p class="qp-my-rank-hint">你当前排名：<strong>#' . $myRankRow . '</strong></p>';
    }
}

$backurl = new moodle_url('/mod/quickpractice/view.php', ['id' => $id]);
echo html_writer::link($backurl, '← ' . get_string('back', 'quickpractice'), ['class' => 'btn btn-secondary mt-3']);
echo '</div>';
echo $OUTPUT->footer();
