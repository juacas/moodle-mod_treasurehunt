<?php
// This file is part of TreasureHunt activity for Moodle
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
 * Define all the backup steps that will be used by the backup_treasurehunt_activity_task
 *
 * @package   mod_treasurehunt
 * @category  backup
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Backup definition.
 */
class backup_treasurehunt_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines the backup structure of the module.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        global $CFG;
        // Get know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define the root element describing the treasurehunt instance.
        $treasurehunt = new backup_nested_element(
            'treasurehunt',
            ['id'],
            [
            'name', 'intro', 'introformat', 'timecreated', 'timemodified', 'playwithoutmoving',
            'groupmode', 'alwaysshowdescription', 'allowattemptsfromdate',
            'cutoffdate', 'grade', 'grademethod', 'gradepenlocation', 'gradepenanswer',
            'tracking', 'custommapconfig', 'completionfinish', 'completionpass',
            ]
        );
        // This file areas haven't itemid.
        $treasurehunt->annotate_files('mod_treasurehunt', 'custombackground', null);

        $roads = new backup_nested_element('roads');

        $road = new backup_nested_element('road', ['id'], [
            'name', 'timecreated', 'timemodified', 'groupid', 'groupingid', 'validated']);

        $stages = new backup_nested_element('stages');

        $stage = new backup_nested_element('stage', ['id'], [
            'name', 'position', 'cluetext', 'cluetextformat', 'cluetexttrust',
            'timecreated', 'timemodified', 'playstagewithoutmoving', 'activitytoend', 'questiontext',
            'questiontextformat', 'questiontexttrust', 'qrtext', 'geom']);

        $answers = new backup_nested_element('answers');

        $answer = new backup_nested_element('answer', ['id'], [
            'answertext', 'answertextformat', 'answertexttrust', 'timecreated',
            'timemodified', 'correct']);

        $attempts = new backup_nested_element('attempts');

        $attempt = new backup_nested_element('attempt', ['id'], [
            'timecreated', 'userid', 'groupid', 'success',
            'penalty', 'type', 'questionsolved', 'activitysolved',
            'geometrysolved', 'location']);
        $tracks = new backup_nested_element('tracks');
        $track = new backup_nested_element('track', ['stageid', 'userid', 'timestamp'], ['location']);

        // Build the tree.
        $treasurehunt->add_child($roads);
        $roads->add_child($road);

        $road->add_child($stages);
        $stages->add_child($stage);

        $stage->add_child($answers);
        $answers->add_child($answer);

        $stage->add_child($attempts);
        $attempts->add_child($attempt);

        $treasurehunt->add_child($tracks);
        $tracks->add_child($track);

        // Define sources.
        $treasurehunt->set_source_table('treasurehunt', ['id' => backup::VAR_ACTIVITYID]);

        $road->set_source_table('treasurehunt_roads', ['treasurehuntid' => backup::VAR_PARENTID], 'id ASC');
        $stage->set_source_table('treasurehunt_stages', ['roadid' => backup::VAR_PARENTID]);
        $answer->set_source_table('treasurehunt_answers', ['stageid' => backup::VAR_PARENTID], 'id ASC');

        // All the rest of elements only happen if we are including user info.
        if ($userinfo) {
            $attempt->set_source_table('treasurehunt_attempts', ['stageid' => backup::VAR_PARENTID]);
            $track->set_source_table('treasurehunt_track', ['treasurehuntid' => backup::VAR_PARENTID]);
        }

        // Define id annotations.
        $road->annotate_ids('group', 'groupid');
        $road->annotate_ids('grouping', 'groupingid');
        $stage->annotate_ids('course_module', 'activitytoend');
        $attempt->annotate_ids('user', 'userid');
        $attempt->annotate_ids('group', 'groupid');

        // Define file annotations.
        $treasurehunt->annotate_files('mod_treasurehunt', 'intro', null);
        $stage->annotate_files('mod_treasurehunt', 'cluetext', 'id');
        $stage->annotate_files('mod_treasurehunt', 'questiontext', 'id');
        $answer->annotate_files('mod_treasurehunt', 'answertext', 'id');

        // Return the root element (treasurehunt), wrapped into standard activity structure.
        return $this->prepare_activity_structure($treasurehunt);
    }
}
