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
 * Europeana source tests without external API requests.
 *
 * @package mod_treasurehunt
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_treasurehunt;

use mod_treasurehunt\opendata\europeana;
use mod_treasurehunt\opendata\sources;

/**
 * Keep provider themes, geographic filtering and pagination predictable.
 *
 * @covers \mod_treasurehunt\opendata\europeana
 * @covers \mod_treasurehunt\opendata\sources
 */
final class opendata_europeana_test extends \advanced_testcase {
    /**
     * Themes are controlled by the provider and the API key is never part of the public parameters.
     */
    public function test_theme_query_and_private_key(): void {
        $this->resetAfterTest();
        $source = sources::get('europeana');
        set_config('europeanaapikey', '', 'mod_treasurehunt');
        $this->assertFalse($source->available());
        set_config('europeanaapikey', 'private-test-key', 'mod_treasurehunt');
        $this->assertTrue($source->available());
        $params = $source->request_params(['art'], 'old "castle"', [-4, 40, -3, 41], '');
        $this->assertSame('art', $params['theme']);
        $this->assertSame('"old \\"castle\\""', $params['query']);
        $this->assertSame('*', $params['cursor']);
        $this->assertStringContainsString('pl_wgs84_pos_lat:[40.000000 TO 41.000000]', $params['qf']);
        $this->assertStringNotContainsString('private-test-key', json_encode($params));
        $audiovisual = $source->request_params(['audiovisual'], '', [-4, 40, -3, 41], 'next');
        $this->assertArrayNotHasKey('theme', $audiovisual);
        $this->assertStringContainsString('TYPE:VIDEO OR TYPE:SOUND', $audiovisual['qf']);
        $this->assertSame('next', $audiovisual['cursor']);
    }

    /**
     * Invalid themes cannot become Europeana query filters.
     */
    public function test_invalid_theme_is_rejected(): void {
        $this->expectException(\moodle_exception::class);
        (new europeana())->request_params(['art OR *'], '', [-4, 40, -3, 41], '');
    }

    /**
     * Only georeferenced items within the buffered area enter the shared map layer.
     */
    public function test_parse_results_and_cursor(): void {
        $valid = [
            'id' => '/202001/123',
            'title' => ['Old bridge'],
            'dcDescription' => ['Historic crossing'],
            'edmPlaceLatitude' => ['40.5'],
            'edmPlaceLongitude' => ['-3.5'],
            'edmPreview' => ['https://api.europeana.eu/thumbnail/1'],
            'guid' => 'https://www.europeana.eu/en/item/202001/123',
            'edmIsShownAt' => ['https://provider.example/item'],
        ];
        $outside = $valid;
        $outside['id'] = '/202001/456';
        $outside['edmPlaceLatitude'] = ['55'];
        $result = europeana::parse_results(['items' => [$valid, $outside], 'nextCursor' => 'abc'],
            [-4, 40, -3, 41], '*');
        $this->assertCount(1, $result['geojson']['features']);
        $feature = $result['geojson']['features'][0];
        $this->assertSame('europeana', $feature['properties']['source']);
        $this->assertSame('Old bridge', $feature['properties']['name']);
        $this->assertSame([-3.5, 40.5], $feature['geometry']['coordinates']);
        $this->assertSame('abc', $result['nextcursor']);
    }
}
