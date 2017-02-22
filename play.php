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
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Replace treasurehunt with the name of your module and remove this line.

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once("$CFG->dirroot/mod/treasurehunt/locallib.php");
require_once ($CFG->libdir . '/formslib.php');

global $USER;

$id = required_param('id', PARAM_INT);
list ($course, $cm) = get_course_and_cm_from_cmid($id, 'treasurehunt');
$treasurehunt = $DB->get_record('treasurehunt', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/treasurehunt:play', $context, null, false);

//Poner evento de edicion o algo asi
/* $event = \mod_treasurehunt\event\course_module_viewed::create(array(
  'objectid' => $PAGE->cm->instance,
  'context' => $PAGE->context,
  ));
  $event->add_record_snapshot('course', $PAGE->course);
  $event->add_record_snapshot($PAGE->cm->modname, $treasurehunt);
  $event->trigger(); */

// Print the page header.

$PAGE->set_url('/mod/treasurehunt/play.php', array('id' => $cm->id));
$PAGE->set_title(format_string($treasurehunt->name));
$PAGE->set_heading(format_string($course->fullname));
//$PAGE->set_pagelayout('standard');
if ($treasurehunt->allowattemptsfromdate > time()) {
    $returnurl = new moodle_url('/mod/treasurehunt/view.php', array('id' => $id));
    print_error('treasurehuntnotavailable', 'treasurehunt', $returnurl, userdate($treasurehunt->allowattemptsfromdate));
}
// Get last timestamp 
$user = treasurehunt_get_user_group_and_road($USER->id, $treasurehunt, $cm->id);
list($lastattempttimestamp, $lastroadtimestamp) = treasurehunt_get_last_timestamps($USER->id, $user->groupid,
        $user->roadid);
$gameupdatetime = treasurehunt_get_setting_game_update_time() * 1000;

$PAGE->requires->js('/mod/treasurehunt/js/jquery2/jquery-2.1.4.min.js');
$PAGE->requires->js_call_amd('mod_treasurehunt/play', 'playtreasurehunt',
        array(
    treasurehunt_get_strings_play(),
    $cm->id, $cm->instance,
    intval($treasurehunt->playwithoutmoving),
    intval($treasurehunt->groupmode),
    $lastattempttimestamp, $lastroadtimestamp, $gameupdatetime));
$PAGE->requires->css('/mod/treasurehunt/css/jquerymobile.css');

$PAGE->set_pagelayout('embedded');

/*
 * Other things you may want to set - remove if not needed.
 * $PAGE->set_cacheable(false);
 * $PAGE->set_focuscontrol('some-html-id');
 * $PAGE->add_body_class('treasurehunt-'.$somevar);
 */
$output = $PAGE->get_renderer('mod_treasurehunt');
// Output starts here.
echo $output->header();
// Replace the following lines with you own code.
echo treasurehunt_view_play_page($treasurehunt, $cm->id);
// Finish the page.
echo $output->footer();
