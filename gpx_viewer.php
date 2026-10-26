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
$usersids = treasurehunt_get_users_with_tracks($treasurehunt->id);
$users = [];
$userrecords = $DB->get_records_list('user', 'id', $usersids);
$roads = $DB->get_records('treasurehunt_roads', ['treasurehuntid' => $treasurehunt->id], 'id', 'id,groupid,groupingid');
$roadstages = [];
$stages = $DB->get_records_sql(
    'SELECT s.id, s.roadid, s.position, s.geom FROM {treasurehunt_stages} s ' .
    'JOIN {treasurehunt_roads} r ON r.id = s.roadid WHERE r.treasurehuntid = ? ORDER BY s.roadid,s.position',
    [$treasurehunt->id]
);
foreach ($stages as $stage) {
    $roadstages[$stage->roadid][$stage->id] = $stage->id;
}
$attemptsbyactor = [];
$latestroadbyactor = [];
$historicgroupbyuser = [];
$attemptrecords = $DB->get_records_sql(
    'SELECT a.id,a.userid,a.groupid,a.timecreated,a.success,a.type,a.penalty,a.geometrysolved,' .
    'a.questionsolved,a.activitysolved,s.id AS stageid,s.roadid ' .
    'FROM {treasurehunt_attempts} a JOIN {treasurehunt_stages} s ON s.id = a.stageid ' .
    'JOIN {treasurehunt_roads} r ON r.id = s.roadid ' .
    'WHERE r.treasurehuntid = ? ORDER BY a.timecreated,a.id',
    [$treasurehunt->id]
);
foreach ($attemptrecords as $attempt) {
    if ($treasurehunt->groupmode && !$attempt->groupid) {
        continue;
    }
    $actor = $treasurehunt->groupmode ? 'g' . $attempt->groupid : 'u' . $attempt->userid;
    $key = $actor . 'r' . $attempt->roadid;
    $attemptsbyactor[$key][] = [
        'time' => (int)$attempt->timecreated,
        'stageid' => (int)$attempt->stageid,
        'success' => (bool)$attempt->success && in_array($attempt->type, ['location', 'qr'], true),
        'penalty' => (bool)$attempt->penalty,
        'geometrysolved' => (bool)$attempt->geometrysolved,
        'questionsolved' => (bool)$attempt->questionsolved,
        'activitysolved' => (bool)$attempt->activitysolved,
    ];
    $latestroadbyactor[$actor] = (int)$attempt->roadid;
    if ($treasurehunt->groupmode) {
        $historicgroupbyuser[$attempt->userid] = (int)$attempt->groupid;
    }
}
$historicgroups = $historicgroupbyuser ? $DB->get_records_list('groups', 'id', array_unique($historicgroupbyuser)) : [];
$teamsbyuser = [];
if (!empty($treasurehunt->groupmode) && $userrecords) {
    [$usersql, $userparams] = $DB->get_in_or_equal(array_keys($userrecords), SQL_PARAMS_NAMED, 'trackeduser');
    $teamrecords = $DB->get_records_sql(
        'SELECT DISTINCT gm.id, gm.userid, g.id AS groupid, g.name AS groupname, r.id AS roadid ' .
        'FROM {groups_members} gm JOIN {groups} g ON g.id = gm.groupid ' .
        'JOIN {groupings_groups} gg ON gg.groupid = g.id ' .
        'JOIN {treasurehunt_roads} r ON r.groupingid = gg.groupingid ' .
        "WHERE r.treasurehuntid = :huntid AND gm.userid $usersql ORDER BY g.id",
        ['huntid' => $treasurehunt->id] + $userparams
    );
    foreach ($teamrecords as $teamrecord) {
        $teamsbyuser[$teamrecord->userid] ??= $teamrecord;
    }
}
$roadsbyuser = [];
if (empty($treasurehunt->groupmode) && $userrecords) {
    if (count($roads) === 1 && empty(current($roads)->groupid)) {
        $defaultroadid = (int)current($roads)->id;
    } else {
        [$usersql, $userparams] = $DB->get_in_or_equal(array_keys($userrecords), SQL_PARAMS_NAMED, 'roaduser');
        $roadassignments = $DB->get_records_sql(
            'SELECT gm.id, gm.userid, r.id AS roadid FROM {groups_members} gm ' .
            'JOIN {treasurehunt_roads} r ON r.groupid = gm.groupid ' .
            "WHERE r.treasurehuntid = :huntid AND gm.userid $usersql",
            ['huntid' => $treasurehunt->id] + $userparams
        );
        foreach ($roadassignments as $assignment) {
            $roadsbyuser[$assignment->userid] ??= (int)$assignment->roadid;
        }
    }
}
foreach ($userrecords as $userrecord) {
    $user = new stdClass();
    $user->id = $userrecord->id;
    $user->fullname = fullname($userrecord);
    $user->pic = $output->user_picture($userrecord);
    $user->pictureurl = '';
    if (empty($treasurehunt->groupmode) && !empty($userrecord->picture) && user_picture::allow_view($userrecord->id)) {
        $picture = new user_picture($userrecord);
        $picture->size = 48;
        $picture->link = false;
        $user->pictureurl = $picture->get_url($PAGE)->out(false);
    }
    $team = $teamsbyuser[$userrecord->id] ?? null;
    $user->teamid = $historicgroupbyuser[$userrecord->id] ?? ($team ? (int)$team->groupid : 0);
    $user->teamname = isset($historicgroups[$user->teamid]) ? format_string($historicgroups[$user->teamid]->name) :
        ($team ? format_string($team->groupname) : '');
    $actor = $treasurehunt->groupmode ? 'g' . $user->teamid : 'u' . $user->id;
    $user->roadid = $latestroadbyactor[$actor] ?? ($team ? (int)$team->roadid :
        ($roadsbyuser[$user->id] ?? ($defaultroadid ?? 0)));
    $user->totalstages = count($roadstages[$user->roadid] ?? []);
    $user->attempts = $attemptsbyactor[$actor . 'r' . $user->roadid] ?? [];
    $iconname = $user->teamid ? $user->teamname : $user->fullname;
    $nameparts = preg_split('/\s+/u', trim($iconname), -1, PREG_SPLIT_NO_EMPTY);
    $user->initials = $nameparts ? core_text::strtoupper(core_text::substr($nameparts[0], 0, 1) .
        (count($nameparts) > 1 ? core_text::substr(end($nameparts), 0, 1) : '')) : '?';
    $users[] = $user;
}
$stagebounds = null;
// The viewer needs only the overall extent, not the stage geometries or clues.
$visitcoordinates = function ($coordinates) use (&$visitcoordinates, &$stagebounds): void {
    if (!is_array($coordinates)) {
        return;
    }
    if (count($coordinates) >= 2 && is_numeric($coordinates[0]) && is_numeric($coordinates[1])) {
        $x = (float)$coordinates[0];
        $y = (float)$coordinates[1];
        if (is_finite($x) && is_finite($y) && $x >= -180 && $x <= 180 && $y >= -90 && $y <= 90) {
            $stagebounds = $stagebounds === null ? [$x, $y, $x, $y] : [
                min($stagebounds[0], $x), min($stagebounds[1], $y),
                max($stagebounds[2], $x), max($stagebounds[3], $y),
            ];
        }
        return;
    }
    foreach ($coordinates as $part) {
        $visitcoordinates($part);
    }
};
foreach ($stages as $stage) {
    if (!empty($stage->geom)) {
        $geometry = treasurehunt_wkt_to_object($stage->geom);
        if ($geometry !== null) {
            $visitcoordinates($geometry->getCoordinates());
        }
    }
}
$refreshtracksinterval = treasurehunt_tracks_need_refresh($treasurehunt, $cm, $context) ? 60 : 0;
$custommapping = treasurehunt_get_custommappingconfig($treasurehunt, $context);

$PAGE->requires->js_call_amd(
    'mod_treasurehunt/viewgpx',
    'creategpxviewer',
    [$id, $treasurehunt->id, 'global', $custommapping, $refreshtracksinterval]
);

echo $output->header();
// Pass large data $users via Javascript.
echo "\n<script>\n";
echo "window.users_param = " . json_encode($users, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ";\n";
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
echo html_writer::tag(
    'button',
    html_writer::tag('span', '', ['class' => 'fa fa-play', 'aria-hidden' => 'true']) . ' ' .
    html_writer::tag('span', get_string('trackviewerreplay', 'treasurehunt'), ['id' => 'track-replay-label']),
    ['id' => 'track-replay', 'type' => 'button', 'class' => 'btn btn-outline-primary btn-sm',
        'disabled' => empty($users) ? 'disabled' : null]
);
echo html_writer::start_tag('div', ['class' => 'track-refresh-control']);
echo html_writer::tag('label', get_string('trackviewerrefreshinterval', 'treasurehunt'), [
    'class' => 'form-label mb-0',
    'for' => 'refreshtracks',
    'id' => 'refreshtrackslabel',
]);
echo html_writer::empty_tag('input', [
    'type' => 'range',
    'id' => 'refreshtracks',
    'class' => 'form-range',
    'min' => 10,
    'max' => 70,
    'step' => 10,
    'value' => $refreshtracksinterval ?: 70,
    'list' => 'refreshtrackssteps',
    'aria-valuetext' => $refreshtracksinterval ?
        get_string('trackviewerrefreshseconds', 'treasurehunt', $refreshtracksinterval) :
        get_string('trackviewerrefreshdisabled', 'treasurehunt'),
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
echo html_writer::tag('output', $refreshtracksinterval ?
    get_string('trackviewerrefreshseconds', 'treasurehunt', $refreshtracksinterval) :
    get_string('trackviewerrefreshdisabled', 'treasurehunt'), [
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
echo html_writer::div('', 'th-participant-progress th-participant-progress-inline th-replay-timeline', [
    'id' => 'th-participant-progress-trackviewer',
    'aria-label' => get_string('trackviewertimeline', 'treasurehunt'),
]);
echo html_writer::tag('div', '', [
    'id' => 'mapgpx',
    'role' => 'region',
    'aria-label' => get_string('trackviewer', 'treasurehunt'),
    'data-stage-bounds' => $stagebounds === null ? '' : json_encode($stagebounds),
]);
echo html_writer::end_tag('div');
echo html_writer::end_tag('section');
echo html_writer::tag('div', '', ['id' => 'info']);

echo $output->footer();
