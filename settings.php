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
 * Administration settings definitions for the treasurehunt module.
 *
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;
require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');

if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_heading('treasurehuntintro', '', get_string('configintro', 'treasurehunt')));
        // Available game player styles.
        $settings->add(new admin_setting_configmultiselect(
                'mod_treasurehunt/availableplayerstyles',
                get_string('availableplayerstyles', 'treasurehunt'),
                '',
                [TREASUREHUNT_PLAYERCLASSIC, TREASUREHUNT_PLAYERFANCY, TREASUREHUNT_PLAYERBOOTSTRAP],
                treasurehunt_get_installedplayerstyles()
        ));
        // Default game player style.
        $settings->add(new admin_setting_configselect(
                'mod_treasurehunt/defaultplayerstyle',
                get_string('defaultplayerstyle', 'treasurehunt'),
                get_string('playerstyle_help', 'treasurehunt'),
                TREASUREHUNT_PLAYERBOOTSTRAP,
                treasurehunt_get_installedplayerstyles()
        ));
        // Maximum grade.
        $settings->add(new admin_setting_configtext(
                'mod_treasurehunt/maximumgrade',
                get_string('maximumgrade'),
                get_string('configmaximumgrade', 'treasurehunt'),
                10,
                PARAM_INT
        ));
        // Grading method.
        $settings->add(new mod_treasurehunt_admin_setting_grademethod(
                'mod_treasurehunt/grademethod',
                get_string('grademethod', 'treasurehunt'),
                get_string('grademethod_help', 'treasurehunt'),
                TREASUREHUNT_GRADEFROMSTAGES,
                null
        ));
        // Location penalization.
        $settings->add(new admin_setting_configtext(
                'mod_treasurehunt/penaltylocation',
                get_string('gradepenlocation', 'treasurehunt'),
                get_string('gradepenlocation_help', 'treasurehunt'),
                0.00,
                PARAM_FLOAT
        ));
        // Question penalization.
        $settings->add(new admin_setting_configtext(
                'mod_treasurehunt/penaltyanswer',
                get_string('gradepenanswer', 'treasurehunt'),
                get_string('gradepenlocation_help', 'treasurehunt'),
                0.00,
                PARAM_FLOAT
        ));
        // Renewed times.
        $settings->add(new admin_setting_heading('updatetimesheading', get_string('updatetimes', 'treasurehunt'), ''));
        // Lock time editing.
        $settings->add(new admin_setting_configtext(
                'mod_treasurehunt/locktimeediting',
                get_string('locktimeediting', 'treasurehunt'),
                get_string('locktimeediting_help', 'treasurehunt'),
                TREASUREHUNT_LOCKTIME,
                PARAM_INT
        ));
        // Game update time.
        $settings->add(new admin_setting_configtext(
                'mod_treasurehunt/gameupdatetime',
                get_string('gameupdatetime', 'treasurehunt'),
                get_string('gameupdatetime_help', 'treasurehunt'),
                TREASUREHUNT_GAMEUPDATETIME,
                PARAM_INT
        ));
}
