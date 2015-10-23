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

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once (dirname(__FILE__).'/gisconverter.php');
require_once ($CFG->libdir.'/formslib.php');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or

if ($id) {
    $cm         = get_coursemodule_from_id('scavengerhunt', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $scavengerhunt  = $DB->get_record('scavengerhunt', array('id' => $cm->instance), '*', MUST_EXIST);
}  else {
    error('You must specify a course_module ID or an instance ID');
}
/*Esto lo utilizo para comprobar las geometrÃƒÂ­as 
$var = $DB->get_record('scavengerhunt', array('id' => 1), 'id,name,astext(geom) as WKT', MUST_EXIST);
$decoder = new gisconverter\WKT();
$var2= $decoder->geomFromText($var->wkt);
$var3= $var2->toGeoJSON();
print_object($var3);
print_object($var);
print_r($DB->get_dbfamily());
die();*/
require_login($course, true, $cm);

$event = \mod_scavengerhunt\event\course_module_viewed::create(array(
    'objectid' => $PAGE->cm->instance,
    'context' => $PAGE->context,
));
$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $scavengerhunt);
$event->trigger();

// Print the page header.

$PAGE->set_url('/mod/scavengerhunt/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($scavengerhunt->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('standard');

/*
 * Other things you may want to set - remove if not needed.
 * $PAGE->set_cacheable(false);
 * $PAGE->set_focuscontrol('some-html-id');
 * $PAGE->add_body_class('scavengerhunt-'.$somevar);
 */
$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->jquery_plugin('ui-css');
$PAGE->requires->js_call_amd('mod_scavengerhunt/init','init');
$PAGE->requires->css('/mod/scavengerhunt/css/ol.css');

// Output starts here.
echo $OUTPUT->header();
// Conditions to show the intro can change to look for own settings or whatever.
if ($scavengerhunt->intro) {
    echo $OUTPUT->box(format_module_intro('scavengerhunt', $scavengerhunt, $cm->id), 'generalbox mod_introbox', 'scavengerhuntintro');
}
// Replace the following lines with you own code.
echo $OUTPUT->heading('Nada');
//echo $OUTPUT->render_from_template('mod_scavengerhunt/recipe',null);
echo $OUTPUT->box(null, null, 'controlPanel');
echo $OUTPUT->box(null, null, 'riddleListPanel');
echo $OUTPUT->container_start(null, 'map_global');
echo $OUTPUT->box(null, null, 'map');
echo $OUTPUT->container_end();



// Finish the page.
echo $OUTPUT->footer();
