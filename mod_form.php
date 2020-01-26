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

        // WMS layer.
        $mform->addElement('text', 'customlayername', get_string('customlayername', 'treasurehunt'));
        $mform->addHelpButton('customlayername', 'customlayername', 'treasurehunt');
        $mform->setType('customlayername', PARAM_TEXT);

        $mform->addElement('text', 'customlayerwms', get_string('customlayerwms', 'treasurehunt'));
        $mform->addHelpButton('customlayerwms', 'customlayerwms', 'treasurehunt');
        $mform->setType('customlayerwms', PARAM_URL);
        $mform->disabledIf('customlayerwms', 'customlayername', 'eq', '');

        $mform->addElement('text', 'customwmsparams', get_string('customwmsparams', 'treasurehunt'));
        $mform->addHelpButton('customwmsparams', 'customwmsparams', 'treasurehunt');
        $mform->setType('customwmsparams', PARAM_RAW);
        $mform->disabledIf('customwmsparams', 'customlayerwms', 'eq', '');

        // Local file overlay.
        $mform->addElement('filemanager', 'custombackground',  get_string('custommapimagefile', 'treasurehunt'), null,
                            array('subdirs' => false, 'maxbytes' => null, 'areamaxbytes' => null, 'maxfiles' => 1,
                                'accepted_types' => array('jpg', 'svg', 'png'), 'return_types' => FILE_INTERNAL | FILE_EXTERNAL));
        $mform->addHelpButton('custombackground', 'custommapimagefile', 'treasurehunt');
        $mform->disabledIf('custombackground', 'customlayername', 'eq', '');

        $layertypes = ['base' => get_string('custommapbaselayer', 'treasurehunt'),
                        'overlay' => get_string('custommapoverlaylayer', 'treasurehunt'),
                        'onlybase' => get_string('custommaponlybaselayer', 'treasurehunt'),
                        'nongeographic' => get_string('custommapnongeographic', 'treasurehunt')
                      ];
        $mform->addElement('select', 'customlayertype', get_string('customlayertype', 'treasurehunt'), $layertypes);
        $mform->addHelpButton('customlayertype', 'customlayertype', 'treasurehunt');
        $mform->setDefault('customlayertype', 'base');
        $mform->disabledIf('customlayertype', 'customlayername', 'eq', '');

        $mform->addElement('text', 'custommapminlon', get_string('custommapminlon', 'treasurehunt'));
        $mform->addHelpButton('custommapminlon', 'custommapminlon', 'treasurehunt');
        $mform->setType('custommapminlon', PARAM_FLOAT);
        $mform->addRule('custommapminlon', get_string('errnumeric', 'treasurehunt'), 'numeric', null, 'client');
        $mform->disabledIf('custommapminlon', 'customlayername', 'eq', '');
        $mform->disabledIf('custommapminlon', 'customlayertype', 'eq', 'nongeographic');

        $mform->addElement('text', 'custommapminlat', get_string('custommapminlat', 'treasurehunt'));
        $mform->addHelpButton('custommapminlat', 'custommapminlat', 'treasurehunt');
        $mform->setType('custommapminlat', PARAM_FLOAT);
        $mform->addRule('custommapminlat', get_string('errnumeric', 'treasurehunt'), 'numeric', null, 'client');
        $mform->disabledIf('custommapminlat', 'customlayername', 'eq', '');
        $mform->disabledIf('custommapminlat', 'customlayertype', 'eq', 'nongeographic');

        $mform->addElement('text', 'custommapmaxlon', get_string('custommapmaxlon', 'treasurehunt'));
        $mform->addHelpButton('custommapmaxlon', 'custommapmaxlon', 'treasurehunt');
        $mform->setType('custommapmaxlon', PARAM_FLOAT);
        $mform->addRule('custommapmaxlon', get_string('errnumeric', 'treasurehunt'), 'numeric', null, 'client');
        $mform->disabledIf('custommapmaxlon', 'customlayername', 'eq', '');
        $mform->disabledIf('custommapmaxlon', 'customlayertype', 'eq', 'nongeographic');

        $mform->addElement('text', 'custommapmaxlat', get_string('custommapmaxlat', 'treasurehunt'));
        $mform->addHelpButton('custommapmaxlat', 'custommapmaxlat', 'treasurehunt');
        $mform->setType('custommapmaxlat', PARAM_FLOAT);
        $mform->addRule('custommapmaxlat', get_string('errnumeric', 'treasurehunt'), 'numeric', null, 'client');
        $mform->disabledIf('custommapmaxlat', 'customlayername', 'eq', '');
        $mform->disabledIf('custommapmaxlat', 'customlayertype', 'eq', 'nongeographic');

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
        // Layer name is the only lock field of the customlayer section.
        if (!empty($data['customlayername'])) {
            $draftitemid = $data['custombackground'];
            global $USER;
            $usercontext = context_user::instance($USER->id);
            $fs = get_file_storage();
            $draftfiles = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id');
            if ($data['customlayername'] == '') {
                $errors['customlayername'] = get_string('customlayername_help', 'treasurehunt');
            }
            if (count($draftfiles) == 0 && $data['customlayerwms'] == '') {
                $errors['customlayerwms'] = get_string('customlayerwms_help', 'treasurehunt');
                $errors['custombackground'] = get_string('custommapimagefile_help', 'treasurehunt');
            }
            if ($data['customlayertype'] !== 'nongeographic') {
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
            if (!empty($data['customlayerwms'])) {
                if ($data['customwmsparams'] == '') {
                    $errors['customwmsparams'] = get_string('customwmsparams_help', 'treasurehunt');
                }
            }
        }
        return $errors;
    }
    public function data_preprocessing(&$defaultvalues) {
        $draftitemid = file_get_submitted_draft_itemid('custombackground');
        file_prepare_draft_area($draftitemid, $this->context->id, 'mod_treasurehunt', 'custombackground',
                                0, array('subdirs' => false));
        $defaultvalues['custombackground'] = $draftitemid;
        $custommapconfig = treasurehunt_get_custommappingconfig($this->current);
        if ($custommapconfig) {
            $defaultvalues['custommapminlon'] = $custommapconfig->bbox[0];
            $defaultvalues['custommapminlat'] = $custommapconfig->bbox[1];
            $defaultvalues['custommapmaxlon'] = $custommapconfig->bbox[2];
            $defaultvalues['custommapmaxlat'] = $custommapconfig->bbox[3];
            if (isset($custommapconfig->geographic) && $custommapconfig->geographic === false) {
                $defaultvalues['customlayertype'] = 'nongeographic';
                // Adjust vertical frame for canvas on the map.
                $defaultvalues['custommapminlon'] = null;
                $defaultvalues['custommapminlat'] = -80;
                $defaultvalues['custommapmaxlon'] = null;
                $defaultvalues['custommapmaxlat'] = 80;

            } else if (isset($custommapconfig->onlybase) && $custommapconfig->onlybase == true ) {
                $defaultvalues['customlayertype'] = 'onlybase';
            } else {
                $defaultvalues['customlayertype'] = $custommapconfig->layertype; // Or base or overlay.
            }
            $defaultvalues['customlayername'] = isset($custommapconfig->layername) ? $custommapconfig->layername : null;
            if (isset($custommapconfig->wmsurl)) {
                $defaultvalues['customlayerwms'] = $custommapconfig->wmsurl;
            }
            if (isset($custommapconfig->wmsparams)) {
                $paramstr = (array) $custommapconfig->wmsparams;
                $params = http_build_query($paramstr, '', ';');
                $defaultvalues['customwmsparams'] = $params;
            }
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
        $mapconfig = treasurehunt_build_custommappingconfig($data);
        $data->custommapconfig = $mapconfig === null ? null : json_encode($mapconfig);
    }

    /**
     * Return submitted data if properly submitted or returns NULL if validation fails or
     * if there is no submitted data.
     *
     * Do not override this method, override data_postprocessing() instead.
     * JPC: Method introduced in moodleform_mod.php  Moodle 3.3
     * @return object submitted data; NULL if not valid or not submitted or cancelled
     */
    public function get_data() {
        global $CFG;
        $mform = $this->_form;
        // JPC workaround #24: In Moodle 3.5 when filemanager is disabled, no value is submitted and
        // form validation fails in filemanager.php.
        if ($mform->getSubmitValue('custombackground') == null) {
            $mform->_submitValues['custombackground'] = 0;
        }
        $data = parent::get_data();
        if ($CFG->version < 2017051500) {
            if ($data) {
                // Convert the grade pass value - we may be using a language which uses commas,
                // rather than decimal points, in numbers. These need to be converted so that
                // they can be added to the DB.
                if (isset($data->gradepass)) {
                    $data->gradepass = unformat_float($data->gradepass);
                }
                $this->data_postprocessing($data);
            }
        }
        return $data;
    }
    /**
     * Add any custom completion rules to the form.
     *
     * @return array Contains the names of the added form elements
     */
    public function add_completion_rules() {
        $mform =& $this->_form;
        $mform->addElement('advcheckbox', 'completionpass', '', get_string('completionpass', 'quiz'));
        $mform->disabledIf('completionpass', 'completionusegrade', 'notchecked');
        $mform->addHelpButton('completionpass', 'completionpass', 'quiz');
        // Enable this completion rule by default.
        $mform->setDefault('completionpass', 1);

        $mform->addElement('advcheckbox', 'completionfinish', '', get_string('completionfinish', 'treasurehunt'));
        $mform->addHelpButton('completionfinish', 'completionfinish', 'treasurehunt');
        // Enable this completion rule by default.
        $mform->setDefault('completionfinish', 1);
        return array('completionpass', 'completionfinish');
    }

    /**
     * Determines if completion is enabled for this module.
     *
     * @param array $data
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return !empty($data['completionfinish']) || !empty($data['completionpass']);
    }

}
