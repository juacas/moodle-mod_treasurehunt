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

function object_to_wkt($text) {
    $WKT = new WKT();
    return $WKT->write($text);
}

function wkt_to_object($text) {
    $WKT = new WKT();
    return $WKT->read($text);
}

function geojson_to_object($text) {
    $GeoJSON = new GeoJSON();
    return $GeoJSON->load($text);
}

function object_to_geojson($text) {
    $GeoJSON = new GeoJSON();
    return $GeoJSON->dump($text);
}

/* ------------------------------------------------------------------------------ */

function insertEntryBD(stdClass $entry) {
    GLOBAL $DB;
    $timenow = time();
    $name = $entry->name;
    $road_id = $entry->road_id;
    $description = $entry->description;
    $descriptionformat = $entry->descriptionformat;
    $descriptiontrust = $entry->descriptiontrust;
    $question_id = $entry->question_id;
    if (isset($entry->num_riddle) && $entry->num_riddle > 0) {
        $num_riddle = $entry->num_riddle;
    } else {
        $num_riddle = $DB->get_record_sql('SELECT count(id) + 1 as num_riddle FROM mdl_scavengerhunt_riddles where road_id = (?)', array($road_id));
        $num_riddle = $num_riddle->num_riddle;
    }
    $sql = 'INSERT INTO mdl_scavengerhunt_riddles (name, road_id, num_riddle, description, descriptionformat, descriptiontrust, '
            . 'timecreated, question_id) VALUES ((?),(?),(?),(?),(?),(?),(?),(?))';
    $params = array($name, $road_id, $num_riddle, $description,
        $descriptionformat, $descriptiontrust, $timenow, $question_id);
    $DB->execute($sql, $params);
//Como no tengo nada para saber el id, tengo que hacer otra consulta
    $sql = 'SELECT id FROM mdl_scavengerhunt_riddles  WHERE name= ? AND road_id = ? AND num_riddle = ? AND description = ? AND '
            . 'descriptionformat = ? AND descriptiontrust = ? AND timecreated = ?';
    $params = array($name, $road_id, $num_riddle, $description, $descriptionformat,
        $descriptiontrust, $timenow);
//Como nos devuelve un objeto lo convierto en una variable
    $result = $DB->get_record_sql($sql, $params);
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
    $sql = 'UPDATE mdl_scavengerhunt_riddles SET name=(?), description = (?), descriptionformat=(?), descriptiontrust=(?),timemodified=(?),question_id=(?) WHERE mdl_scavengerhunt_riddles.id = (?)';
    $parms = array($name, $description, $descriptionformat, $descriptiontrust, $timemodified, $question_id, $idRiddle);
    $DB->execute($sql, $parms);
}

function updateRiddleBD(Feature $feature) {
    GLOBAL $DB;
    $numRiddle = $feature->getProperty('numRiddle');
    $geometryWKT = object_to_wkt($feature->getGeometry());
    $timemodified = time();
    $idRiddle = $feature->getId();
    $sql = 'UPDATE mdl_scavengerhunt_riddles SET num_riddle=(?), geom = GeomFromText((?)), timemodified=(?) WHERE mdl_scavengerhunt_riddles.id = (?)';
    $parms = array($numRiddle, $geometryWKT, $timemodified, $idRiddle);
    $DB->execute($sql, $parms);
}

function deleteEntryBD($id) {
    GLOBAL $DB;
    $riddle_sql = 'SELECT num_riddle,road_id FROM {scavengerhunt_riddles} WHERE id = ?';
    $riddle_result = $DB->get_record_sql($riddle_sql, array($id));
    $table = 'scavengerhunt_riddles';
    $select = 'id = ?';
    $params = array($id);
    $DB->delete_records_select($table, $select, $params);
    $sql = 'UPDATE mdl_scavengerhunt_riddles SET num_riddle = num_riddle - 1 WHERE road_id = (?) AND num_riddle > (?)';
    $parms = array($riddle_result->num_riddle, $riddle_result->road_id);
    $DB->execute($sql, $parms);
}

function getScavengerhunt($idScavengerhunt, $context) {
    global $DB;
//Recojo todas las features
    $riddles_sql = 'SELECT riddle.id, riddle.name, riddle.description, road_id, num_riddle,astext(geom) as geometry FROM {scavengerhunt_riddles} AS riddle'
            . ' inner join {scavengerhunt_roads} AS roads on riddle.road_id = roads.id WHERE scavengerhunt_id = ? ORDER BY num_riddle DESC';
    $riddles_result = $DB->get_records_sql($riddles_sql, array($idScavengerhunt));
    $geojson = riddlesDb2Geojson($riddles_result, $context, $idScavengerhunt);
//Recojo todos los caminos
    $roads_sql = 'SELECT id, name FROM {scavengerhunt_roads} AS roads where scavengerhunt_id = ?';
    $roads_result = $DB->get_records_sql($roads_sql, array($idScavengerhunt));
    foreach ($roads_result as &$value) {
        $value->id = intval($value->id);
    }
    $roadsjson = json_encode($roads_result);
    $fetchstage_returns = array($geojson, $roadsjson);
    return $fetchstage_returns;
}

function renewLockScavengerhunt($idScavengerhunt) {
    global $DB, $USER;

    $table = 'scavengerhunt_locks';
    $userid = $USER->id;
    $params = array('scavengerhunt_id' => $idScavengerhunt, 'user_id' => $userid);
    $time = time() + 120;
    $lock = $DB->get_record($table, $params);

    if (!empty($lock)) {
        $DB->update_record($table, array('id' => $lock->id, 'lockedat' => $time));
        return $lock->id;
    } else {
        return $DB->insert_record($table, array('scavengerhunt_id' => $idScavengerhunt, 'user_id' => $userid, 'lockedat' => $time));
    }
}

function isLockScavengerhunt($idScavengerhunt) {
    global $DB, $USER;
    deleteOldLocks($idScavengerhunt);
    $select = "scavengerhunt_id = ? AND lockedat > ? AND user_id != ?";
    $params = array($idScavengerhunt, time(), $USER->id);
    return $DB->record_exists_select('scavengerhunt_locks', $select, $params);
}

function idLockIsValid($idLock) {
    global $DB;
    return $DB->record_exists_select('scavengerhunt_locks', "id = ?", array($idLock));
}

function deleteOldLocks($idScavengerhunt) {
    global $DB;
    $DB->delete_records_select('scavengerhunt_locks', "lockedat < ? AND scavengerhunt_id = ? ", array(time(), $idScavengerhunt));
}

function checkLock($idScavengerhunt, $idLock) {
    if (!isLockScavengerhunt($idScavengerhunt) && idLockIsValid($idLock)) {
        return true;
    } else {
        return false;
    }
}

function checkRiddle($idRoad) {
    global $USER, $DB;
    //Recupero la Ãºltima pista descubierta por el usuario para esta instancia
    $query = "SELECT max(num_riddle) from {scavengerhunt_riddless} r, {scavengerhunt_attempts} a where a.riddle_id=r.id and a.user_id=? and a.road_id=r.road_id and a.road_id=?";
    $params = array($USER->id, $idRoad);
    $currentriddle = $DB->get_record_sql($query, $params);
    $nextriddle = $currentriddle + 1;
    //Compruebo si la geometrÃ­a estÃ¡ dentro
}

function riddlesDb2Geojson($riddles_result, $context, $idScavengerhunt) {
    $riddlesArray = array();
    foreach ($riddles_result as $value) {
        $multipolygon = wkt_to_object($value->geometry);
        $description = file_rewrite_pluginfile_urls($value->description, 'pluginfile.php', $context->id, 'mod_scavengerhunt', 'description', $value->id);
        $attr = array('idRoad' => intval($value->road_id), 'numRiddle' => intval($value->num_riddle), 'name' => $value->name, 'idStage' => $idScavengerhunt, 'description' => $description);
        $feature = new Feature(intval($value->id), $multipolygon, $attr);
        array_push($riddlesArray, $feature);
    }
    $featureCollection = new FeatureCollection($riddlesArray);
    $geojson = object_to_geojson($featureCollection);
    return $geojson;
}

function getUserProgress($idRoad, $groupmode, $idgroup, $BB, $idScavengerhunt, $context) {
    global $USER, $DB;
    if ($groupmode) {
        //Recupero la ultima pista descubierta por el grupo para esta instancia
        $query = "SELECT r.id,r.name,r.num_riddle,r.description,r.geom as geometry,r.road_id from {scavengerhunt_riddles} r, {scavengerhunt_attempts} a where a.riddle_id=r.id and a.group_id=? and a.road_id=r.road_id and a.road_id=?";
    } else {
        //Recupero la ultima pista descubierta por el usuario para esta instancia
        $query = "SELECT r.id,r.name,r.num_riddle,r.description,r.geom as geometry,r.road_id from {scavengerhunt_riddles} r, {scavengerhunt_attempts} a where a.riddle_id=r.id and a.user_id=? and a.road_id=r.road_id and a.road_id=?";
    }
    $params = array($idgroup, $idRoad);
    $user_progress = $DB->get_records_sql($query, $params);
    return riddlesDb2Geojson($user_progress, $context, $idScavengerhunt);
}

function setFirstRiddle($idRoad, $groupmode, $idgroup, $idScavengerhunt) {
    if ($groupmode) {
        //muestro la pista inicial al grupo para esta instancia
        $query = "INSERT INTO mdl_scavengerhunt_attemps (road_id, riddle_id, accum_time, accum_distance,timecreated,group_id,success, locations) 
			VALUES (?,?,0,0,now(),ST_SetSRID(ST_MakePoint($4,$3),4326), $5);";
    } else {
        //muestro la pista inicial al usuario para esta instancia
        $query = "INSERT INTO mdl_scavengerhunt_attemps (road_id, riddle_id, accum_time, accum_distance,timecreated,user_id,success, locations) 
			VALUES (?,?,0,0,now(),ST_SetSRID(ST_MakePoint($4,$3),4326), $5);";
    }
    $params = array($idgroup, $idRoad);
    $DB->execute($query, $params);
}

function getUserGroupAndRoad($idScavengerhunt, $cm, $courseid) {
    global $USER, $DB;

    $groups = array();
    if ($groupmode = groups_get_activity_groupmode($cm)) {
        //group mode
        $query = "SELECT grouping_id, id as idRoad from {scavengerhunt_roads} where scavengerhunt_id=? AND grouping_id != 0";
        $params = array($idScavengerhunt);
        $availablegroupings = $DB->get_records_sql($query, $params);
        foreach ($availablegroupings as $groupingid) {
            if (count($allgroupsingrouping = groups_get_all_groups($courseid, $USER->id, $groupingid->grouping_id, 'g.id'))) {
                foreach ($allgroupsingrouping as $groupingrouping) {
                    array_push($groups, (object) array('groupmode' => $groupmode, 'group_id' => $groupingrouping->id, 'idRoad' => $groupingid->idroad));
                }
            }
        }
    } else {
        //individual mode
        $query = "SELECT group_id, id as idRoad from {scavengerhunt_roads} where scavengerhunt_id=? AND group_id != 0";
        $params = array($idScavengerhunt);
        $availablegroups = $DB->get_records_sql($query, $params);
        foreach ($availablegroups as $groupid) {
            if (groups_is_member($groupid->group_id)) {
                $groupid->groupmode = $groupmode;
                array_push($groups, $groupid);
            }
        }
    }
    $returnurl = new moodle_url('/mod/scavengerhunt/view.php', array('id' => $cm->id));
    if (count($groups) === 0) {
        //No pertenece a ningÃƒÂºn grupo
        print_error('noteamplay', 'scavengerhunt', $returnurl);
    } else if (count($groups) > 1) {
        //Pertenece a mÃƒÂ¡s de un grupo
        print_error('multipleteamsplay', 'scavengerhunt', $returnurl);
    } else {
        //Bien
        return $groups[0];
    }
}
