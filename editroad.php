<?php

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/editroad_form.php');
require_once("$CFG->dirroot/mod/treasurehunt/locallib.php");

global $COURSE, $PAGE, $CFG, $USER;
// You will process some page parameters at the top here and get the info about
// what instance of your module and what course you're in etc. Make sure you
// include hidden variable in your forms which have their defaults set in set_data
// which pass these variables from page to page.
// Setup $PAGE here.
// Print the page header.
$cmid = required_param('cmid', PARAM_INT); // Course_module ID
$id = optional_param('id', 0, PARAM_INT);           // EntryID


list ($course, $cm) = get_course_and_cm_from_cmid($cmid, 'treasurehunt');
$treasurehunt = $DB->get_record('treasurehunt', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$url = new moodle_url('/mod/treasurehunt/editroad.php', array('cmid' => $cmid));
if (!empty($id)) {
    $url->param('id', $id);
}
$PAGE->set_url($url);

require_capability('mod/treasurehunt:managetreasurehunt', $context);
require_capability('mod/treasurehunt:addroad', $context);

$returnurl = new moodle_url('/mod/treasurehunt/edit.php', array('id' => $cmid));

if (!is_edition_loked($cm->instance, $USER->id)) {
    $lockid = renew_edition_lock($cm->instance, $USER->id);
    $PAGE->requires->js_call_amd('mod_treasurehunt/renewlock', 'renew_edition_lock', array($cm->instance, $lockid));

    if ($id) { // if entry is specified
        $title = get_string('editingroad', 'treasurehunt');
        $sql = 'SELECT id,name,groupid,groupingid FROM mdl_treasurehunt_roads  WHERE id=?';
        $parms = array('id' => $id);
        if (!$entry = $DB->get_record_sql($sql, $parms)) {
            print_error('invalidentry');
        }
    } else { // new entry
        $title = get_string('addingroad', 'treasurehunt');
        $entry = new stdClass();
        $entry->id = null;
    }
    $entry->cmid = $cmid;



    //Compruebo el tipo de grupo
    if ($cm->groupmode) {
        $selectoptions = groups_get_all_groupings($course->id);
        //Consulta de groupings ocupados en esta instancia
        $sql = 'SELECT groupingid as busy FROM mdl_treasurehunt_roads  WHERE treasurehuntid=? AND id !=?';
        $groups = true;
    } else {
        $selectoptions = groups_get_all_groups($course->id);
        //Consulta de grupos ocupados en esta instancia
        $sql = 'SELECT groupid as busy FROM mdl_treasurehunt_roads  WHERE treasurehuntid=? AND id !=?';
        $groups = false;
    }
    //Elimino los grupos ocupados
    $parms = array('treasurehuntid' => $treasurehunt->id, 'id' => $id);
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
            $entry->treasurehuntid = $treasurehunt->id;
            $entry->timecreated = $timenow;
            $entry->id = $DB->insert_record('treasurehunt_roads', $entry);
            $event = \mod_treasurehunt\event\road_created::create($eventparams);
        } else {
            $entry->timemodified = $timenow;
            $DB->update_record('treasurehunt_roads', $entry);
            $event = \mod_treasurehunt\event\road_updated::create($eventparams);
        }
        // store the updated value values
        // Trigger event and update completion (if entry was created).

        $event->trigger();
        redirect($returnurl);
    }
} else {
    $returnurl = new moodle_url('/mod/treasurehunt/view.php', array('id' => $cmid));
    print_error('treasurehuntislocked', 'treasurehunt', $returnurl, get_username_blocking_edition($treasurehunt->id));
}
$PAGE->navbar->add(get_string('edittreasurehunt', 'treasurehunt'), $returnurl);
$PAGE->navbar->add(get_string('editroad', 'treasurehunt'), $url);
$PAGE->set_title($title);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('standard');
echo $OUTPUT->header();
echo $OUTPUT->heading($title);
$mform->display();
echo $OUTPUT->footer();




