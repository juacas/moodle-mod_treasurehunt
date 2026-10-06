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

/**
 * Check that the QR scanner is only presented after the player requests a test.
 *
 * @coversNothing
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
        $this->assertStringNotContainsString('<script src=', $html);
    }
}
