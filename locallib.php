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
    $activitytoend = $entry->activitytoend;

    $num_riddle_result = $DB->get_record_sql('SELECT count(id) + 1 as num_riddle FROM mdl_scavengerhunt_riddles where road_id = (?)', array($road_id));
    $num_riddle = $num_riddle_result->num_riddle;

    $sql = 'INSERT INTO mdl_scavengerhunt_riddles (name, road_id, num_riddle, description, descriptionformat, descriptiontrust, '
            . 'timecreated, activitytoend) VALUES ((?),(?),(?),(?),(?),(?),(?),(?))';
    $params = array($name, $road_id, $num_riddle, $description,
        $descriptionformat, $descriptiontrust, $timenow, $activitytoend);
    $DB->execute($sql, $params);
    //Como he insertado una nueva pista sin geometrias pongo el camino como no valido
    setValidRoad($road_id, false);
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
    $activitytoend = $entry->activitytoend;
    $idRiddle = $entry->id;
    $sql = 'UPDATE mdl_scavengerhunt_riddles SET name=(?), description = (?), descriptionformat=(?), descriptiontrust=(?),timemodified=(?),activitytoend=(?) WHERE mdl_scavengerhunt_riddles.id = (?)';
    $params = array($name, $description, $descriptionformat, $descriptiontrust, $timemodified, $activitytoend, $idRiddle);
    $DB->execute($sql, $params);
}

function updateRiddleBD(Feature $feature) {
    GLOBAL $DB;
    $numRiddle = $feature->getProperty('numRiddle');
    $road_id = $feature->getProperty('idRoad');
    $geometryWKT = object_to_wkt($feature->getGeometry());
    $timemodified = time();
    $idRiddle = $feature->getId();
    $geomfuncs = getgeometryfunctions($DB);
    $sql = 'UPDATE mdl_scavengerhunt_riddles SET num_riddle=(?), geom = ' . $geomfuncs['ST_GeomFromText'] . '((?)), timemodified=(?) WHERE mdl_scavengerhunt_riddles.id = (?)';
    $params = array($numRiddle, $geometryWKT, $timemodified, $idRiddle);
    $DB->execute($sql, $params);
    setValidRoad($road_id);
}

function insertRoadBD($idScavengerhunt, $nameRoad) {
    GLOBAL $DB;
    $road = new stdClass();
    if (empty($nameRoad)) {
        throw new invalid_parameter_exception('El nombre introducido no puede estar vacio');
    }
    $road->name = $nameRoad;
    $road->scavengerhunt_id = $idScavengerhunt;
    $road->timecreated = time();
    $road->timemodified = 0;
    $id = $DB->insert_record('scavengerhunt_roads', $road);
    return $id;
}

function updateRoadBD($roadid, $nameRoad) {
    GLOBAL $DB;
    if (empty($nameRoad)) {
        throw new invalid_parameter_exception('El nombre introducido no puede estar vacio');
    }
    $road = new stdClass();
    $road->id = $roadid;
    $road->name = $nameRoad;
    $road->timemodified = time();
    $DB->update_record('scavengerhunt_roads', $road, $bulk = false);
}

function deleteRoadBD($roadid) {
    GLOBAL $DB;
    $DB->delete_records('scavengerhunt_roads', array('id' => $roadid));
    $select = 'road_id = ?';
    $params = array($roadid);
    $DB->delete_records_select('scavengerhunt_riddles', $select, $params);
    $DB->delete_records_select('scavengerhunt_attempts', $select, $params);
}

function getTotalRoads($idScavengerhunt) {
    GLOBAL $DB;
    $number = $DB->count_records('scavengerhunt_roads', array('scavengerhunt_id' => $idScavengerhunt));
    return $number;
}

function getgeometryfunctions(moodle_database $DB) {
    $info = $DB->get_server_info();
    $dbtype = $DB->get_dbfamily();
    $functions = array();
    if ($dbtype === 'mysql' && version_compare($info['version'], '5.6.1') < 0) {
        $functions['ST_GeomFromText'] = 'GeomFromText';
        $functions['ST_Intersects'] = 'Intersects';
    } else { // OGC Simple SQL for Features.
        $functions['ST_GeomFromText'] = 'ST_GeomFromText';
        $functions['ST_Intersects'] = 'ST_Intersects';
    }
    return $functions;
}

function setValidRoad($road_id, $valid = null) {
    GLOBAL $DB;
    $road = new stdClass();
    $road->id = $road_id;
    $road->timemodified = time();
    if (is_null($valid)) {
        $road->validated = is_valid_road($road_id);
    } else {
        $road->validated = $valid;
    }
    $DB->update_record("scavengerhunt_roads", $road);
}

function deleteEntryBD($id) {
    GLOBAL $DB;
    $riddle_sql = 'SELECT num_riddle,road_id FROM {scavengerhunt_riddles} WHERE id = ?';
    $riddle_result = $DB->get_record_sql($riddle_sql, array($id));
    $table = 'scavengerhunt_riddles';
    $select = 'id = ?';
    $params = array($id);
    $DB->delete_records_select($table, $select, $params);
    $table = 'scavengerhunt_attempts';
    $select = 'riddle_id = ?';
    $DB->delete_records_select($table, $select, $params);
    $sql = 'UPDATE mdl_scavengerhunt_riddles SET num_riddle = num_riddle - 1 WHERE road_id = (?) AND num_riddle > (?)';
    $params = array($riddle_result->road_id, $riddle_result->num_riddle);
    $DB->execute($sql, $params);
    setValidRoad($riddle_result->road_id);
}

function check_riddle_is_blocked($riddleid) {
    global $DB;
    $select = "riddle_id = ? AND success = 1";
    $params = array($riddleid);
    return $DB->record_exists_select('scavengerhunt_attempts', $select, $params);
}

function get_scavengerhunt($idScavengerhunt, $context) {
    global $DB;
//Recojo todas las features
    $riddles_sql = 'SELECT riddle.id, CASE WHEN (SELECT at.success FROM {scavengerhunt_attempts} at WHERE riddle.id=at.riddle_id AND at.success = 1) THEN true ELSE false END AS blocked, riddle.name, riddle.description, road_id, num_riddle,astext(geom) as geometry FROM {scavengerhunt_riddles} AS riddle'
            . ' inner join {scavengerhunt_roads} AS roads on riddle.road_id = roads.id WHERE scavengerhunt_id = ? ORDER BY num_riddle DESC';
    $riddles_result = $DB->get_records_sql($riddles_sql, array($idScavengerhunt));
    $geojson = riddles_to_geojson($riddles_result, $context, $idScavengerhunt);
//Recojo todos los caminos
    $roads_sql = 'SELECT id, name FROM {scavengerhunt_roads} AS road where scavengerhunt_id = ?';
    $roads_result = $DB->get_records_sql($roads_sql, array($idScavengerhunt));
    foreach ($roads_result as &$value) {
        $value->id = intval($value->id);
    }
    $roadsjson = json_encode($roads_result);
    $fetchstage_returns = array($geojson, $roadsjson);
    return $fetchstage_returns;
}

function renewLockScavengerhunt($idScavengerhunt, $userid) {
    global $DB;

    $table = 'scavengerhunt_locks';
    $params = array('scavengerhunt_id' => $idScavengerhunt, 'user_id' => $userid);
    $time = time() + 120;
    $lock = $DB->get_record($table, $params);

    if (!empty($lock)) {
        $DB->update_record($table, array('id' => $lock->id, 'lockedat' => $time));
        return $lock->id;
    } else {
        deleteOldLocks($idScavengerhunt);
        return $DB->insert_record($table, array('scavengerhunt_id' => $idScavengerhunt, 'user_id' => $userid, 'lockedat' => $time));
    }
}

function isLockScavengerhunt($idScavengerhunt, $userid) {
    global $DB;
    $select = "scavengerhunt_id = ? AND lockedat > ? AND user_id != ?";
    $params = array($idScavengerhunt, time(), $userid);
    return $DB->record_exists_select('scavengerhunt_locks', $select, $params);
}

function idLockIsValid($idLock) {
    global $DB;
    return $DB->record_exists_select('scavengerhunt_locks', "id = ?", array($idLock));
}

function get_username_blocking_edition($idScavengerhunt) {
    global $DB;
    $table = 'scavengerhunt_locks';
    $params = array('scavengerhunt_id' => $idScavengerhunt);
    $result = $DB->get_record($table, $params);
    return get_user_fullname_from_id($result->user_id);
}

function deleteOldLocks($idScavengerhunt) {
    global $DB;
    $DB->delete_records_select('scavengerhunt_locks', "lockedat < ? AND scavengerhunt_id = ? ", array(time(), $idScavengerhunt));
}

function checkRiddle($userid, $idgroup, $roadid, $point, $groupmode, $course) {
    global $DB;
    $return = new stdClass();
    $location = object_to_wkt($point);
    if ($groupmode) {
        $group_type = 'group_id';
        $params = array($idgroup, $roadid, $roadid);
    } else {
        $group_type = 'user_id';
        $params = array($userid, $roadid, $roadid);
    }
    // Recupero la ultima pista descubierta por el usuario/grupo para esta instancia.
    $query = "SELECT id,num_riddle from {scavengerhunt_riddles} WHERE num_riddle=(Select max(num_riddle) from {scavengerhunt_riddles} r INNER JOIN {scavengerhunt_attempts} a ON a.riddle_id=r.id  WHERE a.$group_type=? and a.road_id=? and a.success=1) AND road_id = ?";
    $currentriddle = $DB->get_record_sql($query, $params);
    if ($currentriddle) {
        $nextnumriddle = $currentriddle->num_riddle + 1;
    } else {
        $nextnumriddle = 1;
    }
    // Compruebo si la geometria esta dentro.
    $geomfuncs = getgeometryfunctions($DB);
    $query = "SELECT id, {$geomfuncs['ST_Intersects']}(geom,{$geomfuncs['ST_GeomFromText']}((?))) as inside,activitytoend from {scavengerhunt_riddles} where num_riddle=(?) and road_id=(?)";
    $params = array($location, $nextnumriddle, $roadid);
    $nextriddle = $DB->get_record_sql($query, $params);
    if ($nextriddle->inside) {
        $isInside = 1;
        $pointIdRiddle = $nextriddle->id;
        $return->msg = get_string('successlocation', 'scavengerhunt');
    } else {
        $isInside = 0;
        $pointIdRiddle = $currentriddle->id;
        $return->msg = get_string('faillocation', 'scavengerhunt');
    }
    // Si no es la primera pista fallada, y por lo tanto null.
    if (!is_null($pointIdRiddle)) {
        //Si has completado la actividad requerida o has fallado la localizacion.
        if (check_completion_activity($course, $nextriddle->activitytoend) || !$isInside) {
            $return->attempttimestamp = time();
            $query = 'INSERT INTO mdl_scavengerhunt_attempts (road_id, riddle_id, timecreated, group_id, user_id, success,'
                    . ' locations) VALUES ((?),(?),(?),(?),(?),(?),' . $geomfuncs['ST_GeomFromText'] . '((?)))';
            $params = array($roadid, $pointIdRiddle, $return->attempttimestamp,
                $idgroup, $userid, $isInside, $location);
            $DB->execute($query, $params);
        } else {
            $return->msg = get_string('lockedriddle', 'scavengerhunt');
        }
    }
    return $return;
}

function riddles_to_geojson($riddles_result, $context, $idScavengerhunt, $userid = null) {
    $riddlesArray = array();
    foreach ($riddles_result as $riddle) {
        $multipolygon = wkt_to_object($riddle->geometry);
        if (isset($riddle->description)) {
            $description = file_rewrite_pluginfile_urls($riddle->description, 'pluginfile.php', $context->id, 'mod_scavengerhunt', 'description', $riddle->id);
        } else {
            $description = null;
        }
        $attr = array('idRoad' => intval($riddle->road_id),
            'numRiddle' => intval($riddle->num_riddle),
            'name' => $riddle->name,
            'idStage' => $idScavengerhunt,
            'description' => $description);
        if (property_exists($riddle, 'blocked')) {
            $attr['blocked'] = intval($riddle->blocked);
        }
        if (property_exists($riddle, 'timecreated')) {
            $attr['date'] = (is_null($riddle->timecreated)) ? null : userdate($riddle->timecreated);
        }
        if (property_exists($riddle, 'success')) {
            $attr['success'] = ((is_null($riddle->success)) ? null : intval($riddle->success));
            $attr['info'] = set_string_attempt($riddle, $userid);
        }
        $feature = new Feature($riddle->id ?
                        intval($riddle->id) : null, $multipolygon, $attr);
        array_push($riddlesArray, $feature);
    }
    $featureCollection = new FeatureCollection($riddlesArray);
    $geojson = object_to_geojson($featureCollection);
    return $geojson;
}

function get_user_progress($roadid, $groupmode, $idgroup, $userid, $idScavengerhunt, $context) {
    global $DB;
    $lastsuccess = new stdClass();
    if ($groupmode) {
        $group_type = 'group_id';
        $params = array($idgroup, $roadid);
    } else {
        $group_type = 'user_id';
        $params = array($userid, $roadid);
    }
    // Recupero las pistas descubiertas y fallos cometidos por el usuario/grupo para esta instancia.
    $query = "SELECT a.timecreated,a.user_id as user ,r.name,IF(a.success=0,NULL,r.id) as id,IF(a.success=0,NULL,r.description) as description" .
            ",r.num_riddle,  astext(a.locations) as geometry,r.road_id,a.success from {scavengerhunt_riddles} r INNER JOIN {scavengerhunt_attempts} a ON a.riddle_id=r.id where a." . $group_type . "=(?) AND a.road_id=(?) ORDER BY r.num_riddle DESC, a.timecreated DESC";
    $user_progress = $DB->get_records_sql($query, $params);
    // Si no tiene ningun progreso mostrar primera pista del camino para comenzar.
    if (count($user_progress) === 0) {
        $query = "SELECT num_riddle -1,astext(geom) as geometry,road_id from {scavengerhunt_riddles}  where  road_id=? and num_riddle=1";
        $params = array($roadid);
        $user_progress = $DB->get_records_sql($query, $params);
        $lastsuccess->name = get_string('start', 'scavengerhunt');
        $lastsuccess->description = get_string('overcomefirstriddle', 'scavengerhunt');
    } else {
         // Recupero la ultima pista acertada. He ordenado la consulta por numero de pista descendiente y luego por tiempo descendiente.
        foreach ($user_progress as $riddle) {
            if ($riddle->success) {
                $lastsuccess->name = $riddle->name;
                $lastsuccess->description = file_rewrite_pluginfile_urls($riddle->description, 'pluginfile.php', $context->id, 'mod_scavengerhunt', 'description', $riddle->id);
                break;
            }
        }
    }
    $geojson = riddles_to_geojson($user_progress, $context, $idScavengerhunt, $userid);
    return array($geojson,$lastsuccess);
}

function is_valid_road($roadid) {
    global $DB;

    $query = "SELECT geom as geometry from {scavengerhunt_riddles} where road_id = ?";
    $params = array($roadid);
    $riddles = $DB->get_records_sql($query, $params);
    if (count($riddles) <= 1) {
        return false;
    }
    foreach ($riddles as $riddle) {
        if ($riddle->geometry === null) {
            return false;
        }
    }
    return true;
}

function check_completion_activity($course, $cmid) {
    if ($cmid != 0) {
        $modinfo = get_fast_modinfo($course);
        $cmactivitytoend = $modinfo->get_cm($cmid);
    } else {
        return true;
    }
    // Check if a user has complete that activity.
    $completioninfo = new completion_info($course);
    $current = $completioninfo->get_data($cmactivitytoend);
    return $completioninfo->internal_get_state($cmactivitytoend, null, $current); // 0 or 1 , true or false.
}

function get_user_group_and_road($userid, $cm, $courseid) {
    global $DB;

    $groups = array();

    if ($cm->groupmode) {
        // Group mode.
        $query = "SELECT grouping_id,validated, id as idroad from {scavengerhunt_roads} where scavengerhunt_id=? AND grouping_id != 0";
        $params = array($cm->instance);
        // Recojo todos los groupings disponibles en la actividad.
        $availablegroupings = $DB->get_records_sql($query, $params);
        // Para cada grouping saco los grupos que contiene y compruebo si el usuario pertenece a uno de ellos.
        foreach ($availablegroupings as $groupingid) {
            if (count($allgroupsingrouping = groups_get_all_groups($courseid, $userid, $groupingid->grouping_id, 'g.id'))) {
                foreach ($allgroupsingrouping as $groupingrouping) {
                    array_push($groups, (object) array('group_id' => $groupingrouping->id, 'idroad' => $groupingid->idroad, 'validated' => $groupingid->validated));
                }
            }
        }
    } else {
        // Individual mode.
        $query = "SELECT  id as idroad, group_id,validated from {scavengerhunt_roads} where scavengerhunt_id=?";
        $params = array($cm->instance);
        $availablegroups = $DB->get_records_sql($query, $params);
        // If there is only one road validated and no groups.
        if (count($availablegroups) === 1 && current($availablegroups)->group_id == 0) {
            array_push($groups, current($availablegroups));
        } else {
            foreach ($availablegroups as $groupid) {
                if (groups_is_member($groupid->group_id)) {
                    $groupid->group_id = 0;
                    array_push($groups, $groupid);
                }
            }
        }
    }
    $returnurl = new moodle_url('/mod/scavengerhunt/view.php', array('id' => $cm->id));
    if (count($groups) === 0) {
        //No pertenece a ningun grupo
        print_error('noteamplay', 'scavengerhunt', $returnurl);
    } else if (count($groups) > 1) {
        //Pertenece a mas de un grupo
        print_error('multipleteamsplay', 'scavengerhunt', $returnurl);
    } else {
        //Bien
        if ($groups[0]->validated == 0) {
            // El camino no esta validado.
            print_error('invalidassignedroad', 'scavengerhunt', $returnurl);
        }

        return $groups[0];
    }
}

function get_list_participants_and_attempts_in_roads($cm, $courseid, $context) {
    global $DB;

    $roads = array();
    if ($cm->groupmode) {
        // Group mode.
        $query = "SELECT id as roadid,grouping_id,validated, name as roadname, (SELECT MAX(num_riddle) FROM {scavengerhunt_riddles} where road_id = r.id) as totalriddles from {scavengerhunt_roads} r where scavengerhunt_id=?";
        $params = array($cm->instance);
        // Recojo todos los groupings disponibles en la actividad.
        $availablegroupings = $DB->get_records_sql($query, $params);
        // Para cada grouping saco los grupos que contiene.
        foreach ($availablegroupings as $groupingid) {
            if ($groupingid->grouping_id == 0) {
                $groupingid->grouping_id = -1;
            }
            $userlist = groups_get_all_groups($courseid, null, $groupingid->grouping_id);
            $roads = add_road_userlist($roads, $groupingid, $userlist, $cm->groupmode);
        }
    } else {
        // Individual mode.
        $query = "SELECT id as roadid,validated, group_id, name as roadname,  (SELECT MAX(num_riddle) FROM {scavengerhunt_riddles} where road_id = r.id)  as totalriddles from {scavengerhunt_roads} r where scavengerhunt_id=?";
        $params = array($cm->instance);
        $availablegroups = $DB->get_records_sql($query, $params);
        // If there is only one road validated and no groups.
        if (count($availablegroups) === 1 && current($availablegroups)->group_id == 0) {
            $userlist = get_enrolled_users($context);
            $roads = add_road_userlist($roads, current($availablegroups), $userlist, $cm->groupmode);
        } else {
            foreach ($availablegroups as $groupid) {
                $userlist = groups_get_members($groupid->group_id);
                $roads = add_road_userlist($roads, $groupid, $userlist, $cm->groupmode);
            }
        }
    }
    return $roads;
}

function get_strings_play() {

    return get_strings(array("discoveredriddle", "failedlocation", "riddlename",
        "riddledescription", "timelabelfailed",
        "timelabelsuccess", "searching", "continue", "noattempts"
        , "noresults", "startfromhere", "nomarks", "updates"), "mod_scavengerhunt");
}
function get_strings_edit() {
    return get_strings(array('insert_riddle', 'insert_road', 'empty_ridle'), 'mod_scavengerhunt');
}

function get_last_timestamps($userid, $groupmode, $idgroup, $roadid) {
    global $DB;
    if ($groupmode) {
        $group_type = 'group_id';
        $params = array($idgroup, $roadid, $roadid);
    } else {
        $group_type = 'user_id';
        $params = array($userid, $roadid, $roadid);
    }
    // Recupero la ultima marca de tiempo realizada para esta instancia por el grupo/usuario y
    // la ultima marca de tiempo de modificacion del camino.
    $query = "SELECT max(a.timecreated) as attempttimestamp, max(r.timemodified) as roadtimestamp FROM {scavengerhunt_attempts} a, {scavengerhunt_roads} r WHERE a.$group_type=? AND a.road_id=? AND r.id=?";
    $timestamp = $DB->get_record_sql($query, $params);
    return array(intval($timestamp->attempttimestamp), intval($timestamp->roadtimestamp));
}

function check_timestamp($timestamp, $groupmode, $idgroup, $userid, $roadid) {
    global $DB;
    $return = new stdClass();
    $return->strings = [];
    $return->success = false;
    if ($groupmode) {
        $group_type = 'group_id';
        $params = array($timestamp, $idgroup, $roadid);
    } else {
        $group_type = 'user_id';
        $params = array($timestamp, $userid, $roadid);
    }
    list($return->attempttimestamp, $return->roadtimestamp) = get_last_timestamps($userid, $groupmode, $idgroup, $roadid);
    if ($return->attempttimestamp > $timestamp) {
        // Recupero las acciones del usuario/grupo superiores a un timestamp dado.
        $query = "SELECT a.timecreated,a.success,r.num_riddle,a.user_id as user FROM {scavengerhunt_riddles} r INNER JOIN {scavengerhunt_attempts} a ON a.riddle_id=r.id WHERE a.timecreated >? AND $group_type=? AND a.road_id=? ORDER BY a.timecreated ASC";
        $attempts = $DB->get_records_sql($query, $params);
        foreach ($attempts as $attempt) {
            if ($attempt->success) {
                $return->success = true;
            }
            $return->strings[] = set_string_attempt($attempt, $userid);
        }
    }
    return $return;
}

function view_user_historical_attempts($groupmode, $idgroup, $userid, $roadid, $cmid) {
    global $DB, $PAGE;
    $attempts = [];
    if ($groupmode) {
        $group_type = 'group_id';
        $params = array($idgroup, $roadid);
    } else {
        $group_type = 'user_id';
        $params = array($userid, $roadid);
    }
    // Recupero todas las acciones de un usuario/grupo y las imprimo en una tabla.
    $query = "SELECT a.id,a.timecreated,a.success,r.num_riddle,a.user_id as user FROM {scavengerhunt_riddles} r INNER JOIN {scavengerhunt_attempts} a ON a.riddle_id=r.id WHERE $group_type=? AND a.road_id=? ORDER BY a.timecreated ASC";
    $results = $DB->get_records_sql($query, $params);
    foreach ($results as $result) {
        $attempt = new stdClass();
        $attempt->string = set_string_attempt($result, $userid);
        $attempt->success = $result->success;
        $attempts[] = $attempt;
    }
    $output = $PAGE->get_renderer('mod_scavengerhunt');
    $renderable = new scavengerhunt_user_historical_attempts($attempts, $cmid);
    return $output->render($renderable);
}

function view_users_progress_table($cm, $courseid, $context) {
    global $PAGE;

    // Recojo la lista de usuarios/grupos asignada a cada camino.
    $roads = get_list_participants_and_attempts_in_roads($cm, $courseid, $context);
    $output = $PAGE->get_renderer('mod_scavengerhunt');
    $renderable = new scavengerhunt_users_progress($roads, $cm->groupmode, $cm->id);
    return $output->render($renderable);
}


function set_string_attempt($attempt, $userid) {
    global $DB;
    $attempt->date = userdate($attempt->timecreated);
    if ($userid != $attempt->user) {
        $attempt->user = get_user_fullname_from_id($attempt->user);
        if ($attempt->success) {
            return get_string('groupattemptovercome', 'scavengerhunt', $attempt);
        } else {
            return get_string('groupattemptfailed', 'scavengerhunt', $attempt);
        }
    } else {
        if ($attempt->success) {
            return get_string('userattemptovercome', 'scavengerhunt', $attempt);
        } else {
            return get_string('userattemptfailed', 'scavengerhunt', $attempt);
        }
    }
}

function add_road_userlist($roads, $data, $userlist, $groupmode) {
    $road = new stdClass();
    $road->id = $data->roadid;
    $road->name = $data->roadname;
    $road->validated = $data->validated;
    $road->userlist = $userlist;
    insert_riddle_progress_in_road_userlist($road, $groupmode);
    $road->totalriddles = $data->totalriddles;
    $roads[$road->id] = $road;
    return $roads;
}

function view_intro($scavengerhunt) {
    if ($scavengerhunt->alwaysshowdescription ||
            time() > $scavengerhunt->allowsubmissionsfromdate) {
        return true;
    }
    return false;
}

function insert_riddle_progress_in_road_userlist($road, $groupmode) {
    global $DB;
    foreach ($road->userlist as $user) {
        if ($groupmode) {
            $query = "SELECT a.id,r.num_riddle,COUNT(a.success) as attemptsnumber,
                (SELECT at.success FROM {scavengerhunt_riddles} ri INNER JOIN {scavengerhunt_attempts} at 
                ON at.riddle_id=ri.id WHERE ri.num_riddle=r.num_riddle+1 AND ri.road_id=r.road_id 
                AND at.group_id=a.group_id GROUP BY ri.num_riddle)  as success 
                FROM {scavengerhunt_riddles} r INNER JOIN {scavengerhunt_attempts} a 
                ON a.riddle_id=r.id INNER JOIN {scavengerhunt_roads} ro 
                ON ro.id=r.road_id WHERE (
                r.road_id= ? AND a.group_id = ?) 
                GROUP BY r.num_riddle ORDER BY  a.timecreated ASC";
        } else {
            $query = "SELECT a.id,r.num_riddle,COUNT(a.success) as attemptsnumber,
                (SELECT at.success FROM {scavengerhunt_riddles} ri INNER JOIN {scavengerhunt_attempts} at 
                ON at.riddle_id=ri.id WHERE ri.num_riddle=r.num_riddle+1 AND ri.road_id=r.road_id 
                AND at.group_id=a.group_id GROUP BY ri.num_riddle)  as success 
                FROM {scavengerhunt_riddles} r INNER JOIN {scavengerhunt_attempts} a 
                ON a.riddle_id=r.id INNER JOIN {scavengerhunt_roads} ro 
                ON ro.id=r.road_id WHERE (
                r.road_id= ? AND a.user_id = ? AND a.group_id = 0) 
                GROUP BY r.num_riddle ORDER BY  a.timecreated ASC";
        }
        $params = array($road->id, $user->id);
        $attempts = $DB->get_records_sql($query, $params);
        $user->ratings = array();
        // Anado a cada usuario/grupo su calificacion en color de cada pista.
        foreach ($attempts as $attempt) {
            $rating = new stdClass();
            $rating->riddlenum = $attempt->num_riddle;
            if ($attempt->attemptsnumber > 1 && $attempt->success) {
                $rating->class = "successwithfailures";
            } else if ($attempt->attemptsnumber > 1) {
                $rating->class = "failure";
            } else if ($attempt->success) {
                $rating->class = "successwithoutfailures";
            } else {
                $rating->class = "noattempt";
            }
            $user->ratings[$rating->riddlenum] = $rating;
        }
    }
}

function get_user_fullname_from_id($id) {
    global $DB;
    $select = 'SELECT id,firstnamephonetic,lastnamephonetic,middlename,alternatename,firstname,lastname FROM {user} WHERE id = ?';
    $result = $DB->get_records_sql($select, array($id));
    return fullname($result[$id]);
}
