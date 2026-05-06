<?php
/**
 * Privacy API implementation for mod_quickpractice
 *
 * @package    mod_quickpractice
 * @copyright  2026 刘兵（青海师范大学附属中学信息科技教师）robinpcy@126.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quickpractice\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\helper;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for mod_quickpractice.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection) {
        $collection->add_database_table(
            'quickpractice_attempts',
            [
                'userid'    => 'privacy:metadata:quickpractice_attempts:userid',
                'score'     => 'privacy:metadata:quickpractice_attempts:score',
                'classname' => 'privacy:metadata:quickpractice_attempts:classname',
            ],
            'privacy:metadata:quickpractice_attempts'
        );
        $collection->add_database_table(
            'quickpractice_responses',
            [
                'response' => 'privacy:metadata:quickpractice_responses:response',
            ],
            'privacy:metadata:quickpractice_responses'
        );
        return $collection;
    }

    public static function get_contexts_for_userid($userid) {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
            INNER JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {quickpractice} qp ON qp.id = cm.instance
            INNER JOIN {quickpractice_attempts} qa ON qa.quickpracticeid = qp.id AND qa.userid = :userid";
        $params = ['contextlevel' => CONTEXT_MODULE, 'modname' => 'quickpractice', 'userid' => $userid];
        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $sql = "SELECT qa.userid
                  FROM {quickpractice_attempts} qa
            INNER JOIN {course_modules} cm ON cm.instance = qa.quickpracticeid
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid]);
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        if (empty($contextlist->count())) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $cm = get_coursemodule_from_id('quickpractice', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $attempts = $DB->get_records('quickpractice_attempts',
                ['quickpracticeid' => $cm->instance, 'userid' => $userid]);
            writer::with_context($context)->export_data([], (object)['attempts' => $attempts]);
        }
    }

    public static function delete_data_for_all_users_in_context($context) {
        global $DB;
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('quickpractice', $context->instanceid);
        if (!$cm) {
            return;
        }
        $attemptids = $DB->get_fieldset_select('quickpractice_attempts', 'id',
            'quickpracticeid = ?', [$cm->instance]);
        if ($attemptids) {
            list($in, $params) = $DB->get_in_or_equal($attemptids);
            $DB->delete_records_select('quickpractice_responses', "attemptid $in", $params);
        }
        $DB->delete_records('quickpractice_attempts', ['quickpracticeid' => $cm->instance]);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (empty($contextlist->count())) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('quickpractice', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $attemptids = $DB->get_fieldset_select('quickpractice_attempts', 'id',
                'quickpracticeid = ? AND userid = ?', [$cm->instance, $userid]);
            if ($attemptids) {
                list($in, $params) = $DB->get_in_or_equal($attemptids);
                $DB->delete_records_select('quickpractice_responses', "attemptid $in", $params);
            }
            $DB->delete_records('quickpractice_attempts',
                ['quickpracticeid' => $cm->instance, 'userid' => $userid]);
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('quickpractice', $context->instanceid);
        if (!$cm) {
            return;
        }
        $userids = $userlist->get_userids();
        foreach ($userids as $userid) {
            $attemptids = $DB->get_fieldset_select('quickpractice_attempts', 'id',
                'quickpracticeid = ? AND userid = ?', [$cm->instance, $userid]);
            if ($attemptids) {
                list($in, $params) = $DB->get_in_or_equal($attemptids);
                $DB->delete_records_select('quickpractice_responses', "attemptid $in", $params);
            }
            $DB->delete_records('quickpractice_attempts',
                ['quickpracticeid' => $cm->instance, 'userid' => $userid]);
        }
    }
}
