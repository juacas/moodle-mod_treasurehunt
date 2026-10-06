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
 * @copyright  Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @author Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once("$CFG->dirroot/mod/treasurehunt/locallib.php");

/** @global moodle_database $DB Database.*/ // phpcs:ignore
global $DB;

$id = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($id, 'treasurehunt');
$treasurehunt = $DB->get_record('treasurehunt', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

require_capability('mod/treasurehunt:viewusershistoricalattempts', $context);
// Print the page header.
$url = new moodle_url('/mod/treasurehunt/gpx_viewer.php', ['id' => $cm->id]);
/** @var renderer_base $output */
$output = $PAGE->get_renderer('mod_treasurehunt');

$PAGE->set_url($url);
$PAGE->set_title($course->shortname . ': ' . format_string($treasurehunt->name) .
    ' : ' . get_string('trackviewer', 'treasurehunt'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');
$PAGE->activityheader->disable();
$PAGE->requires->jquery();
$PAGE->requires->css('/mod/treasurehunt/css/introjs.css');
$PAGE->requires->css('/mod/treasurehunt/css/ol.css');
$PAGE->requires->css('/mod/treasurehunt/css/ol3-layerswitcher.css');
$usersids = treasurehunt_get_users_with_tracks($treasurehunt->id);
$users = [];
$userrecords = $DB->get_records_list('user', 'id', $usersids);
foreach ($userrecords as $userrecord) {
    $user = new stdClass();
    $user->id = $userrecord->id;
    $user->fullname = fullname($userrecord);
    $user->pic = $output->user_picture($userrecord);
    $users[] = $user;
}
$refreshtracksinterval = 60;
$custommapping = treasurehunt_get_custommappingconfig($treasurehunt, $context);

$PAGE->requires->js_call_amd(
    'mod_treasurehunt/viewgpx',
    'creategpxviewer',
    [$id, $treasurehunt->id, 'global', $custommapping, $refreshtracksinterval]
);

echo $output->header();
// Pass large data $users via Javascript.
echo "\n<script>\n";
echo "users_param = " . json_encode($users) . ";\n";
echo "</script>\n";
$downloadurl = new moodle_url('/mod/treasurehunt/gpx.php', [
    'id' => $id,
    'userid' => implode(',', array_keys($userrecords)),
]);
echo html_writer::start_tag('section', ['id' => 'treasurehunt-gpx', 'class' => 'card treasurehunt-gpx-viewer']);
echo html_writer::start_tag('div', ['class' => 'card-header d-flex flex-wrap align-items-center gap-2']);
echo html_writer::tag('h2', format_string($treasurehunt->name), [
    'class' => 'h5 mb-0 me-auto',
]);
echo html_writer::start_tag('div', ['class' => 'form-check form-switch mb-0']);
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'id' => 'refreshtracks',
    'class' => 'form-check-input',
    'value' => 'refresh',
]);
echo html_writer::tag('label', get_string('trackviewerrefreshtracks', 'treasurehunt', $refreshtracksinterval), [
    'class' => 'form-check-label',
    'for' => 'refreshtracks',
]);
echo ' ' . html_writer::tag('span', '', [
    'id' => 'timecircle',
    'class' => 'badge rounded-pill text-bg-secondary',
    'style' => 'display: none;',
    'aria-live' => 'off',
]);
echo html_writer::end_tag('div');
echo html_writer::link($downloadurl, get_string('trackviewerdownloadgpx', 'treasurehunt'), [
    'class' => 'btn btn-outline-primary btn-sm',
]);
echo html_writer::end_tag('div');
echo html_writer::start_tag('div', ['class' => 'card-body p-0']);
echo html_writer::tag('div', '', [
    'id' => 'mapgpx',
    'role' => 'region',
    'aria-label' => get_string('trackviewer', 'treasurehunt'),
]);
echo html_writer::end_tag('div');
echo html_writer::end_tag('section');
echo html_writer::tag('div', '', ['id' => 'info']);

echo $output->footer();
