<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/mod/scavengerhunt/locallib.php");

class mod_scavengerhunt_external_fetchstage extends external_api {

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function fetchstage_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function fetchstage_parameters() {
        return new external_function_parameters(
                array(
            'idStage' => new external_value(PARAM_INT, 'id of stage'),
                )
        );
    }

    public static function fetchstage_returns() {
        return new external_single_structure(
                array(
            'geojson' => new external_value(PARAM_RAW, 'geojson with all features of the stage'),
            'roads' => new external_value(PARAM_RAW, 'array with all roads of the stage')
        ));
    }

    /**
     * Create groups
     * @param array $groups array of group description arrays (with keys groupname and courseid)
     * @return array of newly created groups
     */
    public static function fetchstage($idStage) { //Don't forget to set it as static
        global $CFG, $DB;

        $params = self::validate_parameters(self::fetchstage_parameters(), array('idStage' => $idStage));
        //Recojo todas las features
        $features_sql = 'SELECT riddle.id, riddle.name, road_id, num_riddle,astext(geom) as geometry FROM {scavengerhunt_riddle} AS riddle'
                . ' inner join {scavengerhunt_roads} AS roads on riddle.road_id = roads.id WHERE scavengerhunt_id = ? ORDER BY num_riddle DESC';
        $features_result = $DB->get_records_sql($features_sql, $params);
        $featureArray = array();
        foreach ($features_result as $value) {
            $multipolygon = wkt_to_geojson($value->geometry);
            $attr = array('idRoad' => intval($value->road_id), 'numRiddle' => intval($value->num_riddle), 'name' => $value->name, 'idStage' => $idStage);
            $feature = new Feature(intval($value->id), $multipolygon, $attr);
            array_push($featureArray, $feature);
        }
        //Recojo todos los caminos
        $roads_sql = 'SELECT id, name FROM {scavengerhunt_roads} AS roads where scavengerhunt_id = ?';
        $roads_result = $DB->get_records_sql($roads_sql, $params);
        $featureCollection = new FeatureCollection($featureArray);
        $geojson = object_to_geojson($featureCollection);
        foreach ($roads_result as &$value) {
            $value->id = intval($value->id);
        }
        $roadsjson = json_encode($roads_result);
        $fetchstage_returns = array($geojson, $roadsjson);
        return $fetchstage_returns;
    }

}

class mod_scavengerhunt_external_savestage extends external_api {

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function savestage_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function savestage_parameters() {
        return new external_function_parameters(
                array(
            'idStage' => new external_value(PARAM_INT, 'id of stage'),
            'GeoJSON' => new external_value(PARAM_RAW, 'GeoJSON with all features')      
                )
        );
    }

    public static function savestage_returns() {
        return new external_single_structure(
                array(
            'geojson' => new external_value(PARAM_RAW, 'geojson with all features of the stage'),
            'roads' => new external_value(PARAM_RAW, 'array with all roads of the stage')
        ));
    }

    /**
     * Create groups
     * @param array $groups array of group description arrays (with keys groupname and courseid)
     * @return array of newly created groups
     */
    public static function savestage($idStage,$GeoJSON) { //Don't forget to set it as static
        global $CFG, $DB;

        $params = self::validate_parameters(self::fetchstage_parameters(), array('idStage' => $idStage));
        //Recojo todas las features
        $features_sql = 'SELECT riddle.name, riddle.id, road_id, num_riddle,astext(geom)as geometry FROM mdl_scavengerhunt_riddle AS riddle'
                . ' inner join mdl_scavengerhunt_roads AS roads on riddle.road_id = roads.id WHERE scavengerhunt_id = ? ORDER BY num_riddle DESC';
        $features_result = $DB->get_records_sql($features_sql, $params);
        $featureArray = array();
        foreach ($features_result as $value) {
            $multipolygon = wkt_to_geojson($value->geometry);
            $attr = array('idRoad' => intval($value->road_id), 'numRiddle' => intval($value->num_riddle), 'name' => $value->name, 'idStage' => $idStage);
            $feature = new Feature(intval($value->id), $multipolygon, $attr);
            array_push($featureArray, $feature);
        }
        //Recojo todos los caminos
        $roads_sql = 'SELECT id, name FROM mdl_scavengerhunt_roads AS roads where scavengerhunt_id = ?';
        $roads_result = $DB->get_records_sql($roads_sql, $params);
        $featureCollection = new FeatureCollection($featureArray);
        $geojson = object_to_geojson($featureCollection);
        foreach ($roads_result as &$value) {
            $value->id = intval($value->id);
        }
        $roadsjson = json_encode($roads_result);
        $fetchstage_returns = array($geojson, $roadsjson);
        return $fetchstage_returns;
    }

}