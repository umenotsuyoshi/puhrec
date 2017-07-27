<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Library of interface functions and constants for module puhrec
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 *
 * All the puhrec specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package    mod_puhrec
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Example constant, you probably want to remove this :-)
 */
define('puhrec_ULTIMATE_ANSWER', 42);

/* Moodle core API */

/**
 * Returns the information on whether the module supports a feature
 *
 * See {@link plugin_supports()} for more info.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function puhrec_supports($feature) {

    switch($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the puhrec into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $puhrec Submitted data from the form in mod_form.php
 * @param mod_puhrec_mod_form $mform The form instance itself (if needed)
 * @return int The id of the newly inserted puhrec record
 */
function puhrec_add_instance(stdClass $puhrec, mod_puhrec_mod_form $mform = null) {
    global $DB;

    $puhrec->timecreated = time();

    // You may have to add extra stuff in here.

    $puhrec->id = $DB->insert_record('puhrec', $puhrec);

    puhrec_grade_item_update($puhrec);

    return $puhrec->id;
}

/**
 * Updates an instance of the puhrec in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param stdClass $puhrec An object from the form in mod_form.php
 * @param mod_puhrec_mod_form $mform The form instance itself (if needed)
 * @return boolean Success/Fail
 */
function puhrec_update_instance(stdClass $puhrec, mod_puhrec_mod_form $mform = null) {
    global $DB;

    $puhrec->timemodified = time();
    $puhrec->id = $puhrec->instance;

    // You may have to add extra stuff in here.

    $result = $DB->update_record('puhrec', $puhrec);

    puhrec_grade_item_update($puhrec);

    return $result;
}

/**
 * Removes an instance of the puhrec from the database
 * 活動削除時に呼ばれて活動に関するレコード、ファイルを削除する。
 * 
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function puhrec_delete_instance($id) {
    global  $CFG, $DB;
    if (! $puhrec = $DB->get_record('puhrec', array('id'=>$id))) {
        return false;
    }
    $result = true;
    $fs = get_file_storage();
    if ($cm = get_coursemodule_from_instance('puhrec', $puhrec->id)) {
        $context = context_module::instance($cm->id);
        $fs->delete_area_files($context->id);
    }
    
    if (! $DB->delete_records('puhrec_messages', array('puhrecid'=>$puhrec->id))) {
        $result = false;
    }
    
    if (! $DB->delete_records('puhrec_audios', array('puhrecid'=>$puhrec->id))) {
        $result = false;
    }
    
    if (! $DB->delete_records('event', array('modulename'=>'puhrec', 'instance'=>$puhrec->id))) {
        $result = false;
    }
    
    if (! $DB->delete_records('puhrec', array('id'=>$puhrec->id))) {
        $result = false;
    }
    $mod = $DB->get_field('modules','id',array('name'=>'puhrec'));
    
    puhrec_grade_item_delete($puhrec);
    
    return $result;
    
}
/**
 * Returns a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 *
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param stdClass $course The course record
 * @param stdClass $user The user record
 * @param cm_info|stdClass $mod The course module info object or record
 * @param stdClass $puhrec The puhrec instance record
 * @return stdClass|null
 */
function puhrec_user_outline($course, $user, $mod, $puhrec) {
    global $CFG;
    
    require_once("$CFG->libdir/gradelib.php");
    
    $grade = puhrec_get_user_grades($puhrec, $user->id);
    if ($grade > -1) {
        $result = new stdClass();
        $result->info = get_string('grade').': '.$grade;
        $result->time = '';
        return $result;
    }
    else {
        return null;
    }}

/**
 * Prints a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * It is supposed to echo directly without returning a value.
 *
 * @param stdClass $course the current course record
 * @param stdClass $user the record of the user we are generating report for
 * @param cm_info $mod course module info
 * @param stdClass $puhrec the module instance record
 */
function puhrec_user_complete($course, $user, $mod, $puhrec) {
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in puhrec activities and print it out.
 *
 * @param stdClass $course The course record
 * @param bool $viewfullnames Should we display full names
 * @param int $timestart Print activity since this timestamp
 * @return boolean True if anything was printed, otherwise false
 */
function puhrec_print_recent_activity($course, $viewfullnames, $timestart) {
    return false;
}

/**
 * Prepares the recent activity data
 *
 * This callback function is supposed to populate the passed array with
 * custom activity records. These records are then rendered into HTML via
 * {@link puhrec_print_recent_mod_activity()}.
 *
 * Returns void, it adds items into $activities and increases $index.
 *
 * @param array $activities sequentially indexed array of objects with added 'cmid' property
 * @param int $index the index in the $activities to use for the next record
 * @param int $timestart append activity since this time
 * @param int $courseid the id of the course we produce the report for
 * @param int $cmid course module id
 * @param int $userid check for a particular user's activity only, defaults to 0 (all users)
 * @param int $groupid check for a particular group's activity only, defaults to 0 (all groups)
 */
function puhrec_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0) {
}

/**
 * Prints single activity item prepared by {@link puhrec_get_recent_mod_activity()}
 *
 * @param stdClass $activity activity record with added 'cmid' property
 * @param int $courseid the id of the course we produce the report for
 * @param bool $detail print detailed report
 * @param array $modnames as returned by {@link get_module_types_names()}
 * @param bool $viewfullnames display users' full names
 */
function puhrec_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
}

/**
 * Function to be run periodically according to the moodle cron
 *
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * Note that this has been deprecated in favour of scheduled task API.
 *
 * @return boolean
 */
function puhrec_cron () {
    return true;
}

/**
 * Returns all other caps used in the module
 *
 * For example, this could be array('moodle/site:accessallgroups') if the
 * module uses that capability.
 *
 * @return array
 */
function puhrec_get_extra_capabilities() {
    return array();
}

/* Gradebook API */

/**
 * Is a given scale used by the instance of puhrec?
 *
 * This function returns if a scale is being used by one puhrec
 * if it has support for grading and scales.
 *
 * @param int $puhrecid ID of an instance of this module
 * @param int $scaleid ID of the scale
 * @return bool true if the scale is used by the given puhrec instance
 */
function puhrec_scale_used($puhrecid, $scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('puhrec', array('id' => $puhrecid, 'grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Checks if scale is being used by any instance of puhrec.
 *
 * This is used to find out if scale used anywhere.
 *
 * @param int $scaleid ID of the scale
 * @return boolean true if the scale is used by any puhrec instance
 */
function puhrec_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('puhrec', array('grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Creates or updates grade item for the given puhrec instance
 *
 * Needed by {@link grade_update_mod_grades()}.
 *
 * @param stdClass $puhrec instance object with extra cmidnumber and modname property
 * @param unknown $grades
 * @return void
 */
function puhrec_grade_item_update(stdClass $puhrec, $grades=NULL) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    $item = array();
    $item = array();
    $item['itemname'] = clean_param($puhrec->name, PARAM_NOTAGS);
    $item['gradetype'] = GRADE_TYPE_VALUE;
    $item['grademax']  = $puhrec->grade;
    $item['grademin']  = 0;
    // gradelib.php:61 
    // function grade_update($source, $courseid, $itemtype, $itemmodule, $iteminstance, $itemnumber, $grades=NULL, $itemdetails=NULL) {
    $update_ret = grade_update('mod/puhrec', $puhrec->course, 'mod', 'puhrec', $puhrec->id, 0, $grades, $item);
}

/**
 * Delete grade item for given puhrec instance
 * 活動削除時に呼ばれて活動に関する評定関連のデータを削除する。
 * puhrec_delete_instanceから呼ばれる
 * 
 * @param stdClass $puhrec instance object
 * @return grade_item
 */
function puhrec_grade_item_delete($puhrec) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('mod/puhrec', $puhrec->course, 'mod', 'puhrec',
            $puhrec->id, 0, null, array('deleted' => 1));
}
/**
 * Return grade for given user or all users.
 * 
 * @param int $puhrecid id of puhrec
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function puhrec_get_user_grades($puhrec, $userid=0) {
    global $CFG, $DB;

    if ($userid) {
        $user = "AND u.id = :userid";
        $params = array('userid'=>$userid);
    } else {
        $user = "";
    }
    $params['nid'] = $puhrec->id;

    $sql = "SELECT u.id, u.id AS userid, m.grade AS rawgrade, m.comments AS feedback, m.commentsformat AS feedbackformat, m.commentedby AS usermodified, m.timestamp AS dategraded
    FROM {user} u, {puhrec_messages} m
    WHERE u.id = m.userid AND m.puhrecid = :nid
    $user";

    return $DB->get_records_sql($sql, $params);
}
/**
 * Update puhrec grades in the gradebook
 *
 * Needed by {@link grade_update_mod_grades()}.
 * 
 * @param stdClass $puhrec instance object with extra cmidnumber and modname property
 * @param int $userid update grade of specific user only, 0 means all participants
 */
function puhrec_update_grades(stdClass $puhrec, $userid = 0) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');
    if ($puhrec->grade == 0) {
        puhrec_grade_item_update($puhrec);
    }
    else if ($grades = puhrec_get_user_grades($puhrec, $userid)) {
        foreach($grades as $k=>$v) {
            if ($v->rawgrade == -1) {
                $grades[$k]->rawgrade = null;
            }
        }
        puhrec_grade_item_update($puhrec, $grades);
    }
    else {
        puhrec_grade_item_update($puhrec);
    }
}

/* File API */

/**
 * Returns the lists of all browsable file areas within the given module context
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array of [(string)filearea] => (string)description
 */
function puhrec_get_file_areas($course, $cm, $context) {
    return array();
}

/**
 * File browsing support for puhrec file areas
 *
 * @package mod_puhrec
 * @category files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info instance or null if not found
 */
function puhrec_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    return null;
}

/**
 * Serves the files from the puhrec file areas
 *
 * @package mod_puhrec
 * @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the puhrec's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 */
function puhrec_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options=array()) {
    global $DB, $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }
    require_login($course, true, $cm);
   
    if (!$puhrec = $DB->get_record('puhrec', array('id'=>$cm->instance))) {
        send_file_not_found();
    }
    require_capability('mod/puhrec:view', $context);
    
    $fullpath = "/{$context->id}/mod_puhrec/$filearea/".implode('/', $args);
    
    $fs = get_file_storage();
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        send_file_not_found();
    }
    
    //session_get_instance()->write_close(); // unlock session during fileserving
    \core\session\manager::write_close(); // Unlock session during file serving. umeno
    
    send_stored_file($file, 60*60, 0, true);
    
    
}

/* Navigation API */

/**
 * Extends the global navigation tree by adding puhrec nodes if there is a relevant content
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref An object representing the navigation tree node of the puhrec module instance
 * @param stdClass $course current course record
 * @param stdClass $module current puhrec instance record
 * @param cm_info $cm course module information
 */
function puhrec_extend_navigation(navigation_node $navref, stdClass $course, stdClass $module, cm_info $cm) {
    // TODO Delete this function and its docblock, or implement it.
}

/**
 * Extends the settings navigation with the puhrec settings
 *
 * This function is called when the context for the page is a puhrec module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav complete settings navigation tree
 * @param navigation_node $puhrecnode puhrec administration node
 */
function puhrec_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $puhrecnode=null) {
    // TODO Delete this function and its docblock, or implement it.
}

