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
 * @coversNothing
 */
final class user_progress_test extends \advanced_testcase {
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
            'attempttimestamp' => 0,
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
        $params['attempttimestamp'] = $initial['attempttimestamp'];
        $params['roadtimestamp'] = $initial['roadtimestamp'];
        $unchanged = user_progress::execute($params);
        $this->assertEquals(0, $unchanged['status']['code']);
        $this->assertEquals($initial['attempttimestamp'], $unchanged['attempttimestamp']);
        $this->assertArrayNotHasKey('attempts', $unchanged);

        $params['location'] = ['type' => 'Point', 'coordinates' => [0.5, 0.5]];
        $found = user_progress::execute($params);
        $this->assertEquals(0, $found['status']['code']);
        $this->assertEquals(1, $found['lastsuccessfulstage']->position);
        $this->assertEquals(2, $found['nextstage']['features'][0]['properties']['stageposition']);
        $this->assertGreaterThan(0, $DB->count_records('treasurehunt_attempts'));

        $params['attempttimestamp'] = $found['attempttimestamp'];
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
            'treasurehuntid' => $hunt->id, 'attempttimestamp' => 0, 'roadtimestamp' => 0,
            'playwithoutmoving' => true, 'groupmode' => false, 'initialize' => true,
            'selectedanswerid' => 0, 'qoaremoved' => false,
        ];
        $initial = user_progress::execute($params);
        \core_external\external_api::clean_returnvalue(user_progress::execute_returns(), $initial);
        $this->assertCount(2, $initial['nextstage']['features']);
        $this->assertCount(2, $initial['clues']);
        $params['initialize'] = false;
        $params['attempttimestamp'] = $initial['attempttimestamp'];
        $params['roadtimestamp'] = $initial['roadtimestamp'];
        $params['location'] = ['type' => 'Point', 'coordinates' => [4.5, 0.5]];
        $early = user_progress::execute($params);
        $this->assertFalse($early['roadfinished']);
        $this->assertEquals(1, $early['nextstage']['features'][0]['properties']['stageposition']);
        $this->assertCount(1, $early['clues']);
        $params['attempttimestamp'] = $early['attempttimestamp'];
        $params['location']['coordinates'] = [0.5, 0.5];
        $first = user_progress::execute($params);
        $this->assertEquals(2, $first['nextstage']['features'][0]['properties']['stageposition']);
        $this->assertStringContainsString('Next stage clue', $first['clues'][0]['html']);
        $params['attempttimestamp'] = $first['attempttimestamp'];
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
            'treasurehuntid' => $hunt->id, 'attempttimestamp' => 0, 'roadtimestamp' => 0,
            'playwithoutmoving' => true, 'groupmode' => false, 'initialize' => true,
            'selectedanswerid' => 0, 'qoaremoved' => false,
        ];
        $initial = user_progress::execute($params);
        $this->assertSame([$stageids[1], $stageids[3]], array_column($initial['clues'], 'stageid'));
        $this->assertSame([1, 3], array_column($initial['clues'], 'position'));

        $params['initialize'] = false;
        $params['attempttimestamp'] = $initial['attempttimestamp'];
        $params['roadtimestamp'] = $initial['roadtimestamp'];
        $params['location'] = ['type' => 'Point', 'coordinates' => [4.5, 0.5]];
        $third = user_progress::execute($params);
        $params['attempttimestamp'] = $third['attempttimestamp'];
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
            'attempttimestamp' => 0, 'roadtimestamp' => 0,
            'playwithoutmoving' => true, 'groupmode' => true,
            'initialize' => true, 'selectedanswerid' => 0, 'qoaremoved' => false,
        ]);
        $this->assertTrue($result['available']);
        $this->assertEquals($roadids[1], $result['nextstage']['features'][0]['properties']['roadid']);
        $this->assertCount(1, $result['clues']);
        $this->assertStringContainsString('Preview clue', $result['clues'][0]['html']);

        $unchanged = user_progress::execute([
            'treasurehuntid' => $hunt->id, 'previewroadid' => $roadids[1],
            'attempttimestamp' => $result['attempttimestamp'], 'roadtimestamp' => $result['roadtimestamp'],
            'playwithoutmoving' => true, 'groupmode' => true,
            'initialize' => false, 'selectedanswerid' => 0, 'qoaremoved' => false,
        ]);
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
