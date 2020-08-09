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
                    'version' => '2.0',
                ),
                'displayrefresh' => true,
                'displayprefetch' => false
            ),
        ),
        'lang' => array(
            array('playwithoutmoving', 'treasurehunt'),
            array('movingplay', 'treasurehunt'),
            array('groupmode', 'treasurehunt'),
            array('gamemode', 'treasurehunt'),
            array('groups', 'treasurehunt'),
            array('grademethod', 'treasurehunt'),
            array('gradefromstages', 'treasurehunt'),
            array('gradefromtime', 'treasurehunt'),
            array('gradefromposition', 'treasurehunt'),
            array('gradefromabsolutetime', 'treasurehunt'),
            array('grademethod_help', 'treasurehunt'),
            array('treasurehuntnotavailable', 'treasurehunt'),
            array('treasurehuntclosed', 'treasurehunt'),
            array('treasurehuntopenedon', 'treasurehunt'),
            array('treasurehuntcloseson', 'treasurehunt'),
            array('attempthistory', 'treasurehunt'),
            array('noattempts', 'treasurehunt'),
            array('noroads', 'treasurehunt'),
            array('play', 'treasurehunt'),
            array('reviewofplay', 'treasurehunt'),
            array('warnusersgrouping', 'treasurehunt'),
            array('warnusersgroup', 'treasurehunt'),
            array('warnusersoutside', 'treasurehunt'),
            array('group', 'treasurehunt'),
            array('user', 'treasurehunt'),
            array('totaltime', 'treasurehunt'),
            array('stages', 'treasurehunt'),
            array('nousersprogress', 'treasurehunt'),
            array('nogroupassigned', 'treasurehunt'),
            array('nouserassigned', 'treasurehunt'),
            array('invalroadid', 'treasurehunt'),
            array('geolocation_needed_title', 'treasurehunt'),
            array('geolocation_needed', 'treasurehunt'),
            array('trackviewer', 'treasurehunt'),
            array('usersprogress', 'treasurehunt'),
            array('usersprogress_help', 'treasurehunt'),
            array('stageovercome', 'treasurehunt'),
            array('failedlocation', 'treasurehunt'),
            array('showclue', 'treasurehunt'),
            array('clue', 'treasurehunt'),
            array('stage', 'treasurehunt'),
            array('stagename', 'treasurehunt'),
            array('stageclue', 'treasurehunt'),
            array('question', 'treasurehunt'),
            array('noanswerselected', 'treasurehunt'),
            array('timeexceeded', 'treasurehunt'),
            array('searching', 'treasurehunt'),
            array('continue', 'treasurehunt'),
            array('noattempts', 'treasurehunt'),
            array('aerialview', 'treasurehunt'),
            array('roadview', 'treasurehunt'),
            array('startfromhere', 'treasurehunt'),
            array('nomarks', 'treasurehunt'),
            array('updates', 'treasurehunt'),
            array('activitytoendwarning', 'treasurehunt'),
            array('huntcompleted', 'treasurehunt'),
            array('roadended', 'treasurehunt'),
            array('discoveredlocation', 'treasurehunt'),
            array('answerwarning', 'treasurehunt'),
            array('sendlocationtitle', 'treasurehunt'),
            array('sendlocationcontent', 'treasurehunt'),
            array('cancel', 'treasurehunt'),
            array('send', 'treasurehunt'),
            array('error', 'treasurehunt'),
            array('layers', 'treasurehunt'),
            array('search', 'treasurehunt'),
            array('nextstep', 'treasurehunt'),
            array('prevstep', 'treasurehunt'),
            array('skiptutorial', 'treasurehunt'),
            array('donetutorial', 'treasurehunt'),
            array('welcome_play_tour', 'treasurehunt'),
            array('lastsuccessfulstage_tour', 'treasurehunt'),
            array('mapplay_tour', 'treasurehunt'),
            array('validatelocation_tour', 'treasurehunt'),
            array('autolocate_tour', 'treasurehunt'),
            array('playend_tour', 'treasurehunt'),
        )
    ),
);
