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
 * Geographic OpenData query tests.
 *
 * @package mod_treasurehunt
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_treasurehunt;

use mod_treasurehunt\opendata\wikidata;

/**
 * Validate geographic bounds and the controlled Wikidata response format.
 *
 * @covers \mod_treasurehunt\opendata\wikidata
 */
final class opendata_wikidata_test extends \advanced_testcase {
    /**
     * The search area grows by one full viewport width and height on each side.
     */
    public function test_buffer_and_limit(): void {
        $this->assertSame([-1.0, -2.0, 2.0, 4.0], wikidata::buffered_bounds([0, 0, 1, 2]));
        $this->assertSame([178.0, 75.0, 180.0, 90.0], wikidata::buffered_bounds([179, 80, 180, 85]));
        $this->expectException(\moodle_exception::class);
        wikidata::buffered_bounds([0, 0, 6, 1]);
    }

    /**
     * The category stays on a server-controlled Wikidata item and text remains a literal.
     */
    public function test_query_escapes_search_term(): void {
        $term = 'castle" } ?item ?p ?o #';
        $query = wikidata::build_query(['historic'], $term, [-3.8, 40.3, -3.6, 40.5], 'es');
        $this->assertStringContainsString('wd:Q1081138', $query);
        $this->assertStringContainsString('wd:Q839954', $query);
        $this->assertStringContainsString(json_encode($term), $query);
        $this->assertStringContainsString('Point(-3.800000 40.300000)', $query);
        $this->expectException(\moodle_exception::class);
        wikidata::build_query(['wd:Q5'], '', [-3.8, 40.3, -3.6, 40.5], 'es');
    }

    /**
     * Searching by historical period matches places associated with a named period.
     */
    public function test_period_matches_georeferenced_places(): void {
        $query = wikidata::build_query(['period'], 'roman', [-3.8, 40.3, -3.6, 40.5], 'en');
        $this->assertStringContainsString('?item wdt:P2348 ?period.', $query);
        $this->assertStringContainsString('?period rdfs:label ?searchLabel.', $query);
        $this->assertStringNotContainsString('?item rdfs:label ?searchLabel.', $query);
    }

    /**
     * Multiple checked types use one geographic query and combine their matches.
     */
    public function test_query_combines_categories_and_deduplicates_roots(): void {
        $query = wikidata::build_query(['historic', 'archaeology', 'period'], 'roman',
            [-3.8, 40.3, -3.6, 40.5], 'en');
        $this->assertStringContainsString('UNION', $query);
        $this->assertStringContainsString('?item wdt:P31/wdt:P279* ?root.', $query);
        $this->assertStringContainsString('?item wdt:P2348 ?period.', $query);
        $this->assertStringContainsString('?period rdfs:label ?searchLabel.', $query);
        $this->assertSame(1, substr_count($query, 'wd:Q839954'));
        $all = wikidata::build_query(['all', 'historic'], '', [-3.8, 40.3, -3.6, 40.5], 'en');
        $this->assertStringNotContainsString('?root', $all);
    }

    /**
     * A search without any checked type is rejected.
     */
    public function test_query_requires_a_category(): void {
        $this->expectException(\moodle_exception::class);
        wikidata::build_query([], '', [-3.8, 40.3, -3.6, 40.5], 'es');
    }

    /**
     * Nested request values never reach the SPARQL category builder.
     */
    public function test_query_rejects_nested_category(): void {
        $this->expectException(\moodle_exception::class);
        wikidata::build_query([['historic']], '', [-3.8, 40.3, -3.6, 40.5], 'es');
    }

    /**
     * Only valid Wikidata points enter the GeoJSON returned to the editor.
     */
    public function test_parse_filters_invalid_points_and_duplicate_items(): void {
        $valid = [
            'item' => ['value' => 'http://www.wikidata.org/entity/Q42'],
            'location' => ['value' => 'Point(-3.7 40.4)'],
            'itemLabel' => ['value' => 'Sample place'],
            'itemDescription' => ['value' => 'A historic site'],
        ];
        $invalid = $valid;
        $invalid['item']['value'] = 'https://example.com/other';
        $response = ['results' => ['bindings' => [$valid, $valid, $invalid]]];
        $result = wikidata::parse_results($response);
        $this->assertCount(1, $result['geojson']['features']);
        $this->assertSame([-3.7, 40.4], $result['geojson']['features'][0]['geometry']['coordinates']);
        $this->assertSame('https://www.wikidata.org/wiki/Q42', $result['geojson']['features'][0]['properties']['url']);
        $this->assertFalse($result['truncated']);
    }

    /**
     * A full page exposes the last returned item as the cursor for the next page.
     */
    public function test_search_page_cursor(): void {
        $bindings = [];
        for ($number = 1; $number <= 101; $number++) {
            $bindings[] = [
                'item' => ['value' => 'https://www.wikidata.org/entity/Q' . $number],
                'location' => ['value' => 'Point(-3.7 40.4)'],
            ];
        }
        $result = wikidata::parse_results(['results' => ['bindings' => $bindings]]);
        $this->assertCount(100, $result['geojson']['features']);
        $this->assertSame('Q100', $result['nextcursor']);
        $query = wikidata::build_query(['all'], '', [-3.8, 40.3, -3.6, 40.5], 'en', 'Q100');
        $this->assertStringContainsString('FILTER(STR(?item) > STR(wd:Q100))', $query);
        $this->assertStringContainsString('ORDER BY ?item LIMIT 101', $query);
    }

    /**
     * Detail queries cannot be redirected to arbitrary SPARQL entities.
     */
    public function test_details_query_restricts_item_identifier(): void {
        $query = wikidata::build_details_query('Q42');
        $this->assertStringContainsString('VALUES ?item { wd:Q42 }', $query);
        $this->assertStringContainsString('wdt:P18', $query);
        $this->assertStringContainsString('wdt:P856', $query);
        $this->assertStringContainsString('wdt:P973', $query);
        $this->assertStringContainsString('skos:altLabel', $query);
        $this->expectException(\moodle_exception::class);
        wikidata::build_details_query('Q42 } SERVICE <https://example.org> {');
    }

    /**
     * The card only receives valid images and websites, without duplicate aliases.
     */
    public function test_parse_details_filters_external_values(): void {
        $binding = static function(string $kind, string $value, string $language = ''): array {
            $result = ['kind' => ['value' => $kind], 'value' => ['value' => $value]];
            if ($language !== '') {
                $result['value']['xml:lang'] = $language;
            }
            return $result;
        };
        $response = ['results' => ['bindings' => [
            $binding('image', 'http://commons.wikimedia.org/wiki/Special:FilePath/Castillo%20viejo.jpg'),
            $binding('alias', 'Castillo antiguo', 'es'),
            $binding('alias', 'Castillo antiguo', 'es'),
            $binding('description', 'Old castle', 'en'),
            $binding('description', 'Castillo medieval', 'es'),
            $binding('official', 'https://example.org/castle'),
            $binding('official', 'javascript:alert(1)'),
            $binding('about', 'https://example.org/history'),
        ]]];
        $details = wikidata::parse_details($response, 'es');
        $this->assertSame('Castillo medieval', $details['description']);
        $this->assertSame(['Castillo antiguo'], $details['aliases']);
        $this->assertSame(
            'https://commons.wikimedia.org/wiki/Special:FilePath/Castillo%20viejo.jpg?width=480',
            $details['image']
        );
        $this->assertCount(2, $details['websites']);
        $this->assertSame('https://example.org/history', $details['websites'][1]['url']);
    }
}
