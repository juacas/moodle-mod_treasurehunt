<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

/** @var $DB database_manager Database */
global $DB;
$id = required_param('id', PARAM_INT);
$userid= required_param('userid', PARAM_INT);
list ($course, $cm) = get_course_and_cm_from_cmid($id, 'treasurehunt');
$treasurehunt = $DB->get_record('treasurehunt', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

require_capability('mod/treasurehunt:managetreasurehunt', $context);
date_default_timezone_set("UTC");

$segments = [];
$tracks = $DB->get_records('treasurehunt_track',['userid'=>$userid,'treasurehuntid'=>$treasurehunt->id]);

$activity=new stdClass();
$activity->trackPoints=[];
foreach ($tracks as $track) {
    $trackpoint=new stdClass();
    $trackpoint->time = $track->timestamp;
    $coords = explode(' ',substr($track->location, 6,-1));
    $trackpoint->lon = $coords[0];
    $trackpoint->lat = $coords[1];
    $trackpoint->type = 'trackpoint';
    $activity->trackPoints[]=$trackpoint;
}
$segment = new stdClass();
$segment->activities = [$activity];
$segment->type = 'tracks';
$segments[]=$segment;
$gpx = makeXml($segments);
header('Content-type: application/gpx');
header('Content-Disposition: attachment; filename="treasure_track.gpx"');
echo $gpx;
die;
function makeXml($segments)
{
	$xml = '<?xml version="1.0" encoding="UTF-8"?>
            <gpx version="1.1" creator="TreasureHuntTrackExporter" xsi:schemaLocation="http://www.topografix.com/GPX/1/1 http://www.topografix.com/GPX/1/1/gpx.xsd http://www.garmin.com/xmlschemas/GpxExtensions/v3 http://www.garmin.com/xmlschemas/GpxExtensionsv3.xsd http://www.garmin.com/xmlschemas/TrackPointExtension/v1 http://www.garmin.com/xmlschemas/TrackPointExtensionv1.xsd" xmlns="http://www.topografix.com/GPX/1/1" xmlns:gpxtpx="http://www.garmin.com/xmlschemas/TrackPointExtension/v1" xmlns:gpxx="http://www.garmin.com/xmlschemas/GpxExtensions/v3" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
			<metadata>
	        <link href="https://github.com/juacas/moodle-mod_treasurehunt">
	            <text>Treasurehunt track</text>
	        </link>
	    </metadata>
	    <trk>
        <name>Treasurehunt trace</name>';

	foreach ($segments as $segment)
	{
		$xml .=  "<trkseg>";
		if ($segment->type == 'place')
		{
			$xml .= makeTrackPoint($segment);
		}
		else
		{
			foreach ($segment->activities as $activity)
			{
				if ($activity->trackPoints)
					foreach ($activity->trackPoints as $point)
						$xml .= makeTrackPoint($point);
			}
		}
		$xml .=  "</trkseg>";
	}
	$xml .= '</trk>
	</gpx>';

	return $xml;
}

function getIsoTime($date)
{
	$dateObj = new DateTime();
        $dateObj->setTimestamp($date);
	return $dateObj->format('c');
}

function makeTrackPoint(&$data)
{
	$return = '';
	if ($data->type == 'place')
	{
		$startTime = getIsoTime($data->startTime);
		$endTime = getIsoTime($data->endTime);

		$return .= "<trkpt lat=\"" . $data->place->location->lat . "\" lon=\"" . $data->place->location->lon . "\"><time>$startTime</time><location>" . $data->place->name . "</location></trkpt>";
		$return .= "<trkpt lat=\"" . $data->place->location->lat . "\" lon=\"" . $data->place->location->lon . "\"><time>$endTime</time><location>" . $data->place->name . "</location></trkpt>";
	}
	else
	{
		$time = getIsoTime($data->time);
		$return .= "<trkpt lat=\"$data->lat\" lon=\"$data->lon\"><time>$time</time></trkpt>";
	}

	return $return;
}
