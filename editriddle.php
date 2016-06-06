<?php

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/editriddle_form.php');
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
$roadid = optional_param('roadid',0, PARAM_INT);
$addanswers=optional_param('addanswers', '', PARAM_TEXT);

list ($course, $cm) = get_course_and_cm_from_cmid($cmid, 'treasurehunt');
$treasurehunt = $DB->get_record('treasurehunt', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$url = new moodle_url('/mod/treasurehunt/editriddle.php', array('cmid' => $cmid));
if (!empty($id)) {
    $url->param('id', $id);
}
if (!empty($roadid)) {
    $url->param('roadid', $roadid);
}
$PAGE->set_url($url);

require_capability('mod/treasurehunt:managetreasurehunt', $context);
require_capability('mod/treasurehunt:addriddle', $context);


if (!is_edition_loked($cm->instance, $USER->id)) {
    $lockid = renew_edition_lock($cm->instance, $USER->id);
    $PAGE->requires->js_call_amd('mod_treasurehunt/renewlock', 'renew_edition_lock', array($cm->instance, $lockid));

    if ($id) { // if entry is specified
        $title = get_string('editingriddle', 'treasurehunt');
        $sql = 'SELECT id,name,description,descriptionformat,descriptiontrust,'
                . 'activitytoend,roadid,questiontext,questiontextformat,'
                . 'questiontexttrust FROM {treasurehunt_riddles}  WHERE id=?';
        $params = array($id);
        if (!$entry = $DB->get_record_sql($sql, $params)) {
            print_error('invalidentry');
        }
        // Si existe la pregunta recojo las respuestas.
        if ($entry->questiontext !== '') {
            // Hago que se muestre la pregunta.
            $entry->addsimplequestion = optional_param('addsimplequestion', 1, PARAM_INT);
            $sqlanswers = 'SELECT a.id,a.answertext,a.answertextformat,a.answertexttrust,'
                    . ' a.correct FROM {treasurehunt_answers} a INNER JOIN'
                    . ' {treasurehunt_riddles} r ON r.id=a.riddleid WHERE r.id=?';
            $params = array($entry->id);
            $answers = $DB->get_records_sql($sqlanswers, $params);
            $entry->answers = $answers;
            $entry->noanswers = optional_param('noanswers', count($answers), PARAM_INT);
            if (!empty($addanswers)) {
                $entry->noanswers += NUMBER_NEW_ANSWERS;
            }
        }
    } else { // new entry
        $title = get_string('addingriddle', 'treasurehunt');
        $roadid = required_param('roadid', PARAM_INT);
        $select = "id = ?";
        $params = array($roadid);
        // Compruebo si existe el camino
        if (!$DB->record_exists_select('treasurehunt_roads', $select, $params)) {
            print_error('invalidentry');
        }
        // Compruebo si no esta bloqueado y por tanto no se puede anadir ninguna pista.
        if (check_road_is_blocked($roadid)) {
            print_error('notcreateriddle', 'treasurehunt', $returnurl);
        }
        $entry = new stdClass();
        $entry->id = null;
        $entry->roadid = $roadid;
    }
    if (!isset($entry->questiontext) || $entry->questiontext === '') {
        $entry->addsimplequestion = optional_param('addsimplequestion', 0, PARAM_INT);
        $entry->noanswers = optional_param('noanswers', 2, PARAM_INT);
        if (!empty($addanswers)) {
            $entry->noanswers += NUMBER_NEW_ANSWERS;
        }
    }
    $entry->cmid = $cmid;
    $returnurl = new moodle_url('/mod/treasurehunt/edit.php', array('id' => $cmid,'roadid'=>$entry->roadid));

    $maxbytes = get_user_max_upload_file_size($PAGE->context, $CFG->maxbytes, $COURSE->maxbytes);
    $editoroptions = array('trusttext' => true, 'maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes' => $maxbytes, 'context' => $context,
        'subdirs' => file_area_contains_subdirs($context, 'mod_treasurehunt', 'description', $entry->id));
    // List activities with Completion enabled
    $completioninfo = new completion_info($course);
    $completionactivities = $completioninfo->get_activities();


    $mform = new riddle_form(null, array('current' => $entry, 'context' => $context, 'editoroptions' => $editoroptions, 'completionactivities' => $completionactivities)); //name of the form you defined in file above.

    if ($mform->is_reloaded()) {
        // Si se ha recargado es porque hemos cambiado algo
    } else if ($mform->is_cancelled()) {
        // You need this section if you have a cancel button on your form
        // here you tell php what to do if your user presses cancel
        // probably a redirect is called for!
        // PLEASE NOTE: is_cancelled() should be called before get_data().
        redirect($returnurl);
    } else if ($entry = $mform->get_data()) {

        // Actualizamos los campos
        $entry->name = trim($entry->name);
        $entry->description = '';          // updated later
        $entry->descriptionformat = FORMAT_HTML; // updated later
        $entry->descriptiontrust = 0;           // updated later
        $entry->questiontext = '';          // updated later
        $entry->questiontextformat = FORMAT_HTML; // updated later
        $entry->questiontexttrust = 0;           // updated later

        if (empty($entry->id)) {
            $entry->id = insert_riddle_form($entry);
            $isnewentry = true;
        } else {
            $isnewentry = false;
        }

        // This branch is where you process validated data.
        // Do stuff ...
        // Typically you finish up by redirecting to somewhere where the user
        // can see what they did.
        // save and relink embedded images and save attachments
        $entry = file_postupdate_standard_editor($entry, 'description', $editoroptions, $context, 'mod_treasurehunt', 'description', $entry->id);
        // store the updated value values
        if ($entry->addsimplequestion) {
            // Proceso los ficheros del editor de pregunta.
            $entry = file_postupdate_standard_editor($entry, 'questiontext', $editoroptions, $context, 'mod_treasurehunt', 'questiontext', $entry->id);
            if (isset($entry->answertext_editor)) {
                // Proceso los editores de respuesta y guardo las respuestas.
                foreach ($entry->answertext_editor as $key => $answertext) {
                    $timenow = time();
                    if (isset($answers) && count($answers) > 0) {
                        $answer = array_shift($answers);
                        if (trim($answertext['text']) === '') {
                            $DB->delete_records('treasurehunt_answers', array('id' => $answer->id));
                            continue;
                        }
                        $answer->timemodified = $timenow;
                        $answer->correct = $entry->correct[$key];
                    } else {
                        if (trim($answertext['text']) === '') {
                            continue;
                        }
                        $answer = new stdClass();
                        $answer->answertext = '';          // updated later
                        $answer->answertextformat = FORMAT_HTML; // updated later
                        $answer->answertexttrust = 0;           // updated later
                        $answer->timecreated = $timenow;
                        $answer->riddleid = $entry->id;
                        $answer->correct = $entry->correct[$key];
                        $answer->id = $DB->insert_record('treasurehunt_answers', $answer);
                    }
                    $answer->answertext_editor = $answertext;
                    $answer = file_postupdate_standard_editor($answer, 'answertext', $editoroptions, $context, 'mod_treasurehunt', 'answertext', $answer->id);
                    $DB->update_record('treasurehunt_answers', $answer);
                }
            }
        } else {
            if (isset($answers)) {
                // Elimino las anteriores respuestas.
                foreach ($answers as $answer) {
                    $DB->delete_records('treasurehunt_answers', array('id' => $answer->id));
                }
            }
        }
        // Actualizo la pista con los ficheros procesados.
        update_riddle_form($entry);

        // Trigger event and update completion (if entry was created).
        $eventparams = array(
            'context' => $context,
            'objectid' => $entry->id,
        );
        if ($isnewentry) {
            $event = \mod_treasurehunt\event\riddle_created::create($eventparams);
        } else {
            $event = \mod_treasurehunt\event\riddle_updated::create($eventparams);
        }
        $event->trigger();

        // Actualizo el tiempo de modificacion del camino.
        $road = new stdClass();
        $road->id = $entry->roadid;
        $road->timemodified = time();
        $DB->update_record('treasurehunt_roads', $road);


        redirect($returnurl);
    }
} else {
    $returnurl = new moodle_url('/mod/treasurehunt/view.php', array('id' => $cmid));
    print_error('treasurehuntislocked', 'treasurehunt', $returnurl, get_username_blocking_edition($treasurehunt->id));
}
//$PAGE->navbar->ignore_active();
$PAGE->navbar->add(get_string('edittreasurehunt', 'treasurehunt'), $returnurl);
$PAGE->navbar->add(get_string('editriddle', 'treasurehunt'), $url);
$PAGE->set_title($title);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('standard');
echo $OUTPUT->header();
echo $OUTPUT->heading($title);
$mform->display();
echo $OUTPUT->footer();




