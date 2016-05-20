<?php

// This file is part of Moodle - http://moodle.org/
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
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package    mod_treasurehunt
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

/**
 * Module instance settings form
 *
 * @package    mod_treasurehunt
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class riddle_form extends moodleform {

    /**
     * Defines forms elements
     */
    public function definition() {

        $mform = $this->_form;
        $formid = $mform->_attributes['id'];
        $editoroptions = $this->_customdata['descriptionoptions'];
        $currententry = $this->_customdata['current'];
        $completionactivities = $this->_customdata['completionactivities'];

        // Adding the "general" fieldset, where all the common settings are showed.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('riddlename', 'treasurehunt'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        //Aqui anadimos la regla del tamano maximo de la cadena.
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        // Adding the standard "intro" and "introformat" fields. Esto sirve para poner la descripciÃ³n, si quieres 
        // ... que aparezca en la portada, etc.
        $mform->addElement('editor', 'description_editor', get_string('riddledescription', 'treasurehunt'), null, $editoroptions);
        $mform->setType('description_editor', PARAM_RAW);
        $mform->addRule('description_editor', null, 'required', null, 'client');

        $mform->addElement('header', 'overcomeriddlesrestrictions', get_string('overcomeriddlerestrictions', 'treasurehunt'));
        // Add restrict access completion activity.
        $options = array();
        $options[0] = get_string('none');
        foreach ($completionactivities as $option) {
            $options[$option->id] = $option->name;
        }
        $mform->addElement('select', 'activitytoend', get_string('activitytoend', 'treasurehunt'), $options);
        $mform->addHelpButton('activitytoend', 'activitytoend', 'treasurehunt');

        // Seleccionar si quiero pregunta opcional. En el caso de cambio recargo la pagina con truco: llamo al cancel que no necesita comprobar la validacion
        // ... y le doy un valor a una variable escondida.
        $form = "document.forms['" . $formid . "']";
        $javascript = "$form.reloaded.value='1';$form.showquestion.value= $form.addsimplequestion.value;$form.cancel.click();"; //create javascript: set reloaded field to "1" 
        $attributes = array("onChange" => $javascript); // set onChange attribute
        $select = $mform->addElement('selectyesno', 'addsimplequestion', get_string('addsimplequestion', 'treasurehunt'), $attributes);
        $mform->addHelpButton('addsimplequestion', 'addsimplequestion', 'treasurehunt');
        $select->setSelected('0');

        if (optional_param('showquestion', 0, PARAM_INT)) {
            // Imprimo el editor de preguntas
            $mform->addElement('editor', 'questiontext_editor', get_string('question', 'treasurehunt'), null, $editoroptions);
            $mform->setType('questiontext_editor', PARAM_RAW);
            $mform->addRule('questiontext_editor', null, 'required', null, 'client');
            // Imprimo las respuestas
            $this->add_per_answer_fields($mform, get_string('choiceno', 'qtype_multichoice', '{no}'), $editoroptions, 2, 2);
        }

        // Anado los campos ocultos.
        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'roadid');
        $mform->setType('roadid', PARAM_INT);
        // Necesario para recargar la pagina
        $mform->addElement('hidden', 'reloaded');
        $mform->setType('reloaded', PARAM_INT);
        $mform->setConstant('reloaded', 0);
        // Necesario para mostrar la pregunta
        $mform->addElement('hidden', 'showquestion');
        $mform->setType('showquestion', PARAM_INT, 0);



        // Add standard buttons, common to all modules. Botones.
        $this->add_action_buttons();

        $this->set_data($currententry);
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
    protected function get_per_answer_fields($mform, $label, $editoroptions, &$repeatedoptions, &$answersoption) {
        $repeated = array();
        $repeated[] = $mform->createElement('editor', 'answertext', $label, array('rows' => 1), $editoroptions);
        $repeated[] = $mform->createElement('advcheckbox', 'groupmode', '', get_string('groupmode', 'treasurehunt'));
        $repeatedoptions['answer']['type'] = PARAM_RAW;
        $repeatedoptions['fraction']['default'] = 0;
        $answersoption = 'answers';
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
    protected function add_per_answer_fields(&$mform, $label, $editoroptions, $minoptions = 2, $addoptions = 0) {
        global $CFG;
        $answersoption = '';
        $repeatedoptions = array();
        $repeated = $this->get_per_answer_fields($mform, $label, $editoroptions, $repeatedoptions, $answersoption);

        if (isset($this->question->options)) {
            $repeatsatstart = count($this->question->options->$answersoption);
        } else {
            $repeatsatstart = $minoptions;
        }
        $this->repeat_elements($repeated, $repeatsatstart, $repeatedoptions, 'noanswers', 'addanswers', $addoptions);
    }

    public function is_reloaded() {

        return optional_param('reloaded', 0, PARAM_INT);
    }

}
