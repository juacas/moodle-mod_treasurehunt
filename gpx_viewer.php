<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once("$CFG->dirroot/mod/treasurehunt/locallib.php");
/** @var $DB database_manager Database */
global $DB;
global $USER;
$id = required_param('id', PARAM_INT);
list ($course, $cm) = get_course_and_cm_from_cmid($id, 'treasurehunt');
$treasurehunt = $DB->get_record('treasurehunt', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

require_capability('mod/treasurehunt:managetreasurehunt', $context);
// Print the page header.
$url = new moodle_url('/mod/treasurehunt/gpx_viewer.php', array('id' => $cm->id));

$output = $PAGE->get_renderer('mod_treasurehunt');

$PAGE->set_url($url);
$PAGE->set_title($course->shortname . ': ' . format_string($treasurehunt->name). ' : '.get_string('trackviewer','treasurehunt'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('standard');
    $PAGE->requires->jquery();
    $PAGE->requires->jquery_plugin('ui');
    $PAGE->requires->jquery_plugin('ui-css');
    $PAGE->requires->css('/mod/treasurehunt/css/introjs.css');
    $PAGE->requires->css('/mod/treasurehunt/css/ol.css');
    $PAGE->requires->css('/mod/treasurehunt/css/ol3-layerswitcher.css');
    $PAGE->requires->css('/mod/treasurehunt/css/treasure.css');
$usersids = treasurehunt_get_users_with_tracks($treasurehunt->id);
$users = array();
$userrecords = $DB->get_records_list('user','id',$usersids);
foreach ($userrecords as $userrecord) {
    $user = new stdClass();
    $user->id = $userrecord->id;
    $user->fullname = fullname($userrecord);
    $user->pic = $output->user_picture($userrecord);
    $users[]=$user;
}
$PAGE->requires->js_call_amd('mod_treasurehunt/viewgpx', 'creategpxviewer',  array($id, $treasurehunt->id, treasurehunt_get_strings_trackviewer(), $users));
echo $output->header();
echo $output->heading(format_string($treasurehunt->name));
echo $OUTPUT->container_start("treasurehunt-gpx","treasurehunt-gpx");
echo $OUTPUT->box($OUTPUT->help_icon('edition', 'treasurehunt', ''), 'invisible', 'controlpanel');
echo $OUTPUT->box('', null, 'mapgpx');
echo $OUTPUT->container_end();
echo $OUTPUT->box('', null, 'info');


echo $output->footer();