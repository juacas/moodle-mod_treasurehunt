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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Tests for stage editor validation.
 *
 * @package mod_treasurehunt
 * @copyright 2026 Juan Pablo de Castro
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_treasurehunt;

/**
 * Verify that either rich text clue can satisfy the stage requirement.
 *
 * @coversNothing
 */
final class stage_form_test extends \advanced_testcase {
    /**
     * Empty editor markup is rejected, while either text or an image is accepted.
     */
    public function test_at_least_one_clue_has_content(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/treasurehunt/editstage_form.php');

        $form = (new \ReflectionClass(\stage_form::class))->newInstanceWithoutConstructor();
        $data = [
            'cluetext_editor' => ['text' => '<p>&nbsp;</p>'],
            'clueforstage_editor' => ['text' => '<p><br></p>'],
        ];
        $errors = $form->validation($data, []);
        $this->assertArrayHasKey('cluetext_editor', $errors);
        $this->assertArrayHasKey('clueforstage_editor', $errors);

        $data['cluetext_editor']['text'] = '<p>Follow the river</p>';
        $this->assertEmpty($form->validation($data, []));

        $data['cluetext_editor']['text'] = '<p><br></p>';
        $data['clueforstage_editor']['text'] = '<p>Look for the bridge</p>';
        $this->assertEmpty($form->validation($data, []));

        $data['clueforstage_editor']['text'] = '<p><img src="@@PLUGINFILE@@/map.png" alt="Map"></p>';
        $this->assertEmpty($form->validation($data, []));
    }

    /**
     * The editor service exposes the stage setting to both editor clients.
     */
    public function test_editor_summary_includes_out_of_sequence_setting(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $huntid = $DB->insert_record('treasurehunt', (object)[
            'course' => $course->id, 'name' => 'Summary test', 'intro' => '',
        ]);
        $moduleid = $DB->get_field('modules', 'id', ['name' => 'treasurehunt'], MUST_EXIST);
        $cmid = $DB->insert_record('course_modules', (object)[
            'course' => $course->id, 'module' => $moduleid, 'instance' => $huntid,
            'added' => time(), 'visible' => 1,
        ]);
        course_add_cm_to_section($course, $cmid, 0, null, 'treasurehunt');
        $roadid = $DB->insert_record('treasurehunt_roads', (object)[
            'treasurehuntid' => $huntid, 'name' => 'Road',
        ]);
        $DB->insert_record('treasurehunt_stages', (object)[
            'roadid' => $roadid, 'name' => 'Free stage', 'position' => 1,
            'cluetext' => '', 'clueforstage' => 'Find me', 'questiontext' => '',
            'discoveroutofsequence' => 1,
            'geom' => 'MULTIPOLYGON (((0 0, 0 1, 1 1, 1 0, 0 0)))',
        ]);

        $result = \mod_treasurehunt\external\fetch_treasurehunt::execute($huntid);
        \core_external\external_api::clean_returnvalue(
            \mod_treasurehunt\external\fetch_treasurehunt::execute_returns(),
            $result
        );
        $roads = $result['treasurehunt']->roads;
        $road = reset($roads);
        $properties = $road->stages['features'][0]['properties'];
        $this->assertTrue($properties['discoveroutofsequence']);
    }
}
