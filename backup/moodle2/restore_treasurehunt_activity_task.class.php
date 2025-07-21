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
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/treasurehunt/backup/moodle2/restore_treasurehunt_stepslib.php');
/**
 * Restore definitions.
 */
class restore_treasurehunt_activity_task extends restore_activity_task {
    /**
     * Define (add) particular settings this activity can have.
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define (add) particular steps this activity can have.
     */
    protected function define_my_steps() {
        // We have just one structure step here.
        $this->add_step(new restore_treasurehunt_activity_structure_step('treasurehunt_structure', 'treasurehunt.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    public static function define_decode_contents() {
        $contents = [];

        $contents[] = new restore_decode_content('treasurehunt', ['intro'], 'treasurehunt');
        $contents[] = new restore_decode_content(
            'treasurehunt_stages',
            ['cluetext', 'questiontext'],
            'treasurehunt_stage'
        );
        $contents[] = new restore_decode_content('treasurehunt_answers', ['answertext'], 'treasurehunt_answer');
        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    public static function define_decode_rules() {
        $rules = [];

        $rules[] = new restore_decode_rule('TREASUREHUNTVIEWBYID', '/mod/treasurehunt/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('TREASUREHUNTINDEX', '/mod/treasurehunt/index.php?id=$1', 'course');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * treasurehunt logs. It must return one array
     * of {@link restore_log_rule} objects
     */
    public static function define_restore_log_rules() {
        $rules = [];

        $rules[] = new restore_log_rule('treasurehunt', 'add', 'view.php?id={course_module}', '{treasurehunt}');
        $rules[] = new restore_log_rule('treasurehunt', 'update', 'view.php?id={course_module}', '{treasurehunt}');
        $rules[] = new restore_log_rule('treasurehunt', 'view', 'view.php?id={course_module}', '{treasurehunt}');
        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * course logs. It must return one array
     * of {@link restore_log_rule} objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     */
    public static function define_restore_log_rules_for_course() {
        $rules = [];
        $rules[] = new restore_log_rule('treasurehunt', 'view all', 'index.php?id={course}', null);
        return $rules;
    }
}
