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

require_once("$CFG->libdir/formslib.php");

/**
 * Module instance settings form
 *
 * @package    mod_scavengerhunt
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class riddle_form extends moodleform {

    /**
     * Defines forms elements
     */
    public function definition() {

        $mform = $this->_form;
        $descriptionoptions = $this->_customdata['descriptionoptions'];
        $currententry = $this->_customdata['current'];
        $completionactivities = $this->_customdata['completionactivities'];

        // Adding the "general" fieldset, where all the common settings are showed.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('riddlename', 'scavengerhunt'), array('size' => '64'));
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
        $mform->addElement('editor', 'description_editor', get_string('riddle_editor', 'scavengerhunt'), null, $descriptionoptions);
        $mform->setType('description_editor', PARAM_RAW);
        $mform->addRule('description_editor', null, 'required', null, 'client');
        // Add restrict access completion activity.
        $options = array();
        $options[0] = get_string('none');
        foreach ($completionactivities as $option) {
            $options[$option->id] = $option->name;
        }
        $mform->addElement('header', 'availabilityconditionsheader', get_string('restrictaccess', 'scavengerhunt'));
        $mform->addElement('select', 'activitytoend', get_string('activitytoend', 'scavengerhunt'), $options);
        $mform->addHelpButton('activitytoend', 'activitytoend', 'scavengerhunt');

        // Añado los campos ocultos id y newFeature.
        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'road_id');
        $mform->setType('road_id', PARAM_INT);

        // Add standard buttons, common to all modules. Botones.
        $this->add_action_buttons($cancel = true);

        $this->set_data($currententry);
    }

}
