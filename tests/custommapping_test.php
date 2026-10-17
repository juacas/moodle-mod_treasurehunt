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
 * Tests for custom map configuration values.
 *
 * @package mod_treasurehunt
 * @copyright 2026
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_treasurehunt;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');

/**
 * Test image layers with no WMS-specific form fields.
 *
 * @covers ::treasurehunt_build_custommappingconfig
 */
final class custommapping_test extends \advanced_testcase {
    /**
     * An uploaded image does not require the hidden WMS URL or parameter fields.
     */
    public function test_image_layer_without_wms_fields_and_with_affine_corners(): void {
        $this->resetAfterTest();
        $formdata = (object)[
            'customlayertype' => 'overlay',
            'customlayerservicetype' => 'image',
            'customlayername' => 'Historic map',
            'custompreserveaspectratio' => 0,
            'customimagecorners' => json_encode([
                [-1.0, 42.0], [-0.9, 42.01], [-0.89, 41.91], [-0.99, 41.9],
            ]),
        ];

        $config = treasurehunt_build_custommappingconfig($formdata);

        $this->assertSame('', $config->wmsurl);
        $this->assertSame('image', $config->layerservicetype);
        $this->assertCount(4, $config->imagecorners);
        $this->assertSame([-1.0, 42.0], $config->imagecorners[0]);
    }

    /**
     * Malformed corner data is ignored instead of being stored as a partial transform.
     */
    public function test_malformed_affine_corners_are_ignored(): void {
        $formdata = (object)[
            'customlayertype' => 'overlay',
            'customlayerservicetype' => 'image',
            'customimagecorners' => '{"invalid":true}',
        ];

        $config = treasurehunt_build_custommappingconfig($formdata);

        $this->assertObjectNotHasProperty('imagecorners', $config);
    }
}
