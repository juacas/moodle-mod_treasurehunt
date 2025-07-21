<?php
// This file is part of Treasurehunt for Moodle - http://moodle.org/
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
 * Treasure Hunt mobile module capability definition
 *
 * @package   mod_treasurehunt
 * @copyright 2020 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http:// www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;
$addons = [
    "mod_treasurehunt" => [
        "handlers" => [ // Different places where the add-on will display content.
            'coursetreasurehunt' => [ // Handler unique name (can be anything)
                'displaydata' => [
                    'title' => 'Treasure Hunt',
                    'icon' => $CFG->wwwroot . '/mod/treasurehunt/pix/icon.svg',
                    'class' => '',
                ],
                'delegate' => 'CoreCourseModuleDelegate', // Delegate (where to display the link to the add-on)
                'method' => 'mobile_treasurehunt_view', // Main function in \mod\treasurehunt\classes\output\mobile.php
                'init' => 'mobile_treasurehunt_init', // Init function in \mod\treasurehunt\classes\output\mobile.php
                'offlinefunctions' => [
                    'mobile_treasurehunt_view' => [],
                ], // Function needs caching for offline.
                'styles' => [
                    'url' => $CFG->wwwroot . '/mod/treasurehunt/mobile/mobile_css.css',
                    'version' => '2020083000',
                ],
                'displayrefresh' => true,
                'displayprefetch' => false,
            ],
        ],
        'lang' => [
            ['activitytoendwarning', 'treasurehunt'],
            ['aerialview', 'treasurehunt'],
            ['answerwarning', 'treasurehunt'],
            ['attempthistory', 'treasurehunt'],
            ['autolocate_tour', 'treasurehunt'],
            ['baselayers', 'treasurehunt'],
            ['cancel', 'treasurehunt'],
            ['clue', 'treasurehunt'],
            ['continue', 'treasurehunt'],
            ['discoveredlocation', 'treasurehunt'],
            ['donetutorial', 'treasurehunt'],
            ['error', 'treasurehunt'],
            ['failedlocation', 'treasurehunt'],
            ['findplace', 'treasurehunt'],
            ['gamemode', 'treasurehunt'],
            ['geolocation_needed', 'treasurehunt'],
            ['geolocation_needed_title', 'treasurehunt'],
            ['gradefromabsolutetime', 'treasurehunt'],
            ['gradefromposition', 'treasurehunt'],
            ['gradefromstages', 'treasurehunt'],
            ['gradefromtime', 'treasurehunt'],
            ['grademethod', 'treasurehunt'],
            ['grademethod_help', 'treasurehunt'],
            ['group', 'treasurehunt'],
            ['groupmode', 'treasurehunt'],
            ['groups', 'treasurehunt'],
            ['huntcompleted', 'treasurehunt'],
            ['invalroadid', 'treasurehunt'],
            ['lastsuccessfulstage_tour', 'treasurehunt'],
            ['layers', 'treasurehunt'],
            ['mapplaymobile_tour', 'treasurehunt'],
            ['movingplay', 'treasurehunt'],
            ['nextstep', 'treasurehunt'],
            ['noanswerselected', 'treasurehunt'],
            ['noattempts', 'treasurehunt'],
            ['noattempts', 'treasurehunt'],
            ['nogroupassigned', 'treasurehunt'],
            ['nomarksmobile', 'treasurehunt'],
            ['noresults', 'treasurehunt'],
            ['noroads', 'treasurehunt'],
            ['nouserassigned', 'treasurehunt'],
            ['nousersprogress', 'treasurehunt'],
            ['overlaylayers', 'treasurehunt'],
            ['pegmanlabel', 'treasurehunt'],
            ['play', 'treasurehunt'],
            ['playend_tour', 'treasurehunt'],
            ['playwithoutmoving', 'treasurehunt'],
            ['prevstep', 'treasurehunt'],
            ['qrreaded', 'treasurehunt'],
            ['question', 'treasurehunt'],
            ['reviewofplay', 'treasurehunt'],
            ['roadended', 'treasurehunt'],
            ['roadview', 'treasurehunt'],
            ['search', 'treasurehunt'],
            ['searching', 'treasurehunt'],
            ['send', 'treasurehunt'],
            ['sendlocationcontent', 'treasurehunt'],
            ['sendlocationtitle', 'treasurehunt'],
            ['showclue', 'treasurehunt'],
            ['skiptutorial', 'treasurehunt'],
            ['stage', 'treasurehunt'],
            ['stageclue', 'treasurehunt'],
            ['stagename', 'treasurehunt'],
            ['stageovercome', 'treasurehunt'],
            ['stages', 'treasurehunt'],
            ['startfromhere', 'treasurehunt'],
            ['timeexceeded', 'treasurehunt'],
            ['totalprogress', 'treasurehunt'],
            ['totaltime', 'treasurehunt'],
            ['trackviewer', 'treasurehunt'],
            ['treasurehuntclosed', 'treasurehunt'],
            ['treasurehuntcloseson', 'treasurehunt'],
            ['treasurehuntnotavailable', 'treasurehunt'],
            ['treasurehuntopenedon', 'treasurehunt'],
            ['updates', 'treasurehunt'],
            ['user', 'treasurehunt'],
            ['userattempthistory', 'treasurehunt'],
            ['usersprogress', 'treasurehunt'],
            ['usersprogress_help', 'treasurehunt'],
            ['validatelocation_tour', 'treasurehunt'],
            ['warnusersgroup', 'treasurehunt'],
            ['warnusersgrouping', 'treasurehunt'],
            ['warnusersoutside', 'treasurehunt'],
            ['welcome_play_tour', 'treasurehunt'],
        ],
    ],
];
