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
 * Contract for geographic OpenData sources.
 *
 * @package mod_treasurehunt
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_treasurehunt\opendata;

/**
 * A source offers themes and returns one page of normalized GeoJSON features.
 */
interface source {
    /** Return the stable source identifier.
     *
     * @return string Source ID.
     */
    public function id(): string;

    /** Return the selectable themes.
     *
     * @return array Theme ID to language-string key.
     */
    public function themes(): array;

    /** Check whether the source is configured.
     *
     * @return bool Whether the source has the configuration needed to search.
     */
    public function available(): bool;

    /**
     * Search one page. Return geojson and a nextcursor, empty when complete.
     *
     * @param array $themes Selected theme IDs.
     * @param string $term Optional text.
     * @param array $viewport Visible geographic bounds.
     * @param string $language Preferred language.
     * @param string $cursor Opaque source cursor, empty for the first page.
     * @return array Search page.
     */
    public function search_page(array $themes, string $term, array $viewport,
            string $language, string $cursor): array;

    /**
     * Fetch optional card details for one item.
     *
     * @param string $itemid Source item ID.
     * @param string $language Preferred language.
     * @return array Card details.
     */
    public function item_details(string $itemid, string $language): array;
}
