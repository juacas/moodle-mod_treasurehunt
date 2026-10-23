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
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;
require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');

if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_heading('treasurehuntintro', '', get_string('configintro', 'treasurehunt')));
        $settings->add(new admin_setting_heading(
            'treasurehuntgamemapsheading',
            get_string('gamemapsheading', 'treasurehunt'),
            ''
        ));
        // Available game player styles.
        $styles = treasurehunt_get_installedplayerstyles();
        $settings->add(new admin_setting_configmultiselect(
            'mod_treasurehunt/availableplayerstyles',
            get_string('availableplayerstyles', 'treasurehunt'),
            '',
            array_keys($styles), // All enabled.
            $styles
        ));
        // Default game player style.
        $settings->add(new admin_setting_configselect(
            'mod_treasurehunt/defaultplayerstyle',
            get_string('defaultplayerstyle', 'treasurehunt'),
            get_string('playerstyle_help', 'treasurehunt'),
            TREASUREHUNT_PLAYERBOOTSTRAP,
            treasurehunt_get_installedplayerstyles()
        ));
        $basemaps = treasurehunt_get_basemap_pair_options();
        $defaultbasemappairs = [];
    foreach ($basemaps as $playermaps) {
            $defaultbasemappairs = array_merge($defaultbasemappairs, array_keys($playermaps));
    }
        $settings->add(new admin_setting_configmultiselect(
            'mod_treasurehunt/availablebasemaps',
            get_string('availablebasemaps', 'treasurehunt'),
            get_string('availablebasemaps_desc', 'treasurehunt'),
            $defaultbasemappairs,
            $basemaps
        ));
        $mapboxtoken = new admin_setting_configpasswordunmask(
            'mod_treasurehunt/mapboxaccesstoken',
            get_string('mapboxaccesstoken', 'treasurehunt'),
            get_string('mapboxaccesstoken_desc', 'treasurehunt'),
            ''
        );
        $mapboxtoken->paramtype = PARAM_RAW_TRIMMED;
        $settings->add($mapboxtoken);
        $bingtoken = new admin_setting_configpasswordunmask(
            'mod_treasurehunt/bingmapskey',
            get_string('bingmapskey', 'treasurehunt'),
            get_string('bingmapskey_desc', 'treasurehunt'),
            ''
        );
        $bingtoken->paramtype = PARAM_RAW_TRIMMED;
        $settings->add($bingtoken);
        $googletoken = new admin_setting_configpasswordunmask(
            'mod_treasurehunt/googlemapstileapikey',
            get_string('googlemapstileapikey', 'treasurehunt'),
            get_string('googlemapstileapikey_desc', 'treasurehunt'),
            ''
        );
        $googletoken->paramtype = PARAM_RAW_TRIMMED;
        $settings->add($googletoken);
        $stadiatoken = new admin_setting_configpasswordunmask(
            'mod_treasurehunt/stadiamapsapikey',
            get_string('stadiamapsapikey', 'treasurehunt'),
            get_string('stadiamapsapikey_desc', 'treasurehunt'),
            ''
        );
        $stadiatoken->paramtype = PARAM_RAW_TRIMMED;
        $settings->add($stadiatoken);
        $settings->add(new admin_setting_configcheckbox(
            'mod_treasurehunt/stadiamapsdomainauth',
            get_string('stadiamapsdomainauth', 'treasurehunt'),
            get_string('stadiamapsdomainauth_desc', 'treasurehunt'),
            0
        ));
        $settings->add(new admin_setting_heading(
            'treasurehuntappearanceheading',
            get_string('appearance', 'treasurehunt'),
            ''
        ));
        $settings->add(new admin_setting_configmultiselect(
            'mod_treasurehunt/allowedstageanimations',
            get_string('allowedstageanimations', 'treasurehunt'),
            get_string('allowedstageanimations_desc', 'treasurehunt'),
            ['chest', 'safe'],
            [
                'chest' => get_string('stageanimationchest', 'treasurehunt'),
                'safe' => get_string('stageanimationsafe', 'treasurehunt'),
            ]
        ));
        $settings->add(new admin_setting_heading(
            'treasurehuntscoringheading',
            get_string('scoringsettingsheading', 'treasurehunt'),
            ''
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
        $settings->add(new admin_setting_heading(
            'treasurehuntopendataheading',
            get_string('opendatasettingsheading', 'treasurehunt'),
            ''
        ));
        $settings->add(new admin_setting_configpasswordunmask(
            'mod_treasurehunt/europeanaapikey',
            get_string('opendataeuropeanaapikey', 'treasurehunt'),
            get_string('opendataeuropeanaapikey_desc', 'treasurehunt'),
            ''
        ));
}
