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
 * Tests for restored progress belonging to participants who are not enrolled.
 *
 * @package mod_treasurehunt
 * @author Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @copyright 2026 Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_treasurehunt;

/**
 * Verify that staff can review restored attempts without enrolling former participants.
 *
 * @covers ::treasurehunt_get_list_participants_and_attempts_in_roads
 */
final class restored_progress_test extends \advanced_testcase {
    /**
     * Load the progress helper.
     */
    protected function setUp(): void {
        parent::setUp();
        global $CFG;
        require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');
    }

    /**
     * Historical attempts appear to staff on their own road, but not to students.
     */
    public function test_unenrolled_historical_participant_is_visible_only_to_staff(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $huntid = $DB->insert_record('treasurehunt', (object)[
            'course' => $course->id,
            'name' => 'Restored hunt',
            'intro' => '',
            'groupmode' => 0,
        ]);
        $moduleid = $DB->get_field('modules', 'id', ['name' => 'treasurehunt'], MUST_EXIST);
        $cmid = $DB->insert_record('course_modules', (object)[
            'course' => $course->id,
            'module' => $moduleid,
            'instance' => $huntid,
            'added' => time(),
            'visible' => 1,
        ]);
        course_add_cm_to_section($course, $cmid, 0);
        $cm = get_fast_modinfo($course)->get_cm($cmid);
        $context = \context_module::instance($cmid);
        $participant = $this->getDataGenerator()->create_user();
        $roads = [];
        foreach (['First', 'Second'] as $name) {
            $roadid = $DB->insert_record('treasurehunt_roads', (object)[
                'treasurehuntid' => $huntid,
                'name' => $name,
                'validated' => 1,
            ]);
            $stageid = $DB->insert_record('treasurehunt_stages', (object)[
                'roadid' => $roadid,
                'name' => $name . ' stage',
                'position' => 1,
                'cluetext' => '',
                'questiontext' => '',
            ]);
            $roads[] = [$roadid, $stageid];
        }
        $DB->insert_record('treasurehunt_attempts', (object)[
            'stageid' => $roads[0][1],
            'userid' => $participant->id,
            'groupid' => 0,
            'timecreated' => time(),
            'success' => 1,
            'type' => 'location',
        ]);
        $this->assertEmpty(get_enrolled_users($context, 'mod/treasurehunt:enterplayer'));

        $this->setAdminUser();
        [$staffroads] = treasurehunt_get_list_participants_and_attempts_in_roads($cm, $course->id, $context);
        $this->assertCount(1, $staffroads[0]->userlist);
        $this->assertEquals($participant->id, $staffroads[0]->userlist[0]->id);
        $this->assertSame('successwithoutfailures', $staffroads[0]->userlist[0]->ratings[1]->class);
        $this->assertEmpty($staffroads[1]->userlist);
        $PAGE->set_url(new \moodle_url('/mod/treasurehunt/view.php', ['id' => $cmid]));
        $PAGE->set_context($context);
        $PAGE->set_course($course);
        $html = treasurehunt_view_users_progress_table($cm, $course->id, $context);
        $this->assertStringContainsString('treasurehunt-unenrolled-warning', $html);
        $this->assertStringContainsString(get_string('progressusernotenrolled', 'treasurehunt'), $html);

        $this->setUser($this->getDataGenerator()->create_user());
        [$studentroads] = treasurehunt_get_list_participants_and_attempts_in_roads($cm, $course->id, $context);
        $this->assertEmpty($studentroads[0]->userlist);
        $this->assertEmpty($studentroads[1]->userlist);

        $this->setAdminUser();
        $hunt = (object)['id' => $huntid, 'course' => $course->id, 'groupmode' => 0, 'cutoffdate' => 0];
        $this->assertFalse(treasurehunt_tracks_need_refresh($hunt, $cm, $context));
        $active = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($active->id, $course->id, 'student');
        $DB->insert_record('treasurehunt_attempts', (object)[
            'stageid' => $roads[0][1],
            'userid' => $active->id,
            'groupid' => 0,
            'timecreated' => time(),
            'success' => 0,
            'type' => 'location',
        ]);
        $this->assertTrue(treasurehunt_tracks_need_refresh($hunt, $cm, $context));
        $hunt->cutoffdate = time() - 1;
        $this->assertFalse(treasurehunt_tracks_need_refresh($hunt, $cm, $context));
        $hunt->cutoffdate = 0;
        $DB->insert_record('treasurehunt_attempts', (object)[
            'stageid' => $roads[0][1],
            'userid' => $active->id,
            'groupid' => 0,
            'timecreated' => time(),
            'success' => 1,
            'type' => 'location',
        ]);
        treasurehunt_invalidate_player_state($roads[0][0], 0, $active->id);
        $this->assertFalse(treasurehunt_tracks_need_refresh($hunt, $cm, $context));
    }

    /**
     * Track polling stops after the active team completes its assigned road.
     */
    public function test_track_refresh_stops_when_team_finishes(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $grouping = $this->getDataGenerator()->create_grouping(['courseid' => $course->id]);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_assign_grouping($grouping->id, $group->id);
        $player = $this->getDataGenerator()->create_and_enrol($course);
        groups_add_member($group->id, $player->id);
        $huntid = $DB->insert_record('treasurehunt', (object)[
            'course' => $course->id,
            'name' => 'Team track refresh',
            'intro' => '',
            'groupmode' => 1,
        ]);
        $moduleid = $DB->get_field('modules', 'id', ['name' => 'treasurehunt'], MUST_EXIST);
        $cmid = $DB->insert_record('course_modules', (object)[
            'course' => $course->id,
            'module' => $moduleid,
            'instance' => $huntid,
            'added' => time(),
            'visible' => 1,
            'groupmode' => 1,
        ]);
        course_add_cm_to_section($course, $cmid, 0);
        $cm = get_fast_modinfo($course)->get_cm($cmid);
        $context = \context_module::instance($cmid);
        $roadid = $DB->insert_record('treasurehunt_roads', (object)[
            'treasurehuntid' => $huntid,
            'name' => 'Team road',
            'validated' => 1,
            'groupingid' => $grouping->id,
        ]);
        $stageid = $DB->insert_record('treasurehunt_stages', (object)[
            'roadid' => $roadid,
            'name' => 'Finish',
            'position' => 1,
            'cluetext' => '',
            'questiontext' => '',
        ]);
        $this->setAdminUser();
        $hunt = (object)['id' => $huntid, 'course' => $course->id, 'groupmode' => 1, 'cutoffdate' => 0];
        $this->assertTrue(treasurehunt_tracks_need_refresh($hunt, $cm, $context));
        $DB->insert_record('treasurehunt_attempts', (object)[
            'stageid' => $stageid,
            'userid' => $player->id,
            'groupid' => $group->id,
            'timecreated' => time(),
            'success' => 1,
            'type' => 'location',
        ]);
        treasurehunt_invalidate_player_state($roadid, $group->id, $player->id);
        $this->assertFalse(treasurehunt_tracks_need_refresh($hunt, $cm, $context));
    }
}
