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
            'status' => new external_value(PARAM_RAW, 'status of fetch scavengerhunt')
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

        $cm = get_coursemodule_from_instance('scavengerhunt', $idScavengerhunt);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/scavengerhunt:getscavengerhunt', $context);
        list($scavengerhunt['riddles'], $scavengerhunt['roads']) = getScavengerhunt($idScavengerhunt, $context);
        $status = 'La caza del tesoro se ha cargado con Ã©xito';

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
            'idScavengerhunt' => new external_value(PARAM_INT, 'id of scavengerhunt')
                )
        );
    }

    public static function update_riddles_returns() {
        return new external_single_structure(
                array(
            'status' => new external_value(PARAM_RAW, 'status of update riddle'),
        ));
    }

    /**
     * Create groups
     * @param array $groups array of group description arrays (with keys groupname and courseid)
     * @return array of newly created groups
     */
    public static function update_riddles($riddles, $idScavengerhunt) { //Don't forget to set it as static
        global $DB;
        self::validate_parameters(self::update_riddles_parameters(), array('riddles' => $riddles, 'idScavengerhunt' => $idScavengerhunt));
//Recojo todas las features

        $cm = get_coursemodule_from_instance('scavengerhunt', $idScavengerhunt);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/scavengerhunt:managescavenger', $context);
        $features = geojson_to_object($riddles);
        try {
            $transaction = $DB->start_delegated_transaction();
            foreach ($features as $feature) {
                updateRiddleBD($feature);
            }
            $transaction->allow_commit();
            $status = 'La actualizaciÃ³n de las pistas se ha realizado con Ã©xito';
        } catch (Exception $e) {
            $transaction->rollback($e);
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
            'idScavengerhunt' => new external_value(PARAM_INT, 'id of scavengerhunt')
                )
        );
    }

    public static function delete_riddle_returns() {
        return new external_single_structure(
                array(
            'status' => new external_value(PARAM_RAW, 'status of delete riddle'),
        ));
    }

    /**
     * Create groups
     * @param array $groups array of group description arrays (with keys groupname and courseid)
     * @return array of newly created groups
     */
    public static function delete_riddle($idRiddle, $idScavengerhunt) { //Don't forget to set it as static
        self::validate_parameters(self::delete_riddle_parameters(), array('idRiddle' => $idRiddle, 'idScavengerhunt' => $idScavengerhunt));
//Recojo todas las features

        $cm = get_coursemodule_from_instance('scavengerhunt', $idScavengerhunt);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/scavengerhunt:managescavenger', $context);
        deleteEntryBD($idRiddle);
        $status = 'La eliminaciÃ³n de la pista se ha realizado con Ã©xito';

        $result = array();
        $result['status'] = $status;
        return $result;
    }

}

class mod_scavengerhunt_external_add_road extends external_api {

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function add_road_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function add_road_parameters() {
        return new external_function_parameters(
                array(
            'nameRoad' => new external_value(PARAM_RAW, 'collection id riddles in JSON format', VALUE_OPTIONAL),
            'idScavengerhunt' => new external_value(PARAM_INT, 'id of scavengerhunt')
                )
        );
    }

    public static function add_road_returns() {
        return new external_single_structure(
                array(
            'road' => new external_single_structure(
                    array(
                'id' => new external_value(PARAM_INT, 'id of road'),
                'name' => new external_value(PARAM_RAW, 'name of road'))),
            'status' => new external_value(PARAM_RAW, 'status of add new road'),
        ));
    }

    /**
     * Create groups
     * @param array $groups array of group description arrays (with keys groupname and courseid)
     * @return array of newly created groups
     */
    public static function add_road($nameRoad, $idScavengerhunt) { //Don't forget to set it as static
        self::validate_parameters(self::add_road_parameters(), array('nameRoad' => $nameRoad, 'idScavengerhunt' => $idScavengerhunt));

        $road = array();

        $cm = get_coursemodule_from_instance('scavengerhunt', $idScavengerhunt);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/scavengerhunt:managescavenger', $context);
        if (!$nameRoad) {
            list($id, $nameRoad) = addDefaultRoad($idScavengerhunt);
        } else {
            $id = insertRoadBD($idScavengerhunt, $nameRoad);
        }
        $road['id'] = $id;
        $road['name'] = $nameRoad;
        $status = 'El nuevo camino se ha generado con Ã©xito';

        $result = array();
        $result['road'] = $road;
        $result['status'] = $status;
        return $result;
    }

}

class mod_scavengerhunt_external_update_road extends external_api {

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function update_road_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function update_road_parameters() {
        return new external_function_parameters(
                array(
            'nameRoad' => new external_value(PARAM_RAW, 'new name for road'),
            'idRoad' => new external_value(PARAM_INT, 'id of road'),
            'idScavengerhunt' => new external_value(PARAM_INT, 'id of scavengerhunt')
                )
        );
    }

    public static function update_road_returns() {
        return new external_single_structure(
                array(
            'status' => new external_value(PARAM_RAW, 'status of update road'),
        ));
    }

    /**
     * Create groups
     * @param array $groups array of group description arrays (with keys groupname and courseid)
     * @return array of newly created groups
     */
    public static function update_road($nameRoad, $idRoad, $idScavengerhunt) { //Don't forget to set it as static
        self::validate_parameters(self::update_road_parameters(), array('nameRoad' => $nameRoad, 'idRoad' => $idRoad, 'idScavengerhunt' => $idScavengerhunt));

        $cm = get_coursemodule_from_instance('scavengerhunt', $idScavengerhunt);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/scavengerhunt:managescavenger', $context);
        updateRoadBD($idRoad, $nameRoad);
        $status = 'El camino se ha actualizado con Ã©xito';

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
            'idScavengerhunt' => new external_value(PARAM_INT, 'id of scavengerhunt')
                )
        );
    }

    public static function delete_road_returns() {
        return new external_single_structure(
                array(
            'status' => new external_value(PARAM_RAW, 'status of delete road'),
        ));
    }

    /**
     * Create groups
     * @param array $groups array of group description arrays (with keys groupname and courseid)
     * @return array of newly created groups
     */
    public static function delete_road($idRoad, $idScavengerhunt) { //Don't forget to set it as static
        self::validate_parameters(self::delete_road_parameters(), array('idRoad' => $idRoad, 'idScavengerhunt' => $idScavengerhunt));

        $cm = get_coursemodule_from_instance('scavengerhunt', $idScavengerhunt);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/scavengerhunt:managescavenger', $context);
        deleteRoadBD($idRoad);
        $status = 'El camino se ha eliminado con Ã©xito';

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
            'idScavengerhunt' => new external_value(PARAM_INT, 'id of scavengerhunt')
                )
        );
    }

    public static function renew_lock_returns() {
        return new external_single_structure(
                array(
            'status' => new external_value(PARAM_RAW, 'status of renew lock'),
        ));
    }

    /**
     * Create groups
     * @param array $groups array of group description arrays (with keys groupname and courseid)
     * @return array of newly created groups
     */
    public static function renew_lock($idScavengerhunt) { //Don't forget to set it as static
        self::validate_parameters(self::renew_lock_parameters(), array('idScavengerhunt' => $idScavengerhunt));

        $cm = get_coursemodule_from_instance('scavengerhunt', $idScavengerhunt);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/scavengerhunt:managescavenger', $context);
        if (!isLockScavengerhunt($idScavengerhunt)) {
            renewLockScavengerhunt($idScavengerhunt);
            $status = 'Se ha renovado con exito';
        } else {
            $status = 'Ya hay alguien editando esta caza del tesoro';
        }
        $result = array();
        $result['status'] = $status;
        return $result;
    }

}
