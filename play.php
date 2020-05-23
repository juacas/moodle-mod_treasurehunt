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
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 *
 * @package mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro
 *            <jpdecastro@tel.uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Replace treasurehunt with the name of your module and remove this line.
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once("$CFG->dirroot/mod/treasurehunt/locallib.php");
require_once($CFG->libdir . '/formslib.php');

global $USER;

$id = required_param('id', PARAM_INT);
list($course, $cm) = get_course_and_cm_from_cmid($id, 'treasurehunt');
$treasurehunt = $DB->get_record('treasurehunt', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/treasurehunt:play', $context, null, false);
// Check availability
if ($treasurehunt->allowattemptsfromdate > time() && !has_capability('mod/treasurehunt:manage', $context)) {
    $returnurl = new moodle_url('/mod/treasurehunt/view.php', array('id' => $id));
    print_error('treasurehuntnotavailable', 'treasurehunt', $returnurl, userdate($treasurehunt->allowattemptsfromdate));
}
// Log event.
require_once('classes/event/player_entered.php');
$event = \mod_treasurehunt\event\player_entered::create(array(
    'objectid' => $id,
    'context' => $context,
));
$event->add_record_snapshot("treasurehunt", $treasurehunt);
$event->trigger();

// Print the page header.
$PAGE->set_url('/mod/treasurehunt/play.php', array('id' => $cm->id));
$PAGE->set_title(format_string($treasurehunt->name));
$PAGE->set_heading(format_string($course->fullname));

// Get last timestamp.
$user = treasurehunt_get_user_group_and_road($USER->id, $treasurehunt, $cm->id);
list($lastattempttimestamp, $lastroadtimestamp) = treasurehunt_get_last_timestamps($USER->id, $user->groupid, $user->roadid);
// Instance selected player renderable.
$playerstyle = $treasurehunt->playerstyle;
$renderableclass = "treasurehunt_play_page_$playerstyle";
$renderable = new $renderableclass($treasurehunt, $cm);

$output = $PAGE->get_renderer('mod_treasurehunt');
$renderable->lastattempttimestamp = $lastattempttimestamp;
$renderable->lastroadtimestamp = $lastroadtimestamp;
$renderable->gameupdatetime = treasurehunt_get_setting_game_update_time() * 1000;
$user = new stdClass();
$user->id = $USER->id;
$user->fullname = fullname($USER);
$user->pic = $output->user_picture($USER, array('link' => false));
$renderable->set_user($user);

$custommapping = treasurehunt_get_custommappingconfig($treasurehunt, $context);
$renderable->set_custommapping($custommapping);
echo $output->render($renderable);
