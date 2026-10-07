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
 * Security checks for activity-scoped editing.
 *
 * @package mod_treasurehunt
 * @copyright 2026
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_treasurehunt;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/../locallib.php');

/**
 * Exercise ownership and lock checks used by the editing endpoints.
 *
 */
final class security_test extends \advanced_testcase {
    /**
     * Create a minimal instance without relying on an activity generator.
     *
     * @param int $courseid Course identifier.
     * @return \stdClass The activity identifier.
     */
    private function create_hunt(int $courseid): \stdClass {
        global $DB;
        return (object)['id' => $DB->insert_record('treasurehunt', (object)[
            'course' => $courseid, 'name' => 'Test hunt', 'intro' => '',
        ])];
    }

    /**
     * A road and its stages cannot be used while editing a different activity.
     */
    public function test_road_and_stage_ownership(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $first = $this->create_hunt($course->id);
        $second = $this->create_hunt($course->id);
        $roadid = $DB->insert_record('treasurehunt_roads', (object)[
            'treasurehuntid' => $first->id, 'name' => 'First road', 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $stageid = $DB->insert_record('treasurehunt_stages', (object)[
            'roadid' => $roadid, 'name' => 'First stage', 'position' => 1,
            'cluetext' => '', 'questiontext' => '', 'timecreated' => time(), 'timemodified' => time(),
        ]);

        treasurehunt_require_road_in_activity($roadid, $first->id);
        $this->assertEquals($stageid, treasurehunt_require_stage_in_activity($stageid, $first->id)->id);
        try {
            treasurehunt_require_road_in_activity($roadid, $second->id);
            $this->fail('A road from another activity was accepted.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('invalidentry', $e->errorcode);
        }
        $this->expectException(\moodle_exception::class);
        treasurehunt_require_stage_in_activity($stageid, $second->id);
    }

    /**
     * A lock only authorises its owner, activity and active lifetime.
     */
    public function test_edition_lock_scope_and_expiry(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $first = $this->create_hunt($course->id);
        $second = $this->create_hunt($course->id);
        $user = $this->getDataGenerator()->create_user();
        $lockid = $DB->insert_record('treasurehunt_locks', (object)[
            'treasurehuntid' => $first->id, 'userid' => $user->id, 'lockedtill' => time() + 60,
        ]);

        $this->assertTrue(treasurehunt_edition_lock_id_is_valid($lockid, $first->id, $user->id));
        $this->assertFalse(treasurehunt_edition_lock_id_is_valid($lockid, $second->id, $user->id));
        $this->assertFalse(treasurehunt_edition_lock_id_is_valid($lockid, $first->id, 1));
        $DB->set_field('treasurehunt_locks', 'lockedtill', time() - 1, ['id' => $lockid]);
        $this->assertFalse(treasurehunt_edition_lock_id_is_valid($lockid, $first->id, $user->id));
    }

    /**
     * A suspended tab can recover its own expired lease when no one else is editing.
     */
    public function test_expired_editor_lock_is_renewed_for_its_owner(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $hunt = $this->create_hunt($course->id);
        $editor = $this->getDataGenerator()->create_user();
        $lockid = $DB->insert_record('treasurehunt_locks', (object)[
            'treasurehuntid' => $hunt->id, 'userid' => $editor->id, 'lockedtill' => time() - 1,
        ]);

        $this->assertEquals($lockid, treasurehunt_try_renew_edition_lock($hunt->id, $editor->id, $lockid));
        $this->assertTrue(treasurehunt_edition_lock_id_is_valid($lockid, $hunt->id, $editor->id));

        $DB->delete_records('treasurehunt_locks', ['id' => $lockid]);
        $newid = treasurehunt_try_renew_edition_lock($hunt->id, $editor->id, $lockid);
        $this->assertNotEquals($lockid, $newid);
        $this->assertTrue(treasurehunt_edition_lock_id_is_valid($newid, $hunt->id, $editor->id));
    }

    /**
     * Renewal must not take an active lock from a different editor.
     */
    public function test_expired_editor_lock_does_not_override_another_editor(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $hunt = $this->create_hunt($course->id);
        $editor = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $oldid = $DB->insert_record('treasurehunt_locks', (object)[
            'treasurehuntid' => $hunt->id, 'userid' => $editor->id, 'lockedtill' => time() - 1,
        ]);
        $otherid = $DB->insert_record('treasurehunt_locks', (object)[
            'treasurehuntid' => $hunt->id, 'userid' => $other->id, 'lockedtill' => time() + 60,
        ]);

        $this->assertSame(0, treasurehunt_try_renew_edition_lock($hunt->id, $editor->id, $oldid));
        $this->assertFalse(treasurehunt_ensure_editor_lock($oldid, $hunt->id, $editor->id));
        $this->assertFalse(treasurehunt_edition_lock_id_is_valid($oldid, $hunt->id, $editor->id));
        $this->assertTrue(treasurehunt_edition_lock_id_is_valid($otherid, $hunt->id, $other->id));
    }

    /**
     * A save from an open editor also recovers its own expired lease.
     */
    public function test_save_recovers_expired_owned_lock(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $hunt = $this->create_hunt($course->id);
        $editor = $this->getDataGenerator()->create_user();
        $lockid = $DB->insert_record('treasurehunt_locks', (object)[
            'treasurehuntid' => $hunt->id, 'userid' => $editor->id, 'lockedtill' => time() - 1,
        ]);

        $this->assertTrue(treasurehunt_ensure_editor_lock($lockid, $hunt->id, $editor->id));
        $this->assertTrue(treasurehunt_edition_lock_id_is_valid($lockid, $hunt->id, $editor->id));
    }

    /**
     * The renewal service reports success after an owned lease expires.
     */
    public function test_renewal_service_recovers_expired_lock(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $hunt = $this->create_hunt($course->id);
        $moduleid = $DB->get_field('modules', 'id', ['name' => 'treasurehunt'], MUST_EXIST);
        $cmid = $DB->insert_record('course_modules', (object)[
            'course' => $course->id, 'module' => $moduleid, 'instance' => $hunt->id,
            'added' => time(), 'visible' => 1,
        ]);
        course_add_cm_to_section($course, $cmid, 0, null, 'treasurehunt');
        $lockid = $DB->insert_record('treasurehunt_locks', (object)[
            'treasurehuntid' => $hunt->id, 'userid' => $USER->id, 'lockedtill' => time() - 1,
        ]);

        $result = \mod_treasurehunt\external\renew_lock::execute($hunt->id, $lockid);
        $this->assertEquals(0, $result['status']['code']);
        $this->assertEquals($lockid, $result['lockid']);

        $DB->set_field('treasurehunt_locks', 'lockedtill', time() - 1, ['id' => $lockid]);
        $other = $this->getDataGenerator()->create_user();
        $DB->insert_record('treasurehunt_locks', (object)[
            'treasurehuntid' => $hunt->id, 'userid' => $other->id, 'lockedtill' => time() + 60,
        ]);
        $result = \mod_treasurehunt\external\renew_lock::execute($hunt->id, $lockid);
        $this->assertEquals(1, $result['status']['code']);
        $this->assertSame(get_string('editorlocktaken', 'treasurehunt'), $result['status']['msg']);
    }

    /**
     * The latest geometry-solved attempt wins when several attempts share a timestamp.
     */
    public function test_latest_attempt_uses_id_to_break_timestamp_ties(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $hunt = $this->create_hunt($course->id);
        $user = $this->getDataGenerator()->create_user();
        $roadid = $DB->insert_record('treasurehunt_roads', (object)[
            'treasurehuntid' => $hunt->id, 'name' => 'Road', 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $stageid = $DB->insert_record('treasurehunt_stages', (object)[
            'roadid' => $roadid, 'name' => 'Stage', 'position' => 1,
            'cluetext' => '', 'questiontext' => '', 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $attempt = (object)[
            'stageid' => $stageid, 'userid' => $user->id, 'groupid' => 0,
            'timecreated' => time(), 'geometrysolved' => 1, 'success' => 0, 'location' => 'POINT (1 2)',
        ];
        $DB->insert_record('treasurehunt_attempts', $attempt);
        $attempt->success = 1;
        $lastid = $DB->insert_record('treasurehunt_attempts', $attempt);
        $attempt->geometrysolved = 0;
        $DB->insert_record('treasurehunt_attempts', $attempt);

        $latest = treasurehunt_query_last_successful_attempt($user->id, 0, $roadid);
        $this->assertEquals($lastid, $latest->id);
    }

    /**
     * Passive position updates are sampled at the configured interval.
     */
    public function test_passive_tracking_interval(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $hunt = $this->create_hunt($course->id);
        $user = $this->getDataGenerator()->create_user();
        $roadid = $DB->insert_record('treasurehunt_roads', (object)[
            'treasurehuntid' => $hunt->id, 'name' => 'Road', 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $stageid = $DB->insert_record('treasurehunt_stages', (object)[
            'roadid' => $roadid, 'name' => 'Stage', 'position' => 1,
            'cluetext' => '', 'questiontext' => '', 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $now = time();
        treasurehunt_track_user($user->id, $hunt, $stageid, $now, 'POINT (1 2)', 15);
        treasurehunt_track_user($user->id, $hunt, $stageid, $now + 5, 'POINT (2 3)', 15);
        treasurehunt_track_user($user->id, $hunt, $stageid, $now + 16, 'POINT (3 4)', 15);
        $this->assertEquals(2, $DB->count_records('treasurehunt_track', [
            'treasurehuntid' => $hunt->id, 'userid' => $user->id,
        ]));
    }
}
