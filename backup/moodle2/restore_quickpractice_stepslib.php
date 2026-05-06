<?php
/**
 * Restore steps for mod_quickpractice
 *
 * @package    mod_quickpractice
 * @copyright  2026 刘兵（青海师范大学附属中学信息科技教师）robinpcy@126.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class restore_quickpractice_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {
        $paths   = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('quickpractice', '/activity/quickpractice');
        $paths[] = new restore_path_element('quickpractice_question',
            '/activity/quickpractice/questions/question');

        if ($userinfo) {
            $paths[] = new restore_path_element('quickpractice_attempt',
                '/activity/quickpractice/attempts/attempt');
            $paths[] = new restore_path_element('quickpractice_response',
                '/activity/quickpractice/attempts/attempt/responses/response');
        }

        return $this->prepare_activity_structure($paths);
    }

    protected function process_quickpractice($data) {
        global $DB;
        $data               = (object) $data;
        $data->course       = $this->get_courseid();
        $data->timecreated  = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $newid = $DB->insert_record('quickpractice', $data);
        $this->apply_activity_instance($newid);
    }

    protected function process_quickpractice_question($data) {
        global $DB;
        $data                   = (object) $data;
        $oldid                  = $data->id;
        $data->quickpracticeid  = $this->get_new_parentid('quickpractice');
        $newid = $DB->insert_record('quickpractice_questions', $data);
        $this->set_mapping('quickpractice_question', $oldid, $newid);
    }

    protected function process_quickpractice_attempt($data) {
        global $DB;
        $data                   = (object) $data;
        $oldid                  = $data->id;
        $data->quickpracticeid  = $this->get_new_parentid('quickpractice');
        $data->userid           = $this->get_mappingid('user', $data->userid);
        $newid = $DB->insert_record('quickpractice_attempts', $data);
        $this->set_mapping('quickpractice_attempt', $oldid, $newid);
    }

    protected function process_quickpractice_response($data) {
        global $DB;
        $data             = (object) $data;
        $data->attemptid  = $this->get_new_parentid('quickpractice_attempt');
        $data->questionid = $this->get_mappingid('quickpractice_question', $data->questionid);
        $DB->insert_record('quickpractice_responses', $data);
    }

    protected function after_execute() {
        $this->add_related_files('mod_quickpractice', 'intro', null);
    }
}
