<?php

// TODO: Cambiar documentación/comentarios

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
 * Mobile output class for Choice group
 *
 * @package    mod_choicegroup
 * @copyright  2018 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_treasurehunt\output;

require_once $CFG->dirroot . '/mod/treasurehunt/locallib.php';


defined('MOODLE_INTERNAL') || die();

use context_module;
use completion_info;
use core\session\exception;

/**
 * Mobile output class for Choice group
 *
 * @copyright  2018 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile
{

    /**
     * Returns shared (global) templates and information for the mobile app feature.
     *
     * @param array $args Arguments (empty)
     * @return array Array with information required by app
     */
    public static function mobile_treasurehunt_init(array $args): array
    {
        global $CFG;
        return [
            'templates' => [],
            'javascript' => file_get_contents($CFG->dirroot . '/mod/treasurehunt/mobile/js/mobile_init.js'),
            'otherdata' => '',
            'files' => []
        ];
    }

    /**
     * Returns the choice group course view for the mobile app.
     * @param  array $args Arguments from tool_mobile_get_content WS
     *
     * @return array HTML, javascript and otherdata
     */
    public static function mobile_treasurehunt_view($args)
    {
        global  $CFG, $OUTPUT, $USER, $DB, $PAGE;

        $args = (object) $args;

        $cm = get_coursemodule_from_id('treasurehunt', $args->cmid);
        $course = $DB->get_record('course', array('id' => $cm->course));
        $treasurehunt = $DB->get_record('treasurehunt', array('id' => $cm->instance), '*', MUST_EXIST);


        // Capabilities check.
        require_login($args->courseid, false, $cm, true, true);
        $context = context_module::instance($cm->id);
        require_capability('mod/treasurehunt:view', $context);

        $event = \mod_treasurehunt\event\course_module_viewed::create(
            array(
                'objectid' => $PAGE->cm->instance,
                'context' => $PAGE->context,
            )
        );
        $event->add_record_snapshot('course', $PAGE->course);
        $event->add_record_snapshot($PAGE->cm->modname, $treasurehunt);
        $event->trigger();

        $completion = new completion_info($course);
        $completion->set_module_viewed($cm);

        // Conditions to show the intro can change to look for own settings or whatever.
        if (treasurehunt_view_intro($treasurehunt)) {
            $treasurehunt->intro = format_module_intro('treasurehunt', $treasurehunt, $cm->id);
        } else {
            $treasurehunt->intro = '';
        }

        // User/group historical attempts
        $playpermission = has_capability('mod/treasurehunt:play', $context, null, false);
        $timenow = time();
        $username = '';
        $roadfinished = false;
        $attempts = [];
        $exception = '';
        if ($playpermission && $timenow > $treasurehunt->allowattemptsfromdate) {
            try {
                $params = treasurehunt_get_user_group_and_road($USER->id, $treasurehunt, $cm->id, false, null);
                $roadfinished = treasurehunt_check_if_user_has_finished($USER->id, $params->groupid, $params->roadid);
                $attempts = treasurehunt_get_user_attempt_history($params->groupid, $USER->id, $params->roadid);
                if ($params->groupid) {
                    $username = groups_get_group_name($params->groupid);
                } else {
                    $username = treasurehunt_get_user_fullname_from_id($USER->id);
                }
            } catch (Exception $e) {
                $exception = $e->getMessage();
            }
        }

        // Grade Method
        $grademethod = treasurehunt_get_grading_options()[$treasurehunt->grademethod];

        // Progress users
        $viewpermission = has_capability('mod/treasurehunt:viewusershistoricalattempts', $context);
        $managepermission = has_capability('mod/treasurehunt:managetreasurehunt', $context);
        list(
            $roads, $duplicategroupsingroupings, $duplicateusersingroups,
            $unassignedusers, $availablegroups
        ) = treasurehunt_get_list_participants_and_attempts_in_roads($cm, $course->id, $context);

        $usersprogress = array(
            'roads' => $roads,
            'duplicategroupsingroupings' => $duplicategroupsingroupings,
            'duplicateusersingroups' => $duplicateusersingroups,
            'unassignedusers' => $unassignedusers,
            'availablegroups' => $availablegroups
        );

        $data = array(
            'cmid' => $cm->id,
            'courseid' => $args->courseid,
            'treasurehunt' => $treasurehunt
        );

        return array(
            'templates' => array(
                array(
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_treasurehunt/mobile_view_page', $data),
                ),
            ),
            'javascript' => file_get_contents($CFG->dirroot . '/mod/treasurehunt/mobile/js/mobile_view.js'),
            // 'javascript' => "",
            'otherdata' => array(
                'timenow' => $timenow,
                'grademethod' => $grademethod,
                'groupmode' => boolval($treasurehunt->groupmode),
                'roadfinished' => $roadfinished,
                'attempts' => json_encode($attempts), // Cannot put arrays in otherdata.
                'username' => $username,
                'playpermission' => $playpermission,
                'viewpermission' => $viewpermission,
                'managepermission' => $managepermission,
                'exception' => $exception,
                'usersprogress' => json_encode(
                    $usersprogress
                )
            ),
        );
    }

    /**
     * Returns the play view for the mobile app.
     * @param  array $args Arguments from tool_mobile_get_content WS
     *
     * @return array       HTML, javascript and otherdata
     */
    public static function mobile_treasurehunt_play($args)
    {
        global  $CFG, $OUTPUT, $DB, $USER;

        $args = (object) $args;

        $cm = get_coursemodule_from_id('treasurehunt', $args->cmid);
        $treasurehunt = $DB->get_record('treasurehunt', array('id' => $cm->instance), '*', MUST_EXIST);

        // Capabilities check.
        require_login($args->courseid, false, $cm, true, true);
        $context = context_module::instance($cm->id);
        require_capability('mod/treasurehunt:play', $context);

        // Get last timestamp.
        $user = treasurehunt_get_user_group_and_road($USER->id, $treasurehunt, $cm->id);
        list($lastattempttimestamp, $lastroadtimestamp) = treasurehunt_get_last_timestamps($USER->id, $user->groupid, $user->roadid);

        $playconfig = array(
            'treasurehuntid'=> $treasurehunt->id,
            'playwithoutmoving'=> boolval($treasurehunt->playwithoutmoving),
            'groupmode' => boolval($treasurehunt->groupmode),
            'lastattempttimestamp' => $lastattempttimestamp,
            'lastroadtimestamp' => $lastroadtimestamp,
            'tracking' => boolval($treasurehunt->tracking),
            'gameupdatetime' => treasurehunt_get_setting_game_update_time() * 1000,
            'custommapping' => json_encode(treasurehunt_get_custommappingconfig($treasurehunt, $context))
        );

        $data = array(
            'cmid' => $cm->id,
            'courseid' => $args->courseid,
            'treasurehunt' => $treasurehunt,
            'options' => array(),
        );

        return array(
            'templates' => array(
                array(
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_treasurehunt/mobile_play_page', $data),
                ),
            ),
            'javascript' => file_get_contents($CFG->dirroot . '/mod/treasurehunt/mobile/js/mobile_play.js'),
            'otherdata' => array('playconfig' => json_encode($playconfig))
        );
    }

    /**
     * Returns the search location view for the mobile app.
     * @param  array $args Arguments from tool_mobile_get_content WS
     *
     * @return array       HTML, javascript and otherdata
     */
    public static function mobile_treasurehunt_play_search($args)
    {
        global  $CFG, $OUTPUT;

        $args = (object) $args;

        return array(
            'templates' => array(
                array(
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_treasurehunt/mobile_play_search_page', array()),
                ),
            ),
            'javascript' => file_get_contents($CFG->dirroot . '/mod/treasurehunt/mobile/js/mobile_play_search.js'),
            'otherdata' => array(),
        );
    }

    /**
     * Returns the layers view for the mobile app.
     * @param  array $args Arguments from tool_mobile_get_content WS
     *
     * @return array       HTML, javascript and otherdata
     */
    public static function mobile_treasurehunt_play_layers($args)
    {
        global  $CFG, $OUTPUT;

        $args = (object) $args;

        return array(
            'templates' => array(
                array(
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_treasurehunt/mobile_play_layers_page', array()),
                ),
            ),
            'javascript' => file_get_contents($CFG->dirroot . '/mod/treasurehunt/mobile/js/mobile_play_layers.js'),
            'otherdata' => array(),
        );
    }

    /**
     * Returns the clue view for the mobile app.
     * @param  array $args Arguments from tool_mobile_get_content WS
     *
     * @return array       HTML, javascript and otherdata
     */
    public static function mobile_treasurehunt_play_clue($args)
    {
        global  $CFG, $OUTPUT;

        $args = (object) $args;

        return array(
            'templates' => array(
                array(
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_treasurehunt/mobile_play_clue_page', array()),
                ),
            ),
            'javascript' => '',
            'otherdata' => array(),
        );
    }

    /**
     * Returns the question view for the mobile app.
     * @param  array $args Arguments from tool_mobile_get_content WS
     *
     * @return array       HTML, javascript and otherdata
     */
    public static function mobile_treasurehunt_play_question($args)
    {
        global  $CFG, $OUTPUT;

        $args = (object) $args;

        return array(
            'templates' => array(
                array(
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_treasurehunt/mobile_play_question_page', array()),
                ),
            ),
            'javascript' => file_get_contents($CFG->dirroot . '/mod/treasurehunt/mobile/js/mobile_play_question.js'),
            'otherdata' => array(),
        );
    }
}
