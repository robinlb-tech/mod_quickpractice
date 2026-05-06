<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * @package    mod_quickpractice
 * @copyright  2026 刘兵（青海师范大学附属中学信息科技教师）robinpcy@126.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// ============================================================
// Activity module API functions (required by Moodle)
// ============================================================

/**
 * Add a new instance of quickpractice.
 */
function quickpractice_add_instance($data, $mform = null) {
    global $DB, $USER;
    $data->timecreated  = time();
    $data->timemodified = time();
    $data->createdby    = $USER->id;
    $data->id = $DB->insert_record('quickpractice', $data);
    quickpractice_grade_item_update($data);
    return $data->id;
}

/**
 * Update an existing instance of quickpractice.
 */
function quickpractice_update_instance($data, $mform = null) {
    global $DB;
    $data->timemodified = time();
    $data->id = $data->instance;
    $DB->update_record('quickpractice', $data);
    quickpractice_grade_item_update($data);
    return true;
}

/**
 * Delete an instance of quickpractice.
 */
function quickpractice_delete_instance($id) {
    global $DB;
    if (!$quickpractice = $DB->get_record('quickpractice', ['id' => $id])) {
        return false;
    }
    // Cascade delete.
    $attemptids = $DB->get_fieldset_select('quickpractice_attempts', 'id', 'quickpracticeid = ?', [$id]);
    if ($attemptids) {
        list($in, $params) = $DB->get_in_or_equal($attemptids);
        $DB->delete_records_select('quickpractice_responses', "attemptid $in", $params);
    }
    $DB->delete_records('quickpractice_attempts',   ['quickpracticeid' => $id]);
    $DB->delete_records('quickpractice_questions',  ['quickpracticeid' => $id]);
    $DB->delete_records('quickpractice_reports',    ['quickpracticeid' => $id]);
    $DB->delete_records('quickpractice', ['id' => $id]);
    quickpractice_grade_item_delete($DB->get_record('course_modules', ['instance' => $id]));
    return true;
}

/**
 * Returns the information on whether the module supports a feature.
 */
function quickpractice_supports($feature) {
    switch ($feature) {
        case FEATURE_GRADE_HAS_GRADE:        return true;
        case FEATURE_GRADE_OUTCOMES:         return true;
        case FEATURE_BACKUP_MOODLE2:         return true;
        case FEATURE_SHOW_DESCRIPTION:       return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_COMPLETION_HAS_RULES:   return true;
        case FEATURE_MOD_INTRO:              return true;
        case FEATURE_MOD_PURPOSE:            return MOD_PURPOSE_ASSESSMENT;
        default: return null;
    }
}

// ============================================================
// Grading functions
// ============================================================

function quickpractice_grade_item_update($quickpractice, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    $item = [
        'itemname'  => clean_param($quickpractice->name, PARAM_NOTAGS),
        'gradetype' => GRADE_TYPE_VALUE,
        'grademax'  => 100,
        'grademin'  => 0,
    ];
    if ($grades === 'reset') {
        $item['reset'] = true;
        $grades        = null;
    }
    return grade_update('mod/quickpractice', $quickpractice->course, 'mod', 'quickpractice',
        $quickpractice->id, 0, $grades, $item);
}

function quickpractice_grade_item_delete($quickpractice) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    return grade_update('mod/quickpractice', $quickpractice->course, 'mod', 'quickpractice',
        $quickpractice->id, 0, null, ['deleted' => 1]);
}

function quickpractice_update_grades($quickpractice, $userid = 0) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    $sql = "SELECT a.userid,
                   MAX(a.score / NULLIF(a.maxscore, 0) * 100) AS finalgrade
              FROM {quickpractice_attempts} a
             WHERE a.quickpracticeid = :qpid
               AND a.state = 'finished'
               " . ($userid ? "AND a.userid = :uid" : "") . "
          GROUP BY a.userid";
    $params = ['qpid' => $quickpractice->id];
    if ($userid) {
        $params['uid'] = $userid;
    }
    $records = $DB->get_records_sql($sql, $params);

    $grades = [];
    foreach ($records as $r) {
        $grades[$r->userid] = (object)[
            'userid'    => $r->userid,
            'rawgrade'  => round($r->finalgrade, 2),
        ];
    }
    quickpractice_grade_item_update($quickpractice, $grades ?: null);
}

// ============================================================
// Navigation
// ============================================================

function quickpractice_extend_navigation($navref, $course, $module, $cm) {
    $context = context_module::instance($cm->id);
    if (has_capability('mod/quickpractice:viewreport', $context)) {
        $url = new moodle_url('/mod/quickpractice/report.php', ['id' => $cm->id]);
        $navref->add(get_string('teacherdashboard', 'quickpractice'), $url,
            navigation_node::TYPE_SETTING);
    }
    if (has_capability('mod/quickpractice:managequestions', $context)) {
        $url = new moodle_url('/mod/quickpractice/questionbank.php', ['id' => $cm->id]);
        $navref->add(get_string('questionbank', 'quickpractice'), $url,
            navigation_node::TYPE_SETTING);
    }
}

// ============================================================
// Completion rules
// ============================================================

/**
 * Returns completion rules description strings for display.
 * Kept simple - no custom completion class needed for base install.
 */
function quickpractice_get_completion_active_rule_descriptions($cm) {
    // Return empty array; custom completion can be added in future versions.
    return [];
}

// ============================================================
// Search / Logging helper
// ============================================================

function quickpractice_get_coursemodule_info($coursemodule) {
    global $DB;
    $info = new cached_cm_info();
    $record = $DB->get_record('quickpractice', ['id' => $coursemodule->instance],
        'id, name, intro, introformat, timeopen, timeclose');
    if (!$record) {
        return $info;
    }
    $info->name = $record->name;
    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('quickpractice', $record, $coursemodule->id, false);
    }
    return $info;
}

// ============================================================
// Utility: extract class name from user profile
// e.g. "高一(3)班 张三" -> "高一(3)班"
// ============================================================

function quickpractice_extract_classname($user) {
    // Strategy 1: from department field
    if (!empty($user->department)) {
        return trim($user->department);
    }
    // Strategy 2: parse Chinese class pattern from fullname
    // 格式示例: "高一3班-张三", "2024级3班 李四"
    $fullname = fullname($user);
    if (preg_match('/^([^\-\s]+[班级组])\s*[\-\s]/u', $fullname, $m)) {
        return $m[1];
    }
    // Strategy 3: from institution
    if (!empty($user->institution)) {
        return trim($user->institution);
    }
    return get_string('unknownclass', 'quickpractice');
}
