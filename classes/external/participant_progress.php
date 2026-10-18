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
 * Read the compact participant progress display for an activity.
 *
 * @package mod_treasurehunt
 * @author Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @copyright 2026 Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_treasurehunt\external;

use cache;
use context_module;
use core_text;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;
use stdClass;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/mod/treasurehunt/externalcompatibility.php');
require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');

/**
 * Return a cached, privacy-scoped snapshot of participants in the treasure hunt.
 */
class participant_progress extends external_api {
    /** Describe the input parameters for the participant progress service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'treasurehuntid' => new external_value(PARAM_INT, 'Treasurehunt instance id'),
            'previewroadid' => new external_value(PARAM_INT, 'Teacher preview road', VALUE_DEFAULT, 0),
            'roadid' => new external_value(PARAM_INT, 'Optional road filter', VALUE_DEFAULT, 0),
        ]);
    }

    /** Describe the response structure returned by the participant progress service.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'enabled' => new external_value(PARAM_BOOL, 'Whether participant progress sharing is enabled'),
            'groupmode' => new external_value(PARAM_BOOL, 'Whether participants are represented as teams'),
            'participants' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_ALPHANUMEXT, 'Stable participant key'),
                'roadid' => new external_value(PARAM_INT, 'Assigned road id'),
                'name' => new external_value(PARAM_TEXT, 'Participant or team name'),
                'initials' => new external_value(PARAM_TEXT, 'One or two display initials'),
                'pictureurl' => new external_value(PARAM_RAW, 'Profile image URL or empty string'),
                'position' => new external_value(PARAM_INT, 'Current sequential stage position'),
                'totalstages' => new external_value(PARAM_INT, 'Number of stages on the assigned road'),
                'state' => new external_value(PARAM_ALPHA, 'failed, passed or pending'),
                'iscurrent' => new external_value(PARAM_BOOL, 'Whether this is the current user or team'),
                'teamcolor' => new external_value(PARAM_TEXT, 'Stable team color, or empty for individuals'),
            ])),
        ]);
    }

    /**
     * Get progress for all participants assigned to validated roads in this activity.
     * The expensive roster and progress queries are shared through a short-lived application cache.
     *
     * @param int $treasurehuntid Activity instance id.
     * @param int $previewroadid Teacher preview road, which does not represent a participant.
     * @param int $roadid Optional road filter for progress summaries.
     * @return array
     */
    public static function execute(int $treasurehuntid, int $previewroadid = 0, int $roadid = 0): array {
        global $DB, $PAGE, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'treasurehuntid' => $treasurehuntid,
            'previewroadid' => $previewroadid,
            'roadid' => $roadid,
        ]);
        $cm = get_coursemodule_from_instance('treasurehunt', $params['treasurehuntid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        if (
            !has_any_capability([
                'mod/treasurehunt:enterplayer',
                'mod/treasurehunt:managetreasurehunt',
                'mod/treasurehunt:viewusershistoricalattempts',
            ], $context)
        ) {
            throw new \required_capability_exception($context, 'mod/treasurehunt:enterplayer', 'nopermissions', '');
        }
        $treasurehunt = $DB->get_record('treasurehunt', ['id' => $params['treasurehuntid']], '*', MUST_EXIST);
        if (empty($treasurehunt->showboard)) {
            return ['enabled' => false, 'groupmode' => (bool)$treasurehunt->groupmode, 'participants' => []];
        }

        $roadid = $params['roadid'];
        if ($params['previewroadid']) {
            if (!has_capability('mod/treasurehunt:managetreasurehunt', $context)) {
                throw new \required_capability_exception($context, 'mod/treasurehunt:managetreasurehunt', 'nopermissions', '');
            }
            if ($roadid && $roadid !== $params['previewroadid']) {
                throw new \invalid_parameter_exception('Preview road does not match the requested road filter.');
            }
            $roadid = $params['previewroadid'];
        }

        if (
            $roadid && !$DB->record_exists('treasurehunt_roads', [
            'id' => $roadid,
            'treasurehuntid' => $treasurehunt->id,
            'validated' => 1,
            ])
        ) {
            return ['enabled' => false, 'groupmode' => (bool)$treasurehunt->groupmode, 'participants' => []];
        }

        // Teachers viewing activity summaries may not be assigned to a road.
        $currentkey = null;
        try {
            $userparams = treasurehunt_get_user_group_and_road($USER->id, $treasurehunt, $cm->id);
            $currentkey = $treasurehunt->groupmode ?
                'g' . $userparams->groupid . 'r' . $userparams->roadid :
                'u' . $USER->id . 'r' . $userparams->roadid;
        } catch (\core\session\exception $e) {
            // Activity summaries can be opened by users who are not assigned to a road.
            $currentkey = '';
        }
        $cache = cache::make('mod_treasurehunt', 'participantprogress');
        $snapshot = $cache->get((string)$params['treasurehuntid']);
        if ($snapshot === false) {
            $snapshot = self::build_snapshot($treasurehunt, $cm, $context);
            $cache->set((string)$params['treasurehuntid'], $snapshot);
        }

        $participants = [];
        foreach ($snapshot as $record) {
            if ($roadid && $record['roadid'] !== $roadid) {
                continue;
            }
            $name = $record['name'];
            $pictureurl = '';
            if (!$record['isgroup'] && !empty($record['picture'])) {
                $pictureuser = (object)$record['pictureuser'];
                if (\user_picture::allow_view((int)$pictureuser->id)) {
                    $picture = new \user_picture($pictureuser);
                    $picture->size = 48;
                    $picture->link = false;
                    $pictureurl = $picture->get_url($PAGE)->out(false);
                }
            }
            $participants[] = [
                'id' => $record['key'],
                'roadid' => $record['roadid'],
                'name' => $name,
                'initials' => self::initials($name),
                'pictureurl' => $pictureurl,
                'position' => $record['position'],
                'totalstages' => $record['totalstages'],
                'state' => $record['state'],
                'iscurrent' => $record['key'] === $currentkey,
                'teamcolor' => $record['isgroup'] ? self::team_color((int)$record['actorid']) : '',
            ];
        }

        return [
            'enabled' => true,
            'groupmode' => (bool)$treasurehunt->groupmode,
            'participants' => $participants,
        ];
    }

    /**
     * Build a compact activity snapshot using one roster pass and batched progress queries.
     *
     * @param stdClass $treasurehunt Activity record.
     * @param stdClass $cm Course module record.
     * @param context_module $context Activity context.
     * @return array
     */
    private static function build_snapshot(stdClass $treasurehunt, stdClass $cm, context_module $context): array {
        global $DB;

        $roads = $DB->get_records(
            'treasurehunt_roads',
            ['treasurehuntid' => $treasurehunt->id],
            'id',
            'id,name,groupid,groupingid,validated'
        );
        $participants = [];
        $validroads = [];
        foreach ($roads as $road) {
            if (empty($road->validated)) {
                continue;
            }
            $validroads[$road->id] = $road;
            if ($treasurehunt->groupmode) {
                if (empty($road->groupingid)) {
                    continue;
                }
                $groups = groups_get_all_groups($cm->course, 0, $road->groupingid, 'g.id,g.name');
                foreach ($groups as $group) {
                    if (!get_enrolled_users($context, 'mod/treasurehunt:enterplayer', $group->id, 'u.id')) {
                        continue;
                    }
                    $key = 'g' . $group->id . 'r' . $road->id;
                    $participants[$key] = [
                        'key' => $key,
                        'name' => format_string($group->name),
                        'isgroup' => true,
                        'actorid' => (int)$group->id,
                        'roadid' => (int)$road->id,
                        'picture' => false,
                        'pictureuser' => [],
                    ];
                }
                continue;
            }

            if (count($roads) === 1 && empty($road->groupid)) {
                $members = get_enrolled_users(
                    $context,
                    'mod/treasurehunt:enterplayer',
                    0,
                    'u.id,u.firstname,u.lastname,u.firstnamephonetic,u.lastnamephonetic,u.middlename,' .
                    'u.alternatename,u.picture,u.imagealt'
                );
            } else if (!empty($road->groupid)) {
                $members = get_enrolled_users(
                    $context,
                    'mod/treasurehunt:enterplayer',
                    $road->groupid,
                    'u.id,u.firstname,u.lastname,u.firstnamephonetic,u.lastnamephonetic,u.middlename,' .
                    'u.alternatename,u.picture,u.imagealt'
                );
            } else {
                $members = [];
            }
            foreach ($members as $member) {
                $key = 'u' . $member->id . 'r' . $road->id;
                $participants[$key] = [
                    'key' => $key,
                    'name' => fullname($member),
                    'isgroup' => false,
                    'actorid' => (int)$member->id,
                    'roadid' => (int)$road->id,
                    'picture' => !empty($member->picture),
                    'pictureuser' => (array)$member,
                ];
            }
        }
        if (!$participants) {
            return [];
        }

        [$roadsql, $roadparams] = $DB->get_in_or_equal(array_keys($validroads), SQL_PARAMS_NAMED, 'road');
        $stages = $DB->get_records_sql(
            "SELECT id,roadid,position FROM {treasurehunt_stages} WHERE roadid $roadsql ORDER BY roadid,position",
            $roadparams
        );
        $stagesbyroad = [];
        foreach ($stages as $stage) {
            $stagesbyroad[$stage->roadid][] = (int)$stage->position;
        }

        $actorwhere = $treasurehunt->groupmode ? 'a2.groupid = a.groupid' : 'a2.groupid = 0 AND a2.userid = a.userid';
        $latestattempts = $DB->get_records_sql(
            "SELECT a.id,a.groupid,a.userid,s.roadid,s.position,a.geometrysolved,a.questionsolved,a.activitysolved " .
                "FROM {treasurehunt_attempts} a JOIN {treasurehunt_stages} s ON s.id=a.stageid " .
                "WHERE s.roadid $roadsql AND a.id=(SELECT MAX(a2.id) FROM {treasurehunt_attempts} a2 " .
                "JOIN {treasurehunt_stages} s2 ON s2.id=a2.stageid WHERE s2.roadid=s.roadid AND $actorwhere)",
            $roadparams
        );
        $latestbykey = [];
        foreach ($latestattempts as $attempt) {
            $scope = $treasurehunt->groupmode ? 'g' . $attempt->groupid : 'u' . $attempt->userid;
            $latestbykey[$scope . 'r' . $attempt->roadid] = $attempt;
        }

        $successfulattempts = $DB->get_records_sql(
            "SELECT DISTINCT a.id,a.groupid,a.userid,s.roadid,s.position " .
                "FROM {treasurehunt_attempts} a JOIN {treasurehunt_stages} s ON s.id=a.stageid " .
                "WHERE s.roadid $roadsql AND a.success=1 AND a.type IN ('location','qr')",
            $roadparams
        );
        $completed = [];
        foreach ($successfulattempts as $attempt) {
            $scope = $treasurehunt->groupmode ? 'g' . $attempt->groupid : 'u' . $attempt->userid;
            $completed[$scope . 'r' . $attempt->roadid][(int)$attempt->position] = true;
        }

        foreach ($participants as &$participant) {
            $key = $participant['key'];
            $attempt = $latestbykey[$key] ?? null;
            if (!$attempt) {
                $participant['state'] = 'pending';
            } else if (empty($attempt->geometrysolved)) {
                $participant['state'] = 'failed';
            } else if (empty($attempt->questionsolved) || empty($attempt->activitysolved)) {
                $participant['state'] = 'pending';
            } else {
                $participant['state'] = 'passed';
            }
            $stagepositions = $stagesbyroad[$participant['roadid']] ?? [];
            $position = $stagepositions[0] ?? 1;
            foreach ($stagepositions as $stageposition) {
                if (empty($completed[$key][$stageposition])) {
                    $position = $stageposition;
                    break;
                }
                $position = $stageposition;
            }
            if ($attempt && $participant['state'] !== 'passed') {
                $position = (int)$attempt->position;
            } else if ($attempt && $participant['state'] === 'passed') {
                $next = false;
                foreach ($stagepositions as $stageposition) {
                    if (empty($completed[$key][$stageposition])) {
                        $position = $stageposition;
                        $next = true;
                        break;
                    }
                }
                if (!$next && $stagepositions) {
                    $position = end($stagepositions);
                }
            }
            $participant['position'] = (int)$position;
            $participant['totalstages'] = count($stagepositions);
        }
        unset($participant);

        return array_values($participants);
    }

    /** Create one or two initials from a participant's full name.
     *
     * @param string $name Full name.
     * @return string
     */
    private static function initials(string $name): string {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        if (!$parts) {
            return '?';
        }
        $initials = core_text::substr($parts[0], 0, 1);
        if (count($parts) > 1) {
            $initials .= core_text::substr($parts[count($parts) - 1], 0, 1);
        }
        return core_text::strtoupper($initials);
    }

    /** Choose a stable display color for a Moodle group.
     *
     * @param int $groupid Group id.
     * @return string
     */
    private static function team_color(int $groupid): string {
        $colors = ['#3478c5', '#7a4db3', '#008f87', '#c06a28', '#b14b78', '#587d32', '#5363a6'];
        return $colors[$groupid % count($colors)];
    }
}
