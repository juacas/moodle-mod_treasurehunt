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
 * Cesium player integration tests.
 *
 * @package mod_treasurehunt
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_treasurehunt;

/**
 * Check that the experimental player can be selected and its shell rendered.
 *
 * @coversNothing
 */
final class cessium_player_test extends \advanced_testcase {
    /**
     * The registered style must load its renderable and render all core controls.
     */
    public function test_registered_player_renders_template(): void {
        global $CFG, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');

        $styles = treasurehunt_get_installedplayerstyles();
        $this->assertArrayHasKey(TREASUREHUNT_PLAYERCESSIUM, $styles);
        $this->assertTrue(class_exists(\mod_treasurehunt\output\play_page_cessium_player::class));
        $this->assertTrue(property_exists(\mod_treasurehunt\output\play_page_cessium_player::class, 'totalstages'));

        $PAGE->set_url('/mod/treasurehunt/play.php', ['id' => 1]);
        $PAGE->set_context(\context_system::instance());
        $renderer = $PAGE->get_renderer('mod_treasurehunt');
        $html = $renderer->render_from_template('mod_treasurehunt/cessium_player', [
            'cmid' => 1,
            'huntname' => 'Cesium test mission',
        ]);
        $this->assertStringContainsString('id="cessium-globe"', $html);
        $this->assertStringContainsString('id="cessium-clue-dialog"', $html);
        $this->assertStringContainsString('id="cessium-submit"', $html);
        $this->assertStringContainsString('Cesium test mission', $html);
    }

    /**
     * A fullscreen player must not initialise Moodle's hidden activity header component.
     */
    public function test_fullscreen_player_does_not_initialise_hidden_activity_header(): void {
        global $CFG, $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');

        $course = $this->getDataGenerator()->create_course();
        $hunt = (object)[
            'course' => $course->id,
            'name' => 'Cesium test mission',
            'intro' => '',
            'description' => '',
            'playwithoutmoving' => 1,
            'tracking' => 0,
            'groupmode' => 0,
            'customplayerconfig' => '{}',
            'playerstyle' => TREASUREHUNT_PLAYERCESSIUM,
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
        $renderable = new \mod_treasurehunt\output\play_page_cessium_player($hunt, $cm);
        $renderable->lastattempttimestamp = 0;
        $renderable->lastroadtimestamp = 0;
        $renderable->gameupdatetime = 10000;
        $renderable->totalstages = 2;

        ob_start();
        try {
            $html = $PAGE->get_renderer('mod_treasurehunt')->render($renderable);
        } finally {
            ob_end_clean();
        }
        $this->assertStringContainsString('id="cessium-globe"', $html);
        $this->assertStringNotContainsString('core_courseformat/local/content/activity_header', $html);
    }
}
