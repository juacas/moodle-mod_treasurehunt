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
 * Tests for the live participant progress service.
 *
 * @package mod_treasurehunt
 * @author Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @copyright 2026 Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_treasurehunt;

use mod_treasurehunt\external\participant_progress;

/**
 * Check isolation, team grouping and stage-state mapping.
 *
 * @covers \mod_treasurehunt\external\participant_progress
 */
final class participant_progress_test extends \advanced_testcase {
    /** @var \stdClass */
    private $course;
    /** @var \stdClass */
    private $hunt;
    /** @var \context_module */
    private $context;

    /**
     * Clear the cross-request progress snapshot before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        \cache::make('mod_treasurehunt', 'participantprogress')->purge();
    }

    /**
     * Set up a hunt with one valid two-stage road.
     *
     * @param bool $groupmode Whether this is a team hunt.
     * @param int $groupid Road group assignment.
     * @param int $groupingid Road grouping assignment.
     * @return array Road and stage ids.
     */
    private function create_hunt(
        bool $groupmode = false,
        int $groupid = 0,
        int $groupingid = 0,
        ?\stdClass $course = null
    ): array {
        global $DB;
        $this->course = $course ?? $this->getDataGenerator()->create_course();
        $this->hunt = (object)[
            'course' => $this->course->id,
            'name' => 'Progress strip test',
            'intro' => '',
            'playwithoutmoving' => 1,
            'tracking' => 0,
            'showboard' => 1,
            'groupmode' => (int)$groupmode,
            'allowattemptsfromdate' => 0,
            'cutoffdate' => 0,
        ];
        $this->hunt->id = $DB->insert_record('treasurehunt', $this->hunt);
        $moduleid = $DB->get_field('modules', 'id', ['name' => 'treasurehunt'], MUST_EXIST);
        $cmid = $DB->insert_record('course_modules', (object)[
            'course' => $this->course->id,
            'module' => $moduleid,
            'instance' => $this->hunt->id,
            'added' => time(),
            'visible' => 1,
        ]);
        course_add_cm_to_section($this->course, $cmid, 0, null, 'treasurehunt');
        $this->context = \context_module::instance($cmid);
        $roadid = $DB->insert_record('treasurehunt_roads', (object)[
            'treasurehuntid' => $this->hunt->id,
            'name' => 'Test road',
            'groupid' => $groupid,
            'groupingid' => $groupingid,
            'validated' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $stageids = [];
        foreach ([1, 2] as $position) {
            $stageids[] = $DB->insert_record('treasurehunt_stages', (object)[
                'roadid' => $roadid,
                'name' => 'Stage ' . $position,
                'position' => $position,
                'cluetext' => '',
                'clueforstage' => '',
                'questiontext' => '',
                'geom' => 'MULTIPOLYGON (((0 0, 0 1, 1 1, 1 0, 0 0)))',
            ]);
        }
        return [$roadid, $stageids];
    }

    /**
     * A failed location is red, and one user's progress is not assigned to another participant.
     */
    public function test_individual_states_are_scoped_to_each_user(): void {
        global $DB;
        $this->resetAfterTest();
        [$roadid, $stages] = $this->create_hunt();
        $first = $this->getDataGenerator()->create_and_enrol($this->course);
        $second = $this->getDataGenerator()->create_and_enrol($this->course);
        $success = (object)[
            'stageid' => $stages[0], 'timecreated' => time(), 'userid' => $first->id, 'groupid' => 0,
            'success' => 1, 'penalty' => 0, 'type' => 'location', 'questionsolved' => 1,
            'activitysolved' => 1, 'geometrysolved' => 1, 'location' => 'POINT (0.5 0.5)',
        ];
        $DB->insert_record('treasurehunt_attempts', $success);
        $failure = clone $success;
        $failure->userid = $second->id;
        $failure->success = 0;
        $failure->penalty = 1;
        $failure->geometrysolved = 0;
        $DB->insert_record('treasurehunt_attempts', $failure);

        $this->setUser($first);
        $response = participant_progress::execute($this->hunt->id);
        \core_external\external_api::clean_returnvalue(participant_progress::execute_returns(), $response);
        $this->assertTrue($response['enabled']);
        $this->assertCount(2, $response['participants']);
        $byid = [];
        foreach ($response['participants'] as $participant) {
            $byid[$participant['id']] = $participant;
        }
        $this->assertSame('passed', $byid['u' . $first->id . 'r' . $roadid]['state']);
        $this->assertSame(2, $byid['u' . $first->id . 'r' . $roadid]['position']);
        $this->assertTrue($byid['u' . $first->id . 'r' . $roadid]['iscurrent']);
        $this->assertSame('failed', $byid['u' . $second->id . 'r' . $roadid]['state']);
        $this->assertFalse($byid['u' . $second->id . 'r' . $roadid]['iscurrent']);
    }

    /**
     * Teammates share one colored progress marker for their assigned grouping.
     */
    public function test_team_mode_returns_one_marker_per_team(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $grouping = $this->getDataGenerator()->create_grouping(['courseid' => $course->id, 'name' => 'Treasure teams']);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Blue Wolves']);
        groups_assign_grouping($grouping->id, $group->id);
        [$roadid] = $this->create_hunt(true, 0, $grouping->id, $course);
        $first = $this->getDataGenerator()->create_and_enrol($this->course);
        $second = $this->getDataGenerator()->create_and_enrol($this->course);
        groups_add_member($group->id, $first->id);
        groups_add_member($group->id, $second->id);
        $this->setUser($first);

        $response = participant_progress::execute($this->hunt->id);
        \core_external\external_api::clean_returnvalue(participant_progress::execute_returns(), $response);
        $this->assertTrue($response['groupmode']);
        $this->assertCount(1, $response['participants']);
        $this->assertSame('Blue Wolves', $response['participants'][0]['name']);
        $this->assertSame('pending', $response['participants'][0]['state']);
        $this->assertSame('g' . $group->id . 'r' . $roadid, $response['participants'][0]['id']);
        $this->assertNotSame('', $response['participants'][0]['teamcolor']);
        $this->assertTrue($response['participants'][0]['iscurrent']);
    }

    /**
     * Activity summaries can show road progress when the viewing user is not assigned to a road.
     */
    public function test_road_summary_allows_unassigned_viewer(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group([
            'courseid' => $course->id,
            'name' => 'Assigned players',
        ]);
        [$roadid] = $this->create_hunt(false, $group->id, 0, $course);
        $assigned = $this->getDataGenerator()->create_and_enrol($course);
        groups_add_member($group->id, $assigned->id);
        $viewer = $this->getDataGenerator()->create_and_enrol($course);
        $this->setUser($viewer);

        $response = participant_progress::execute($this->hunt->id, 0, $roadid);
        \core_external\external_api::clean_returnvalue(participant_progress::execute_returns(), $response);
        $this->assertCount(1, $response['participants']);
        $this->assertSame($roadid, $response['participants'][0]['roadid']);
        $this->assertFalse($response['participants'][0]['iscurrent']);
    }

    /**
     * A manager's road preview shows the participants assigned to that road.
     */
    public function test_manager_preview_shows_selected_road_progress(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group([
            'courseid' => $course->id,
            'name' => 'Preview players',
        ]);
        [$roadid] = $this->create_hunt(false, $group->id, 0, $course);
        $participant = $this->getDataGenerator()->create_and_enrol($course);
        groups_add_member($group->id, $participant->id);
        $this->setAdminUser();

        $response = participant_progress::execute($this->hunt->id, $roadid, $roadid);
        \core_external\external_api::clean_returnvalue(participant_progress::execute_returns(), $response);
        $this->assertTrue($response['enabled']);
        $this->assertCount(1, $response['participants']);
        $this->assertSame($roadid, $response['participants'][0]['roadid']);
        $this->assertFalse($response['participants'][0]['iscurrent']);
    }

    /**
     * A student cannot use the teacher preview parameter to inspect other players.
     */
    public function test_student_cannot_request_preview_road_progress(): void {
        $this->resetAfterTest();
        [$roadid] = $this->create_hunt();
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);

        participant_progress::execute($this->hunt->id, $roadid, $roadid);
    }
}
