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
 * Internal library of functions for module treasurehunt
 *
 * All the treasurehunt specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package    mod_treasurehunt
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once("$CFG->dirroot/mod/treasurehunt/lib.php");
require_once(dirname(__FILE__) . '/GeoJSON/GeoJSON.class.php');
require_once($CFG->dirroot . '/mod/treasurehunt/renderable.php');


//Cargo las clases necesarias de un objeto GeoJSON
spl_autoload_register(array('GeoJSON', 'autoload'));
/*
 * Does something really useful with the passed things
 *
 * @param array $things
 * @return object
 * function treasurehunt_do_something_useful(array $things) {
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

/**
 * Check if a point in inside a multipolygon geometry
 * @param type $mpolygon_wkt
 * @param type $point_wkt
 * @return boolean
 */
function check_point_in_multipolygon($mpolygon, $point) {
    $polygons = $mpolygon->getComponents();
    foreach ($polygons as $polygon) {
        if ($polygon instanceof Polygon) {
            $result = $polygon->pointInPolygon($point);
            if ($result) {
                return true;
            };
        }
    }
    return false;
}

/* ------------------------------------------------------------------------------ */

/**
 * @return array int => lang string the options for calculating the quiz grade
 *      from the individual attempt grades.
 */
function treasurehunt_get_grading_options() {
    return array(
        TREASUREHUNT_GRADEFROMRIDDLES => get_string('gradefromriddles', 'treasurehunt'),
        TREASUREHUNT_GRADEFROMTIME => get_string('gradefromtime', 'treasurehunt'),
        TREASUREHUNT_GRADEFROMPOSITION => get_string('gradefromposition', 'treasurehunt')
    );
}

function insert_riddle_form(stdClass $entry) {
    GLOBAL $DB;
    $number = $DB->get_record_sql('SELECT count(id) + 1 as number FROM '
            . '{treasurehunt_riddles} where roadid = (?)', array($entry->roadid));
    $entry->number = $number->number;

    $id = $DB->insert_record("treasurehunt_riddles", $entry);
    //Como he insertado una nueva pista sin geometrias pongo el camino como no valido
    set_valid_road($entry->roadid, false);
    return $id;
}

function update_geometry_and_position_of_riddle(Feature $feature, $context) {
    GLOBAL $DB;
    $riddle = new stdClass();
    $riddle->number = $feature->getProperty('noriddle');
    $riddle->roadid = $feature->getProperty('roadid');
    $geometry = $feature->getGeometry();
    $riddle->geom = object_to_wkt($geometry);
    $riddle->timemodified = time();
    $riddle->id = $feature->getId();
    $parms = array('id' => $riddle->id);
    $entry = $DB->get_record('treasurehunt_riddles', $parms, 'id,number', MUST_EXIST);
    if (check_road_is_blocked($riddle->roadid) && ($riddle->number != $entry->number)) {
        // No se puede cambiar el numero de pista una vez bloqueado el camino.
        print_error('notchangeorderriddle', 'treasurehunt');
    }
    // Si intento salvar una pista sin geometria devuelvo error
    if (count($geometry->getComponents()) === 0) {
        print_error('saveemptyridle', 'treasurehunt');
    }
    $DB->update_record('treasurehunt_riddles', $riddle);
    set_valid_road($riddle->roadid);
    // Trigger update riddle event.
    $eventparams = array(
        'context' => $context,
        'objectid' => $riddle->id
    );
    \mod_treasurehunt\event\riddle_updated::create($eventparams)->trigger();
}

function delete_riddle($id, $context) {
    GLOBAL $DB;
    $riddle_result = $DB->get_record('treasurehunt_riddles', array('id' => $id), 'number,roadid', MUST_EXIST);
    if (check_road_is_blocked($riddle_result->roadid)) {
        // No se puede borrar una pista de un camino empezado.
        print_error('notdeleteriddle', 'treasurehunt');
    }

    $DB->delete_records('treasurehunt_riddles', array('id' => $id));
    $DB->delete_records('treasurehunt_attempts', array('riddleid' => $id));
    $sql = 'UPDATE {treasurehunt_riddles} '
            . 'SET number = number - 1 WHERE roadid = (?) AND number > (?)';
    $params = array($riddle_result->roadid, $riddle_result->number);
    $DB->execute($sql, $params);
    set_valid_road($riddle_result->roadid);
    // Trigger deleted riddle event.
    $eventparams = array(
        'context' => $context,
        'objectid' => $id,
    );
    \mod_treasurehunt\event\riddle_deleted::create($eventparams)->trigger();
}

function delete_road($roadid, $context) {
    GLOBAL $DB;
    $DB->delete_records('treasurehunt_roads', array('id' => $roadid));
    $params = array($roadid);
    $riddles = $DB->get_records_sql('SELECT id FROM {treasurehunt_riddles} WHERE roadid = ?'
            , $params);
    foreach ($riddles as $riddle) {
        $DB->delete_records_select('treasurehunt_attempts', 'riddleid = ?', array($riddle->id));
        $DB->delete_records_select('treasurehunt_answers', 'riddleid = ?', array($riddle->id));
    }
    $DB->delete_records_select('treasurehunt_riddles', 'roadid = ?', $params);
    // Trigger deleted road event.
    $eventparams = array(
        'context' => $context,
        'objectid' => $roadid
    );
    \mod_treasurehunt\event\road_deleted::create($eventparams)->trigger();
}

function get_total_roads($treasurehuntid) {
    GLOBAL $DB;
    $number = $DB->count_records('treasurehunt_roads', array('treasurehuntid' => $treasurehuntid));
    return $number;
}

function get_total_riddles($roadid) {
    GLOBAL $DB;
    $number = $DB->count_records('treasurehunt_riddles', array('roadid' => $roadid));
    return $number;
}

function check_if_user_has_finished($userid, $groupid, $roadid) {
    GLOBAL $DB;
    if ($groupid) {
        $grouptype = 'a.groupid=(?)';
        $params = array($roadid, $groupid);
    } else {
        $grouptype = 'a.groupid=0 AND a.userid=(?)';
        $params = array($roadid, $userid);
    }
    $sql = "SELECT MAX(a.timecreated) as finished FROM "
            . "{treasurehunt_attempts} a INNER JOIN {treasurehunt_riddles} r "
            . "ON r.id = a.riddleid WHERE a.success=1 AND r.number=(SELECT "
            . "max(ri.number) FROM {treasurehunt_riddles} ri where "
            . "ri.roadid=r.roadid) AND r.roadid = ? "
            . "AND a.type='location' AND  $grouptype";
    $finished = $DB->get_record_sql($sql, $params);
    if (isset($finished->finished)) {
        return true;
    } else {
        return false;
    }
}

/**
 * 
 * @param moodle_database $DB
 * @return string
 * @deprecated since version 1.0
 */
function get_geometry_functions(moodle_database $DB) {
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

function set_valid_road($roadid, $valid = null) {
    GLOBAL $DB;
    $road = new stdClass();
    $road->id = $roadid;
    $road->timemodified = time();
    if (is_null($valid)) {
        $road->validated = is_valid_road($roadid);
    } else {
        $road->validated = $valid;
    }
    $DB->update_record("treasurehunt_roads", $road);
}

function check_road_is_blocked($roadid) {
    global $DB;
    $sql = "SELECT at.success "
            . "FROM {treasurehunt_attempts} at INNER JOIN {treasurehunt_riddles} ri "
            . "ON ri.id = at.riddleid INNER JOIN {treasurehunt_roads} r "
            . "ON ri.roadid=r.id WHERE r.id=?";
    $params = array($roadid);
    return $DB->record_exists_sql($sql, $params);
}

function get_treasurehunt($treasurehuntid, $context) {
    global $DB;

//Recojo todas las features
    $riddlessql = "SELECT riddle.id, "
            . "riddle.name, riddle.description, roadid, number,"
            . "geom as geometry FROM {treasurehunt_riddles} AS riddle"
            . " inner join {treasurehunt_roads} AS roads on riddle.roadid = roads.id"
            . " WHERE treasurehuntid = ? ORDER BY number DESC";
    $riddlesresult = $DB->get_records_sql($riddlessql, array($treasurehuntid));
    $geojson = riddles_to_geojson($riddlesresult, $context, $treasurehuntid);
    // Recojo todos los caminos, los bloqueo en cuanto exista un intento.
    $roadssql = "SELECT id, name, CASE WHEN (SELECT COUNT(at.id) "
            . "FROM {treasurehunt_attempts} at INNER JOIN {treasurehunt_riddles} ri "
            . "ON ri.id = at.riddleid INNER JOIN {treasurehunt_roads} r "
            . "ON ri.roadid=r.id WHERE r.id= road.id) > 0 THEN 1 ELSE 0 "
            . "END AS blocked FROM {treasurehunt_roads} AS road where treasurehuntid = ?";
    $roadsresult = $DB->get_records_sql($roadssql, array($treasurehuntid));
    foreach ($roadsresult as &$value) {
        $value->id = intval($value->id);
        $value->blocked = intval($value->blocked);
    }
    $roadsjson = json_encode($roadsresult);
    $fetchstagereturns = array($geojson, $roadsjson);
    return $fetchstagereturns;
}

function renew_edition_lock($treasurehuntid, $userid) {
    global $DB;

    $table = 'treasurehunt_locks';
    $params = array('treasurehuntid' => $treasurehuntid, 'userid' => $userid);
    $time = time() + get_setting_lock_time();
    $lock = $DB->get_record($table, $params);

    if (!empty($lock)) {
        $DB->update_record($table, array('id' => $lock->id, 'lockedat' => $time));
        return $lock->id;
    } else {
        delete_old_locks($treasurehuntid);
        return $DB->insert_record($table, array('treasurehuntid' => $treasurehuntid, 'userid' => $userid, 'lockedat' => $time));
    }
}

function get_setting_lock_time() {
    if (($locktimeediting = get_config('mod_treasurehunt', 'locktimeediting')) > 5) {
        return $locktimeediting;
    } else {
        return TREASUREHUNT_LOCKTIME;
    }
}

function get_setting_game_update_time() {
    if (($gameupdatetime = get_config('mod_treasurehunt', 'gameupdatetime')) > 0) {
        return $gameupdatetime;
    } else {
        return TREASUREHUNT_GAMEUPDATETIME;
    }
}

function is_edition_loked($treasurehuntid, $userid) {
    global $DB;
    $select = "treasurehuntid = ? AND lockedat > ? AND userid != ?";
    $params = array($treasurehuntid, time(), $userid);
    return $DB->record_exists_select('treasurehunt_locks', $select, $params);
}

function edition_lock_id_is_valid($lockid) {
    global $DB;
    return $DB->record_exists_select('treasurehunt_locks', "id = ?", array($lockid));
}

function get_username_blocking_edition($treasurehuntid) {
    global $DB;
    $table = 'treasurehunt_locks';
    $params = array('treasurehuntid' => $treasurehuntid);
    $result = $DB->get_record($table, $params);
    return get_user_fullname_from_id($result->userid);
}

function delete_old_locks($treasurehuntid) {
    global $DB;
    $DB->delete_records_select('treasurehunt_locks', "lockedat < ? AND treasurehuntid = ? ", array(time(), $treasurehuntid));
}

function check_user_location($userid, $groupid, $roadid, $point, $context, $treasurehunt, $noriddles) {
    global $DB;
    $return = new stdClass();
    $return->update = '';
    $return->roadfinished = false;
    $locationwkt = object_to_wkt($point);
    // Recupero los datos del ultimo intento con geometria acertada para saber si tiene geometria resuelta y no esta superada.
    $currentriddle = get_last_successful_attempt($userid, $groupid, $roadid);
    if ($currentriddle->success || !$currentriddle) {
        $return->newattempt = true;
        if ($currentriddle) {
            $nextnoriddle = $currentriddle->number + 1;
        } else {
            $nextnoriddle = 1;
        }
        // Compruebo si la geometria esta dentro.
//        $geomfuncs = get_geometry_functions($DB);
//        $query = "SELECT id,questiontext,activitytoend, {$geomfuncs['ST_Intersects']}({$geomfuncs['ST_GeomFromText']}(geom),{$geomfuncs['ST_GeomFromText']}"
//                . "((?))) as inside,number from {treasurehunt_riddles} where number=(?) and roadid=(?)";
//        $params = array($locationwkt, $nextnoriddle, $roadid);
//        $nextriddle = $DB->get_record_sql($query, $params);
        $nextriddle = $DB->get_record('treasurehunt_riddles', array('number' => $nextnoriddle, 'roadid' => $roadid), '*', MUST_EXIST);
        $inside = check_point_in_multipolygon(wkt_to_object($nextriddle->geom), $point);
        // Si esta dentro
        if ($inside) {
            $nextriddle->inside = 1;
            $questionsolved = ($nextriddle->questiontext === '' ? true : false);
            $completionsolved = ($nextriddle->activitytoend == 0 ? true : false);
            if ($questionsolved && $completionsolved) {
                $success = true;
            } else {
                $success = false;
            }
            $penalty = false;
            $return->msg = get_string('successlocation', 'treasurehunt');
            $return->newriddle = true;
        } else {
            $nextriddle->inside = 0;
            $penalty = true;
            $questionsolved = false;
            $completionsolved = false;
            $success = false;
            $return->msg = get_string('faillocation', 'treasurehunt');
            $return->newriddle = false;
        }
        // Creo el attempt.
        $attempt = new stdClass();
        $attempt->riddleid = $nextriddle->id;
        $attempt->timecreated = time();
        $attempt->userid = $userid;
        $attempt->groupid = $groupid;
        $attempt->success = $success;
        $attempt->type = 'location';
        $attempt->completionsolved = $completionsolved;
        $attempt->questionsolved = $questionsolved;
        $attempt->geometrysolved = $nextriddle->inside;
        $attempt->location = $locationwkt;
        $attempt->penalty = $penalty;
        insert_attempt($attempt, $context);

        // Si el intento acierta la localizacion  y existe el completion compruebo si esta superado.
        if ($nextriddle->inside && !$completionsolved) {
            if ($usercompletion = check_completion_activity($nextriddle->activitytoend, $userid, $groupid, $context)) {
                $attempt->type = 'completion';
                $attempt->completionsolved = 1;
                $attempt->userid = $usercompletion;
                // Para que siga un orden cronologico;
                $attempt->timecreated +=1;
                if ($questionsolved) {
                    $attempt->success = 1;
                }
                insert_attempt($attempt, $context);
                // Si ya se ha superado inserto el attempt de localizacion.
                if ($questionsolved) {
                    $attempt->type = 'location';
                    // Para que siga un orden cronologico;
                    $attempt->timecreated +=1;
                    insert_attempt($attempt, $context);
                }
                $return->update = get_string('overcomeactivitytoend', 'treasurehunt', get_activity_to_end_link($nextriddle->activitytoend));
            }
        }
        if ($attempt->success && $nextnoriddle == $noriddles) {
            if ($treasurehunt->grademethod != TREASUREHUNT_GRADEFROMRIDDLES) {
                treasurehunt_update_grades($treasurehunt);
            } else {
                set_grade($treasurehunt, $groupid, $userid);
            }
            $return->roadfinished = true;
        } else {
            set_grade($treasurehunt, $groupid, $userid);
        }
        $return->attempttimestamp = $attempt->timecreated;
    } else {
        $return->newriddle = false;
        $return->newattempt = false;
        if (!$currentriddle->questionsolved && !$currentriddle->completionsolved) {
            $return->msg = get_string('mustcompleteboth', 'treasurehunt');
        } else if (!$currentriddle->questionsolved) {
            $return->msg = get_string('mustanswerquestion', 'treasurehunt');
        } else {
            $return->msg = get_string('mustcompleteactivity', 'treasurehunt');
        }
    }

    return $return;
}

function get_activity_to_end_link($activitytoend) {
    global $COURSE;
    if ($activitytoend != 0) {
        $modinfo = get_fast_modinfo($COURSE);
        $cmactivitytoend = $modinfo->get_cm($activitytoend);
        //
        return '<a title="' . $cmactivitytoend->name . '" data-ajax="false" '
                . 'href="' . $cmactivitytoend->url->__toString() . '">' . $cmactivitytoend->name . '</a>';
    } else {
        return '';
    }
}

function treasurehunt_is_available($treasurehunt) {
    $timenow = time();
    $return = new stdClass();
    $return->available = false;
    $return->outoftime = false;
    $return->actnotavailableyet = false;
    if ($timenow > $treasurehunt->cutoffdate && $treasurehunt->cutoffdate) {
        $return->outoftime = true;
    } else if ($treasurehunt->allowattemptsfromdate > $timenow) {
        $return->actnotavailableyet = true;
    } else {
        $return->available = true;
    }
    return $return;
}

function get_riddle_answers($riddleid, $context) {
    global $DB;

    $sql = "SELECT id,answertext from {treasurehunt_answers} WHERE riddleid = ?";
    $answers = $DB->get_records('treasurehunt_answers', array('riddleid' => $riddleid), '', 'id,answertext');
    foreach ($answers as &$answer) {
        $answer->answertext = file_rewrite_pluginfile_urls($answer->answertext, 'pluginfile.php', $context->id, 'mod_treasurehunt', 'answertext', $answer->id);
    }
    return $answers;
}

function riddles_to_geojson($riddles, $context, $treasurehuntid, $groupid = 0) {
    $riddlesarray = array();
    foreach ($riddles as $riddle) {
        $multipolygon = wkt_to_object($riddle->geometry);
        if (isset($riddle->description)) {
            $description = file_rewrite_pluginfile_urls($riddle->description, 'pluginfile.php', $context->id, 'mod_treasurehunt', 'description', $riddle->id);
        } else {
            $description = null;
        }
        $attr = array('roadid' => intval($riddle->roadid),
            'noriddle' => intval($riddle->number),
            'name' => $riddle->name,
            'treasurehuntid' => $treasurehuntid,
            'description' => $description);
        if (property_exists($riddle, 'timecreated')) {
            $attr['date'] = $riddle->timecreated;
        }
        if (property_exists($riddle, 'geometrysolved') && property_exists($riddle, 'success')) {
            $attr['geometrysolved'] = intval($riddle->geometrysolved);
            $attr['success'] = intval($riddle->success);
            $riddle->type = "location";
            // Modifico el tipo a location
            $attr['info'] = set_string_attempt($riddle, $groupid);
        }
        $feature = new Feature($riddle->id ?
                        intval($riddle->id) : null, $multipolygon, $attr);
        array_push($riddlesarray, $feature);
    }
    $featurecollection = new FeatureCollection($riddlesarray);
    $geojson = object_to_geojson($featurecollection);
    return $geojson;
}

function get_locked_name_and_description($attempt, $context) {
    $return = new stdClass();
    $return->name = get_string('lockedriddle', 'treasurehunt');
    if (!$attempt->completionsolved) {
        $activitytoendname = get_activity_to_end_link($attempt->activitytoend);
    }
    if ((!$attempt->questionsolved && $attempt->questiontext !== '') &&
            (!$attempt->completionsolved && $attempt->activitytoend)) {
        $return->description = get_string('lockedqacriddle', 'treasurehunt', $activitytoendname);
    } else if (!$attempt->questionsolved && $attempt->questiontext !== '') {
        $return->description = get_string('lockedqriddle', 'treasurehunt');
    } else if (!$attempt->completionsolved && $attempt->activitytoend) {
        $return->description = get_string('lockedcpriddle', 'treasurehunt', $activitytoendname);
    } else {
        $return->name = $attempt->name;
        $return->description = file_rewrite_pluginfile_urls($attempt->description, 'pluginfile.php', $context->id, 'mod_treasurehunt', 'description', $attempt->riddleid);
    }
    return $return;
}

function set_grade($treasurehunt, $groupid, $userid) {
    if ($groupid == 0) {
        treasurehunt_update_grades($treasurehunt, $userid);
    } else {
        $userlist = groups_get_members($groupid);
        foreach ($userlist as $user) {
            treasurehunt_update_grades($treasurehunt, $user->id);
        }
    }
}

function get_user_progress($roadid, $groupid, $userid, $treasurehuntid, $context) {
    global $DB;

    // Recupero las pistas descubiertas y fallos cometidos por el usuario/grupo para esta instancia.
    if ($groupid) {
        $grouptype = 'a.groupid=(?)';
        $grouptypewithin = 'at.groupid=?';
        $params = array($roadid, $groupid, $roadid, $groupid);
    } else {
        $grouptype = 'a.groupid=0 AND a.userid=(?)';
        $grouptypewithin = 'at.groupid=0 AND at.userid=?';
        $params = array($roadid, $userid, $roadid, $userid);
    }
    $query = "SELECT a.id as attemptid,a.timecreated,a.userid as user,a.riddleid,CASE WHEN a.success = 0 "
            . "THEN NULL ELSE r.name END AS name, CASE WHEN a.success=0 THEN NULL ELSE "
            . "r.description END AS description,CASE WHEN a.geometrysolved=1 "
            . "THEN r.id ELSE null END as id,a.geometrysolved,r.number,apt.geometry,"
            . "r.roadid,a.success FROM (SELECT MAX(at.timecreated) AS maxtime,"
            . "at.location AS geometry FROM {treasurehunt_attempts} "
            . "at INNER JOIN {treasurehunt_riddles} ri ON ri.id=at.riddleid WHERE ri.roadid=? "
            . "AND $grouptypewithin group by geometry) apt INNER JOIN {treasurehunt_attempts} a ON "
            . "a.timecreated=apt.maxtime AND apt.geometry = a.location "
            . "INNER JOIN {treasurehunt_riddles} r ON a.riddleid=r.id WHERE r.roadid=? AND $grouptype";
    $userprogress = $DB->get_records_sql($query, $params);
    $geometrysolved = false;
    foreach ($userprogress as $attempt) {
        if ($attempt->geometrysolved) {
            $geometrysolved = true;
        }
    }
    // Si no tiene ningun progreso mostrar primera pista del camino para comenzar.
    if (count($userprogress) == 0 || !$geometrysolved) {
        $query = "SELECT number -1,geom as geometry,"
                . "roadid FROM {treasurehunt_riddles}  WHERE  roadid=? AND number=1";
        $params = array($roadid);
        $userprogress[] = $DB->get_record_sql($query, $params);
    }
    $geojson = riddles_to_geojson($userprogress, $context, $treasurehuntid, $groupid);
    return $geojson;
}

function is_valid_road($roadid) {
    global $DB;

    $query = "SELECT geom as geometry from {treasurehunt_riddles} where roadid = ?";
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

function check_completion_activity($cmid, $userid, $groupid, $context) {
    global $COURSE;
    $users = array();
    if ($cmid != 0) {
        $modinfo = get_fast_modinfo($COURSE);
        $cmactivitytoend = $modinfo->get_cm($cmid);
    } else {
        return true;
    }
    // Get all users.
    if ($groupid) {
        $users = get_enrolled_users($context, 'mod/treasurehunt:play', $groupid, 'u.id');
    } else {
        $user = new stdClass();
        $user->id = $userid;
        $users [] = $user;
    }
    foreach ($users as $user) {
        // Check if a user has complete that activity.
        $completioninfo = new completion_info($COURSE);
        $current = $completioninfo->get_data($cmactivitytoend, false, $user->id);
        if ($current->completionstate == 1) {
            return $user->id;
        }
    }
    return false;
}

function get_group_road($groupid, $treasurehuntid, $groupame = '') {
    global $DB;

    $query = "SELECT r.id as roadid, r.validated, gg.groupid "
            . "FROM  {treasurehunt_roads} r INNER JOIN {groupings_groups} "
            . "gg ON gg.groupingid = r.groupingid  WHERE gg.groupid =? AND r.treasurehuntid=?";
    $params = array($groupid, $treasurehuntid);
    // Recojo todos los groupings disponibles en la actividad.
    $groupdata = $DB->get_records_sql($query, $params);
    if (count($groupdata) === 0) {
        // El grupo no pertenece a ningun grouping.
        print_error('nogrouproad', 'treasurehunt', '', $groupame);
    } else if (count($groupdata) > 1) {
        // El grupo pertenece a mas de un grouping.
        print_error('groupmultipleroads', 'treasurehunt', '', $groupame);
    } else {
        if (current($groupdata)->validated == 0) {
            // El camino no esta validado.
            print_error('groupinvalidroad', 'treasurehunt', '', $groupame);
        }
        return current($groupdata);
    }
}

function get_user_group_and_road($userid, $treasurehunt, $cmid, $teacherreview = false, $username = '') {
    global $DB;

    $returnurl = new moodle_url('/mod/treasurehunt/view.php', array('id' => $cmid));
    if ($treasurehunt->groupmode) {
        // Group mode.
        $cond = "{groupings_groups} gg ON gg.groupingid = r.groupingid "
                . "INNER JOIN {groups_members} gm ON gm.groupid = gg.groupid";
    } else {
        // Individual mode.
        $cond = "{groups_members} gm ON gm.groupid = r.groupid";
    }
    $query = "SELECT r.id as roadid,count(r.id) as groupsnumber, "
            . "gm.groupid,r.validated FROM {treasurehunt_roads} r "
            . "INNER JOIN  $cond WHERE gm.userid =? AND "
            . "r.treasurehuntid=? group by roadid,gm.groupid";
    $params = array($userid, $treasurehunt->id);
    $userdata = $DB->get_records_sql($query, $params);
    // Si estamos en modo individual y no hay datos comprobamos si existe un unico camino
    // para la caza que no tenga grupos.
    if (count($userdata) === 0 && !$treasurehunt->groupmode) {
        $query = "SELECT r.id as roadid, r.validated,r.groupid FROM "
                . "{treasurehunt_roads} r WHERE r.treasurehuntid=?";
        $availableroads = $DB->get_records_sql($query, array($treasurehunt->id));
        if (count($availableroads) === 1 && current($availableroads)->groupid == 0) {
            $userdata [] = current($availableroads);
        }
    }
    if (count($userdata) === 0) {
        if ($treasurehunt->groupmode) {
            $errormsg = 'nogroupingplay';
        } else {
            $errormsg = 'nogroupplay';
        }
        if ($teacherreview) {
            $errormsg = 'nouserroad';
        }
        // El usuario no pertenece a ningun grupo.
        print_error($errormsg, 'treasurehunt', $returnurl, $username);
    } else if (count($userdata) > 1) {
        if ($treasurehunt->groupmode) {
            $errormsg = 'multiplegroupingsplay';
        } else {
            $errormsg = 'multiplegroupsplay';
        }
        if ($teacherreview) {
            $errormsg = 'usermultipleroads';
        }
        // El usuario pertenece a mas de un grupo.
        print_error($errormsg, 'treasurehunt', $returnurl, $username);
    } else {
        if ($treasurehunt->groupmode) {
            if (current($userdata)->groupsnumber > 1) {
                if ($teacherreview) {
                    $errormsg = 'usermultiplesameroad';
                } else {
                    $errormsg = 'multiplegroupssameroadplay';
                }
                // El usuario pertenece a mas de un grupo dentro de un mismo grouping.
                print_error($errormsg, 'treasurehunt', $returnurl, $username);
            }
        } else {
            current($userdata)->groupid = 0;
        }
        if (current($userdata)->validated == 0) {
            if ($teacherreview) {
                $errormsg = 'userinvalidroad';
            } else {
                $errormsg = 'invalidassignedroad';
            }
            // El camino no esta validado.
            print_error($errormsg, 'treasurehunt', $returnurl, $username);
        } else {
            return current($userdata);
        }
    }
}

function check_if_user_has_multiple_groups_or_roads($totalparticipants, $userlist, $duplicated, $grouping) {
    foreach ($userlist as $user) {
        if (!array_key_exists($user->id, $totalparticipants)) {
            $totalparticipants[$user->id] = $user;
        } else {
            if ($grouping) {
                $duplicated[$user->id] = $user->name;
            } else {
                $duplicated[$user->id] = fullname($user);
            }
        }
    }
    return array($totalparticipants, $duplicated);
}

function check_if_user_has_none_groups_and_roads($totalparticipants, $userlist, $noassignedusers) {
    foreach ($userlist as $user) {
        if (!array_key_exists($user->id, $totalparticipants)) {
            $noassignedusers[$user->id] = fullname($user);
        }
    }
    return $noassignedusers;
}

function get_list_participants_and_attempts_in_roads($cm, $courseid, $context) {
    global $DB;

    $roads = array();
    $totalparticipantsgroups = array();
    $totalparticipants = array();
    $duplicategroupsingroupings = array();
    $duplicateusersingroups = array();
    $noassignedusers = array();

    if ($cm->groupmode) {
        $grouptype = 'groupingid';
        $user = 'a.groupid';
        $groupid = 'a.groupid != 0';
        $groupidwithin = 'at.groupid=a.groupid';
    } else {
        $grouptype = 'groupid';
        $user = 'a.userid';
        $groupid = 'a.groupid=0';
        $groupidwithin = 'at.groupid=a.groupid AND at.userid=a.userid';
    }
    $attemptsquery = "SELECT a.id,$user as user,r.number, CASE WHEN EXISTS(SELECT 1 FROM "
            . "{treasurehunt_riddles} ri INNER JOIN {treasurehunt_attempts} at "
            . "ON at.riddleid=ri.id WHERE ri.number=r.number AND ri.roadid=r.roadid "
            . "AND $groupidwithin AND at.penalty=1) THEN 1 ELSE 0 end as withfailures, "
            . "CASE WHEN EXISTS(SELECT 1 FROM {treasurehunt_riddles} ri INNER JOIN "
            . "{treasurehunt_attempts} at ON at.riddleid=ri.id WHERE ri.number=r.number "
            . "AND ri.roadid=r.roadid AND $groupidwithin AND at.success=1 AND "
            . "at.type='location') THEN 1 ELSE 0 end as success FROM {treasurehunt_attempts} a INNER JOIN "
            . "{treasurehunt_riddles} r ON a.riddleid=r.id INNER JOIN {treasurehunt_roads} "
            . "ro ON r.roadid=ro.id WHERE ro.treasurehuntid=? AND $groupid group by r.number,user,a.id,r.roadid";
    $roadsquery = "SELECT id as roadid,$grouptype,validated, name as roadname, "
            . "(SELECT MAX(number) FROM {treasurehunt_riddles} where roadid "
            . "= r.id) as totalriddles from {treasurehunt_roads} r where treasurehuntid=?";
    $params = array($cm->instance);
    $attempts = $DB->get_records_sql($attemptsquery, $params);
    if ($cm->groupmode) {
        // Group mode.
        // Recojo todos los groupings disponibles en la actividad.
        $availablegroupings = $DB->get_records_sql($roadsquery, $params);
        // Para cada grouping saco los grupos que contiene.
        foreach ($availablegroupings as $groupingid) {
            if ($groupingid->groupingid == 0) {
                $groupingid->groupingid = -1;
            }
            $grouplist = groups_get_all_groups($courseid, null, $groupingid->groupingid);
            // Compruebo si existe mas de un camino asignado a cada grupo. Significa que hay grupos en mas de un grouping.
            list($totalparticipantsgroups,
                    $duplicategroupsingroupings) = check_if_user_has_multiple_groups_or_roads($totalparticipantsgroups, $grouplist, $duplicategroupsingroupings, true);
            $roads = add_road_userlist($roads, $groupingid, $grouplist, $attempts);
        }
        // Compruebo si existen participantes en mas de un grupo dentro del mismo camino. Significa que hay usuarios en mÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡s de un grupo dentro del mismo camino.
        foreach ($totalparticipantsgroups as $group) {
            list($totalparticipants,
                    $duplicateusersingroups) = check_if_user_has_multiple_groups_or_roads($totalparticipants, get_enrolled_users($context, 'mod/treasurehunt:play', $group->id), $duplicateusersingroups, false);
        }
    } else {
        // Individual mode.
        $availablegroups = $DB->get_records_sql($roadsquery, $params);
        // If there is only one road validated and no groups.
        if (count($availablegroups) === 1 && current($availablegroups)->groupid == 0) {
            $totalparticipants = get_enrolled_users($context, 'mod/treasurehunt:play');
            $roads = add_road_userlist($roads, current($availablegroups), $totalparticipants, $attempts);
        } else {
            foreach ($availablegroups as $groupid) {
                if ($groupid->groupid) {
                    $userlist = get_enrolled_users($context, 'mod/treasurehunt:play', $groupid->groupid);
                    // Compruebo si existe mas de un camino asignado a cada usuario. Significa que hay usuarios en mas de un grupo.
                    list($totalparticipants,
                            $duplicateusersingroups) = check_if_user_has_multiple_groups_or_roads($totalparticipants, $userlist, $duplicateusersingroups, false);
                } else {
                    $userlist = array();
                }
                $roads = add_road_userlist($roads, $groupid, $userlist, $attempts);
            }
        }
    }
    // Compruebo si algun usuario con acceso no puede realizar la actividad.
    $totalparticipantsincourse = get_enrolled_users($context, 'mod/treasurehunt:play');
    if ((count($totalparticipantsincourse) !== count($totalparticipants))) {
        $noassignedusers = check_if_user_has_none_groups_and_roads($totalparticipants, $totalparticipantsincourse, $noassignedusers);
    }
    return array($roads, $duplicategroupsingroupings, $duplicateusersingroups, $noassignedusers);
}

function get_strings_play() {

    return get_strings(array("overcomeriddle", "failedlocation", "riddlename",
        "riddledescription", "question", "noasnwerselected", "timeexceeded",
        "searching", "continue", "noattempts", "aerialview", "roadview"
        , "noresults", "startfromhere", "nomarks", "updates", "activitytoendwarning",
        "huntcompleted", "discoveredlocation", "answerwarning", "error"), "mod_treasurehunt");
}

function get_strings_edit() {
    return get_strings(array('riddle', 'road', 'add', 'modify', 'save',
        'remove', 'searchlocation', 'savewarning', 'removewarning',
        'areyousure', 'removeroadwarning', 'confirm', 'cancel'), 'mod_treasurehunt');
}

function get_last_timestamps($userid, $groupid, $roadid) {
    global $DB;
    // Recupero la ultima marca de tiempo realizada para esta instancia por el grupo/usuario y
    // la ultima marca de tiempo de modificacion del camino.
    if ($groupid) {
        $grouptype = 'a.groupid=(?)';
        $params = array($groupid, $roadid);
    } else {
        $grouptype = 'a.groupid=0 AND a.userid=(?)';
        $params = array($userid, $roadid);
    }
    $query = "SELECT coalesce(attempttimestamp,0) as attempttimestamp, "
            . "ro.timemodified as roadtimestamp FROM  {treasurehunt_roads} ro LEFT JOIN (SELECT "
            . "MAX(a.timecreated) as attempttimestamp, r.roadid FROM {treasurehunt_attempts} a INNER JOIN "
            . "{treasurehunt_riddles} r ON a.riddleid=r.id where $grouptype group by r.roadid) q "
            . "ON q.roadid = ro.id WHERE ro.id=?";
    $timestamp = $DB->get_record_sql($query, $params);
    if (!isset($timestamp->attempttimestamp)) {
        $timestamp->attempttimestamp = 0;
    }
    return array(intval($timestamp->attempttimestamp), intval($timestamp->roadtimestamp));
}

function get_last_successful_attempt($userid, $groupid, $roadid) {
    global $DB;
    // Recupero el ultimo intento con geometria solucionada realizado por el usuario/grupo para esta instancia.
    if ($groupid) {
        $grouptypewithin = 'at.groupid=a.groupid';
        $grouptype = 'a.groupid=(?)';
        $params = array($groupid, $roadid);
    } else {
        $grouptypewithin = 'at.groupid=a.groupid AND at.userid=a.userid';
        $grouptype = 'a.groupid=0 AND a.userid=(?)';
        $params = array($userid, $roadid);
    }
    $sql = "SELECT a.id,a.riddleid,a.success,a.location AS location,"
            . "a.geometrysolved,a.questionsolved,a.completionsolved,r.name,r.description,"
            . "r.questiontext,r.number,r.activitytoend FROM {treasurehunt_riddles} r "
            . "INNER JOIN {treasurehunt_attempts} a ON a.riddleid=r.id WHERE "
            . "a.timecreated=(SELECT MAX(at.timecreated) FROM {treasurehunt_riddles} ri "
            . "INNER JOIN {treasurehunt_attempts} at ON at.riddleid=ri.id  WHERE "
            . "$grouptypewithin AND ri.roadid=r.roadid AND at.geometrysolved=1)"
            . "AND $grouptype AND r.roadid = ?";
    $algo = $DB->get_record_sql($sql, $params);
    return $algo;
}

// Compruebo si se ha acertado la pista y completado la actividad requerida.
function check_question_and_completion_solved($selectedanswerid, $userid, $groupid, $roadid, $updateroad, $context, $treasurehunt, $noriddles, $qocremoved) {
    global $DB;

    $return = new stdClass();
    $return->msg = '';
    $return->updates = array();
    $return->newattempt = false;
    $return->attemptsolved = false;
    $return->roadfinished = false;
    $return->qocremoved = false;

    // Recupero los datos del ultimo intento con geometria acertada para saber si tiene geometria resuelta y no esta superada.
    $lastattempt = get_last_successful_attempt($userid, $groupid, $roadid);

    // Si el ultimo intento tiene la geometria resuelta pero no esta superado.
    if (!$lastattempt->success && $lastattempt->geometrysolved) {
        $lastattempt->userid = $userid;
        $lastattempt->groupid = $groupid;
        $completionsolved = false;
        // Si no tiene completada la actividad a superar.
        if (!$lastattempt->completionsolved) {
            // Si existe una actividad a superar.
            if ($lastattempt->activitytoend) {
                if ($usercompletion = check_completion_activity($lastattempt->activitytoend, $userid, $groupid, $context)) {
                    $return->newattempt = true;
                    $return->attemptsolved = true;
                    $return->updates[] = get_string('overcomeactivitytoend', 'treasurehunt', get_activity_to_end_link($lastattempt->activitytoend));
                    // Si no existe la pregunta y esta por superar es que la han borrado.
                    if (!$lastattempt->questionsolved && $lastattempt->questiontext === '') {
                        $lastattempt->questionsolved = 1;
                        $return->updates[] = get_string('removedquestion', 'treasurehunt');
                    }
                    $lastattempt->userid = $usercompletion;
                    $lastattempt->type = 'completion';
                    $lastattempt->timecreated = time();
                    $lastattempt->completionsolved = 1;
                    $lastattempt->penalty = 0;
                    // Si ya esta resuelta la pregunta la marco como superada.
                    if ($lastattempt->questionsolved) {
                        $lastattempt->success = 1;
                    } else {
                        $lastattempt->success = 0;
                    }
                    insert_attempt($lastattempt, $context);
                    $completionsolved = true;
                    // Si esta superada creo el intento como superado.
                    if ($lastattempt->questionsolved) {
                        $lastattempt->type = 'location';
                        $lastattempt->timecreated += 1;
                        insert_attempt($lastattempt, $context);
                    }
                }
            } else { // Si no existe la actividad a superar es que la han borrado.
                $return->qocremoved = true;
                if (!$qocremoved) {
                    $return->updates[] = get_string('removedactivitytoend', 'treasurehunt');
                    $return->attemptsolved = true;
                }
                $lastattempt->completionsolved = 1;
                // Si no existe la pregunta es que la han borrado.
                if ($lastattempt->questiontext === '') {
                    $lastattempt->questionsolved = 1;
                    $return->updates[] = get_string('removedquestion', 'treasurehunt');
                }
                // Si la pregunta esta superada creo el intento como superado.
                if ($lastattempt->questionsolved) {
                    $lastattempt->success = 1;
                    $lastattempt->type = 'location';
                    $lastattempt->penalty = 0;
                    $lastattempt->timecreated = time();
                    insert_attempt($lastattempt, $context);
                    $return->newattempt = true;
                    $return->attemptsolved = true;
                }
            }
        }
        // Si la pregunta no esta superada.
        if (!$lastattempt->questionsolved) {
            // Si no existe la pregunta es que la han borrado.
            if ($lastattempt->questiontext === '') {
                $return->qocremoved = true;
                if (!$qocremoved) {
                    $return->updates[] = get_string('removedquestion', 'treasurehunt');
                    $return->attemptsolved = true;
                }
                // Si la actividad a completar esta superada creo el intento como superado.
                if ($lastattempt->completionsolved) {
                    $lastattempt->success = 1;
                    $lastattempt->type = 'location';
                    $lastattempt->questionsolved = 1;
                    $lastattempt->penalty = 0;
                    $lastattempt->timecreated = time();
                    insert_attempt($lastattempt, $context);
                    $return->newattempt = true;
                    $return->attemptsolved = true;
                }
            } else {
                // Si exite la respuesta y no se ha actualizado el camino.
                if ($selectedanswerid > 0 && !$updateroad) {
                    $answer = $DB->get_record('treasurehunt_answers', array('id' => $selectedanswerid), 'correct,riddleid', MUST_EXIST);
                    if ($answer->riddleid != $lastattempt->riddleid) {
                        $return->msg = get_string('warmatchanswer', 'treasurehunt');
                    } else {
                        $return->newattempt = true;

                        $lastattempt->type = 'question';
                        // Sumo uno por si se ha completado tambien la actividad a completar.
                        $lastattempt->timecreated = time() + 1;
                        if ($answer->correct) {
                            $return->attemptsolved = true;
                            $return->msg = get_string('correctanswer', 'treasurehunt');

                            $lastattempt->questionsolved = 1;
                            $lastattempt->penalty = 0;
                            // Si ya esta resuelta la actividad a completar la marco como superada.
                            if ($lastattempt->completionsolved) {
                                $lastattempt->success = 1;
                            } else {
                                $lastattempt->success = 0;
                            }
                            insert_attempt($lastattempt, $context);
                            // Si esta superada creo el intento como superado.
                            if ($lastattempt->completionsolved) {
                                $lastattempt->type = 'location';
                                $lastattempt->timecreated += 1;
                                insert_attempt($lastattempt, $context);
                            }
                        } else {
                            $return->msg = get_string('incorrectanswer', 'treasurehunt');
                            $lastattempt->questionsolved = 0;
                            $lastattempt->penalty = 1;
                            insert_attempt($lastattempt, $context);
                        }
                    }
                }
            }
        }
        if ($return->newattempt == true) {
            if ($lastattempt->success && $lastattempt->number == $noriddles) {
                if ($treasurehunt->grademethod != TREASUREHUNT_GRADEFROMRIDDLES) {
                    treasurehunt_update_grades($treasurehunt);
                } else {
                    set_grade($treasurehunt, $groupid, $userid);
                }
                $return->roadfinished = true;
            } else {
                set_grade($treasurehunt, $groupid, $userid);
            }
        }
        $return->attempttimestamp = $lastattempt->timecreated;
    }

    return $return;
}

function insert_attempt($attempt, $context) {
    global $DB;
    $id = $DB->insert_record("treasurehunt_attempts", $attempt);
    $event = \mod_treasurehunt\event\attempt_submitted::create(array(
                'objectid' => $id,
                'context' => $context,
                'other' => array('groupid' => $attempt->groupid)
    ));
    $event->trigger();
}

function get_last_successful_riddle($userid, $groupid, $roadid, $noriddles, $outoftime, $actnotavailableyet, $roadfinished, $context) {

    $lastsuccessfulriddle = new stdClass();

    // Recupero el ultimo intento con geometria solucionada realizado por el usuario/grupo para esta instancia.
    $attempt = get_last_successful_attempt($userid, $groupid, $roadid);
    if ($attempt && !$outoftime && !$actnotavailableyet) {
        $lastsuccessfulriddle = get_locked_name_and_description($attempt, $context);
        if (!$roadfinished) {
            $lastsuccessfulriddle->id = intval($attempt->riddleid);
        } else {
            $lastsuccessfulriddle->id = 0;
        }
        $lastsuccessfulriddle->totalnumber = $noriddles;
        $lastsuccessfulriddle->question = '';
        $lastsuccessfulriddle->answers = array();
        $lastsuccessfulriddle->number = intval($attempt->number);
        $lastsuccessfulriddle->completion = intval($attempt->completionsolved);
        if (!$attempt->questionsolved) {
            // Envio la pregunta y las respuestas de la pista anterior.
            $lastsuccessfulriddle->answers = get_riddle_answers($attempt->riddleid, $context);
            $lastsuccessfulriddle->question = file_rewrite_pluginfile_urls($attempt->questiontext, 'pluginfile.php', $context->id, 'mod_treasurehunt', 'questiontext', $attempt->riddleid);
        }
    } else {
        $lastsuccessfulriddle->id = 0;
        $lastsuccessfulriddle->totalnumber = $noriddles;
        $lastsuccessfulriddle->question = '';
        $lastsuccessfulriddle->answers = array();
        $lastsuccessfulriddle->number = 0;
        $lastsuccessfulriddle->completion = 1;
        if ($outoftime || $actnotavailableyet) {
            if (isset($attempt->number)) {
                $lastsuccessfulriddle->number = intval($attempt->number);
            }
            if ($outoftime) {
                $lastsuccessfulriddle->name = get_string('outoftime', 'treasurehunt');
                $lastsuccessfulriddle->description = get_string('timeexceeded', 'treasurehunt');
            } else {
                $lastsuccessfulriddle->name = get_string('outoftime', 'treasurehunt');
                $lastsuccessfulriddle->description = get_string('actnotavailableyet', 'treasurehunt');
            }
        } else {
            $lastsuccessfulriddle->name = get_string('start', 'treasurehunt');
            $lastsuccessfulriddle->description = get_string('overcomefirstriddle', 'treasurehunt');
        }
    }
    return $lastsuccessfulriddle;
}

function check_attempts_updates($timestamp, $groupid, $userid, $roadid, $changesingroupmode) {
    global $DB;
    $return = new stdClass();
    $return->strings = [];
    $return->newgeometry = false;
    $return->attemptsolved = false;
    $return->geometrysolved = false;
    $return->geometrysolved = false;
    $newattempts = array();

    list($return->newattempttimestamp, $return->newroadtimestamp) = get_last_timestamps($userid, $groupid, $roadid);
    // Si ha habido un cambio de grupo.
    if ($changesingroupmode) {
        // Recupero todas las acciones de un usuario/grupo y las imprimo en una tabla.
        if ($groupid) {
            $grouptype = 'a.groupid=?';
            $params = array($groupid, $roadid);
            $return->strings[] = get_string('changetogroupmode', 'treasurehunt');
        } else {
            $grouptype = 'a.groupid=0 AND a.userid=?';
            $params = array($userid, $roadid);
            $return->strings[] = get_string('changetoindividualmode', 'treasurehunt');
        }
        $query = "SELECT a.id,a.type,a.timecreated,a.questionsolved,"
                . "a.success,a.geometrysolved,a.penalty,r.number,a.userid as user "
                . "FROM {treasurehunt_riddles} r INNER JOIN {treasurehunt_attempts} a "
                . "ON a.riddleid=r.id WHERE $grouptype AND r.roadid=? ORDER BY "
                . "a.timecreated ASC";

        $newattempts = $DB->get_records_sql($query, $params);
    }
    // Si el timestamp recuperado es mayor que el que teniamos ha habido actualizaciones.
    if ($return->newattempttimestamp > $timestamp && !$changesingroupmode) {
        // Recupero las acciones del usuario/grupo superiores a un timestamp dado.
        if ($groupid) {
            $grouptype = 'a.groupid=(?)';
            $params = array($timestamp, $groupid, $roadid);
        } else {
            $grouptype = 'a.groupid=0 AND a.userid=(?)';
            $params = array($timestamp, $userid, $roadid);
        }
        $query = "SELECT a.id,a.type,a.questionsolved,a.completionsolved,a.timecreated,"
                . "a.success,r.number,a.userid as user,a.geometrysolved "
                . "FROM {treasurehunt_riddles} r INNER JOIN {treasurehunt_attempts} a "
                . "ON a.riddleid=r.id WHERE a.timecreated >? AND $grouptype "
                . "AND r.roadid=? ORDER BY a.timecreated ASC";

        $newattempts = $DB->get_records_sql($query, $params);
    }

    foreach ($newattempts as $newattempt) {
        if ($newattempt->type === 'location') {
            if ($newattempt->geometrysolved) {
                $return->geometrysolved = true;
            }
            $return->newgeometry = true;
        } else {
            if (($newattempt->type === 'question' && $newattempt->questionsolved) ||
                    ($newattempt->type === 'completion' && $newattempt->completionsolved)) {
                $return->attemptsolved = true;
            }
        }
        $return->strings [] = set_string_attempt($newattempt, $groupid);
    }
    return $return;
}

function get_user_historical_attempts($groupid, $userid, $roadid) {
    global $DB;

    $attempts = [];
    // Recupero todas las acciones de un usuario/grupo y las imprimo en una tabla.
    if ($groupid) {
        $grouptype = 'a.groupid=?';
        $params = array($groupid, $roadid);
    } else {
        $grouptype = 'a.groupid=0 AND a.userid=?';
        $params = array($userid, $roadid);
    }
    $query = "SELECT a.id,a.type,a.timecreated,a.questionsolved,"
            . "a.success,a.geometrysolved,a.penalty,r.number,a.userid as user "
            . "FROM {treasurehunt_riddles} r INNER JOIN {treasurehunt_attempts} a "
            . "ON a.riddleid=r.id WHERE $grouptype AND r.roadid=? ORDER BY "
            . "a.timecreated ASC";
    $results = $DB->get_records_sql($query, $params);
    foreach ($results as $result) {
        $attempt = new stdClass();
        $attempt->string = set_string_attempt($result, $groupid);
        $attempt->penalty = intval($result->penalty);
        $attempts[] = $attempt;
    }
    return $attempts;
}

function view_treasurehunt_info($treasurehunt, $courseid) {
    global $PAGE;
    $timenow = time();

    $output = $PAGE->get_renderer('mod_treasurehunt');
    $renderable = new treasurehunt_info($treasurehunt, $timenow, $courseid);
    return $output->render($renderable);
}

function view_user_historical_attempts($treasurehunt, $groupid, $userid, $roadid, $cmid, $username, $teacherreview) {
    global $PAGE;
    $roadfinished = check_if_user_has_finished($userid, $groupid, $roadid);
    $attempts = get_user_historical_attempts($groupid, $userid, $roadid);
    if (time() > $treasurehunt->cutoffdate && $treasurehunt->cutoffdate) {
        $outoftime = true;
    } else {
        $outoftime = false;
    }
    $output = $PAGE->get_renderer('mod_treasurehunt');
    $renderable = new treasurehunt_user_historical_attempts($attempts, $cmid, $username, $outoftime, $roadfinished, $teacherreview);
    return $output->render($renderable);
}

function treasurehunt_view_play_page($treasurehunt, $cmid) {
    global $PAGE;
    $treasurehunt->description = format_module_intro('treasurehunt', $treasurehunt, $cmid);
    $output = $PAGE->get_renderer('mod_treasurehunt');
    $renderable = new treasurehunt_play_page($treasurehunt, $cmid);
    return $output->render($renderable);
}

function view_users_progress_table($cm, $courseid, $context) {
    global $PAGE;

    // Recojo la lista de usuarios/grupos asignada a cada camino y los posibles warnings.
    list($roads, $duplicategroupsingroupings, $duplicateusersingroups,
            $noassignedusers) = get_list_participants_and_attempts_in_roads($cm, $courseid, $context);
    $viewpermission = has_capability('mod/treasurehunt:viewusershistoricalattempts', $context);
    $managepermission = has_capability('mod/treasurehunt:managetreasurehunt', $context);
    $output = $PAGE->get_renderer('mod_treasurehunt');
    $renderable = new treasurehunt_users_progress($roads, $cm->groupmode, $cm->id, $duplicategroupsingroupings, $duplicateusersingroups, $noassignedusers, $viewpermission, $managepermission);
    return $output->render($renderable);
}

function set_string_attempt($attempt, $group) {

    $attempt->date = userdate($attempt->timecreated);
    // Si se es un grupo y el usuario no es el mismo que el que lo descubrio/fallo.
    if ($group) {
        $attempt->user = get_user_fullname_from_id($attempt->user);
        // Si son intentos a preguntas
        if ($attempt->type === 'question') {
            if ($attempt->questionsolved) {
                return get_string('groupquestionovercome', 'treasurehunt', $attempt);
            } else {
                return get_string('groupquestionfailed', 'treasurehunt', $attempt);
            }
        }
        // Si son intentos a pistas
        else if ($attempt->type === 'location') {
            if ($attempt->geometrysolved) {
                if (!$attempt->success) {
                    return get_string('grouplocationovercome', 'treasurehunt', $attempt);
                } else {
                    return get_string('groupriddleovercome', 'treasurehunt', $attempt);
                }
            } else {
                return get_string('grouplocationfailed', 'treasurehunt', $attempt);
            }
        } else if ($attempt->type === 'completion') {
            return get_string('groupcompletionovercome', 'treasurehunt', $attempt);
        }
    } else {
        // Si son intentos a preguntas
        if ($attempt->type === 'question') {
            if ($attempt->questionsolved) {
                return get_string('userquestionovercome', 'treasurehunt', $attempt);
            } else {
                return get_string('userquestionfailed', 'treasurehunt', $attempt);
            }
        }
        // Si son intentos a pistas
        else if ($attempt->type === 'location') {
            if ($attempt->geometrysolved) {
                if (!$attempt->success) {
                    return get_string('userlocationovercome', 'treasurehunt', $attempt);
                } else {
                    return get_string('userriddleovercome', 'treasurehunt', $attempt);
                }
            } else {
                return get_string('userlocationfailed', 'treasurehunt', $attempt);
            }
        } else if ($attempt->type === 'completion') {
            return get_string('usercompletionovercome', 'treasurehunt', $attempt);
        }
    }
}

function add_road_userlist($roads, $data, $userlist, $attempts) {
    $road = new stdClass();
    $road->id = $data->roadid;
    $road->name = $data->roadname;
    $road->validated = $data->validated;
    $road->totalriddles = $data->totalriddles;
    $road = insert_riddle_progress_in_road_userlist($road, $userlist, $attempts);
    $roads[$road->id] = $road;
    return $roads;
}

function view_intro($treasurehunt) {
    if ($treasurehunt->alwaysshowdescription ||
            time() > $treasurehunt->allowattemptsfromdate) {
        return true;
    }
    return false;
}

function insert_riddle_progress_in_road_userlist($road, $userlist, $attempts) {
    $road->userlist = array();
    foreach ($userlist as $user) {
        $user->ratings = array();
        // Anado a cada usuario/grupo su calificacion en color de cada pista.
        foreach ($attempts as $attempt) {
            if ($attempt->user === $user->id) {
                $rating = new stdClass();
                $rating->riddlenum = $attempt->number;
                if ($attempt->withfailures && $attempt->success) {
                    $rating->class = "successwithfailures";
                } else if ($attempt->withfailures) {
                    $rating->class = "failure";
                } else if ($attempt->success) {
                    $rating->class = "successwithoutfailures";
                } else {
                    $rating->class = "noattempt";
                }
                $user->ratings[$rating->riddlenum] = $rating;
            }
        }
        $road->userlist [] = clone $user;
    }
    return $road;
}

function get_user_fullname_from_id($id) {
    global $DB;
    $select = 'SELECT id,firstnamephonetic,lastnamephonetic,middlename,alternatename,firstname,lastname FROM {user} WHERE id = ?';
    $result = $DB->get_records_sql($select, array($id));
    return fullname($result[$id]);
}

function treasurehunt_calculate_stats($treasurehunt, $restrictedusers) {
    global $DB;

    if ($treasurehunt->groupmode) {
        $user = 'gr.userid';
        $groupsmembers = "INNER JOIN {groups_members} gr ON a.groupid=gr.groupid";
        $groupid = 'a.groupid != 0';
        $groupid2 = 'at.groupid != 0';
        $groupidwithin = 'at.groupid=a.groupid';
    } else {
        $user = 'a.userid';
        $groupsmembers = "";
        $groupid = 'a.groupid=0';
        $groupid2 = 'at.groupid=0';
        $groupidwithin = 'at.groupid=a.groupid AND at.userid=a.userid';
    }
    $userarray = array();
    foreach ($restrictedusers as $restricteduser) {
        $userarray[] = $restricteduser->id;
    }
    $users = '(' . join(",", $userarray) . ')';
    $orderby = '';
    $grademethodsql = '';
    $usercompletiontimesql = "(SELECT max(at.timecreated) from {treasurehunt_attempts} at 
            INNER JOIN {treasurehunt_riddles} ri ON ri.id = at.riddleid 
            INNER JOIN {treasurehunt_roads} roa ON ri.roadid=roa.id where 
            at.success=1 AND ri.number=(select max(rid.number) from 
            {treasurehunt_riddles} rid where rid.roadid=ri.roadid) AND 
            roa.treasurehuntid=ro.treasurehuntid AND at.type='location' 
            AND $groupidwithin) as usertime";
    if ($treasurehunt->grademethod == TREASUREHUNT_GRADEFROMTIME) {
        $grademethodsql = "(SELECT max(at.timecreated) from {treasurehunt_attempts}
            at INNER JOIN {treasurehunt_riddles} ri ON ri.id = at.riddleid INNER JOIN 
            {treasurehunt_roads} roa ON ri.roadid=roa.id where at.success=1 AND 
            ri.number=(select max(rid.number) from {treasurehunt_riddles} 
            rid where rid.roadid=ri.roadid) AND roa.treasurehuntid=ro.treasurehuntid 
            AND at.type='location' AND at.userid IN $users AND $groupid2) as worsttime,
            (SELECT min(at.timecreated) from {treasurehunt_attempts} at 
            INNER JOIN {treasurehunt_riddles} ri ON ri.id = at.riddleid 
            INNER JOIN {treasurehunt_roads} roa ON ri.roadid=roa.id where 
            at.success=1 AND ri.number=(select max(rid.number) from 
            {treasurehunt_riddles} rid where rid.roadid=ri.roadid) 
            AND roa.treasurehuntid=ro.treasurehuntid AND at.type='location'
            AND at.userid IN $users AND $groupid2) as besttime,$usercompletiontimesql,";
        $orderby = 'ORDER BY usertime ASC';
    }if ($treasurehunt->grademethod == TREASUREHUNT_GRADEFROMPOSITION) {
        $grademethodsql = "(SELECT COUNT(*) from {treasurehunt_attempts} at
            INNER JOIN {treasurehunt_riddles} ri ON ri.id = at.riddleid INNER 
            JOIN {treasurehunt_roads} roa ON ri.roadid=roa.id where at.success=1 
            AND ri.number=(select max(rid.number) from {treasurehunt_riddles} rid 
            where rid.roadid=ri.roadid) AND roa.treasurehuntid=ro.treasurehuntid 
            AND at.type='location' AND at.userid IN $users AND $groupid2) as lastposition,
            $usercompletiontimesql,";
        $orderby = 'ORDER BY usertime ASC';
    }
    $sql = "SELECT $user as user,$grademethodsql(SELECT COUNT(*) from 
        {treasurehunt_attempts} at INNER JOIN {treasurehunt_riddles} ri 
        ON ri.id = at.riddleid INNER JOIN {treasurehunt_roads} roa ON 
        ri.roadid=roa.id where roa.treasurehuntid=ro.treasurehuntid AND 
        at.type='location' AND at.penalty=1 AND $groupidwithin) as  
        nolocationsfailed,
        (SELECT COUNT(*) from {treasurehunt_attempts} at INNER JOIN 
        {treasurehunt_riddles} ri ON ri.id = at.riddleid INNER JOIN 
        {treasurehunt_roads} roa ON ri.roadid=roa.id where 
        roa.treasurehuntid=ro.treasurehuntid AND at.type='question' AND 
        at.penalty=1 AND $groupidwithin) as noanswersfailed,
        (SELECT COUNT(*) from {treasurehunt_attempts} at INNER JOIN 
        {treasurehunt_riddles} ri ON ri.id = at.riddleid INNER JOIN 
        {treasurehunt_roads} roa ON ri.roadid=roa.id where 
        roa.treasurehuntid=ro.treasurehuntid AND at.type='location' 
        AND at.success=1 AND $groupidwithin) as nosuccessfulriddles,
        (SELECT COUNT(*) from {treasurehunt_riddles} ri INNER JOIN 
        {treasurehunt_roads} roa ON ri.roadid=roa.id where 
        roa.treasurehuntid=ro.treasurehuntid AND roa.id=ro.id) as noriddles
        from {treasurehunt_attempts} a INNER JOIN {treasurehunt_riddles} 
        r ON r.id=a.riddleid INNER JOIN {treasurehunt_roads} ro ON 
        r.roadid=ro.id $groupsmembers WHERE ro.treasurehuntid=?  AND a.userid IN $users
        AND $groupid group by $user,ro.treasurehuntid,a.groupid,ro.id $orderby";
    $stats = $DB->get_records_sql($sql, array($treasurehunt->id));
    // Si el metodo de calificacion es por posicion.
    if ($treasurehunt->grademethod == TREASUREHUNT_GRADEFROMPOSITION) {
        $i = 0;
        $grouptimes = array();
        foreach ($stats as $stat) {
            if (isset($stat->usertime)) {
                if (isset($grouptimes[$stat->usertime])) {
                    $stat->position = $i;
                } else {
                    $grouptimes[$stat->usertime] = 1;
                    $stat->position = ++$i;
                }
            }
        }
    }
    return $stats;
}

function treasurehunt_calculate_grades($treasurehunt, $stats, $students) {
    $grades = array();
    foreach ($students as $student) {
        $grade = new stdClass();
        $grade->userid = $student->id;
        $grade->itemname = 'treasurehuntscore';
        if (isset($stats[$student->id])) {
            if ($treasurehunt->grademethod == TREASUREHUNT_GRADEFROMPOSITION &&
                    isset($stats[$student->id]->position)) {
                $positiverate = treasurehunt_calculate_line_equation(
                        1
                        , $treasurehunt->grade
                        , $stats[$student->id]->lastposition
                        , $treasurehunt->grade / 2
                        , $stats[$student->id]->position);
            } else if ($treasurehunt->grademethod == TREASUREHUNT_GRADEFROMTIME &&
                    isset($stats[$student->id]->usertime)) {
                $positiverate = treasurehunt_calculate_line_equation(
                        $stats[$student->id]->besttime
                        , $treasurehunt->grade
                        , $stats[$student->id]->worsttime
                        , $treasurehunt->grade / 2
                        , $stats[$student->id]->usertime);
            } else if ($treasurehunt->grademethod != TREASUREHUNT_GRADEFROMRIDDLES) {
                $positiverate = ($stats[$student->id]->nosuccessfulriddles * $treasurehunt->grade) /
                        (2 * $stats[$student->id]->noriddles);
            } else {
                $positiverate = ($stats[$student->id]->nosuccessfulriddles * $treasurehunt->grade) /
                        $stats[$student->id]->noriddles;
            }
            $negativepercentage = 1 - ((($stats[$student->id]->nolocationsfailed * $treasurehunt->gradepenlocation) +
                    ($stats[$student->id]->noanswersfailed * $treasurehunt->gradepenanswer) ) / 100);
            $grade->rawgrade = max($positiverate * $negativepercentage, 0);
            $grades[$student->id] = $grade;
        } else {
            $grade->rawgrade = null;
            $grades[$student->id] = $grade;
        }
    }
    return $grades;
}

function treasurehunt_calculate_line_equation($x1, $y1, $x2, $y2, $x3) {
    if ($x2 == $x1) {
        $m = 0;
    } else {
        $m = ($y2 - $y1) / ($x2 - $x1);
    }
    $y3 = ($m * ($x3 - $x1)) + $y1;
    return $y3;
}

function treasurehunt_calculate_user_grades($treasurehunt, $userid = 0) {
    $cm = get_coursemodule_from_instance('treasurehunt', $treasurehunt->id, 0, false, MUST_EXIST);
    $context = context_module::instance($cm->id);
    $restrictedusers = get_enrolled_users($context, 'mod/treasurehunt:play', 0, 'u.id');
    if ($userid == 0) {
        $students = $restrictedusers;
    } else {
        $student = new stdClass();
        $student->id = $userid;
        $students = array($student);
    }

    $stats = treasurehunt_calculate_stats($treasurehunt, $restrictedusers);
    $grades = treasurehunt_calculate_grades($treasurehunt, $stats, $students);
    return $grades;
}
