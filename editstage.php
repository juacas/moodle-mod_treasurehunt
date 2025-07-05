<?php
// This file is part of Treasurehunt for Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Page to edit stage
 *
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/editstage_form.php');
require_once("$CFG->dirroot/mod/treasurehunt/locallib.php");

global $COURSE, $PAGE, $CFG, $USER;
// You will process some page parameters at the top here and get the info about
// what instance of your module and what course you're in etc. Make sure you
// include hidden variable in your forms which have their defaults set in set_data
// which pass these variables from page to page.
// Setup $PAGE here.
// Print the page header.
$cmid = required_param('cmid', PARAM_INT); // Course_module ID.
$id = optional_param('id', 0, PARAM_INT);           // EntryID.
$roadid = optional_param('roadid', 0, PARAM_INT);
$addanswers = optional_param('addanswers', '', PARAM_TEXT);

list ($course, $cm) = get_course_and_cm_from_cmid($cmid, 'treasurehunt');
$treasurehunt = $DB->get_record('treasurehunt', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$url = new moodle_url('/mod/treasurehunt/editstage.php', array('cmid' => $cmid));
if (!empty($id)) {
    $url->param('id', $id);
}
if (!empty($roadid)) {
    $url->param('roadid', $roadid);
}
$PAGE->set_url($url);
$PAGE->activityheader->disable();
require_capability('mod/treasurehunt:managetreasurehunt', $context);
$PAGE->requires->jquery();

if (!treasurehunt_is_edition_locked($treasurehunt->id, $USER->id)) {
    $lockid = treasurehunt_renew_edition_lock($treasurehunt->id, $USER->id);
    $renewlocktime = (treasurehunt_get_setting_lock_time() - 5) * 1000;
    $PAGE->requires->js_call_amd('mod_treasurehunt/renewlock', 'renew_edition_lock',
                                array($treasurehunt->id, $lockid, $renewlocktime));

    if ($id) { // If entry is specified.
        require_capability('mod/treasurehunt:editstage', $context);
        $title = get_string('editingstage', 'treasurehunt');

        $stage = $DB->get_record('treasurehunt_stages', ['id' => $id], '*', MUST_EXIST);
        // Si existe la pregunta recojo las respuestas.
        if ($stage->questiontext !== '') {
            // Hago que se muestre la pregunta.
            $stage->addsimplequestion = optional_param('addsimplequestion', 1, PARAM_INT);
            $sqlanswers = 'SELECT a.id,a.answertext,a.answertextformat,a.answertexttrust,'
                    . ' a.correct FROM {treasurehunt_answers} a INNER JOIN'
                    . ' {treasurehunt_stages} r ON r.id=a.stageid WHERE r.id=?';
            $params = array($stage->id);
            $answers = $DB->get_records_sql($sqlanswers, $params);
            $stage->answers = $answers;
            $stage->noanswers = optional_param('noanswers', count($answers), PARAM_INT);
            if (!empty($addanswers)) {
                $stage->noanswers += NUMBER_NEW_ANSWERS;
            }
        }
    } else { // New entry.
        require_capability('mod/treasurehunt:addstage', $context);
        $title = get_string('addingstage', 'treasurehunt');
        $roadid = required_param('roadid', PARAM_INT);
        $select = 'id = ?';
        $params = array($roadid);
        // Compruebo si existe el camino.
        if (!$DB->record_exists_select('treasurehunt_roads', $select, $params)) {
            throw new moodle_exception('invalidentry');
        }
        // Compruebo si no esta bloqueado y por tanto no se puede anadir ninguna etapa.
        if (treasurehunt_check_road_is_blocked($roadid)) {
            throw new moodle_exception('notcreatestage', 'treasurehunt', $returnurl);
        }
        $stage = new stdClass();
        $stage->id = null;
        $stage->roadid = $roadid;
    }
    if (!isset($stage->questiontext) || $stage->questiontext === '') {
        $stage->addsimplequestion = optional_param('addsimplequestion', 0, PARAM_INT);
        $stage->noanswers = optional_param('noanswers', 2, PARAM_INT);
        if (!empty($addanswers)) {
            $stage->noanswers += NUMBER_NEW_ANSWERS;
        }
    }
    $stage->cmid = $cmid;
    $returnurl = new moodle_url('/mod/treasurehunt/edit.php', array('id' => $cmid, 'roadid' => $stage->roadid));

    $maxbytes = get_user_max_upload_file_size($PAGE->context, $CFG->maxbytes, $COURSE->maxbytes);
    $editoroptions = array('trusttext' => true, 'maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes' => $maxbytes,
                            'context' => $context,
                            'subdirs' => file_area_contains_subdirs($context, 'mod_treasurehunt', 'cluetext', $stage->id));
    // List activities with Completion enabled.
    $completioninfo = new completion_info($course);
    $completionactivities = $completioninfo->get_activities();
    // TODO: bypass if availability/treasurehunt is not installed.
    $lockedmods = treasurehunt_get_activities_with_stage_restriction($course->id, $stage->id);

    // Name of the form you defined in file above.
    $mform = new stage_form(null, 
        [   'current' => $stage,
                        'context' => $context,
                        'editoroptions' => $editoroptions,
                        'completionactivities' => $completionactivities,
                        'lockableactivities' => $lockedmods
                ]);

    if ($mform->is_reloaded()) {
        // Ignore this event. Some data may be changes.
        echo ' ';
    } else if ($mform->is_cancelled()) {
        // You need this section if you have a cancel button on your form
        // here you tell php what to do if your user presses cancel
        // probably a redirect is called for!
        // PLEASE NOTE: is_cancelled() should be called before get_data().
        redirect($returnurl);
    } else if ($stage = $mform->get_data()) {

        // Actualizamos los campos.
        $timenow = time();
        $stage->name = trim($stage->name);
        $stage->cluetext = '';          // Updated later.
        $stage->cluetextformat = FORMAT_HTML;  // Updated later.
        $stage->cluetexttrust = 0;            // Updated later.
        $stage->questiontext = '';           // Updated later.
        $stage->questiontextformat = FORMAT_HTML;  // Updated later.
        $stage->questiontexttrust = 0;            // Updated later.

        if (empty($stage->id)) {
            $stage->timecreated = $timenow;
            $stage->id = treasurehunt_insert_stage_form($stage);
            $isnewentry = true;
        } else {
            $stage->timemodified = $timenow;
            $isnewentry = false;
        }

        // This branch is where you process validated data.
        // Do stuff ...
        // Typically you finish up by redirecting to somewhere where the user
        // can see what they did.
        // save and relink embedded images and save attachments
        $stage = file_postupdate_standard_editor($stage, 'cluetext', $editoroptions, $context,
                                                'mod_treasurehunt', 'cluetext', $stage->id);
        // Store the updated value values.
        if ($stage->addsimplequestion) {
            // Proceso los ficheros del editor de pregunta.
            $stage = file_postupdate_standard_editor($stage, 'questiontext', $editoroptions, $context,
                                                    'mod_treasurehunt', 'questiontext', $stage->id);
            if (isset($stage->answertext_editor)) {
                // Process answer texts and save answers.
                foreach ($stage->answertext_editor as $key => $answertext) {
                    if (isset($answers) && count($answers) > 0) {
                        $answer = array_shift($answers);
                        if (trim($answertext['text']) === '') {
                            $DB->delete_records('treasurehunt_answers', array('id' => $answer->id));
                            continue;
                        }
                        $answer->timemodified = $timenow;
                        $answer->correct = $stage->correct[$key];
                    } else {
                        if (trim($answertext['text']) === '') {
                            continue;
                        }
                        $answer = new stdClass();
                        $answer->answertext = '';          // Updated later.
                        $answer->answertextformat = FORMAT_HTML; // Updated later.
                        $answer->answertexttrust = 0;           // Updated later.
                        $answer->timecreated = $timenow;
                        $answer->stageid = $stage->id;
                        $answer->correct = $stage->correct[$key];
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

        // TODO: Configure lockactivities.
        $lockedmods = array_filter($lockedmods, function($cminfo) { return $cminfo->locked == true; });
        $lockedcmids = array_map(function($cm) {return $cm->cmid;}, $lockedmods);
        $newlockedcms = $stage->lockactivity;
        // Locks to remove.
        $locks_to_remove = array_diff($lockedcmids, $newlockedcms);
        $locks_to_add = array_diff($newlockedcms, $lockedcmids);
        $cms = get_fast_modinfo($course->id)->get_cms();
        // Remove locks.
        foreach ($locks_to_remove as $cmid) {
            $availability = treasurehunt_remove_restriction($cms[$cmid], $stage);
            treasurehunt_update_activity_availability($cms[$cmid], $availability);
        }
        // Add new locks.
        foreach ($locks_to_add as $cmid) {
           $availability = treasurehunt_add_restriction($cms[$cmid], $stage, $treasurehunt->id);
            treasurehunt_update_activity_availability($cms[$cmid], $availability);
        }
        
        // Actualizo la etapa con los ficheros procesados.
        $DB->update_record('treasurehunt_stages', $stage);

        // Trigger event and update completion (if entry was created).
        $eventparams = array(
            'context' => $context,
            'objectid' => $stage->id,
            'other' => $stage->name,
        );
        if ($isnewentry) {
            $event = \mod_treasurehunt\event\stage_created::create($eventparams);
        } else {
            $event = \mod_treasurehunt\event\stage_updated::create($eventparams);
        }
        $event->trigger();

        // Actualizo el tiempo de modificacion del camino.
        $road = new stdClass();
        $road->id = $stage->roadid;
        $road->timemodified = time();
        $DB->update_record('treasurehunt_roads', $road);

        redirect($returnurl);
    }
} else {
    $returnurl = new moodle_url('/mod/treasurehunt/view.php', array('id' => $cmid));
    throw new moodle_exception('treasurehuntislocked', 'treasurehunt', $returnurl, treasurehunt_get_username_blocking_edition($treasurehunt->id));
}
$PAGE->navbar->add(get_string('edittreasurehunt', 'treasurehunt'), $returnurl);
$PAGE->navbar->add(get_string('editstage', 'treasurehunt'), $url);
$PAGE->set_title($title);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('standard');
echo $OUTPUT->header();
echo $OUTPUT->heading($title);
// Support for QR scan.
treasurehunt_qr_support($PAGE, 'enableEditForm');
// End QR.
$mform->display();
echo $OUTPUT->footer();



