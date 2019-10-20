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

defined('MOODLE_INTERNAL') || die();

use context_module;
use completion_info;

/**
 * Mobile output class for Choice group
 *
 * @copyright  2018 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile
{

    /**
     * Returns the choice group course view for the mobile app.
     * @param  array $args Arguments from tool_mobile_get_content WS
     *
     * @return array HTML, javascript and otherdata
     */
    public static function mobile_course_view($args)
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


        // // Check if the activity is open.
        // $timenow = time();

        // if (!empty($choicegroup->timeopen) && $choicegroup->timeopen > $timenow) {
        //     $choicegroup->open = false;
        //     $choicegroup->message = get_string("notopenyet", "choicegroup", userdate($choicegroup->timeopen));
        // } else {
        //     $choicegroup->open = true;
        // }
        // if (!empty($choicegroup->timeclose) && $timenow > $choicegroup->timeclose) {
        //     $choicegroup->expired = true;
        //     $choicegroup->message = get_string("expired", "choicegroup", userdate($choicegroup->timeclose));
        // } else {
        //     $choicegroup->expired = false;
        // }

        // // The user has made her choice and updates are not allowed or choicegroup is not open.
        // $choicegroup->answergiven = choicegroup_get_user_answer($choicegroup, $USER->id);
        // $choicegroup->alloptionsdisabled = (!$choicegroup->open || $choicegroup->expired
        //     || ($choicegroup->answergiven && !$choicegroup->allowupdate)
        //     || !is_enrolled($context, null, 'mod/choicegroup:choose'));

        // // Get choicegroup options from external.
        // try {
        //     $returnedoptions = mod_choicegroup_external::get_choicegroup_options(
        //         $cm->instance,
        //         $USER->id,
        //         $choicegroup->alloptionsdisabled
        //     );
        //     $options = array_values($returnedoptions['options']); // Make it mustache compatible.
        //     $responses = array();
        //     foreach ($options as $option) {
        //         if ($choicegroup->multipleenrollmentspossible) {
        //             $responses['responses_' . $option['id']] = $option['checked'];
        //         } else if ($option['checked']) {
        //             $responses['responses'] = $option['id'];
        //         }
        //     }
        // } catch (Exception $e) {
        //     $options = array();
        // }

        // // Format name and intro.
        // $choicegroup->name = format_string($choicegroup->name);
        // list($choicegroup->intro, $choicegroup->introformat) = external_format_text(
        //     $choicegroup->intro,
        //     $choicegroup->introformat,
        //     $context->id,
        //     'mod_choicegroup',
        //     'intro'
        // );
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
                    'html' => $OUTPUT->render_from_template('mod_treasurehunt/mobile_view_page', $data),
                ),
            ),
            'javascript' => file_get_contents($CFG->dirroot . '/mod/treasurehunt/appjs/mobile-play.js'),
            // 'javascript' => "",
            'otherdata' => array(),
        );
    }
}
