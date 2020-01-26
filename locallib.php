<?php
use core\notification;
use core\session\exception;

// This file is part of Treasurehunt for Moodle
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
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @copyright 2017 onwards Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @author Adrian Rodriguez <huorwhisp@gmail.com>
 * @author Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/mod/treasurehunt/lib.php');
require_once($CFG->dirroot . '/mod/treasurehunt/GeoJSON/GeoJSON.class.php');
require_once($CFG->dirroot . '/mod/treasurehunt/renderable.php');

/* * #@+
 * Options determining how the grades from individual attempts are combined to give
 * the overall grade for a user or group
 */
define('TREASUREHUNT_GRADEFROMSTAGES', '1');
define('TREASUREHUNT_GRADEFROMTIME', '2');
define('TREASUREHUNT_GRADEFROMPOSITION', '3');
define('TREASUREHUNT_GRADEFROMABSOLUTETIME', '4');
/* * #@- */

/* * #@+
 * Options determining lock time and game update time
 */
define('TREASUREHUNT_LOCKTIME', 120);
define('TREASUREHUNT_GAMEUPDATETIME', 20);
/* * #@- */

// Load classes needed for GeoJSON library.
spl_autoload_register(array('GeoJSON', 'autoload'));

/**
 * Compatibility with Moodle 2.9 notifications.
 *
 * @param string $message
 */
function treasurehunt_notify_info($message) {
    if (class_exists('\core\notification')) {
        \core\notification::info($message);
    } else {
        global $OUTPUT;
        echo $OUTPUT->notification($message , 'notifymessage');
    }
}
/**
 * Compatibility with Moodle 2.9 notifications
 * @param unknown $message
 */
function treasurehunt_notify_error($message) {
    if (class_exists('\core\notification')) {
        \core\notification::error($message);
    } else {
        global $OUTPUT;
        echo $OUTPUT->notification($message , 'notifyproblem');
    }
}
/**
 * Compatibility with Moodle 2.9 notifications
 * @param unknown $message
 */
function treasurehunt_notify_warning($message) {
    if (class_exists('\core\notification')) {
        \core\notification::warning($message);
    } else {
        global $OUTPUT;
        echo $OUTPUT->notification($message , 'notifyproblem');
    }
}
/**
 * @param Geometry $geom
 */
function treasurehunt_geometry_centroid(Geometry $geom) {
    $coords = [];
    $geomarray = $geom->getCoordinates();
    foreach ($geomarray as $polys) {
        foreach ($polys as $line) {
            foreach ($line as $point) {
                $coords[] = $point;
            }
        }
    }
    $sumx = 0;
    $sumy = 0;
    foreach ($coords as $coord) {
        $sumx += $coord[0];
        $sumy += $coord[1];
    }
    return new Point($sumx / count($coords), $sumy / count($coords));
}

/**
 * Serialize geometries into a WKT string.
 *
 * @param Geometry $geometry
 *
 * @return string The WKT string representation of the input geometries
 */
function treasurehunt_geometry_to_wkt($geometry) {
    if (!$geometry) {
        return 'GEOMETRY EMPTY';
    } else {
        $wkt = new WKT();
        return $wkt->write($geometry);
    }
}

/**
 * Read WKT string into geometry objects.
 *
 * @param string $text A WKT string.
 *
 * @return Geometry|GeometryCollection.
 */
function treasurehunt_wkt_to_object($text) {
    $wkt = new WKT();
    return $wkt->read($text);
}

/**
 * Format a human-readable format for a duration.
 * calculates from seconds to days
 * trim the details to the two more significant units
 * @param int $durationinseconds
 * @return string
 */
function treasurehunt_get_nice_duration($durationinseconds) {
    $durationstring = '';
    $days = floor($durationinseconds / 86400);
    $durationinseconds -= $days * 86400;
    $hours = floor($durationinseconds / 3600);
    $durationinseconds -= $hours * 3600;
    $minutes = floor($durationinseconds / 60);
    $seconds = round($durationinseconds - $minutes * 60);

    if ($days > 0) {
        $durationstring .= $days . ' ' . get_string('days');
        // Trim details less significant.
        $minutes = false;
        $days = false;
        $seconds = false;
    }
    if ($hours > 0) {
        $durationstring .= ' ' . $hours . ' ' . get_string('hours');
        $seconds = false;
    }
    if ($minutes > 0) {
        $durationstring .= ' ' . $minutes . ' ' . get_string('minutes');
    }
    if ($seconds > 0) {
        $durationstring .= ' ' . $seconds . ' s.';
    }
    return $durationstring;
}

/**
 * Deserialize a JSON array in a compatible object with the library GeoJSON.
 *
 * @param string $array The GeoJSON string.
 *
 * @return Feature|FeatureCollection The PHP equivalent object.
 */
function treasurehunt_geojson_to_object($array) {
    if ($array === null) {
        return null;
    }
    $geojson = new GeoJSON();
    return $geojson->loadArray($array);
}

/**
 * Check if a point is inside a multipolygon geometry.
 *
 * @param MultiPolygon $mpolygon
 * @param Point $point
 * @return bool
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
/**
 *
 * @param stdClass $data form data.
 * @return stdClass
 */
function treasurehunt_build_custommappingconfig($data) {
    $mapconfig = new stdClass();
    if (empty($data->customlayername)) {
        $mapconfig = null;
    } else {
        if ($data->customlayertype !== 'nongeographic') {
            $bbox = [floatval($data->custommapminlon),
                            floatval($data->custommapminlat),
                            floatval($data->custommapmaxlon),
                            floatval($data->custommapmaxlat)];
            $mapconfig->bbox = $bbox;
        }
        if ($data->customlayertype == 'onlybase') {
            $mapconfig->layertype = 'base';
            $mapconfig->onlybase = true;
            $mapconfig->geographic = true;
        } else if ($data->customlayertype == 'nongeographic') {
            $mapconfig->layertype = 'base';
            $mapconfig->onlybase = true;
            $mapconfig->geographic = false;
        } else {
            $mapconfig->layertype = $data->customlayertype; // Or base or overlay.
            $mapconfig->onlybase = false;
            $mapconfig->geographic = true;
        }

        $mapconfig->wmsurl = $data->customlayerwms;
        if (!empty($data->customwmsparams)) {
            $params = explode('&', $data->customwmsparams);
            $parmsobj = new stdClass();
            foreach ($params as $param) {
                $parts = explode('=', $param);
                $parmsobj->{$parts[0]} = $parts[1];
            }
            $mapconfig->wmsparams = $parmsobj;
        } else {
            $mapconfig->wmsparams = [];
        }
        $mapconfig->layername = $data->customlayername;
    }
    return $mapconfig;
}
/**
 *
 * @param \stdClass $treasurehunt Treasurehunt record.
 * @param context_module $context
 * @return NULL|mixed
 */
function treasurehunt_get_custommappingconfig($treasurehunt, $context = null) {
    if (empty($treasurehunt->custommapconfig)) {
        return null;
    }
    $cm = get_coursemodule_from_instance('treasurehunt', $treasurehunt->id);
    $context = context_module::instance($cm->id);
    $custommapconfig = json_decode($treasurehunt->custommapconfig);
    if ($custommapconfig->geographic === true) {
        $custommapconfig->bbox = array_map(function ($item) {
                                    return floatval($item);
        }, $custommapconfig->bbox);
    } else {
        $custommapconfig->bbox = [null, -50, null, 50];
    }
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_treasurehunt', 'custombackground', 0, 'sortorder DESC, id ASC', false, 0, 0, 1);
    $file = reset($files);
    if ($file) {
        $path = '/'.$context->id.'/mod_treasurehunt/custombackground/0/' . $file->get_filename();
        $moodleurl = new moodle_url('/pluginfile.php' . $path);
        $custommapconfig->custombackgroundurl = $moodleurl->out();
    } else {
        $custommapconfig->custombackgroundurl = null;
    }
    if (trim($custommapconfig->wmsurl) === "") {
        $custommapconfig->wmsurl = null;
    }
    return $custommapconfig;
}
/**
 * @return array int => lang string the options for calculating the treasure hunt grade
 *      from the individual attempt grades.
 */
function treasurehunt_get_grading_options() {
    return array(
        TREASUREHUNT_GRADEFROMSTAGES => get_string('gradefromstages', 'treasurehunt'),
        TREASUREHUNT_GRADEFROMTIME => get_string('gradefromtime', 'treasurehunt'),
        TREASUREHUNT_GRADEFROMABSOLUTETIME => get_string('gradefromabsolutetime', 'treasurehunt'),
        TREASUREHUNT_GRADEFROMPOSITION => get_string('gradefromposition', 'treasurehunt')
    );
}

/**
 * Creates a default road with a default stage to the treasurehunt if empty
 * @param stdClass $treasurehunt
 */
function treasurehunt_create_default_items($treasurehunt) {
    $roads = treasurehunt_get_total_roads($treasurehunt->id);
    $context = context_module::instance($treasurehunt->coursemodule);
    if ($roads == 0) {
        $road = new stdClass();
        $road->name = get_string('road', 'treasurehunt');
        treasurehunt_add_update_road($treasurehunt, $road, $context);
        // Adds a default stage to the road.
        $stage = new stdClass();
        $stage->id = null;
        $stage->roadid = $road->id;
        $stage->timecreated = time();
        $stage->name = get_string('stage', 'treasurehunt');
        $stage->cluetext = '';          // Updated later.
        $stage->cluetextformat = FORMAT_HTML; // Updated later.
        $stage->cluetexttrust = 0;           // Updated later.
        $stage->questiontext = '';          // Updated later.
        $stage->questiontextformat = FORMAT_HTML; // Updated later.
        $stage->questiontexttrust = 0;           // Updated later.
        $stage->id = treasurehunt_insert_stage_form($stage);
    }
}

/**
 * Adds a stage without geometry to a road by updating treasurehunt_stages table.
 * Then set the road as invalid.
 *
 * @param stdClass $stage The extended stage object as used by edit_stage.php
 * @return int The id of the new stage.
 */
function treasurehunt_insert_stage_form(stdClass $stage) {
    GLOBAL $DB;

    // The position of the stage in the road is the next to the last introduced.
    $position = $DB->get_record_sql('SELECT count(id) + 1 as position FROM '
            . '{treasurehunt_stages} WHERE roadid = (?)', array($stage->roadid));
    $stage->position = $position->position;
    $id = $DB->insert_record("treasurehunt_stages", $stage);

    // As the stage has no geometry, the road is set as invalid.
    treasurehunt_set_valid_road($stage->roadid, false);

    return $id;
}

/**
 * Updates the position and/or geometry of a stage in a road by updating
 * treasurehunt_stages table. Then, check if road is valid.
 *
 * @see treasurehunt_set_valid_road()
 * @param Feature $feature The feature with the id, position, road and geometry of the stage.
 * @param stdClass $context The context object.
 */
function treasurehunt_update_geometry_and_position_of_stage(Feature $feature, $context) {
    GLOBAL $DB;
    $stage = new stdClass();
    $stage->position = $feature->getProperty('stageposition');
    $stage->roadid = $feature->getProperty('roadid');
    /** @var Multipolygon $geometry */
    $geometry = $feature->getGeometry();
    $stage->geom = treasurehunt_geometry_to_wkt($geometry);
    $stage->timemodified = time();
    $stage->id = $feature->getId();
    $parms = array('id' => $stage->id);
    $entry = $DB->get_record('treasurehunt_stages', $parms, 'id,position', MUST_EXIST);

    // It can not be change the position of stage once the road is blocked.
    if (treasurehunt_check_road_is_blocked($stage->roadid) && ($stage->position != $entry->position)) {
        print_error('notchangeorderstage', 'treasurehunt');
    }
    // It can not be save an existing stage without geometry.
    if (count($geometry->getComponents()) === 0) {
        print_error('saveemptyridle', 'treasurehunt');
    }
    $DB->update_record('treasurehunt_stages', $stage);

    // Check if road is valid.
    treasurehunt_set_valid_road($stage->roadid);
    $stage  = $DB->get_record('treasurehunt_stages', $parms);
    // Trigger update stage event.
    $eventparams = array(
        'context' => $context,
        'objectid' => $stage->id,
        'other' => $stage->name
    );
    $event  = \mod_treasurehunt\event\stage_updated::create($eventparams);
    $event->add_record_snapshot('treasurehunt_stages', $stage);
    $event->trigger();
}

/**
 * Delete a treasure hunt stage in a road and all fields associated. Then,
 * repositions the other stages in the road and checks if road is valid.
 *
 * @param int $id The stage id.
 * @param stdClass $context The context object.
 */
function treasurehunt_delete_stage($id, $context) {
    GLOBAL $DB;
    $stageresult = $DB->get_record('treasurehunt_stages', array('id' => $id), '*', MUST_EXIST);
    // It can not be delete a stage of a started road.
    if (treasurehunt_check_road_is_blocked($stageresult->roadid)) {
        print_error('notdeletestage', 'treasurehunt');
    }

    $DB->delete_records('treasurehunt_stages', array('id' => $id));
    $DB->delete_records('treasurehunt_attempts', array('stageid' => $id));
    $sql = 'UPDATE {treasurehunt_stages} '
            . 'SET position = position - 1 WHERE roadid = (?) AND position > (?)';
    $params = array($stageresult->roadid, $stageresult->position);
    $DB->execute($sql, $params);
    // Check if road is valid.
    treasurehunt_set_valid_road($stageresult->roadid);
    // Trigger deleted stage event.
    $eventparams = array(
        'context' => $context,
        'objectid' => $id,
        'other' => $stageresult->name
    );
    $event = \mod_treasurehunt\event\stage_deleted::create($eventparams);
    $event->add_record_snapshot('treasurehunt_stages', $stageresult);
    $event->trigger();
}

/**
 * Delete a treasure hunt road and all fields associated.
 *
 * @param int $roadid The road id.
 * @param object $treasurehunt The treasure hunt object.
 * @param stdClass $context The context object.
 */
function treasurehunt_delete_road($roadid, $treasurehunt, $context) {
    GLOBAL $DB;
    $params = array($roadid);
    $stages = $DB->get_records_sql('SELECT id FROM {treasurehunt_stages} WHERE roadid = ?'
    , $params);
    foreach ($stages as $stage) {
        $DB->delete_records_select('treasurehunt_attempts', 'stageid = ?', array($stage->id));
        $DB->delete_records_select('treasurehunt_answers', 'stageid = ?', array($stage->id));
    }
    $DB->delete_records_select('treasurehunt_stages', 'roadid = ?', $params);
    $road = $DB->get_record('treasurehunt_roads', array('id' => $roadid));
    $DB->delete_records('treasurehunt_roads', array('id' => $roadid));
    treasurehunt_update_grades($treasurehunt);

    // Trigger deleted road event.
    $eventparams = array(
        'context' => $context,
        'objectid' => $roadid,
    );
    $event = \mod_treasurehunt\event\road_deleted::create($eventparams);
    $event->add_record_snapshot('treasurehunt_roads', $road);
    $event->trigger();
}

/**
 * Count the total number of roads belonging to an instance of treasurehunt.
 *
 * @param int $treasurehuntid The identifier of treasure hunt to check.
 * @return int The number of roads in the instance.
 */
function treasurehunt_get_total_roads($treasurehuntid) {
    GLOBAL $DB;
    $number = $DB->count_records('treasurehunt_roads', array('treasurehuntid' => $treasurehuntid));
    return $number;
}

/**
 * Count the total number of stages belonging to road.
 *
 * @param int $roadid The identifier of road to check.
 * @return int The number of stages in the road.
 */
function treasurehunt_get_total_stages($roadid) {
    GLOBAL $DB;
    $number = $DB->count_records('treasurehunt_stages', array('roadid' => $roadid));
    return $number;
}

/**
 * Check if a user or group has completed the assigned road of treasure hunt.
 * If the group identifier provided is not 0, the group is checked, else the user is checked.
 *
 * @param int $userid The user id.
 * @param int $groupid The group id.
 * @param int $roadid The road id.
 * @return bool True if user or group has finished the road, else false.
 */
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
            . "AND (a.type='location' OR a.type='qr') "
            . "AND  $grouptype";
    $finished = $DB->get_record_sql($sql, $params);
    if (isset($finished->finished)) {
        return true;
    } else {
        return false;
    }
}

/**
 * Updates the value of validity of a road.
 *
 * @see treasurehunt_is_valid_road()
 * @param int $roadid The road id.
 * @param bool $valid If not set, check actual state of the road.
 */
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

/**
 * Check if a road is blocked.
 *
 * @param int $roadid The road id.
 * @return int Return 1 if road is blocked, else 0.
 */
function treasurehunt_check_road_is_blocked($roadid) {
    global $DB;
    $sql = "SELECT at.success "
            . "FROM {treasurehunt_attempts} at INNER JOIN {treasurehunt_stages} ri "
            . "ON ri.id = at.stageid INNER JOIN {treasurehunt_roads} r "
            . "ON ri.roadid=r.id WHERE r.id=?";
    $params = array($roadid);
    return $DB->record_exists_sql($sql, $params);
}

/**
 * Get all roads and stages in GeoJSON format from an instance of treasurehunt.
 *
 * @param int $treasurehuntid The identifier of treasure hunt.
 * @param stdClass $context The context object.
 * @return array All the roads with stages in GeoJSON format.
 */
function treasurehunt_get_all_roads_and_stages($treasurehuntid, $context) {
    global $DB;

    // Get all stages from the instance of treasure hunt.
    $stagessql = "SELECT stage.id, "
            . "stage.name, stage.cluetext, roadid, position,"
            . "geom as geometry FROM {treasurehunt_stages}  stage"
            . " inner join {treasurehunt_roads} roads on stage.roadid = roads.id"
            . " WHERE treasurehuntid = ? ORDER BY position DESC";
    $stagesresult = $DB->get_records_sql($stagessql, array($treasurehuntid));

    // Get all roads from the instance of treasure hunt.
    $roadssql = "SELECT id, name, CASE WHEN (SELECT COUNT(at.id) "
            . "FROM {treasurehunt_attempts} at INNER JOIN {treasurehunt_stages} ri "
            . "ON ri.id = at.stageid INNER JOIN {treasurehunt_roads} r "
            . "ON ri.roadid=r.id WHERE r.id= road.id) > 0 THEN 1 ELSE 0 "
            . "END AS blocked FROM {treasurehunt_roads} road where treasurehuntid = ?";
    $roads = $DB->get_records_sql($roadssql, array($treasurehuntid));

    foreach ($roads as $road) {
        $stagesinroad = array();
        foreach ($stagesresult as $key => $stage) {
            if ($stage->roadid == $road->id) {
                $stagesinroad [] = $stage;
                unset($stagesresult[$key]);
            }
        }
        $road->stages = treasurehunt_features_to_geojson($stagesinroad, $context, $treasurehuntid);
    }

    return $roads;
}

/**
 * Create or renew the user edition lock in an instance of treasurehunt.
 *
 * @param int $treasurehuntid The identifier of treasure hunt.
 * @param int $userid The identifier of user who block the instance.
 * @return int The lock id.
 */
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
        return $DB->insert_record($table, array('treasurehuntid' => $treasurehuntid, 'userid' => $userid, 'lockedtill' => $time));
    }
}

/**
 * Get the value of the setting lock time.
 *
 * @return int Lock time.
 */
function treasurehunt_get_setting_lock_time() {

    if (($locktimeediting = get_config('mod_treasurehunt', 'locktimeediting')) > 5) {
        return $locktimeediting;
    } else {
        return TREASUREHUNT_LOCKTIME;
    }
}

/**
 * Get the value of the setting game update time.
 *
 * @return int Game update time.
 */
function treasurehunt_get_setting_game_update_time() {

    if (($gameupdatetime = get_config('mod_treasurehunt', 'gameupdatetime')) > 0) {
        return $gameupdatetime;
    } else {
        return TREASUREHUNT_GAMEUPDATETIME;
    }
}

/**
 * Check whether access to editing an instance of treasure hunt is locked to a specific user.
 *
 * @param int $treasurehuntid The identifier of treasure hunt.
 * @param int $userid The identifier of user who want to know if the edition is locked.
 * @return int Return 1 if the edition of the instance is locked, else 0.
 */
function treasurehunt_is_edition_loked($treasurehuntid, $userid) {
    global $DB;

    $select = "treasurehuntid = ? AND lockedtill > ? AND userid != ?";
    $params = array($treasurehuntid, time(), $userid);
    return $DB->record_exists_select('treasurehunt_locks', $select, $params);
}

/**
 * Check if a locking session still exists.
 *
 * @param int $lockid The identifier of lock to check.
 * @return int Return 1 if lock exists, else 0.
 */
function treasurehunt_edition_lock_id_is_valid($lockid) {
    global $DB;

    return $DB->record_exists_select('treasurehunt_locks', "id = ?", array($lockid));
}

/**
 * Get the name of the user who is editing the instance of treasure hunt.
 *
 * @param int $treasurehuntid The identifier of treasure hunt.
 * @return string
 */
function treasurehunt_get_username_blocking_edition($treasurehuntid) {
    global $DB;

    $table = 'treasurehunt_locks';
    $params = array('treasurehuntid' => $treasurehuntid);
    $result = $DB->get_record($table, $params);
    return treasurehunt_get_user_fullname_from_id($result->userid);
}

/**
 * Removes all locks belonging to a concrete instance of treasure hunt whose time has been exceeded.
 *
 * @param int $treasurehuntid The identifier of treasure hunt.
 */
function treasurehunt_delete_old_locks($treasurehuntid) {
    global $DB;

    $DB->delete_records_select('treasurehunt_locks', "lockedtill < ? AND treasurehuntid = ? ", array(time(), $treasurehuntid));
}

/**
 * Get the play mode to the stage of a user or group.
 * If the group identifier provided is not 0, the group is checked, else the user is checked.
 *
 * @param int $userid The identifier of user.
 * @param int $groupid The identifier of group.
 * @param int $roadid The identifier of the road of user or group.
 * @param stdClass $treasurehunt The treasurehunt instance.
 * @return int 1 for play without moving and 0 for the other.
 */
function treasurehunt_get_play_mode($userid, $groupid, $roadid, $treasurehunt) {
    global $DB;

    if ($treasurehunt->playwithoutmoving) {
        return 1;
    }
    if ($groupid) {
        $grouptype = 'a.groupid=(?)';
        $params = array($groupid, $roadid);
    } else {
        $grouptype = 'a.groupid=0 AND a.userid=(?)';
        $params = array($userid, $roadid);
    }
    $sql = "SELECT r.playstagewithoutmoving FROM {treasurehunt_stages} r "
            . "WHERE r.position = (SELECT COALESCE(MAX(ri.position) +1,1) FROM {treasurehunt_stages} ri "
            . "INNER JOIN {treasurehunt_attempts} a ON ri.id= a.stageid WHERE "
            . "a.success = 1 AND ri.roadid = r.roadid AND $grouptype) AND r.roadid = ?";
    $playmode = $DB->get_record_sql($sql, $params);
    return $playmode->playstagewithoutmoving;
}

/**
 * Checks if the point sent by the user or the group is within the geometry of the corresponding stage.
 * Also checks the qrtext param.
 * If the group identifier provided is not 0, the group is checked, else the user is checked.
 *
 * @param int $userid The identifier of user.
 * @param int $groupid The identifier of group.
 * @param int $roadid The identifier of the road of user or group.
 * @param Point $point The identifier of the road of user or group.
 * @param stdClass $context The context object.
 * @param stdClass $treasurehunt The treasurehunt instance.
 * @param int $nostages The total number of stages in the road.
 * @return stdClass The control parameters.
 */
function treasurehunt_check_user_location($userid, $groupid, $roadid, $point, $qrtext, $context, $treasurehunt, $nostages) {
    global $DB;
    $return = new stdClass();
    $locationgeom = null;
    $return->update = '';
    $return->roadfinished = false;
    $return->success = false;
    // Last attempt data with correct geometry to know if it has resolved geometry and the stage is overcome.
    $currentstage = treasurehunt_get_last_successful_attempt($userid, $groupid, $roadid);
    if (!$currentstage || $currentstage->success) {
        $return->newattempt = true;
        if ($currentstage) {
            $nextnostage = $currentstage->position + 1;
        } else {
            $nextnostage = 1;
        }
        $nextstage = $DB->get_record('treasurehunt_stages', array('position' => $nextnostage, 'roadid' => $roadid), '*', MUST_EXIST);
        // Check qrtext or location
        $nextstagegeom = treasurehunt_wkt_to_object($nextstage->geom);
        $inside = $point == null ? false : treasurehunt_check_point_in_multipolygon($nextstagegeom, $point);
        $qrguessed = $qrtext == '' ? false : $nextstage->qrtext === $qrtext;
        // If point is within stage geometry or qrguessed.
        if ($inside || $qrguessed) {
            $questionsolved = ($nextstage->questiontext === '' ? true : false);
            $activitysolved = (treasurehunt_check_activity_completion($nextstage->activitytoend) == 0 ? true : false);
            if ($questionsolved && $activitysolved) {
                $success = true;
            } else {
                $success = false;
            }
            $penalty = false;
            $return->msg = $return->update = get_string('successlocation', 'treasurehunt');
            $return->newstage = true;
        } else {
            $penalty = true;
            $questionsolved = false;
            $activitysolved = false;
            $success = false;
            $return->msg = $return->update = get_string('faillocation', 'treasurehunt');
            $return->newstage = false;
        }
        // Get a reasonable location for reporting.
        if ($inside) {
            $locationgeom = $point;
        } else if (isset($qrtext)) { // This is a QR scan.
            if ($qrguessed) {
                $locationgeom = treasurehunt_geometry_centroid($nextstagegeom);
            } else {
                // Get last known location in the tracking.
                $locationgeom = treasurehunt_get_last_location($treasurehunt, $userid);
                if ($locationgeom === null) { // If not, report an approximation.
                    if ($currentstage == false ) {
                        // If this is previous of the first stage, it is safe to report the initial point.
                        $locationgeom = treasurehunt_geometry_centroid($nextstagegeom);
                    } else {
                        // Report the location of current stage (already discovered).
                        $locationgeom = treasurehunt_wkt_to_object($currentstage->location);
                    }     
                }
            }
        } else {
            $locationgeom = $point;
        }
        // Create the attempt.
        $attempt = new stdClass();
        $attempt->stageid = $nextstage->id;
        $attempt->timecreated = time();
        $attempt->userid = $userid;
        $attempt->groupid = $groupid;
        $attempt->success = $success;
        $attempt->type = isset($qrtext) ? 'qr' : 'location';
        $attempt->activitysolved = $activitysolved;
        $attempt->questionsolved = $questionsolved;
        $attempt->geometrysolved = $inside || $qrguessed;
        $attempt->location = treasurehunt_geometry_to_wkt($locationgeom);
        $attempt->penalty = $penalty;

        treasurehunt_insert_attempt($attempt, $context);
        // If the attempt succeeds the location and there is an activity to overcome, it is checked if it is exceeded.
        if ($attempt->geometrysolved && !$activitysolved) {
            if ($usercompletion = treasurehunt_check_completion_activity($nextstage->activitytoend, $userid, $groupid, $context)) {
                $attempt->type = 'activity';
                $attempt->activitysolved = 1;
                $attempt->userid = $usercompletion;
                // To follow a chronological order.
                $attempt->timecreated += 1;
                if ($questionsolved) {
                    $attempt->success = 1;
                }
                treasurehunt_insert_attempt($attempt, $context);
                // If it has already exceeded the location attempt is inserted.
                if ($questionsolved) {
                    $attempt->type = isset($qrtext) ? 'qr' : 'location';
                    // To follow a chronological order.
                    $attempt->timecreated += 1;
                    treasurehunt_insert_attempt($attempt, $context);
                }
                $return->update = get_string('activitytoendovercome', 'treasurehunt', treasurehunt_get_activity_to_end_link($nextstage->activitytoend));
            }
        }
        if ($attempt->success) {
            $return->success = true;
        }

        if ($attempt->success && $nextnostage == $nostages) {
            treasurehunt_road_finished($treasurehunt, $groupid, $userid, $context);

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
    // Track user's position.
    if ($treasurehunt->tracking && $point != null) {
        $currentworkingstage = $nextstage ? $nextstage : $currentstage;
        treasurehunt_track_user($userid, $treasurehunt, $currentworkingstage->id, time(), treasurehunt_geometry_to_wkt($point));
    }
    return $return;
}

/**
 * @param int $activitytoendid The identifier of the activity in the course to end.
 * @return string The link to activity to end.
 */
function treasurehunt_get_activity_to_end_link($activitytoendid) {
    global $COURSE;
    if ($activitytoendid != 0) {
        $modinfo = get_fast_modinfo($COURSE);
        $cmactivitytoend = $modinfo->get_cm($activitytoendid);
        return '<a title="' . $cmactivitytoend->name . '" data-ajax="false" '
                . 'href="' . $cmactivitytoend->url->__toString() . '">' . $cmactivitytoend->name . '</a>';
    } else {
        return '';
    }
}

/**
 * Checks if an instance is available.
 *
 * @param stdClass $treasurehunt The treasurehunt instance.
 * @return stdClass The object availability parameters.
 */
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

/**
 * Get the answers to the question of the given stage.
 *
 * @param int $stageid The identifier of the stage.
 * @param stdClass $context The context object.
 * @return array
 */
function treasurehunt_get_stage_answers($stageid, $context) {
    global $DB;

    $sql = "SELECT id,answertext from {treasurehunt_answers} WHERE stageid = ?";
    $answers = $DB->get_records('treasurehunt_answers', array('stageid' => $stageid), '', 'id,answertext');
    foreach ($answers as &$answer) {
        $answer->answertext = file_rewrite_pluginfile_urls($answer->answertext, 'pluginfile.php', $context->id, 'mod_treasurehunt', 'answertext', $answer->id);
    }
    return $answers;
}

/**
 * Convert an array of features in GeoJSON format.
 *
 * @param array $features The array of features to convert.
 * @param stdClass $context The context object.
 * @param int $treasurehuntid The identifier of the treasure hunt instance.
 * @param int $groupid The identifier of the group or 0 if is individually.
 * @return array
 */
function treasurehunt_features_to_geojson($features, $context, $treasurehuntid, $groupid = 0) {
    $featuresarray = array();
    foreach ($features as $feature) {
        $geometry = treasurehunt_wkt_to_object($feature->geometry);
        if (isset($feature->cluetext)) {
            $cluetext = file_rewrite_pluginfile_urls($feature->cluetext, 'pluginfile.php', $context->id, 'mod_treasurehunt',
                    'cluetext', isset($feature->stageid) ? $feature->stageid : $feature->id);
        } else {
            $cluetext = null;
        }
        $attr = array('roadid' => intval($feature->roadid),
            'stageposition' => intval($feature->position),
            'name' => isset($feature->name) ? $feature->name : '',
            'treasurehuntid' => $treasurehuntid,
            'clue' => $cluetext);
        if (property_exists($feature, 'geometrysolved') && property_exists($feature, 'success')) {
            $attr['geometrysolved'] = intval($feature->geometrysolved);
            // The type of attempt is modified to location for the next function.
            $feature->type = "location";
            $attr['info'] = treasurehunt_set_string_attempt($feature, $groupid);
        }
        $feature = new Feature(isset($feature->id) ? intval($feature->id) : 0, $geometry, $attr);
        array_push($featuresarray, $feature);
    }
    $featurecollection = new FeatureCollection($featuresarray);
    $geojson = $featurecollection->getGeoInterface();

    return $geojson;
}

/**
 * Get the name and clue for a given attempt.
 *
 * @param stdClass $attempt The object attempt.
 * @param stdClass $context The context object.
 * @return stdClass
 */
function treasurehunt_get_name_and_clue($attempt, $context) {
    $return = new stdClass();
    $return->name = get_string('lockedclue', 'treasurehunt');
    if (!$attempt->activitysolved) {
        $activitytoendname = treasurehunt_get_activity_to_end_link($attempt->activitytoend);
    }
    if ((!$attempt->questionsolved && $attempt->questiontext !== '') && (!$attempt->activitysolved && $attempt->activitytoend)) {
        $return->clue = get_string('lockedaqclue', 'treasurehunt', $activitytoendname);
    } else if (!$attempt->questionsolved && $attempt->questiontext !== '') {
        $return->clue = get_string('lockedqclue', 'treasurehunt');
    } else if (!$attempt->activitysolved && $attempt->activitytoend) {
        $return->clue = get_string('lockedaclue', 'treasurehunt', $activitytoendname);
    } else {
        $return->name = $attempt->name;
        $return->clue = file_rewrite_pluginfile_urls($attempt->cluetext, 'pluginfile.php', $context->id, 'mod_treasurehunt', 'cluetext', $attempt->stageid);
    }
    return $return;
}
/**
 *
 * @param stdClass $treasurehunt
 * @param int $groupid
 * @param int $userid
 * @param context_module $context
 */
function treasurehunt_road_finished($treasurehunt, $groupid, $userid, $context) {
    if ($treasurehunt->grademethod != TREASUREHUNT_GRADEFROMSTAGES) {
        treasurehunt_update_grades($treasurehunt);
    } else {
        treasurehunt_set_grade($treasurehunt, $groupid, $userid);
    }

    // Launch events about the finishing the trasurehunt.
    if ($groupid) {
        $users = get_enrolled_users($context, 'mod/treasurehunt:play', $groupid, 'u.id');
    } else {
        $user = new stdClass();
        $user->id = $userid;
        $users [] = $user;
    }
    // Completion.
    $cm = get_fast_modinfo($treasurehunt->course)->instances['treasurehunt'][$treasurehunt->id];
    $course = get_course($cm->course);
    $completion = new completion_info($course);

    foreach ($users as $user) {
        $event = \mod_treasurehunt\event\hunt_succeded::create(array(
                        'objectid' => $treasurehunt->id,
                        'context' => $context,
                        'other' => array('groupid' => $groupid),
                        'userid' => $user->id,
        ));
        $event->add_record_snapshot("treasurehunt", $treasurehunt);
        $event->trigger();
        // Notify that this user entitles for finishing completion.
        if ($completion->is_enabled($cm) && $cm->completion == COMPLETION_TRACKING_AUTOMATIC) {
            $completion->update_state($cm, COMPLETION_UNKNOWN, $userid);
        }
    }
}
/**
 *
 * @param unknown $data
 */
function treasurehunt_set_custombackground($data) {
    global $DB;
    $fs = get_file_storage();
    $cmid = $data->coursemodule;
    $draftitemid = $data->custombackground;

    $context = context_module::instance($cmid);
    if ($draftitemid) {
        $options = array('subdirs' => false, 'embed' => false);
        file_save_draft_area_files($draftitemid, $context->id, 'mod_treasurehunt', 'custombackground', 0, $options);
    }
    $files = $fs->get_area_files($context->id, 'mod_treasurehunt', 'custombackground', 0, 'sortorder', false);
    if (count($files) == 1) {
        // Only one file attached, set it as main file automatically.
        $file = reset($files);
        file_set_sortorder($context->id, 'mod_treasurehunt', 'custombackground', 0, $file->get_filepath(), $file->get_filename(), 1);
    }
}
/**
 * Set a grade to a user or group for a given treasure hunt instance.
 * If the group identifier provided is not 0, the group is checked, else the user is checked.
 *
 * @param stdClass $treasurehunt The treasurehunt instance.
 * @param int $groupid The identifier of group.
 * @param int $userid The identifier of user.
 * @return stdClass
 */
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

/**
 * @global moodle_database $DB
 * @param type $treasurehuntid
 * @param type $userid
 * @param type $groupid
 */
function treasurehunt_get_hunt_duration($cmid, $userid, $groupid) {
    global $DB;
    list ($course, $cm) = get_course_and_cm_from_cmid($cmid, 'treasurehunt');
    $sql = <<<SQL
select (max(a.timecreated)-min(a.timecreated)) as duration,
        max(a.timecreated) as last, min(a.timecreated) as first from {treasurehunt_attempts} a
left join {treasurehunt_stages} s on (a.stageid=s.id)
left join {treasurehunt_roads} r on (r.id=s.roadid)
left join {treasurehunt} t on (r.treasurehuntid = t.id)
where t.id=?
SQL;
    $params = [$cm->instance];
    if ($userid) {
        $sql = $sql . ' and a.userid = ?';
        $params[] = $userid;
    }
    if ($groupid) {
        $sql = $sql . ' and a.groupid = ?';
        $params[] = $groupid;
    }
    $result = $DB->get_record_sql($sql, $params);
    return $result ? $result->duration : false;
}

/**
 * Get the user or group progress for a given road of an instance.
 * If the group identifier provided is not 0, the group is checked, else the user is checked.
 *
 * @param int $roadid The identifier of the road of user or group.
 * @param int $groupid The identifier of group.
 * @param int $userid The identifier of user.
 * @param int $treasurehuntid The identifier of treasure hunt instance.
 * @param stdClass $context The context object.
 * @return array The attempts by the user or group and / or geometry of the first stage.
 */
function treasurehunt_get_user_progress($roadid, $groupid, $userid, $treasurehuntid, $context) {
    global $DB;
    $firststagegeomgeojson = false;
    // Get discovered stages and mistakes made by the user / group for this instance.
    if ($groupid) {
        $grouptype = 'a.groupid=(?)';
        $grouptypewithin = 'at.groupid=?';
        $params = array($roadid, $groupid, $roadid, $groupid);
    } else {
        $grouptype = 'a.groupid=0 AND a.userid=(?)';
        $grouptypewithin = 'at.groupid=0 AND at.userid=?';
        $params = array($roadid, $userid, $roadid, $userid);
    }
    $query = "SELECT a.id as id,a.timecreated,a.userid as \"user\",a.stageid,CASE WHEN a.success = 0 "
            . "THEN NULL ELSE r.name END AS name, CASE WHEN a.success=0 THEN NULL ELSE "
            . "r.cluetext END AS cluetext,a.geometrysolved,r.position,a.location as geometry,"
            . "r.roadid,r.id AS stageid,a.success FROM ("
            . "SELECT MAX(at.id) AS id,"
            . "at.location AS geometry FROM {treasurehunt_attempts} at "
            . "INNER JOIN {treasurehunt_stages} ri ON ri.id=at.stageid WHERE ri.roadid=? "
            . "AND $grouptypewithin group by at.stageid, at.location) apt "
            . "INNER JOIN {treasurehunt_attempts} a ON "
            . "a.id = apt.id INNER JOIN {treasurehunt_stages} r ON a.stageid=r.id WHERE "
            . "r.roadid=? AND $grouptype";
    $userprogress = $DB->get_records_sql($query, $params);
    $geometrysolved = false;
    foreach ($userprogress as $attempt) {
        if ($attempt->geometrysolved) {
            $geometrysolved = true;
        }
    }
    // If the user does not have any progress, the geometry of the first stage of the road shows.
    if (count($userprogress) == 0 || !$geometrysolved) {
        $query = "SELECT position -1 as position,geom as geometry,"
                . "roadid FROM {treasurehunt_stages}  WHERE  roadid=? AND position=1";
        $params = array($roadid);
        $firststagegeom = $DB->get_records_sql($query, $params);
        // Convert the feature format in GeoJSON.
        $firststagegeomgeojson = treasurehunt_features_to_geojson($firststagegeom, $context, $treasurehuntid, $groupid);
    }
    // Convert the features format in GeoJSON.
    $attemptsgeojson = treasurehunt_features_to_geojson($userprogress, $context, $treasurehuntid, $groupid);
    return array($attemptsgeojson, $firststagegeomgeojson);
}

/**
 * Check if a road is valid. For a road to be valid,
 * it must contain more than two stages, and they all have a geometry.
 *
 * @param int $roadid The road id.
 * @return bool True/false.
 */
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

/**
 * Check whether a user or a component of a group has completed an activity
 * If the group identifier provided is not 0, the group is checked, else the user is checked.
 *
 * @param int $cmid The identifier of course module activity.
 * @param int $userid The identifier of user.
 * @param int $groupid The identifier of group.
 * @param stdClass $context The context object.
 * @return int|bool userid that meets the completion/false if nobody in the group has completed.
 */
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
        if ($current->completionstate !== 0) {
            return $user->id;
        }
    }
    return false;
}

/**
 * Checks if an activity has enabled completion
 *
 * @param int $cmid The identifier of course module activity.
 * @return bool True/false.
 */
function treasurehunt_check_activity_completion($cmid) {
    global $DB;
    if ($cmid != 0) {
        $cm = $DB->get_record('course_modules', array('id' => $cmid), 'completion', IGNORE_MISSING);
        if ($cm && isset($cm->completion)) {
            return $cm->completion;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

/**
 * Get the road assigned to a group
 *
 * @param int $groupid The identifier of group.
 * @param int $treasurehuntid The identifier of treasure hunt instance.
 * @param string $groupname The group name.
 * @return array
 */
function treasurehunt_get_group_road($groupid, $treasurehuntid, $groupname = '') {
    global $DB;

    $query = "SELECT r.id as roadid, r.validated, gg.groupid "
            . "FROM  {treasurehunt_roads} r INNER JOIN {groupings_groups} "
            . "gg ON gg.groupingid = r.groupingid  WHERE gg.groupid =? AND r.treasurehuntid=?";
    $params = array($groupid, $treasurehuntid);
    $groupdata = $DB->get_records_sql($query, $params);
    if (count($groupdata) === 0) {
        // The group does not belong to any grouping.
        print_error('nogrouproad', 'treasurehunt', '', $groupname);
    } else if (count($groupdata) > 1) {
        // The group belong to more than one grouping.
        print_error('groupmultipleroads', 'treasurehunt', '', $groupname);
    } else {
        if (current($groupdata)->validated == 0) {
            // The road is not valid.
            print_error('groupinvalidroad', 'treasurehunt', '', $groupname);
        }
        return current($groupdata);
    }
}

/**
 * Get the road and the group assigned to an user.
 *
 * @param int $userid The identifier of user to check.
 * @param stdClass $treasurehunt The treasurehunt instance.
 * @param int $cmid The identifier of treasure hunt course module activity.
 * @param bool $teacherreview If the function is invoked by a review of the teacher.
 * @param string $username The user name.
 * @return array
 */
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
    $query = "SELECT r.id as roadid, count(r.id) as groupsnumber, "
            . "gm.groupid, r.validated FROM {treasurehunt_roads} r "
            . "INNER JOIN  $cond WHERE gm.userid =? AND "
            . "r.treasurehuntid=? group by r.id, gm.groupid, r.validated";
    $params = array($userid, $treasurehunt->id);
    $userdata = $DB->get_records_sql($query, $params);

    // If the instance is individually and there is no road assigned to the user
    // check if there is only one road in the instance.
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
        // The user does not belong to any group.
        throw new exception($errormsg, 'treasurehunt', $returnurl, $username);
    } else if (count($userdata) > 1) {
        if ($treasurehunt->groupmode) {
            $errormsg = 'multiplegroupingsplay';
        } else {
            $errormsg = 'multiplegroupsplay';
        }
        if ($teacherreview) {
            $errormsg = 'usermultipleroads';
        }
        // The user belongs to more than one group.
        throw new exception($errormsg, 'treasurehunt', $returnurl, $username);
    } else {
        if ($treasurehunt->groupmode) {
            if (current($userdata)->groupsnumber > 1) {
                if ($teacherreview) {
                    $errormsg = 'usermultiplesameroad';
                } else {
                    $errormsg = 'multiplegroupssameroadplay';
                }
                // The user belongs to more than one group within a grouping.
                throw new exception($errormsg, 'treasurehunt', $returnurl, $username);
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
            // The road is not valid.
            throw new exception($errormsg, 'treasurehunt', $returnurl, $username);
        } else {
            return current($userdata);
        }
    }
}

/**
 * Check all duplicate users or groups from a list
 *
 * @param array $totalparticipants The total list of participants.
 * @param array $userlist The total list of users/groups.
 * @param array $duplicated The list of users/groups names duplicate.
 * @param bool $grouping If is true is check a group, else is a user.
 * @return array $totalparticipants and $duplicated
 */
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

/**
 * Check all users who do not have an assigned road
 *
 * @param array $totalparticipants The total list of participants.
 * @param array $userlist The total list of users.
 * @return array The list of users names who do not have an assigned road
 */
function treasurehunt_get_all_users_has_none_groups_and_roads($totalparticipants, $userlist) {

    $unassignedusers = array();

    foreach ($userlist as $user) {
        if (!array_key_exists($user->id, $totalparticipants)) {
            $unassignedusers[$user->id] = fullname($user);
        }
    }
    return $unassignedusers;
}

/**
 * Get the full list of participants and their attempts for all the roads
 * of the treasure hunt instance
 *
 * @param stdClass $cm The treasure hunt course module activity.
 * @param array $courseid The identifier of the course.
 * @param stdClass $context The context object.
 * @return array The roads, duplicate users and unassignedusers
 */
function treasurehunt_get_list_participants_and_attempts_in_roads($cm, $courseid, $context) {
    global $DB;

    $roads = array();
    $totalparticipantsgroups = array();
    $totalparticipants = array();
    $duplicategroupsingroupings = array();
    $duplicateusersingroups = array();
    $unassignedusers = array();
    $grouplist = array();

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
    $attemptsquery = "SELECT a.id, $user as \"user\", r.position, a.timecreated, CASE WHEN EXISTS(SELECT 1 FROM "
            . "{treasurehunt_stages} ri INNER JOIN {treasurehunt_attempts} at "
            . "ON at.stageid=ri.id WHERE ri.position=r.position AND ri.roadid=r.roadid "
            . "AND $groupidwithin AND at.penalty=1) THEN 1 ELSE 0 end as withfailures, "
            . "CASE WHEN EXISTS(SELECT 1 FROM {treasurehunt_stages} ri INNER JOIN "
            . "{treasurehunt_attempts} at ON at.stageid=ri.id WHERE ri.position=r.position "
            . "AND ri.roadid=r.roadid AND $groupidwithin AND at.success=1 AND "
            . "(at.type='location' OR at.type='qr')) THEN 1 ELSE 0 end as success FROM {treasurehunt_attempts} a INNER JOIN "
            . "{treasurehunt_stages} r ON a.stageid=r.id INNER JOIN {treasurehunt_roads} "
            . "ro ON r.roadid=ro.id WHERE ro.treasurehuntid=? AND $groupid "
            . "order by r.position, \"user\", a.id, r.roadid";
    $roadsquery = "SELECT id as roadid,$grouptype,validated, name as roadname, "
            . "(SELECT MAX(position) FROM {treasurehunt_stages} where roadid "
            . "= r.id) as totalstages from {treasurehunt_roads} r where treasurehuntid=?";
    $params = array($cm->instance);
    $attempts = $DB->get_records_sql($attemptsquery, $params);
    if ($cm->groupmode) {
        // Group mode.
        // Get all groupings available in the activity.
        $availablegroupings = $DB->get_records_sql($roadsquery, $params);
        // For each grouping gets all groups containing.
        foreach ($availablegroupings as $groupingid) {
            if ($groupingid->groupingid == 0) {
                $groupingid->groupingid = -1;
            }
            $grouplist = groups_get_all_groups($courseid, null, $groupingid->groupingid);

            // Check if there is more than one road assigned to each group.
            list($totalparticipantsgroups,
                    $duplicategroupsingroupings) = treasurehunt_get_all_users_has_multiple_groups_or_roads($totalparticipantsgroups, $grouplist, $duplicategroupsingroupings, true);
            $roads = treasurehunt_add_road_userlist($roads, $groupingid, $grouplist, $attempts);
        }

        // Check if there are participants in more than one group in the same road.
        foreach ($totalparticipantsgroups as $group) {
            list($totalparticipants, $duplicateusersingroups) = treasurehunt_get_all_users_has_multiple_groups_or_roads(
                    $totalparticipants, get_enrolled_users($context, 'mod/treasurehunt:play', $group->id), $duplicateusersingroups,
                    false);
        }
    } else {
        // Individual mode.
        $availablegroups = $DB->get_records_sql($roadsquery, $params);
        // If there is only one road validated and no groups.
        if (count($availablegroups) === 1 && current($availablegroups)->groupid == 0) {
            $totalparticipants = get_enrolled_users($context, 'mod/treasurehunt:play');
            $roads = treasurehunt_add_road_userlist($roads, current($availablegroups), $totalparticipants, $attempts);
        } else {
            foreach ($availablegroups as $group) {
                if ($group->groupid) {
                    $grouplist[] = $group;
                    $userlist = get_enrolled_users($context, 'mod/treasurehunt:play', $group->groupid);

                    // Check if there is more than one road assigned to each user.
                    list($totalparticipants,
                            $duplicateusersingroups) = treasurehunt_get_all_users_has_multiple_groups_or_roads(
                            $totalparticipants, $userlist, $duplicateusersingroups, false);
                } else {
                    $userlist = array();
                }
                $roads = treasurehunt_add_road_userlist($roads, $group, $userlist, $attempts);
            }
        }
    }
    // Check if any user with access can not perform the activity.
    $totalparticipantsincourse = get_enrolled_users($context, 'mod/treasurehunt:play');
    if ((count($totalparticipantsincourse) !== count($totalparticipants))) {
        $unassignedusers = treasurehunt_get_all_users_has_none_groups_and_roads($totalparticipants, $totalparticipantsincourse);
    }
    return array($roads, $duplicategroupsingroupings, $duplicateusersingroups, $unassignedusers, $grouplist);
}



/**
 * Get the latest timestamp made by the group / user for the road and the last modification timestamp of the road.
 * If the group identifier provided is not 0, the group is checked, else the user is checked.
 *
 * @param int $userid The identifier of user.
 * @param int $groupid The identifier of group.
 * @param int $roadid The identifier of the road of user or group.
 * @return array Both timestamps.
 */
function treasurehunt_get_last_timestamps($userid, $groupid, $roadid) {
    global $DB;

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

/**
 * Get the latest attempt with geometry solved by the user / group for the given road.
 * If the group identifier provided is not 0, the group is checked, else the user is checked.
 *
 * @param int $userid The identifier of user.
 * @param int $groupid The identifier of group.
 * @param int $roadid The identifier of the road of user or group.
 * @return false|stdClass the record object or false if there is not succesful attempt.
 */
function treasurehunt_get_last_successful_attempt($userid, $groupid, $roadid) {
    global $DB;

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
            . "$grouptypewithin AND ri.roadid=r.roadid AND at.geometrysolved=1) "
            . "AND $grouptype AND r.roadid = ?";
    $lastsuccesfulattempt = $DB->get_record_sql($sql, $params);
    return $lastsuccesfulattempt;
}

/**
 * Check if the user or group has correctly answered the question and complete the required activity.
 * If the group identifier provided is not 0, the group is checked, else the user is checked.
 *
 * @param int $selectedanswerid The identifier of the answer selected by the user.
 * @param int $userid The identifier of user.
 * @param int $groupid The identifier of the group to which the user belongs.
 * @param int $roadid The identifier of the road of user.
 * @param bool $updateroad If the road has been updated or not.
 * @param stdClass $context The context object.
 * @param stdClass $treasurehunt The treasurehunt instance.
 * @param int $nostages The total number of stages in the road.
 * @param bool $qoaremoved If the question or activity to end has been removed or not.
 * @return stdClass The control parameters.
 */
function treasurehunt_check_question_and_activity_solved($selectedanswerid, $userid, $groupid, $roadid, $updateroad,
                                                            $context, $treasurehunt, $nostages, $qoaremoved) {
    global $DB;

    $return = new stdClass();
    $return->msg = '';
    $return->updates = array();
    $return->newattempt = false;
    $return->attemptsolved = false;
    $return->roadfinished = false;
    $return->qoaremoved = false;
    $return->success = false;

    $lastattempt = treasurehunt_get_last_successful_attempt($userid, $groupid, $roadid);

    // If the last attempt has resolved geometry but the stage is not exceeded.
    if ($lastattempt && !$lastattempt->success && $lastattempt->geometrysolved) {
        $lastattempt->userid = $userid;
        $lastattempt->groupid = $groupid;
        $activitysolved = false;
        // If the last attempt is not completed activity to overcome.
        if (!$lastattempt->activitysolved) {
            // If there is an activity to overcome.
            if (treasurehunt_check_activity_completion($lastattempt->activitytoend)) {
                if ($usercompletion = treasurehunt_check_completion_activity($lastattempt->activitytoend, $userid, $groupid, $context)) {
                    $return->newattempt = true;
                    $return->attemptsolved = true;
                    $return->updates[] = get_string('activitytoendovercome', 'treasurehunt', treasurehunt_get_activity_to_end_link($lastattempt->activitytoend));
                    // If there is no question and still has not been resolved.
                    if (!$lastattempt->questionsolved && $lastattempt->questiontext === '') {
                        $lastattempt->questionsolved = 1;
                        $return->updates[] = get_string('removedquestion', 'treasurehunt');
                    }
                    $lastattempt->userid = $usercompletion;
                    $lastattempt->type = 'activity';
                    $lastattempt->timecreated = time();
                    $lastattempt->activitysolved = 1;
                    $lastattempt->penalty = 0;
                    // If the question is already resolved is set to overcome.
                    if ($lastattempt->questionsolved) {
                        $lastattempt->success = 1;
                    } else {
                        $lastattempt->success = 0;
                    }
                    treasurehunt_insert_attempt($lastattempt, $context);
                    $activitysolved = true;
                    // If the stage is overcome, attempt is created.
                    if ($lastattempt->success == 1) {
                        $lastattempt->type = 'location';
                        $lastattempt->timecreated += 1;
                        treasurehunt_insert_attempt($lastattempt, $context);
                    }
                }
            } else { // If there is no activity to overcome is that has been deleted.
                $return->qoaremoved = true;
                if (!$qoaremoved) {
                    $return->updates[] = get_string('removedactivitytoend', 'treasurehunt');
                    $return->attemptsolved = true;
                }
                $lastattempt->activitysolved = 1;
                // If there is no question.
                if ($lastattempt->questiontext === '') {
                    $lastattempt->questionsolved = 1;
                    $return->updates[] = get_string('removedquestion', 'treasurehunt');
                }
                // If the question is overcome.
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
        // If the question is not overcome.
        if (!$lastattempt->questionsolved) {
            // If there is no question.
            if ($lastattempt->questiontext === '') {
                $return->qoaremoved = true;
                if (!$qoaremoved) {
                    $return->updates[] = get_string('removedquestion', 'treasurehunt');
                    $return->attemptsolved = true;
                }
                // If the activity to end is overcome, succesful attempt is created.
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
                // If there is an answer and the road has not been updated.
                if ($selectedanswerid > 0 && !$updateroad) {
                    $answer = $DB->get_record('treasurehunt_answers', array('id' => $selectedanswerid), 'correct,stageid', MUST_EXIST);
                    if ($answer->stageid != $lastattempt->stageid) {
                        $return->msg = get_string('warmatchanswer', 'treasurehunt');
                    } else {
                        $return->newattempt = true;

                        $lastattempt->type = 'question';
                        // To follow a chronological order.
                        $lastattempt->timecreated = time() + 1;
                        if ($answer->correct) {
                            $return->attemptsolved = true;
                            $return->msg = get_string('correctanswer', 'treasurehunt');

                            $lastattempt->questionsolved = 1;
                            $lastattempt->penalty = 0;
                            if ($lastattempt->activitysolved) {
                                $lastattempt->success = 1;
                            } else {
                                $lastattempt->success = 0;
                            }
                            treasurehunt_insert_attempt($lastattempt, $context);
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
            if ($lastattempt->success) {
                $return->success = true;
            }
            if ($lastattempt->success && $lastattempt->position == $nostages) {
                treasurehunt_road_finished($treasurehunt, $groupid, $userid, $context);
                $return->roadfinished = true;
            } else {
                treasurehunt_set_grade($treasurehunt, $groupid, $userid);
            }
        }
        $return->attempttimestamp = $lastattempt->timecreated;
    }

    return $return;
}

/**
 * Inserts one attempt and create the event of attempt submitted.
 *
 * @param stdClass $attempt The attempt object.
 * @param stdClass $context The context object.
 */
function treasurehunt_insert_attempt($attempt, $context) {
    global $DB;
    $id = $DB->insert_record("treasurehunt_attempts", $attempt);
    $attempt->id = $id;
    $event = \mod_treasurehunt\event\attempt_submitted::create(array(
                'objectid' => $id,
                'context' => $context,
                'other' => array('groupid' => $attempt->groupid)
    ));
    $event->add_record_snapshot("treasurehunt_attempts", $attempt);
    $event->trigger();
    // Event stage succeded.
    if ($attempt->success == 1 && $attempt->type == 'location') {
        $event = \mod_treasurehunt\event\attempt_succeded::create(array(
                        'objectid' => $id,
                        'context' => $context,
                        'other' => array('groupid' => $attempt->groupid)
        ));
        $event->add_record_snapshot("treasurehunt_attempts", $attempt);
        $event->trigger();
    }
}

/**
 *
 * @global moodle_database $DB
 * @param int $treasurehuntid
 * @return array userids
 */
function treasurehunt_get_users_with_tracks($treasurehuntid) {
    global $DB;
    $sql = 'SELECT DISTINCT userid from {treasurehunt_track} WHERE treasurehuntid=?';
    $results = $DB->get_records_sql($sql, [$treasurehuntid]);
    return array_keys($results);
}
/**
 * Get last recorded track location.
 * @global moodle_database $DB
 * @param stdClass $treasurehunt
 * @param int $userid
 * @return Geometry|null
 */
function treasurehunt_get_last_location($treasurehunt, $userid) {
    global $DB;
    $sql = 'SELECT location from {treasurehunt_track} WHERE treasurehuntid=? and userid=? order by timestamp desc limit 1';
    $location = $DB->get_record_sql($sql, ['treasurehuntid' => $treasurehunt->id, 'userid' => $userid]);
    if ($location) {
        return treasurehunt_wkt_to_object($location);
    } else {
        return null;
    }
}

/**
 * Inserts one point in a tracked game.
 * @global moodle_database $DB
 * @param type $userid
 * @param type $treasurehunt
 * @param type $currentstageid
 * @param type $time
 * @param type $locationwkt The point in WKT format.
 */
function treasurehunt_track_user($userid, $treasurehunt, $currentstageid, $time, $locationwkt) {
    global $DB;
    $tracking = new stdClass();
    $tracking->treasurehuntid = $treasurehunt->id;
    $tracking->userid = $userid;
    $tracking->timestamp = $time;
    $tracking->location = $locationwkt;
    $tracking->stageid = $currentstageid;
    $id = $DB->insert_record("treasurehunt_track", $tracking);
}

/**
 * Get the last stage of the user or group for the given road.
 * If the group identifier provided is not 0, the group is checked, else the user is checked.
 *
 * @param int $userid The identifier of user.
 * @param int $groupid The identifier of the group to which the user belongs.
 * @param int $roadid The identifier of the road of user.
 * @param int $nostages The total number of stages in the road.
 * @param bool $outoftime If the instance is out of time.
 * @param bool $actnotavailableyet If the instance is not avaible yet.
 * @param stdClass $context The context object.
 * @return stdClass The last succesful stage.
 */
function treasurehunt_get_last_successful_stage($userid, $groupid, $roadid, $nostages, $outoftime, $actnotavailableyet, $context) {

    $lastsuccessfulstage = new stdClass();

    // Get the last attempt with geometry solved by the user / group for the road.
    $attempt = treasurehunt_get_last_successful_attempt($userid, $groupid, $roadid);
    if ($attempt && !$outoftime && !$actnotavailableyet) {
        $lastsuccessfulstage = treasurehunt_get_name_and_clue($attempt, $context);
        $lastsuccessfulstage->id = intval($attempt->stageid);
        $lastsuccessfulstage->totalnumber = $nostages;
        $lastsuccessfulstage->question = '';
        $lastsuccessfulstage->answers = array();
        $lastsuccessfulstage->position = intval($attempt->position);
        $lastsuccessfulstage->activitysolved = intval($attempt->activitysolved);
        if (!$attempt->questionsolved) {
                // Get the questions and answers.
            $lastsuccessfulstage->answers = treasurehunt_get_stage_answers($attempt->stageid, $context);
            $lastsuccessfulstage->question = file_rewrite_pluginfile_urls($attempt->questiontext, 'pluginfile.php', $context->id,
                    'mod_treasurehunt', 'questiontext', $attempt->stageid);
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

/**
 * Checks for updates of attempts from timestamp given.
 * If the group identifier provided is not 0, the group is checked, else the user is checked.
 *
 * @param int $timestamp The last known timestamp since user progress has not been updated.
 * @param int $groupid The identifier of the group to which the user belongs.
 * @param int $userid The identifier of user.
 * @param int $roadid The identifier of the road of user.
 * @param bool $changesingroupmode If the instance has change the group mode.
 * @return stdClass Update parameters.
 */
function treasurehunt_check_attempts_updates($timestamp, $groupid, $userid, $roadid, $changesingroupmode) {
    global $DB;
    $return = new stdClass();
    $return->strings = [];
    $return->newgeometry = false;
    $return->attemptsolved = false;
    $return->geometrysolved = false;
    $return->geometrysolved = false;
    $newattempts = array();

    list($return->newattempttimestamp, $return->newroadtimestamp) = treasurehunt_get_last_timestamps($userid, $groupid, $roadid);
    // If there has been a change in the group mode.
    if ($changesingroupmode) {
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
                . "a.success,a.geometrysolved,a.penalty,r.position,a.userid as \"user\" "
                . "FROM {treasurehunt_stages} r INNER JOIN {treasurehunt_attempts} a "
                . "ON a.stageid=r.id WHERE $grouptype AND r.roadid=? ORDER BY "
                . "a.timecreated ASC";

        $newattempts = $DB->get_records_sql($query, $params);
    }
    // If the retrieved timestamp is greater than the parameter, has been updates.
    if ($return->newattempttimestamp > $timestamp && !$changesingroupmode) {
        // Get user/group actions greater than a given timestamp.
        if ($groupid) {
            $grouptype = 'a.groupid=(?)';
            $params = array($timestamp, $groupid, $roadid);
        } else {
            $grouptype = 'a.groupid=0 AND a.userid=(?)';
            $params = array($timestamp, $userid, $roadid);
        }
        $query = "SELECT a.id,a.type,a.questionsolved,a.activitysolved,a.timecreated,"
                . "a.success,r.position,a.userid as \"user\",a.geometrysolved "
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

/**
 * Get all attempts of a user or group for a given road.
 * If the group identifier provided is not 0, the group is checked, else the user is checked.
 *
 * @param int $groupid The identifier of the group to which the user belongs.
 * @param int $userid The identifier of user.
 * @param int $roadid The identifier of the road of user.
 * @return array All attempts described in strings.
 */
function treasurehunt_get_user_historical_attempts($groupid, $userid, $roadid) {
    global $DB;

    $attempts = [];
    if ($groupid) {
        $grouptype = 'a.groupid=?';
        $params = array($groupid, $roadid);
    } else {
        $grouptype = 'a.groupid=0 AND a.userid=?';
        $params = array($userid, $roadid);
    }
    $query = "SELECT a.id,a.type,a.timecreated,a.questionsolved,"
            . "a.success,a.geometrysolved,a.penalty,r.position,a.userid as \"user\" "
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

/**
 * @global moodle_database $DB
 * @param type $treasurehuntid record id in table treasurehunt
 * @return array
 */
function treasurehunt_get_all_attempts($treasurehuntid) {
    global $DB;
    $query = <<< 'SQL'
select a.*, r.id as roadid, t.id as treasurehuntid from {treasurehunt_attempts} a
    left join {treasurehunt_stages} s on (s.id = a.stageid)
    left join {treasurehunt_roads} r on (r.id = s.roadid)
    left join {treasurehunt} t on (t.id = r.treasurehuntid) where t.id = ?
SQL;
    $params = [$treasurehuntid];
    $results = $DB->get_records_sql($query, $params);
    return $results;
}

/**
 * Clear all recorded activity of this instance.
 * @global moodle_database $DB
 * @param int $treasurehuntid record id in treasurehunt table.
 */
function treasurehunt_clear_activities($treasurehuntid) {
    global $DB;
    $attempts = treasurehunt_get_all_attempts($treasurehuntid);
    if (count($attempts) > 0) {
        $DB->delete_records_list('treasurehunt_attempts', 'id', array_keys($attempts));
    };
    $DB->delete_records('treasurehunt_track', ['treasurehuntid' => $treasurehuntid]);
}
/**
 * Initialices the javascript needed to use QRScanner
 * @param moodle_page $PAGE
 * @param string global function name to initialice the code.
 */
function treasurehunt_qr_support($PAGE, $initfunction = '', $params = null) {
    $PAGE->requires->js('/mod/treasurehunt/js/instascan/webqr.js', false);
    if ($initfunction) {
        $PAGE->requires->js_init_call($initfunction, $params);
    }
}
/**
 * Gets the HTML view format of the information displayed on the main screen.
 *
 * @param stdClass $treasurehunt The treasurehunt instance.
 * @param int $courseid The identifier of course.
 * @return string
 */
function treasurehunt_view_info($treasurehunt, $courseid) {
    global $PAGE, $DB;
    $timenow = time();
    // Get roads.
    $roads = $DB->get_records('treasurehunt_roads', ['treasurehuntid' => $treasurehunt->id]);
    $output = $PAGE->get_renderer('mod_treasurehunt');
    list($select, $params) = $DB->get_in_or_equal(array_keys($roads));
    $select = "roadid $select and qrtext <> ''";
    $hasqr = $DB->count_records_select('treasurehunt_stages', $select, $params, 'count(qrtext)');
    $renderable = new treasurehunt_info($treasurehunt, $timenow, $courseid, $roads, $hasqr);
    return $output->render($renderable);
}

/**
 * Gets the table with the historical attempts of the user or group in HTML format
 * displayed on the main screen.If the group identifier provided is not 0, the group is checked,
 * else the user is checked.
 *
 * @param stdClass $treasurehunt The treasurehunt instance.
 * @param int $groupid The identifier of the group to which the user belongs.
 * @param int $userid The identifier of user.
 * @param int $roadid The identifier of the road of user.
 * @param int $cmid The identifier of course module activity.
 * @param string $username The user name.
 * @param bool $teacherreview If the function is invoked by a review of the teacher.
 * @return string
 */
function treasurehunt_view_user_historical_attempts($treasurehunt, $groupid, $userid, $roadid, $cmid, $username, $teacherreview) {
    global $PAGE;
    $roadfinished = treasurehunt_check_if_user_has_finished($userid, $groupid, $roadid);
    $attempts = treasurehunt_get_user_historical_attempts($groupid, $userid, $roadid);
    if (time() > $treasurehunt->cutoffdate && $treasurehunt->cutoffdate) {
        $outoftime = true;
    } else {
        $outoftime = false;
    }
    $output = $PAGE->get_renderer('mod_treasurehunt');
    $renderable = new treasurehunt_user_historical_attempts($attempts, $cmid, $username, $outoftime, $roadfinished, $teacherreview);
    return $output->render($renderable);
}

/**
 * Gets the view in HTML format of game interface.
 *
 * @param stdClass $treasurehunt The treasurehunt instance.
 * @param int $cmid The identifier of course module activity.
 * @return string
 */
function treasurehunt_view_play_page($treasurehunt, $cmid) {
    global $PAGE;
    $treasurehunt->description = format_module_intro('treasurehunt', $treasurehunt, $cmid);
    $output = $PAGE->get_renderer('mod_treasurehunt');
    $renderable = new treasurehunt_play_page($treasurehunt, $cmid);
    return $output->render($renderable);
}

/**
 * Gets the table with the progress of users or groups with assigned roads,
 * belonging to the instance treasure hunt, in HTML format.
 *
 * @param stdClass $cm The treasure hunt course module activity.
 * @param int $courseid The identifier of course.
 * @param stdClass $context The context object.
 * @return string
 */
function treasurehunt_view_users_progress_table($cm, $courseid, $context) {
    global $PAGE;

    list($roads, $duplicategroupsingroupings, $duplicateusersingroups,
            $unassignedusers, $availablegroups) = treasurehunt_get_list_participants_and_attempts_in_roads($cm, $courseid, $context);
    $viewpermission = has_capability('mod/treasurehunt:viewusershistoricalattempts', $context);
    $managepermission = has_capability('mod/treasurehunt:managetreasurehunt', $context);
    $output = $PAGE->get_renderer('mod_treasurehunt');
    $renderable = new treasurehunt_users_progress($roads, $cm->groupmode, $cm->id, $duplicategroupsingroupings,
            $duplicateusersingroups, $unassignedusers, $viewpermission, $managepermission, $availablegroups);
    return $output->render($renderable);
}

/**
 * Set attempt in a text string with the date
 *
 * @param stdClass $attempt The treasure hunt course module activity.
 * @param bool $groupmode If instance is in group mode or not.
 * @return string
 */
function treasurehunt_set_string_attempt($attempt, $groupmode) {

    $attempt->date = userdate($attempt->timecreated);
    // Si se es un grupo y el usuario no es el mismo que el que lo descubrio/fallo.
    if ($groupmode) {
        $attempt->user = treasurehunt_get_user_fullname_from_id($attempt->user);
        // If it is an attempt to a question.
        if ($attempt->type === 'question') {
            if ($attempt->questionsolved) {
                return get_string('groupquestionovercome', 'treasurehunt', $attempt);
            } else {
                return get_string('groupquestionfailed', 'treasurehunt', $attempt);
            }
        } else if ($attempt->type === 'location' || $attempt->type === 'qr') { // If it is an attempt at a location.
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
    } else {// Individual mode.
        // If it is an attempt to a question.
        if ($attempt->type === 'question') {
            if ($attempt->questionsolved) {
                return get_string('userquestionovercome', 'treasurehunt', $attempt);
            } else {
                return get_string('userquestionfailed', 'treasurehunt', $attempt);
            }
        } else if ($attempt->type === 'location' || $attempt->type === 'qr') { // If it is an attempt at a location.
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

/**
 * Adds a new road or update existing one
 * @param stdClass $treassurehunt
 * @param stdClass $road record for a row: name, treasurehuntid
 * @param type $context
 * @return \stdClass
 */
function treasurehunt_add_update_road(stdClass $treasurehunt, stdClass $road, $context) {
    global $DB;
    $eventparams = array('context' => $context);
    if (empty($road->id)) {
        $road->treasurehuntid = $treasurehunt->id;
        $road->timecreated = time();
        $road->id = $DB->insert_record('treasurehunt_roads', $road);
        $eventparams['objectid'] = $road->id;
        $event = \mod_treasurehunt\event\road_created::create($eventparams);
    } else {
        $DB->update_record('treasurehunt_roads', $road);
        $eventparams['objectid'] = $road->id;
        $event = \mod_treasurehunt\event\road_updated::create($eventparams);
    }
    // Trigger event and update or creation of a road.
    $event->trigger();
    return $road;
}

/**
 * Add the list of users with their attempts to their road assigned.
 *
 * @param array $roads The collection of roads.
 * @param stdClass $data The road information.
 * @param array $userlist The list of users assigned to the road.
 * @param array $attempts The collection of attempts.
 * @return array The collection of roads
 */
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

/**
 * Checks whether the intro of the activity may be shown or not.
 *
 * @param stdClass $treasurehunt The treasurehunt instance.
 * @return bool True/false
 */
function treasurehunt_view_intro($treasurehunt) {
    if ($treasurehunt->alwaysshowdescription || time() > $treasurehunt->allowattemptsfromdate) {
        return true;
    }
    return false;
}

/**
 * Add the list of users with their attempts to their road assigned.
 *
 * @param stdClass $road The road object.
 * @param array $userlist The list of users assigned to the road.
 * @param array $attempts The collection of attempts.
 * @return stdClass The modified road object.
 */
function treasurehunt_insert_stage_progress_in_road_userlist($road, $userlist, $attempts) {
    $road->userlist = array();
    foreach ($userlist as $user) {
        $user->ratings = array();
        // Add each user / group the corresponding color of his stage.
        foreach ($attempts as $key => $attempt) {
            if ($attempt->user === $user->id) {
                $rating = new stdClass();
                $rating->stagenum = $attempt->position;
                $rating->timestamp = $attempt->timecreated;
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
                unset($attempts[$key]);
            }
        }
        $road->userlist [] = clone $user;
    }
    return $road;
}

/**
 * Gets the full name of a user from its identifier.
 *
 * @param int $id The identifier of user.
 * @return string The full name of user.
 */
function treasurehunt_get_user_fullname_from_id($id) {
    global $DB;
    $select = 'SELECT id,firstnamephonetic,lastnamephonetic,middlename,alternatename,firstname,lastname FROM {user} WHERE id = ?';
    $result = $DB->get_records_sql($select, array($id));
    return fullname($result[$id]);
}

/**
 * Get all attempts statistics for restricted users list.
 *
 * @param stdClass $treasurehunt The treasurehunt instance.
 * @param array $restrictedusers The list of users to collect their attempts statistics for.
 * @return array All stats.
 */
function treasurehunt_calculate_stats($treasurehunt, $restrictedusers) {
    global $DB;
    if (count($restrictedusers) == 0 ) {
        return [];
    }
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
    $usercompletiontimesql = "";
    $usertimetable = "";
    if ($treasurehunt->grademethod == TREASUREHUNT_GRADEFROMABSOLUTETIME) {
        $usercompletiontimesql = <<<SQL
(SELECT max(at.timecreated) from {treasurehunt_attempts} at
    INNER JOIN {treasurehunt_stages} ri ON ri.id = at.stageid
    INNER JOIN {treasurehunt_roads} roa ON ri.roadid=roa.id
    WHERE
        at.success=1 AND
        ri.position=1 AND
        roa.treasurehuntid=ro.treasurehuntid
        AND (at.type='location' OR at.type='qr')
        AND $groupidwithin) as usertime
SQL;
        $grademethodsql = <<<SQL
(SELECT max(at.timecreated) from {treasurehunt_attempts} at
    INNER JOIN {treasurehunt_stages} ri ON ri.id = at.stageid
    INNER JOIN {treasurehunt_roads} roa ON ri.roadid=roa.id
where at.success=1
    AND ri.position=(select max(rid.position) from {treasurehunt_stages} rid where rid.roadid=ri.roadid)
    AND roa.treasurehuntid=ro.treasurehuntid
    AND at.type='location'
    AND at.userid IN $users AND $groupid2) as worsttime,
(SELECT min(at.timecreated) from {treasurehunt_attempts} at
    INNER JOIN {treasurehunt_stages} ri ON ri.id = at.stageid
    INNER JOIN {treasurehunt_roads} roa ON ri.roadid=roa.id
where
    at.success=1
    AND ri.position=(select max(rid.position) from {treasurehunt_stages} rid where rid.roadid=ri.roadid)
    AND roa.treasurehuntid=ro.treasurehuntid
    AND (at.type='location' OR at.type='qr')
    AND at.userid IN $users
    AND $groupid2) as besttime, $usercompletiontimesql,
SQL;
        $orderby = 'ORDER BY usertime ASC';
    }
    if ($treasurehunt->grademethod == TREASUREHUNT_GRADEFROMTIME) {
        $orderby = 'ORDER BY usertime ASC';
        $usertimetable = <<<SQL
(SELECT (SELECT max(at.timecreated) from {treasurehunt_attempts} at
    INNER JOIN {treasurehunt_stages} ri ON ri.id = at.stageid
    INNER JOIN {treasurehunt_roads} roa ON ri.roadid=roa.id
where   at.success=1
        AND ri.position=(select max(rid.position) from {treasurehunt_stages} rid where rid.roadid=ri.roadid)
        AND roa.treasurehuntid=ro.treasurehuntid
        AND at.type='location'
        AND $groupidwithin) -
(SELECT max(at.timecreated) from {treasurehunt_attempts} at
    INNER JOIN {treasurehunt_stages} ri ON ri.id = at.stageid
    INNER JOIN {treasurehunt_roads} roa ON ri.roadid=roa.id
where   at.success=1
        AND ri.position=1
        AND roa.treasurehuntid=ro.treasurehuntid
        AND (at.type='location' OR at.type='qr')
        AND $groupidwithin) as usertime
            from {treasurehunt_attempts} a
            INNER JOIN {treasurehunt_stages} r ON r.id=a.stageid
            INNER JOIN {treasurehunt_roads} ro ON r.roadid=ro.id $groupsmembers
            WHERE ro.treasurehuntid=?
                AND a.userid IN $users
                AND $groupid group by $user,ro.treasurehuntid,a.groupid,ro.id $orderby) as time,
SQL;
        $grademethodsql = <<<SQL
            max(time.usertime) as worsttime, min(time.usertime) as besttime,
            (SELECT max(at.timecreated) from {treasurehunt_attempts} at
                INNER JOIN {treasurehunt_stages} ri ON ri.id = at.stageid
                INNER JOIN {treasurehunt_roads} roa ON ri.roadid=roa.id
                where at.success=1
                    AND ri.position=(select max(rid.position) from {treasurehunt_stages} rid where rid.roadid=ri.roadid)
                    AND roa.treasurehuntid=ro.treasurehuntid
                    AND (at.type='location' OR at.type='qr')
                    AND $groupidwithin) -
            (SELECT max(at.timecreated) from {treasurehunt_attempts} at
                    INNER JOIN {treasurehunt_stages} ri ON ri.id = at.stageid
                    INNER JOIN {treasurehunt_roads} roa ON ri.roadid=roa.id
                    where at.success=1
                        AND ri.position=1
                        AND roa.treasurehuntid=ro.treasurehuntid
                        AND (at.type='location' OR at.type='qr')
                        AND $groupidwithin) as usertime,
SQL;
    } else if ($treasurehunt->grademethod == TREASUREHUNT_GRADEFROMPOSITION) {
        $grademethodsql = <<<SQL
            (SELECT COUNT(*) from {treasurehunt_attempts} at
            INNER JOIN {treasurehunt_stages} ri ON ri.id = at.stageid
            INNER JOIN {treasurehunt_roads} roa ON ri.roadid=roa.id
            where at.success=1
                AND ri.position=(select max(rid.position) from {treasurehunt_stages} rid where rid.roadid=ri.roadid)
                AND roa.treasurehuntid=ro.treasurehuntid
                AND (at.type='location' OR at.type='qr')
                AND at.userid IN $users
                AND $groupid2) as lastposition,
            (SELECT max(at.timecreated) from {treasurehunt_attempts} at
            INNER JOIN {treasurehunt_stages} ri ON ri.id = at.stageid
            INNER JOIN {treasurehunt_roads} roa ON ri.roadid=roa.id
            where at.success=1
                AND ri.position=(select max(rid.position) from {treasurehunt_stages} rid where rid.roadid=ri.roadid)
                AND roa.treasurehuntid=ro.treasurehuntid
                AND (at.type='location' OR at.type='qr')
                AND $groupidwithin) as finishtime,
SQL;
        $orderby = 'ORDER BY finishtime ASC';
    }
    $sql = <<<SQL
        SELECT $user as "user", $grademethodsql(SELECT COUNT(*) from {treasurehunt_attempts} at
        INNER JOIN {treasurehunt_stages} ri ON ri.id = at.stageid
        INNER JOIN {treasurehunt_roads} roa ON ri.roadid=roa.id
        where roa.treasurehuntid=ro.treasurehuntid
            AND (at.type='location' OR at.type='qr')
            AND at.penalty=1 AND $groupidwithin) as nolocationsfailed,
        (SELECT COUNT(*) from {treasurehunt_attempts} at
            INNER JOIN {treasurehunt_stages} ri ON ri.id = at.stageid
            INNER JOIN {treasurehunt_roads} roa ON ri.roadid=roa.id
            where roa.treasurehuntid=ro.treasurehuntid
                AND at.type='question'
                AND at.penalty=1
                AND $groupidwithin) as noanswersfailed,
        (SELECT COUNT(*) from {treasurehunt_attempts} at
            INNER JOIN {treasurehunt_stages} ri ON ri.id = at.stageid
            INNER JOIN {treasurehunt_roads} roa ON ri.roadid=roa.id
            where roa.treasurehuntid=ro.treasurehuntid
                AND (at.type='location' or at.type='qr')
                AND at.success=1
                AND $groupidwithin) as nosuccessfulstages,
        (SELECT COUNT(*) from {treasurehunt_stages} ri
            INNER JOIN {treasurehunt_roads} roa ON ri.roadid=roa.id
            where roa.treasurehuntid=ro.treasurehuntid
                AND roa.id=ro.id) as nostages
        from $usertimetable {treasurehunt_attempts} a
            INNER JOIN {treasurehunt_stages} r ON r.id=a.stageid
            INNER JOIN {treasurehunt_roads} ro ON r.roadid=ro.id $groupsmembers
            WHERE ro.treasurehuntid=?
                AND a.userid IN $users
                AND $groupid
            group by $user,ro.treasurehuntid,a.groupid,ro.id $orderby
SQL;
    $stats = $DB->get_records_sql($sql, array($treasurehunt->id, $treasurehunt->id));

    // If the grading method is by position.
    if ($treasurehunt->grademethod == TREASUREHUNT_GRADEFROMPOSITION) {
        $i = 0;
        $grouptimes = array();
        foreach ($stats as $stat) {
            if (isset($stat->finishtime)) {
                if (isset($grouptimes[$stat->finishtime])) {
                    $stat->position = $i;
                } else {
                    $grouptimes[$stat->finishtime] = 1;
                    $stat->position = ++$i;
                }
            }
        }
    }
    return $stats;
}

/**
 * Apply a formula to calculate a raw grade of users with attempts statistics.
 *
 * @param stdClass $treasurehunt The module instance.
 * @param array $stats Aggregated statistics of the attempts.
 * @param array $students The list of users to check.
 * @see treasurehunt_calculate_line_equation
 * @return array grade struct
 */
function treasurehunt_calculate_grades($treasurehunt, $stats, $students) {
    $grades = array();
    foreach ($students as $student) {
        $feedback = '';
        $grade = new stdClass();
        $grade->userid = $student->id;
        $grade->itemname = 'treasurehuntscore';
        if (isset($stats[$student->id])) {
            $negativepercentage = 1 - ((($stats[$student->id]->nolocationsfailed * $treasurehunt->gradepenlocation)
                     + ($stats[$student->id]->noanswersfailed * $treasurehunt->gradepenanswer) ) / 100);
            $msgparams = (object) [
                        'grademax' => $treasurehunt->grade,
                        'nolocationsfailed' => $stats[$student->id]->nolocationsfailed,
                        'noanswersfailed' => $stats[$student->id]->noanswersfailed,
                        'nosuccessfulstages' => $stats[$student->id]->nosuccessfulstages,
                        'nostages' => $stats[$student->id]->nostages,
                        'penalization' => number_format(1 - $negativepercentage, 1),
                        'treasurehunt' => $treasurehunt
            ];

            if ($treasurehunt->grademethod == TREASUREHUNT_GRADEFROMPOSITION && isset($stats[$student->id]->position)) {
                $positiverate = treasurehunt_calculate_line_equation(
                        1
                        , $treasurehunt->grade
                        , $stats[$student->id]->lastposition
                        , $treasurehunt->grade / 2
                        , $stats[$student->id]->position);
                $msgparams->rawscore = $positiverate;
                $msgparams->lastposition = $stats[$student->id]->lastposition;
                $msgparams->position = $stats[$student->id]->position;
                $feedback = get_string('grade_explaination_fromposition', 'treasurehunt', $msgparams);
            } else if ($treasurehunt->grademethod == TREASUREHUNT_GRADEFROMTIME && isset($stats[$student->id]->usertime)) {
                $positiverate = treasurehunt_calculate_line_equation(
                        $stats[$student->id]->besttime, $treasurehunt->grade, $stats[$student->id]->worsttime, $treasurehunt->grade / 2, $stats[$student->id]->usertime);
                $msgparams->rawscore = $positiverate;
                $msgparams->besttime = treasurehunt_get_nice_duration($stats[$student->id]->besttime);
                $msgparams->worsttime = treasurehunt_get_nice_duration($stats[$student->id]->worsttime);
                $msgparams->yourtime = treasurehunt_get_nice_duration($stats[$student->id]->usertime);
                $feedback = get_string('grade_explaination_fromtime', 'treasurehunt', $msgparams);
            } else if ($treasurehunt->grademethod == TREASUREHUNT_GRADEFROMABSOLUTETIME && isset($stats[$student->id]->usertime)) {
                $positiverate = treasurehunt_calculate_line_equation(
                        $stats[$student->id]->besttime, $treasurehunt->grade, $stats[$student->id]->worsttime, $treasurehunt->grade / 2, $stats[$student->id]->usertime);
                $msgparams->rawscore = number_format($positiverate, 1);
                $msgparams->besttime = userdate($stats[$student->id]->besttime);
                $msgparams->worsttime = userdate($stats[$student->id]->worsttime);
                $msgparams->yourtime = userdate($stats[$student->id]->usertime);
                $feedback = get_string('grade_explaination_fromabsolutetime', 'treasurehunt', $msgparams);
            } else if ($treasurehunt->grademethod == TREASUREHUNT_GRADEFROMSTAGES) {
                $positiverate = ($stats[$student->id]->nosuccessfulstages * $treasurehunt->grade) / ($stats[$student->id]->nostages);
                $msgparams->rawscore = $positiverate;
                $feedback = get_string('grade_explaination_fromstages', 'treasurehunt', $msgparams);
            } else {
                // Default grading when there is no data for calculation.
                $positiverate = ($stats[$student->id]->nosuccessfulstages * $treasurehunt->grade) / (2 * $stats[$student->id]->nostages);
                $msgparams->rawscore = $positiverate;
                $feedback = get_string('grade_explaination_temporary', 'treasurehunt', $msgparams);
            }

            $grade->rawgrade = max($positiverate * $negativepercentage, 0);
            $grades[$student->id] = $grade;
            $grade->feedbackformat = FORMAT_PLAIN;
            $grade->feedback = $feedback;
        } else {
            $grade->rawgrade = null;
            $grades[$student->id] = $grade;
        }
    }
    return $grades;
}

/**
 * Apply a eqution of the line with the 5 variables introduced.
 *
 * @param int $x1
 * @param int $y1
 * @param int $x2
 * @param int $y2
 * @param int $x3
 * @return int y3
 */
function treasurehunt_calculate_line_equation($x1, $y1, $x2, $y2, $x3) {
    if ($x2 == $x1) {
        $m = 0;
    } else {
        $m = ($y2 - $y1) / ($x2 - $x1);
    }
    $y3 = ($m * ($x3 - $x1)) + $y1;
    return $y3;
}

/**
 * @param stdClass $treasurehunt The module instance.
 * @param int/array $userid The list of users to check.
 * @return array grade struct
 */
function treasurehunt_calculate_user_grades($treasurehunt, $userid = 0) {
    $cm = get_coursemodule_from_instance('treasurehunt', $treasurehunt->id, 0, false, MUST_EXIST);
    $context = context_module::instance($cm->id);
    $enrolledusers = get_enrolled_users($context, 'mod/treasurehunt:play', 0, 'u.id');
    if ($userid == 0) {
        $students = $enrolledusers;
    } else {
        $student = new stdClass();
        $student->id = $userid;
        $students = array($student);
    }
    $stats = treasurehunt_calculate_stats($treasurehunt, $enrolledusers);
    $grades = treasurehunt_calculate_grades($treasurehunt, $stats, $students);
    return $grades;
}
