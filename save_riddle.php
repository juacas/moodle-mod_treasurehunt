<?php

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once('riddle_form.php');
global $COURSE, $PAGE, $CFG;
// You will process some page parameters at the top here and get the info about
// what instance of your module and what course you're in etc. Make sure you
// include hidden variable in your forms which have their defaults set in set_data
// which pass these variables from page to page.
 
// Setup $PAGE here.
 // Print the page header.
$id = required_param('id', PARAM_INT); // Course_module ID



if ($id) {
    $cm         = get_coursemodule_from_id('scavengerhunt', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $scavengerhunt  = $DB->get_record('scavengerhunt', array('id' => $cm->instance), '*', MUST_EXIST);
}  else {
    print_error('You must specify a course_module ID');
}
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
$actualurl=new moodle_url('/mod/scavengerhunt/save_riddle.php', array('id' => $id));
$PAGE->set_url($actualurl);
$PAGE->set_title('Caza del tesoro');
$PAGE->set_heading('Prueba');
$PAGE->set_pagelayout('standard');
$returnurl = 'view.php?id='.$cm->id;
if(isset($_POST['json']))
{
   @$json = $_POST['json'];
   $json= json_decode(($json),true);
}
//
// Default 'action' for form is strip_querystring(qualified_me()).
 
// Set the initial values, for example the existing data loaded from the database.
// (an array of name/value pairs that match the names of data elements in the form.
// You can also use an object)
//$mform->set_data($toform);
//Copiado de /mod/glossary/edit.php 


/*if ($idRiddle != -1) { // if entry is specified
    if (isguestuser()) {
        //print_error('guestnoedit', 'glossary', "$CFG->wwwroot/mod/glossary/view.php?id=$cmid");
    }

    if (!$entry = $DB->get_record('scavengerhunt_riddle', array('id'=>$idRiddle))) {
        print_error('invalidentry');
    }
} else { // new entry
    //require_capability('mod/glossary:write', $context);
    // note: guest user does not have any write capability
    $entry = new stdClass();
    $entry->id = null;
}*/
$maxbytes = get_user_max_upload_file_size($PAGE->context, $CFG->maxbytes, $COURSE->maxbytes);
$descriptionoptions = array('trusttext'=>true, 'maxfiles'=>EDITOR_UNLIMITED_FILES, 'maxbytes'=>$maxbytes, 'context'=>$context,
    'subdirs'=>false);
$entry = file_prepare_standard_editor($entry, 'description', $descriptionoptions, $context, 'mod_scavengerhunt', 'entry', $entry->id);


$mform = new riddle_form($actualurl,array('current'=>$entry,'descriptionoptions'=>$descriptionoptions));//name of the form you defined in file above.

if ($mform->is_cancelled()) {
    // You need this section if you have a cancel button on your form
    // here you tell php what to do if your user presses cancel
    // probably a redirect is called for!
    // PLEASE NOTE: is_cancelled() should be called before get_data().
    redirect($returnurl);
 
} else if ($fromform = $mform->get_data()) {
    $timenow = time();
    // This branch is where you process validated data.
    // Do stuff ...
 
    // Typically you finish up by redirecting to somewhere where the user
    // can see what they did.
    // save and relink embedded images and save attachments
    $entry = file_postupdate_standard_editor($entry, 'description', $descriptionoptions, $context, 'mod_scavengerhunt', 'entry', $entry->id);
    redirect($returnurl);
}
 
echo $OUTPUT->header();
echo $OUTPUT->heading("A heading here");
$mform->display();
echo $OUTPUT->footer();

