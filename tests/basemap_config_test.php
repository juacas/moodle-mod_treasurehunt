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
 * Base map availability and authorization tests.
 *
 * @package mod_treasurehunt
 * @copyright 2026 Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @author Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_treasurehunt;

/**
 * Ensure the teacher can only authorize configured maps.
 *
 * @coversNothing
 */
final class basemap_config_test extends \advanced_testcase {
    /**
     * Optional map providers only appear after the site configures access.
     */
    public function test_available_maps_follow_site_credentials(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');
        unset_config('availablebasemaps', 'mod_treasurehunt');
        set_config('googlemapstileapikey', '', 'mod_treasurehunt');
        set_config('bingmapskey', '', 'mod_treasurehunt');
        set_config('stadiamapsapikey', '', 'mod_treasurehunt');
        set_config('stadiamapsdomainauth', 0, 'mod_treasurehunt');
        set_config('availableplayerstyles', 'bootstrap', 'mod_treasurehunt');

        $maps = treasurehunt_get_available_basemaps();
        $this->assertArrayHasKey('osm', $maps);
        $this->assertArrayHasKey('arcgis_world_imagery', $maps);
        $this->assertArrayNotHasKey('google_satellite', $maps);
        $this->assertArrayNotHasKey('bing_aerial_labels', $maps);

        set_config('googlemapstileapikey', 'test-key', 'mod_treasurehunt');
        set_config('stadiamapsdomainauth', 1, 'mod_treasurehunt');
        $maps = treasurehunt_get_available_basemaps();
        $this->assertArrayHasKey('google_satellite', $maps);
        $this->assertArrayHasKey('stadia_stamen_watercolor', $maps);
        set_config('availablebasemaps', 'osm,google_satellite', 'mod_treasurehunt');
        $maps = treasurehunt_get_available_basemaps();
        $this->assertSame(['osm', 'google_satellite'], array_keys($maps));
        set_config('availablebasemaps', 'osm,mapbox_satellite', 'mod_treasurehunt');
        set_config('mapboxaccesstoken', 'test-token', 'mod_treasurehunt');
        set_config('availableplayerstyles', 'bootstrap,cesium', 'mod_treasurehunt');
        $this->assertArrayNotHasKey('mapbox_satellite', treasurehunt_get_available_basemaps());
        set_config('availableplayerstyles', 'bootstrap,cesium,mapbox', 'mod_treasurehunt');
        $this->assertArrayHasKey('mapbox_satellite', treasurehunt_get_available_basemaps());
        set_config('availablebasemaps', 'osm,google_satellite,mapbox_satellite', 'mod_treasurehunt');
        set_config('availableplayerstyles', 'mapbox', 'mod_treasurehunt');
        $this->assertArrayNotHasKey('google_satellite', treasurehunt_get_available_basemaps());
        $this->assertArrayHasKey('mapbox_satellite', treasurehunt_get_available_basemaps());
    }

    /**
     * Invalid or unconfigured selections cannot reach a player.
     */
    public function test_authorized_maps_are_filtered_and_defaults_are_safe(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');
        unset_config('availablebasemaps', 'mod_treasurehunt');
        set_config('googlemapstileapikey', '', 'mod_treasurehunt');
        set_config('bingmapskey', '', 'mod_treasurehunt');
        set_config('stadiamapsapikey', '', 'mod_treasurehunt');
        set_config('stadiamapsdomainauth', 0, 'mod_treasurehunt');
        set_config('availableplayerstyles', 'bootstrap', 'mod_treasurehunt');

        $this->assertSame(
            array_keys(treasurehunt_get_available_basemaps()),
            treasurehunt_filter_allowed_basemaps(null)
        );
        $this->assertSame([], treasurehunt_filter_allowed_basemaps([]));
        $this->assertSame(['osm'], treasurehunt_filter_allowed_basemaps(['fake', 'osm', 'google_satellite']));
        $this->assertContains('arcgis_world_imagery', treasurehunt_get_player_basemaps(TREASUREHUNT_PLAYERMAPBOX));
        $this->assertNotContains('google_satellite', treasurehunt_get_player_basemaps(TREASUREHUNT_PLAYERMAPBOX));
        $this->assertTrue(treasurehunt_has_usable_basemap(['osm'], TREASUREHUNT_PLAYERMAPBOX, 'none'));
        $this->assertFalse(treasurehunt_has_usable_basemap(['google_satellite'], TREASUREHUNT_PLAYERMAPBOX, 'none'));
        $this->assertFalse(treasurehunt_has_usable_basemap([], TREASUREHUNT_PLAYERBOOTSTRAP, 'overlay'));
        $this->assertTrue(treasurehunt_has_usable_basemap([], TREASUREHUNT_PLAYERBOOTSTRAP, 'base'));
    }

    /**
     * Saving the activity preserves the teacher's choice in player configuration.
     */
    public function test_player_configuration_keeps_selected_maps(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');
        unset_config('availablebasemaps', 'mod_treasurehunt');
        set_config('googlemapstileapikey', '', 'mod_treasurehunt');
        set_config('availableplayerstyles', 'bootstrap', 'mod_treasurehunt');

        $data = (object)[
            'searchpaneldisabled' => 0,
            'localizationbuttondisabled' => 0,
            'showheadinghint' => 0,
            'showinzonehint' => 0,
            'showdistancehint' => 0,
            'shownextareahint' => 0,
            'allowedbasemaps' => ['arcgis_world_imagery', 'google_satellite'],
        ];
        $configuration = treasurehunt_build_customplayerconfig($data);
        $this->assertSame(['arcgis_world_imagery'], $configuration->allowedbasemaps);
        $data->allowedbasemaps = [];
        $this->assertSame([], treasurehunt_build_customplayerconfig($data)->allowedbasemaps);
    }

    /**
     * Cesium's bundled catalog is authorizable without unrelated provider keys.
     */
    public function test_cesium_catalog_is_available_only_when_player_enabled(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');
        unset_config('availablebasemaps', 'mod_treasurehunt');
        set_config('googlemapstileapikey', '', 'mod_treasurehunt');
        set_config('availableplayerstyles', 'cesium', 'mod_treasurehunt');

        $maps = treasurehunt_get_available_basemaps();
        $this->assertArrayHasKey('cesium_blue_marble', $maps);
        $this->assertArrayHasKey('cesium_azure_roads', $maps);
        $this->assertArrayHasKey('google_satellite', $maps);
        $this->assertStringContainsString('(Cesium)', $maps['google_satellite']);
        $this->assertArrayNotHasKey('mapbox_satellite', $maps);

        set_config('availableplayerstyles', 'bootstrap', 'mod_treasurehunt');
        $this->assertArrayNotHasKey('cesium_blue_marble', treasurehunt_get_available_basemaps());
        $this->assertArrayNotHasKey('google_satellite', treasurehunt_get_available_basemaps());
    }
}
