<?php

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/editriddle_form.php');
require_once("$CFG->dirroot/mod/scavengerhunt/locallib.php");

global $COURSE, $PAGE, $CFG, $USER;
// You will process some page parameters at the top here and get the info about
// what instance of your module and what course you're in etc. Make sure you
// include hidden variable in your forms which have their defaults set in set_data
// which pass these variables from page to page.
// Setup $PAGE here.
// Print the page header.
$cmid = required_param('cmid', PARAM_INT); // Course_module ID
$id = optional_param('id', 0, PARAM_INT);           // EntryID


list ($course, $cm) = get_course_and_cm_from_cmid($cmid, 'scavengerhunt');
$scavengerhunt = $DB->get_record('scavengerhunt', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$url = new moodle_url('/mod/scavengerhunt/editriddle.php', array('cmid' => $cmid));
if (!empty($id)) {
    $url->param('id', $id);
}
$PAGE->set_url($url);

require_capability('mod/scavengerhunt:managescavenger', $context);
require_capability('mod/scavengerhunt:addriddle', $context);

$returnurl = new moodle_url('/mod/scavengerhunt/edit.php', array('id' => $cmid));

if (!$lock = isLockScavengerhunt($cm->instance, $USER->id)) {
    $idLock = renewLockScavengerhunt($cm->instance, $USER->id);
    $PAGE->requires->js_call_amd('mod_scavengerhunt/renewlock', 'renewLockScavengerhunt', array($cm->instance, $idLock));

    if ($id) { // if entry is specified
        $sql = 'SELECT id,name,description,descriptionformat,descriptiontrust,activitytoend FROM mdl_scavengerhunt_riddles  WHERE id=?';
        $parms = array('id' => $id);
        if (!$entry = $DB->get_record_sql($sql, $parms)) {
            print_error('invalidentry');
        }
    } else { // new entry
        $entry = new stdClass();
        $entry->id = null;
        $entry->road_id = required_param('road_id', PARAM_INT);
    }
    $entry->cmid = $cmid;
    $maxbytes = get_user_max_upload_file_size($PAGE->context, $CFG->maxbytes, $COURSE->maxbytes);
    $descriptionoptions = array('trusttext' => true, 'maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes' => $maxbytes, 'context' => $context,
        'subdirs' => file_area_contains_subdirs($context, 'mod_scavengerhunt', 'description', $entry->id));
    $entry = file_prepare_standard_editor($entry, 'description', $descriptionoptions, $context, 'mod_scavengerhunt', 'description', $entry->id);

    // List activities with Completion enabled
    $completioninfo = new completion_info($course);
    $completionactivities = $completioninfo->get_activities();
    

    $mform = new riddle_form(null, array('current' => $entry, 'descriptionoptions' => $descriptionoptions, 'completionactivities'=>$completionactivities)); //name of the form you defined in file above.

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
        if (empty($entry->id)) {
            $entry->id = insertEntryBD($entry);
            $isnewentry = true;
        } else {
            $isnewentry = false;
        }
        // This branch is where you process validated data.
        // Do stuff ...
        // Typically you finish up by redirecting to somewhere where the user
        // can see what they did.
        // save and relink embedded images and save attachments
        $entry = file_postupdate_standard_editor($entry, 'description', $descriptionoptions, $context, 'mod_scavengerhunt', 'description', $entry->id);

        // store the updated value values
        updateEntryBD($entry);
        // Trigger event and update completion (if entry was created).
        $eventparams = array(
            'context' => $context,
            'objectid' => $entry->id,
        );
        if ($isnewentry) {
            $event = \mod_scavengerhunt\event\riddle_created::create($eventparams);
        } else {
            $event = \mod_scavengerhunt\event\riddle_updated::create($eventparams);
        }
        $event->trigger();
        redirect($returnurl);
    }
}
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('standard');
echo $OUTPUT->header();
if ($lock) {
    $returnurl = new moodle_url('/mod/scavengerhunt/view.php', array('id' => $cmid));
    print_error('scavengerhuntislocked', 'scavengerhunt', $returnurl);
} else {
    echo $OUTPUT->heading(format_string($scavengerhunt->name));
    $mform->display();
}
echo $OUTPUT->footer();




