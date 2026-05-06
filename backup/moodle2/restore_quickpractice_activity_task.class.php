<?php
/**
 * Restore task for mod_quickpractice
 *
 * @package    mod_quickpractice
 * @copyright  2026 刘兵（青海师范大学附属中学信息科技教师）robinpcy@126.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quickpractice/backup/moodle2/restore_quickpractice_stepslib.php');

class restore_quickpractice_activity_task extends restore_activity_task {

    protected function define_my_settings() {
        // No specific settings.
    }

    protected function define_my_steps() {
        $this->add_step(new restore_quickpractice_activity_structure_step(
            'quickpractice_structure', 'quickpractice.xml'));
    }

    public static function define_decode_contents() {
        $contents = [];
        $contents[] = new restore_decode_content('quickpractice', ['intro'], 'quickpractice');
        return $contents;
    }

    public static function define_decode_rules() {
        $rules = [];
        $rules[] = new restore_decode_rule(
            'QUICKPRACTICEVIEWBYID',
            '/mod/quickpractice/view.php?id=$1',
            'course_module');
        return $rules;
    }

    public static function define_restore_log_rules() {
        $rules = [];
        $rules[] = new restore_log_rule('quickpractice', 'view',
            'view.php?id={course_module}', '{quickpractice}');
        $rules[] = new restore_log_rule('quickpractice', 'attempt',
            'attempt.php?id={course_module}', '{quickpractice}');
        return $rules;
    }

    public static function define_restore_log_rules_for_course() {
        $rules = [];
        return $rules;
    }
}
