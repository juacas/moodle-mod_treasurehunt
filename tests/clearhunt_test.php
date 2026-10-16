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
 * Scoped activity reset tests.
 *
 * @package mod_treasurehunt
 * @copyright 2026
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_treasurehunt;

/**
 * Verify that a reset does not remove another sequence or road.
 *
 * @covers ::treasurehunt_clear_road_activities
 * @covers ::treasurehunt_get_clearable_road_ids
 */
final class clearhunt_test extends \advanced_testcase {
    /**
     * Load plugin functions.
     */
    protected function setUp(): void {
        parent::setUp();
        global $CFG;
        require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');
    }

    /**
     * Create a road and one stage for a hunt.
     *
     * @param int $huntid Hunt ID.
     * @param string $name Road name.
     * @return array Road and stage IDs.
     */
    private function create_road(int $huntid, string $name): array {
        global $DB;
        $roadid = $DB->insert_record('treasurehunt_roads', (object)[
            'treasurehuntid' => $huntid, 'name' => $name,
        ]);
        $stageid = $DB->insert_record('treasurehunt_stages', (object)[
            'roadid' => $roadid, 'name' => $name . ' stage',
            'position' => 1, 'cluetext' => '', 'questiontext' => '',
        ]);
        return [$roadid, $stageid];
    }

    /**
     * Create an attempt and a position sample for one user.
     *
     * @param int $huntid Hunt ID.
     * @param int $stageid Stage ID.
     * @param int $userid User ID.
     * @param int $groupid Group ID or zero.
     * @return array Attempt and track IDs.
     */
    private function create_activity(int $huntid, int $stageid, int $userid, int $groupid = 0): array {
        global $DB;
        $attemptid = $DB->insert_record('treasurehunt_attempts', (object)[
            'stageid' => $stageid, 'userid' => $userid, 'groupid' => $groupid,
            'timecreated' => time(),
        ]);
        $trackid = $DB->insert_record('treasurehunt_track', (object)[
            'treasurehuntid' => $huntid, 'stageid' => $stageid,
            'userid' => $userid, 'location' => 'POINT(0 0)', 'timestamp' => time(),
        ]);
        return [$attemptid, $trackid];
    }

    /**
     * Individual reset keeps the other user and other road, then road reset removes its remainder.
     */
    public function test_individual_and_road_resets_are_scoped(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $huntid = $DB->insert_record('treasurehunt', (object)[
            'course' => $course->id, 'name' => 'Test hunt', 'intro' => '',
        ]);
        $first = $this->getDataGenerator()->create_user();
        $second = $this->getDataGenerator()->create_user();
        [$roadone, $stageone] = $this->create_road($huntid, 'First');
        [$roadtwo, $stagetwo] = $this->create_road($huntid, 'Second');
        [$firstattempt, $firsttrack] = $this->create_activity($huntid, $stageone, $first->id);
        [$secondattempt, $secondtrack] = $this->create_activity($huntid, $stageone, $second->id);
        [$otherattempt, $othertrack] = $this->create_activity($huntid, $stagetwo, $first->id);

        $this->assertEqualsCanonicalizing([$roadone, $roadtwo], array_keys(treasurehunt_get_clearable_road_ids($huntid)));
        $this->assertSame(1, treasurehunt_clear_road_activities($huntid, $roadone, $first->id));
        $this->assertFalse($DB->record_exists('treasurehunt_attempts', ['id' => $firstattempt]));
        $this->assertFalse($DB->record_exists('treasurehunt_track', ['id' => $firsttrack]));
        foreach ([$secondattempt, $otherattempt] as $id) {
            $this->assertTrue($DB->record_exists('treasurehunt_attempts', ['id' => $id]));
        }
        foreach ([$secondtrack, $othertrack] as $id) {
            $this->assertTrue($DB->record_exists('treasurehunt_track', ['id' => $id]));
        }

        $this->assertSame(1, treasurehunt_clear_road_activities($huntid, $roadone));
        $this->assertFalse($DB->record_exists('treasurehunt_attempts', ['id' => $secondattempt]));
        $this->assertFalse($DB->record_exists('treasurehunt_track', ['id' => $secondtrack]));
        $this->assertEqualsCanonicalizing([$roadtwo], array_keys(treasurehunt_get_clearable_road_ids($huntid)));
        $this->assertTrue($DB->record_exists('treasurehunt_attempts', ['id' => $otherattempt]));
        $this->assertTrue($DB->record_exists('treasurehunt_track', ['id' => $othertrack]));

        $this->assertSame(1, treasurehunt_clear_road_activities($huntid, $roadtwo));
        $this->assertSame([], treasurehunt_get_clearable_road_ids($huntid));
        $trackonly = $DB->insert_record('treasurehunt_track', (object)[
            'treasurehuntid' => $huntid, 'stageid' => $stagetwo,
            'userid' => $first->id, 'location' => 'POINT(0 0)', 'timestamp' => time(),
        ]);
        $this->assertEqualsCanonicalizing([$roadtwo], array_keys(treasurehunt_get_clearable_road_ids($huntid)));
        $this->assertSame(0, treasurehunt_clear_road_activities($huntid, $roadtwo));
        $this->assertFalse($DB->record_exists('treasurehunt_track', ['id' => $trackonly]));
        $this->assertSame([], treasurehunt_get_clearable_road_ids($huntid));
    }

    /**
     * Group reset removes its members' road activity but preserves another team's sequence.
     */
    public function test_team_reset_is_scoped_to_group_and_road(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $huntid = $DB->insert_record('treasurehunt', (object)[
            'course' => $course->id, 'name' => 'Team hunt', 'intro' => '', 'groupmode' => 1,
        ]);
        $first = $this->getDataGenerator()->create_user();
        $second = $this->getDataGenerator()->create_user();
        $firstgroup = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $secondgroup = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_add_member($firstgroup->id, $first->id);
        groups_add_member($secondgroup->id, $second->id);
        [$roadone, $stageone] = $this->create_road($huntid, 'Shared');
        [, $stagetwo] = $this->create_road($huntid, 'Other');
        $grouping = $this->getDataGenerator()->create_grouping(['courseid' => $course->id]);
        foreach ([$firstgroup, $secondgroup] as $group) {
            $this->getDataGenerator()->create_grouping_group([
                'groupingid' => $grouping->id, 'groupid' => $group->id,
            ]);
        }
        $DB->set_field('treasurehunt_roads', 'groupingid', $grouping->id, ['id' => $roadone]);
        [$firstattempt, $firsttrack] = $this->create_activity($huntid, $stageone, $first->id, $firstgroup->id);
        [$secondattempt, $secondtrack] = $this->create_activity($huntid, $stageone, $second->id, $secondgroup->id);
        [$otherattempt, $othertrack] = $this->create_activity($huntid, $stagetwo, $first->id, $firstgroup->id);

        $this->assertSame(1, treasurehunt_clear_road_activities($huntid, $roadone, $firstgroup->id, true));
        $this->assertFalse($DB->record_exists('treasurehunt_attempts', ['id' => $firstattempt]));
        $this->assertFalse($DB->record_exists('treasurehunt_track', ['id' => $firsttrack]));
        foreach ([$secondattempt, $otherattempt] as $id) {
            $this->assertTrue($DB->record_exists('treasurehunt_attempts', ['id' => $id]));
        }
        foreach ([$secondtrack, $othertrack] as $id) {
            $this->assertTrue($DB->record_exists('treasurehunt_track', ['id' => $id]));
        }

        // The track format cannot distinguish teams when a player belongs to both.
        $DB->insert_record('groups_members', (object)[
            'groupid' => $secondgroup->id, 'userid' => $first->id, 'timeadded' => time(),
        ]);
        $this->assertTrue($DB->record_exists('groups_members', [
            'groupid' => $secondgroup->id, 'userid' => $first->id,
        ]));
        [$ambiguousattempt, $ambiguoustrack] = $this->create_activity(
            $huntid,
            $stageone,
            $first->id,
            $firstgroup->id
        );
        $this->assertSame(1, treasurehunt_clear_road_activities($huntid, $roadone, $firstgroup->id, true));
        $this->assertFalse($DB->record_exists('treasurehunt_attempts', ['id' => $ambiguousattempt]));
        $this->assertTrue($DB->record_exists('treasurehunt_track', ['id' => $ambiguoustrack]));
    }
}
