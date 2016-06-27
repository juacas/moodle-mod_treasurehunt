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

/**
 * Define all the restore steps that will be used by the restore_treasurehunt_activity_task
 *
 * @package   mod_treasurehunt
 * @category  backup
 * @copyright 2015 Your Name <your@email.adress>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
global $CFG;
// Needed for get_geometry_functions();
require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');

/**
 * Structure step to restore one treasurehunt activity
 *
 * @package   mod_treasurehunt
 * @category  backup
 * @copyright 2015 Your Name <your@email.adress>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_treasurehunt_activity_structure_step extends restore_activity_structure_step {

    /**
     * Defines structure of path elements to be processed during the restore
     *
     * @return array of {@link restore_path_element}
     */
    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('treasurehunt', '/activity/treasurehunt');
        $paths[] = new restore_path_element('treasurehunt_road', '/activity/treasurehunt/roads/road');
        $paths[] = new restore_path_element('treasurehunt_riddle', '/activity/treasurehunt/roads/road/riddles/riddle');
        $paths[] = new restore_path_element('treasurehunt_answer', '/activity/treasurehunt/roads/road/riddles/riddle/answers/answer');
        if ($userinfo) {
            $paths[] = new restore_path_element('treasurehunt_attempt', '/activity/treasurehunt/roads/road/riddles/riddle/attempts/attempt');
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process the given restore path element data
     *
     * @param array $data parsed element data
     */
    protected function process_treasurehunt($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();


        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);


        if ($data->grade < 0) {
            // Scale found, get mapping.
            $data->grade = -($this->get_mappingid('scale', abs($data->grade)));
        }

        // Create the treasurehunt instance.
        $newitemid = $DB->insert_record('treasurehunt', $data);
        $this->apply_activity_instance($newitemid);
    }

    protected function process_treasurehunt_road($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->groupid = $this->get_mappingid('group', $data->groupid);
        $data->groupingid = $this->get_mappingid('grouping', $data->groupingid);
        $data->treasurehuntid = $this->get_new_parentid('treasurehunt');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('treasurehunt_roads', $data);
        $this->set_mapping('treasurehunt_road', $oldid, $newitemid);
    }

    protected function process_treasurehunt_riddle($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->roadid = $this->get_new_parentid('treasurehunt_road');
        $data->activitytoend = $this->get_mappingid('course_module', $data->activitytoend);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('treasurehunt_riddles', $data);
        $this->set_mapping('treasurehunt_riddle', $oldid, $newitemid, true);
    }

    protected function process_treasurehunt_answer($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->riddleid = $this->get_new_parentid('treasurehunt_riddle');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('treasurehunt_answers', $data);
        $this->set_mapping('treasurehunt_answer', $oldid, $newitemid, true);
    }

    protected function process_treasurehunt_attempt($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->riddleid = $this->get_new_parentid('treasurehunt_riddle');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->groupid = $this->get_mappingid('group', $data->groupid);

        $newitemid = $DB->insert_record('treasurehunt_attempts', $data);
        $this->set_mapping('treasurehunt_attempt', $oldid, $newitemid);
    }

    /**
     * Post-execution actions
     */
    protected function after_execute() {
        // Add treasurehunt related files
        $this->add_related_files('mod_treasurehunt', 'intro', null);
        $this->add_related_files('mod_treasurehunt', 'description', 'treasurehunt_riddle');
        $this->add_related_files('mod_treasurehunt', 'questiontext', 'treasurehunt_riddle');
        $this->add_related_files('mod_treasurehunt', 'answertext', 'treasurehunt_answer');
    }

}
