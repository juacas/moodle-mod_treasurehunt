<?php
// This file is part of TreasureHunt activity for Moodle
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
 * Clear the activity registered in this module instance.
 * This resets the state of the trasurehunt and allows the number of roads and stages to be
 * edited again.
 */
require_once("../../config.php");
require_once("locallib.php");

$confirm = optional_param('confirm', false, PARAM_BOOL);

$PAGE->set_url('/mod/trasurehunt/clearhunt.php');
list ($course, $cm) = get_course_and_cm_from_cmid(required_param('id', PARAM_INTEGER), 'treasurehunt');
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
$treasurehuntid = $cm->instance;
$return = new moodle_url('/mod/treasurehunt/view.php', array('id' => $cm->id));

if (!has_capability('mod/treasurehunt:managetreasurehunt', $context)) {
    redirect($return);
}

$clearhunt = get_string('cleartreasurehunt', 'treasurehunt');
$PAGE->navbar->add(get_string('modulename', 'treasurehunt'));
$PAGE->navbar->add($clearhunt);
$PAGE->set_title($clearhunt);
$PAGE->set_heading($COURSE->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading($clearhunt);

if (data_submitted() and $confirm and confirm_sesskey()) {
    treasurehunt_clear_activities($treasurehuntid);

    echo $OUTPUT->box(get_string('cleartreasurehunt_done', 'treasurehunt'));
    echo $OUTPUT->continue_button($return);
    echo $OUTPUT->footer();
    die;
} else {

    $attempts = treasurehunt_get_all_attempts($treasurehuntid);
    $count = count($attempts);
    $msg = get_string('cleartreasurehuntconfirm', 'treasurehunt', $count);
    echo $OUTPUT->confirm($msg, new moodle_url('clearhunt.php', array('confirm' => 1, 'id' => $cm->id)), $return);
    echo $OUTPUT->footer();
    die;
}

