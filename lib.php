<?php
// This file is part of Treasurehunt for Moodle
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
 * Library of functions for the treasurehunt module.
 *
 * This contains functions that are called also from outside the treasurehunt module
 *
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @copyright 2017 onwards Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/calendar/lib.php');

/* Moodle core API */

/**
 * Returns the information on whether the module supports a feature
 *
 * See {@link plugin_supports()} for more info.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function treasurehunt_supports($feature) {

    switch ($feature) {
        case FEATURE_GROUPS:
            return false;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the treasurehunt into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $treasurehunt Submitted data from the form in mod_form.php
 * @param mod_treasurehunt_mod_form $mform The form instance itself (if needed)
 * @return int The id of the newly inserted treasurehunt record
 */
function treasurehunt_add_instance(stdClass $treasurehunt, mod_treasurehunt_mod_form $mform = null) {
    global $DB;
    $timenow = time();
    $treasurehunt->timecreated = $timenow;

    // You may have to add extra stuff in here.

    $treasurehunt->id = $DB->insert_record('treasurehunt', $treasurehunt);
    if ($mform !== null) { // This indicates it is a manual creation. Do not create items when restoring backups.
        treasurehunt_create_default_items($treasurehunt);
    }
    treasurehunt_grade_item_update($treasurehunt);
    treasurehunt_update_events($treasurehunt);
    return $treasurehunt->id;
}

/**
 * Updates an instance of the treasurehunt in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param stdClass $treasurehunt An object from the form in mod_form.php
 * @param mod_treasurehunt_mod_form $mform The form instance itself (if needed)
 * @return boolean Success/Fail
 */
function treasurehunt_update_instance(stdClass $treasurehunt, mod_treasurehunt_mod_form $mform = null) {
    global $DB;

    // Get the current value, so we can see what changed.
    $oldtreasurehunt = $DB->get_record('treasurehunt', array('id' => $treasurehunt->instance));
    // Update the database.
    $treasurehunt->timemodified = time();
    $treasurehunt->id = $treasurehunt->instance;
    $result = $DB->update_record('treasurehunt', $treasurehunt);
    if (($oldtreasurehunt->grade != $treasurehunt->grade && $treasurehunt->grade > 0)
            || $oldtreasurehunt->grademethod != $treasurehunt->grademethod
            || $oldtreasurehunt->gradepenlocation != $treasurehunt->gradepenlocation
            || $oldtreasurehunt->gradepenanswer != $treasurehunt->gradepenanswer
            || $oldtreasurehunt->groupmode != $treasurehunt->groupmode) {
        treasurehunt_update_grades($treasurehunt);
    }
    treasurehunt_grade_item_update($treasurehunt);

    treasurehunt_update_events($treasurehunt);

    return $result;
}

/**
 * Removes an instance of the treasurehunt from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 * @global moodle_database $DB
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function treasurehunt_delete_instance($id) {
    global $DB;
    $treasurehunt = $DB->get_record('treasurehunt', array('id' => $id), MUST_EXIST);
    // Delete any dependent records here.
    $DB->delete_records('treasurehunt', array('id' => $treasurehunt->id));
    $roads = $DB->get_records('treasurehunt_roads', array('treasurehuntid' => $treasurehunt->id));
    foreach ($roads as $road) {
        $stages = $DB->get_records_sql('SELECT id FROM {treasurehunt_stages} WHERE roadid = ?'
                , array($road->id));
        foreach ($stages as $stage) {
            $DB->delete_records_select('treasurehunt_attempts', 'stageid = ?', array($stage->id));
            $DB->delete_records_select('treasurehunt_answers', 'stageid = ?', array($stage->id));
        }
        $DB->delete_records_select('treasurehunt_stages', 'roadid = ?', array($road->id));
    }
    $DB->delete_records('treasurehunt_roads', array('treasurehuntid' => $treasurehunt->id));
    $DB->delete_records('treasurehunt_track', array('treasurehuntid' => $treasurehunt->id));

    $DB->delete_records('treasurehunt_locks', array('treasurehuntid' => $treasurehunt->id));
    treasurehunt_grade_item_delete($treasurehunt);

    $events = $DB->get_records('event', array('modulename' => 'treasurehunt', 'instance' => $treasurehunt->id));
    foreach ($events as $event) {
        $event = calendar_event::load($event);
        $event->delete();
    }

    return true;
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
 * @param stdClass $treasurehunt The treasurehunt instance record
 * @return stdClass|null
 */
function treasurehunt_user_outline($course, $user, $mod, $treasurehunt) {
    return null;
}

/**
 * Prints a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * It is supposed to echo directly without returning a value.
 * TODO
 * 
 * @param stdClass $course the current course record
 * @param stdClass $user the record of the user we are generating report for
 * @param cm_info $mod course module info
 * @param stdClass $treasurehunt the module instance record
 */
function treasurehunt_user_complete($course, $user, $mod, $treasurehunt) {
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in treasurehunt activities and print it out.
 *
 * @param stdClass $course The course record
 * @param bool $viewfullnames Should we display full names
 * @param int $timestart Print activity since this timestamp
 * @return boolean True if anything was printed, otherwise false
 */
function treasurehunt_print_recent_activity($course, $viewfullnames, $timestart) {
    return false;
}

/**
 * Prepares the recent activity data
 *
 * This callback function is supposed to populate the passed array with
 * custom activity records. These records are then rendered into HTML via
 * {@link treasurehunt_print_recent_mod_activity()}.
 * TODO
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
function treasurehunt_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid = 0, $groupid = 0) {

}

/**
 * Prints single activity item prepared by {@link treasurehunt_get_recent_mod_activity()}
 * TODO
 * @param stdClass $activity activity record with added 'cmid' property
 * @param int $courseid the id of the course we produce the report for
 * @param bool $detail print detailed report
 * @param array $modnames as returned by {@link get_module_types_names()}
 * @param bool $viewfullnames display users' full names
 */
function treasurehunt_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {

}

/**
 * Add a get_coursemodule_info function in case any assignment type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info An object on information that the courses
 *                        will know about (most noticeably, an icon).
 */
function treasurehunt_get_coursemodule_info($coursemodule) {
    global $DB;

    $dbparams = array('id' => $coursemodule->instance);
    $fields = 'id, name, alwaysshowdescription, allowattemptsfromdate, intro, introformat';
    if (!$treasurehunt = $DB->get_record('treasurehunt', $dbparams, $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $treasurehunt->name;
    if ($coursemodule->showdescription) {
        if ($treasurehunt->alwaysshowdescription || time() > $treasurehunt->allowattemptsfromdate) {
            // Convert intro to html. Do not filter cached version, filters run at display time.
            $result->content = format_module_intro('treasurehunt', $treasurehunt, $coursemodule->id, false);
        }
    }
    return $result;
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
function treasurehunt_cron() {
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
function treasurehunt_get_extra_capabilities() {
    return array();
}

/* Gradebook API */

/**
 * Is a given scale used by the instance of treasurehunt?
 *
 * This function returns if a scale is being used by one treasurehunt
 * if it has support for grading and scales.
 *
 * @param int $treasurehuntid ID of an instance of this module
 * @param int $scaleid ID of the scale
 * @return bool true if the scale is used by the given treasurehunt instance
 */
function treasurehunt_scale_used($treasurehuntid, $scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('treasurehunt', array('id' => $treasurehuntid, 'grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Checks if scale is being used by any instance of treasurehunt.
 *
 * This is used to find out if scale used anywhere.
 *
 * @param int $scaleid ID of the scale
 * @return boolean true if the scale is used by any treasurehunt instance
 */
function treasurehunt_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('treasurehunt', array('grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Creates or updates grade item for the given treasurehunt instance
 *
 * Needed by {@link grade_update_mod_grades()}.
 *
 * @param stdClass $treasurehunt instance object with extra cmidnumber and modname property
 * @param bool $reset reset grades in the gradebook
 * @return void
 */
function treasurehunt_grade_item_update(stdClass $treasurehunt, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $item = array();
    $item['itemname'] = clean_param($treasurehunt->name, PARAM_NOTAGS);
    $item['idnumber'] = isset($treasurehunt->cmidnumber) ? $treasurehunt->cmidnumber : null;

    if ($treasurehunt->grade > 0) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax'] = $treasurehunt->grade;
        $item['grademin'] = 0;
    } else if ($treasurehunt->grade < 0) {
        $item['gradetype'] = GRADE_TYPE_SCALE;
        $item['scaleid'] = -$treasurehunt->grade;
    } else {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($grades === 'reset') {
        $item['reset'] = true;
        $grades = null;
    }

    grade_update('mod/treasurehunt', $treasurehunt->course, 'mod', 'treasurehunt', $treasurehunt->id, 0, $grades, $item);
}

/**
 * Delete grade item for given treasurehunt instance
 *
 * @param stdClass $treasurehunt instance object
 * @return grade_item
 */
function treasurehunt_grade_item_delete($treasurehunt) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update('mod/treasurehunt', $treasurehunt->course, 'mod', 'treasurehunt', $treasurehunt->id, 0, null, array('deleted' => 1));
}

/**
 * Update treasurehunt grades in the gradebook
 *
 * Needed by {@link grade_update_mod_grades()}.
 *
 * @param stdClass $treasurehunt instance object with extra cmidnumber and modname property
 * @param int $userid update grade of specific user only, 0 means all participants
 */
function treasurehunt_update_grades(stdClass $treasurehunt, $userid = 0, $nullifnone = true) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    if ($treasurehunt->grade == 0) {
        treasurehunt_grade_item_update($treasurehunt);
    } else if ($grades = treasurehunt_get_user_grades($treasurehunt, $userid)) {
        treasurehunt_grade_item_update($treasurehunt, $grades);
    } else if ($userid && $nullifnone) {
        $grade = new stdClass();
        $grade->userid = $userid;
        $grade->rawgrade = null;
        treasurehunt_grade_item_update($treasurehunt, $grade);
    } else {
        treasurehunt_grade_item_update($treasurehunt);
    }
}

/**
 * Return grade for given user or all users.
 *
 * @param stdClass $treasurehunt instance object with extra cmidnumber and modname property
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none. These are raw grades. They should
 * be processed with quiz_format_grade for display.
 */
function treasurehunt_get_user_grades($treasurehunt, $userid = 0) {
    global $CFG;

    require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');

    $grades = treasurehunt_calculate_user_grades($treasurehunt, $userid);
    return $grades;
}

/**
 * Serves the files from the treasurehunt file areas
 *
 * @package mod_treasurehunt
 * @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the treasurehunt's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 */
function treasurehunt_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options = array()) {
    global $DB, $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }

    require_login($course, true, $cm);
    $fileareas = array('cluetext', 'questiontext', 'answertext');
    if (!in_array($filearea, $fileareas)) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_treasurehunt/$filearea/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        send_file_not_found();
    }

    // Finally send the file.
    send_stored_file($file, null, 0, $forcedownload, $options);
}

/**
 * Extends the settings navigation with the treasurehunt settings
 *
 * This function is called when the context for the page is a treasurehunt module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav complete settings navigation tree
 * @param navigation_node $treasurehuntnode treasurehunt administration node
 */
function treasurehunt_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $treasurehuntnode = null) {

    global $PAGE;
    // We want to add these new nodes after the Edit settings node, and before the
    // Locally assigned roles node. Of course, both of those are controlled by capabilities.
    $keys = $treasurehuntnode->get_children_key_list();
    $beforekey = null;
    $i = array_search('modedit', $keys);
    if ($i === false and array_key_exists(0, $keys)) {
        $beforekey = $keys[0];
    } else if (array_key_exists($i + 1, $keys)) {
        $beforekey = $keys[$i + 1];
    }

    if (has_capability('mod/treasurehunt:managetreasurehunt', $PAGE->cm->context)) {
        $node = navigation_node::create(get_string('edittreasurehunt', 'treasurehunt'),
                new moodle_url('/mod/treasurehunt/edit.php', array('id' => $PAGE->cm->id)),
                navigation_node::TYPE_SETTING, null, 'mod_treasurehunt_edit', new pix_icon('t/edit', ''));
        $treasurehuntnode->add_node($node, $beforekey);
    }
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the treasure hunt.
 *
 * @param $mform the course reset form that is being built.
 */
function treasurehunt_reset_course_form_definition($mform) {
    $mform->addElement('header', 'treasurehuntheader', get_string('modulenameplural', 'treasurehunt'));
    $mform->addElement('advcheckbox', 'reset_treasurehunt_attempts', get_string('removealltreasurehuntattempts', 'treasurehunt'));
}

/**
 * Course reset form defaults.
 * @return array the defaults.
 */
function treasurehunt_reset_course_form_defaults($course) {
    return array('reset_treasurehunt_attempts' => 1);
}

/**
 * Removes all grades from gradebook
 *
 * @param int $courseid
 * @param string optional type
 */
function treasurehunt_reset_gradebook($courseid, $type = '') {
    global $DB;

    $treasurehunts = $DB->get_records_sql("
            SELECT t.*, cm.idnumber as cmidnumber, t.course as courseid
            FROM {modules} m
            JOIN {course_modules} cm ON m.id = cm.module
            JOIN {treasurehunt} t ON cm.instance = t.id
            WHERE m.name = 'treasurehunt' AND cm.course = ?", array($courseid));

    foreach ($treasurehunts as $treasurehunt) {
        treasurehunt_grade_item_update($treasurehunt, 'reset');
    }
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * treasure hunt attempts for course $data->courseid, if $data->reset_treasurehunt_attempts is
 * set and true.
 *
 * Also, move the treasurehunt open and close dates, if the course start date is changing.
 *
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function treasurehunt_reset_userdata($data) {
    global $DB;

    $componentstr = get_string('modulenameplural', 'treasurehunt');
    $status = array();

    // Delete attempts.
    if (!empty($data->reset_treasurehunt_attempts)) {
        $DB->delete_records_select('treasurehunt_attempts', 'stageid IN (SELECT ri.id FROM {treasurehunt} t INNER JOIN '
                . '{treasurehunt_roads} r ON t.id=r.treasurehuntid INNER JOIN '
                . '{treasurehunt_stages} ri ON r.id=ri.roadid WHERE t.course = ?)', array($data->courseid));
        $status[] = array(
            'component' => $componentstr,
            'item' => get_string('attemptsdeleted', 'treasurehunt'),
            'error' => false);

        // Remove all grades from gradebook.
        if (!empty($data->reset_gradebook_grades)) {
            treasurehunt_reset_gradebook($data->courseid);
            $status[] = array(
                'component' => $componentstr,
                'item' => get_string('gradesdeleted', 'treasurehunt'),
                'error' => false);
        }
    }

    // Updating dates - shift may be negative too.
    if ($data->timeshift) {
        shift_course_mod_dates('treasurehunt', array('allowattemptsfromdate', 'cutoffdate'), $data->timeshift, $data->courseid);

        $status[] = array(
            'component' => $componentstr,
            'item' => get_string('datechanged'),
            'error' => false);
    }

    return $status;
}

/**
 * This function updates the events associated to the treasure hunt.
 *
 * @param object $treasurehunt the treasure hunt object.
 */
function treasurehunt_update_events($treasurehunt) {
    global $DB;

    // Load the old events relating to this treasure hunt.
    $conds = array('modulename' => 'treasurehunt',
        'instance' => $treasurehunt->id);

    $oldevents = $DB->get_records('event', $conds);

    if (!empty($treasurehunt->coursemodule)) {
        $cmid = $treasurehunt->coursemodule;
    } else {
        $cmid = get_coursemodule_from_instance('treasurehunt', $treasurehunt->id, $treasurehunt->course)->id;
    }

    $event = new stdClass();
    $event->description = format_module_intro('treasurehunt', $treasurehunt, $cmid);

    $event->courseid = $treasurehunt->course;
    $event->groupid = 0;
    $event->userid = 0;
    $event->modulename = 'treasurehunt';
    $event->instance = $treasurehunt->id;
    $event->timeduration = 0;
    $event->visible = instance_is_visible('treasurehunt', $treasurehunt);

    // Separate start and end events.
    $event->timeduration = 0;
    if ($treasurehunt->allowattemptsfromdate) {
        if ($oldevent = array_shift($oldevents)) {
            $event->id = $oldevent->id;
        } else {
            unset($event->id);
        }
        $event->name = $treasurehunt->name . ' (' . get_string('treasurehuntopens', 'treasurehunt') . ')';
        $event->timestart = $treasurehunt->allowattemptsfromdate;
        $event->eventtype = 'open';
        // The method calendar_event::create will reuse a db record if the id field is set.
        calendar_event::create($event);
    }
    if ($treasurehunt->cutoffdate) {
        if ($oldevent = array_shift($oldevents)) {
            $event->id = $oldevent->id;
        } else {
            unset($event->id);
        }
        $event->name = $treasurehunt->name . ' (' . get_string('treasurehuntcloses', 'treasurehunt') . ')';
        $event->timestart = $treasurehunt->cutoffdate;
        $event->eventtype = 'close';
        calendar_event::create($event);
    }

    // Delete any leftover events.
    foreach ($oldevents as $badevent) {
        $badevent = calendar_event::load($badevent);
        $badevent->delete();
    }
}
