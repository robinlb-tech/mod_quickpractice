<?php
/**
 * Backup task for mod_quickpractice
 *
 * @package    mod_quickpractice
 * @copyright  2026 刘兵（青海师范大学附属中学信息科技教师）robinpcy@126.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quickpractice/backup/moodle2/backup_quickpractice_stepslib.php');

class backup_quickpractice_activity_task extends backup_activity_task {

    protected function define_my_settings() {
        // No specific settings.
    }

    protected function define_my_steps() {
        $this->add_step(new backup_quickpractice_activity_structure_step(
            'quickpractice_structure', 'quickpractice.xml'));
    }

    public static function encode_content_links($content) {
        global $CFG;
        $base = preg_quote($CFG->wwwroot, '/');
        $content = preg_replace(
            "/($base\/mod\/quickpractice\/view\.php\?id=)([0-9]+)/",
            '$@QUICKPRACTICEVIEWBYID*$2@$', $content);
        return $content;
    }
}
