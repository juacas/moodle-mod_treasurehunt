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
 * Player progress service tests.
 *
 * @package mod_treasurehunt
 * @copyright 2026
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_treasurehunt;

use mod_treasurehunt\external\user_progress;
use mod_treasurehunt\external\delete_stage;

/**
 * Exercise the polling contract on a playable road.
 *
 */
final class user_progress_test extends \advanced_testcase {
    /**
     * Load local functions after Moodle has initialized its configuration.
     */
    protected function setUp(): void {
        parent::setUp();
        global $CFG;
        require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');
    }
    /**
     * Create a playable road with two stages.
     *
     * @param int $huntid Activity ID.
     * @param string $name Road name.
     * @param int $groupid Group assigned in individual mode.
     * @param int $groupingid Grouping assigned in team mode.
     * @return array Road ID and stage IDs.
     */
    private function create_road(int $huntid, string $name, int $groupid = 0, int $groupingid = 0): array {
        global $DB;
        $roadid = $DB->insert_record('treasurehunt_roads', (object)[
            'treasurehuntid' => $huntid, 'name' => $name, 'groupid' => $groupid,
            'groupingid' => $groupingid, 'validated' => 1,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $stageids = [];
        foreach ([1, 2] as $position) {
            $stageids[] = $DB->insert_record('treasurehunt_stages', (object)[
                'roadid' => $roadid, 'name' => $name . ' stage ' . $position,
                'position' => $position, 'cluetext' => '', 'clueforstage' => $name . ' clue',
                'questiontext' => '', 'geom' => 'MULTIPOLYGON (((0 0, 0 1, 1 1, 1 0, 0 0)))',
            ]);
        }
        return [$roadid, $stageids];
    }

    /**
     * Record a successful attempt without changing the clock between writes.
     *
     * @param int $stageid Stage ID.
     * @param int $userid User ID.
     * @param int $groupid Team ID, or zero.
     * @param \context_module $context Module context.
     * @param string $type Attempt type.
     * @return int Attempt ID.
     */
    private function complete_stage(
        int $stageid,
        int $userid,
        int $groupid,
        \context_module $context,
        string $type = 'location'
    ): int {
        global $DB;
        $attempt = (object)[
            'stageid' => $stageid, 'userid' => $userid, 'groupid' => $groupid,
            'timecreated' => time(), 'success' => 1, 'penalty' => 0,
            'type' => $type, 'questionsolved' => 1, 'activitysolved' => 1,
            'geometrysolved' => 1, 'location' => 'POINT (0.5 0.5)',
        ];
        treasurehunt_insert_attempt($attempt, $context);
        return $attempt->id;
    }

    /**
     * Individual assignments use separate roads and separate progress cache keys.
     */
    public function test_individual_road_isolation_and_same_second_cursor(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $hunt = $this->create_hunt($course);
        $first = $this->getDataGenerator()->create_and_enrol($course);
        $second = $this->getDataGenerator()->create_and_enrol($course);
        $firstgroup = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $secondgroup = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_add_member($firstgroup->id, $first->id);
        groups_add_member($secondgroup->id, $second->id);
        [$firstroad, $firststages] = $this->create_road($hunt->id, 'Alpha', $firstgroup->id);
        [$secondroad, $secondstages] = $this->create_road($hunt->id, 'Beta', $secondgroup->id);
        $cm = get_coursemodule_from_instance('treasurehunt', $hunt->id);
        $context = \context_module::instance($cm->id);
        $firstassignment = treasurehunt_get_user_group_and_road($first->id, $hunt, $cm->id);
        $secondassignment = treasurehunt_get_user_group_and_road($second->id, $hunt, $cm->id);
        $this->assertEquals($firstroad, $firstassignment->roadid);
        $this->assertEquals($secondroad, $secondassignment->roadid);

        $firstid = $this->complete_stage($firststages[0], $first->id, 0, $context);
        $secondid = $this->complete_stage($firststages[1], $first->id, 0, $context);
        $this->assertGreaterThan($firstid, $secondid);
        $delta = treasurehunt_check_attempts_updates($firstid, 0, $first->id, $firstroad, false);
        $this->assertEquals($secondid, $delta->newattemptid);
        $this->assertTrue($delta->geometrysolved);
        $this->assertCount(1, $delta->strings);
        $this->assertCount(2, treasurehunt_get_road_stage_state($first->id, 0, $firstroad)->completed);
        $this->assertEmpty(treasurehunt_get_road_stage_state($second->id, 0, $secondroad)->completed);
        $this->assertEquals(0, treasurehunt_get_progress_markers($second->id, 0, $secondroad)[0]);
        $this->assertEquals(0, $DB->count_records('treasurehunt_attempts', ['stageid' => $secondstages[0]]));
    }

    /**
     * Teammates share progress, while a team on another road remains isolated.
     */
    public function test_team_progress_is_shared_and_other_road_is_isolated(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $hunt = $this->create_hunt($course);
        $hunt->groupmode = 1;
        $DB->update_record('treasurehunt', $hunt);
        $players = [];
        foreach ([0, 1, 2] as $index) {
            $players[$index] = $this->getDataGenerator()->create_and_enrol($course);
        }
        $groups = [];
        $roads = [];
        $stages = [];
        foreach ([0, 1] as $index) {
            $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
            $grouping = $this->getDataGenerator()->create_grouping(['courseid' => $course->id]);
            $this->getDataGenerator()->create_grouping_group([
                'groupingid' => $grouping->id, 'groupid' => $group->id,
            ]);
            $groups[] = $group;
            [$roads[$index], $stages[$index]] = $this->create_road($hunt->id, 'Team ' . $index, 0, $grouping->id);
        }
        groups_add_member($groups[0]->id, $players[0]->id);
        groups_add_member($groups[0]->id, $players[1]->id);
        groups_add_member($groups[1]->id, $players[2]->id);
        $cm = get_coursemodule_from_instance('treasurehunt', $hunt->id);
        $context = \context_module::instance($cm->id);
        $this->assertEquals($roads[0], treasurehunt_get_user_group_and_road(
            $players[1]->id, $hunt, $cm->id
        )->roadid);
        // Prime the request cache for both teams before an attempt is written.
        $this->assertEmpty(treasurehunt_get_road_stage_state($players[1]->id, $groups[0]->id, $roads[0])->completed);
        $this->assertEmpty(treasurehunt_get_road_stage_state($players[2]->id, $groups[1]->id, $roads[1])->completed);
        $attemptid = $this->complete_stage($stages[0][0], $players[0]->id, $groups[0]->id, $context);
        $this->assertArrayHasKey(
            $stages[0][0],
            treasurehunt_get_road_stage_state($players[1]->id, $groups[0]->id, $roads[0])->completed
        );
        $this->assertEmpty(treasurehunt_get_road_stage_state($players[2]->id, $groups[1]->id, $roads[1])->completed);
        $delta = treasurehunt_check_attempts_updates(0, $groups[0]->id, $players[1]->id, $roads[0], false);
        $this->assertEquals($attemptid, $delta->newattemptid);
        $this->assertTrue($delta->newgeometry);
        $this->assertEquals(0, treasurehunt_get_progress_markers($players[2]->id, $groups[1]->id, $roads[1])[0]);

        $params = [
            'treasurehuntid' => $hunt->id, 'attemptid' => 0, 'roadtimestamp' => 0,
            'playwithoutmoving' => true, 'groupmode' => true, 'initialize' => true,
            'selectedanswerid' => 0, 'qoaremoved' => false,
        ];
        $this->setUser($players[1]);
        $teammateview = user_progress::execute($params);
        \core_external\external_api::clean_returnvalue(user_progress::execute_returns(), $teammateview);
        $this->assertEquals($attemptid, $teammateview['attemptid']);
        $this->assertEquals(2, $teammateview['nextstage']['features'][0]['properties']['stageposition']);
        $this->setUser($players[2]);
        $otherview = user_progress::execute($params);
        \core_external\external_api::clean_returnvalue(user_progress::execute_returns(), $otherview);
        $this->assertEquals(0, $otherview['attemptid']);
        $this->assertEquals($roads[1], $otherview['nextstage']['features'][0]['properties']['roadid']);
        $this->assertEquals(1, $otherview['nextstage']['features'][0]['properties']['stageposition']);
        $qrid = $this->complete_stage($stages[0][1], $players[0]->id, $groups[0]->id, $context, 'qr');
        $qrdelta = treasurehunt_check_attempts_updates(
            $attemptid, $groups[0]->id, $players[1]->id, $roads[0], false
        );
        $this->assertEquals($qrid, $qrdelta->newattemptid);
        $this->assertTrue($qrdelta->newgeometry);
    }

    /**
     * Multiple teams in the same grouping must not collapse into one SQL row.
     */
    public function test_member_of_two_teams_on_one_road_is_rejected(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $hunt = $this->create_hunt($course);
        $hunt->groupmode = 1;
        $DB->update_record('treasurehunt', $hunt);
        $player = $this->getDataGenerator()->create_and_enrol($course);
        $grouping = $this->getDataGenerator()->create_grouping(['courseid' => $course->id]);
        foreach ([1, 2] as $number) {
            $group = $this->getDataGenerator()->create_group([
                'courseid' => $course->id, 'name' => 'Team ' . $number,
            ]);
            $this->getDataGenerator()->create_grouping_group([
                'groupingid' => $grouping->id, 'groupid' => $group->id,
            ]);
            groups_add_member($group->id, $player->id);
        }
        $this->create_road($hunt->id, 'Shared road', 0, $grouping->id);
        $cm = get_coursemodule_from_instance('treasurehunt', $hunt->id);
        $this->expectException(\core\session\exception::class);
        treasurehunt_get_user_group_and_road($player->id, $hunt, $cm->id);
    }

    /**
     * A partial player configuration must still satisfy the external response contract.
     */
    public function test_team_first_poll_accepts_partial_player_configuration(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $hunt = $this->create_hunt($course);
        $hunt->groupmode = 1;
        $hunt->customplayerconfig = '{"showheadinghint":true}';
        $DB->update_record('treasurehunt', $hunt);
        $player = $this->getDataGenerator()->create_and_enrol($course);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $grouping = $this->getDataGenerator()->create_grouping(['courseid' => $course->id]);
        $this->getDataGenerator()->create_grouping_group([
            'groupingid' => $grouping->id, 'groupid' => $group->id,
        ]);
        groups_add_member($group->id, $player->id);
        $this->create_road($hunt->id, 'Partial config', 0, $grouping->id);
        $this->setUser($player);
        $result = user_progress::execute([
            'treasurehuntid' => $hunt->id, 'attemptid' => 0, 'roadtimestamp' => 0,
            'playwithoutmoving' => true, 'groupmode' => true, 'initialize' => true,
            'selectedanswerid' => 0, 'qoaremoved' => false,
        ]);
        $clean = \core_external\external_api::clean_returnvalue(user_progress::execute_returns(), $result);
        $this->assertFalse($clean['playerconfig']['searchpaneldisabled']);
        $this->assertFalse($clean['playerconfig']['localizationbuttondisabled']);
    }

    /**
     * Repeated state reads use MUC; editing a road and writing an attempt invalidate it.
     */
    public function test_state_cache_is_reused_and_invalidated(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $hunt = $this->create_hunt($course);
        [$roadid, $stages] = $this->create_road($hunt->id, 'Cached');
        $player = $this->getDataGenerator()->create_and_enrol($course);
        $cm = get_coursemodule_from_instance('treasurehunt', $hunt->id);
        $context = \context_module::instance($cm->id);
        treasurehunt_get_road_stage_state($player->id, 0, $roadid);
        $reads = $DB->perf_get_reads();
        treasurehunt_get_road_stage_state($player->id, 0, $roadid);
        $this->assertEquals($reads, $DB->perf_get_reads());
        // Simulate a new request: the application snapshot needs only the marker query.
        \cache::make('mod_treasurehunt', 'progressverified')->purge();
        \cache::make('mod_treasurehunt', 'progressmarkers')->purge();
        $reads = $DB->perf_get_reads();
        treasurehunt_get_road_stage_state($player->id, 0, $roadid);
        $this->assertEquals($reads + 1, $DB->perf_get_reads());
        $attemptid = $this->complete_stage($stages[0], $player->id, 0, $context);
        $this->assertCount(1, treasurehunt_get_road_stage_state($player->id, 0, $roadid)->completed);
        $DB->set_field('treasurehunt_stages', 'name', 'Edited', ['id' => $stages[1]]);
        treasurehunt_invalidate_road_state($roadid);
        $state = treasurehunt_get_road_stage_state($player->id, 0, $roadid);
        $this->assertEquals('Edited', $state->stages[1]->name);
        $revision = $DB->get_field('treasurehunt_roads', 'timemodified', ['id' => $roadid]);
        treasurehunt_set_valid_road($roadid);
        $this->assertGreaterThan(
            $revision,
            $DB->get_field('treasurehunt_roads', 'timemodified', ['id' => $roadid])
        );
        treasurehunt_clear_activities($hunt->id);
        $this->assertEmpty(treasurehunt_get_road_stage_state($player->id, 0, $roadid)->completed);
        $reset = treasurehunt_check_attempts_updates($attemptid, 0, $player->id, $roadid, false);
        $this->assertEquals(0, $reset->newattemptid);
        $this->assertTrue($reset->newgeometry);
    }
    /**
     * Create a minimal activity and course module without invoking the module form.
     *
     * @param \stdClass $course Course record.
     * @return \stdClass Activity record.
     */
    private function create_hunt(\stdClass $course): \stdClass {
        global $DB;
        $hunt = (object)[
            'course' => $course->id, 'name' => 'Test hunt', 'intro' => '',
            'playwithoutmoving' => 1, 'tracking' => 0, 'groupmode' => 0,
            'allowattemptsfromdate' => 0, 'cutoffdate' => 0,
            'customplayerconfig' => '{"searchpaneldisabled":false,"localizationbuttondisabled":false,'
                . '"showheadinghint":true,"showinzonehint":false,"showdistancehint":false,'
                . '"shownextareahint":false}',
        ];
        $hunt->id = $DB->insert_record('treasurehunt', $hunt);
        $moduleid = $DB->get_field('modules', 'id', ['name' => 'treasurehunt'], MUST_EXIST);
        $cmid = $DB->insert_record('course_modules', (object)[
            'course' => $course->id, 'module' => $moduleid, 'instance' => $hunt->id,
            'added' => time(), 'visible' => 1,
        ]);
        course_add_cm_to_section($course, $cmid, 0, null, 'treasurehunt');
        return $hunt;
    }

    /**
     * The first poll and an unchanged poll preserve the player response contract.
     */
    public function test_initial_and_unchanged_poll(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $hunt = $this->create_hunt($course);
        $roadid = $DB->insert_record('treasurehunt_roads', (object)[
            'treasurehuntid' => $hunt->id, 'name' => 'Road', 'validated' => 1,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $geometry = 'MULTIPOLYGON (((0 0, 0 1, 1 1, 1 0, 0 0)))';
        foreach ([1, 2] as $position) {
            $DB->insert_record('treasurehunt_stages', (object)[
                'roadid' => $roadid, 'name' => 'Stage ' . $position, 'position' => $position,
                'cluetext' => '', 'questiontext' => '', 'geom' => $geometry,
                'timecreated' => time(), 'timemodified' => time(),
            ]);
        }
        $student = $this->getDataGenerator()->create_and_enrol($course);
        $this->setUser($student);
        $params = [
            'treasurehuntid' => $hunt->id,
            'attemptid' => 0,
            'roadtimestamp' => 0,
            'playwithoutmoving' => true,
            'groupmode' => false,
            'initialize' => true,
            'selectedanswerid' => 0,
            'qoaremoved' => false,
        ];

        $initial = user_progress::execute($params);
        $this->assertEquals(0, $initial['status']['code']);
        $this->assertFalse($initial['roadfinished']);
        $this->assertArrayHasKey('nextstage', $initial);
        $this->assertEquals(1, $initial['nextstage']['features'][0]['properties']['stageposition']);

        $params['initialize'] = false;
        $params['attemptid'] = $initial['attemptid'];
        $params['roadtimestamp'] = $initial['roadtimestamp'];
        $unchanged = user_progress::execute($params);
        $this->assertEquals(0, $unchanged['status']['code']);
        $this->assertEquals($initial['attemptid'], $unchanged['attemptid']);
        $this->assertArrayNotHasKey('attempts', $unchanged);

        $params['location'] = ['type' => 'Point', 'coordinates' => [0.5, 0.5]];
        $found = user_progress::execute($params);
        $this->assertEquals(0, $found['status']['code']);
        $this->assertEquals(1, $found['lastsuccessfulstage']->position);
        $this->assertEquals(2, $found['nextstage']['features'][0]['properties']['stageposition']);
        $this->assertGreaterThan(0, $DB->count_records('treasurehunt_attempts'));

        $params['attemptid'] = $found['attemptid'];
        $params['roadtimestamp'] = $found['roadtimestamp'];
        $params['location'] = ['type' => 'LineString', 'coordinates' => [0.5, 0.5]];
        $this->expectException(\invalid_parameter_exception::class);
        user_progress::execute($params);
    }

    /**
     * A free stage may be found early and is skipped when the ordered route reaches it.
     */
    public function test_out_of_sequence_discovery_and_clue_tabs(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $hunt = $this->create_hunt($course);
        $roadid = $DB->insert_record('treasurehunt_roads', (object)[
            'treasurehuntid' => $hunt->id, 'name' => 'Road', 'validated' => 1,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        foreach ([1, 2, 3] as $position) {
            $low = ($position - 1) * 2;
            $high = $low + 1;
            $DB->insert_record('treasurehunt_stages', (object)[
                'roadid' => $roadid, 'name' => 'Stage ' . $position, 'position' => $position,
                'cluetext' => $position === 1 ? 'Next stage clue' : '',
                'clueforstage' => 'Own clue ' . $position,
                'discoveroutofsequence' => $position === 3 ? 1 : 0,
                'questiontext' => '',
                'geom' => "MULTIPOLYGON ((($low 0, $low 1, $high 1, $high 0, $low 0)))",
            ]);
        }
        $student = $this->getDataGenerator()->create_and_enrol($course);
        $this->setUser($student);
        $params = [
            'treasurehuntid' => $hunt->id, 'attemptid' => 0, 'roadtimestamp' => 0,
            'playwithoutmoving' => true, 'groupmode' => false, 'initialize' => true,
            'selectedanswerid' => 0, 'qoaremoved' => false,
        ];
        $initial = user_progress::execute($params);
        \core_external\external_api::clean_returnvalue(user_progress::execute_returns(), $initial);
        $this->assertCount(2, $initial['nextstage']['features']);
        $this->assertCount(2, $initial['clues']);
        $params['initialize'] = false;
        $params['attemptid'] = $initial['attemptid'];
        $params['roadtimestamp'] = $initial['roadtimestamp'];
        $params['location'] = ['type' => 'Point', 'coordinates' => [4.5, 0.5]];
        $early = user_progress::execute($params);
        $this->assertFalse($early['roadfinished']);
        $this->assertEquals(1, $early['nextstage']['features'][0]['properties']['stageposition']);
        $this->assertCount(1, $early['clues']);
        $params['attemptid'] = $early['attemptid'];
        $params['location']['coordinates'] = [0.5, 0.5];
        $first = user_progress::execute($params);
        $this->assertEquals(2, $first['nextstage']['features'][0]['properties']['stageposition']);
        $this->assertStringContainsString('Next stage clue', $first['clues'][0]['html']);
        $params['attemptid'] = $first['attemptid'];
        $params['location']['coordinates'] = [2.5, 0.5];
        $last = user_progress::execute($params);
        \core_external\external_api::clean_returnvalue(user_progress::execute_returns(), $last);
        $this->assertTrue($last['roadfinished']);
        $this->assertTrue(treasurehunt_check_if_user_has_finished($student->id, 0, $roadid));
    }

    /**
     * A completed predecessor provides the clue even when its successor is not a free stage.
     */
    public function test_clues_follow_each_immediate_predecessor(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $hunt = $this->create_hunt($course);
        $roadid = $DB->insert_record('treasurehunt_roads', (object)[
            'treasurehuntid' => $hunt->id, 'name' => 'Road', 'validated' => 1,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $stageids = [];
        foreach ([1, 2, 3, 4] as $position) {
            $low = ($position - 1) * 2;
            $high = $low + 1;
            $stageids[$position] = $DB->insert_record('treasurehunt_stages', (object)[
                'roadid' => $roadid, 'name' => 'Stage ' . $position, 'position' => $position,
                'cluetext' => in_array($position, [1, 3]) ? 'Exit clue ' . $position : '',
                'clueforstage' => 'Own clue ' . $position,
                'discoveroutofsequence' => $position === 3 ? 1 : 0,
                'questiontext' => '',
                'geom' => "MULTIPOLYGON ((($low 0, $low 1, $high 1, $high 0, $low 0)))",
            ]);
        }
        $student = $this->getDataGenerator()->create_and_enrol($course);
        $this->setUser($student);
        $params = [
            'treasurehuntid' => $hunt->id, 'attemptid' => 0, 'roadtimestamp' => 0,
            'playwithoutmoving' => true, 'groupmode' => false, 'initialize' => true,
            'selectedanswerid' => 0, 'qoaremoved' => false,
        ];
        $initial = user_progress::execute($params);
        $this->assertSame([$stageids[1], $stageids[3]], array_column($initial['clues'], 'stageid'));
        $this->assertSame([1, 3], array_column($initial['clues'], 'position'));

        $params['initialize'] = false;
        $params['attemptid'] = $initial['attemptid'];
        $params['roadtimestamp'] = $initial['roadtimestamp'];
        $params['location'] = ['type' => 'Point', 'coordinates' => [4.5, 0.5]];
        $third = user_progress::execute($params);
        $params['attemptid'] = $third['attemptid'];
        $params['location']['coordinates'] = [0.5, 0.5];
        $first = user_progress::execute($params);
        \core_external\external_api::clean_returnvalue(user_progress::execute_returns(), $first);

        $this->assertSame([$stageids[2], $stageids[4]], array_column($first['clues'], 'stageid'));
        $this->assertSame([2, 4], array_column($first['clues'], 'position'));
        $this->assertStringContainsString('Exit clue 1', $first['clues'][0]['html']);
        $this->assertStringContainsString('Exit clue 3', $first['clues'][1]['html']);
        $this->assertStringNotContainsString('Own clue 2', $first['clues'][0]['html']);
        $this->assertStringNotContainsString('Own clue 4', $first['clues'][1]['html']);
    }

    /**
     * A manager can preview a selected road without belonging to its group.
     */
    public function test_manager_can_preview_selected_road_without_group_membership(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $hunt = $this->create_hunt($course);
        $hunt->groupmode = 1;
        $hunt->allowattemptsfromdate = time() + 3600;
        $DB->update_record('treasurehunt', $hunt);
        $otherhunt = $this->create_hunt($course);
        $roadids = [];
        foreach ([$hunt->id, $hunt->id, $otherhunt->id] as $huntid) {
            $roadid = $DB->insert_record('treasurehunt_roads', (object)[
                'treasurehuntid' => $huntid, 'name' => 'Road', 'validated' => 1,
                'timecreated' => time(), 'timemodified' => time(),
            ]);
            $roadids[] = $roadid;
            $DB->insert_record('treasurehunt_stages', (object)[
                'roadid' => $roadid, 'name' => 'First stage', 'position' => 1,
                'cluetext' => '', 'clueforstage' => 'Preview clue',
                'discoveroutofsequence' => 1, 'questiontext' => '',
                'geom' => 'MULTIPOLYGON (((0 0, 0 1, 1 1, 1 0, 0 0)))',
            ]);
        }
        $cm = get_coursemodule_from_instance('treasurehunt', $hunt->id);
        $selected = treasurehunt_get_user_group_and_road($USER->id, $hunt, $cm->id, false, '', $roadids[1]);
        $this->assertEquals($roadids[1], $selected->roadid);
        $this->assertEquals(0, $selected->groupid);

        $result = user_progress::execute([
            'treasurehuntid' => $hunt->id, 'previewroadid' => $roadids[1],
            'attemptid' => 0, 'roadtimestamp' => 0,
            'playwithoutmoving' => true, 'groupmode' => true,
            'initialize' => true, 'selectedanswerid' => 0, 'qoaremoved' => false,
        ]);
        \core_external\external_api::clean_returnvalue(user_progress::execute_returns(), $result);
        $this->assertTrue($result['available']);
        $this->assertEquals($roadids[1], $result['nextstage']['features'][0]['properties']['roadid']);
        $this->assertCount(1, $result['clues']);
        $this->assertStringContainsString('Preview clue', $result['clues'][0]['html']);

        $unchanged = user_progress::execute([
            'treasurehuntid' => $hunt->id, 'previewroadid' => $roadids[1],
            'attemptid' => $result['attemptid'], 'roadtimestamp' => $result['roadtimestamp'],
            'playwithoutmoving' => true, 'groupmode' => true,
            'initialize' => false, 'selectedanswerid' => 0, 'qoaremoved' => false,
        ]);
        \core_external\external_api::clean_returnvalue(user_progress::execute_returns(), $unchanged);
        $this->assertArrayNotHasKey('lastsuccessfulstage', $unchanged);
        $this->assertCount(1, $unchanged['clues']);

        $this->expectException(\dml_missing_record_exception::class);
        treasurehunt_get_user_group_and_road($USER->id, $hunt, $cm->id, false, '', $roadids[2]);
    }

    /**
     * A player cannot choose a road by supplying the preview parameter.
     */
    public function test_player_cannot_select_preview_road(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $hunt = $this->create_hunt($course);
        $roadid = $DB->insert_record('treasurehunt_roads', (object)[
            'treasurehuntid' => $hunt->id, 'name' => 'Road', 'validated' => 1,
        ]);
        $cm = get_coursemodule_from_instance('treasurehunt', $hunt->id);
        $student = $this->getDataGenerator()->create_and_enrol($course);
        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        treasurehunt_get_user_group_and_road($USER->id, $hunt, $cm->id, false, '', $roadid);
    }

    /**
     * An unfinished road cannot be previewed even when the manager names it explicitly.
     */
    public function test_manager_cannot_preview_unvalidated_road(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $hunt = $this->create_hunt($course);
        $roadid = $DB->insert_record('treasurehunt_roads', (object)[
            'treasurehuntid' => $hunt->id, 'name' => 'Unfinished road', 'validated' => 0,
        ]);

        try {
            user_progress::execute([
                'treasurehuntid' => $hunt->id, 'previewroadid' => $roadid,
                'attemptid' => 0, 'roadtimestamp' => 0,
                'playwithoutmoving' => true, 'groupmode' => false,
                'initialize' => true, 'selectedanswerid' => 0, 'qoaremoved' => false,
            ]);
            $this->fail('An unvalidated road must not be previewed.');
        } catch (\core\session\exception $e) {
            $this->assertSame('previewinvalidroad', $e->errorcode);
        }
    }

    /**
     * A stale validated flag must not produce a GeoJSON feature without geometry.
     */
    public function test_preview_omits_nextstage_without_geometry(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $hunt = $this->create_hunt($course);
        $roadid = $DB->insert_record('treasurehunt_roads', (object)[
            'treasurehuntid' => $hunt->id, 'name' => 'Stale validation', 'validated' => 1,
        ]);
        $DB->insert_record('treasurehunt_stages', (object)[
            'roadid' => $roadid, 'name' => 'No geometry', 'position' => 1,
            'cluetext' => '', 'questiontext' => '', 'geom' => '',
        ]);
        $DB->insert_record('treasurehunt_stages', (object)[
            'roadid' => $roadid, 'name' => 'Also no geometry', 'position' => 2,
            'cluetext' => '', 'questiontext' => '', 'geom' => '',
        ]);
        $this->assertFalse(treasurehunt_is_valid_road($roadid));

        $result = user_progress::execute([
            'treasurehuntid' => $hunt->id, 'previewroadid' => $roadid,
            'attemptid' => 0, 'roadtimestamp' => 0,
            'playwithoutmoving' => true, 'groupmode' => false,
            'initialize' => true, 'selectedanswerid' => 0, 'qoaremoved' => false,
        ]);
        \core_external\external_api::clean_returnvalue(user_progress::execute_returns(), $result);
        $this->assertArrayNotHasKey('nextstage', $result);
    }

    /**
     * A valid lock on one activity cannot delete a stage from another activity.
     */
    public function test_delete_stage_rejects_another_activity(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $first = $this->create_hunt($course);
        $second = $this->create_hunt($course);
        $roadid = $DB->insert_record('treasurehunt_roads', (object)[
            'treasurehuntid' => $second->id, 'name' => 'Other road',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $stageid = $DB->insert_record('treasurehunt_stages', (object)[
            'roadid' => $roadid, 'name' => 'Other stage', 'position' => 1,
            'cluetext' => '', 'questiontext' => '', 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $lockid = $DB->insert_record('treasurehunt_locks', (object)[
            'treasurehuntid' => $first->id, 'userid' => $USER->id, 'lockedtill' => time() + 60,
        ]);

        try {
            delete_stage::execute($stageid, $first->id, $lockid);
            $this->fail('The foreign stage was accepted.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('invalidentry', $e->errorcode);
        }
        $this->assertTrue($DB->record_exists('treasurehunt_stages', ['id' => $stageid]));
    }
}
