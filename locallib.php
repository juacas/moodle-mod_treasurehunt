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
require_once (dirname(__FILE__) . '/GeoJSON/GeoJSON.class.php');



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

/* ------------------------------------------------------------------------------ */

function insert_riddle_form(stdClass $entry) {
    GLOBAL $DB;
    $timenow = time();
    $name = $entry->name;
    $roadid = $entry->roadid;
    $description = $entry->description;
    $descriptionformat = $entry->descriptionformat;
    $descriptiontrust = $entry->descriptiontrust;
    $questiontext = $entry->questiontext;
    $questiontextformat = $entry->questiontextformat;
    $questiontexttrust = $entry->questiontexttrust;
    $activitytoend = $entry->activitytoend;

    $number_result = $DB->get_record_sql('SELECT count(id) + 1 as number FROM mdl_treasurehunt_riddles where roadid = (?)', array($roadid));
    $number = $number_result->number;

    $sql = 'INSERT INTO mdl_treasurehunt_riddles (name, roadid, '
            . 'number, description, descriptionformat, descriptiontrust, '
            . 'timecreated,questiontext,questiontextformat,questiontexttrust, '
            . 'activitytoend) VALUES ((?),(?),(?),(?),(?),(?),(?),(?),(?),(?),(?))';
    $params = array($name, $roadid, $number, $description,
        $descriptionformat, $descriptiontrust, $timenow, $questiontext,
        $questiontextformat, $questiontexttrust, $activitytoend);
    $DB->execute($sql, $params);
    //Como he insertado una nueva pista sin geometrias pongo el camino como no valido
    set_valid_road($roadid, false);
//Como no tengo nada para saber el id, tengo que hacer otra consulta
    $sql = 'SELECT id FROM mdl_treasurehunt_riddles  WHERE name= ? AND roadid = ? AND number = ? AND description = ? AND '
            . 'descriptionformat = ? AND descriptiontrust = ? AND timecreated = ?';
    $params = array($name, $roadid, $number, $description, $descriptionformat,
        $descriptiontrust, $timenow);
//Como nos devuelve un objeto lo convierto en una variable
    $result = $DB->get_record_sql($sql, $params);
    $id = $result->id;
    return $id;
}

function update_riddle_form(stdClass $entry) {
    GLOBAL $DB;
    $name = $entry->name;
    $description = $entry->description;
    $descriptionformat = $entry->descriptionformat;
    $descriptiontrust = $entry->descriptiontrust;
    $timemodified = time();
    $activitytoend = $entry->activitytoend;
    $riddleid = $entry->id;
    $questiontext = $entry->questiontext;
    $questiontextformat = $entry->questiontextformat;
    $questiontexttrust = $entry->questiontexttrust;
    $sql = 'UPDATE mdl_treasurehunt_riddles SET name=(?), description = (?), descriptionformat=(?), '
            . 'descriptiontrust=(?),timemodified=(?),questiontext=(?),'
            . 'questiontextformat=(?),questiontexttrust=(?),activitytoend=(?) '
            . 'WHERE mdl_treasurehunt_riddles.id = (?)';
    $params = array($name, $description, $descriptionformat,
        $descriptiontrust, $timemodified, $questiontext, $questiontextformat,
        $questiontexttrust, $activitytoend, $riddleid);
    $DB->execute($sql, $params);
}

function update_geometry_and_position_of_riddle(Feature $feature) {
    GLOBAL $DB;
    $noriddle = $feature->getProperty('noriddle');
    $roadid = $feature->getProperty('roadid');
    $geometry = $feature->getGeometry();
    $geometryWKT = object_to_wkt($geometry);
    $timemodified = time();
    $riddleid = $feature->getId();
    $geomfuncs = get_geometry_functions($DB);
    $sql = 'SELECT id,number FROM mdl_treasurehunt_riddles  WHERE id=?';
    $parms = array('id' => $riddleid);
    if (!$entry = $DB->get_record_sql($sql, $parms)) {
        print_error('noexsitsriddle', 'treasurehunt', '', $noriddle);
    }
    if (check_road_is_blocked($roadid) && ($noriddle != $entry->number)) {
        // No se puede cambiar el numero de pista una vez bloqueado el camino.
        print_error('notchangeorderriddle', 'treasurehunt');
    }

    // Si intento salvar una pista sin geometria devuelvo error
    if (count($geometry->getComponents()) === 0) {
        print_error('saveemptyridle', 'treasurehunt');
    }
    $sql = 'UPDATE mdl_treasurehunt_riddles SET number=(?), geom = ' . $geomfuncs['ST_GeomFromText'] . '((?)), timemodified=(?) WHERE mdl_treasurehunt_riddles.id = (?)';
    $params = array($noriddle, $geometryWKT, $timemodified, $riddleid);
    $DB->execute($sql, $params);
    set_valid_road($roadid);
}

function delete_riddle($id) {
    GLOBAL $DB;
    $riddle_sql = 'SELECT number,roadid FROM {treasurehunt_riddles} WHERE id = ?';
    $riddle_result = $DB->get_record_sql($riddle_sql, array($id));
    if (check_road_is_blocked($riddle_result->roadid)) {
        // No se puede borrar una pista de un camino empezado.
        print_error('notdeleteriddle', 'treasurehunt');
    }
    $table = 'treasurehunt_riddles';
    $select = 'id = ?';
    $params = array($id);
    $DB->delete_records_select($table, $select, $params);
    $table = 'treasurehunt_attempts';
    $select = 'riddleid = ?';
    $DB->delete_records_select($table, $select, $params);
    $sql = 'UPDATE mdl_treasurehunt_riddles '
            . 'SET number = number - 1 WHERE roadid = (?) AND number > (?)';
    $params = array($riddle_result->roadid, $riddle_result->number);
    $DB->execute($sql, $params);
    set_valid_road($riddle_result->roadid);
}

function delete_road($roadid) {
    GLOBAL $DB;
    $DB->delete_records('treasurehunt_roads', array('id' => $roadid));
    $select = 'roadid = ?';
    $params = array($roadid);
    $DB->delete_records_select('treasurehunt_riddles', $select, $params);
    $DB->delete_records_select('treasurehunt_attempts', $select, $params);
}

function get_total_roads($treasurehuntid) {
    GLOBAL $DB;
    $number = $DB->count_records('treasurehunt_roads', array('treasurehuntid' => $treasurehuntid));
    return $number;
}

function get_geometry_functions(moodle_database $DB) {
    $info = $DB->get_server_info();
    $dbtype = $DB->get_dbfamily();
    $functions = array();
    if ($dbtype === 'mysql' && version_compare($info['version'], '5.6.1') < 0) {
        $functions['ST_GeomFromText'] = 'GeomFromText';
        $functions['ST_Intersects'] = 'Intersects';
        $functions['ST_AsText'] = 'AsText';
    } else { // OGC Simple SQL for Features.
        $functions['ST_GeomFromText'] = 'ST_GeomFromText';
        $functions['ST_Intersects'] = 'ST_Intersects';
        $functions['ST_AsText'] = 'ST_AsText';
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
            . "ON ri.roadid=r.id WHERE r.id=? AND at.success=1";
    $params = array($roadid);
    return $DB->record_exists_sql($sql, $params);
}

function get_treasurehunt($treasurehuntid, $context) {
    global $DB;
    $geomfuncs = get_geometry_functions($DB);
//Recojo todas las features
    $riddles_sql = "SELECT riddle.id, "
            . "riddle.name, riddle.description, roadid, number,"
            . "{$geomfuncs['ST_AsText']}(geom) as geometry FROM {treasurehunt_riddles} AS riddle"
            . " inner join {treasurehunt_roads} AS roads on riddle.roadid = roads.id"
            . " WHERE treasurehuntid = ? ORDER BY number DESC";
    $riddles_result = $DB->get_records_sql($riddles_sql, array($treasurehuntid));
    $geojson = riddles_to_geojson($riddles_result, $context, $treasurehuntid);
//Recojo todos los caminos
    $roads_sql = "SELECT id, name, CASE WHEN (SELECT IF(COUNT(at.success)>0,1,0) "
            . "FROM {treasurehunt_attempts} at INNER JOIN {treasurehunt_riddles} ri "
            . "ON ri.id = at.riddleid INNER JOIN {treasurehunt_roads} r "
            . "ON ri.roadid=r.id WHERE r.id= road.id AND at.success=1) "
            . "THEN true ELSE false END AS blocked FROM {treasurehunt_roads} AS road where treasurehuntid = ?";
    $roads_result = $DB->get_records_sql($roads_sql, array($treasurehuntid));
    foreach ($roads_result as &$value) {
        $value->id = intval($value->id);
        $value->blocked = intval($value->blocked);
    }
    $roadsjson = json_encode($roads_result);
    $fetchstage_returns = array($geojson, $roadsjson);
    return $fetchstage_returns;
}

function renew_edition_lock($treasurehuntid, $userid) {
    global $DB;

    $table = 'treasurehunt_locks';
    $params = array('treasurehuntid' => $treasurehuntid, 'userid' => $userid);
    $time = time() + 120;
    $lock = $DB->get_record($table, $params);

    if (!empty($lock)) {
        $DB->update_record($table, array('id' => $lock->id, 'lockedat' => $time));
        return $lock->id;
    } else {
        delete_old_locks($treasurehuntid);
        return $DB->insert_record($table, array('treasurehuntid' => $treasurehuntid, 'userid' => $userid, 'lockedat' => $time));
    }
}

function is_edition_loked($treasurehuntid, $userid) {
    global $DB;
    $select = "treasurehuntid = ? AND lockedat > ? AND userid != ?";
    $params = array($treasurehuntid, time(), $userid);
    return $DB->record_exists_select('treasurehunt_locks', $select, $params);
}

function edition_lock_id_is_valid($idLock) {
    global $DB;
    return $DB->record_exists_select('treasurehunt_locks', "id = ?", array($idLock));
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

function get_total_number_of_riddles($roadid) {
    global $DB;
}

function check_user_location($lastsuccesfulatttemptid, $userid, $groupid, $roadid, $point) {
    global $DB;
    $return = new stdClass();
    $return->roadfinished = false;
    $locationattempt = new stdClass();
    $locationattempt->location = object_to_wkt($point);
    $locationattempt->userid = $userid;
    $locationattempt->groupid = $groupid;
    if ($lastsuccesfulatttemptid) {
        // Recupero la ultima pista descubierta por el usuario/grupo para esta instancia.
        $query = "SELECT r.id,r.number,r.questiontext,r.activitytoend FROM {treasurehunt_riddles} r "
                . "INNER JOIN {treasurehunt_attempts} q ON r.id=q.riddleid WHERE q.id=?";
        $currentriddle = $DB->get_record_sql($query, array($lastsuccesfulatttemptid));
        $nextnoriddle = $currentriddle->number + 1;
    } else {
        $currentriddle = 0;
        $nextnoriddle = 1;
    }
    // Compruebo si la geometria esta dentro.
    $geomfuncs = get_geometry_functions($DB);
    $query = "SELECT id, {$geomfuncs['ST_Intersects']}(geom,{$geomfuncs['ST_GeomFromText']}"
            . "((?))) as inside,number from {treasurehunt_riddles} where number=(?) and roadid=(?)";
    $params = array($locationattempt->location, $nextnoriddle, $roadid);
    $nextriddle = $DB->get_record_sql($query, $params);
    // Si está dentro
    if ($nextriddle->inside) {
        // El tiempo de la localizacion tiene que ir antes que el de completion.
        $locationattempt->timecreated = time() - 1;
        $locationattempt->success = 1;
        $locationattempt->riddleid = $nextriddle->id;
        $return->msg = get_string('successlocation', 'treasurehunt');
        $return->newriddle = true;
        if ($currentriddle) {
            $locationattempt->questionsolved = ($currentriddle->questiontext === '' ? true : false);
            if ($currentriddle->activitytoend) {
                // Si es distinto de 0 significa que ha mandado una actividad real.
                $locationattempt->completionsolved = check_completion_activity($currentriddle->activitytoend);
                if ($locationattempt->completionsolved) {
                    if ($locationattempt->questionsolved) {
                        // El tiempo de la localizacion tiene que ir despues que el de completion.
                        $locationattempt->timecreated = time() + 1;
                    }
                    insert_completion_attempt($currentriddle->id, $groupid, $userid);
                }
            } else {
                $locationattempt->completionsolved = true;
            }
        } else {
            $locationattempt->completionsolved = true;
            $locationattempt->questionsolved = true;
            // El tiempo de la localizacion tiene que ir antes que el de completion.
            $locationattempt->timecreated = time();
        }
        if ($nextriddle->number == get_total_number_of_riddles($roadid)) {
            $return->roadfinished = true;
        }
    } else {
        $locationattempt->timecreated = time();
        $locationattempt->success = 0;
        if ($currentriddle) {
            $locationattempt->riddleid = $currentriddle->id;
        } else {
            $locationattempt->riddleid = 0;
        }
        $locationattempt->completionsolved = false;
        $locationattempt->questionsolved = false;
        $return->msg = get_string('faillocation', 'treasurehunt');
        $return->newriddle = false;
    }
    // Si no es la primera pista fallada, y por lo tanto null.
    if ($locationattempt->riddleid) {
        $return->newattempt = true;
        $locationattempt->type = 'location';
        $return->attempttimestamp = $locationattempt->timecreated;
        insert_attempt($locationattempt);
    } else {
        $return->newattempt = false;
        $return->msg = get_string('overcomefirstriddle', 'treasurehunt');
    }
    return $return;
}

function insert_completion_attempt($riddleid, $groupid, $userid) {
    $completionattempt = new stdClass();
    $completionattempt->riddleid = $riddleid;
    $completionattempt->timecreated = time();
    $completionattempt->success = true;
    $completionattempt->userid = $userid;
    $completionattempt->groupid = $groupid;
    $completionattempt->type = 'completion';
    insert_attempt($completionattempt);
}

function insert_attempt(stdClass $attempt) {
    global $DB;
    if (!property_exists($attempt, 'questionsolved') &&
            !property_exists($attempt, 'completionsolved') && !property_exists($attempt, 'location')) {
        $attempt->questionsolved = 0;
        $attempt->completionsolved = 0;
        $attempt->location = null;
    }
    $geomfuncs = get_geometry_functions($DB);
    $query = 'INSERT INTO mdl_treasurehunt_attempts (riddleid, timecreated, groupid, '
            . 'userid, success,type, completionsolved,questionsolved, location) '
            . 'VALUES ((?),(?),(?),(?),(?),(?),(?),(?),' . $geomfuncs['ST_GeomFromText'] . '((?)))';
    $params = array($attempt->riddleid, $attempt->timecreated,
        $attempt->groupid, $attempt->userid, $attempt->success, $attempt->type,
        $attempt->completionsolved, $attempt->questionsolved, $attempt->location);
    $DB->execute($query, $params);
}

function get_riddle_activity_to_end_name($roadid, $noriddle) {
    global $DB, $COURSE;
    $cmactivitytoend = new stdClass();
    $sql = "SELECT activitytoend as cmid FROM {treasurehunt_riddles} WHERE number=? AND roadid=?";
    $activitytoend = $DB->get_record_sql($sql, array($noriddle, $roadid));
    if ($activitytoend->cmid != 0) {
        $modinfo = get_fast_modinfo($COURSE);
        $cmactivitytoend = $modinfo->get_cm($activitytoend->cmid);
        return $cmactivitytoend->name;
    } else {
        return '';
    }
}

function get_riddle_question_and_answers($roadid, $noriddle, $context) {
    global $DB;
    $return = new stdClass();
    // Consigo la pregunta
    $sql = "SELECT id,questiontext FROM {treasurehunt_riddles} WHERE number=? AND roadid=?";
    $question = $DB->get_record_sql($sql, array($noriddle, $roadid));
    $question->questiontext = file_rewrite_pluginfile_urls($question->questiontext, 'pluginfile.php', $context->id, 'mod_treasurehunt', 'answertext', $question->id);

    $sql = "SELECT id,answertext from {treasurehunt_answers} WHERE riddleid = ?";
    $answers = $DB->get_records_sql($sql, array($question->id));
    foreach ($answers as &$answer) {
        $answer->answertext = file_rewrite_pluginfile_urls($answer->answertext, 'pluginfile.php', $context->id, 'mod_treasurehunt', 'answertext', $answer->id);
    }
    $return->question = $question->questiontext;
    $return->answers = $answers;
    return $return;
}

function riddles_to_geojson($riddles, $context, $treasurehuntid, $userid = null) {
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
        if (property_exists($riddle, 'success')) {
            $attr['success'] = intval($riddle->success);
            $attr['info'] = set_string_attempt($riddle, $userid);
        }
        if (property_exists($riddle, 'lastsuccessfulriddle')) {
            $attr['name'] = $riddle->lastsuccessfulriddle->name;
            $attr['description'] = $riddle->lastsuccessfulriddle->description;
            $attr['questionsolved'] = intval($riddle->questionsolved);
        }
        $feature = new Feature($riddle->id ?
                        intval($riddle->id) : null, $multipolygon, $attr);
        array_push($riddlesarray, $feature);
    }
    $featurecollection = new FeatureCollection($riddlesarray);
    $geojson = object_to_geojson($featurecollection);
    return $geojson;
}

function get_locked_name_and_description($questionsolved, $completionsolved, $roadid, $noriddle) {
    $return = new stdClass();
    $return->name = get_string('lockedriddle', 'treasurehunt');
    if (!$completionsolved) {
        $activitytoendname = get_riddle_activity_to_end_name($roadid, $noriddle);
    }
    if (!$questionsolved && !$completionsolved) {
        $return->description = get_string('lockedqacriddle', 'treasurehunt', $activitytoendname);
    } else if (!$questionsolved) {
        $return->description = get_string('lockedqriddle', 'treasurehunt');
    } else if (!$completionsolved) {
        $return->description = get_string('lockedcpriddle', 'treasurehunt', $activitytoendname);
    } else if ($questionsolved && $completionsolved) {
        return false;
    }
    return $return;
}

function get_user_progress($roadid, $groupmode, $groupid, $userid, $treasurehuntid, $context, $lastsuccesfulatttempt) {
    global $DB;
    $lastsuccessfulriddle = new stdClass();
    $geomfuncs = get_geometry_functions($DB);
    // Recupero las pistas descubiertas y fallos cometidos por el usuario/grupo para esta instancia.
    if ($groupmode) {
        $query = "SELECT a.id,a.timecreated,a.questionsolved,a.completionsolved,"
                . "a.userid as user ,a.type,r.activitytoend,r.name,"
                . "IF(a.success=0,NULL,r.description) as description,r.number, "
                . "{$geomfuncs['ST_AsText']}(a.location) as geometry,"
                . "r.roadid,a.success FROM {treasurehunt_riddles} r INNER JOIN "
                . "{treasurehunt_attempts} a ON a.riddleid=r.id WHERE a.groupid=(?)"
                . " AND r.roadid=(?) AND a.type = 'location' ORDER BY r.number ASC, "
                . "a.timecreated ASC";
        $params = array($groupid, $roadid);
    } else {
        $query = "SELECT a.id,a.timecreated,a.questionsolved,a.completionsolved,"
                . "a.userid as user ,a.type,r.activitytoend,r.name,"
                . "IF(a.success=0,NULL,r.description) as description,r.number, "
                . "{$geomfuncs['ST_AsText']}(a.location) as geometry,"
                . "r.roadid,a.success FROM {treasurehunt_riddles} r INNER JOIN "
                . "{treasurehunt_attempts} a ON a.riddleid=r.id WHERE a.userid=(?)"
                . " AND r.roadid=(?) AND a.type = 'location' AND a.groupid=0 "
                . "ORDER BY r.number ASC, a.timecreated ASC";
        $params = array($userid, $roadid);
    }
    $userprogress = $DB->get_records_sql($query, $params);
    // Si no tiene ningun progreso mostrar primera pista del camino para comenzar.
    if (count($userprogress) === 0) {
        $query = "SELECT number -1,{$geomfuncs['ST_AsText']}(geom) as geometry,"
                . "roadid FROM {treasurehunt_riddles}  WHERE  roadid=? AND number=1";
        $params = array($roadid);
        $userprogress = $DB->get_records_sql($query, $params);
        $lastsuccessfulriddle->name = get_string('start', 'treasurehunt');
        $lastsuccessfulriddle->description = get_string('overcomefirstriddle', 'treasurehunt');
        $lastsuccessfulriddle->questionsolved = true;
        $lastsuccessfulriddle->completionsolved = true;
    } else {
        $riddle = $userprogress[$lastsuccesfulatttempt];
        $lastsuccessfulriddle = get_locked_name_and_description($riddle->questionsolved, $riddle->completionsolved, $riddle->roadid, $riddle->number - 1);
        if (!$lastsuccessfulriddle) {
            // Recupero la ultima pista acertada.
            $lastsuccessfulriddle->name = $riddle->name;
            $lastsuccessfulriddle->description = file_rewrite_pluginfile_urls($riddle->description, 'pluginfile.php', $context->id, 'mod_treasurehunt', 'description', $riddle->id);
        } else {
            if (!$riddle->questionsolved) {
                // Envio la pregunta y las respuestas de la pista anterior.
                $qaa = get_riddle_question_and_answers($riddle->roadid, $riddle->number - 1, $context);
                $lastsuccessfulriddle->question = $qaa->question;
                $lastsuccessfulriddle->answers = $qaa->answers;
            }
        }
        $userprogress[$lastsuccesfulatttempt]->lastsuccessfulriddle = $lastsuccessfulriddle;
        $lastsuccessfulriddle->questionsolved = intval($riddle->questionsolved);
        $lastsuccessfulriddle->completionsolved = intval($riddle->completionsolved);
        $lastsuccessfulriddle->id = intval($riddle->id);
    }
    $geojson = riddles_to_geojson($userprogress, $context, $treasurehuntid, $userid);
    return array($geojson, $lastsuccessfulriddle);
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

function check_completion_activity($cmid) {
    global $COURSE;
    if ($cmid != 0) {
        $modinfo = get_fast_modinfo($COURSE);
        $cmactivitytoend = $modinfo->get_cm($cmid);
    } else {
        return true;
    }
    // Check if a user has complete that activity.
    $completioninfo = new completion_info($COURSE);
    $current = $completioninfo->get_data($cmactivitytoend);
    return $current->completionstate; // 0 or 1 , true or false.
}

function get_user_group_and_road($userid, $cm) {
    global $DB, $COURSE;

    $groups = array();
    $returnurl = new moodle_url('/mod/treasurehunt/view.php', array('id' => $cm->id));
    if ($cm->groupmode) {
        // Group mode.
        $query = "SELECT groupingid,validated, id as roadid from {treasurehunt_roads} where treasurehuntid=? AND groupingid != 0";
        $params = array($cm->instance);
        // Recojo todos los groupings disponibles en la actividad.
        $availablegroupings = $DB->get_records_sql($query, $params);
        // Para cada grouping saco los grupos que contiene y compruebo si el usuario pertenece a uno de ellos.
        foreach ($availablegroupings as $groupingid) {
            $allgroupsingrouping = groups_get_all_groups($COURSE->id, $userid, $groupingid->groupingid, 'g.id');
            if (count($allgroupsingrouping) > 1) {
                // El usuario pertenece a mas de un grupo dentro de un mismo grouping.
                print_error('multiplegroupssameroadplay', 'treasurehunt', $returnurl);
            }
            foreach ($allgroupsingrouping as $groupingrouping) {
                array_push($groups, (object) array('groupid' => $groupingrouping->id, 'roadid' => $groupingid->roadid, 'validated' => $groupingid->validated));
            }
        }
    } else {
        // Individual mode.
        $query = "SELECT  id as roadid, groupid,validated from {treasurehunt_roads} where treasurehuntid=?";
        $params = array($cm->instance);
        $availablegroups = $DB->get_records_sql($query, $params);
        // If there is only one road validated and no groups.
        if (count($availablegroups) === 1 && current($availablegroups)->groupid == 0) {
            array_push($groups, current($availablegroups));
        } else {
            foreach ($availablegroups as $groupid) {
                if (groups_is_member($groupid->groupid)) {
                    $groupid->groupid = 0;
                    array_push($groups, $groupid);
                }
            }
        }
    }

    if (count($groups) === 0) {
        if ($cm->groupmode) {
            // El grupo no pertenece a ningun grouping.
            print_error('nogroupingplay', 'treasurehunt', $returnurl);
        } else {
            // El usuario no pertenece a ningun grupo.
            print_error('nogroupplay', 'treasurehunt', $returnurl);
        }
    } else if (count($groups) > 1) {
        if ($cm->groupmode) {
            // El grupo pertenece a mas de un grouping.
            print_error('multiplegroupingsplay', 'treasurehunt', $returnurl);
        } else {
            // El usuario pertenece a mas de un grupo.
            print_error('multiplegroupsplay', 'treasurehunt', $returnurl);
        }
    } else {
        //Bien
        if ($groups[0]->validated == 0) {
            // El camino no esta validado.
            print_error('invalidassignedroad', 'treasurehunt', $returnurl);
        }

        return $groups[0];
    }
}

function check_if_user_has_multiple_groups_or_roads(&$totalparticipantsgroups, $userlist) {
    foreach ($userlist as $user) {
        if (array_key_exists($user->id, $totalparticipantsgroups)) {
            return true;
        } else {
            $totalparticipantsgroups[$user->id] = $user;
        }
    }
    return false;
}

function get_list_participants_and_attempts_in_roads($cm, $courseid, $context) {
    global $DB;

    $roads = array();
    $totalparticipantsgroups = array();
    $totalparticipants = array();
    $warngroupedusers = false;
    if ($cm->groupmode) {
        // Group mode.
        $query = "SELECT id as roadid,groupingid,validated, name as roadname, (SELECT MAX(number) FROM {treasurehunt_riddles} where roadid = r.id) as totalriddles from {treasurehunt_roads} r where treasurehuntid=?";
        $params = array($cm->instance);
        // Recojo todos los groupings disponibles en la actividad.
        $availablegroupings = $DB->get_records_sql($query, $params);
        // Para cada grouping saco los grupos que contiene.
        foreach ($availablegroupings as $groupingid) {
            if ($groupingid->groupingid == 0) {
                $groupingid->groupingid = -1;
            }
            $grouplist = groups_get_all_groups($courseid, null, $groupingid->groupingid);
            // Compruebo si existe mas de un camino asignado a cada grupo
            if (check_if_user_has_multiple_groups_or_roads($totalparticipantsgroups, $grouplist)) {
                $warngroupedusers = true;
            }
            add_road_userlist($roads, $groupingid, $grouplist, $cm->groupmode);
        }
        // Compruebo si existen participantes en mas de un grupo dentro del mismo camino
        foreach ($totalparticipantsgroups as $group) {
            if (check_if_user_has_multiple_groups_or_roads($totalparticipants, groups_get_members($group->id))) {
                $warngroupedusers = true;
            }
        }
    } else {
        // Individual mode.
        $query = "SELECT id as roadid,validated, groupid, name as roadname,  (SELECT MAX(number) FROM {treasurehunt_riddles} where roadid = r.id)  as totalriddles from {treasurehunt_roads} r where treasurehuntid=?";
        $params = array($cm->instance);
        $availablegroups = $DB->get_records_sql($query, $params);
        // If there is only one road validated and no groups.
        if (count($availablegroups) === 1 && current($availablegroups)->groupid == 0) {
            $totalparticipants = get_enrolled_users($context);
            add_road_userlist($roads, current($availablegroups), $totalparticipants, $cm->groupmode);
        } else {
            foreach ($availablegroups as $groupid) {
                $userlist = groups_get_members($groupid->groupid);
                // Compruebo si existe mas de un camino asignado a cada usuario.
                if (check_if_user_has_multiple_groups_or_roads($totalparticipants, $userlist)) {
                    $warngroupedusers = true;
                }
                add_road_userlist($roads, $groupid, $userlist, $cm->groupmode);
            }
        }
    }
    // Compruebo si algun usuario con acceso no puede realizar la actividad.
    $noparticipants = count($totalparticipants);
    if ((count(get_enrolled_users($context)) !== $noparticipants)) {
        $warngroupedusers = true;
    }
    return array($roads, $warngroupedusers);
}

function get_strings_play() {

    return get_strings(array("discoveredriddle", "failedlocation", "riddlename",
        "riddledescription", "timelabelfailed", "question",
        "timelabelsuccess", "searching", "continue", "noattempts", "aerialview", "roadview"
        , "noresults", "startfromhere", "nomarks", "updates"), "mod_treasurehunt");
}

function get_strings_edit() {
    return get_strings(array('insert_riddle', 'insert_road', 'empty_ridle'), 'mod_treasurehunt');
}

function get_last_timestamps($userid, $groupmode, $groupid, $roadid) {
    global $DB;
    // Recupero la ultima marca de tiempo realizada para esta instancia por el grupo/usuario y
    // la ultima marca de tiempo de modificacion del camino.
    if ($groupmode) {
        $query = "SELECT max(a.timecreated) as attempttimestamp, max(ro.timemodified)"
                . " as roadtimestamp FROM mdl_treasurehunt_attempts a INNER JOIN "
                . "mdl_treasurehunt_riddles r ON a.riddleid=r.id INNER JOIN "
                . "mdl_treasurehunt_roads ro ON r.roadid = ro.id WHERE "
                . "a.groupid=? AND ro.id=?";
        $params = array($groupid, $roadid);
    } else {
        $query = "SELECT max(a.timecreated) as attempttimestamp, max(ro.timemodified)"
                . " as roadtimestamp FROM mdl_treasurehunt_attempts a INNER JOIN "
                . "mdl_treasurehunt_riddles r ON a.riddleid=r.id INNER JOIN "
                . "mdl_treasurehunt_roads ro ON r.roadid = ro.id WHERE "
                . "a.userid=? AND a.groupid=0 AND ro.id=?";
        $params = array($userid, $roadid);
    }
    $timestamp = $DB->get_record_sql($query, $params);
    return array(intval($timestamp->attempttimestamp), intval($timestamp->roadtimestamp));
}

function check_if_question_has_been_overcome($riddleid, $userid, $groupid, $groupmode) {
    global $DB;
    if ($groupmode) {
        $select = "riddleid =? AND groupid =? AND success=true AND location=null";
        $params = array($riddleid, $groupid);
    } else {
        $select = "riddleid =? AND userid =? AND groupid=0 AND success=true AND location=null";
        $params = array($riddleid, $userid);
    }
    return $DB->record_exists_select('treasurehunt_attempts', $select, $params);
}

// Compruebo si se ha acertado la pista y completado la actividad requerida.
function check_question_and_completion_solved($lastsuccessfulattemptid, $selectedanswerid, $userid, $groupid, $updateroad, $riddleresolved) {
    global $DB;
    $return = new stdClass();
    $return->msg = '';
    $return->newattempt = false;


    // Recupero los datos para comprobar si la pista anterior ha sido superada.
    $sql = "SELECT a.riddleid,a.completionsolved,a.questionsolved,r.number,r.roadid FROM {treasurehunt_riddles} r INNER JOIN {treasurehunt_attempts} a ON a.riddleid=r.id WHERE a.id =?";
    $lastsuccesfulattempt = $DB->get_record_sql($sql, array($lastsuccessfulattemptid));

    if (!$lastsuccesfulattempt->completionsolved || !$lastsuccesfulattempt->questionsolved) {
        // Recupero los datos de la pista anterior.
        $sql = "SELECT id,activitytoend,questiontext,number FROM {treasurehunt_riddles}  WHERE number =? AND roadid =? ";
        $params = array($lastsuccesfulattempt->number - 1, $lastsuccesfulattempt->roadid);
        $lastsuccesriddle = $DB->get_record_sql($sql, $params);
    }
    // Si la actividad a completar no esta completada.
    if (!$lastsuccesfulattempt->completionsolved) {
        if (check_completion_activity($lastsuccesriddle->activitytoend)) {
            $return->newattempt = true;
            // Le anado uno para que no sea igual al de completion y los eventos salgan en orden.
            $return->attempttimestamp = time() + 1;
            $return->msg = 'Actividad a completar superada';
            $return->code = 0;
            // Creo un nuevo attempt de completion activity y modifico el attempt de location
            if ($lastsuccesriddle->activitytoend) {
                insert_completion_attempt($lastsuccesriddle->id, $groupid, $userid);
                if (!$lastsuccesfulattempt->questionsolved) {
                    $sql = 'UPDATE mdl_treasurehunt_attempts SET completionsolved=(?) WHERE id = (?)';
                    $params = array(1, $lastsuccessfulattemptid);
                } else {
                    $sql = 'UPDATE mdl_treasurehunt_attempts SET completionsolved=(?), timecreated=(?) WHERE id = (?)';
                    $params = array(1, $return->attempttimestamp, $lastsuccessfulattemptid);
                    $DB->execute($sql, $params);
                }
            } else {
                $sql = 'UPDATE mdl_treasurehunt_attempts SET completionsolved=(?), timecreated=(?) WHERE id = (?)';
                $params = array(1, $return->attempttimestamp, $lastsuccessfulattemptid);
                $DB->execute($sql, $params);
            }
        }
    }
    // Si la pista no esta acertada incluyo el intento.
    if (!$lastsuccesfulattempt->questionsolved) {
        // Si ya no existe la pregunta elimino los intentos,lo guardo y aviso.
        if ($lastsuccesriddle->questiontext === '') {
            $return->msg = 'Se ha eliminado la pregunta';
            $return->code = 1;
            $return->newattempt = true;
            $return->attempttimestamp = time();
            $conditions = 'riddleid=? AND userid=? AND groupid=? AND type="question"';
            $params = array($lastsuccesriddle->id, $userid, $groupid);
            $DB->delete_records_select('treasurehunt_attempts', $conditions, $params);
            if (!$lastsuccesfulattempt->completionsolved) {
                $sql = 'UPDATE mdl_treasurehunt_attempts SET questionsolved=(?) WHERE id = (?)';
                $params = array(1, $lastsuccessfulattemptid);
            } else {
                $sql = 'UPDATE mdl_treasurehunt_attempts SET questionsolved=(?), timecreated=(?) WHERE id = (?)';
                $params = array(1, $return->attempttimestamp, $lastsuccessfulattemptid);
            }
            $DB->execute($sql, $params);
        } // Si hay una pista enviada, no se ha modificado el camino y un compañero no ha descubierto mientras una nueva pista.
        else if ($selectedanswerid && !$updateroad && !$riddleresolved && !$location) {
            $questionattempt = new stdClass();
            $sql = 'SELECT correct FROM {treasurehunt_answers} WHERE id = (?)';
            $answer = $DB->get_record_sql($sql, array($selectedanswerid));
            $return->newattempt = true;
            $return->attempttimestamp = time();
            $questionattempt->timecreated = $return->attempttimestamp;
            $questionattempt->success = $answer->correct;
            $questionattempt->riddleid = $lastsuccesriddle->id;
            $questionattempt->userid = $userid;
            $questionattempt->groupid = $groupid;
            $questionattempt->type = 'question';
            insert_attempt($questionattempt);
            if ($answer->correct) {
                $return->newattempt = true;
                // Le anado uno para que no sea igual al de completion y los eventos salgan en orden.
                $return->attempttimestamp += 1;
                $return->msg = 'Respuesta correcta';
                $return->code = 0;
                if (!$lastsuccesfulattempt->completionsolved) {
                    $sql = 'UPDATE mdl_treasurehunt_attempts SET questionsolved=(?) WHERE id = (?)';
                    $params = array(1, $lastsuccessfulattemptid);
                } else {
                    $sql = 'UPDATE mdl_treasurehunt_attempts SET questionsolved=(?), timecreated=(?) WHERE id = (?)';
                    $params = array(1, $return->attempttimestamp, $lastsuccessfulattemptid);
                }
                $DB->execute($sql, $params);
            } else {
                $return->msg = 'Respuesta incorrecta';
                $return->code = 0;
            }
        }
    }
    return $return;
}

function get_last_successful_attempt($userid, $groupid, $groupmode, $roadid) {
    global $DB;
    // Recupero el ultimo intento correcto realizado por el usuario/grupo para esta instancia.
    if ($groupmode) {
        $sql = "SELECT a.id FROM {treasurehunt_riddles} r INNER JOIN {treasurehunt_attempts} a "
                . "ON a.riddleid=r.id WHERE r.number=(SELECT MAX(number) FROM {treasurehunt_riddles} ri "
                . "INNER JOIN {treasurehunt_attempts} at ON at.riddleid=ri.id  WHERE "
                . "at.groupid=? AND ri.roadid=? AND at.success=1) AND a.groupid=? "
                . "AND a.type = 'location' AND r.roadid = ?";
        $params = array($groupid, $roadid, $groupid, $roadid);
    } else {
        $sql = "SELECT a.id FROM {treasurehunt_riddles} r INNER JOIN {treasurehunt_attempts} a "
                . "ON a.riddleid=r.id WHERE r.number=(SELECT MAX(number) FROM {treasurehunt_riddles} ri "
                . "INNER JOIN {treasurehunt_attempts} at ON at.riddleid=ri.id  WHERE "
                . "at.userid=? AND ri.roadid=? AND at.groupid=0 AND at.success=1) AND a.userid=?"
                . " AND r.roadid = ? AND a.groupid=0 AND  a.type = 'location'";
        $params = array($userid, $roadid, $userid, $roadid);
    }

    if ($attempt = $DB->get_record_sql($sql, $params)) {
        return $attempt->id;
    } else {
        return 0;
    }
}

function check_attempts_updates($timestamp, $groupmode, $groupid, $userid, $roadid) {
    global $DB;
    $return = new stdClass();
    $strings = [];
    $riddleresolved = false;
    $questionresolved = false;

    list($attempttimestamp, $roadtimestamp) = get_last_timestamps($userid, $groupmode, $groupid, $roadid);
    if ($attempttimestamp > $timestamp) {
        // Recupero las acciones del usuario/grupo superiores a un timestamp dado.
        if ($groupmode) {
            $query = "SELECT a.id,a.type,a.questionsolved,a.completionsolved,a.timecreated,"
                    . "a.success,r.number,a.userid,a.location as user "
                    . "FROM {treasurehunt_riddles} r INNER JOIN {treasurehunt_attempts} a "
                    . "ON a.riddleid=r.id WHERE a.timecreated >? AND a.groupid=? "
                    . "AND r.roadid=? ORDER BY a.timecreated ASC";
            $params = array($timestamp, $groupid, $roadid);
        } else {
            $query = "SELECT a.id,a.type,a.questionsolved,a.completionsolved,a.timecreated,"
                    . "a.success,r.number,a.userid,a.location as user "
                    . "FROM {treasurehunt_riddles} r INNER JOIN {treasurehunt_attempts} a "
                    . "ON a.riddleid=r.id WHERE a.timecreated >? AND a.groupid=0 "
                    . "AND a.userid=? AND r.roadid=? ORDER BY a.timecreated ASC";
            $params = array($timestamp, $userid, $roadid);
        }

        $attempts = $DB->get_records_sql($query, $params);
        foreach ($attempts as $attempt) {
            if ($attempt->success && $attempt->location !== null) {
                $riddleresolved = true;
            }
            if ($attempt->success && $attempt->location === null) {
                $questionresolved = true;
            }
            $return->strings[] = set_string_attempt($attempt, $userid);
        }
    }
    return array($attempttimestamp, $roadtimestamp, $strings, $riddleresolved, $questionresolved);
}

function get_user_historical_attempts($groupmode, $groupid, $userid, $roadid) {
    global $DB;

    $attempts = [];
    // Recupero todas las acciones de un usuario/grupo y las imprimo en una tabla.
    if ($groupmode) {
        $query = "SELECT a.id,a.type,a.questionsolved,a.completionsolved,a.timecreated,"
                . "a.success,r.number,a.userid as user "
                . "FROM {treasurehunt_riddles} r INNER JOIN {treasurehunt_attempts} a "
                . "ON a.riddleid=r.id WHERE a.groupid=? AND r.roadid=? ORDER BY "
                . "a.timecreated ASC";
        $params = array($groupid, $roadid);
    } else {
        $query = "SELECT a.id,a.type,a.questionsolved,a.completionsolved,a.timecreated,"
                . "a.success,r.number,a.userid as user "
                . "FROM {treasurehunt_riddles} r INNER JOIN {treasurehunt_attempts} a "
                . "ON a.riddleid=r.id WHERE a.groupid=0 AND a.userid=? AND r.roadid=? ORDER BY "
                . "a.timecreated ASC";
        $params = array($userid, $roadid);
    }
    $results = $DB->get_records_sql($query, $params);
    foreach ($results as $result) {
        $attempt = new stdClass();
        $attempt->string = set_string_attempt($result, $userid);
        $attempt->success = intval($result->success);
        $attempts[] = $attempt;
    }
    return $attempts;
}

function view_user_historical_attempts($groupmode, $groupid, $userid, $roadid, $cmid) {
    global $PAGE;
    
    $attempts = get_user_historical_attempts($groupmode, $groupid, $userid, $roadid);
    $output = $PAGE->get_renderer('mod_treasurehunt');
    $renderable = new treasurehunt_user_historical_attempts($attempts, $cmid);
    return $output->render($renderable);
}

function view_users_progress_table($cm, $courseid, $context) {
    global $PAGE;

    // Recojo la lista de usuarios/grupos asignada a cada camino y los posibles warnings.
    list($roads,
            $warngroupedusers) = get_list_participants_and_attempts_in_roads($cm, $courseid, $context);
    $output = $PAGE->get_renderer('mod_treasurehunt');
    $renderable = new treasurehunt_users_progress($roads, $cm->groupmode, $cm->id, $warngroupedusers);
    return $output->render($renderable);
}

function set_string_attempt($attempt, $userid) {

    $attempt->date = userdate($attempt->timecreated);
    // Si se es un grupo y el usuario no es el mismo que el que lo descubrio/fallo.
    if ($userid != $attempt->user) {
        $attempt->user = get_user_fullname_from_id($attempt->user);
        // Si son intentos a preguntas
        if ($attempt->type === 'question') {
            if ($attempt->success) {
                return get_string('groupquestionovercome', 'treasurehunt', $attempt);
            } else {
                return get_string('groupquestionfailed', 'treasurehunt', $attempt);
            }
        }
        // Si son intentos a pistas
        else if ($attempt->type === 'location') {
            if ($attempt->success) {
                if ($attempt->questionsolved && $attempt->completionsolved) {
                    return get_string('groupattemptovercome', 'treasurehunt', $attempt);
                } else {
                    return get_string('grouplocationblocked', 'treasurehunt', $attempt);
                }
            } else {
                return get_string('groupattemptfailed', 'treasurehunt', $attempt);
            }
        } else if ($attempt->type === 'completion') {
            return get_string('usercompletionovercome', 'treasurehunt', $attempt);
        }
    } else {
        // Si son intentos a preguntas
        if ($attempt->type === 'question') {
            if ($attempt->success) {
                return get_string('userquestionovercome', 'treasurehunt', $attempt);
            } else {
                return get_string('userquestionfailed', 'treasurehunt', $attempt);
            }
        }
        // Si son intentos a pistas
        else if ($attempt->type === 'location') {
            if ($attempt->success) {
                if ($attempt->questionsolved && $attempt->completionsolved) {
                    return get_string('userattemptovercome', 'treasurehunt', $attempt);
                } else {
                    return get_string('userlocationblocked', 'treasurehunt', $attempt);
                }
            } else {
                return get_string('userattemptfailed', 'treasurehunt', $attempt);
            }
        } else if ($attempt->type === 'completion') {
            return get_string('usercompletionovercome', 'treasurehunt', $attempt);
        }
    }
}

function add_road_userlist(&$roads, $data, $userlist, $groupmode) {
    $road = new stdClass();
    $road->id = $data->roadid;
    $road->name = $data->roadname;
    $road->validated = $data->validated;
    $road->userlist = array();
    insert_riddle_progress_in_road_userlist($road, $userlist, $groupmode);
    $road->totalriddles = $data->totalriddles;
    $roads[$road->id] = $road;
}

function view_intro($treasurehunt) {
    if ($treasurehunt->alwaysshowdescription ||
            time() > $treasurehunt->allowattemptsfromdate) {
        return true;
    }
    return false;
}

function insert_riddle_progress_in_road_userlist(&$road, $userlist, $groupmode) {
    global $DB;
    foreach ($userlist as $user) {
        if ($groupmode) {
            $query = "SELECT a.id,r.number, EXISTS(SELECT 1 FROM {treasurehunt_riddles} "
                    . "ri INNER JOIN {treasurehunt_attempts} at ON at.riddleid=ri.id WHERE "
                    . "ri.number=r.number AND ri.roadid=r.roadid AND at.groupid=a.groupid "
                    . "AND at.success=0) as withfailures, EXISTS(SELECT 1 FROM "
                    . "{treasurehunt_riddles} ri INNER JOIN {treasurehunt_attempts} at ON "
                    . "at.riddleid=ri.id WHERE ri.number=r.number+1 AND ri.roadid=r.roadid AND "
                    . "at.groupid=a.groupid AND at.success=1  AND at.type='location') as success "
                    . "FROM {treasurehunt_riddles} r INNER JOIN {treasurehunt_attempts} a "
                    . "ON a.riddleid=r.id INNER JOIN {treasurehunt_roads} ro "
                    . "ON ro.id=r.roadid WHERE (r.roadid= ? AND a.groupid = ?) "
                    . "GROUP BY r.number ORDER BY  a.timecreated ASC";
        } else {
            $query = "SELECT a.id,r.number, EXISTS(SELECT 1 FROM {treasurehunt_riddles} "
                    . "ri INNER JOIN {treasurehunt_attempts} at ON at.riddleid=ri.id WHERE "
                    . "ri.number=r.number AND ri.roadid=r.roadid AND at.groupid=a.groupid "
                    . "AND at.success=0 AND at.userid=a.userid) as withfailures, EXISTS(SELECT 1 FROM "
                    . "{treasurehunt_riddles} ri INNER JOIN {treasurehunt_attempts} at ON "
                    . "at.riddleid=ri.id WHERE ri.number=r.number+1 AND ri.roadid=r.roadid AND "
                    . "at.groupid=a.groupid AND at.userid=a.userid AND at.success=1  AND "
                    . "at.type='location') as success FROM {treasurehunt_riddles} r "
                    . "INNER JOIN {treasurehunt_attempts} a ON a.riddleid=r.id INNER "
                    . "JOIN {treasurehunt_roads} ro ON ro.id=r.roadid WHERE (r.roadid= ? AND "
                    . "a.userid = ? AND a.groupid=0) GROUP BY r.number ORDER BY  a.timecreated ASC";
        }
        $params = array($road->id, $user->id);
        $attempts = $DB->get_records_sql($query, $params);
        $user->ratings = array();
        // Anado a cada usuario/grupo su calificacion en color de cada pista.
        foreach ($attempts as $attempt) {
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
        $road->userlist [] = clone $user;
    }
}

function get_user_fullname_from_id($id) {
    global $DB;
    $select = 'SELECT id,firstnamephonetic,lastnamephonetic,middlename,alternatename,firstname,lastname FROM {user} WHERE id = ?';
    $result = $DB->get_records_sql($select, array($id));
    return fullname($result[$id]);
}
