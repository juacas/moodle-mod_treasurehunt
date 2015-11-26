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
 * Internal library of functions for module scavengerhunt
 *
 * All the scavengerhunt specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package    mod_scavengerhunt
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once("$CFG->dirroot/mod/scavengerhunt/lib.php");
require_once (dirname(__FILE__) . '/GeoJSON/GeoJSON.class.php');
       

        //Cargo las clases necesarias de un objeto GeoJSON
        spl_autoload_register(array('GeoJSON', 'autoload'));
/*
 * Does something really useful with the passed things
 *
 * @param array $things
 * @return object
 * function scavengerhunt_do_something_useful(array $things) {
 *    return new stdClass();
 * }
 */
function geojson_to_wkt($text) {
    $WKT = new WKT();
    return $WKT->write($text);
}
function wkt_to_geojson ($text) {
    $WKT = new WKT();
    return $WKT->read($text);
}
function geojson_to_object($text){
    $GeoJSON = new GeoJSON();
    return $GeoJSON->load($text);
}
function object_to_geojson($text){
    $GeoJSON = new GeoJSON();
    return $GeoJSON->dump($text);
}


function insertFeatureBD(stdClass $entry) {
    GLOBAL $DB;
    $timenow = time();
    $idRiddle = $entry->id;
    $name = $entry->name;
    $road_id = $entry->road_id;
    $num_riddle = $entry->num_riddle;
    $description = $entry->description;
    $descriptionformat = $entry->descriptionformat;
    $descriptiontrust = $entry->descriptiontrust;
    $timecreated = $timenow;
    $timemodified = $timenow;
    $question_id = $entry->question_id;
    $geometryWKT = $entry->geom;
    $sql = 'INSERT INTO mdl_scavengerhunt_riddle (id, name, road_id, num_riddle, description, descriptionformat, descriptiontrust, '
            . 'timecreated, timemodified, question_id, geom) VALUES ((?),(?),(?),(?),(?),(?),(?),(?),(?),(?),GeomFromText((?)))';
    $parms = array($idRiddle, $name, $road_id, $num_riddle, $description,
        $descriptionformat, $descriptiontrust, $timecreated, $timemodified, $question_id, $geometryWKT);
    $id = $DB->execute($sql,$parms);
    //Como no tengo nada para saber el id, tengo que hacer otra consulta
    $sql = 'SELECT id FROM mdl_scavengerhunt_riddle  WHERE name= ? AND road_id = ? AND num_riddle = ? AND description = ? AND '
            . 'descriptionformat = ? AND descriptiontrust = ? AND timecreated = ? AND timemodified = ?';
    $parms = array($name, $road_id, $num_riddle, $description, $descriptionformat,
        $descriptiontrust, $timecreated, $timemodified);
    //Como nos devuelve un objeto lo convierto en una variable
    $result = $DB->get_record_sql($sql, $parms);
    $id = $result->id;
    return $id;
}

function updateEntryBD(stdClass $entry) {
    GLOBAL $DB;
    $name = $entry->name;
    $description = $entry->description;
    $descriptionformat = $entry->descriptionformat;
    $descriptiontrust = $entry->descriptiontrust;
    $timemodified = time();
    $question_id = $entry->question_id;
    $idRiddle = $entry->id;
    $sql = 'UPDATE mdl_scavengerhunt_riddle SET name=(?), description = (?), descriptionformat=(?), descriptiontrust=(?),timemodified=(?),question_id=(?) WHERE mdl_scavengerhunt_riddle.id = (?)';
    $parms = array($name, $description, $descriptionformat, $descriptiontrust, $timemodified, $question_id, $idRiddle);
    $DB->execute($sql, $parms);
}

function updateFeatureBD(Feature $feature) {
    GLOBAL $DB;
    $geojson = new GeoJSON();
    $numRiddle = $feature->getProperty('numRiddle');
    $geometryWKT = geojson_to_wkt($geojson->dump($feature));
    $timemodified = time();
    $idRiddle = $feature->getProperty('idRiddle');
    $sql = 'UPDATE mdl_scavengerhunt_riddle SET num_riddle=(?), geom = GeomFromText((?)), timemodified=(?) WHERE mdl_scavengerhunt_riddle.id = (?)';
    $parms = array($numRiddle, $geometryWKT, $timemodified, $idRiddle);
    $DB->execute($sql, $parms);
}

function deleteEntryBD(){
    
}