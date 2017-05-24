<?php
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
$userid = required_param('userid', PARAM_SEQUENCE);
$userids = explode(',', $userid);

list ($course, $cm) = get_course_and_cm_from_cmid($id, 'treasurehunt');
$treasurehunt = $DB->get_record('treasurehunt', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
if (count($userids) > 1 || $USER->id != $userid) {
    require_capability('mod/treasurehunt:managetreasurehunt', $context);
}
date_default_timezone_set("UTC");
foreach ($userids as $userid) {
    $trackpoints = $DB->get_records('treasurehunt_track', ['userid' => $userid, 'treasurehuntid' => $treasurehunt->id]);
    $description = "Track for user:" . fullname($DB->get_record('user', ['id' => $userid]));
    $segment = makesegment($trackpoints);
    $segments[] = $segment;
    $tracks[] = maketrack($description, $segments);
}
$gpx = makegpx($tracks);
header('Content-type: application/gpx');
header('Content-Disposition: attachment; filename="treasure_track.gpx"');
echo $gpx;
die;

/**
 * 
 * @param array $trackpoint  from table treasurehunt_track
 * @return \stdClass $segment
 */
function makesegment($trackpoints) {
    $activity = new stdClass();
    $activity->trackPoints = [];

    foreach ($trackpoints as $trackpoint) {
        $gpxtrackpoint = new stdClass();
        $gpxtrackpoint->time = $trackpoint->timestamp;
        $coords = explode(' ', substr($trackpoint->location, 6, -1));
        $gpxtrackpoint->lon = $coords[0];
        $gpxtrackpoint->lat = $coords[1];
        $gpxtrackpoint->type = 'trackpoint';
        $activity->trackPoints[] = $gpxtrackpoint;
    }
    $segment = new stdClass();
    $segment->activities = [$activity];
    $segment->type = 'tracks';
    return $segment;
}

/**
 * 
 * @param array(string) $tracks tracks xml sections
 * @return string xml format for gpx
 */
function makegpx($tracks) {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="TreasureHuntTrackExporter" xsi:schemaLocation="http://www.topografix.com/GPX/1/1 http://www.topografix.com/GPX/1/1/gpx.xsd http://www.garmin.com/xmlschemas/GpxExtensions/v3 http://www.garmin.com/xmlschemas/GpxExtensionsv3.xsd http://www.garmin.com/xmlschemas/TrackPointExtension/v1 http://www.garmin.com/xmlschemas/TrackPointExtensionv1.xsd" xmlns="http://www.topografix.com/GPX/1/1" xmlns:gpxtpx="http://www.garmin.com/xmlschemas/TrackPointExtension/v1" xmlns:gpxx="http://www.garmin.com/xmlschemas/GpxExtensions/v3" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
	<metadata>
	    <link href="https://github.com/juacas/moodle-mod_treasurehunt">
	        <text>Treasurehunt track</text>
	    </link>
	</metadata>';
    $xml .= "\n";
    $xml .= join("\n", $tracks);
    $xml .= "\n";
    $xml .= '</gpx>';
    return $xml;
}

/**
 * 
 * @param string $description
 * @param array(\stdClass) array of $segments
 * @return string xmlsection for a track. From <trk> to </trk>
 */
function maketrack($description, $segments) {
    $xml = '<trk>
        <name>Treasurehunt trace</name>
         <desc>' . $description . '</desc>';

    foreach ($segments as $segment) {
        $xml .= "\n";
        $xml .= "<trkseg>";
        if ($segment->type == 'place') {
            $xml .= maketrackpoint($segment);
        } else {
            foreach ($segment->activities as $activity) {
                if ($activity->trackPoints) {
                    foreach ($activity->trackPoints as $point) {
                        $xml .= "\n";
                        $xml .= maketrackpoint($point);
                    }
                }
            }
        }
        $xml .= "\n";
        $xml .= "</trkseg>";
    }
    $xml .= '</trk>';
    return $xml;
}

function getisotime($date) {
    $dateobj = new DateTime();
    $dateobj->setTimestamp($date);
    return $dateobj->format('c');
}

function maketrackpoint(&$data) {
    $return = '';
    if ($data->type == 'place') {
        $starttime = getisotime($data->startTime);
        $endtime = getisotime($data->endTime);

        $return .= "<trkpt lat=\"" . $data->place->location->lat . "\" lon=\"" . $data->place->location->lon . "\"><time>$starttime</time><location>" . $data->place->name . "</location></trkpt>";
        $return .= "<trkpt lat=\"" . $data->place->location->lat . "\" lon=\"" . $data->place->location->lon . "\"><time>$endtime</time><location>" . $data->place->name . "</location></trkpt>";
    } else {
        $time = getisotime($data->time);
        $return .= "<trkpt lat=\"$data->lat\" lon=\"$data->lon\"><time>$time</time>";
        $return .= "</trkpt>";
    }

    return $return;
}
