<?php
/**
 * Course listing page (index.php — required by Moodle)
 *
 * @package    mod_quickpractice
 * @copyright  2026 刘兵（青海师范大学附属中学信息科技教师）robinpcy@126.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/quickpractice/lib.php');

$id = required_param('id', PARAM_INT);   // Course id
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_course_login($course);

$PAGE->set_url('/mod/quickpractice/index.php', ['id' => $id]);
$PAGE->set_title(get_string('modulenameplural', 'quickpractice'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'quickpractice'));

$instances = get_all_instances_in_course('quickpractice', $course);
if (empty($instances)) {
    notice(get_string('noquestions', 'quickpractice'),
        new moodle_url('/course/view.php', ['id' => $id]));
}

$usesections = course_format_uses_sections($course->format);
$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';
if ($usesections) {
    $table->head  = [get_string('sectionname', 'format_' . $course->format), get_string('name')];
    $table->align = ['center', 'left'];
} else {
    $table->head  = [get_string('name')];
    $table->align = ['left'];
}

$modinfo = get_fast_modinfo($course);
$currentsection = '';
foreach ($instances as $instance) {
    $cm = $modinfo->cms[$instance->coursemodule];
    $row = [];
    if ($usesections) {
        $sectionname = get_section_name($course, $instance->section);
        if ($sectionname !== $currentsection) {
            $currentsection = $sectionname;
            $row[] = $sectionname;
        } else {
            $row[] = '';
        }
    }
    $url = new moodle_url('/mod/quickpractice/view.php', ['id' => $cm->id]);
    $row[] = html_writer::link($url, format_string($instance->name));
    $table->data[] = $row;
}
echo html_writer::table($table);
echo $OUTPUT->footer();
