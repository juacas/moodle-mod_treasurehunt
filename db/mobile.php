<?php
// TODO: Cambiar documentación/comentarios

// This file is part of the Choice group module for Moodle - http://moodle.org/
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
 * Choice group module capability definition
 *
 * @package   mod_choicegroup
 * @copyright 2018 Sara Arjona <sara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$addons = array(
    "mod_treasurehunt" => array(
        "handlers" => array( // Different places where the add-on will display content.
            'coursetreasurehunt' => array( // Handler unique name (can be anything)
                'displaydata' => array(
                    'title' => 'pluginname',
                    'icon' => $CFG->wwwroot . '/mod/treasurehunt/pix/icon.svg',
                    'class' => '',
                ),
                'delegate' => 'CoreCourseModuleDelegate', // Delegate (where to display the link to the add-on)
                'method' => 'mobile_treasurehunt_view', // Main function in \mod_choicegroup\output\mobile
                'init' => 'mobile_treasurehunt_init',
                'offlinefunctions' => array(
                    'mobile_course_view' => array(),
                ), // Function needs caching for offline.
                'styles' => array(
                    'url' => $CFG->wwwroot . '/mod/treasurehunt/mobile/mobile_css.css',
                    'version' => '2020083000',
                ),
                'displayrefresh' => true,
                'displayprefetch' => false
            ),
        ),
        'lang' => array(
            array('activitytoendwarning', 'treasurehunt'),
            array('aerialview', 'treasurehunt'),
            array('answerwarning', 'treasurehunt'),
            array('attempthistory', 'treasurehunt'),
            array('autolocate_tour', 'treasurehunt'),
            array('baselayers', 'treasurehunt'),
            array('cancel', 'treasurehunt'),
            array('clue', 'treasurehunt'),
            array('continue', 'treasurehunt'),
            array('discoveredlocation', 'treasurehunt'),
            array('donetutorial', 'treasurehunt'),
            array('error', 'treasurehunt'),
            array('failedlocation', 'treasurehunt'),
            array('findplace', 'treasurehunt'),
            array('gamemode', 'treasurehunt'),
            array('geolocation_needed', 'treasurehunt'),
            array('geolocation_needed_title', 'treasurehunt'),
            array('gradefromabsolutetime', 'treasurehunt'),
            array('gradefromposition', 'treasurehunt'),
            array('gradefromstages', 'treasurehunt'),
            array('gradefromtime', 'treasurehunt'),
            array('grademethod', 'treasurehunt'),
            array('grademethod_help', 'treasurehunt'),
            array('group', 'treasurehunt'),
            array('groupmode', 'treasurehunt'),
            array('groups', 'treasurehunt'),
            array('huntcompleted', 'treasurehunt'),
            array('invalroadid', 'treasurehunt'),
            array('lastsuccessfulstage_tour', 'treasurehunt'),
            array('layers', 'treasurehunt'),
            array('mapplaymobile_tour', 'treasurehunt'),
            array('movingplay', 'treasurehunt'),
            array('nextstep', 'treasurehunt'),
            array('noanswerselected', 'treasurehunt'),
            array('noattempts', 'treasurehunt'),
            array('noattempts', 'treasurehunt'),
            array('nogroupassigned', 'treasurehunt'),
            array('nomarksmobile', 'treasurehunt'),
            array('noresults', 'treasurehunt'),
            array('noroads', 'treasurehunt'),
            array('nouserassigned', 'treasurehunt'),
            array('nousersprogress', 'treasurehunt'),
            array('overlaylayers', 'treasurehunt'),
            array('pegmanlabel', 'treasurehunt'),
            array('play', 'treasurehunt'),
            array('playend_tour', 'treasurehunt'),
            array('playwithoutmoving', 'treasurehunt'),
            array('prevstep', 'treasurehunt'),
            array('qrreaded', 'treasurehunt'),
            array('question', 'treasurehunt'),
            array('reviewofplay', 'treasurehunt'),
            array('roadended', 'treasurehunt'),
            array('roadview', 'treasurehunt'),
            array('search', 'treasurehunt'),
            array('searching', 'treasurehunt'),
            array('send', 'treasurehunt'),
            array('sendlocationcontent', 'treasurehunt'),
            array('sendlocationtitle', 'treasurehunt'),
            array('showclue', 'treasurehunt'),
            array('skiptutorial', 'treasurehunt'),
            array('stage', 'treasurehunt'),
            array('stageclue', 'treasurehunt'),
            array('stagename', 'treasurehunt'),
            array('stageovercome', 'treasurehunt'),
            array('stages', 'treasurehunt'),
            array('startfromhere', 'treasurehunt'),
            array('timeexceeded', 'treasurehunt'),
            array('totalprogress', 'treasurehunt'),
            array('totaltime', 'treasurehunt'),
            array('trackviewer', 'treasurehunt'),
            array('treasurehuntclosed', 'treasurehunt'),
            array('treasurehuntcloseson', 'treasurehunt'),
            array('treasurehuntnotavailable', 'treasurehunt'),
            array('treasurehuntopenedon', 'treasurehunt'),
            array('updates', 'treasurehunt'),
            array('user', 'treasurehunt'),
            array('userattempthistory', 'treasurehunt'),
            array('usersprogress', 'treasurehunt'),
            array('usersprogress_help', 'treasurehunt'),
            array('validatelocation_tour', 'treasurehunt'),
            array('warnusersgroup', 'treasurehunt'),
            array('warnusersgrouping', 'treasurehunt'),
            array('warnusersoutside', 'treasurehunt'),
            array('welcome_play_tour', 'treasurehunt')
        )
    ),
);
