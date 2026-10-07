<?php
// This file is part of Moodle - http://moodle.org/.
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
 * Copying stages between roads.
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
 * Verify append, replacement and road boundaries.
 *
 */
final class copy_stages_test extends \advanced_testcase {
    /**
     * Make a minimal activity with a course module.
     *
     * @param \stdClass $course Course.
     * @return array Activity id and context.
     */
    private function create_hunt(\stdClass $course): array {
        global $DB;
        $huntid = $DB->insert_record('treasurehunt', (object)[
            'course' => $course->id, 'name' => 'Copy test', 'intro' => '',
        ]);
        $moduleid = $DB->get_field('modules', 'id', ['name' => 'treasurehunt'], MUST_EXIST);
        $cmid = $DB->insert_record('course_modules', (object)[
            'course' => $course->id, 'module' => $moduleid, 'instance' => $huntid,
            'added' => time(), 'visible' => 1,
        ]);
        course_add_cm_to_section($course, $cmid, 0, null, 'treasurehunt');
        return [$huntid, \context_module::instance($cmid)];
    }

    /**
     * Create a road for the test.
     *
     * @param int $huntid Activity id.
     * @param string $name Road name.
     * @return int Road id.
     */
    private function create_road(int $huntid, string $name): int {
        global $DB;
        return $DB->insert_record('treasurehunt_roads', (object)[
            'treasurehuntid' => $huntid, 'name' => $name,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Create a stage for the test.
     *
     * @param int $roadid Road id.
     * @param int $position Stage position.
     * @param string $name Stage name.
     * @return int Stage id.
     */
    private function create_stage(int $roadid, int $position, string $name): int {
        global $DB;
        return $DB->insert_record('treasurehunt_stages', (object)[
            'roadid' => $roadid, 'position' => $position, 'name' => $name,
            'cluetext' => 'Clue ' . $name, 'questiontext' => 'Question ' . $name,
            'qrtext' => 'QR ' . $name, 'geom' => 'MULTIPOLYGON (((0 0, 0 1, 1 1, 1 0, 0 0)))',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Appending preserves order and content; replacing removes old stages and files.
     */
    public function test_append_and_replace_with_answers_and_files(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        [$huntid, $context] = $this->create_hunt($course);
        $sourceid = $this->create_road($huntid, 'Source');
        $targetid = $this->create_road($huntid, 'Target');
        $firstid = $this->create_stage($sourceid, 1, 'First');
        $this->create_stage($sourceid, 2, 'Second');
        $oldid = $this->create_stage($targetid, 1, 'Existing');
        $answerid = $DB->insert_record('treasurehunt_answers', (object)[
            'stageid' => $firstid, 'answertext' => 'Yes', 'correct' => 1,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $storage = get_file_storage();
        foreach ([['cluetext', $firstid], ['answertext', $answerid], ['cluetext', $oldid]] as [$area, $itemid]) {
            $storage->create_file_from_string([
                'contextid' => $context->id, 'component' => 'mod_treasurehunt',
                'filearea' => $area, 'itemid' => $itemid, 'filepath' => '/',
                'filename' => 'asset-' . $itemid . '.txt', 'mimetype' => 'text/plain',
            ], 'asset content');
        }

        $this->assertSame(2, treasurehunt_copy_stages($sourceid, $targetid, $huntid, false, $context));
        $stages = array_values($DB->get_records('treasurehunt_stages', ['roadid' => $targetid], 'position ASC'));
        $this->assertSame(['Existing', 'First', 'Second'], array_column($stages, 'name'));
        $this->assertSame([1, 2, 3], array_map('intval', array_column($stages, 'position')));
        $this->assertEquals(1, $DB->get_field('treasurehunt_roads', 'validated', ['id' => $targetid]));
        $this->assertSame('QR First', $stages[1]->qrtext);
        $this->assertSame(1, $DB->count_records('treasurehunt_answers', ['stageid' => $stages[1]->id]));
        $copiedanswer = $DB->get_record('treasurehunt_answers', ['stageid' => $stages[1]->id], '*', MUST_EXIST);
        $this->assertCount(1, $storage->get_area_files(
            $context->id,
            'mod_treasurehunt',
            'answertext',
            $copiedanswer->id,
            'id',
            false
        ));
        $this->assertCount(1, $storage->get_area_files(
            $context->id,
            'mod_treasurehunt',
            'cluetext',
            $stages[1]->id,
            'id',
            false
        ));

        $this->assertSame(2, treasurehunt_copy_stages($sourceid, $targetid, $huntid, true, $context));
        $stages = array_values($DB->get_records('treasurehunt_stages', ['roadid' => $targetid], 'position ASC'));
        $this->assertSame(['First', 'Second'], array_column($stages, 'name'));
        $this->assertFalse($DB->record_exists('treasurehunt_stages', ['id' => $oldid]));
        $this->assertCount(0, $storage->get_area_files(
            $context->id,
            'mod_treasurehunt',
            'cluetext',
            $oldid,
            'id',
            false
        ));
    }

    /**
     * Foreign roads and roads with attempts cannot be changed.
     */
    public function test_reject_foreign_and_blocked_destinations(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        [$huntid, $context] = $this->create_hunt($course);
        [$otherid] = $this->create_hunt($course);
        $sourceid = $this->create_road($huntid, 'Source');
        $targetid = $this->create_road($huntid, 'Target');
        $foreignid = $this->create_road($otherid, 'Foreign');
        $this->create_stage($sourceid, 1, 'First');
        $targetstageid = $this->create_stage($targetid, 1, 'Existing');
        try {
            treasurehunt_copy_stages($foreignid, $targetid, $huntid, false, $context);
            $this->fail('A road from another activity was accepted.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('invalidentry', $e->errorcode);
        }
        $DB->insert_record('treasurehunt_attempts', (object)[
            'stageid' => $targetstageid, 'userid' => 1, 'timecreated' => time(),
        ]);
        $this->expectException(\moodle_exception::class);
        treasurehunt_copy_stages($sourceid, $targetid, $huntid, true, $context);
    }
}
