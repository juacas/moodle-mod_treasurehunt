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
 * GPX tracks exporter
 *
 * @package   mod_treasurehunt
 * @copyright Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @author Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

/** @var $DB database_manager Database */
global $DB;
global $USER;
$id = required_param('id', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
list ($course, $cm) = get_course_and_cm_from_cmid($id, 'treasurehunt');
$treasurehunt = $DB->get_record('treasurehunt', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
if ($USER->id != $userid) {
    require_capability('mod/treasurehunt:managetreasurehunt', $context);
}
date_default_timezone_set("UTC");

$segments = [];
$tracks = $DB->get_records('treasurehunt_track', ['userid' => $userid, 'treasurehuntid' => $treasurehunt->id]);
$description = "Track for user:" . fullname($DB->get_record('user', ['id' => $userid]));
$activity = new stdClass();
$activity->trackPoints = [];
foreach ($tracks as $track) {
    $trackpoint = new stdClass();
    $trackpoint->time = $track->timestamp;
    $coords = explode(' ', substr($track->location, 6, -1));
    $trackpoint->lon = $coords[0];
    $trackpoint->lat = $coords[1];
    $trackpoint->type = 'trackpoint';
    $activity->trackPoints[] = $trackpoint;
}
$segment = new stdClass();
$segment->activities = [$activity];
$segment->type = 'tracks';
$segments[] = $segment;
$gpx = makeXml($segments, $description);
header('Content-type: application/gpx');
header('Content-Disposition: attachment; filename="treasure_track.gpx"');
echo $gpx;
die;

function makeXml($segments, $description) {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <gpx version="1.1" creator="TreasureHuntTrackExporter" xsi:schemaLocation="http://www.topografix.com/GPX/1/1 http://www.topografix.com/GPX/1/1/gpx.xsd http://www.garmin.com/xmlschemas/GpxExtensions/v3 http://www.garmin.com/xmlschemas/GpxExtensionsv3.xsd http://www.garmin.com/xmlschemas/TrackPointExtension/v1 http://www.garmin.com/xmlschemas/TrackPointExtensionv1.xsd" xmlns="http://www.topografix.com/GPX/1/1" xmlns:gpxtpx="http://www.garmin.com/xmlschemas/TrackPointExtension/v1" xmlns:gpxx="http://www.garmin.com/xmlschemas/GpxExtensions/v3" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
			<metadata>
	        <link href="https://github.com/juacas/moodle-mod_treasurehunt">
	            <text>Treasurehunt track</text>
	        </link>
	    </metadata>
	    <trk>
        <name>Treasurehunt trace</name>
         <desc>' . $description . '</desc>';

    foreach ($segments as $segment) {
        $xml .= "<trkseg>";
        if ($segment->type == 'place') {
            $xml .= makeTrackPoint($segment);
        } else {
            foreach ($segment->activities as $activity) {
                if ($activity->trackPoints)
                    foreach ($activity->trackPoints as $point)
                        $xml .= makeTrackPoint($point);
            }
        }
        $xml .= "</trkseg>";
    }
    $xml .= '</trk>
	</gpx>';

    return $xml;
}

function getIsoTime($date) {
    $dateObj = new DateTime();
    $dateObj->setTimestamp($date);
    return $dateObj->format('c');
}

function makeTrackPoint(&$data) {
    $return = '';
    if ($data->type == 'place') {
        $startTime = getIsoTime($data->startTime);
        $endTime = getIsoTime($data->endTime);

        $return .= "<trkpt lat=\"" . $data->place->location->lat . "\" lon=\"" . $data->place->location->lon . "\"><time>$startTime</time><location>" . $data->place->name . "</location></trkpt>";
        $return .= "<trkpt lat=\"" . $data->place->location->lat . "\" lon=\"" . $data->place->location->lon . "\"><time>$endTime</time><location>" . $data->place->name . "</location></trkpt>";
    } else {
        $time = getIsoTime($data->time);
        $return .= "<trkpt lat=\"$data->lat\" lon=\"$data->lon\"><time>$time</time>";
        $return .= "</trkpt>";
    }

    return $return;
}
