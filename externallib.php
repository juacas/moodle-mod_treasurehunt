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
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @author Adrian Rodriguez <huorwhisp@gmail.com>
 * @author Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/mod/treasurehunt/locallib.php");

class mod_treasurehunt_external extends external_api {

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function fetch_treasurehunt_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function fetch_treasurehunt_parameters() {
        return new external_function_parameters(
                array(
            'treasurehuntid' => new external_value(PARAM_INT, 'id of treasurehunt'),
                )
        );
    }

    /**
     * Describes the fetch_treasurehunt return values
     * @return external_single_structure
     */
    public static function fetch_treasurehunt_returns() {
        return new external_single_structure(
                array(
            'treasurehunt' => new external_single_structure(
                    array(
                'roads' => new external_multiple_structure(
                        new external_single_structure(
                        array(
                    'id' => new external_value(PARAM_INT, 'The id of the road'),
                    'name' => new external_value(PARAM_TEXT, 'The name of the road'),
                    'blocked' => new external_value(PARAM_BOOL, 'If true the road is blocked'),
                    'stages' => new external_single_structure(
                            array('type' => new external_value(PARAM_TEXT, 'FeatureColletion'),
                        'features' => new external_multiple_structure(
                                new external_single_structure(
                                array(
                            'type' => new external_value(PARAM_TEXT, 'Feature'),
                            'id' => new external_value(PARAM_INT, 'Feature id'),
                            'geometry' => new external_single_structure(
                                    array(
                                'type' => new external_value(PARAM_TEXT, 'Geometry type'),
                                'coordinates' => new external_multiple_structure(
                                        new external_multiple_structure(
                                        new external_multiple_structure(
                                        new external_single_structure(
                                        array(
                                    new external_value(PARAM_FLOAT, "Longitude"),
                                    new external_value(PARAM_FLOAT, "Latitude")))
                                        )), 'Coordinates definition in geojson format for multipolygon')
                                        ), 'Geometry definition in geojson format', VALUE_OPTIONAL),
                                'properties' => new external_single_structure(
                                    array(
                                'roadid' => new external_value(PARAM_INT, "Associated road id"),
                                'stageposition' => new external_value(PARAM_INT, "Position of associated stage"),
                                'name' => new external_value(PARAM_TEXT, "Name of associated stage"),
                                'treasurehuntid' => new external_value(PARAM_INT, "Associated treasurehunt id"),
                                'clue' => new external_value(PARAM_RAW, "Clue of associated stage")
                                    )
                                )
                                )), 'Features definition in geojson format')
                            ), 'All stages of the road in geojson format')
                        ), 'Array with all roads in the instance.'))
                    )
                ),
                'status' => new external_single_structure(
                    array(
                'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                'msg' => new external_value(PARAM_RAW, 'message explain code')))
                )
        );
    }

    public static function fetch_treasurehunt($treasurehuntid) {
        $params = self::validate_parameters(self::fetch_treasurehunt_parameters(), array('treasurehuntid' => $treasurehuntid));
        $status = array();
        $treasurehunt = new stdClass();
        $cm = get_coursemodule_from_instance('treasurehunt', $params['treasurehuntid']);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/treasurehunt:managetreasurehunt', $context);
        $treasurehunt->roads = treasurehunt_get_all_roads_and_stages($params['treasurehuntid'], $context);
        $status['code'] = 0;
        $status['msg'] = 'La caza del tesoro se ha cargado con éxito';

        $result = array();
        $result['treasurehunt'] = $treasurehunt;
        $result['status'] = $status;
        return $result;
    }

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function update_stages_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function update_stages_parameters() {
        return new external_function_parameters(
                array(
            'stages' => new external_single_structure(
                    array(
                'type' => new external_value(PARAM_TEXT, 'FeatureColletion'),
                'features' => new external_multiple_structure(
                        new external_single_structure(
                        array(
                    'type' => new external_value(PARAM_TEXT, 'Feature'),
                    'id' => new external_value(PARAM_INT, 'Feature id'),
                    'geometry' => new external_single_structure(
                            array(
                        'type' => new external_value(PARAM_TEXT, 'Geometry type'),
                        'coordinates' => new external_multiple_structure(
                                new external_multiple_structure(
                                new external_multiple_structure(
                                new external_single_structure(
                                array(
                            new external_value(PARAM_FLOAT, "Longitude"),
                            new external_value(PARAM_FLOAT, "Latitude")))
                                )), 'Coordinates definition in geojson format for multipolygon')
                            ), 'Geometry definition in geojson format', VALUE_OPTIONAL),
                        'properties' => new external_single_structure(
                            array(
                        'roadid' => new external_value(PARAM_INT, "Associated road id"),
                        'stageposition' => new external_value(PARAM_INT, "Position of associated stage")
                            )
                        )
                        )), 'Features definition in geojson format')
                    ), 'All stages to update of an instance in geojson format'),
                'treasurehuntid' => new external_value(PARAM_INT, 'id of treasurehunt'),
                'lockid' => new external_value(PARAM_INT, 'id of lock')
                )
        );
    }

    /**
     * Describes the update_stages return value
     * @return external_single_structure
     */
    public static function update_stages_returns() {
        return new external_single_structure(
                array(
            'status' => new external_single_structure(
                    array(
                'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                'msg' => new external_value(PARAM_RAW, 'message explain code')))
        ));
    }

    public static function update_stages($stages, $treasurehuntid, $lockid) {
        global $DB;
        $params = self::validate_parameters(self::update_stages_parameters(),
                                            array('stages' => $stages,
                                                'treasurehuntid' => $treasurehuntid,
                                                'lockid' => $lockid));
        $cm = get_coursemodule_from_instance('treasurehunt', $params['treasurehuntid']);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/treasurehunt:managetreasurehunt', $context);
        require_capability('mod/treasurehunt:editstage', $context);
        $features = treasurehunt_geojson_to_object($params['stages']);
        $status = array();
        if (treasurehunt_edition_lock_id_is_valid($params['lockid'])) {
            try {
                $transaction = $DB->start_delegated_transaction();
                foreach ($features as $feature) {
                    treasurehunt_update_geometry_and_position_of_stage($feature, $context);
                }
                $transaction->allow_commit();
                $status['code'] = 0;
                $status['msg'] = 'La actualización de las etapas se ha realizado con éxito';
            } catch (Exception $e) {
                $transaction->rollback($e);
                $status['code'] = 1;
                $status['msg'] = $e;
            }
        } else {
            $status['code'] = 1;
            $status['msg'] = 'Se ha editado esta caza del tesoro, recargue esta página';
        }
        $result = array();
        $result['status'] = $status;
        return $result;
    }

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function delete_stage_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function delete_stage_parameters() {
        return new external_function_parameters(
                array(
            'stageid' => new external_value(PARAM_RAW, 'id of stage'),
            'treasurehuntid' => new external_value(PARAM_INT, 'id of treasurehunt'),
            'lockid' => new external_value(PARAM_INT, 'id of lock')
                )
        );
    }

    /**
     * Describes the delete_stage return value
     * @return external_single_structure
     */
    public static function delete_stage_returns() {
        return new external_single_structure(
                array(
            'status' => new external_single_structure(
                    array(
                'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                'msg' => new external_value(PARAM_RAW, 'message explain code')))
        ));
    }

    public static function delete_stage($stageid, $treasurehuntid, $lockid) {
        $params = self::validate_parameters(self::delete_stage_parameters(),
                array('stageid' => $stageid, 'treasurehuntid' => $treasurehuntid, 'lockid' => $lockid));
        $cm = get_coursemodule_from_instance('treasurehunt', $params['treasurehuntid']);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/treasurehunt:managetreasurehunt', $context);
        require_capability('mod/treasurehunt:editstage', $context);
        $status = array();
        if (treasurehunt_edition_lock_id_is_valid($params['lockid'])) {
            treasurehunt_delete_stage($params['stageid'], $context);
            $status['code'] = 0;
            $status['msg'] = 'La eliminación de la etapa se ha realizado con éxito';
        } else {
            $status['code'] = 1;
            $status['msg'] = 'Se ha editado esta caza del tesoro, recargue esta página';
        }

        $result = array();
        $result['status'] = $status;
        return $result;
    }

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function delete_road_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function delete_road_parameters() {
        return new external_function_parameters(
                array(
            'roadid' => new external_value(PARAM_INT, 'id of road'),
            'treasurehuntid' => new external_value(PARAM_INT, 'id of treasurehunt'),
            'lockid' => new external_value(PARAM_INT, 'id of lock')
                )
        );
    }

    /**
     * Describes the delete_road return value
     * @return external_single_structure
     */
    public static function delete_road_returns() {
        return new external_single_structure(
                array(
            'status' => new external_single_structure(
                    array(
                'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                'msg' => new external_value(PARAM_RAW, 'message explain code')))
        ));
    }

    public static function delete_road($roadid, $treasurehuntid, $lockid) {
        global $DB;
        $params = self::validate_parameters(self::delete_road_parameters(),
                array('roadid' => $roadid, 'treasurehuntid' => $treasurehuntid, 'lockid' => $lockid));
        $cm = get_coursemodule_from_instance('treasurehunt', $params['treasurehuntid']);
        $treasurehunt = $DB->get_record('treasurehunt', array('id' => $cm->instance), '*', MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/treasurehunt:managetreasurehunt', $context);
        require_capability('mod/treasurehunt:editroad', $context);
        $status = array();
        if (treasurehunt_edition_lock_id_is_valid($params['lockid'])) {
            treasurehunt_delete_road($params['roadid'], $treasurehunt, $context);
            $status['code'] = 0;
            $status['msg'] = 'El camino se ha eliminado con ÃƒÂ©xito';
        } else {
            $status['code'] = 1;
            $status['msg'] = 'Se ha editado esta caza del tesoro, recargue esta página';
        }

        $result = array();
        $result['status'] = $status;
        return $result;
    }

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function renew_lock_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function renew_lock_parameters() {
        return new external_function_parameters(
                array(
            'treasurehuntid' => new external_value(PARAM_INT, 'id of treasurehunt'),
            'lockid' => new external_value(PARAM_INT, 'id of lock', VALUE_OPTIONAL, null, NULL_ALLOWED)
                )
        );
    }

    /**
     * Describes the renew_lock return values
     * @return external_single_structure
     */
    public static function renew_lock_returns() {
        return new external_single_structure(
                array(
            'lockid' => new external_value(PARAM_INT, 'id of lock'),
            'status' => new external_single_structure(
                    array(
                'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                'msg' => new external_value(PARAM_RAW, 'message explain code')))
                )
        );
    }

    public static function renew_lock($treasurehuntid, $lockid) {
        GLOBAL $USER;
        $params = self::validate_parameters(self::renew_lock_parameters(),
                array('treasurehuntid' => $treasurehuntid, 'lockid' => $lockid));
        $cm = get_coursemodule_from_instance('treasurehunt', $params['treasurehuntid']);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/treasurehunt:managetreasurehunt', $context);
        $status = array();
        if (isset($params['lockid'])) {
            if (treasurehunt_edition_lock_id_is_valid($params['lockid'])) {
                $lockid = treasurehunt_renew_edition_lock($params['treasurehuntid'], $USER->id);
                $status['code'] = 0;
                $status['msg'] = 'Se ha renovado el bloqueo con exito';
            } else {
                $status['code'] = 1;
                $status['msg'] = 'Se ha editado esta caza del tesoro, recargue esta página';
            }
        } else {
            if (!treasurehunt_is_edition_loked($params['treasurehuntid'], $USER->id)) {
                $lockid = treasurehunt_renew_edition_lock($params['treasurehuntid'], $USER->id);
                $status['code'] = 0;
                $status['msg'] = 'Se ha creado el bloqueo con exito';
            } else {
                $status['code'] = 1;
                $status['msg'] = 'La caza del tesoro está siendo editada';
            }
        }
        $result = array();
        $result['status'] = $status;
        $result['lockid'] = $lockid;
        return $result;
    }

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function user_progress_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function user_progress_parameters() {
        return new external_function_parameters(
                array(
                'userprogress' => new external_single_structure(
                        array(
                    'treasurehuntid' => new external_value(PARAM_INT, 'id of treasurehunt'),
                    'attempttimestamp' => new external_value(PARAM_INT, 'last known timestamp since user\'s progress has not been updated'),
                    'roadtimestamp' => new external_value(PARAM_INT, 'last known timestamp since the road has not been updated'),
                    'playwithoutmoving' => new external_value(PARAM_BOOL, 'If true the play mode is without move.'),
                    'groupmode' => new external_value(PARAM_BOOL, 'If true the game is in groups.'),
                    'initialize' => new external_value(PARAM_BOOL, 'If the map is initializing', VALUE_DEFAULT),
                    'selectedanswerid' => new external_value(PARAM_INT, "id of selected answer", VALUE_DEFAULT, 0),
                    'qoaremoved' => new external_value(PARAM_BOOL, 'If true question or acivity to end has been removed.'),
                    'qrtext' => new external_value(PARAM_TEXT, 'Text scanned', VALUE_OPTIONAL),
                    'location' => new external_single_structure(
                            array(
                        'type' => new external_value(PARAM_TEXT, 'Geometry type'),
                        'coordinates' => new external_single_structure(
                                array(
                            new external_value(PARAM_FLOAT, "Longitude"),
                            new external_value(PARAM_FLOAT, "Latitude")
                                ), 'Coordinates definition in geojson format for point'),
                            ), 'Geometry definition in geojson format', VALUE_OPTIONAL),
                        'currentposition' => new external_single_structure(
                            array(
                        'type' => new external_value(PARAM_TEXT, 'Geometry type'),
                        'coordinates' => new external_single_structure(
                                array(
                            new external_value(PARAM_FLOAT, "Longitude"),
                            new external_value(PARAM_FLOAT, "Latitude")
                                ), 'Coordinates definition in geojson format for point'),
                            ), 'Geometry definition in geojson format', VALUE_OPTIONAL),
                        )
                )
                )
        );
    }

    /**
     * Describes the user_progress return values
     * @return external_single_structure
     */
    public static function user_progress_returns() {
        return new external_single_structure(
                array(
            'attempts' => new external_single_structure(
                    array('type' => new external_value(PARAM_TEXT, 'FeatureColletion'),
                'features' => new external_multiple_structure(
                        new external_single_structure(
                        array(
                    'type' => new external_value(PARAM_TEXT, 'Feature'),
                    'id' => new external_value(PARAM_INT, 'Feature id'),
                    'geometry' => new external_single_structure(
                            array(
                        'type' => new external_value(PARAM_TEXT, 'Geometry type'),
                        'coordinates' => new external_single_structure(
                                array(
                            new external_value(PARAM_FLOAT, "Longitude"),
                            new external_value(PARAM_FLOAT, "Latitude")
                                ), 'Coordinates definition in geojson format for points')
                            ), 'Geometry definition in geojson format'),
                        'properties' => new external_single_structure(
                            array(
                        'roadid' => new external_value(PARAM_INT, "Associated road id"),
                        'stageposition' => new external_value(PARAM_INT, "Position of associated stage"),
                        'name' => new external_value(PARAM_TEXT, "Name of associated stage"),
                        'treasurehuntid' => new external_value(PARAM_INT, "Associated treasurehunt id"),
                        'clue' => new external_value(PARAM_RAW, "Clue of associated stage"),
                        'geometrysolved' => new external_value(PARAM_BOOL, "If true, geometry of attempt is solved"),
                        'info' => new external_value(PARAM_RAW, "The info text of attempt")
                            )
                        )
                        )), 'Features definition in geojson format')
                    ), 'All attempts of the user/group in geojson format', VALUE_OPTIONAL),
                'firststagegeom' => new external_single_structure(
                    array('type' => new external_value(PARAM_TEXT, 'FeatureColletion'),
                'features' => new external_multiple_structure(
                        new external_single_structure(
                        array(
                    'type' => new external_value(PARAM_TEXT, 'Feature'),
                    'id' => new external_value(PARAM_INT, 'Feature id'),
                    'geometry' => new external_single_structure(
                            array(
                        'type' => new external_value(PARAM_TEXT, 'Geometry type'),
                        'coordinates' => new external_multiple_structure(
                                new external_multiple_structure(
                                new external_multiple_structure(
                                new external_single_structure(
                                array(
                            new external_value(PARAM_FLOAT, "Longitude"),
                            new external_value(PARAM_FLOAT, "Latitude")))
                                )), 'Coordinates definition in geojson format for multipolygon')
                            ), 'Geometry definition in geojson format'),
                        'properties' => new external_single_structure(
                            array(
                        'roadid' => new external_value(PARAM_INT, "Associated road id"),
                        'stageposition' => new external_value(PARAM_INT, "Position of associated stage"),
                        'treasurehuntid' => new external_value(PARAM_INT, "Associated treasurehunt id"),
                            )
                        )
                        )), 'Features definition in geojson format')
                    ), 'First stage geometry in geojson format', VALUE_OPTIONAL),
                'attempttimestamp' => new external_value(PARAM_INT, 'Last updated timestamp attempt'),
                'roadtimestamp' => new external_value(PARAM_INT, 'Last updated timestamp road'),
                'infomsg' => new external_multiple_structure(
                    new external_value(PARAM_RAW, 'The info text of attempt'),
                                        'Array with all strings with attempts since the last stored timestamp'),
                'lastsuccessfulstage' => new external_single_structure(
                    array(
                'id' => new external_value(PARAM_INT, 'The id of the last successful stage.'),
                'position' => new external_value(PARAM_INT, 'The position of the last successful stage.'),
                'name' => new external_value(PARAM_RAW, 'The name of the last successful stage.'),
                'clue' => new external_value(PARAM_RAW, 'The clue of the last successful stage.'),
                'question' => new external_value(PARAM_RAW, 'The question of the last successful stage.'),
                'answers' => new external_multiple_structure(new external_single_structure(
                        array(
                    'id' => new external_value(PARAM_INT, 'The id of answer'),
                    'answertext' => new external_value(PARAM_RAW, 'The text of answer')
                        )), 'Array with all answers of the last successful stage.'),
                    'totalnumber' => new external_value(PARAM_INT, 'The total number of stages on the road.'),
                    'activitysolved' => new external_value(PARAM_BOOL, 'If true the activity to end is solved.')
                    ), 'object with data from the last successful stage', VALUE_OPTIONAL),
                'roadfinished' => new external_value(PARAM_RAW, 'If true the road is finished.'),
                'available' => new external_value(PARAM_BOOL, 'If true the hunt is available.'),
                'playwithoutmoving' => new external_value(PARAM_BOOL, 'If true the play mode is without move.'),
                'qrexpected' => new external_value(PARAM_BOOL, 'If true the QRScanner can be used.'),
                'groupmode' => new external_value(PARAM_BOOL, 'If true the game is in groups.'),
                'historicalattempts' => new external_multiple_structure(
                    new external_single_structure(
                    array(
                'string' => new external_value(PARAM_TEXT, 'The info text of attempt'),
                'penalty' => new external_value(PARAM_BOOL, 'If true the attempt is penalized')
                    )
                    ), 'Array with user/group historical attempts.'),
                'qoaremoved' => new external_value(PARAM_BOOL, 'If true question or acivity to end has been removed.'),
                'status' => new external_single_structure(
                    array(
                'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                'msg' => new external_value(PARAM_RAW, 'message explain code')))
                )
        );
    }

    public static function user_progress($userprogress) {
        global $USER, $DB;
        $firststagegeom = null;
        $userattempts = null;
        $qrmode = false;
        $params = self::validate_parameters(self::user_progress_parameters(),
                array('userprogress' => $userprogress))['userprogress'];
        $treasurehuntid = $params['treasurehuntid'];
        $cm = get_coursemodule_from_instance('treasurehunt', $treasurehuntid);
        $treasurehunt = $DB->get_record('treasurehunt', array('id' => $cm->instance), '*', MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/treasurehunt:play', $context, null, false);
        $status = array();
        // Get the group and road to which the user belongs.
        $userparams = treasurehunt_get_user_group_and_road($USER->id, $treasurehunt, $cm->id);
        // Get the total number of stages of the road of the user.
        $nostages = treasurehunt_get_total_stages($userparams->roadid);
        // Last attempt data with correct geometry to know if it has resolved geometry and the stage is overcome.
        $currentstage = treasurehunt_get_last_successful_attempt($USER->id, $userparams->groupid, $userparams->roadid);
        if ($currentstage) {
            $nextnostage = min([$currentstage->position + 1, $nostages]);
        } else {
            $nextnostage = 1;
        }
        $currentworkingstage = $DB->get_record('treasurehunt_stages',
                array('position' => $nextnostage, 'roadid' => $userparams->roadid), '*', MUST_EXIST);
        // Track path.
        if ($treasurehunt->tracking && isset($params['currentposition'])) {
            $location = treasurehunt_geojson_to_object($params['currentposition']);
            $locationwkt = treasurehunt_geometry_to_wkt($location);

            treasurehunt_track_user($USER->id, $treasurehunt, $currentworkingstage->id, time(), $locationwkt);
        }
        // Check if the user has finished the road.
        $roadfinished = treasurehunt_check_if_user_has_finished($USER->id, $userparams->groupid, $userparams->roadid);
        $changesingroupmode = false;
        $qoaremoved = $params['qoaremoved'];
        if ($params['groupmode'] != $treasurehunt->groupmode) {
            $changesingroupmode = true;
        }
        // Get the info of the newly discovered stages if any , and the new timestamp if they have changed.
        $updates = treasurehunt_check_attempts_updates($params['attempttimestamp'],
                                                        $userparams->groupid,
                                                        $USER->id,
                                                        $userparams->roadid,
                                                        $changesingroupmode);
        if ($updates->newroadtimestamp != $params['roadtimestamp']) {
            $updateroad = true;
        } else {
            $updateroad = false;
        }
        $available = treasurehunt_is_available($treasurehunt);
        $playmode = $params['playwithoutmoving'];
        if ($available->available && !$roadfinished) {
            $changesinplaymode = false;
            $playmode = treasurehunt_get_play_mode($USER->id, $userparams->groupid, $userparams->roadid, $treasurehunt);
            if ($params['playwithoutmoving'] != $playmode) {
                $changesinplaymode = true;
            }
            // Check if the user has correctly completed the question and the required activity.
            $qocsolved = treasurehunt_check_question_and_activity_solved($params['selectedanswerid'],
                            $USER->id, $userparams->groupid, $userparams->roadid,
                            $updateroad, $context, $treasurehunt, $nostages, $qoaremoved);
            // Refresh the attempts updates (mainly for reporting).
            $updates = treasurehunt_check_attempts_updates($params['attempttimestamp'],
                            $userparams->groupid,
                            $USER->id,
                            $userparams->roadid,
                            $changesingroupmode);

            if ($qocsolved->msg !== '') {
                $status['msg'] = $qocsolved->msg;
                $status['code'] = 0;
            }
            if ($qocsolved->success) {
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

            if (!$updates->geometrysolved
                    && (isset($params['location']) || isset($params['qrtext']))
                    && !$updateroad
                    && !$changesinplaymode
                    && !$changesingroupmode) {
                $qrtextparam = isset($params['qrtext']) ? $params['qrtext'] : null;
                $locationparam = isset($params['location']) ? treasurehunt_geojson_to_object($params['location']) : null;
                $checklocation = treasurehunt_check_user_location(
                                    $USER->id,
                                    $userparams->groupid,
                                    $userparams->roadid,
                                    $locationparam,
                                    $qrtextparam,
                                    $context,
                                    $treasurehunt,
                                    $nostages);

                if ($checklocation->newattempt) {
                    $updates->newattempttimestamp = $checklocation->attempttimestamp;
                    $updates->newgeometry = true;
                }
                if ($checklocation->newstage) {
                    $updates->geometrysolved = true;
                    if ($checklocation->success) {
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

        $historicalattempts = array();
        // If there was any new attempt, reload the history of attempts.
        if ($updates->newattempttimestamp != $params['attempttimestamp'] || $params['initialize']) {
            $historicalattempts = treasurehunt_get_user_historical_attempts($userparams->groupid, $USER->id, $userparams->roadid);
        }
        $lastsuccessfulstage = array();
        if ($updates->geometrysolved
                || !$available->available
                || $updateroad
                || $updates->attemptsolved
                || $params['initialize']
                || $changesingroupmode) {
            $lastsuccessfulstage = treasurehunt_get_last_successful_stage($USER->id, $userparams->groupid,
                                        $userparams->roadid, $nostages, $available->outoftime,
                                        $available->actnotavailableyet, $context);
        }

        if ($updates->newgeometry
                || $updateroad
                || $roadfinished
                || $params['initialize']
                || $changesingroupmode) {
            list($userattempts, $firststagegeom) = treasurehunt_get_user_progress($userparams->roadid, $userparams->groupid,
                                                                                    $USER->id, $treasurehuntid, $context);
        }
        // If the road has been edited, warn the user.
        if ($updateroad) {
            if ($params['location']) {
                $status = array();
                $status['msg'] = get_string('errsendinglocation', 'treasurehunt');
                $status['code'] = 1;
            }
            if ($params['selectedanswerid']) {
                $status = array();
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
            $status = array();
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
        if (!$updates->geometrysolved && $currentworkingstage->qrtext != '') {
            $qrmode = true;
        }
        $result = array();
        $result['infomsg'] = $updates->strings;
        $result['attempttimestamp'] = $updates->newattempttimestamp;
        $result['roadtimestamp'] = $updates->newroadtimestamp;
        $result['status'] = $status;
        if ($userattempts || $firststagegeom) {
            if ($firststagegeom) {
                $result['firststagegeom'] = $firststagegeom;
            }
            $result['attempts'] = $userattempts;
        }
        if ($lastsuccessfulstage) {
            $result['lastsuccessfulstage'] = $lastsuccessfulstage;
        }
        $result['roadfinished'] = $roadfinished;
        $result['available'] = $available->available;
        $result['playwithoutmoving'] = intval($playmode);
        $result['qrexpected'] = intval($qrmode);
        $result['groupmode'] = intval($treasurehunt->groupmode);
        $result['historicalattempts'] = $historicalattempts;
        $result['qoaremoved'] = $qoaremoved;
        return $result;
    }

}
