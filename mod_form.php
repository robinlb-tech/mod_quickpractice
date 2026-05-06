<?php
/**
 * Activity settings form for mod_quickpractice
 *
 * @package    mod_quickpractice
 * @copyright  2026 刘兵（青海师范大学附属中学信息科技教师）robinpcy@126.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_quickpractice_mod_form extends moodleform_mod {

    public function definition() {
        global $CFG;
        $mform = $this->_form;

        // ── 基础信息 ──────────────────────────────────────
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name', 'quickpractice'),
            ['size' => '64', 'maxlength' => '255']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements();

        // ── 时间设置 ──────────────────────────────────────
        $mform->addElement('header', 'timing', get_string('timing', 'quiz'));

        $mform->addElement('date_time_selector', 'timeopen', get_string('timeopen', 'quickpractice'),
            ['optional' => true]);
        $mform->addHelpButton('timeopen', 'timeopen', 'quickpractice');

        $mform->addElement('date_time_selector', 'timeclose', get_string('timeclose', 'quickpractice'),
            ['optional' => true]);
        $mform->addHelpButton('timeclose', 'timeclose', 'quickpractice');

        $mform->addElement('duration', 'timelimit', get_string('timelimit', 'quickpractice'),
            ['optional' => true, 'defaultunit' => 60]);
        $mform->addHelpButton('timelimit', 'timelimit', 'quickpractice');

        // ── 作答设置 ──────────────────────────────────────
        $mform->addElement('header', 'attemptssettings', get_string('attemptsallowed', 'quickpractice'));

        $attempts = [0 => get_string('unlimited')];
        for ($i = 1; $i <= 10; $i++) {
            $attempts[$i] = $i;
        }
        $mform->addElement('select', 'maxattempts', get_string('maxattempts', 'quickpractice'), $attempts);
        $mform->setDefault('maxattempts', 1);
        $mform->addHelpButton('maxattempts', 'maxattempts', 'quickpractice');

        $mform->addElement('selectyesno', 'shuffle', get_string('shuffle', 'quickpractice'));
        $mform->setDefault('shuffle', 0);

        $grademethods = [
            1 => get_string('grademethod_highest', 'quickpractice'),
            2 => get_string('grademethod_last',    'quickpractice'),
            3 => get_string('grademethod_average', 'quickpractice'),
        ];
        $mform->addElement('select', 'grademethod', get_string('grademethod', 'quickpractice'), $grademethods);
        $mform->setDefault('grademethod', 1);

        // ── 反馈设置 ──────────────────────────────────────
        $mform->addElement('header', 'feedbacksettings', get_string('showfeedback', 'quickpractice'));

        $mform->addElement('selectyesno', 'showfeedback', get_string('showfeedback', 'quickpractice'));
        $mform->setDefault('showfeedback', 1);

        $mform->addElement('selectyesno', 'showranking', get_string('showranking', 'quickpractice'));
        $mform->setDefault('showranking', 1);

        $mform->addElement('text', 'passscore', get_string('passscore', 'quickpractice'),
            ['size' => 10]);
        $mform->setType('passscore', PARAM_FLOAT);
        $mform->setDefault('passscore', 60);
        $mform->addRule('passscore', null, 'numeric', null, 'client');

        // ── 标准评分 ──────────────────────────────────────
        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ($data['timeopen'] && $data['timeclose'] && $data['timeopen'] > $data['timeclose']) {
            $errors['timeclose'] = get_string('closebeforeopen', 'quiz');
        }
        if ($data['passscore'] < 0 || $data['passscore'] > 100) {
            $errors['passscore'] = get_string('err_numeric', 'form');
        }
        return $errors;
    }
}
