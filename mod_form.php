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

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');

/**
 * Module instance settings form
 *
 * @package    mod_treasurehunt
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_treasurehunt_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     */
    public function definition() {
        $treasurehuntconfig = get_config('mod_treasurehunt');
        $mform = $this->_form;

        // Adding the "general" fieldset, where all the common settings are showed.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('treasurehuntname', 'treasurehunt'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        //Aquí añadimos la regla del tamaño máximo de la cadena.
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Adding the standard "intro" and "introformat" fields. Esto sirve para poner la descripción, si quieres 
        // ... que aparezca en la portada, etc.
        $this->standard_intro_elements();
        $mform->addElement('advcheckbox', 'playwithoutmove', get_string('playwithoutmove', 'treasurehunt'));
        $mform->addHelpButton('playwithoutmove', 'playwithoutmove', 'treasurehunt');

        // Adding the rest of treasurehunt settings, spreading all them into this fieldset
        // ... or adding more fieldsets ('header' elements) if needed for better logic.

        $mform->addElement('header', 'availability', get_string('availability', 'treasurehunt'));
        $mform->setExpanded('availability', true);

        $name = get_string('allowattemptsfromdate', 'treasurehunt');
        $options = array('optional' => true, 'step' => 1);
        $mform->addElement('date_time_selector', 'allowattemptsfromdate', $name, $options);
        $mform->addHelpButton('allowattemptsfromdate', 'allowattemptsfromdate', 'treasurehunt');

        $name = get_string('cutoffdate', 'treasurehunt');
        $mform->addElement('date_time_selector', 'cutoffdate', $name, $options);
        $mform->addHelpButton('cutoffdate', 'cutoffdate', 'treasurehunt');

        $name = get_string('alwaysshowdescription', 'treasurehunt');
        $mform->addElement('checkbox', 'alwaysshowdescription', $name);
        $mform->addHelpButton('alwaysshowdescription', 'alwaysshowdescription', 'treasurehunt');
        $mform->disabledIf('alwaysshowdescription', 'allowsubmissionsfromdate[enabled]', 'notchecked');

        $mform->addElement('header', 'groups', get_string('groups', 'treasurehunt'));
        $mform->setExpanded('groups', true);
        $mform->addElement('advcheckbox', 'groupmode', get_string('groupmode', 'treasurehunt'));
        $mform->addHelpButton('groupmode', 'groupmode', 'treasurehunt');


        // Add standard grading elements. Calificación.
        $this->standard_grading_coursemodule_elements();
        // If is not an update.
        if (empty($this->_cm)) {
            $mform->setDefault('grade[modgrade_type]', 'point');
        }
        if (!$this->current->grade > 0) {
            $mform->setDefault('grade[modgrade_point]', $treasurehuntconfig->maximumgrade);
        }
        // Grading method.
        $mform->addElement('select', 'grademethod', get_string('grademethod', 'treasurehunt'), treasurehunt_get_grading_options());
        $mform->addHelpButton('grademethod', 'grademethod', 'treasurehunt');
        $mform->setDefault('grademethod', $treasurehuntconfig->grademethod);
        $mform->disabledIf('grademethod', 'grade[modgrade_type]', 'neq', 'point');
        // Grading penalization.
        $mform->addElement('text', 'gradepenlocation', get_string('gradepenlocation', 'treasurehunt'));
        $mform->addHelpButton('gradepenlocation', 'gradepenlocation', 'treasurehunt');
        $mform->setType('gradepenlocation', PARAM_FLOAT);
        $mform->setDefault('gradepenlocation', $treasurehuntconfig->penaltylocation);
        $mform->addRule('gradepenlocation', get_string('errnumeric', 'treasurehunt'), 'numeric', null, 'client');
        $mform->disabledIf('gradepenlocation', 'grade[modgrade_type]', 'neq', 'point');
        $mform->addElement('text', 'gradepenanswer', get_string('gradepenanswer', 'treasurehunt'));
        $mform->addHelpButton('gradepenanswer', 'gradepenlocation', 'treasurehunt');
        $mform->setType('gradepenanswer', PARAM_FLOAT);
        $mform->setDefault('gradepenanswer', $treasurehuntconfig->penaltyanswer);
        $mform->addRule('gradepenanswer', get_string('errnumeric', 'treasurehunt'), 'numeric', null, 'client');
        $mform->disabledIf('gradepenanswer', 'grade[modgrade_type]', 'neq', 'point');
        // Add standard elements, common to all modules. Ajustes comunes (Visibilidad, número ID y modo grupo).
        $this->standard_coursemodule_elements();



        // Add standard buttons, common to all modules. Botones.
        $this->add_action_buttons($cancel = true);
    }

    /**
     * Perform minimal validation on the settings form
     * @param array $data
     * @param array $files
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($data['allowattemptsfromdate'] && $data['cutoffdate']) {
            if ($data['allowattemptsfromdate'] > $data['cutoffdate']) {
                $errors['cutoffdate'] = get_string('cutoffdatefromdatevalidation', 'treasurehunt');
            }
        }
        if ($data['gradepenlocation'] > 100) {
            $errors['gradepenlocation'] = get_string('errpenalizationexceed', 'treasurehunt');
        }
        if ($data['gradepenlocation'] < 0) {
            $errors['gradepenlocation'] = get_string('errpenalizationexceed', 'treasurehunt');
        }
        if ($data['gradepenanswer'] > 100) {
            $errors['gradepenanswer'] = get_string('errpenalizationfall', 'treasurehunt');
        }
        if ($data['gradepenanswer'] < 0) {
            $errors['gradepenanswer'] = get_string('errpenalizationfall', 'treasurehunt');
        }

        return $errors;
    }

}
