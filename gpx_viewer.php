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
$PAGE->requires->css('/mod/treasurehunt/css/participant_progress.css');
if (!empty($treasurehunt->showboard)) {
    $PAGE->requires->js_call_amd('mod_treasurehunt/participant_progress', 'init_page');
}
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
echo html_writer::start_tag('div', ['class' => 'track-refresh-control']);
echo html_writer::tag('label', get_string('trackviewerrefreshinterval', 'treasurehunt'), [
    'class' => 'form-label mb-0',
    'for' => 'refreshtracks',
]);
echo html_writer::empty_tag('input', [
    'type' => 'range',
    'id' => 'refreshtracks',
    'class' => 'form-range',
    'min' => 10,
    'max' => 70,
    'step' => 10,
    'value' => $refreshtracksinterval,
    'list' => 'refreshtrackssteps',
    'aria-valuetext' => get_string('trackviewerrefreshseconds', 'treasurehunt', $refreshtracksinterval),
]);
echo html_writer::tag(
    'datalist',
    html_writer::tag('option', '', ['value' => 10]) .
    html_writer::tag('option', '', ['value' => 20]) .
    html_writer::tag('option', '', ['value' => 30]) .
    html_writer::tag('option', '', ['value' => 40]) .
    html_writer::tag('option', '', ['value' => 50]) .
    html_writer::tag('option', '', ['value' => 60]) .
    html_writer::tag('option', '', ['value' => 70]),
    ['id' => 'refreshtrackssteps']
);
echo html_writer::tag('output', get_string('trackviewerrefreshseconds', 'treasurehunt', $refreshtracksinterval), [
    'id' => 'refreshtracksvalue',
    'for' => 'refreshtracks',
    'aria-live' => 'polite',
]);
echo html_writer::tag(
    'div',
    html_writer::tag('span', '10') . html_writer::tag('span', '20') .
    html_writer::tag('span', '30') . html_writer::tag('span', '40') .
    html_writer::tag('span', '50') . html_writer::tag('span', '60') .
    html_writer::tag('span', get_string('trackviewerrefreshdisabled', 'treasurehunt')),
    ['class' => 'track-refresh-ticks', 'aria-hidden' => 'true']
);
echo html_writer::tag('span', '', [
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
if (!empty($treasurehunt->showboard)) {
    echo html_writer::div('', 'th-participant-progress th-participant-progress-inline', [
        'id' => 'th-participant-progress-trackviewer',
        'data-th-treasurehuntid' => $treasurehunt->id,
        'data-th-roadid' => 0,
        'aria-label' => get_string('participantprogress', 'treasurehunt'),
    ]);
}
echo html_writer::tag('div', '', [
    'id' => 'mapgpx',
    'role' => 'region',
    'aria-label' => get_string('trackviewer', 'treasurehunt'),
]);
echo html_writer::end_tag('div');
echo html_writer::end_tag('section');
echo html_writer::tag('div', '', ['id' => 'info']);

echo $output->footer();
