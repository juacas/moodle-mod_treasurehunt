<?php

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/editroad_form.php');
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

$url = new moodle_url('/mod/scavengerhunt/editroad.php', array('cmid' => $cmid));
if (!empty($id)) {
    $url->param('id', $id);
}
$PAGE->set_url($url);

require_capability('mod/scavengerhunt:managescavenger', $context);
require_capability('mod/scavengerhunt:addroad', $context);

$returnurl = new moodle_url('/mod/scavengerhunt/edit.php', array('id' => $cmid));

if (!$lock = isLockScavengerhunt($cm->instance,$USER->id)) {
    $idLock = renewLockScavengerhunt($cm->instance,$USER->id);
    $PAGE->requires->js_call_amd('mod_scavengerhunt/renewlock', 'renewLockScavengerhunt', array($cm->instance, $idLock));

    if ($id) { // if entry is specified
        $sql = 'SELECT id,name,group_id,grouping_id FROM mdl_scavengerhunt_roads  WHERE id=?';
        $parms = array('id' => $id);
        if (!$entry = $DB->get_record_sql($sql, $parms)) {
            print_error('invalidentry');
        }
    } else { // new entry
        $entry = new stdClass();
        $entry->id = null;
    }
    $entry->cmid = $cmid;



    //Compruebo el tipo de grupo
    if ($cm->groupmode) {
        $selectoptions = groups_get_all_groupings($course->id);
        //Consulta de groupings ocupados en esta instancia
        $sql = 'SELECT grouping_id as busy FROM mdl_scavengerhunt_roads  WHERE scavengerhunt_id=? AND id !=?';
        $groups = true;
    } else {
        $selectoptions = groups_get_all_groups($course->id);
        //Consulta de grupos ocupados en esta instancia
        $sql = 'SELECT group_id as busy FROM mdl_scavengerhunt_roads  WHERE scavengerhunt_id=? AND id !=?';
        $groups = false;
    }
    //Elimino los grupos ocupados
    $parms = array('scavengerhunt_id' => $scavengerhunt->id, 'id' => $id);
    $busy = $DB->get_records_sql($sql, $parms);
    foreach ($busy as $option) {
        unset($selectoptions[$option->busy]);
    }

    $mform = new road_form(null, array('current' => $entry, 'selectoptions' => $selectoptions, 'groups' => $groups)); //name of the form you defined in file above.

    if ($mform->is_cancelled()) {
// You need this section if you have a cancel button on your form
// here you tell php what to do if your user presses cancel
// probably a redirect is called for!
// PLEASE NOTE: is_cancelled() should be called before get_data().
        redirect($returnurl);
    } else if ($entry = $mform->get_data()) {
        //Actualizamos los campos
        $timenow = time();
        $entry->name = trim($entry->name);

        $eventparams = array(
            'context' => $context,
            'objectid' => $entry->id,
        );
        if (empty($entry->id)) {
            $entry->scavengerhunt_id = $scavengerhunt->id;
            $entry->timecreated = $timenow;
            $entry->id = $DB->insert_record('scavengerhunt_roads', $entry);
            $event = \mod_scavengerhunt\event\road_created::create($eventparams);
        } else {
            $entry->timemodified = $timenow;
            $DB->update_record('scavengerhunt_roads', $entry);
            $event = \mod_scavengerhunt\event\road_updated::create($eventparams);
        }
        // store the updated value values
        // Trigger event and update completion (if entry was created).

        $event->trigger();
        redirect($returnurl);
    }
}
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('standard');
echo $OUTPUT->header();
if ($lock) {
$returnurl = new moodle_url('/mod/scavengerhunt/view.php', array('id' => $cmid));
    print_error('scavengerhuntislocked', 'scavengerhunt', $returnurl,get_username_blocking_edition($scavengerhunt->id));
} else {
    echo $OUTPUT->heading(format_string($scavengerhunt->name));
    $mform->display();
}
echo $OUTPUT->footer();




