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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

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
            'customplayerconfig' => '{"showheadinghint":true}',
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
