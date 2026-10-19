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
 * Mapbox player integration tests.
 *
 * @package mod_treasurehunt
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_treasurehunt;

/**
 * Check the player registration and its server-side configuration.
 *
 * @coversNothing
 */
final class mapbox_player_test extends \advanced_testcase {
    /**
     * A selected Mapbox player must render the GLB URL and the configured public token.
     */
    public function test_registered_player_renders_configuration(): void {
        global $CFG, $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');
        set_config('mapboxaccesstoken', 'pk.test-public-token', 'mod_treasurehunt');

        $this->assertSame('mapbox', TREASUREHUNT_PLAYERMAPBOX);
        $this->assertArrayHasKey(TREASUREHUNT_PLAYERMAPBOX, treasurehunt_get_installedplayerstyles());
        $this->assertTrue(class_exists(\mod_treasurehunt\output\play_page_mapbox_player::class));

        $course = $this->getDataGenerator()->create_course();
        $hunt = (object)[
            'course' => $course->id,
            'name' => 'Mapbox test mission',
            'intro' => '',
            'description' => '',
            'playwithoutmoving' => 1,
            'tracking' => 0,
            'groupmode' => 0,
            'customplayerconfig' => '{}',
            'playerstyle' => TREASUREHUNT_PLAYERMAPBOX,
        ];
        $hunt->id = $DB->insert_record('treasurehunt', $hunt);
        $moduleid = $DB->get_field('modules', 'id', ['name' => 'treasurehunt'], MUST_EXIST);
        $cmid = $DB->insert_record('course_modules', (object)[
            'course' => $course->id,
            'module' => $moduleid,
            'instance' => $hunt->id,
            'added' => time(),
            'visible' => 1,
        ]);
        course_add_cm_to_section($course, $cmid, 0, null, 'treasurehunt');
        $cm = get_fast_modinfo($course)->get_cm($cmid);

        $PAGE->set_url('/mod/treasurehunt/play.php', ['id' => $cmid]);
        $PAGE->set_course($course);
        $PAGE->set_cm($cm, $course);
        $PAGE->set_context(\context_module::instance($cmid));
        $renderable = new \mod_treasurehunt\output\play_page_mapbox_player($hunt, $cm);
        $renderable->gameupdatetime = 10000;

        ob_start();
        try {
            $html = $PAGE->get_renderer('mod_treasurehunt')->render($renderable);
        } finally {
            ob_end_clean();
        }
        $this->assertStringContainsString('id="cesium-globe"', $html);
        $this->assertStringContainsString('Mapbox GL', $html);
        $this->assertStringContainsString('pk.test-public-token', $html);
        $this->assertStringContainsString('pegman_hiker_all_animations_matte.glb', $html);
        $this->assertStringNotContainsString('"roadtimestamp":', $html);
    }
}
