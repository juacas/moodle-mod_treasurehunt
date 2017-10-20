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
 * This file contains the forms to create and edit an instance of this module
 *
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');

/**
 * Module instance settings form
 *
 * @package    mod_treasurehunt
 * @copyright  2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_treasurehunt_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     * @global type $CFG
     */
    public function definition() {
        global $CFG;
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
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Adding the standard "intro" and "introformat" fields. Esto sirve para poner la descripción, si quieres
        // que aparezca en la portada, etc.
        $this->standard_intro_elements();
        $mform->addElement('advcheckbox', 'playwithoutmoving', get_string('playwithoutmoving', 'treasurehunt'));
        $mform->addHelpButton('playwithoutmoving', 'playwithoutmoving', 'treasurehunt');
        // Track users.
        $mform->addElement('advcheckbox', 'tracking', get_string('trackusers', 'treasurehunt'));
        $mform->addHelpButton('tracking', 'trackusers', 'treasurehunt');
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
        if (isset($this->current->grade) && !($this->current->grade > 0) || empty($this->_cm)) {
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

        // Custom background.
        $mform->addElement('header', 'custommaps', get_string('custommapping', 'treasurehunt'));
        $mform->setExpanded('custommaps', false);
        $mform->addElement('filemanager', 'custombackground',  get_string('custommapimagefile', 'treasurehunt'), null,
                            array('subdirs' => false, 'maxbytes' => null, 'areamaxbytes' => null, 'maxfiles' => 1,
                                'accepted_types' => array('jpg', 'svg', 'png'), 'return_types' => FILE_INTERNAL | FILE_EXTERNAL));
        $mform->addHelpButton('custombackground', 'custommapimagefile', 'treasurehunt');

        $layertypes = ['base' => get_string('custommapbaselayer', 'treasurehunt'),
                        'overlay' => get_string('custommapoverlaylayer', 'treasurehunt'),
                        'onlybase' => get_string('custommaponlybaselayer', 'treasurehunt') ];
        $mform->addElement('select', 'customlayertype', get_string('customlayertype', 'treasurehunt'), $layertypes);
        $mform->addHelpButton('customlayertype', 'customlayertype', 'treasurehunt');
        $mform->setDefault('customlayertype', 'base');

        $mform->addElement('text', 'customlayername', get_string('customlayername', 'treasurehunt'));
        $mform->addHelpButton('customlayername', 'customlayername', 'treasurehunt');
        $mform->setType('customlayername', PARAM_TEXT);

        $mform->addElement('text', 'custommapminlon', get_string('custommapminlon', 'treasurehunt'));
        $mform->addHelpButton('custommapminlon', 'custommapminlon', 'treasurehunt');
        $mform->setType('custommapminlon', PARAM_FLOAT);
        $mform->addRule('custommapminlon', get_string('errnumeric', 'treasurehunt'), 'numeric', null, 'client');

        $mform->addElement('text', 'custommapminlat', get_string('custommapminlat', 'treasurehunt'));
        $mform->addHelpButton('custommapminlat', 'custommapminlat', 'treasurehunt');
        $mform->setType('custommapminlat', PARAM_FLOAT);
        $mform->addRule('custommapminlat', get_string('errnumeric', 'treasurehunt'), 'numeric', null, 'client');

        $mform->addElement('text', 'custommapmaxlon', get_string('custommapmaxlon', 'treasurehunt'));
        $mform->addHelpButton('custommapmaxlon', 'custommapmaxlon', 'treasurehunt');
        $mform->setType('custommapmaxlon', PARAM_FLOAT);
        $mform->addRule('custommapmaxlon', get_string('errnumeric', 'treasurehunt'), 'numeric', null, 'client');

        $mform->addElement('text', 'custommapmaxlat', get_string('custommapmaxlat', 'treasurehunt'));
        $mform->addHelpButton('custommapmaxlat', 'custommapmaxlat', 'treasurehunt');
        $mform->setType('custommapmaxlat', PARAM_FLOAT);
        $mform->addRule('custommapmaxlat', get_string('errnumeric', 'treasurehunt'), 'numeric', null, 'client');

        // Add standard elements, common to all modules. Ajustes comunes (Visibilidad, número ID y modo grupo).
        $this->standard_coursemodule_elements();
        // Add standard buttons, common to all modules. Botones.
        $this->add_action_buttons( true);
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
        if ($data['custombackground']) {
            if ($data['customlayername'] < 0) {
                $errors['customlayername'] = get_string('customlayername_help', 'treasurehunt');
            }
            if ($data['custommapminlat'] === '' || $data['custommapminlat'] < -85 ||
                    $data['custommapminlat'] >= $data['custommapmaxlat']) {
                $errors['custommapminlat'] = get_string('custommapminlat_help', 'treasurehunt');
            }
            if ($data['custommapmaxlat'] === '' || $data['custommapmaxlat'] > 85 ||
                    $data['custommapminlat'] >= $data['custommapmaxlat']) {
                $errors['custommapmaxlat'] = get_string('custommapmaxlat_help', 'treasurehunt');
            }
            if ($data['custommapminlon'] === '' || $data['custommapminlon'] < -180 ||
                    $data['custommapminlon'] >= $data['custommapmaxlon']) {
                $errors['custommapminlon'] = get_string('custommapminlon_help', 'treasurehunt');
            }
            if ($data['custommapmaxlon'] === '' || $data['custommapmaxlon'] > 180 ||
                    $data['custommapminlon'] >= $data['custommapmaxlon']) {
                $errors['custommapmaxlon'] = get_string('custommapmaxlon_help', 'treasurehunt');
            }
        }
        return $errors;
    }
    public function data_preprocessing(&$defaultvalues) {
        $draftitemid = file_get_submitted_draft_itemid('custombackground');
        file_prepare_draft_area($draftitemid, $this->context->id, 'mod_treasurehunt', 'custombackground',
                                0, array('subdirs' => false));
        $defaultvalues['custombackground'] = $draftitemid;
        $custommapconfig = isset($this->current->custommapconfig) ? json_decode($this->current->custommapconfig) : null;
        if ($custommapconfig) {
            $defaultvalues['custommapminlon'] = $custommapconfig->bbox[0];
            $defaultvalues['custommapminlat'] = $custommapconfig->bbox[1];
            $defaultvalues['custommapmaxlon'] = $custommapconfig->bbox[2];
            $defaultvalues['custommapmaxlat'] = $custommapconfig->bbox[3];
            if ($custommapconfig->onlybase) {
                $defaultvalues['customlayertype'] = 'onlybase';
            } else {
                $defaultvalues['customlayertype'] = $custommapconfig->layertype;
            }
            $defaultvalues['customlayername'] = isset($custommapconfig->layername) ? $custommapconfig->layername : null;
        }
    }
    /**
     * Allows modules to modify the data returned by form get_data().
     * This method is also called in the bulk activity completion form.
     *
     * Only available on moodleform_mod.
     *
     * @param stdClass $data passed by reference
     */
    public function data_postprocessing($data) {
        $mapconfig = new stdClass();
        $bbox = [floatval($data->custommapminlon) ,
                 floatval($data->custommapminlat),
                 floatval($data->custommapmaxlon),
                 floatval($data->custommapmaxlat)];
        $mapconfig->bbox = $bbox;
        if ($data->customlayertype == 'onlybase') {
            $mapconfig->layertype = 'base';
            $mapconfig->onlybase = true;
        } else {
            $mapconfig->layertype = $data->customlayertype;
            $mapconfig->onlybase = false;
        }
        $mapconfig->layername = $data->customlayername;
        $data->custommapconfig = json_encode($mapconfig);
    }

}
