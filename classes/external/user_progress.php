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
 * External treasurehunt API
 *
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @author Adrian Rodriguez <huorwhisp@gmail.com>
 * @author Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_treasurehunt\external;

use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;
use core_external\external_api;
use stdClass;
use context_module;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/mod/treasurehunt/externalcompatibility.php');
require_once("$CFG->dirroot/mod/treasurehunt/locallib.php");
/**
 * Service: Get the status in the game os a group or user.
 */
class user_progress extends external_api {
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            [
                'userprogress' => new external_single_structure(
                    [
                        'treasurehuntid' => new external_value(PARAM_INT, 'id of treasurehunt'),
                        'previewroadid' => new external_value(PARAM_INT, 'Road selected for manager preview', VALUE_DEFAULT, 0),
                        'attempttimestamp' =>
                            new external_value(PARAM_INT, 'last known timestamp since user\'s progress has not been updated'),
                        'roadtimestamp' =>
                            new external_value(PARAM_INT, 'last known timestamp since the road has not been updated'),
                        'playwithoutmoving' => new external_value(PARAM_BOOL, 'If true the play mode is without move.'),
                        'groupmode' => new external_value(PARAM_BOOL, 'If true the game is in groups.'),
                        'initialize' => new external_value(PARAM_BOOL, 'If the map is initializing', VALUE_DEFAULT),
                        'selectedanswerid' => new external_value(PARAM_INT, "id of selected answer", VALUE_DEFAULT, 0),
                        'qoaremoved' => new external_value(PARAM_BOOL, 'If true question or acivity to end has been removed.'),
                        'qrtext' => new external_value(PARAM_TEXT, 'Text scanned', VALUE_OPTIONAL),
                        'applang' => new external_value(PARAM_TEXT, 'Mobile app language', VALUE_OPTIONAL),
                        'changedapplang' =>
                            new external_value(PARAM_BOOL, 'If true, mobile app language has changed', VALUE_OPTIONAL),
                        'location' => new external_single_structure(
                            [
                                'type' => new external_value(PARAM_TEXT, 'Geometry type'),
                                'coordinates' => new external_single_structure(
                                    [
                                        new external_value(PARAM_FLOAT, "Longitude"),
                                        new external_value(PARAM_FLOAT, "Latitude"),
                                    ],
                                    'Coordinates definition in geojson format for point'
                                ),
                            ],
                            'Geometry definition in geojson format',
                            VALUE_OPTIONAL
                        ),
                        'currentposition' => new external_single_structure(
                            [
                                'type' => new external_value(PARAM_TEXT, 'Geometry type'),
                                'coordinates' => new external_single_structure(
                                    [
                                        new external_value(PARAM_FLOAT, "Longitude"),
                                        new external_value(PARAM_FLOAT, "Latitude"),
                                    ],
                                    'Coordinates definition in geojson format for point'
                                ),
                            ],
                            'Geometry definition in geojson format',
                            VALUE_OPTIONAL
                        ),
                    ]
                ),
            ]
        );
    }

    /**
     * Describes the user_progress return values
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure(
            [
                'attempts' => new external_single_structure(
                    [
                        'type' => new external_value(PARAM_TEXT, 'FeatureColletion'),
                        'features' => new external_multiple_structure(
                            new external_single_structure(
                                [
                                    'type' => new external_value(PARAM_TEXT, 'Feature'),
                                    'id' => new external_value(PARAM_INT, 'Feature id'),
                                    'geometry' => new external_single_structure(
                                        [
                                            'type' => new external_value(PARAM_TEXT, 'Geometry type'),
                                            'coordinates' => new external_single_structure(
                                                [
                                                    new external_value(PARAM_FLOAT, "Longitude"),
                                                    new external_value(PARAM_FLOAT, "Latitude"),
                                                ],
                                                'Coordinates definition in geojson format for points'
                                            ),
                                        ],
                                        'Geometry definition in geojson format'
                                    ),
                                    'properties' => new external_single_structure(
                                        [
                                            'roadid' => new external_value(PARAM_INT, "Associated road id"),
                                            'stageposition' => new external_value(PARAM_INT, "Position of associated stage"),
                                            'name' => new external_value(PARAM_RAW, "Name of associated stage"),
                                            'treasurehuntid' => new external_value(PARAM_INT, "Associated treasurehunt id"),
                                            'clue' => new external_value(PARAM_RAW, "Clue of associated stage"),
                                            'geometrysolved' =>
                                                new external_value(PARAM_BOOL, "If true, geometry of attempt is solved"),
                                            'info' => new external_value(PARAM_RAW, "The info text of attempt"),
                                        ]
                                    ),
                                ]
                            ),
                            'Features definition in geojson format'
                        ),
                    ],
                    'All attempts of the user/group in geojson format',
                    VALUE_OPTIONAL
                ),
                'nextstage' => new external_single_structure(
                    [
                        'type' => new external_value(PARAM_TEXT, 'FeatureColletion'),
                        'features' => new external_multiple_structure(
                            new external_single_structure(
                                [
                                    'type' => new external_value(PARAM_TEXT, 'Feature'),
                                    'id' => new external_value(PARAM_INT, 'Feature id'),
                                    'geometry' => new external_single_structure(
                                        [
                                            'type' => new external_value(PARAM_TEXT, 'Geometry type'),
                                            'coordinates' => new external_multiple_structure(
                                                new external_multiple_structure(
                                                    new external_multiple_structure(
                                                        new external_single_structure(
                                                            [
                                                                new external_value(PARAM_FLOAT, "Longitude"),
                                                                new external_value(PARAM_FLOAT, "Latitude"),
                                                            ]
                                                        )
                                                    )
                                                ),
                                                'Coordinates definition in geojson format for multipolygon'
                                            ),
                                        ],
                                        'Geometry definition in geojson format'
                                    ),
                                    'properties' => new external_single_structure(
                                        [
                                            'roadid' => new external_value(PARAM_INT, "Associated road id"),
                                            'stageposition' => new external_value(PARAM_INT, "Position of associated stage"),
                                            'treasurehuntid' => new external_value(PARAM_INT, "Associated treasurehunt id"),
                                        ]
                                    ),
                                ]
                            ),
                            'Features definition in geojson format'
                        ),
                    ],
                    'Next stage geometry in geojson format',
                    VALUE_OPTIONAL
                ),
                'clues' => new external_multiple_structure(
                    new external_single_structure([
                        'stageid' => new external_value(PARAM_INT, 'Target stage id'),
                        'position' => new external_value(PARAM_INT, 'Target stage position in the road'),
                        'html' => new external_value(PARAM_RAW, 'Formatted clue HTML'),
                    ]),
                    'Clues currently available to the player'
                ),
                'attempttimestamp' => new external_value(PARAM_INT, 'Last updated timestamp attempt'),
                'roadtimestamp' => new external_value(PARAM_INT, 'Last updated timestamp road'),
                'infomsg' => new external_multiple_structure(
                    new external_value(PARAM_RAW, 'The info text of attempt'),
                    'Array with all strings with attempts since the last stored timestamp'
                ),
                'lastsuccessfulstage' => new external_single_structure(
                    [
                        'id' => new external_value(PARAM_INT, 'The id of the last successful stage.'),
                        'position' => new external_value(PARAM_INT, 'The position of the last successful stage.'),
                        'name' => new external_value(PARAM_RAW, 'The name of the last successful stage.'),
                        'clue' => new external_value(PARAM_RAW, 'The clue of the last successful stage.'),
                        'question' => new external_value(PARAM_RAW, 'The question of the last successful stage.'),
                        'answers' => new external_multiple_structure(new external_single_structure(
                            [
                                'id' => new external_value(PARAM_INT, 'The id of answer'),
                                'answertext' => new external_value(PARAM_RAW, 'The text of answer'),
                            ]
                        ), 'Array with all answers of the last successful stage.'),
                        'totalnumber' => new external_value(PARAM_INT, 'The total number of stages on the road.'),
                        'activitysolved' => new external_value(PARAM_BOOL, 'If true the activity to end is solved.'),
                    ],
                    'object with data from the last successful stage',
                    VALUE_OPTIONAL
                ),
                'roadfinished' => new external_value(PARAM_RAW, 'If true the road is finished.'),
                'available' => new external_value(PARAM_BOOL, 'If true the hunt is available.'),
                'playwithoutmoving' => new external_value(PARAM_BOOL, 'If true the play mode is without move.'),
                'qrexpected' => new external_value(PARAM_BOOL, 'If true the QRScanner can be used.'),
                'groupmode' => new external_value(PARAM_BOOL, 'If true the game is in groups.'),
                'attempthistory' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'string' => new external_value(PARAM_RAW, 'The info text of attempt'),
                            'penalty' => new external_value(PARAM_BOOL, 'If true the attempt is penalized'),
                        ]
                    ),
                    'Array with user/group historical attempts.'
                ),
                'qoaremoved' => new external_value(PARAM_BOOL, 'If true question or acivity to end has been removed.'),
                'playerconfig' => new external_single_structure(
                    [
                        'searchpaneldisabled' => new external_value(PARAM_BOOL, 'If true the search panel is disabled'),
                        'localizationbuttondisabled' =>
                            new external_value(PARAM_BOOL, 'If true the localization button is disabled'),
                        'showdistancehint' =>
                            new external_value(PARAM_BOOL, 'If true the distance hint is shown', VALUE_DEFAULT, false),
                        'showheadinghint' =>
                            new external_value(PARAM_BOOL, 'If true the heading hint is shown', VALUE_DEFAULT, false),
                        'showinzonehint' =>
                            new external_value(PARAM_BOOL, 'If true the in-zone hint is shown', VALUE_DEFAULT, false),
                        'shownextareahint' =>
                            new external_value(PARAM_BOOL, 'If true the next area hint is shown', VALUE_DEFAULT, false),
                    ],
                    'Custom player configuration',
                    VALUE_OPTIONAL
                ),
                'status' => new external_single_structure(
                    [
                        'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                        'msg' => new external_value(PARAM_RAW, 'message explain code'),
                    ]
                ),
            ]
        );
    }
    /**
     * Check events and return new game state.
     * TODO: Design cache strategy. This service is polled.
     */
    public static function execute(array $userprogress) {
        global $USER, $DB;
        $nextstagegeom = null;
        $userattempts = null;
        $qrmode = false;
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['userprogress' => $userprogress]
        )['userprogress'];
        $treasurehuntid = $params['treasurehuntid'];
        $cm = get_coursemodule_from_instance('treasurehunt', $treasurehuntid);
        $treasurehunt = $DB->get_record('treasurehunt', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        // Check if the user has permission to view player.
        if (!has_any_capability(['mod/treasurehunt:enterplayer', 'mod/treasurehunt:managetreasurehunt'], $context)) {
            throw new \required_capability_exception($context, 'mod/treasurehunt:enterplayer', 'nopermissions', '');
        }
        $status = [];
        // Force mobile app language.
        if (isset($params['applang']) && $params['applang'] != current_language()) {
            force_current_language($params['applang']);
        }
        // Get the group and road to which the user belongs.
        $userparams = treasurehunt_get_user_group_and_road(
            $USER->id,
            $treasurehunt,
            $cm->id,
            false,
            '',
            $params['previewroadid']
        );
        // Get the total number of stages of the road of the user.
        $numberofstages = treasurehunt_get_total_stages($userparams->roadid);
        if ($numberofstages < 1) {
            throw new \moodle_exception('invalidentry');
        }
        // Last attempt data with correct geometry to know if it has resolved geometry and the stage is overcome.
        $currentstage = treasurehunt_get_last_successful_attempt($USER->id, $userparams->groupid, $userparams->roadid, $context);
        $roadstate = treasurehunt_get_road_stage_state($USER->id, $userparams->groupid, $userparams->roadid);
        $currentworkingstage = $roadstate->next;
        // Check if the user has finished the road.
        $roadfinished = treasurehunt_check_if_user_has_finished($USER->id, $userparams->groupid, $userparams->roadid);
        $changesingroupmode = false;
        $qoaremoved = $params['qoaremoved'];
        if ($params['groupmode'] != $treasurehunt->groupmode) {
            $changesingroupmode = true;
        }
        // Get the info of the newly discovered stages if any , and the new timestamp if they have changed.
        $updates = treasurehunt_check_attempts_updates(
            $params['attempttimestamp'],
            $userparams->groupid,
            $USER->id,
            $userparams->roadid,
            $changesingroupmode
        );
        if ($updates->newroadtimestamp != $params['roadtimestamp']) {
            $updateroad = true;
        } else {
            $updateroad = false;
        }
        $available = treasurehunt_is_available($treasurehunt);
        // Tracking is only useful while the user is allowed to play an active road.
        if (
            $treasurehunt->tracking && isset($params['currentposition']) && !isset($params['location'])
                && $available->available && !$roadfinished
                && has_capability('mod/treasurehunt:play', $context)
        ) {
            self::validate_point($params['currentposition']);
            $location = treasurehunt_geojson_to_object($params['currentposition']);
            treasurehunt_track_user(
                $USER->id,
                $treasurehunt,
                $currentworkingstage->id,
                time(),
                treasurehunt_geometry_to_wkt($location),
                15
            );
        }
        $playmode = $params['playwithoutmoving'];
        // Teacher previewing mode.
        $previewing = ($params['previewroadid'] || $available->actnotavailableyet)
            && has_capability('mod/treasurehunt:managetreasurehunt', $context);
        $effectiveavailable = $previewing || $available->available;
        $canplay = has_capability('mod/treasurehunt:play', $context) || $previewing;
        if ($previewing || ($available->available && !$roadfinished)) {
            $changesinplaymode = false;
            if ($previewing) {
                // Play without moving is always available for teachers for previewing.
                $playmode = 1;
                $available->actnotavailableyet = false;
                $available->outoftime = false;
            } else {
                $playmode = treasurehunt_get_play_mode($USER->id, $userparams->groupid, $userparams->roadid, $treasurehunt);
            }
            if ($params['playwithoutmoving'] != $playmode) {
                $changesinplaymode = true;
            }

            // If the user can play process the submission.
            if ($canplay) {
                // Process if the user has correctly completed the question and the required activity.
                $qocsolved = treasurehunt_check_question_and_activity_solved(
                    $params['selectedanswerid'],
                    $USER->id,
                    $userparams->groupid,
                    $userparams->roadid,
                    $updateroad,
                    $context,
                    $treasurehunt,
                    $numberofstages,
                    $qoaremoved,
                    $currentstage
                );
            } else {
                $qocsolved = new stdClass();
                $qocsolved->success = false;
                $qocsolved->msg = get_string('nopermissions', 'error', get_string('treasurehunt:play', 'mod_treasurehunt'));
                $qocsolved->updates = [];
                $qocsolved->newattempt = false;
                $qocsolved->attemptsolved = false;
                $qocsolved->roadfinished = false;
                $qocsolved->qoaremoved = $qoaremoved;
            }
            // Refresh the attempts updates (mainly for reporting).
            if ($qocsolved->newattempt) {
                $updates = treasurehunt_check_attempts_updates(
                    $params['attempttimestamp'],
                    $userparams->groupid,
                    $USER->id,
                    $userparams->roadid,
                    $changesingroupmode
                );
            }

            if ($qocsolved->msg !== '') {
                $status['msg'] = $qocsolved->msg;
                $status['code'] = 0;
            }
            if ($qocsolved->success && !$previewing) {
                $playmode = treasurehunt_get_play_mode($USER->id, $userparams->groupid, $userparams->roadid, $treasurehunt);
            }
            if (count($qocsolved->updates)) {
                $updates->strings = array_merge($updates->strings, $qocsolved->updates);
            }
            if ($qocsolved->newattempt) {
                $updates->newattempttimestamp = $qocsolved->attempttimestamp;
            }
            if ($qocsolved->attemptsolved) {
                $updates->attemptsolved = true;
            }
            if ($qocsolved->roadfinished) {
                $roadfinished = true;
            }
            $qoaremoved = $qocsolved->qoaremoved;
            // If the stage location is not solved, check if the user can play and has found the location.
            if (
                !$updates->geometrysolved
                && $canplay
                && (isset($params['location']) || isset($params['qrtext']))
                && !$updateroad
                && !$changesinplaymode
                && !$changesingroupmode
            ) {
                $qrtextparam = isset($params['qrtext']) ? $params['qrtext'] : null;
                if (isset($params['location'])) {
                    self::validate_point($params['location']);
                }
                $locationparam = isset($params['location']) ? treasurehunt_geojson_to_object($params['location']) : null;
                $checklocation = treasurehunt_check_user_location(
                    $USER->id,
                    $userparams->groupid,
                    $userparams->roadid,
                    $locationparam,
                    $qrtextparam,
                    $context,
                    $treasurehunt,
                    $numberofstages
                );

                if ($checklocation->newattempt) {
                    $updates->newattempttimestamp = $checklocation->attempttimestamp;
                    $updates->newgeometry = true;
                }
                if ($checklocation->newstage) {
                    $updates->geometrysolved = true;
                    if ($checklocation->success && !$previewing) {
                        $playmode = treasurehunt_get_play_mode($USER->id, $userparams->groupid, $userparams->roadid, $treasurehunt);
                    }
                }
                if ($checklocation->roadfinished) {
                    $roadfinished = true;
                }
                if ($checklocation->update !== '') {
                    $updates->strings[] = $checklocation->update;
                }

                $status['msg'] = $checklocation->msg;
                $status['code'] = 0;
            }
        }
        // Get new user's state and report it.
        $attempthistory = [];
        $changedapplang = isset($params['changedapplang']) ? $params['changedapplang'] : false;
        // If there was any new attempt, reload the history of attempts.
        if ($updates->newattempttimestamp != $params['attempttimestamp'] || $params['initialize'] || $changedapplang) {
            $attempthistory = treasurehunt_get_user_attempt_history($userparams->groupid, $USER->id, $userparams->roadid);
        }
        $lastsuccessfulstage = null;
        if (
            $updates->geometrysolved
            || !$effectiveavailable
            || $updateroad
            || $updates->attemptsolved
            || $params['initialize']
            || $changesingroupmode
        ) {
            $lastsuccessfulstage = treasurehunt_get_last_successful_stage(
                $USER->id,
                $userparams->groupid,
                $userparams->roadid,
                $numberofstages,
                $available->outoftime,
                $available->actnotavailableyet,
                $context,
                ($qocsolved->newattempt ?? false) || ($checklocation->newattempt ?? false) ? null : $currentstage
            );
        }

        if (
            $updates->newgeometry
            || $updateroad
            || $roadfinished
            || $params['initialize']
            || $changesingroupmode
            || $changedapplang
        ) {
            [$userattempts, $nextstagegeom] = treasurehunt_get_user_progress(
                $userparams->roadid,
                $userparams->groupid,
                $USER->id,
                $treasurehuntid,
                $context
            );
        }
        // If the road has been edited, warn the user.
        if ($updateroad) {
            if (isset($params['location'])) {
                $status = [];
                $status['msg'] = get_string('errsendinglocation', 'treasurehunt');
                $status['code'] = 1;
            }
            if ($params['selectedanswerid']) {
                $status = [];
                $status['msg'] = get_string('errsendinganswer', 'treasurehunt');
                $status['code'] = 1;
            }
        }
        // If the activity is out of time and not being initialized, warn the user.
        if ($available->outoftime && !$params['initialize']) {
            $updates->strings[] = get_string('timeexceeded', 'treasurehunt');
        }
        if ($available->actnotavailableyet) {
            $updates->strings[] = get_string('actnotavailableyet', 'treasurehunt');
        }
        if (!$status) {
            $status = [];
            $status['msg'] = get_string('userprogress', 'treasurehunt');
            $status['code'] = 0;
        }
        if ($params['playwithoutmoving'] != $playmode) {
            if (!$playmode) {
                $updates->strings[] = get_string('changetoplaywithmove', 'treasurehunt');
            } else {
                $updates->strings[] = get_string('changetoplaywithoutmoving', 'treasurehunt');
            }
        }
        $roadstate = treasurehunt_get_road_stage_state($USER->id, $userparams->groupid, $userparams->roadid);
        $currentworkingstage = $roadstate->next;
        foreach ($roadstate->candidates as $candidate) {
            if ($candidate->qrtext !== '') {
                $qrmode = true;
            }
        }
        $result = [];
        $result['infomsg'] = $updates->strings;
        $result['attempttimestamp'] = $updates->newattempttimestamp;
        $result['roadtimestamp'] = $updates->newroadtimestamp;
        $result['status'] = $status;
        // Get custom player configuration.
        // This is used to know if next stage is needed for heading hint and in-zone hint.
        $playerconfig = treasurehunt_get_customplayerconfig($treasurehunt);
        $showheadinghint = $playerconfig->showheadinghint ?? false;
        $showinzonehint = $playerconfig->showinzonehint ?? false;
        $showdistancehint = $playerconfig->showdistancehint ?? false;
        $shownextareahint = $playerconfig->shownextareahint ?? false;

        // Check if last successful stage is the same as current working stage.
        // This means that the user has just solved the current stage.
        // Send the next stage geometry if its the first stage or if the heading hint or in-zone hint is enabled.
        if (
            $currentworkingstage &&
            ($showheadinghint || $showinzonehint || $showdistancehint || $shownextareahint ||
                $currentworkingstage->position == 1)
        ) {
            $briefstages = [];
            foreach ($roadstate->candidates as $candidate) {
                $brief = new stdClass();
                $brief->id = $candidate->id;
                $brief->treasurehuntid = $treasurehuntid;
                $brief->roadid = $candidate->roadid;
                $brief->position = $candidate->position;
                $brief->geometry = $candidate->geom;
                $briefstages[] = $brief;
            }
            $currentstagecoll = treasurehunt_features_to_geojson($briefstages, $context, $treasurehuntid);
            $result['nextstage'] = $currentstagecoll;
        }

        $result['clues'] = [];
        if (!$roadfinished && $effectiveavailable) {
            $previous = null;
            foreach ($roadstate->stages as $stage) {
                if (!isset($roadstate->completed[$stage->id])) {
                    $source = null;
                    $area = 'clueforstage';
                    if ($previous && isset($roadstate->completed[$previous->id])) {
                        if (self::has_clue_content($previous->cluetext)) {
                            $source = $previous;
                            $area = 'cluetext';
                        } else if (self::has_clue_content($stage->clueforstage)) {
                            $source = $stage;
                        }
                    } else if ((!$previous || !empty($stage->discoveroutofsequence)) &&
                            self::has_clue_content($stage->clueforstage)) {
                        $source = $stage;
                    }
                    if ($source) {
                        $result['clues'][] = self::format_stage_clue($source, $area, $stage, $context);
                    }
                }
                $previous = $stage;
            }
        }

        if ($userattempts) {
            $result['attempts'] = $userattempts;
        }

        if ($lastsuccessfulstage) {
            $result['lastsuccessfulstage'] = $lastsuccessfulstage;
        }
        $result['roadfinished'] = $roadfinished;
        $result['available'] = $effectiveavailable;
        $result['playwithoutmoving'] = intval($playmode);
        $result['qrexpected'] = intval($qrmode);
        $result['groupmode'] = intval($treasurehunt->groupmode);
        $result['attempthistory'] = $attempthistory;
        $result['qoaremoved'] = $qoaremoved;
        if ($playerconfig) {
            $result['playerconfig'] = $playerconfig;
        }
        return $result;
    }

    /**
     * Reject malformed point geometries before storing or checking them.
     * Coordinates may be non-geographic on a custom image map.
     *
     * @param array $point GeoJSON point.
     */
    private static function validate_point(array $point): void {
        if (
            $point['type'] !== 'Point' || count($point['coordinates']) !== 2
                || !is_finite((float)$point['coordinates'][0]) || !is_finite((float)$point['coordinates'][1])
        ) {
            throw new \invalid_parameter_exception('Invalid point geometry');
        }
    }

    /**
     * Format an editor clue and resolve its files in the stage file area.
     *
     * @param stdClass $stage Source stage.
     * @param string $area Editor file area.
     * @param stdClass $target Stage to discover.
     * @param context_module $context Activity context.
     * @return array Clue data.
     */
    private static function format_stage_clue(stdClass $stage, string $area, stdClass $target,
            context_module $context): array {
        $html = file_rewrite_pluginfile_urls($stage->{$area}, 'pluginfile.php', $context->id,
            'mod_treasurehunt', $area, $stage->id);
        return [
            'stageid' => (int)$target->id,
            'position' => (int)$target->position,
            'html' => format_text($html, $stage->{$area . 'format'}),
        ];
    }

    /**
     * Whether a rich text clue has text or embedded media.
     *
     * @param string|null $html Editor HTML.
     * @return bool True when the clue is meaningful.
     */
    private static function has_clue_content(?string $html): bool {
        $html = $html ?? '';
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return (bool)preg_match('/[^\s\x{00A0}\x{200B}]/u', $text) ||
            (bool)preg_match('/<(?:img|video|audio|iframe|object|embed|svg|math)\b/i', $html);
    }

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function execute_is_allowed_from_ajax() {
        return true;
    }
}
