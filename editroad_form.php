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
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

/**
 * Module instance settings form
 *
 * @package    mod_treasurehunt
 * @copyright  2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class road_form extends moodleform {

    /**
     * Defines forms elements
     */
    public function definition() {
        global $CFG;
        $mform = $this->_form;
        $selectoptions = $this->_customdata['selectoptions'];
        $currentroad = $this->_customdata['current'];
        $groups = $this->_customdata['groups'];

        // Adding the "general" fieldset, where all the common settings are showed.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('roadname', 'treasurehunt'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        // Aquí añadimos la regla del tamaño máximo de la cadena.
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $options = array();
        $options[0] = get_string('none');
        foreach ($selectoptions as $option) {
            $options[$option->id] = $option->name;
        }
        if ($groups) {
            $select = $mform->addElement('select', 'groupingid', get_string('groupingid', 'treasurehunt'), $options);
            $mform->addHelpButton('groupingid', 'groupingid', 'treasurehunt');
        } else {
            $select = $mform->addElement('select', 'groupid', get_string('groupid', 'treasurehunt'), $options);
            $mform->addHelpButton('groupid', 'groupid', 'treasurehunt');
        }

        // Añado los campos ocultos id y newFeature.
        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Add standard buttons, common to all modules. Botones.
        $this->add_action_buttons(true);

        $this->set_data($currentroad);
    }

}
