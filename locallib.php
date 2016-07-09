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
 * Library of functions used by the treasurehunt module.
 *
 * This contains functions that are called from within the treasurehunt module only
 * Functions that are also called by core Moodle are in {@link lib.php}
 *
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/mod/treasurehunt/lib.php');
require_once($CFG->dirroot . '/mod/treasurehunt/GeoJSON/GeoJSON.class.php');
require_once($CFG->dirroot . '/mod/treasurehunt/renderable.php');

/* * #@+
 * Options determining how the grades from individual attempts are combined to give
 * the overall grade for a user
 */
define('TREASUREHUNT_GRADEFROMstageS', '1');
define('TREASUREHUNT_GRADEFROMTIME', '2');
define('TREASUREHUNT_GRADEFROMPOSITION', '3');
/* * #@- */

/* * #@+
 * Options determining lock time and game update time
 */
define('TREASUREHUNT_LOCKTIME', 120);
define('TREASUREHUNT_GAMEUPDATETIME', 20);
/* * #@- */

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

function treasurehunt_object_to_wkt($text) {
    $WKT = new WKT();
    return $WKT->write($text);
}

function treasurehunt_wkt_to_object($text) {
    $WKT = new WKT();
    return $WKT->read($text);
}

function treasurehunt_geojson_to_object($text) {
    $GeoJSON = new GeoJSON();
    return $GeoJSON->load($text);
}

function treasurehunt_object_to_geojson($text) {
    $GeoJSON = new GeoJSON();
    return $GeoJSON->dump($text);
}

/**
 * Check if a point in inside a multipolygon geometry
 * @param type $mpolygon_wkt
 * @param type $point_wkt
 * @return boolean
 */
function treasurehunt_check_point_in_multipolygon($mpolygon, $point) {
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
        TREASUREHUNT_GRADEFROMstageS => get_string('gradefromstages', 'treasurehunt'),
        TREASUREHUNT_GRADEFROMTIME => get_string('gradefromtime', 'treasurehunt'),
        TREASUREHUNT_GRADEFROMPOSITION => get_string('gradefromposition', 'treasurehunt')
    );
}

function treasurehunt_insert_stage_form(stdClass $stage) {
    GLOBAL $DB;
    $position = $DB->get_record_sql('SELECT count(id) + 1 as position FROM '
            . '{treasurehunt_stages} where roadid = (?)', array($stage->roadid));
    $stage->position = $position->position;

    $id = $DB->insert_record("treasurehunt_stages", $stage);
    //Como he insertado una nueva etapa sin geometrias pongo el camino como no valido
    treasurehunt_set_valid_road($stage->roadid, false);
    return $id;
}

function treasurehunt_update_geometry_and_position_of_stage(Feature $feature, $context) {
    GLOBAL $DB;
    $stage = new stdClass();
    $stage->position = $feature->getProperty('nostage');
    $stage->roadid = $feature->getProperty('roadid');
    $geometry = $feature->getGeometry();
    $stage->geom = treasurehunt_object_to_wkt($geometry);
    $stage->timemodified = time();
    $stage->id = $feature->getId();
    $parms = array('id' => $stage->id);
    $entry = $DB->get_record('treasurehunt_stages', $parms, 'id,position', MUST_EXIST);
    if (treasurehunt_check_road_is_blocked($stage->roadid) && ($stage->position != $entry->position)) {
        // No se puede cambiar el numero de etapa una vez bloqueado el camino.
        print_error('notchangeorderstage', 'treasurehunt');
    }
    // Si intento salvar una etapa sin geometria devuelvo error
    if (count($geometry->getComponents()) === 0) {
        print_error('saveemptyridle', 'treasurehunt');
    }
    $DB->update_record('treasurehunt_stages', $stage);
    treasurehunt_set_valid_road($stage->roadid);
    // Trigger update stage event.
    $eventparams = array(
        'context' => $context,
        'objectid' => $stage->id
    );
    \mod_treasurehunt\event\stage_updated::create($eventparams)->trigger();
}

function treasurehunt_delete_stage($id, $context) {
    GLOBAL $DB;
    $stage_result = $DB->get_record('treasurehunt_stages', array('id' => $id), 'position,roadid', MUST_EXIST);
    if (treasurehunt_check_road_is_blocked($stage_result->roadid)) {
        // No se puede borrar una etapa de un camino empezado.
        print_error('notdeletestage', 'treasurehunt');
    }

    $DB->delete_records('treasurehunt_stages', array('id' => $id));
    $DB->delete_records('treasurehunt_attempts', array('stageid' => $id));
    $sql = 'UPDATE {treasurehunt_stages} '
            . 'SET position = position - 1 WHERE roadid = (?) AND position > (?)';
    $params = array($stage_result->roadid, $stage_result->position);
    $DB->execute($sql, $params);
    treasurehunt_set_valid_road($stage_result->roadid);
    // Trigger deleted stage event.
    $eventparams = array(
        'context' => $context,
        'objectid' => $id,
    );
    \mod_treasurehunt\event\stage_deleted::create($eventparams)->trigger();
}

function treasurehunt_delete_road($roadid,$treasurehunt, $context) {
    GLOBAL $DB;
    $DB->delete_records('treasurehunt_roads', array('id' => $roadid));
    $params = array($roadid);
    $stages = $DB->get_records_sql('SELECT id FROM {treasurehunt_stages} WHERE roadid = ?'
            , $params);
    foreach ($stages as $stage) {
        $DB->delete_records_select('treasurehunt_attempts', 'stageid = ?', array($stage->id));
        $DB->delete_records_select('treasurehunt_answers', 'stageid = ?', array($stage->id));
    }
    $DB->delete_records_select('treasurehunt_stages', 'roadid = ?', $params);
    treasurehunt_update_grades($treasurehunt);
    // Trigger deleted road event.
    $eventparams = array(
        'context' => $context,
        'objectid' => $roadid
    );
    \mod_treasurehunt\event\road_deleted::create($eventparams)->trigger();
}

function treasurehunt_get_total_roads($treasurehuntid) {
    GLOBAL $DB;
    $number = $DB->count_records('treasurehunt_roads', array('treasurehuntid' => $treasurehuntid));
    return $number;
}

function treasurehunt_get_total_stages($roadid) {
    GLOBAL $DB;
    $number = $DB->count_records('treasurehunt_stages', array('roadid' => $roadid));
    return $number;
}

function treasurehunt_check_if_user_has_finished($userid, $groupid, $roadid) {
    GLOBAL $DB;
    if ($groupid) {
        $grouptype = 'a.groupid=(?)';
        $params = array($roadid, $groupid);
    } else {
        $grouptype = 'a.groupid=0 AND a.userid=(?)';
        $params = array($roadid, $userid);
    }
    $sql = "SELECT MAX(a.timecreated) as finished FROM "
            . "{treasurehunt_attempts} a INNER JOIN {treasurehunt_stages} r "
            . "ON r.id = a.stageid WHERE a.success=1 AND r.position=(SELECT "
            . "max(ri.position) FROM {treasurehunt_stages} ri where "
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
function treasurehunt_get_geometry_functions(moodle_database $DB) {
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

function treasurehunt_set_valid_road($roadid, $valid = null) {
    GLOBAL $DB;
    $road = new stdClass();
    $road->id = $roadid;
    $road->timemodified = time();
    if (is_null($valid)) {
        $road->validated = treasurehunt_is_valid_road($roadid);
    } else {
        $road->validated = $valid;
    }
    $DB->update_record("treasurehunt_roads", $road);
}

function treasurehunt_check_road_is_blocked($roadid) {
    global $DB;
    $sql = "SELECT at.success "
            . "FROM {treasurehunt_attempts} at INNER JOIN {treasurehunt_stages} ri "
            . "ON ri.id = at.stageid INNER JOIN {treasurehunt_roads} r "
            . "ON ri.roadid=r.id WHERE r.id=?";
    $params = array($roadid);
    return $DB->record_exists_sql($sql, $params);
}

function treasurehunt_get_all_roads_and_stages($treasurehuntid, $context) {
    global $DB;

//Recojo todas las features
    $stagessql = "SELECT stage.id, "
            . "stage.name, stage.cluetext, roadid, position,"
            . "geom as geometry FROM {treasurehunt_stages} AS stage"
            . " inner join {treasurehunt_roads} AS roads on stage.roadid = roads.id"
            . " WHERE treasurehuntid = ? ORDER BY position DESC";
    $stagesresult = $DB->get_records_sql($stagessql, array($treasurehuntid));
    $geojson = treasurehunt_stages_to_geojson($stagesresult, $context, $treasurehuntid);
    // Recojo todos los caminos, los bloqueo en cuanto exista un intento.
    $roadssql = "SELECT id, name, CASE WHEN (SELECT COUNT(at.id) "
            . "FROM {treasurehunt_attempts} at INNER JOIN {treasurehunt_stages} ri "
            . "ON ri.id = at.stageid INNER JOIN {treasurehunt_roads} r "
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

function treasurehunt_renew_edition_lock($treasurehuntid, $userid) {
    global $DB;

    $table = 'treasurehunt_locks';
    $params = array('treasurehuntid' => $treasurehuntid, 'userid' => $userid);
    $time = time() + treasurehunt_get_setting_lock_time();
    $lock = $DB->get_record($table, $params);

    if (!empty($lock)) {
        $DB->update_record($table, array('id' => $lock->id, 'lockedtill' => $time));
        return $lock->id;
    } else {
        treasurehunt_delete_old_locks($treasurehuntid);
        return $DB->insert_record($table,
                        array('treasurehuntid' => $treasurehuntid, 'userid' => $userid, 'lockedtill' => $time));
    }
}

function treasurehunt_get_setting_lock_time() {
    if (($locktimeediting = get_config('mod_treasurehunt', 'locktimeediting')) > 5) {
        return $locktimeediting;
    } else {
        return TREASUREHUNT_LOCKTIME;
    }
}

function treasurehunt_get_setting_game_update_time() {
    if (($gameupdatetime = get_config('mod_treasurehunt', 'gameupdatetime')) > 0) {
        return $gameupdatetime;
    } else {
        return TREASUREHUNT_GAMEUPDATETIME;
    }
}

function treasurehunt_is_edition_loked($treasurehuntid, $userid) {
    global $DB;
    $select = "treasurehuntid = ? AND lockedtill > ? AND userid != ?";
    $params = array($treasurehuntid, time(), $userid);
    return $DB->record_exists_select('treasurehunt_locks', $select, $params);
}

function treasurehunt_edition_lock_id_is_valid($lockid) {
    global $DB;
    return $DB->record_exists_select('treasurehunt_locks', "id = ?", array($lockid));
}

function treasurehunt_get_username_blocking_edition($treasurehuntid) {
    global $DB;
    $table = 'treasurehunt_locks';
    $params = array('treasurehuntid' => $treasurehuntid);
    $result = $DB->get_record($table, $params);
    return treasurehunt_get_user_fullname_from_id($result->userid);
}

function treasurehunt_delete_old_locks($treasurehuntid) {
    global $DB;
    $DB->delete_records_select('treasurehunt_locks', "lockedtill < ? AND treasurehuntid = ? ",
            array(time(), $treasurehuntid));
}

function treasurehunt_check_user_location($userid, $groupid, $roadid, $point, $context, $treasurehunt, $nostages) {
    global $DB;
    $return = new stdClass();
    $return->update = '';
    $return->roadfinished = false;
    $locationwkt = treasurehunt_object_to_wkt($point);
    // Recupero los datos del ultimo intento con geometria acertada para saber si tiene geometria resuelta y no esta superada.
    $currentstage = treasurehunt_get_last_successful_attempt($userid, $groupid, $roadid);
    if ($currentstage->success || !$currentstage) {
        $return->newattempt = true;
        if ($currentstage) {
            $nextnostage = $currentstage->position + 1;
        } else {
            $nextnostage = 1;
        }
        $nextstage = $DB->get_record('treasurehunt_stages', array('position' => $nextnostage, 'roadid' => $roadid), '*',
                MUST_EXIST);
        $inside = treasurehunt_check_point_in_multipolygon(treasurehunt_wkt_to_object($nextstage->geom), $point);
        // Si esta dentro
        if ($inside) {
            $nextstage->inside = 1;
            $questionsolved = ($nextstage->questiontext === '' ? true : false);
            $activitysolved = ($nextstage->activitytoend == 0 ? true : false);
            if ($questionsolved && $activitysolved) {
                $success = true;
            } else {
                $success = false;
            }
            $penalty = false;
            $return->msg = get_string('successlocation', 'treasurehunt');
            $return->newstage = true;
        } else {
            $nextstage->inside = 0;
            $penalty = true;
            $questionsolved = false;
            $activitysolved = false;
            $success = false;
            $return->msg = get_string('faillocation', 'treasurehunt');
            $return->newstage = false;
        }
        // Creo el attempt.
        $attempt = new stdClass();
        $attempt->stageid = $nextstage->id;
        $attempt->timecreated = time();
        $attempt->userid = $userid;
        $attempt->groupid = $groupid;
        $attempt->success = $success;
        $attempt->type = 'location';
        $attempt->activitysolved = $activitysolved;
        $attempt->questionsolved = $questionsolved;
        $attempt->geometrysolved = $nextstage->inside;
        $attempt->location = $locationwkt;
        $attempt->penalty = $penalty;
        treasurehunt_insert_attempt($attempt, $context);

        // Si el intento acierta la localizacion  y existe el completion compruebo si esta superado.
        if ($nextstage->inside && !$activitysolved) {
            if ($usercompletion = treasurehunt_check_completion_activity($nextstage->activitytoend, $userid, $groupid,
                    $context)) {
                $attempt->type = 'activity';
                $attempt->activitysolved = 1;
                $attempt->userid = $usercompletion;
                // Para que siga un orden cronologico;
                $attempt->timecreated +=1;
                if ($questionsolved) {
                    $attempt->success = 1;
                }
                treasurehunt_insert_attempt($attempt, $context);
                // Si ya se ha superado inserto el attempt de localizacion.
                if ($questionsolved) {
                    $attempt->type = 'location';
                    // Para que siga un orden cronologico;
                    $attempt->timecreated +=1;
                    treasurehunt_insert_attempt($attempt, $context);
                }
                $return->update = get_string('overcomeactivitytoend', 'treasurehunt',
                        treasurehunt_get_activity_to_end_link($nextstage->activitytoend));
            }
        }
        if ($attempt->success && $nextnostage == $nostages) {
            if ($treasurehunt->grademethod != TREASUREHUNT_GRADEFROMstageS) {
                treasurehunt_update_grades($treasurehunt);
            } else {
                treasurehunt_set_grade($treasurehunt, $groupid, $userid);
            }
            $return->roadfinished = true;
        } else {
            treasurehunt_set_grade($treasurehunt, $groupid, $userid);
        }
        $return->attempttimestamp = $attempt->timecreated;
    } else {
        $return->newstage = false;
        $return->newattempt = false;
        if (!$currentstage->questionsolved && !$currentstage->activitysolved) {
            $return->msg = get_string('mustcompleteboth', 'treasurehunt');
        } else if (!$currentstage->questionsolved) {
            $return->msg = get_string('mustanswerquestion', 'treasurehunt');
        } else {
            $return->msg = get_string('mustcompleteactivity', 'treasurehunt');
        }
    }

    return $return;
}

function treasurehunt_get_activity_to_end_link($activitytoend) {
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

function treasurehunt_get_stage_answers($stageid, $context) {
    global $DB;

    $sql = "SELECT id,answertext from {treasurehunt_answers} WHERE stageid = ?";
    $answers = $DB->get_records('treasurehunt_answers', array('stageid' => $stageid), '', 'id,answertext');
    foreach ($answers as &$answer) {
        $answer->answertext = file_rewrite_pluginfile_urls($answer->answertext, 'pluginfile.php', $context->id,
                'mod_treasurehunt', 'answertext', $answer->id);
    }
    return $answers;
}

function treasurehunt_stages_to_geojson($stages, $context, $treasurehuntid, $groupid = 0) {
    $stagesarray = array();
    foreach ($stages as $stage) {
        $multipolygon = treasurehunt_wkt_to_object($stage->geometry);
        if (isset($stage->cluetext)) {
            $cluetext = file_rewrite_pluginfile_urls($stage->cluetext, 'pluginfile.php', $context->id,
                    'mod_treasurehunt', 'cluetext', $stage->id);
        } else {
            $cluetext = null;
        }
        $attr = array('roadid' => intval($stage->roadid),
            'nostage' => intval($stage->position),
            'name' => $stage->name,
            'treasurehuntid' => $treasurehuntid,
            'clue' => $cluetext);
        if (property_exists($stage, 'timecreated')) {
            $attr['date'] = $stage->timecreated;
        }
        if (property_exists($stage, 'geometrysolved') && property_exists($stage, 'success')) {
            $attr['geometrysolved'] = intval($stage->geometrysolved);
            $attr['success'] = intval($stage->success);
            $stage->type = "location";
            // Modifico el tipo a location
            $attr['info'] = treasurehunt_set_string_attempt($stage, $groupid);
        }
        $feature = new Feature($stage->id ?
                        intval($stage->id) : null, $multipolygon, $attr);
        array_push($stagesarray, $feature);
    }
    $featurecollection = new FeatureCollection($stagesarray);
    $geojson = treasurehunt_object_to_geojson($featurecollection);
    return $geojson;
}

function treasurehunt_get_locked_clue($attempt, $context) {
    $return = new stdClass();
    $return->name = get_string('lockedclue', 'treasurehunt');
    if (!$attempt->activitysolved) {
        $activitytoendname = treasurehunt_get_activity_to_end_link($attempt->activitytoend);
    }
    if ((!$attempt->questionsolved && $attempt->questiontext !== '')
            && (!$attempt->activitysolved && $attempt->activitytoend)) {
        $return->clue = get_string('lockedqacclue', 'treasurehunt', $activitytoendname);
    } else if (!$attempt->questionsolved && $attempt->questiontext !== '') {
        $return->clue = get_string('lockedqclue', 'treasurehunt');
    } else if (!$attempt->activitysolved && $attempt->activitytoend) {
        $return->clue = get_string('lockedcpstage', 'treasurehunt', $activitytoendname);
    } else {
        $return->name = $attempt->name;
        $return->clue = file_rewrite_pluginfile_urls($attempt->cluetext, 'pluginfile.php', $context->id,
                'mod_treasurehunt', 'cluetext', $attempt->stageid);
    }
    return $return;
}

function treasurehunt_set_grade($treasurehunt, $groupid, $userid) {
    if ($groupid == 0) {
        treasurehunt_update_grades($treasurehunt, $userid);
    } else {
        $userlist = groups_get_members($groupid);
        foreach ($userlist as $user) {
            treasurehunt_update_grades($treasurehunt, $user->id);
        }
    }
}

function treasurehunt_get_user_progress($roadid, $groupid, $userid, $treasurehuntid, $context) {
    global $DB;

    // Recupero las etapas descubiertas y fallos cometidos por el usuario/grupo para esta instancia.
    if ($groupid) {
        $grouptype = 'a.groupid=(?)';
        $grouptypewithin = 'at.groupid=?';
        $params = array($roadid, $groupid, $roadid, $groupid);
    } else {
        $grouptype = 'a.groupid=0 AND a.userid=(?)';
        $grouptypewithin = 'at.groupid=0 AND at.userid=?';
        $params = array($roadid, $userid, $roadid, $userid);
    }
    $query = "SELECT a.id as attemptid,a.timecreated,a.userid as user,a.stageid,CASE WHEN a.success = 0 "
            . "THEN NULL ELSE r.name END AS name, CASE WHEN a.success=0 THEN NULL ELSE "
            . "r.cluetext END AS cluetext,CASE WHEN a.geometrysolved=1 "
            . "THEN r.id ELSE null END as id,a.geometrysolved,r.position,apt.geometry,"
            . "r.roadid,a.success FROM (SELECT MAX(at.timecreated) AS maxtime,"
            . "at.location AS geometry FROM {treasurehunt_attempts} "
            . "at INNER JOIN {treasurehunt_stages} ri ON ri.id=at.stageid WHERE ri.roadid=? "
            . "AND $grouptypewithin group by geometry) apt INNER JOIN {treasurehunt_attempts} a ON "
            . "a.timecreated=apt.maxtime AND apt.geometry = a.location "
            . "INNER JOIN {treasurehunt_stages} r ON a.stageid=r.id WHERE r.roadid=? AND $grouptype";
    $userprogress = $DB->get_records_sql($query, $params);
    $geometrysolved = false;
    foreach ($userprogress as $attempt) {
        if ($attempt->geometrysolved) {
            $geometrysolved = true;
        }
    }
    // Si no tiene ningun progreso mostrar primera etapa del camino para comenzar.
    if (count($userprogress) == 0 || !$geometrysolved) {
        $query = "SELECT position -1,geom as geometry,"
                . "roadid FROM {treasurehunt_stages}  WHERE  roadid=? AND position=1";
        $params = array($roadid);
        $userprogress[] = $DB->get_record_sql($query, $params);
    }
    $geojson = treasurehunt_stages_to_geojson($userprogress, $context, $treasurehuntid, $groupid);
    return $geojson;
}

function treasurehunt_is_valid_road($roadid) {
    global $DB;

    $query = "SELECT geom as geometry from {treasurehunt_stages} where roadid = ?";
    $params = array($roadid);
    $stages = $DB->get_records_sql($query, $params);
    if (count($stages) <= 1) {
        return false;
    }
    foreach ($stages as $stage) {
        if ($stage->geometry === null) {
            return false;
        }
    }
    return true;
}

function treasurehunt_check_completion_activity($cmid, $userid, $groupid, $context) {
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

function treasurehunt_get_group_road($groupid, $treasurehuntid, $groupame = '') {
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

function treasurehunt_get_user_group_and_road($userid, $treasurehunt, $cmid, $teacherreview = false, $username = '') {
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

function treasurehunt_get_all_users_has_multiple_groups_or_roads($totalparticipants, $userlist, $duplicated, $grouping) {
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

function treasurehunt_get_all_users_has_none_groups_and_roads($totalparticipants, $userlist, $noassignedusers) {
    foreach ($userlist as $user) {
        if (!array_key_exists($user->id, $totalparticipants)) {
            $noassignedusers[$user->id] = fullname($user);
        }
    }
    return $noassignedusers;
}

function treasurehunt_get_list_participants_and_attempts_in_roads($cm, $courseid, $context) {
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
    $attemptsquery = "SELECT a.id,$user as user,r.position, CASE WHEN EXISTS(SELECT 1 FROM "
            . "{treasurehunt_stages} ri INNER JOIN {treasurehunt_attempts} at "
            . "ON at.stageid=ri.id WHERE ri.position=r.position AND ri.roadid=r.roadid "
            . "AND $groupidwithin AND at.penalty=1) THEN 1 ELSE 0 end as withfailures, "
            . "CASE WHEN EXISTS(SELECT 1 FROM {treasurehunt_stages} ri INNER JOIN "
            . "{treasurehunt_attempts} at ON at.stageid=ri.id WHERE ri.position=r.position "
            . "AND ri.roadid=r.roadid AND $groupidwithin AND at.success=1 AND "
            . "at.type='location') THEN 1 ELSE 0 end as success FROM {treasurehunt_attempts} a INNER JOIN "
            . "{treasurehunt_stages} r ON a.stageid=r.id INNER JOIN {treasurehunt_roads} "
            . "ro ON r.roadid=ro.id WHERE ro.treasurehuntid=? AND $groupid group by r.position,user,a.id,r.roadid";
    $roadsquery = "SELECT id as roadid,$grouptype,validated, name as roadname, "
            . "(SELECT MAX(position) FROM {treasurehunt_stages} where roadid "
            . "= r.id) as totalstages from {treasurehunt_roads} r where treasurehuntid=?";
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
            // Compruebo si existe mas de un camino asignado a cada grupo. 
            // Significa que hay grupos en mas de un grouping.
            list($totalparticipantsgroups,
                    $duplicategroupsingroupings) = treasurehunt_get_all_users_has_multiple_groups_or_roads($totalparticipantsgroups,
                    $grouplist, $duplicategroupsingroupings, true);
            $roads = treasurehunt_add_road_userlist($roads, $groupingid, $grouplist, $attempts);
        }
        // Compruebo si existen participantes en mas de un grupo dentro del mismo camino. 
        // Significa que hay usuarios en mas de un grupo dentro del mismo camino.
        foreach ($totalparticipantsgroups as $group) {
            list($totalparticipants,
                    $duplicateusersingroups) = treasurehunt_get_all_users_has_multiple_groups_or_roads($totalparticipants,
                    get_enrolled_users($context, 'mod/treasurehunt:play', $group->id), $duplicateusersingroups, false);
        }
    } else {
        // Individual mode.
        $availablegroups = $DB->get_records_sql($roadsquery, $params);
        // If there is only one road validated and no groups.
        if (count($availablegroups) === 1 && current($availablegroups)->groupid == 0) {
            $totalparticipants = get_enrolled_users($context, 'mod/treasurehunt:play');
            $roads = treasurehunt_add_road_userlist($roads, current($availablegroups), $totalparticipants, $attempts);
        } else {
            foreach ($availablegroups as $groupid) {
                if ($groupid->groupid) {
                    $userlist = get_enrolled_users($context, 'mod/treasurehunt:play', $groupid->groupid);
                    // Compruebo si existe mas de un camino asignado a cada usuario. 
                    // Significa que hay usuarios en mas de un grupo.
                    list($totalparticipants,
                            $duplicateusersingroups) = treasurehunt_get_all_users_has_multiple_groups_or_roads($totalparticipants,
                            $userlist, $duplicateusersingroups, false);
                } else {
                    $userlist = array();
                }
                $roads = treasurehunt_add_road_userlist($roads, $groupid, $userlist, $attempts);
            }
        }
    }
    // Compruebo si algun usuario con acceso no puede realizar la actividad.
    $totalparticipantsincourse = get_enrolled_users($context, 'mod/treasurehunt:play');
    if ((count($totalparticipantsincourse) !== count($totalparticipants))) {
        $noassignedusers = treasurehunt_get_all_users_has_none_groups_and_roads($totalparticipants,
                $totalparticipantsincourse, $noassignedusers);
    }
    return array($roads, $duplicategroupsingroupings, $duplicateusersingroups, $noassignedusers);
}

function treasurehunt_get_strings_play() {

    return get_strings(array("overcomestage", "failedlocation", "stagename",
        "stageclue", "question", "noanswerselected", "timeexceeded",
        "searching", "continue", "noattempts", "aerialview", "roadview"
        , "noresults", "startfromhere", "nomarks", "updates", "activitytoendwarning",
        "huntcompleted", "discoveredlocation", "answerwarning", "error"), "mod_treasurehunt");
}

function treasurehunt_get_strings_edit() {
    return get_strings(array('stage', 'road', 'add', 'modify', 'save',
        'remove', 'searchlocation', 'savewarning', 'removewarning',
        'areyousure', 'removeroadwarning', 'confirm', 'cancel'), 'mod_treasurehunt');
}

function treasurehunt_get_last_timestamps($userid, $groupid, $roadid) {
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
            . "{treasurehunt_stages} r ON a.stageid=r.id where $grouptype group by r.roadid) q "
            . "ON q.roadid = ro.id WHERE ro.id=?";
    $timestamp = $DB->get_record_sql($query, $params);
    if (!isset($timestamp->attempttimestamp)) {
        $timestamp->attempttimestamp = 0;
    }
    return array(intval($timestamp->attempttimestamp), intval($timestamp->roadtimestamp));
}

function treasurehunt_get_last_successful_attempt($userid, $groupid, $roadid) {
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
    $sql = "SELECT a.id,a.stageid,a.success,a.location AS location,"
            . "a.geometrysolved,a.questionsolved,a.activitysolved,r.name,r.cluetext,"
            . "r.questiontext,r.position,r.activitytoend FROM {treasurehunt_stages} r "
            . "INNER JOIN {treasurehunt_attempts} a ON a.stageid=r.id WHERE "
            . "a.timecreated=(SELECT MAX(at.timecreated) FROM {treasurehunt_stages} ri "
            . "INNER JOIN {treasurehunt_attempts} at ON at.stageid=ri.id  WHERE "
            . "$grouptypewithin AND ri.roadid=r.roadid AND at.geometrysolved=1)"
            . "AND $grouptype AND r.roadid = ?";
    $algo = $DB->get_record_sql($sql, $params);
    return $algo;
}

// Compruebo si se ha acertado la etapa y completado la actividad requerida.
function treasurehunt_check_question_and_activity_solved($selectedanswerid, $userid, $groupid, $roadid, $updateroad,
        $context, $treasurehunt, $nostages, $qocremoved) {
    global $DB;

    $return = new stdClass();
    $return->msg = '';
    $return->updates = array();
    $return->newattempt = false;
    $return->attemptsolved = false;
    $return->roadfinished = false;
    $return->qocremoved = false;

    // Recupero los datos del ultimo intento con geometria acertada para saber si tiene geometria resuelta y no esta superada.
    $lastattempt = treasurehunt_get_last_successful_attempt($userid, $groupid, $roadid);

    // Si el ultimo intento tiene la geometria resuelta pero no esta superado.
    if (!$lastattempt->success && $lastattempt->geometrysolved) {
        $lastattempt->userid = $userid;
        $lastattempt->groupid = $groupid;
        $activitysolved = false;
        // Si no tiene completada la actividad a superar.
        if (!$lastattempt->activitysolved) {
            // Si existe una actividad a superar.
            if ($lastattempt->activitytoend) {
                if ($usercompletion = treasurehunt_check_completion_activity($lastattempt->activitytoend, $userid,
                        $groupid, $context)) {
                    $return->newattempt = true;
                    $return->attemptsolved = true;
                    $return->updates[] = get_string('overcomeactivitytoend', 'treasurehunt',
                            treasurehunt_get_activity_to_end_link($lastattempt->activitytoend));
                    // Si no existe la pregunta y esta por superar es que la han borrado.
                    if (!$lastattempt->questionsolved && $lastattempt->questiontext === '') {
                        $lastattempt->questionsolved = 1;
                        $return->updates[] = get_string('removedquestion', 'treasurehunt');
                    }
                    $lastattempt->userid = $usercompletion;
                    $lastattempt->type = 'activity';
                    $lastattempt->timecreated = time();
                    $lastattempt->activitysolved = 1;
                    $lastattempt->penalty = 0;
                    // Si ya esta resuelta la pregunta la marco como superada.
                    if ($lastattempt->questionsolved) {
                        $lastattempt->success = 1;
                    } else {
                        $lastattempt->success = 0;
                    }
                    treasurehunt_insert_attempt($lastattempt, $context);
                    $activitysolved = true;
                    // Si esta superada creo el intento como superado.
                    if ($lastattempt->questionsolved) {
                        $lastattempt->type = 'location';
                        $lastattempt->timecreated += 1;
                        treasurehunt_insert_attempt($lastattempt, $context);
                    }
                }
            } else { // Si no existe la actividad a superar es que la han borrado.
                $return->qocremoved = true;
                if (!$qocremoved) {
                    $return->updates[] = get_string('removedactivitytoend', 'treasurehunt');
                    $return->attemptsolved = true;
                }
                $lastattempt->activitysolved = 1;
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
                    treasurehunt_insert_attempt($lastattempt, $context);
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
                if ($lastattempt->activitysolved) {
                    $lastattempt->success = 1;
                    $lastattempt->type = 'location';
                    $lastattempt->questionsolved = 1;
                    $lastattempt->penalty = 0;
                    $lastattempt->timecreated = time();
                    treasurehunt_insert_attempt($lastattempt, $context);
                    $return->newattempt = true;
                    $return->attemptsolved = true;
                }
            } else {
                // Si exite la respuesta y no se ha actualizado el camino.
                if ($selectedanswerid > 0 && !$updateroad) {
                    $answer = $DB->get_record('treasurehunt_answers', array('id' => $selectedanswerid),
                            'correct,stageid', MUST_EXIST);
                    if ($answer->stageid != $lastattempt->stageid) {
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
                            if ($lastattempt->activitysolved) {
                                $lastattempt->success = 1;
                            } else {
                                $lastattempt->success = 0;
                            }
                            treasurehunt_insert_attempt($lastattempt, $context);
                            // Si esta superada creo el intento como superado.
                            if ($lastattempt->activitysolved) {
                                $lastattempt->type = 'location';
                                $lastattempt->timecreated += 1;
                                treasurehunt_insert_attempt($lastattempt, $context);
                            }
                        } else {
                            $return->msg = get_string('incorrectanswer', 'treasurehunt');
                            $lastattempt->questionsolved = 0;
                            $lastattempt->penalty = 1;
                            treasurehunt_insert_attempt($lastattempt, $context);
                        }
                    }
                }
            }
        }
        if ($return->newattempt == true) {
            if ($lastattempt->success && $lastattempt->position == $nostages) {
                if ($treasurehunt->grademethod != TREASUREHUNT_GRADEFROMstageS) {
                    treasurehunt_update_grades($treasurehunt);
                } else {
                    treasurehunt_set_grade($treasurehunt, $groupid, $userid);
                }
                $return->roadfinished = true;
            } else {
                treasurehunt_set_grade($treasurehunt, $groupid, $userid);
            }
        }
        $return->attempttimestamp = $lastattempt->timecreated;
    }

    return $return;
}

function treasurehunt_insert_attempt($attempt, $context) {
    global $DB;
    $id = $DB->insert_record("treasurehunt_attempts", $attempt);
    $event = \mod_treasurehunt\event\attempt_submitted::create(array(
                'objectid' => $id,
                'context' => $context,
                'other' => array('groupid' => $attempt->groupid)
    ));
    $event->trigger();
}

function treasurehunt_get_last_successful_stage($userid, $groupid, $roadid, $nostages, $outoftime, $actnotavailableyet,
        $roadfinished, $context) {

    $lastsuccessfulstage = new stdClass();

    // Recupero el ultimo intento con geometria solucionada realizado por el usuario/grupo para esta instancia.
    $attempt = treasurehunt_get_last_successful_attempt($userid, $groupid, $roadid);
    if ($attempt && !$outoftime && !$actnotavailableyet) {
        $lastsuccessfulstage = treasurehunt_get_locked_clue($attempt, $context);
        if (!$roadfinished) {
            $lastsuccessfulstage->id = intval($attempt->stageid);
        } else {
            $lastsuccessfulstage->id = 0;
        }
        $lastsuccessfulstage->totalnumber = $nostages;
        $lastsuccessfulstage->question = '';
        $lastsuccessfulstage->answers = array();
        $lastsuccessfulstage->position = intval($attempt->position);
        $lastsuccessfulstage->activitysolved = intval($attempt->activitysolved);
        if (!$attempt->questionsolved) {
            // Envio la pregunta y las respuestas de la etapa anterior.
            $lastsuccessfulstage->answers = treasurehunt_get_stage_answers($attempt->stageid, $context);
            $lastsuccessfulstage->question = file_rewrite_pluginfile_urls($attempt->questiontext, 'pluginfile.php',
                    $context->id, 'mod_treasurehunt', 'questiontext', $attempt->stageid);
        }
    } else {
        $lastsuccessfulstage->id = 0;
        $lastsuccessfulstage->totalnumber = $nostages;
        $lastsuccessfulstage->question = '';
        $lastsuccessfulstage->answers = array();
        $lastsuccessfulstage->position = 0;
        $lastsuccessfulstage->activitysolved = 1;
        if ($outoftime || $actnotavailableyet) {
            if (isset($attempt->position)) {
                $lastsuccessfulstage->position = intval($attempt->position);
            }
            if ($outoftime) {
                $lastsuccessfulstage->name = get_string('outoftime', 'treasurehunt');
                $lastsuccessfulstage->clue = get_string('timeexceeded', 'treasurehunt');
            } else {
                $lastsuccessfulstage->name = get_string('outoftime', 'treasurehunt');
                $lastsuccessfulstage->clue = get_string('actnotavailableyet', 'treasurehunt');
            }
        } else {
            $lastsuccessfulstage->name = get_string('start', 'treasurehunt');
            $lastsuccessfulstage->clue = get_string('overcomefirststage', 'treasurehunt');
        }
    }
    return $lastsuccessfulstage;
}

function treasurehunt_check_attempts_updates($timestamp, $groupid, $userid, $roadid, $changesingroupmode) {
    global $DB;
    $return = new stdClass();
    $return->strings = [];
    $return->newgeometry = false;
    $return->attemptsolved = false;
    $return->geometrysolved = false;
    $return->geometrysolved = false;
    $newattempts = array();

    list($return->newattempttimestamp, $return->newroadtimestamp) = treasurehunt_get_last_timestamps($userid, $groupid,
            $roadid);
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
                . "a.success,a.geometrysolved,a.penalty,r.position,a.userid as user "
                . "FROM {treasurehunt_stages} r INNER JOIN {treasurehunt_attempts} a "
                . "ON a.stageid=r.id WHERE $grouptype AND r.roadid=? ORDER BY "
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
        $query = "SELECT a.id,a.type,a.questionsolved,a.activitysolved,a.timecreated,"
                . "a.success,r.position,a.userid as user,a.geometrysolved "
                . "FROM {treasurehunt_stages} r INNER JOIN {treasurehunt_attempts} a "
                . "ON a.stageid=r.id WHERE a.timecreated >? AND $grouptype "
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
            if (($newattempt->type === 'question' && $newattempt->questionsolved) || ($newattempt->type === 'activity' &&
                    $newattempt->activitysolved)) {
                $return->attemptsolved = true;
            }
        }
        $return->strings [] = treasurehunt_set_string_attempt($newattempt, $groupid);
    }
    return $return;
}

function treasurehunt_get_user_historical_attempts($groupid, $userid, $roadid) {
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
            . "a.success,a.geometrysolved,a.penalty,r.position,a.userid as user "
            . "FROM {treasurehunt_stages} r INNER JOIN {treasurehunt_attempts} a "
            . "ON a.stageid=r.id WHERE $grouptype AND r.roadid=? ORDER BY "
            . "a.timecreated ASC";
    $results = $DB->get_records_sql($query, $params);
    foreach ($results as $result) {
        $attempt = new stdClass();
        $attempt->string = treasurehunt_set_string_attempt($result, $groupid);
        $attempt->penalty = intval($result->penalty);
        $attempts[] = $attempt;
    }
    return $attempts;
}

function treasurehunt_view_info($treasurehunt, $courseid) {
    global $PAGE;
    $timenow = time();

    $output = $PAGE->get_renderer('mod_treasurehunt');
    $renderable = new treasurehunt_info($treasurehunt, $timenow, $courseid);
    return $output->render($renderable);
}

function treasurehunt_view_user_historical_attempts($treasurehunt, $groupid, $userid, $roadid, $cmid, $username,
        $teacherreview) {
    global $PAGE;
    $roadfinished = treasurehunt_check_if_user_has_finished($userid, $groupid, $roadid);
    $attempts = treasurehunt_get_user_historical_attempts($groupid, $userid, $roadid);
    if (time() > $treasurehunt->cutoffdate && $treasurehunt->cutoffdate) {
        $outoftime = true;
    } else {
        $outoftime = false;
    }
    $output = $PAGE->get_renderer('mod_treasurehunt');
    $renderable = new treasurehunt_user_historical_attempts($attempts, $cmid, $username, $outoftime, $roadfinished,
            $teacherreview);
    return $output->render($renderable);
}

function treasurehunt_view_play_page($treasurehunt, $cmid) {
    global $PAGE;
    $treasurehunt->cluetext = format_module_intro('treasurehunt', $treasurehunt, $cmid);
    $output = $PAGE->get_renderer('mod_treasurehunt');
    $renderable = new treasurehunt_play_page($treasurehunt, $cmid);
    return $output->render($renderable);
}

function treasurehunt_view_users_progress_table($cm, $courseid, $context) {
    global $PAGE;

    // Recojo la lista de usuarios/grupos asignada a cada camino y los posibles warnings.
    list($roads, $duplicategroupsingroupings, $duplicateusersingroups,
            $noassignedusers) = treasurehunt_get_list_participants_and_attempts_in_roads($cm, $courseid, $context);
    $viewpermission = has_capability('mod/treasurehunt:viewusershistoricalattempts', $context);
    $managepermission = has_capability('mod/treasurehunt:managetreasurehunt', $context);
    $output = $PAGE->get_renderer('mod_treasurehunt');
    $renderable = new treasurehunt_users_progress($roads, $cm->groupmode, $cm->id, $duplicategroupsingroupings,
            $duplicateusersingroups, $noassignedusers, $viewpermission, $managepermission);
    return $output->render($renderable);
}

function treasurehunt_set_string_attempt($attempt, $group) {

    $attempt->date = userdate($attempt->timecreated);
    // Si se es un grupo y el usuario no es el mismo que el que lo descubrio/fallo.
    if ($group) {
        $attempt->user = treasurehunt_get_user_fullname_from_id($attempt->user);
        // Si son intentos a preguntas
        if ($attempt->type === 'question') {
            if ($attempt->questionsolved) {
                return get_string('groupquestionovercome', 'treasurehunt', $attempt);
            } else {
                return get_string('groupquestionfailed', 'treasurehunt', $attempt);
            }
        }
        // Si son intentos a etapas
        else if ($attempt->type === 'location') {
            if ($attempt->geometrysolved) {
                if (!$attempt->success) {
                    return get_string('grouplocationovercome', 'treasurehunt', $attempt);
                } else {
                    return get_string('groupstageovercome', 'treasurehunt', $attempt);
                }
            } else {
                return get_string('grouplocationfailed', 'treasurehunt', $attempt);
            }
        } else if ($attempt->type === 'activity') {
            return get_string('groupactivityovercome', 'treasurehunt', $attempt);
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
        // Si son intentos a etapas
        else if ($attempt->type === 'location') {
            if ($attempt->geometrysolved) {
                if (!$attempt->success) {
                    return get_string('userlocationovercome', 'treasurehunt', $attempt);
                } else {
                    return get_string('userstageovercome', 'treasurehunt', $attempt);
                }
            } else {
                return get_string('userlocationfailed', 'treasurehunt', $attempt);
            }
        } else if ($attempt->type === 'activity') {
            return get_string('useractivityovercome', 'treasurehunt', $attempt);
        }
    }
}

function treasurehunt_add_road_userlist($roads, $data, $userlist, $attempts) {
    $road = new stdClass();
    $road->id = $data->roadid;
    $road->name = $data->roadname;
    $road->validated = $data->validated;
    $road->totalstages = $data->totalstages;
    $road = treasurehunt_insert_stage_progress_in_road_userlist($road, $userlist, $attempts);
    $roads[$road->id] = $road;
    return $roads;
}

function treasurehunt_view_intro($treasurehunt) {
    if ($treasurehunt->alwaysshowdescription || time() > $treasurehunt->allowattemptsfromdate) {
        return true;
    }
    return false;
}

function treasurehunt_insert_stage_progress_in_road_userlist($road, $userlist, $attempts) {
    $road->userlist = array();
    foreach ($userlist as $user) {
        $user->ratings = array();
        // Anado a cada usuario/grupo su calificacion en color de cada etapa.
        foreach ($attempts as $attempt) {
            if ($attempt->user === $user->id) {
                $rating = new stdClass();
                $rating->stagenum = $attempt->position;
                if ($attempt->withfailures && $attempt->success) {
                    $rating->class = "successwithfailures";
                } else if ($attempt->withfailures) {
                    $rating->class = "failure";
                } else if ($attempt->success) {
                    $rating->class = "successwithoutfailures";
                } else {
                    $rating->class = "noattempt";
                }
                $user->ratings[$rating->stagenum] = $rating;
            }
        }
        $road->userlist [] = clone $user;
    }
    return $road;
}

function treasurehunt_get_user_fullname_from_id($id) {
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
            INNER JOIN {treasurehunt_stages} ri ON ri.id = at.stageid 
            INNER JOIN {treasurehunt_roads} roa ON ri.roadid=roa.id where 
            at.success=1 AND ri.position=(select max(rid.position) from 
            {treasurehunt_stages} rid where rid.roadid=ri.roadid) AND 
            roa.treasurehuntid=ro.treasurehuntid AND at.type='location' 
            AND $groupidwithin) as usertime";
    if ($treasurehunt->grademethod == TREASUREHUNT_GRADEFROMTIME) {
        $grademethodsql = "(SELECT max(at.timecreated) from {treasurehunt_attempts}
            at INNER JOIN {treasurehunt_stages} ri ON ri.id = at.stageid INNER JOIN 
            {treasurehunt_roads} roa ON ri.roadid=roa.id where at.success=1 AND 
            ri.position=(select max(rid.position) from {treasurehunt_stages} 
            rid where rid.roadid=ri.roadid) AND roa.treasurehuntid=ro.treasurehuntid 
            AND at.type='location' AND at.userid IN $users AND $groupid2) as worsttime,
            (SELECT min(at.timecreated) from {treasurehunt_attempts} at 
            INNER JOIN {treasurehunt_stages} ri ON ri.id = at.stageid 
            INNER JOIN {treasurehunt_roads} roa ON ri.roadid=roa.id where 
            at.success=1 AND ri.position=(select max(rid.position) from 
            {treasurehunt_stages} rid where rid.roadid=ri.roadid) 
            AND roa.treasurehuntid=ro.treasurehuntid AND at.type='location'
            AND at.userid IN $users AND $groupid2) as besttime,$usercompletiontimesql,";
        $orderby = 'ORDER BY usertime ASC';
    }if ($treasurehunt->grademethod == TREASUREHUNT_GRADEFROMPOSITION) {
        $grademethodsql = "(SELECT COUNT(*) from {treasurehunt_attempts} at
            INNER JOIN {treasurehunt_stages} ri ON ri.id = at.stageid INNER 
            JOIN {treasurehunt_roads} roa ON ri.roadid=roa.id where at.success=1 
            AND ri.position=(select max(rid.position) from {treasurehunt_stages} rid 
            where rid.roadid=ri.roadid) AND roa.treasurehuntid=ro.treasurehuntid 
            AND at.type='location' AND at.userid IN $users AND $groupid2) as lastposition,
            $usercompletiontimesql,";
        $orderby = 'ORDER BY usertime ASC';
    }
    $sql = "SELECT $user as user,$grademethodsql(SELECT COUNT(*) from 
        {treasurehunt_attempts} at INNER JOIN {treasurehunt_stages} ri 
        ON ri.id = at.stageid INNER JOIN {treasurehunt_roads} roa ON 
        ri.roadid=roa.id where roa.treasurehuntid=ro.treasurehuntid AND 
        at.type='location' AND at.penalty=1 AND $groupidwithin) as  
        nolocationsfailed,
        (SELECT COUNT(*) from {treasurehunt_attempts} at INNER JOIN 
        {treasurehunt_stages} ri ON ri.id = at.stageid INNER JOIN 
        {treasurehunt_roads} roa ON ri.roadid=roa.id where 
        roa.treasurehuntid=ro.treasurehuntid AND at.type='question' AND 
        at.penalty=1 AND $groupidwithin) as noanswersfailed,
        (SELECT COUNT(*) from {treasurehunt_attempts} at INNER JOIN 
        {treasurehunt_stages} ri ON ri.id = at.stageid INNER JOIN 
        {treasurehunt_roads} roa ON ri.roadid=roa.id where 
        roa.treasurehuntid=ro.treasurehuntid AND at.type='location' 
        AND at.success=1 AND $groupidwithin) as nosuccessfulstages,
        (SELECT COUNT(*) from {treasurehunt_stages} ri INNER JOIN 
        {treasurehunt_roads} roa ON ri.roadid=roa.id where 
        roa.treasurehuntid=ro.treasurehuntid AND roa.id=ro.id) as nostages
        from {treasurehunt_attempts} a INNER JOIN {treasurehunt_stages} 
        r ON r.id=a.stageid INNER JOIN {treasurehunt_roads} ro ON 
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
            if ($treasurehunt->grademethod == TREASUREHUNT_GRADEFROMPOSITION && isset($stats[$student->id]->position)) {
                $positiverate = treasurehunt_calculate_line_equation(
                        1
                        , $treasurehunt->grade
                        , $stats[$student->id]->lastposition
                        , $treasurehunt->grade / 2
                        , $stats[$student->id]->position);
            } else if ($treasurehunt->grademethod == TREASUREHUNT_GRADEFROMTIME && isset($stats[$student->id]->usertime)) {
                $positiverate = treasurehunt_calculate_line_equation(
                        $stats[$student->id]->besttime
                        , $treasurehunt->grade
                        , $stats[$student->id]->worsttime
                        , $treasurehunt->grade / 2
                        , $stats[$student->id]->usertime);
            } else if ($treasurehunt->grademethod != TREASUREHUNT_GRADEFROMstageS) {
                $positiverate = ($stats[$student->id]->nosuccessfulstages * $treasurehunt->grade)
                        / (2 * $stats[$student->id]->nostages);
            } else {
                $positiverate = ($stats[$student->id]->nosuccessfulstages * $treasurehunt->grade)
                        / $stats[$student->id]->nostages;
            }
            $negativepercentage = 1 - ((($stats[$student->id]->nolocationsfailed * $treasurehunt->gradepenlocation)
                    + ($stats[$student->id]->noanswersfailed * $treasurehunt->gradepenanswer) ) / 100);
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
