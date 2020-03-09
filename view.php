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
 * This file is the entry point to the treasurehunt module. All pages are rendered from here
 *
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @author Adrian Rodriguez <huorwhisp@gmail.com>
 * @author Juan Pablo de Castro <jpdecastro@tel.uva.es>* @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once("$CFG->dirroot/mod/treasurehunt/locallib.php");
require_once("$CFG->dirroot/mod/treasurehunt/renderable.php");
require_once($CFG->libdir . '/formslib.php');

global $USER;
$id = required_param('id', PARAM_INT);
$userid = optional_param('userid', $USER->id, PARAM_INT);
$groupid = optional_param('groupid', -1, PARAM_INT);

list ($course, $cm) = get_course_and_cm_from_cmid($id, 'treasurehunt');
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability('mod/treasurehunt:view', $context);

$treasurehunt = $DB->get_record('treasurehunt', array('id' => $cm->instance), '*', MUST_EXIST);
$PAGE->set_activity_record($treasurehunt);

$event = \mod_treasurehunt\event\course_module_viewed::create(array(
    'objectid' => $PAGE->cm->instance,
            'context' => $PAGE->context,
        ));
$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $treasurehunt);
$event->trigger();

// Print the page header.
$url = new moodle_url('/mod/treasurehunt/view.php', array('id' => $cm->id));
if ($userid != $USER->id) {
    $url->param('userid', $userid);
}
$output = $PAGE->get_renderer('mod_treasurehunt');
$PAGE->set_url($url);
$PAGE->set_title($course->shortname . ': ' . format_string($treasurehunt->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('standard');
/*
* Other things you may want to set - remove if not needed.
* $PAGE->set_cacheable(false);
* $PAGE->set_focuscontrol('some-html-id');
* $PAGE->add_body_class('treasurehunt-'.$somevar);
*/
$completion = new completion_info($course);
$completion->set_module_viewed($cm);
$PAGE->requires->jquery();
$PAGE->requires->js_call_amd('mod_treasurehunt/dyndates', 'init', ['span[data-timestamp']);
//$PAGE->requires->css('/mod/treasurehunt/css/view.css');

echo $output->header();
echo $output->heading(
    html_writer::empty_tag('img', array('src' => treasurehunt_get_proper_icon($treasurehunt, time()))) . ' ' .
    format_string($treasurehunt->name) . 
    $output->help_icon('modulename', 'treasurehunt'));
 
// Warn about the geolocation with no HTTPS.
if ($treasurehunt->playwithoutmoving == false &&
        (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == 'off')) {
            treasurehunt_notify_error(get_string('warnunsecuregeolocation', 'treasurehunt'));
}
// Conditions to show the intro can change to look for own settings or whatever.
if (treasurehunt_view_intro($treasurehunt)) {
    echo $output->box(format_module_intro('treasurehunt', $treasurehunt, $cm->id), 'generalbox mod_introbox',
            'treasurehuntintro');
}

$viewusersattemptscap = has_capability('mod/treasurehunt:viewusershistoricalattempts', $context);
echo treasurehunt_view_info($treasurehunt, $course->id);
if ((has_capability('mod/treasurehunt:play', $context, null, false) && time() > $treasurehunt->allowattemptsfromdate
        && $userid == $USER->id && $groupid == -1) || (has_capability('mod/treasurehunt:play', $context, $userid, false)
        && $viewusersattemptscap && $groupid == -1 && $userid != $USER->id)
        || (count(get_enrolled_users($context, 'mod/treasurehunt:play', $groupid)) && $viewusersattemptscap
        && $treasurehunt->groupmode)) {
    try {
        $teacherreview = true;
        $username = '';
        if ($groupid != -1) {
            $username = groups_get_group_name($groupid);
            $params = treasurehunt_get_group_road($groupid, $treasurehunt->id, $username);
        } else {
            if ($userid == $USER->id) {
                $teacherreview = false;
            } else {
                $username = treasurehunt_get_user_fullname_from_id($userid);
            }
            $params = treasurehunt_get_user_group_and_road($userid, $treasurehunt, $cm->id, $teacherreview, $username);
            if ($userid == $USER->id) {
                if ($params->groupid) {
                    $username = groups_get_group_name($params->groupid);
                } else {
                    $username = treasurehunt_get_user_fullname_from_id($userid);
                }
            }
        }
        echo treasurehunt_view_user_attempt_history($treasurehunt, $params->groupid, $userid, $params->roadid,
                $cm->id, $username, $teacherreview);
    } catch (Exception $e) {
        treasurehunt_notify_error($e->getMessage());
    }
}
if (has_capability('mod/treasurehunt:managetreasurehunt', $context)
        || has_capability('mod/treasurehunt:viewusershistoricalattempts', $context)
        || time() > $treasurehunt->allowattemptsfromdate) {
    echo treasurehunt_view_users_progress_table($cm, $course->id, $context);
}

// Finish the page.
echo $output->footer();
