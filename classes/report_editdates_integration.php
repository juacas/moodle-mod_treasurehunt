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

defined('MOODLE_INTERNAL') || die;
/**
 * Integration with report_editdates plugin
 * @author juacas
 */
class mod_treasurehunt_report_editdates_integration
extends report_editdates_mod_date_extractor {

    public function __construct($course) {
        parent::__construct($course, 'treasurehunt');
        parent::load_data();
    }

    public function get_settings(cm_info $cm) {
        $treasurehunt = $this->mods[$cm->instance];
        return array(
            'allowattemptsfromdate' => new report_editdates_date_setting(
                get_string('allowattemptsfromdate', 'treasurehunt'),
                $treasurehunt->allowattemptsfromdate,
                self::DATETIME, true, 5),
            'cutoffdate' => new report_editdates_date_setting(
                                get_string('cutoffdate', 'treasurehunt'),
                                $treasurehunt->cutoffdate,
                                self::DATETIME, true, 5)
        );
    }

    public function validate_dates(cm_info $cm, array $dates) {
        $errors = array();
        if (!empty($dates['allowattemptsfromdate']) && !empty($dates['timeclose']) &&
                $dates['cutoffdate'] < $dates['allowattemptsfromdate']) {
                    $errors['cutoffdate'] = get_string('timeclose', 'report_editdates');
        }
        return $errors;
    }
}
