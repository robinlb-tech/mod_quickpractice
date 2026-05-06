<?php
/**
 * Backup steps for mod_quickpractice
 *
 * @package    mod_quickpractice
 * @copyright  2026 刘兵（青海师范大学附属中学信息科技教师）robinpcy@126.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class backup_quickpractice_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        $userinfo = $this->get_setting_value('userinfo');

        // Define root element.
        $quickpractice = new backup_nested_element('quickpractice', ['id'], [
            'name', 'intro', 'introformat', 'timeopen', 'timeclose',
            'timelimit', 'maxattempts', 'shuffle', 'showfeedback',
            'showranking', 'passscore', 'grademethod', 'questioncount',
            'timecreated', 'timemodified', 'createdby'
        ]);

        $questions = new backup_nested_element('questions');
        $question  = new backup_nested_element('question', ['id'], [
            'qtype', 'questiontext', 'questionformat', 'options', 'answers',
            'feedback', 'score', 'difficulty', 'category', 'sortorder',
            'source', 'timecreated'
        ]);

        $attempts = new backup_nested_element('attempts');
        $attempt  = new backup_nested_element('attempt', ['id'], [
            'userid', 'attemptnum', 'state', 'score', 'maxscore',
            'timecreated', 'timefinished', 'duration', 'questionorder',
            'classname', 'ipaddress'
        ]);

        $responses = new backup_nested_element('responses');
        $response  = new backup_nested_element('response', ['id'], [
            'questionid', 'response', 'score', 'maxscore', 'correct',
            'feedback', 'timeanswered', 'timetaken'
        ]);

        // Build tree.
        $quickpractice->add_child($questions);
        $questions->add_child($question);

        if ($userinfo) {
            $quickpractice->add_child($attempts);
            $attempts->add_child($attempt);
            $attempt->add_child($responses);
            $responses->add_child($response);
        }

        // Set data sources.
        $quickpractice->set_source_table('quickpractice', ['id' => backup::VAR_ACTIVITYID]);
        $question->set_source_table('quickpractice_questions', ['quickpracticeid' => backup::VAR_PARENTID]);

        if ($userinfo) {
            $attempt->set_source_table('quickpractice_attempts', ['quickpracticeid' => backup::VAR_PARENTID]);
            $response->set_source_table('quickpractice_responses', ['attemptid' => backup::VAR_PARENTID]);
            $attempt->annotate_ids('user', 'userid');
        }

        return $this->prepare_activity_structure($quickpractice);
    }
}
