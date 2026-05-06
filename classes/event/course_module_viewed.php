<?php
/**
 * Event: course_module_viewed
 *
 * @package    mod_quickpractice
 * @copyright  2026 刘兵（青海师范大学附属中学信息科技教师）robinpcy@126.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quickpractice\event;

defined('MOODLE_INTERNAL') || die();

class course_module_viewed extends \core\event\course_module_viewed {

    protected function init() {
        $this->data['objecttable'] = 'quickpractice';
        parent::init();
    }

    public static function get_name() {
        return get_string('pluginname', 'mod_quickpractice');
    }
}
