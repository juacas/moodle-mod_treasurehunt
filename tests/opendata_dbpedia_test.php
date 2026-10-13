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
 * DBpedia OpenData source tests without external requests.
 *
 * @package mod_treasurehunt
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_treasurehunt;

use mod_treasurehunt\opendata\dbpedia;
use mod_treasurehunt\opendata\sources;

/** @covers \mod_treasurehunt\opendata\dbpedia */
final class opendata_dbpedia_test extends \advanced_testcase {
    /** Search parameters are bounded and ontology classes come from the provider. */
    public function test_query_and_registration(): void {
        $this->assertInstanceOf(dbpedia::class, sources::get('dbpedia'));
        $query = dbpedia::build_query(['historic'], 'old "bridge"', [-4, 40, -3, 41], '100');
        $this->assertStringContainsString('dbo:HistoricPlace', $query);
        $this->assertStringContainsString('old \\"bridge\\"', $query);
        $this->assertStringContainsString('OFFSET 100', $query);
        $this->assertStringContainsString('?lon >= -4.000000', $query);
    }

    /** The browser cannot supply SPARQL syntax as a theme or a cursor. */
    public function test_invalid_cursor_is_rejected(): void {
        $this->expectException(\moodle_exception::class);
        dbpedia::build_query(['all'], '', [-4, 40, -3, 41], '0 UNION');
    }

    /** A related search keeps its category inside DBpedia's controlled namespace. */
    public function test_category_query(): void {
        $category = 'http://dbpedia.org/resource/Category:History_of_Madrid';
        $query = dbpedia::build_query(['all'], '', [-4, 40, -3, 41], '', $category);
        $this->assertStringContainsString('?item dct:subject <' . $category . '>', $query);
        $this->expectException(\moodle_exception::class);
        dbpedia::build_query(['all'], '', [-4, 40, -3, 41], '', 'http://example.com/category');
    }

    /** Discard untrusted and out-of-bounds items while advancing by raw rows. */
    public function test_parse_results_and_details(): void {
        $row = ['item' => ['value' => 'http://dbpedia.org/resource/Ateneo_de_Madrid'],
            'lat' => ['value' => '40.4'], 'lon' => ['value' => '-3.7'],
            'label' => ['value' => 'Ateneo de Madrid']];
        $outside = $row;
        $outside['lat']['value'] = '55';
        $result = dbpedia::parse_results(['results' => ['bindings' => [$row, $outside]]], [-4, 40, -3, 41], 0);
        $this->assertCount(1, $result['geojson']['features']);
        $feature = $result['geojson']['features'][0];
        $this->assertSame('dbpedia', $feature['properties']['source']);
        $this->assertSame([-3.7, 40.4], $feature['geometry']['coordinates']);
        $this->assertSame('https://dbpedia.org/page/Ateneo_de_Madrid', $feature['properties']['url']);

        $details = dbpedia::parse_details(['results' => ['bindings' => [
            ['kind' => ['value' => 'description'], 'value' => ['value' => 'Cultural centre']],
            ['kind' => ['value' => 'image'],
                'value' => ['value' => 'http://upload.wikimedia.org/wikipedia/commons/thumb/a/a1/photo.jpg']],
            ['kind' => ['value' => 'type'],
                'value' => ['value' => 'http://dbpedia.org/ontology/HistoricPlace'],
                'valueLabel' => ['value' => 'historic place']],
        ]] ]);
        $this->assertSame('Cultural centre', $details['description']);
        $this->assertSame('https://upload.wikimedia.org/wikipedia/commons/thumb/a/a1/photo.jpg', $details['image']);
        $this->assertSame('historic place', $details['types'][0]['label']);
        $this->assertSame('https://commons.wikimedia.org/wiki/Special:FilePath/Ateneo.jpg?width=480',
            dbpedia::thumbnail_url('http://commons.wikimedia.org/wiki/Special:FilePath/Ateneo.jpg?width=300'));
        $this->assertSame('', dbpedia::thumbnail_url('https://example.com/wikipedia/commons/photo.jpg'));
    }

    /** Category, localized summary, depictions and article link populate the DBpedia card. */
    public function test_extended_card_details(): void {
        $category = 'http://dbpedia.org/resource/Category:History_of_Madrid';
        $image = 'http://commons.wikimedia.org/wiki/Special:FilePath/Ateneo.jpg';
        $details = dbpedia::parse_details(['results' => ['bindings' => [
            ['kind' => ['value' => 'description'], 'value' => ['value' => 'Cultural centre', 'xml:lang' => 'en']],
            ['kind' => ['value' => 'description'], 'value' => ['value' => 'Centro cultural', 'xml:lang' => 'es']],
            ['kind' => ['value' => 'category'], 'value' => ['value' => $category]],
            ['kind' => ['value' => 'depiction'], 'value' => ['value' => $image]],
            ['kind' => ['value' => 'depiction'], 'value' => ['value' => $image]],
            ['kind' => ['value' => 'primary'],
                'value' => ['value' => 'https://en.wikipedia.org/wiki/Ateneo_de_Madrid']],
        ]]], 'es');
        $this->assertSame('Centro cultural', $details['description']);
        $this->assertSame([['id' => $category, 'label' => 'History of Madrid']], $details['categories']);
        $this->assertCount(1, $details['images']);
        $this->assertSame($details['images'][0], $details['image']);
        $this->assertSame('primary', $details['websites'][0]['type']);
        $query = dbpedia::build_details_query('http://dbpedia.org/resource/Ateneo_de_Madrid');
        foreach (['dct:subject', 'dbo:description', 'foaf:depiction', 'foaf:isPrimaryTopicOf'] as $property) {
            $this->assertStringContainsString($property, $query);
        }
        $this->assertStringNotContainsString('wikiPageExternalLink', $query);
    }

    /** Keep every distinct depiction returned for the item. */
    public function test_all_depictions_are_returned(): void {
        $bindings = [];
        for ($index = 1; $index <= 15; $index++) {
            $bindings[] = ['kind' => ['value' => 'depiction'],
                'value' => ['value' => 'http://commons.wikimedia.org/wiki/Special:FilePath/Image' . $index . '.jpg']];
        }
        $details = dbpedia::parse_details(['results' => ['bindings' => $bindings]]);
        $this->assertCount(15, $details['images']);
    }

    /** Only DBpedia resource targets become internal ontology links. */
    public function test_ontology_relations_are_validated(): void {
        $relation = ['kind' => ['value' => 'relation'],
            'property' => ['value' => 'http://dbpedia.org/ontology/architect'],
            'propertyLabel' => ['value' => 'architect'],
            'value' => ['value' => 'http://dbpedia.org/resource/Example_Architect'],
            'valueLabel' => ['value' => 'Example architect']];
        $unsafe = $relation;
        $unsafe['value']['value'] = 'https://example.org/resource/Other';
        $details = dbpedia::parse_details(['results' => ['bindings' => [$relation, $unsafe]]]);
        $this->assertSame([['source' => 'dbpedia', 'id' => 'http://dbpedia.org/resource/Example_Architect',
            'label' => 'Example architect', 'property' => 'architect']], $details['relations']);
    }

    /** SameAs links cross to Wikidata and polygon predicates produce geometry badges. */
    public function test_same_as_and_polygon_details(): void {
        $details = dbpedia::parse_details(['results' => ['bindings' => [
            ['kind' => ['value' => 'sameAs'],
                'value' => ['value' => 'http://www.wikidata.org/entity/Q42']],
            ['kind' => ['value' => 'asWKT'],
                'value' => ['value' => 'POLYGON((-3 40,-2 40,-2 41,-3 40))']],
            ['kind' => ['value' => 'polygon'],
                'value' => ['value' => 'POINT(-3 40)']],
            ['kind' => ['value' => 'geoGeometry'],
                'value' => ['value' => 'POINT(-3 40)']],
        ]]]);
        $this->assertSame('wikidata', $details['relations'][0]['source']);
        $this->assertSame('Q42', $details['relations'][0]['id']);
        $this->assertSame('owl:sameAs', $details['relations'][0]['property']);
        $this->assertSame('geosparql:asWKT', $details['geometries'][0]['source']);
        $this->assertCount(2, $details['geometries']);
        $this->assertSame('geo:geometry', $details['geometries'][1]['source']);
        $this->assertSame('Point', $details['geometries'][1]['type']);
        $query = dbpedia::build_details_query('http://dbpedia.org/resource/Example');
        foreach (['owl:sameAs', 'geosparql:asWKT', 'dbo:polygon', 'geo:geometry'] as $predicate) {
            $this->assertStringContainsString($predicate, $query);
        }
    }
}
