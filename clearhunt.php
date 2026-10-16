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
 *
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @author Adrian Rodriguez <huorwhisp@gmail.com>
 * @author Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("locallib.php");

$confirm = optional_param('confirm', false, PARAM_BOOL);
$scope = optional_param('scope', '', PARAM_ALPHA);
$roadid = optional_param('roadid', 0, PARAM_INT);
$participantid = optional_param('participantid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid(required_param('id', PARAM_INTEGER), 'treasurehunt');
$PAGE->set_url('/mod/treasurehunt/clearhunt.php', ['id' => $cm->id]);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
$treasurehuntid = $cm->instance;
$return = new moodle_url('/mod/treasurehunt/view.php', ['id' => $cm->id]);
$listurl = new moodle_url('/mod/treasurehunt/clearhunt.php', ['id' => $cm->id]);

if (!has_capability('mod/treasurehunt:managetreasurehunt', $context)) {
    redirect($return);
}

$treasurehunt = $DB->get_record('treasurehunt', ['id' => $treasurehuntid], '*', MUST_EXIST);
$PAGE->set_activity_record($treasurehunt);
$progressdata = treasurehunt_get_list_participants_and_attempts_in_roads($cm, $course->id, $context);
$clearableroads = treasurehunt_get_clearable_road_ids($treasurehuntid);
$road = null;
$participant = null;
if ($scope !== '') {
    if (!in_array($scope, ['all', 'road', 'user', 'group'], true)) {
        throw new moodle_exception('invalidparameter');
    }
    if (($scope === 'all' && ($roadid || $participantid)) || ($scope === 'road' && $participantid)) {
        throw new moodle_exception('invalidparameter');
    }
    if ($scope !== 'all') {
        foreach ($progressdata[0] as $listedroad) {
            if ((int)$listedroad->id === $roadid) {
                $road = $listedroad;
                break;
            }
        }
        if (!$road) {
            throw new moodle_exception('invalidparameter');
        }
    }
    if ($scope === 'user' || $scope === 'group') {
        if (($scope === 'group') !== (bool)$treasurehunt->groupmode || $participantid <= 0) {
            throw new moodle_exception('invalidparameter');
        }
        foreach ($road->userlist as $listedparticipant) {
            if ((int)$listedparticipant->id === $participantid && count($listedparticipant->ratings)) {
                $participant = $listedparticipant;
                break;
            }
        }
        if (!$participant) {
            throw new moodle_exception('invalidparameter');
        }
    }
}

if (data_submitted()) {
    require_sesskey();
    if (!$confirm || $scope === '') {
        throw new moodle_exception('invalidparameter');
    }
    if ($scope === 'all') {
        treasurehunt_clear_activities($treasurehuntid);
    } else {
        treasurehunt_clear_road_activities($treasurehuntid, $roadid, $participantid, $scope === 'group');
    }
    redirect($listurl, get_string('clearactivitydone', 'treasurehunt'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$clearhunt = get_string('cleartreasurehunt', 'treasurehunt');
$PAGE->navbar->add(get_string('modulename', 'treasurehunt'));
$PAGE->navbar->add($clearhunt);
$PAGE->set_title($clearhunt);
$PAGE->set_heading($COURSE->fullname);
$PAGE->activityheader->set_description('');
echo $OUTPUT->header();
echo $OUTPUT->heading($clearhunt);
if ($scope !== '') {
    $label = '';
    if ($scope === 'road') {
        $label = format_string($road->name);
    } else if ($participant) {
        $label = $scope === 'group' ? format_string($participant->name) : fullname($participant);
    }
    if ($scope === 'all') {
        $msg = get_string('cleartreasurehuntconfirm', 'treasurehunt', count(treasurehunt_get_all_attempts($treasurehuntid)));
    } else {
        $params = ['roadid' => $roadid];
        $condition = '';
        if ($participant) {
            $condition = $scope === 'group' ? ' AND a.groupid = :participantid'
                : ' AND a.groupid = 0 AND a.userid = :participantid';
            $params['participantid'] = $participantid;
        }
        $count = $DB->count_records_sql(
            'SELECT COUNT(a.id) FROM {treasurehunt_attempts} a '
            . 'JOIN {treasurehunt_stages} s ON s.id = a.stageid '
            . 'WHERE s.roadid = :roadid' . $condition,
            $params
        );
        $msg = get_string('clearactivityconfirm' . $scope, 'treasurehunt', (object)[
            'name' => $label,
            'road' => format_string($road->name),
            'count' => $count,
        ]);
    }
    $confirmurl = new moodle_url($listurl, [
        'confirm' => 1,
        'scope' => $scope,
        'roadid' => $roadid,
        'participantid' => $participantid,
    ]);
    echo $OUTPUT->confirm($msg, $confirmurl, $listurl);
} else {
    echo treasurehunt_view_users_progress_table($cm, $course->id, $context, $progressdata, $clearableroads);
    if ($clearableroads) {
        echo $OUTPUT->single_button(
            new moodle_url($listurl, ['scope' => 'all']),
            get_string('clearallactivity', 'treasurehunt'),
            'get',
            ['class' => 'mt-3']
        );
    }
    echo html_writer::div(html_writer::link($return, get_string('back')), 'mt-3');
}
echo $OUTPUT->footer();
