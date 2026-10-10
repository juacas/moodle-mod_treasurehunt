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

    /**
     * Record metadata supplies readable type and subject tags with trusted links.
     */
    public function test_parse_record_details(): void {
        $record = [
            'proxies' => [[
                'dcType' => ['en' => ['Image', 'Photography'],
                    'def' => ['http://vocab.getty.edu/aat/300162056']],
                'dcSubject' => ['def' => ['http://data.europeana.eu/concept/94',
                    'http://data.europeana.eu/place/204487']],
                'dcCreator' => ['en' => ['Photographer']],
                'dcDate' => ['def' => ['1932']],
            ]],
            'concepts' => [[
                'about' => 'http://vocab.getty.edu/aat/300162056',
                'prefLabel' => ['en' => ['black-and-white photography']],
            ], [
                'about' => 'http://data.europeana.eu/concept/94',
                'prefLabel' => ['es' => ['Arquitectura']],
            ]],
            'places' => [[
                'about' => 'http://data.europeana.eu/place/204487',
                'prefLabel' => ['es' => ['Cataluña']],
            ]],
            'europeanaAggregation' => [
                'edmPreview' => 'https://api.europeana.eu/thumbnail/v2/url.json?uri=sample',
            ],
        ];
        $details = europeana::parse_details($record, 'es');
        $this->assertSame(['Image', 'Photography', 'black-and-white photography'],
            array_column($details['types'], 'label'));
        $this->assertSame(['Arquitectura', 'Cataluña'], array_column($details['subjects'], 'label'));
        $this->assertSame('https://www.europeana.eu/en/collections/topic/94', $details['subjects'][0]['url']);
        $this->assertSame('https://api.europeana.eu/thumbnail/v2/url.json?uri=sample', $details['image']);
        $this->assertSame(['Photographer'], $details['fields']['creator']);
        $this->assertSame(['1932'], $details['fields']['date']);
        $this->assertSame('', europeana::thumbnail_url('https://example.com/thumbnail/v2/sample'));
    }
}
