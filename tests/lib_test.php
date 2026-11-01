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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests for the Treasure hunt module library.
 *
 * @package    mod_treasurehunt
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_treasurehunt;

/**
 * Tests for features declared by Treasure hunt.
 */
final class lib_test extends \advanced_testcase {
    /**
     * Load the module library.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/mod/treasurehunt/lib.php');
    }

    /**
     * The activity chooser shows Treasure hunt under its two supported purposes.
     */
    public function test_activity_chooser_purposes(): void {
        $this->assertSame(MOD_PURPOSE_INTERACTIVECONTENT, treasurehunt_supports(FEATURE_MOD_PURPOSE));
        if (defined('FEATURE_MOD_OTHERPURPOSE')) {
            $this->assertSame(MOD_PURPOSE_ASSESSMENT, treasurehunt_supports(FEATURE_MOD_OTHERPURPOSE));
        }
    }
}
