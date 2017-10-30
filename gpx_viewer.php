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
 * Track viewer
 *
 * @package   mod_treasurehunt
 * @copyright  Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @author Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');
require_once("$CFG->dirroot/mod/treasurehunt/locallib.php");
// @var $DB database_manager Database.
global $DB;
global $USER;
$id = required_param('id', PARAM_INT);
list ($course, $cm) = get_course_and_cm_from_cmid($id, 'treasurehunt');
$treasurehunt = $DB->get_record('treasurehunt', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

require_capability('mod/treasurehunt:managetreasurehunt', $context);
// Print the page header.
$url = new moodle_url('/mod/treasurehunt/gpx_viewer.php', array('id' => $cm->id));

$output = $PAGE->get_renderer('mod_treasurehunt');

$PAGE->set_url($url);
$PAGE->set_title($course->shortname . ': ' . format_string($treasurehunt->name) .
                    ' : ' . get_string('trackviewer', 'treasurehunt'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('standard');
$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->jquery_plugin('ui-css');
$PAGE->requires->css('/mod/treasurehunt/css/introjs.css');
$PAGE->requires->css('/mod/treasurehunt/css/ol.css');
$PAGE->requires->css('/mod/treasurehunt/css/ol3-layerswitcher.css');
$PAGE->requires->css('/mod/treasurehunt/css/treasure.css');
$usersids = treasurehunt_get_users_with_tracks($treasurehunt->id);
$users = array();
$userrecords = $DB->get_records_list('user', 'id', $usersids);
foreach ($userrecords as $userrecord) {
    $user = new stdClass();
    $user->id = $userrecord->id;
    $user->fullname = fullname($userrecord);
    $user->pic = $output->user_picture($userrecord);
    $users[] = $user;
}
$custommapping = treasurehunt_get_custommappingconfig($treasurehunt, $context);
$PAGE->requires->js_call_amd('mod_treasurehunt/viewgpx', 'creategpxviewer',
        array($id, $treasurehunt->id, $users, $custommapping));
echo $output->header();
echo $output->heading(format_string($treasurehunt->name));
echo $OUTPUT->container_start("treasurehunt-gpx", "treasurehunt-gpx");
echo $OUTPUT->box($OUTPUT->help_icon('edition', 'treasurehunt', ''), 'invisible', 'controlpanel');
echo $OUTPUT->box('', null, 'mapgpx');
echo $OUTPUT->container_end();
echo $OUTPUT->box('', null, 'info');


echo $output->footer();
