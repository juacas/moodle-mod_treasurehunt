<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
require_once("../../config.php");
require_once("locallib.php");

$confirm = optional_param('confirm', false, PARAM_BOOL);

$PAGE->set_url('/mod/trasurehunt/clearhunt.php');

// Do not autologin guest..
require_login(null, false);
$context=context_user::instance($USER->id);
$PAGE->set_context($context);
list ($course, $cm) = get_course_and_cm_from_cmid(required_param('id', PARAM_INTEGER), 'treasurehunt');
$treasurehuntid = $cm->instance;
$return = new moodle_url('/mod/treasurehunt/view.php',array('id'=>$cm->id));

if (!has_capability('mod/treasurehunt:managetreasurehunt', $context)) {
    redirect($return);
}

$clearhunt = get_string('cleartreasurehunt', 'treasurehunt');
$PAGE->navbar->add(get_string('modulename', 'treasurehunt'));
$PAGE->navbar->add($clearhunt);
$PAGE->set_title($clearhunt);
$PAGE->set_heading($COURSE->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading($clearhunt);

if (data_submitted() and $confirm and confirm_sesskey()) {
    treasurehunt_clear_activities($treasurehuntid);
    
    echo $OUTPUT->box(get_string('cleartreasurehunt_done', 'treasurehunt'));
    echo $OUTPUT->continue_button($return);
    echo $OUTPUT->footer();
    die;

} else {
    
    $attempts = treasurehunt_get_all_attempts($treasurehuntid);
    $count = count($attempts);
    $msg = get_string('cleartreasurehuntconfirm', 'treasurehunt', $count);
    echo $OUTPUT->confirm($msg, new moodle_url('clearhunt.php', array('confirm'=>1,'id'=>$cm->id)), $return);
    echo $OUTPUT->footer();
    die;
}

