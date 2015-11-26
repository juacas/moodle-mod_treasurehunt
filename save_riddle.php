<?php

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/riddle_form.php');
require_once("$CFG->dirroot/mod/scavengerhunt/locallib.php");

global $COURSE, $PAGE, $CFG;
// You will process some page parameters at the top here and get the info about
// what instance of your module and what course you're in etc. Make sure you
// include hidden variable in your forms which have their defaults set in set_data
// which pass these variables from page to page.
// Setup $PAGE here.
// Print the page header.
$cmid = required_param('cmid', PARAM_INT); // Course_module ID
$id   = optional_param('id', 0, PARAM_INT);           // EntryID
$newFeature = optional_param('newFeature', 0, PARAM_BOOL); //New Feature


if ($cmid) {
    $cm = get_coursemodule_from_id('scavengerhunt', $cmid, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $scavengerhunt = $DB->get_record('scavengerhunt', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    print_error('invalidcoursemodule');
}
require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$url = new moodle_url('/mod/scavengerhunt/save_riddle.php', array('cmid' => $cmid));
if (!empty($id)) {
    $url->param('id', $id);
}
$PAGE->set_url($url);
$returnurl = 'view.php?id=' . $cm->id;

if ($id) { // if entry is specified
    $sql = 'SELECT id,name,description,descriptionformat,descriptiontrust FROM mdl_scavengerhunt_riddle  WHERE id=?';
    $parms = array('id'=>$id);
    if (!$entry = $DB->get_record_sql($sql, $parms)) {
        print_error('invalidentry');
    }
    $entry->cmid = $cmid;
}
if (!$newFeature && !$id) {
    //Compruebo que existe el json
    $json = required_param('json', PARAM_RAW);
    //Lo convierto a un objeto php
    $features = geojson_to_object($json);
    //Recorro las features
    while ($current = $features->current()) {
        if ($current->getProperty('idRiddle') === -1) {
            $entry = new stdClass();
            $entry->id = null;
            $entry->cmid = $cmid;
            $entry->geom = geojson_to_wkt($geojson->dump($current));
            $entry->num_riddle = $current->getProperty('numRiddle');
            $entry->road_id = $current->getProperty('idRoad'); 
            $entry->newFeature = 1;
            $newFeature = 1;
        } else {
            updateFeatureBD($current);
        }
        $features->next();
    }
} else {
    //Debería hacer algo pero no he plateado bien el flujo? 
}

if ($newFeature || $id) {

    if (!isset($entry)) {
        $entry = new stdClass();
        $entry->id = null;
    }
    $maxbytes = get_user_max_upload_file_size($PAGE->context, $CFG->maxbytes, $COURSE->maxbytes);
    $descriptionoptions = array('trusttext' => true, 'maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes' => $maxbytes, 'context' => $context,
        'subdirs' => false);
    $entry = file_prepare_standard_editor($entry, 'description', $descriptionoptions, $context, 'mod_scavengerhunt', 'entry', $entry->id);


    $mform = new riddle_form(null, array('current' => $entry, 'descriptionoptions' => $descriptionoptions)); //name of the form you defined in file above.

    if ($mform->is_cancelled()) {
// You need this section if you have a cancel button on your form
// here you tell php what to do if your user presses cancel
// probably a redirect is called for!
// PLEASE NOTE: is_cancelled() should be called before get_data().
        redirect($returnurl);
    } else if ($entry = $mform->get_data()) {


        //Actualizamos los campos
        $entry->name = trim($entry->name);
        $entry->description = '';          // updated later
        $entry->descriptionformat = FORMAT_HTML; // updated later
        $entry->descriptiontrust = 0;           // updated later
        $entry->question_id = null;
        if (empty($entry->id)) {
             $entry->id = insertFeatureBD($entry);
        }
        // This branch is where you process validated data.
        // Do stuff ...
        // Typically you finish up by redirecting to somewhere where the user
        // can see what they did.
        // save and relink embedded images and save attachments
        $entry = file_postupdate_standard_editor($entry, 'description', $descriptionoptions, $context, 'mod_scavengerhunt', 'entry', $entry->id);
        // store the updated value values
        updateEntryBD($entry);
        redirect($returnurl);
    }
    $PAGE->set_title('Caza del tesoro');
    $PAGE->set_heading('Prueba');
    $PAGE->set_pagelayout('standard');
    echo $OUTPUT->header();
    echo $OUTPUT->heading("A heading here");
    $mform->display();
    echo $OUTPUT->footer();
} else {
    //Se acabó
}



