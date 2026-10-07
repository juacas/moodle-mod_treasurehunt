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
 * Tests for the user's course activity report.
 *
 * @package mod_treasurehunt
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_treasurehunt;

/**
 * Verify report scoping and group progress.
 *
 */
final class user_complete_test extends \advanced_testcase {
    /**
     * Create a minimal hunt.
     *
     * @param int $courseid Course id.
     * @param int $groupmode Group mode flag.
     * @return \stdClass Activity record.
     */
    private function create_hunt(int $courseid, int $groupmode = 0): \stdClass {
        global $DB;
        $hunt = (object)[
            'course' => $courseid,
            'name' => 'Report hunt',
            'intro' => '',
            'groupmode' => $groupmode,
        ];
        $hunt->id = $DB->insert_record('treasurehunt', $hunt);
        $moduleid = $DB->get_field('modules', 'id', ['name' => 'treasurehunt'], MUST_EXIST);
        $hunt->cmid = $DB->insert_record('course_modules', (object)[
            'course' => $courseid,
            'module' => $moduleid,
            'instance' => $hunt->id,
            'added' => time(),
            'visible' => 1,
        ]);
        course_add_cm_to_section(get_course($courseid), $hunt->cmid, 0, null, 'treasurehunt');
        return $hunt;
    }

    /**
     * Create a stage in the given activity.
     *
     * @param int $huntid Activity id.
     * @param string $name Stage name.
     * @return int Stage id.
     */
    private function create_stage(int $huntid, string $name): int {
        global $DB;
        $roadid = $DB->insert_record('treasurehunt_roads', (object)[
            'treasurehuntid' => $huntid,
            'name' => 'Road',
        ]);
        return $DB->insert_record('treasurehunt_stages', (object)[
            'roadid' => $roadid,
            'name' => $name,
            'position' => 1,
            'cluetext' => '',
            'questiontext' => '',
        ]);
    }

    /**
     * Render the callback and return its output.
     *
     * @param \stdClass $course Course record.
     * @param \stdClass $user User record.
     * @param \stdClass $hunt Activity record.
     * @return string Rendered report.
     */
    private function render_report(\stdClass $course, \stdClass $user, \stdClass $hunt): string {
        ob_start();
        try {
            treasurehunt_user_complete($course, $user, (object)['id' => $hunt->cmid], $hunt);
            return ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

    /**
     * Show only this user's attempts in this activity.
     */
    public function test_individual_report_scopes_attempts_and_escapes_stage_name(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();
        $hunt = $this->create_hunt($course->id);
        $stageid = $this->create_stage($hunt->id, 'Visible <stage>');
        $otherhunt = $this->create_hunt($course->id);
        $otherstageid = $this->create_stage($otherhunt->id, 'Foreign stage');

        foreach (
            [
            ['stageid' => $stageid, 'userid' => $user->id, 'success' => 0],
            ['stageid' => $stageid, 'userid' => $user->id, 'success' => 1],
            ['stageid' => $stageid, 'userid' => $otheruser->id, 'success' => 1],
            ['stageid' => $otherstageid, 'userid' => $user->id, 'success' => 1],
            ] as $attempt
        ) {
            $DB->insert_record('treasurehunt_attempts', (object)($attempt + [
                'groupid' => 0,
                'timecreated' => time(),
                'type' => 'location',
            ]));
        }

        $report = $this->render_report($course, $user, $hunt);
        $this->assertStringContainsString('Visible', $report);
        $this->assertStringNotContainsString('Foreign stage', $report);
        $this->assertStringNotContainsString('Visible <stage>', $report);
        $this->assertStringContainsString('>2<', $report);
        $this->assertStringContainsString(get_string('yes'), $report);

        $unuseduser = $this->getDataGenerator()->create_user();
        $this->assertStringContainsString(
            get_string('reportnoattempts', 'treasurehunt'),
            $this->render_report($course, $unuseduser, $hunt)
        );
    }

    /**
     * Show shared attempts of the user's group without including another group.
     */
    public function test_group_report_includes_team_progress(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course);
        $teammate = $this->getDataGenerator()->create_and_enrol($course);
        $hunt = $this->create_hunt($course->id, 1);
        $stageid = $this->create_stage($hunt->id, 'Shared stage');
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Blue team']);
        $othergroup = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Other team']);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $user->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $teammate->id]);
        foreach ([$group->id, $othergroup->id] as $groupid) {
            $DB->insert_record('treasurehunt_attempts', (object)[
                'stageid' => $stageid,
                'userid' => $teammate->id,
                'groupid' => $groupid,
                'timecreated' => time(),
                'type' => 'location',
                'success' => 1,
            ]);
        }

        $this->assertEquals([$group->id], array_values($DB->get_fieldset_select(
            'groups_members',
            'groupid',
            'userid = ?',
            [$user->id]
        )));

        $report = $this->render_report($course, $user, $hunt);
        $this->assertStringContainsString('Shared stage', $report);
        $this->assertStringContainsString('Blue team', $report);
        $this->assertStringNotContainsString('Other team', $report);
    }

    /**
     * Do not reveal a hidden grade in the report.
     */
    public function test_hidden_grade_is_not_disclosed(): void {
        global $CFG;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course);
        $hunt = $this->create_hunt($course->id);
        require_once($CFG->libdir . '/gradelib.php');

        grade_update(
            'mod/treasurehunt',
            $course->id,
            'mod',
            'treasurehunt',
            $hunt->id,
            0,
            [$user->id => (object)['userid' => $user->id, 'rawgrade' => 80]],
            ['itemname' => $hunt->name, 'gradetype' => GRADE_TYPE_VALUE, 'grademax' => 100]
        );
        $item = \grade_item::fetch([
            'courseid' => $course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'treasurehunt',
            'iteminstance' => $hunt->id,
        ]);
        $item->set_hidden(1);
        $this->setUser($user);

        $report = $this->render_report($course, $user, $hunt);
        $this->assertStringContainsString(get_string('hidden', 'grades'), $report);
        $this->assertStringNotContainsString('80', $report);
    }
}
