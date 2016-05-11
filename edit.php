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
 * Prints a particular instance of scavengerhunt
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_scavengerhunt
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Replace scavengerhunt with the name of your module and remove this line.

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once("$CFG->dirroot/mod/scavengerhunt/locallib.php");
require_once ($CFG->libdir . '/formslib.php');

GLOBAL $USER;

$id = required_param('id', PARAM_INT);
list ($course, $cm) = get_course_and_cm_from_cmid($id, 'scavengerhunt');
$scavengerhunt = $DB->get_record('scavengerhunt', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

require_capability('mod/scavengerhunt:view', $context);
require_capability('mod/scavengerhunt:getscavengerhunt', $context);
require_capability('mod/scavengerhunt:managescavenger', $context);

//Poner evento de edicion o algo asi
/* $event = \mod_scavengerhunt\event\course_module_viewed::create(array(
  'objectid' => $PAGE->cm->instance,
  'context' => $PAGE->context,
  ));
  $event->add_record_snapshot('course', $PAGE->course);
  $event->add_record_snapshot($PAGE->cm->modname, $scavengerhunt);
  $event->trigger(); */

// Print the page header.

$PAGE->set_url('/mod/scavengerhunt/edit.php', array('id' => $cm->id));
$PAGE->set_title(format_string($scavengerhunt->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('standard');

// Si no hay ningún camino redirijo para crearlo.
if(getTotalRoads($scavengerhunt->id)==0){
    $roadurl = new moodle_url('/mod/scavengerhunt/editroad.php', array('cmid' => $id));
    redirect($roadurl);
}
/*
 * Other things you may want to set - remove if not needed.
 * $PAGE->set_cacheable(false);
 * $PAGE->set_focuscontrol('some-html-id');
 * $PAGE->add_body_class('scavengerhunt-'.$somevar);
 */
if (!$lock = isLockScavengerhunt($cm->instance,$USER->id)) {
    $idLock = renewLockScavengerhunt($cm->instance,$USER->id);
    $PAGE->requires->js_call_amd('mod_scavengerhunt/renewlock', 'renewLockScavengerhunt', array($cm->instance, $idLock));
    $PAGE->requires->jquery();
    $PAGE->requires->jquery_plugin('ui');
    $PAGE->requires->jquery_plugin('ui-css');
    $PAGE->requires->js_call_amd('mod_scavengerhunt/edit', 'editScavengerhunt', array($id, $cm->instance, get_strings_edit(), $idLock));
    $PAGE->requires->css('/mod/scavengerhunt/css/ol.css');
}
// Output starts here.
echo $OUTPUT->header();
// Replace the following lines with you own code.
if ($lock) {
    $returnurl = new moodle_url('/mod/scavengerhunt/view.php', array('id' => $id));
    print_error('scavengerhuntislocked', 'scavengerhunt', $returnurl,get_username_blocking_edition($scavengerhunt->id));
} else {
    echo $OUTPUT->heading(format_string($scavengerhunt->name));
    // Conditions to show the intro can change to look for own settings or whatever.
    if ($scavengerhunt->intro) {
        echo $OUTPUT->box(format_module_intro('scavengerhunt', $scavengerhunt, $cm->id), 'generalbox mod_introbox', 'scavengerhuntintro');
    }
    echo $OUTPUT->container_start("scavengerhunt_editor");
    echo $OUTPUT->box(null, null, 'controlPanel');
    echo $OUTPUT->container_start(null, 'riddleListPanel_global');
    echo $OUTPUT->box(null, null, 'riddleListPanel');
    echo $OUTPUT->container_end();
    echo $OUTPUT->container_start(null, 'map_global');
    echo $OUTPUT->box(null, null, 'map');
    echo $OUTPUT->container_end();
    echo $OUTPUT->box(null, null, 'roadListPanel');
    echo $OUTPUT->container_end();
}
// Finish the page.
echo $OUTPUT->footer();
