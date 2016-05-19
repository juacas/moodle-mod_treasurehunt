<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/mod/scavengerhunt/locallib.php");

class mod_scavengerhunt_external_fetch_scavengerhunt extends external_api {

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function fetch_scavengerhunt_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function fetch_scavengerhunt_parameters() {
        return new external_function_parameters(
                array(
            'idScavengerhunt' => new external_value(PARAM_INT, 'id of scavengerhunt'),
                )
        );
    }

    public static function fetch_scavengerhunt_returns() {
        return new external_single_structure(
                array(
            'scavengerhunt' => new external_single_structure(
                    array(
                'riddles' => new external_value(PARAM_RAW, 'geojson with all riddles of the scavengerhunt'),
                'roads' => new external_value(PARAM_RAW, 'json with all roads of the stage'))),
            'status' => new external_single_structure(
                    array(
                'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                'msg' => new external_value(PARAM_RAW, 'message explain code')))
                )
        );
    }

    /**
     * Create groups
     * @param array $groups array of group description arrays (with keys groupname and courseid)
     * @return array of newly created groups
     */
    public static function fetch_scavengerhunt($idScavengerhunt) { //Don't forget to set it as static
        self::validate_parameters(self::fetch_scavengerhunt_parameters(), array('idScavengerhunt' => $idScavengerhunt));

        $scavengerhunt = array();
        $status = array();

        $cm = get_coursemodule_from_instance('scavengerhunt', $idScavengerhunt);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/scavengerhunt:getscavengerhunt', $context);
        list($scavengerhunt['riddles'], $scavengerhunt['roads']) = get_scavengerhunt($idScavengerhunt, $context);
        $status['code'] = 0;
        $status['msg'] = 'La caza del tesoro se ha cargado con éxito';

        $result = array();
        $result['scavengerhunt'] = $scavengerhunt;
        $result['status'] = $status;
        return $result;
    }

}

class mod_scavengerhunt_external_update_riddles extends external_api {

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function update_riddles_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function update_riddles_parameters() {
        return new external_function_parameters(
                array(
            'riddles' => new external_value(PARAM_RAW, 'GeoJSON with all riddles to update'),
            'idScavengerhunt' => new external_value(PARAM_INT, 'id of scavengerhunt'),
            'idLock' => new external_value(PARAM_INT, 'id of lock')
                )
        );
    }

    public static function update_riddles_returns() {
        return new external_single_structure(
                array(
            'status' => new external_single_structure(
                    array(
                'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                'msg' => new external_value(PARAM_RAW, 'message explain code')))
        ));
    }

    /**
     * Create groups
     * @param array $groups array of group description arrays (with keys groupname and courseid)
     * @return array of newly created groups
     */
    public static function update_riddles($riddles, $idScavengerhunt, $idLock) { //Don't forget to set it as static
        global $DB, $USER;
        self::validate_parameters(self::update_riddles_parameters(), array('riddles' => $riddles, 'idScavengerhunt' => $idScavengerhunt, 'idLock' => $idLock));
//Recojo todas las features

        $cm = get_coursemodule_from_instance('scavengerhunt', $idScavengerhunt);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/scavengerhunt:managescavenger', $context);
        require_capability('mod/scavengerhunt:editriddle', $context);
        $features = geojson_to_object($riddles);
        if (idLockIsValid($idLock)) {
            try {
                $transaction = $DB->start_delegated_transaction();
                foreach ($features as $feature) {
                    update_geometry_and_position_of_riddle($feature);
                    // Trigger update riddle event.
                    $eventparams = array(
                        'context' => $context,
                        'objectid' => $feature->getId()
                    );
                    \mod_scavengerhunt\event\riddle_updated::create($eventparams)->trigger();
                }
                $transaction->allow_commit();
                $status['code'] = 0;
                $status['msg'] = 'La actualización de las pistas se ha realizado con éxito';
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

}

class mod_scavengerhunt_external_delete_riddle extends external_api {

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function delete_riddle_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function delete_riddle_parameters() {
        return new external_function_parameters(
                array(
            'idRiddle' => new external_value(PARAM_RAW, 'id of riddle'),
            'idScavengerhunt' => new external_value(PARAM_INT, 'id of scavengerhunt'),
            'idLock' => new external_value(PARAM_INT, 'id of lock')
                )
        );
    }

    public static function delete_riddle_returns() {
        return new external_single_structure(
                array(
            'status' => new external_single_structure(
                    array(
                'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                'msg' => new external_value(PARAM_RAW, 'message explain code')))
        ));
    }

    /**
     * Create groups
     * @param array $groups array of group description arrays (with keys groupname and courseid)
     * @return array of newly created groups
     */
    public static function delete_riddle($idRiddle, $idScavengerhunt, $idLock) { //Don't forget to set it as static
        GLOBAL $USER;
        self::validate_parameters(self::delete_riddle_parameters(), array('idRiddle' => $idRiddle, 'idScavengerhunt' => $idScavengerhunt, 'idLock' => $idLock));
//Recojo todas las features

        $cm = get_coursemodule_from_instance('scavengerhunt', $idScavengerhunt);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/scavengerhunt:managescavenger', $context);
        require_capability('mod/scavengerhunt:editriddle', $context);
        if (idLockIsValid($idLock)) {
            delete_riddle($idRiddle);
            // Trigger deleted riddle event.
            $eventparams = array(
                'context' => $context,
                'objectid' => $idRiddle,
            );
            \mod_scavengerhunt\event\riddle_deleted::create($eventparams)->trigger();
            $status['code'] = 0;
            $status['msg'] = 'La eliminación de la pista se ha realizado con éxito';
        } else {
            $status['code'] = 1;
            $status['msg'] = 'Se ha editado esta caza del tesoro, recargue esta página';
        }

        $result = array();
        $result['status'] = $status;
        return $result;
    }

}

class mod_scavengerhunt_external_delete_road extends external_api {

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
            'idRoad' => new external_value(PARAM_INT, 'id of road'),
            'idScavengerhunt' => new external_value(PARAM_INT, 'id of scavengerhunt'),
            'idLock' => new external_value(PARAM_INT, 'id of lock')
                )
        );
    }

    public static function delete_road_returns() {
        return new external_single_structure(
                array(
            'status' => new external_single_structure(
                    array(
                'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                'msg' => new external_value(PARAM_RAW, 'message explain code')))
        ));
    }

    /**
     * Create groups
     * @param array $groups array of group description arrays (with keys groupname and courseid)
     * @return array of newly created groups
     */
    public static function delete_road($idRoad, $idScavengerhunt, $idLock) { //Don't forget to set it as static
        GLOBAL $USER;
        self::validate_parameters(self::delete_road_parameters(), array('idRoad' => $idRoad, 'idScavengerhunt' => $idScavengerhunt, 'idLock' => $idLock));

        $cm = get_coursemodule_from_instance('scavengerhunt', $idScavengerhunt);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/scavengerhunt:managescavenger', $context);
        require_capability('mod/scavengerhunt:editroad', $context);
        if (idLockIsValid($idLock)) {
            deleteRoadBD($idRoad);
            // Trigger deleted road event.
            $eventparams = array(
                'context' => $context,
                'objectid' => $idRoad
            );
            \mod_scavengerhunt\event\road_deleted::create($eventparams)->trigger();
            $status['code'] = 0;
            $status['msg'] = 'El camino se ha eliminado con éxito';
        } else {
            $status['code'] = 1;
            $status['msg'] = 'Se ha editado esta caza del tesoro, recargue esta página';
        }

        $result = array();
        $result['status'] = $status;
        return $result;
    }

}

class mod_scavengerhunt_external_renew_lock extends external_api {

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
            'idScavengerhunt' => new external_value(PARAM_INT, 'id of scavengerhunt'),
            'idLock' => new external_value(PARAM_INT, 'id of lock', VALUE_OPTIONAL)
                )
        );
    }

    public static function renew_lock_returns() {
        return new external_single_structure(
                array(
            'idLock' => new external_value(PARAM_INT, 'id of lock'),
            'status' => new external_single_structure(
                    array(
                'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                'msg' => new external_value(PARAM_RAW, 'message explain code')))
                )
        );
    }

    /**
     * Create groups
     * @param array $groups array of group description arrays (with keys groupname and courseid)
     * @return array of newly created groups
     */
    public static function renew_lock($idScavengerhunt, $idLock) { //Don't forget to set it as static
        GLOBAL $USER;
        self::validate_parameters(self::renew_lock_parameters(), array('idScavengerhunt' => $idScavengerhunt, 'idLock' => $idLock));

        $cm = get_coursemodule_from_instance('scavengerhunt', $idScavengerhunt);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/scavengerhunt:managescavenger', $context);
        if (isset($idLock)) {
            if (idLockIsValid($idLock)) {
                $idLock = renewLockScavengerhunt($idScavengerhunt, $USER->id);
                $status['code'] = 0;
                $status['msg'] = 'Se ha renovado el bloqueo con exito';
            } else {
                $status['code'] = 1;
                $status['msg'] = 'Se ha editado esta caza del tesoro, recargue esta página';
            }
        } else {
            if (!isLockScavengerhunt($idScavengerhunt, $USER->id)) {
                $idLock = renewLockScavengerhunt($idScavengerhunt, $USER->id);
                $status['code'] = 0;
                $status['msg'] = 'Se ha creado el bloqueo con exito';
            } else {
                $status['code'] = 1;
                $status['msg'] = 'La caza del tesoro está siendo editada';
            }
        }
        $result = array();
        $result['status'] = $status;
        $result['idLock'] = $idLock;
        return $result;
    }

}

class mod_scavengerhunt_external_user_progress extends external_api {

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
            'idScavengerhunt' => new external_value(PARAM_INT, 'id of scavengerhunt'),
            'attempttimestamp' => new external_value(PARAM_INT, 'last known timestamp since user\'s progress has not been updated'),
            'roadtimestamp' => new external_value(PARAM_INT, 'last known timestamp since the road has not been updated'),
            'initialize' => new external_value(PARAM_BOOL, 'If the map is initializing'),
            'location' => new external_value(PARAM_RAW, "GeoJSON with point's location", VALUE_OPTIONAL))
        );
    }

    public static function user_progress_returns() {
        return new external_single_structure(
                array(
            'riddles' => new external_value(PARAM_RAW, 'geojson with all riddles of the user/group'),
            'attempttimestamp' => new external_value(PARAM_INT, 'last updated timestamp attempt'),
            'roadtimestamp' => new external_value(PARAM_INT, 'last updated timestamp road'),
            'infomsg' => new external_value(PARAM_RAW, 'array with all strings with attempts since the last stored timestamp'),
            'lastsuccess' => new external_value(PARAM_RAW, 'object with the name and description of the last successful riddle '),
            'attemptshistory' => new external_value(PARAM_RAW, 'array with the attempts history'),
            'status' => new external_single_structure(
                    array(
                'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                'msg' => new external_value(PARAM_RAW, 'message explain code')))
                )
        );
    }

    /**
     * Create groups
     * @param array $groups array of group description arrays (with keys groupname and courseid)
     * @return array of newly created groups
     */
    public static function user_progress($idScavengerhunt, $attempttimestamp, $roadtimestamp, $initialize, $location) { //Don't forget to set it as static
        global $USER, $COURSE;
        self::validate_parameters(self::user_progress_parameters(), array('idScavengerhunt' => $idScavengerhunt, "attempttimestamp" => $attempttimestamp, "roadtimestamp" => $roadtimestamp, 'location' => $location, 'initialize' => $initialize));
        $cm = get_coursemodule_from_instance('scavengerhunt', $idScavengerhunt);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/scavengerhunt:play', $context);
        // Recojo el grupo y camino al que pertenece
        $params = get_user_group_and_road($USER->id, $cm, $COURSE->id);
        // Recojo la info de las nuevas pistas descubiertas en caso de existir y los nuevos timestamp si han variado.
        $checkupdates = check_timestamp($attempttimestamp, $cm->groupmode, $params->group_id, $USER->id, $params->idroad);
        $newattempttimestamp = $checkupdates->attempttimestamp;
        $newroadtimestamp = $checkupdates->roadtimestamp;
        // Compruebo si se ha enviado una localizacion y a la vez otro usuario del grupo no ha acertado ya esa pista. 
        if (!$checkupdates->success && isset($location)) {
            $newattempt = checkRiddle($USER->id, $params->group_id, $params->idroad, geojson_to_object($location), $cm->groupmode, $COURSE);
            $newattempttimestamp = $newattempt->attempttimestamp;
            $status['msg'] = $newattempt->msg;
            $status['code'] = 0;
        }
        // Si se han realizado cambios o se esta inicializando
        if ($newattempttimestamp != $attempttimestamp || $newroadtimestamp != $roadtimestamp || $initialize) {
            list($userriddles,$lastsuccess) = get_user_progress($params->idroad, $cm->groupmode, $params->group_id, $USER->id, $idScavengerhunt, $context);
        }
        /* $status['code'] = 0;
          $status['msg'] = 'El progreso de usuario se ha cargado con éxito'; */
        $result = array();
        $result['infomsg'] = $checkupdates->strings;
        $result['attempttimestamp'] = $newattempttimestamp;
        $result['roadtimestamp'] = $newroadtimestamp;
        $result['status'] = $status;
        $result['riddles'] = $userriddles;
        $result['lastsuccess'] = $lastsuccess;
        $result['attemptshistory'] = $attemptshistory;
        return $result;
    }

}
