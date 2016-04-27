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
    $parms = array($name, $description, $descriptionformat, $descriptiontrust, $timemodified, $activitytoend, $idRiddle);
    $DB->execute($sql, $parms);
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
    $parms = array($numRiddle, $geometryWKT, $timemodified, $idRiddle);
    $DB->execute($sql, $parms);
    setValidRoad($road_id);
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

function deleteEntryBD($id, $context) {
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
    $parms = array($riddle_result->road_id, $riddle_result->num_riddle);
    $DB->execute($sql, $parms);
    setValidRoad($riddle_result->road_id);
}

function getScavengerhunt($idScavengerhunt, $context) {
    global $DB;
//Recojo todas las features
    $riddles_sql = 'SELECT riddle.id, riddle.name, riddle.description, road_id, num_riddle,astext(geom) as geometry FROM {scavengerhunt_riddles} AS riddle'
            . ' inner join {scavengerhunt_roads} AS roads on riddle.road_id = roads.id WHERE scavengerhunt_id = ? ORDER BY num_riddle DESC';
    $riddles_result = $DB->get_records_sql($riddles_sql, array($idScavengerhunt));
    $geojson = riddles_to_geojson($riddles_result, $context, $idScavengerhunt);
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
        return $DB->insert_record($table, array('scavengerhunt_id' => $idScavengerhunt, 'user_id' => $userid, 'lockedat' => $time));
    }
}

function isLockScavengerhunt($idScavengerhunt, $userid) {
    global $DB;
    deleteOldLocks($idScavengerhunt);
    $select = "scavengerhunt_id = ? AND lockedat > ? AND user_id != ?";
    $params = array($idScavengerhunt, time(), $userid);
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

function checkLock($idScavengerhunt, $idLock, $userid) {
    if (!isLockScavengerhunt($idScavengerhunt, $userid) && idLockIsValid($idLock)) {
        return true;
    } else {
        return false;
    }
}

function checkRiddle($userid, $idgroup, $idRoad, $point, $groupmode, $course) {
    global $DB;
    $return = new stdClass();
    $location = object_to_wkt($point);
    if ($groupmode) {
        $group_type = 'group_id';
        $params = array($idgroup, $idRoad, $idRoad);
    } else {
        $group_type = 'user_id';
        $params = array($userid, $idRoad, $idRoad);
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
    $params = array($location, $nextnumriddle, $idRoad);
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
            $params = array($idRoad, $pointIdRiddle, $return->attempttimestamp,
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

function get_user_progress($idRoad, $groupmode, $idgroup, $userid, $idScavengerhunt, $context) {
    global $DB;
    if ($groupmode) {
        $group_type = 'group_id';
        $params = array($idgroup, $idRoad);
    } else {
        $group_type = 'user_id';
        $params = array($userid, $idRoad);
    }
    // Recupero las pistas descubiertas y fallos cometidos por el usuario/grupo para esta instancia.
    $query = "SELECT a.timecreated,a.user_id as user ,r.name,IF(a.success=0,NULL,r.id) as id,IF(a.success=0,NULL,r.description) as description" .
            ",r.num_riddle,  astext(a.locations) as geometry,r.road_id,a.success from {scavengerhunt_riddles} r INNER JOIN {scavengerhunt_attempts} a ON a.riddle_id=r.id where a." . $group_type . "=(?) AND a.road_id=(?) ORDER BY r.num_riddle DESC";
    $user_progress = $DB->get_records_sql($query, $params);
    // Si no tiene ningun progreso mostrar primera pista del camino para comenzar.
    if (count($user_progress) === 0) {
        $query = "SELECT num_riddle -1,astext(geom) as geometry,road_id from {scavengerhunt_riddles}  where  road_id=? and num_riddle=1";
        $params = array($idRoad);
        $user_progress = $DB->get_records_sql($query, $params);
    } else {
        // Recupero la ultima pista acertada
        foreach ($user_progress as $riddle) {
            if ($riddle->success) {
                $lastsuccess = new stdClass();
                $lastsuccess->name = $riddle->name;
                $lastsuccess->description = file_rewrite_pluginfile_urls($riddle->description, 'pluginfile.php', $context->id, 'mod_scavengerhunt', 'description', $riddle->id);
                break;
            }
        }
    }
    $geojson = riddles_to_geojson($user_progress, $context, $idScavengerhunt, $userid);
    return array($geojson, $lastsuccess);
}

function is_valid_road($idRoad) {
    global $DB;

    $query = "SELECT geom as geometry from {scavengerhunt_riddles} where road_id = ?";
    $params = array($idRoad);
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

function get_user_group_and_road($userid, $idScavengerhunt, $cm, $courseid) {
    global $DB;

    $groups = array();

    if ($cm->groupmode) {
        // Group mode.
        $query = "SELECT grouping_id,validated, id as idroad from {scavengerhunt_roads} where scavengerhunt_id=? AND grouping_id != 0";
        $params = array($idScavengerhunt);
        $availablegroupings = $DB->get_records_sql($query, $params);
        foreach ($availablegroupings as $groupingid) {
            if (count($allgroupsingrouping = groups_get_all_groups($courseid, $userid, $groupingid->grouping_id, 'g.id'))) {
                foreach ($allgroupsingrouping as $groupingrouping) {
                    array_push($groups, (object) array('groupmode' => $cm->groupmode, 'group_id' => $groupingrouping->id, 'idroad' => $groupingid->idroad, 'validated' => $groupingid->validated));
                }
            }
        }
    } else {
        // Individual mode.
        $query = "SELECT group_id,validated, id as idroad from {scavengerhunt_roads} where scavengerhunt_id=?";
        $params = array($idScavengerhunt);
        $availablegroups = $DB->get_records_sql($query, $params);
        // If there is only one road validated and no groups.
        if (count($availablegroups) === 1 && isset($availablegroups[0])) {
            $availablegroups[0]->groupmode = $cm->groupmode;
            array_push($groups, $availablegroups[0]);
        } else {
            foreach ($availablegroups as $groupid) {
                if (groups_is_member($groupid->group_id)) {
                    $groupid->groupmode = $cm->groupmode;
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
            // El camino no está validado.
            print_error('invalidroad', 'scavengerhunt', $returnurl);
        }

        return $groups[0];
    }
}

function get_strings_play() {

    return get_strings(array("discoveredriddle", "failedlocation", "riddlename",
        "riddledescription", "timelabelfailed",
        "timelabelsuccess", "searching", "continue"
        , "noresults", "startfromhere", "nomarks", "updates"), "mod_scavengerhunt");
}

function get_last_timestamps($userid, $groupmode, $idgroup, $idRoad) {
    global $DB;
    if ($groupmode) {
        $group_type = 'group_id';
        $params = array($idgroup, $idRoad, $idRoad);
    } else {
        $group_type = 'user_id';
        $params = array($userid, $idRoad, $idRoad);
    }
    // Recupero la ultima marca de tiempo realizada para esta instancia por el grupo/usuario y
    // la ultima marca de tiempo de modificacion del camino.
    $query = "SELECT max(a.timecreated) as attempttimestamp, max(r.timemodified) as roadtimestamp FROM {scavengerhunt_attempts} a, {scavengerhunt_roads} r WHERE a.$group_type=? AND a.road_id=? AND r.id=?";
    $timestamp = $DB->get_record_sql($query, $params);
    return array(intval($timestamp->attempttimestamp), intval($timestamp->roadtimestamp));
}

function check_timestamp($timestamp, $groupmode, $idgroup, $userid, $idRoad) {
    global $DB;
    $return = new stdClass();
    $return->strings = [];
    $return->success = false;
    if ($groupmode) {
        $group_type = 'group_id';
        $params = array($timestamp, $idgroup, $idRoad);
    } else {
        $group_type = 'user_id';
        $params = array($timestamp, $userid, $idRoad);
    }
    list($return->attempttimestamp, $return->roadtimestamp) = get_last_timestamps($userid, $groupmode, $idgroup, $idRoad);
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

function view_user_historical_attempts($groupmode, $idgroup, $userid, $idRoad,$cmid) {
    global $DB, $PAGE;
    $strings = [];
    if ($groupmode) {
        $group_type = 'group_id';
        $params = array($idgroup, $idRoad);
    } else {
        $group_type = 'user_id';
        $params = array($userid, $idRoad);
    }
    // Recupero todas las acciones de un usuario/grupo y las imprimo en una tabla.
    $query = "SELECT a.timecreated,a.success,r.num_riddle,a.user_id as user FROM {scavengerhunt_riddles} r INNER JOIN {scavengerhunt_attempts} a ON a.riddle_id=r.id WHERE $group_type=? AND a.road_id=? ORDER BY a.timecreated ASC";
    $attempts = $DB->get_records_sql($query, $params);
    foreach ($attempts as $attempt) {
        $strings[] = set_string_attempt($attempt, $userid);
    }
    $output = $PAGE->get_renderer('mod_scavengerhunt');
    $renderable = new scavengerhunt_user_historical_attempts($strings,$cmid);
    return $output->render($renderable);
}

function set_string_attempt($attempt, $userid) {
    global $DB;
    $attempt->date = userdate($attempt->timecreated);
    if ($userid != $attempt->user) {
        $select = 'SELECT id,firstnamephonetic,lastnamephonetic,middlename,alternatename,firstname,lastname FROM {user} WHERE id = ?';
        $result = $DB->get_records_sql($select, array($attempt->user));
        $fullname = fullname($result[$attempt->user]);
        $attempt->user = $fullname;
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
