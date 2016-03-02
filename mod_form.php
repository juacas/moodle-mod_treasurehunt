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
 * The main scavengerhunt configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package    mod_scavengerhunt
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Module instance settings form
 *
 * @package    mod_scavengerhunt
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_scavengerhunt_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     */
    public function definition() {

        $mform = $this->_form;

        // Adding the "general" fieldset, where all the common settings are showed.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('scavengerhuntname', 'scavengerhunt'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        //Aquí añadimos la regla del tamaño máximo de la cadena.
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Adding the standard "intro" and "introformat" fields. Esto sirve para poner la descripción, si quieres 
        // ... que aparezca en la portada, etc.
        if (method_exists($this, 'standard_intro_elements')) { //...compatibility check for 2.8
            $this->standard_intro_elements();
        } else {
            $this->add_intro_editor();
        }

        // Adding the rest of scavengerhunt settings, spreading all them into this fieldset
        // ... or adding more fieldsets ('header' elements) if needed for better logic.

        $mform->addElement('header', 'availability', get_string('availability', 'scavengerhunt'));
        $mform->setExpanded('availability', true);

        $name = get_string('allowsubmissionsfromdate', 'scavengerhunt');
        $options = array('optional' => true);
        $mform->addElement('date_time_selector', 'allowsubmissionsfromdate', $name, $options);
        $mform->addHelpButton('allowsubmissionsfromdate', 'allowsubmissionsfromdate', 'scavengerhunt');

        $name = get_string('duedate', 'scavengerhunt');
        $mform->addElement('date_time_selector', 'duedate', $name, array('optional' => true));
        $mform->addHelpButton('duedate', 'duedate', 'scavengerhunt');

        $name = get_string('cutoffdate', 'scavengerhunt');
        $mform->addElement('date_time_selector', 'cutoffdate', $name, array('optional' => true));
        $mform->addHelpButton('cutoffdate', 'cutoffdate', 'scavengerhunt');

        $name = get_string('alwaysshowdescription', 'scavengerhunt');
        $mform->addElement('checkbox', 'alwaysshowdescription', $name);
        $mform->addHelpButton('alwaysshowdescription', 'alwaysshowdescription', 'scavengerhunt');
        $mform->disabledIf('alwaysshowdescription', 'allowsubmissionsfromdate[enabled]', 'notchecked');



        // Add standard grading elements. Calificación.
        $this->standard_grading_coursemodule_elements();

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

        if ($data['allowsubmissionsfromdate'] && $data['duedate']) {
            if ($data['allowsubmissionsfromdate'] > $data['duedate']) {
                $errors['duedate'] = get_string('duedatevalidation', 'scavengerhunt');
            }
        }
        if ($data['duedate'] && $data['cutoffdate']) {
            if ($data['duedate'] > $data['cutoffdate']) {
                $errors['cutoffdate'] = get_string('cutoffdatevalidation', 'scavengerhunt');
            }
        }
        if ($data['allowsubmissionsfromdate'] && $data['cutoffdate']) {
            if ($data['allowsubmissionsfromdate'] > $data['cutoffdate']) {
                $errors['cutoffdate'] = get_string('cutoffdatefromdatevalidation', 'scavengerhunt');
            }
        }

        return $errors;
    }

}
