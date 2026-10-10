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
        $stageid = stage_creator::create($roadid, 'Old bridge', -3.7, 40.4,
            \context_module::instance($cmid), 'europeana', [
                'description' => '<script>alert(1)</script>',
                'image' => 'https://api.europeana.eu/thumbnail/v2/url.json?uri=sample&type=IMAGE',
                'types' => [['label' => 'Photography', 'url' => '']],
                'subjects' => [['label' => 'Architecture',
                    'url' => 'https://www.europeana.eu/en/collections/topic/94']],
                'aliases' => ['Ancient bridge'],
                'fields' => ['creator' => ['Photographer'], 'date' => ['1932']],
                'websites' => [['type' => 'official', 'url' => 'https://example.org/bridge']],
            ]);
        $stage = $DB->get_record('treasurehunt_stages', ['id' => $stageid], '*', MUST_EXIST);
        $this->assertSame((string)$roadid, (string)$stage->roadid);
        $this->assertSame('Old bridge', $stage->name);
        $this->assertStringNotContainsString('<script>', $stage->clueforstage);
        $this->assertStringContainsString('<img src="https://api.europeana.eu/thumbnail/v2/url.json?',
            $stage->clueforstage);
        $this->assertStringContainsString('&amp;type=IMAGE', $stage->clueforstage);
        $this->assertStringContainsString('Photography', $stage->clueforstage);
        $this->assertStringContainsString('Architecture', $stage->clueforstage);
        $this->assertStringContainsString('Ancient bridge', $stage->clueforstage);
        $this->assertStringContainsString('Photographer', $stage->clueforstage);
        $this->assertStringContainsString('1932', $stage->clueforstage);
        $this->assertStringContainsString('https://example.org/bridge', $stage->clueforstage);
        $this->assertSame(1, (int)$stage->position);
        $this->assertSame(0, (int)$stage->playstagewithoutmoving);
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

    /**
     * Wikidata thumbnails and useful metadata are included without trusting provider markup.
     */
    public function test_wikidata_clue_includes_image_and_escapes_metadata(): void {
        $html = stage_creator::clue_html('Castle', 'wikidata', [
            'description' => 'A <b>historic</b> castle',
            'image' => 'https://commons.wikimedia.org/wiki/Special:FilePath/Castle.jpg?width=480',
            'types' => [['label' => '<script>alert(1)</script>', 'url' => 'javascript:alert(1)']],
            'categories' => [['label' => 'Castles in Spain']],
            'url' => 'https://www.wikidata.org/wiki/Q123',
        ]);
        $this->assertStringContainsString('<img src="https://commons.wikimedia.org/wiki/Special:FilePath/Castle.jpg?width=480"',
            $html);
        $this->assertStringContainsString('A &lt;b&gt;historic&lt;/b&gt; castle', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
        $this->assertStringContainsString('Castles in Spain', $html);
        $this->assertStringContainsString('https://www.wikidata.org/wiki/Q123', $html);
    }
}
