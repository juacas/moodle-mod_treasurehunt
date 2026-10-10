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
 * Creating stages from OpenData points.
 *
 * @package mod_treasurehunt
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_treasurehunt;

use mod_treasurehunt\opendata\stage_creator;

/**
 * Verify the created stage has a usable area and a safe initial clue.
 *
 * @covers \mod_treasurehunt\opendata\stage_creator
 */
final class opendata_stage_creator_test extends \advanced_testcase {
    /**
     * Insert one stage into the selected road and keep the source text escaped.
     */
    public function test_create_stage_with_area_and_clue(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');
        $course = $this->getDataGenerator()->create_course();
        $huntid = $DB->insert_record('treasurehunt', (object)[
            'course' => $course->id, 'name' => 'OpenData test', 'intro' => '',
        ]);
        $moduleid = $DB->get_field('modules', 'id', ['name' => 'treasurehunt'], MUST_EXIST);
        $cmid = $DB->insert_record('course_modules', (object)[
            'course' => $course->id, 'module' => $moduleid, 'instance' => $huntid,
            'added' => time(), 'visible' => 1,
        ]);
        course_add_cm_to_section($course, $cmid, 0, null, 'treasurehunt');
        $roadid = $DB->insert_record('treasurehunt_roads', (object)[
            'treasurehuntid' => $huntid, 'name' => 'Chosen road',
        ]);
        $stageid = stage_creator::create($roadid, 'Old bridge', '<script>alert(1)</script>',
            -3.7, 40.4, \context_module::instance($cmid));
        $stage = $DB->get_record('treasurehunt_stages', ['id' => $stageid], '*', MUST_EXIST);
        $this->assertSame((string)$roadid, (string)$stage->roadid);
        $this->assertSame('Old bridge', $stage->name);
        $this->assertStringNotContainsString('<script>', $stage->clueforstage);
        $this->assertSame(1, (int)$stage->position);
        $geometry = \treasurehunt_wkt_to_object($stage->geom);
        $this->assertCount(1, $geometry->getComponents());
        $this->assertFalse((bool)$DB->get_field('treasurehunt_roads', 'validated', ['id' => $roadid]));
    }

    /**
     * Invalid coordinates do not create a malformed stage geometry.
     */
    public function test_rejects_out_of_range_point(): void {
        $this->expectException(\moodle_exception::class);
        stage_creator::geometry_for_point(200, 40);
    }
}
