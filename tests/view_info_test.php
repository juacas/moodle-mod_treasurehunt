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
 * Tests for the activity information shown on the main page.
 *
 * @package mod_treasurehunt
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_treasurehunt;

use mod_treasurehunt\output\info;
use mod_treasurehunt\output\users_progress;

/**
 * Check the activity information and progress shown on the main page.
 *
 */
final class view_info_test extends \advanced_testcase {
    /**
     * The scanner panel starts hidden even when the activity contains QR stages.
     */
    public function test_qr_scanner_panel_starts_hidden(): void {
        global $PAGE;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $PAGE->set_url(new \moodle_url('/mod/treasurehunt/view.php', ['id' => 1]));
        $PAGE->set_context(\context_course::instance($course->id));
        $PAGE->set_course($course);
        $hunt = (object)[
            'course' => $course->id,
            'allowattemptsfromdate' => 0,
            'cutoffdate' => 0,
            'playwithoutmoving' => 0,
            'groupmode' => 0,
            'tracking' => 0,
            'grade' => 0,
        ];
        $renderable = new info($hunt, time(), $course->id, [], 1, false);
        $html = $PAGE->get_renderer('mod_treasurehunt')->render($renderable);

        $this->assertMatchesRegularExpression('/<div[^>]*id="QRStatusDiv"[^>]*hidden="hidden"/', $html);
        $this->assertStringContainsString('id="treasurehunt-qr-status"', $html);
        $this->assertStringContainsString(
            'data-qr-passed-label="' . get_string('activitysummaryqrscan', 'treasurehunt') . '"',
            $html
        );
        $this->assertStringNotContainsString('<script src=', $html);
        $this->assertStringContainsString(get_string('activitysummarysequential', 'treasurehunt'), $html);
        $this->assertStringContainsString(get_string('activitysummarysequential_help', 'treasurehunt'), $html);
        $this->assertStringContainsString('fa fa-road', $html);
        $this->assertStringContainsString('data-treasurehunt-tooltip=', $html);
    }

    /**
     * Team play is shown as a feature and each assigned group is linked beside its road.
     */
    public function test_team_mode_card_and_road_group_link(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $PAGE->set_url(new \moodle_url('/mod/treasurehunt/view.php', ['id' => 1]));
        $PAGE->set_context(\context_course::instance($course->id));
        $PAGE->set_course($course);
        $hunt = (object)[
            'course' => $course->id,
            'allowattemptsfromdate' => 0,
            'cutoffdate' => 0,
            'playwithoutmoving' => 0,
            'groupmode' => 1,
            'tracking' => 0,
            'grade' => 0,
        ];
        $renderer = $PAGE->get_renderer('mod_treasurehunt');
        $infohtml = $renderer->render(new info($hunt, time(), $course->id, [], 0, true));
        $this->assertStringContainsString('fa fa-users', $infohtml);
        $this->assertStringContainsString(get_string('groupmode', 'treasurehunt'), $infohtml);
        $this->assertStringContainsString(get_string('activitysummaryjointeam', 'treasurehunt'), $infohtml);
        $this->assertMatchesRegularExpression(
            '/<span class="treasurehunt-stage-status-label">' .
                preg_quote(get_string('activitysummarytracking', 'treasurehunt'), '/') .
                '<\/span>\s*<\/button>/',
            $infohtml
        );
        $this->assertStringContainsString(get_string('activitysummaryoutofsequence', 'treasurehunt'), $infohtml);
        $this->assertStringContainsString(get_string('activitysummaryoutofsequence_help', 'treasurehunt'), $infohtml);
        $this->assertStringContainsString('fa fa-random', $infohtml);

        $group = (object)['id' => 23, 'name' => 'Team Alpha', 'ratings' => []];
        $road = (object)['name' => 'Path One', 'validated' => true, 'userlist' => [$group], 'totalstages' => 2];
        $progress = new users_progress([$road], true, 1, [], [], [], false, true);
        $progresshtml = $renderer->render($progress);
        $this->assertStringContainsString('Path One', $progresshtml);
        $this->assertStringContainsString('Team Alpha', $progresshtml);
        $this->assertStringContainsString('group=23', $progresshtml);
    }

    /**
     * Students see optional feature cards only when the feature is enabled.
     */
    public function test_student_sees_only_enabled_optional_cards(): void {
        global $PAGE;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course);
        $this->setUser($student);
        $PAGE->set_url(new \moodle_url('/mod/treasurehunt/view.php', ['id' => 1]));
        $PAGE->set_context(\context_course::instance($course->id));
        $PAGE->set_course($course);
        $hunt = (object)[
            'course' => $course->id,
            'allowattemptsfromdate' => 0,
            'cutoffdate' => 0,
            'playwithoutmoving' => 0,
            'groupmode' => 0,
            'tracking' => 0,
            'grade' => 0,
        ];
        $renderer = $PAGE->get_renderer('mod_treasurehunt');
        $disabled = $renderer->render(new info($hunt, time(), $course->id, [], 0, false));
        $this->assertStringNotContainsString(get_string('activitysummaryqr', 'treasurehunt'), $disabled);
        $this->assertStringNotContainsString(get_string('activitysummarytracking', 'treasurehunt'), $disabled);
        $this->assertStringNotContainsString(get_string('groupmode', 'treasurehunt'), $disabled);
        $this->assertStringContainsString(get_string('activitysummarysequential', 'treasurehunt'), $disabled);

        $hunt->groupmode = 1;
        $hunt->tracking = 1;
        $enabled = $renderer->render(new info($hunt, time(), $course->id, [], 1, false));
        $this->assertStringContainsString(get_string('activitysummaryqr', 'treasurehunt'), $enabled);
        $this->assertStringContainsString(get_string('activitysummarytracking', 'treasurehunt'), $enabled);
        $this->assertStringContainsString(get_string('groupmode', 'treasurehunt'), $enabled);

        $this->setAdminUser();
        $hunt->groupmode = 0;
        $hunt->tracking = 0;
        $manager = $renderer->render(new info($hunt, time(), $course->id, [], 0, false));
        $this->assertStringContainsString(get_string('activitysummaryqr', 'treasurehunt'), $manager);
        $this->assertStringContainsString(get_string('activitysummarytracking', 'treasurehunt'), $manager);
        $this->assertStringContainsString(get_string('groupmode', 'treasurehunt'), $manager);
    }
}
