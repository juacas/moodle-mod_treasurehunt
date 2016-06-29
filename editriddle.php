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
$roadid = optional_param('roadid', 0, PARAM_INT);
$addanswers = optional_param('addanswers', '', PARAM_TEXT);

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


if (!is_edition_loked($cm->instance, $USER->id)) {
    $lockid = renew_edition_lock($cm->instance, $USER->id);
    $renewlocktime = (get_setting_lock_time() - 5) * 1000;
    $PAGE->requires->js_call_amd('mod_treasurehunt/renewlock', 'renew_edition_lock',
            array($cm->instance, $lockid, $renewlocktime));

    if ($id) { // if entry is specified
        require_capability('mod/treasurehunt:editriddle', $context);
        $title = get_string('editingriddle', 'treasurehunt');
        $sql = 'SELECT id,name,description,descriptionformat,descriptiontrust,'
                . 'activitytoend,roadid,questiontext,questiontextformat,'
                . 'questiontexttrust FROM {treasurehunt_riddles}  WHERE id=?';
        $params = array($id);
        if (!$riddle = $DB->get_record_sql($sql, $params)) {
            print_error('invalidentry');
        }
        // Si existe la pregunta recojo las respuestas.
        if ($riddle->questiontext !== '') {
            // Hago que se muestre la pregunta.
            $riddle->addsimplequestion = optional_param('addsimplequestion', 1, PARAM_INT);
            $sqlanswers = 'SELECT a.id,a.answertext,a.answertextformat,a.answertexttrust,'
                    . ' a.correct FROM {treasurehunt_answers} a INNER JOIN'
                    . ' {treasurehunt_riddles} r ON r.id=a.riddleid WHERE r.id=?';
            $params = array($riddle->id);
            $answers = $DB->get_records_sql($sqlanswers, $params);
            $riddle->answers = $answers;
            $riddle->noanswers = optional_param('noanswers', count($answers), PARAM_INT);
            if (!empty($addanswers)) {
                $riddle->noanswers += NUMBER_NEW_ANSWERS;
            }
        }
    } else { // new entry
        require_capability('mod/treasurehunt:addriddle', $context);
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
        $riddle = new stdClass();
        $riddle->id = null;
        $riddle->roadid = $roadid;
    }
    if (!isset($riddle->questiontext) || $riddle->questiontext === '') {
        $riddle->addsimplequestion = optional_param('addsimplequestion', 0, PARAM_INT);
        $riddle->noanswers = optional_param('noanswers', 2, PARAM_INT);
        if (!empty($addanswers)) {
            $riddle->noanswers += NUMBER_NEW_ANSWERS;
        }
    }
    $riddle->cmid = $cmid;
    $returnurl = new moodle_url('/mod/treasurehunt/edit.php', array('id' => $cmid, 'roadid' => $riddle->roadid));

    $maxbytes = get_user_max_upload_file_size($PAGE->context, $CFG->maxbytes, $COURSE->maxbytes);
    $editoroptions = array('trusttext' => true, 'maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes' => $maxbytes, 'context' => $context,
        'subdirs' => file_area_contains_subdirs($context, 'mod_treasurehunt', 'description', $riddle->id));
    // List activities with Completion enabled
    $completioninfo = new completion_info($course);
    $completionactivities = $completioninfo->get_activities();


    $mform = new riddle_form(null,
            array('current' => $riddle, 'context' => $context, 'editoroptions' => $editoroptions, 'completionactivities' => $completionactivities)); //name of the form you defined in file above.

    if ($mform->is_reloaded()) {
        // Si se ha recargado es porque hemos cambiado algo
    } else if ($mform->is_cancelled()) {
        // You need this section if you have a cancel button on your form
        // here you tell php what to do if your user presses cancel
        // probably a redirect is called for!
        // PLEASE NOTE: is_cancelled() should be called before get_data().
        redirect($returnurl);
    } else if ($riddle = $mform->get_data()) {

        // Actualizamos los campos
        $timenow = time();
        $riddle->name = trim($riddle->name);
        $riddle->description = '';          // updated later
        $riddle->descriptionformat = FORMAT_HTML; // updated later
        $riddle->descriptiontrust = 0;           // updated later
        $riddle->questiontext = '';          // updated later
        $riddle->questiontextformat = FORMAT_HTML; // updated later
        $riddle->questiontexttrust = 0;           // updated later

        if (empty($riddle->id)) {
            $riddle->timecreated = $timenow;
            $riddle->id = insert_riddle_form($riddle);
            $isnewentry = true;
        } else {
            $riddle->timemodified = $timenow;
            $isnewentry = false;
        }

        // This branch is where you process validated data.
        // Do stuff ...
        // Typically you finish up by redirecting to somewhere where the user
        // can see what they did.
        // save and relink embedded images and save attachments
        $riddle = file_postupdate_standard_editor($riddle, 'description', $editoroptions, $context, 'mod_treasurehunt',
                'description', $riddle->id);
        // store the updated value values
        if ($riddle->addsimplequestion) {
            // Proceso los ficheros del editor de pregunta.
            $riddle = file_postupdate_standard_editor($riddle, 'questiontext', $editoroptions, $context,
                    'mod_treasurehunt', 'questiontext', $riddle->id);
            if (isset($riddle->answertext_editor)) {
                // Proceso los editores de respuesta y guardo las respuestas.
                foreach ($riddle->answertext_editor as $key => $answertext) {
                    if (isset($answers) && count($answers) > 0) {
                        $answer = array_shift($answers);
                        if (trim($answertext['text']) === '') {
                            $DB->delete_records('treasurehunt_answers', array('id' => $answer->id));
                            continue;
                        }
                        $answer->timemodified = $timenow;
                        $answer->correct = $riddle->correct[$key];
                    } else {
                        if (trim($answertext['text']) === '') {
                            continue;
                        }
                        $answer = new stdClass();
                        $answer->answertext = '';          // updated later
                        $answer->answertextformat = FORMAT_HTML; // updated later
                        $answer->answertexttrust = 0;           // updated later
                        $answer->timecreated = $timenow;
                        $answer->riddleid = $riddle->id;
                        $answer->correct = $riddle->correct[$key];
                        $answer->id = $DB->insert_record('treasurehunt_answers', $answer);
                    }
                    $answer->answertext_editor = $answertext;
                    $answer = file_postupdate_standard_editor($answer, 'answertext', $editoroptions, $context,
                            'mod_treasurehunt', 'answertext', $answer->id);
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
        $DB->update_record('treasurehunt_riddles', $riddle);

        // Trigger event and update completion (if entry was created).
        $eventparams = array(
            'context' => $context,
            'objectid' => $riddle->id,
        );
        if ($isnewentry) {
            $event = \mod_treasurehunt\event\riddle_created::create($eventparams);
        } else {
            $event = \mod_treasurehunt\event\riddle_updated::create($eventparams);
        }
        $event->trigger();

        // Actualizo el tiempo de modificacion del camino.
        $road = new stdClass();
        $road->id = $riddle->roadid;
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




