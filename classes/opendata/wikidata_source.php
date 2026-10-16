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
 * Wikidata provider adapter.
 *
 * @package mod_treasurehunt
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_treasurehunt\opendata;

/**
 * Expose the existing Wikidata client through the common source contract.
 */
final class wikidata_source implements source {
    /**
     * Return the stable source ID.
     */
    public function id(): string {
        return 'wikidata';
    }

    /** Return the public Wikidata website. */
    public function website(): string {
        return 'https://www.wikidata.org/';
    }

    /**
     * Return available theme IDs and language keys.
     */
    public function themes(): array {
        return [
            'all' => 'opendataall',
            'historic' => 'opendatahistoric',
            'industrial' => 'opendataindustrial',
            'monument' => 'opendatamonument',
            'art' => 'opendataart',
            'period' => 'opendataperiod',
            'archaeology' => 'opendataarchaeology',
        ];
    }

    /**
     * Whether this source can be queried.
     */
    public function available(): bool {
        return true;
    }

    /**
     * Search one page of georeferenced items.
     */
    public function search_page(array $themes, string $term, array $viewport,
            string $language, string $cursor): array {
        $page = wikidata::search($themes, $term, $viewport, $language, $cursor);
        foreach ($page['geojson']['features'] as &$feature) {
            $feature['properties']['theme'] = count($themes) === 1 ? $themes[0] : 'all';
        }
        unset($feature);
        return $page;
    }

    /**
     * Return optional metadata for one item.
     */
    public function item_details(string $itemid, string $language): array {
        return wikidata::details($itemid, $language);
    }
}
