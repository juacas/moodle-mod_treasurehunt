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
 * The main treasurehunt configuration form
 *
 * @package   mod_treasurehunt
 * @author Adrian Rodriguez <huorwhisp@gmail.com>
 * @author Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

/**
 * Constant determines the number of answer boxes to add in the editing
 * form for multiple choice and similar question types when the user presses
 * 'add form fields button'.
 */
define("NUMBER_NEW_ANSWERS", 2);

/**
 * Module instance settings form
 *
 * @package    mod_treasurehunt
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stage_form extends moodleform {

    /**
     * Defines forms elements
     * @global type $CFG
     */
    public function definition() {
        global $CFG;
        $mform = $this->_form;
        $formid = $mform->_attributes['id'];
        $editoroptions = $this->_customdata['editoroptions'];
        $currentstage = $this->_customdata['current'];
        $completionactivities = $this->_customdata['completionactivities'];

        // Adding the "general" fieldset, where all the common settings are showed.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('stagename', 'treasurehunt'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        // Aqui anadimos la regla del tamano maximo de la cadena.
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('advcheckbox', 'playstagewithoutmoving', get_string('playstagewithoutmoving', 'treasurehunt'));
        $mform->addHelpButton('playstagewithoutmoving', 'playstagewithoutmoving', 'treasurehunt');

        $mform->addElement('header', 'restrictionsdiscoverstage',
                get_string('restrictionsdiscoverstage', 'treasurehunt'));
        // Add restrict access completion activity.
        $options = array();
        $options[0] = get_string('none');
        foreach ($completionactivities as $option) {
            $options[$option->id] = $option->name;
        }
        $mform->addElement('select', 'activitytoend', get_string('activitytoend', 'treasurehunt'), $options);
        $mform->addHelpButton('activitytoend', 'activitytoend', 'treasurehunt');

        // Seleccionar si quiero pregunta opcional. En el caso de cambio recargo la pagina con truco:
        // llamo al cancel que no necesita comprobar la validacion
        // ... y le doy un valor a una variable escondida.
        $form = "document.forms['" . $formid . "']";
        $javascript = "$form.reloaded.value='1';$form.cancel.click();"; // Create javascript: set reloaded field to "1".
        $attributes = array("onChange" => $javascript); // Set onChange attribute.
        $mform->addElement('selectyesno', 'addsimplequestion', get_string('addsimplequestion', 'treasurehunt'),
                $attributes);
        $mform->addHelpButton('addsimplequestion', 'addsimplequestion', 'treasurehunt');

        if ($currentstage->addsimplequestion) {
            // Questions fields.
            $mform->addElement('editor', 'questiontext_editor', get_string('question', 'treasurehunt'), null,
                    $editoroptions);
            $mform->setType('questiontext_editor', PARAM_RAW);
            $mform->addRule('questiontext_editor', null, 'required', null, 'client');
            // Answer fields.
            $this->add_per_answer_fields($mform, get_string('choiceno', 'qtype_multichoice', '{no}'), $editoroptions,
                    $currentstage->noanswers, NUMBER_NEW_ANSWERS);
        }
        $mform->addElement('header', 'cluetextsection',
                get_string('stageclue', 'treasurehunt'));
        // Adding the standard "intro" and "introformat" fields. This is the clue to find out the next stage in the game.
        $mform->addElement('editor', 'cluetext_editor', get_string('stageclue_help', 'treasurehunt'), null, $editoroptions);
        $mform->addHelpButton('cluetext_editor', 'stageclue', 'treasurehunt');
        $mform->setType('cluetext_editor', PARAM_RAW);
        $mform->addRule('cluetext_editor', null, 'required', null, 'client');
        // Anado los campos ocultos.
        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'roadid');
        $mform->setType('roadid', PARAM_INT);
        // Needed to reload the page.
        $mform->addElement('hidden', 'reloaded');
        $mform->setType('reloaded', PARAM_INT);
        $mform->setConstant('reloaded', 0);

        // Add standard buttons, common to all modules. Botones.
        $this->add_action_buttons();

        $this->set_data($currentstage);
    }

    public function set_data($entry) {
        $entry = $this->data_preprocessing($entry);
        parent::set_data($entry);
    }

    protected function data_preprocessing($entry) {
        $editoroptions = $this->_customdata['editoroptions'];
        $context = $this->_customdata['context'];
        // Prepare all editors.
        $entry = file_prepare_standard_editor($entry, 'cluetext', $editoroptions, $context, 'mod_treasurehunt',
                'cluetext', $entry->id);

        // Si existe la pregunta.
        if ($entry->addsimplequestion) {
            if (isset($entry->questiontext)) {
                $entry = file_prepare_standard_editor($entry, 'questiontext', $editoroptions, $context,
                        'mod_treasurehunt', 'questiontext', $entry->id);
            }
            if (isset($entry->answers)) {
                $k = 0;
                foreach ($entry->answers as $answer) {
                    $answer = file_prepare_standard_editor($answer, 'answertext', $editoroptions, $context,
                            'mod_treasurehunt', 'answertext', $answer->id);
                    $entry->answertext_editor[$k] = $answer->answertext_editor;
                    $entry->correct[$k] = $answer->correct;
                    $k++;
                }
            }
        }
        return $entry;
    }

    public function validation($data, $files) {

        $errors = array();

        if (array_key_exists('answertext_editor', $data)) {
            $answers = $data['answertext_editor'];
            $answercount = 0;
            $correctcount = 0;

            foreach ($answers as $key => $answer) {
                // Check no of choices.
                $trimmedanswer = trim($answer['text']);
                $correct = (int) $data['correct'][$key];
                if ($trimmedanswer === '' && $correct === 0) {
                    continue;
                }
                if ($correct === 1) {
                    $correctcount++;
                }
                $answercount++;
                if ($trimmedanswer === '') {
                    $errors['correct[' . $key . ']'] = get_string('errcorrectsetanswerblank', 'treasurehunt');
                }
            }

            if ($answercount === 0) {
                $errors['answertext_editor[0]'] = get_string('notenoughanswers', 'qtype_multichoice', 2);
                $errors['answertext_editor[1]'] = get_string('notenoughanswers', 'qtype_multichoice', 2);
            } else if ($answercount == 1) {
                $errors['answertext_editor[1]'] = get_string('notenoughanswers', 'qtype_multichoice', 2);
            }

            // Check if have correct choice.
            if ($correctcount === 0) {
                $errors['correct[0]'] = get_string('errcorrectanswers', 'treasurehunt');
            } else if ($correctcount > 1) {
                $errors['correct[0]'] = get_string('errnocorrectanswers', 'treasurehunt');
            }
        }

        return $errors;
    }

    /**
     * Get the list of form elements to repeat, one for each answer.
     * @param object $mform the form being built.
     * @param $label the label to use for each option.
     * @param $editoroptions the possible grades for each answer.
     * @param $repeatedoptions reference to array of repeated options to fill
     * @param $answersoption reference to return the name of $question->options
     *      field holding an array of answers
     * @return array of form fields.
     */
    protected function get_per_answer_fields($mform, $label, $editoroptions, &$repeatedoptions) {
        $repeated = array();
        $repeated[] = $mform->createElement('editor', 'answertext_editor', $label, array('rows' => 1), $editoroptions);
        $repeated[] = $mform->createElement('advcheckbox', 'correct', '', get_string('correctanswer', 'treasurehunt'));
        $repeatedoptions['answer']['type'] = PARAM_RAW;
        $repeatedoptions['fraction']['default'] = 0;
        return $repeated;
    }

    /**
     * Add a set of form fields, obtained from get_per_answer_fields, to the form,
     * one for each existing answer, with some blanks for some new ones.
     * @param object $mform the form being built.
     * @param $label the label to use for each option.
     * @param $editoroptions the possible grades for each answer.
     * @param $minoptions the minimum number of answer blanks to display.
     *      Default QUESTION_NUMANS_START.
     * @param $addoptions the number of answer blanks to add. Default QUESTION_NUMANS_ADD.
     */
    protected function add_per_answer_fields(&$mform, $label, $editoroptions, $repeatsatstart, $addoptions = 0) {
        $repeatedoptions = array();
        $repeated = $this->get_per_answer_fields($mform, $label, $editoroptions, $repeatedoptions);
        $this->repeat_elements($repeated, $repeatsatstart, $repeatedoptions, 'noanswers', 'addanswers', $addoptions);
    }

    public function is_reloaded() {

        return optional_param('reloaded', 0, PARAM_INT);
    }

}
