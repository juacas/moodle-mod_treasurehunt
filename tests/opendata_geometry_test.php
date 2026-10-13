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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * Polygon normalization for OpenData stage areas.
 *
 * @package mod_treasurehunt
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_treasurehunt;

use mod_treasurehunt\opendata\geometry;

/** @covers \mod_treasurehunt\opendata\geometry */
final class opendata_geometry_test extends \advanced_testcase {
    /** Provider WKT is normalized to a playable multipolygon. */
    public function test_wkt_polygon_and_crs84(): void {
        $shape = geometry::from_wkt('<http://www.opengis.net/def/crs/OGC/1.3/CRS84> ' .
            'POLYGON((-3 40,-2 40,-2 41,-3 40))', 'geosparql:asWKT');
        $this->assertSame('Polygon', $shape['type']);
        $this->assertStringStartsWith('MULTIPOLYGON(((-3.00000000 40.00000000', $shape['wkt']);
        $this->assertNull(geometry::from_wkt('POLYGON((200 40,201 40,201 41,200 40))', 'dbo:polygon'));
        $point = geometry::from_wkt('POINT(-3 40)', 'geo:geometry');
        $this->assertSame('Point', $point['type']);
        $this->assertSame([-3.0, 40.0], $point['coordinates']);
        $this->assertNull(geometry::from_wkt('POINT(200 40)', 'geo:geometry'));
        $this->assertNull(geometry::from_wkt('POLYGON((0 0,1 1,2 2,0 0))', 'dbo:polygon'));
    }

    /** Commons map data can contain several GeoJSON polygon features. */
    public function test_commons_map_geojson(): void {
        $map = ['data' => ['type' => 'FeatureCollection', 'features' => [[
            'type' => 'Feature', 'geometry' => ['type' => 'Polygon', 'coordinates' => [[
                [-3, 40], [-2, 40], [-2, 41], [-3, 40],
            ]]],
        ]]]];
        $shape = geometry::from_geojson($map, 'wdt:P3896');
        $this->assertSame('wdt:P3896', $shape['source']);
        $this->assertSame('Polygon', $shape['type']);
        $this->assertSame('Point', geometry::from_geojson(
            ['type' => 'Point', 'coordinates' => [-3, 40]], 'wdt:P3896')['type']);
    }

    /** Choose a polygon even when another source reports a point first. */
    public function test_best_geometry_across_sources(): void {
        $point = geometry::from_wkt('POINT(-3 40)', 'geo:geometry');
        $polygon = geometry::from_wkt('POLYGON((-3 40,-2 40,-2 41,-3 40))', 'wdt:P3896');
        $metadata = ['geometries' => [$point], 'collected' => [[
            'relation' => 'owl:sameAs', 'metadata' => ['geometries' => [$polygon]],
        ]]];
        $this->assertSame('wdt:P3896', geometry::best_for_stage($metadata)['source']);
        $metadata['geometries'][] = geometry::from_wkt('POLYGON((-4 40,-3 40,-3 41,-4 40))', 'dbo:polygon');
        $this->assertSame('dbo:polygon', geometry::best_for_stage($metadata)['source']);
        $metadata['collected'][0]['relation'] = 'other';
        $metadata['geometries'] = [$point];
        $this->assertSame('geo:geometry', geometry::best_for_stage($metadata)['source']);
    }
}
