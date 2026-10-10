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
 * Registry for the editor's OpenData sources.
 *
 * @package mod_treasurehunt
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_treasurehunt\opendata;

/**
 * Keep source selection on the server; clients submit only stable IDs.
 */
final class sources {
    /**
     * Return all registered providers.
     *
     * @return array Registered providers by ID.
     */
    public static function all(): array {
        return [
            'wikidata' => new wikidata_source(),
            'europeana' => new europeana(),
        ];
    }

    /**
     * Find a registered provider.
     *
     * @param string $id Provider ID.
     * @return source Validated provider.
     */
    public static function get(string $id): source {
        $providers = self::all();
        if (!isset($providers[$id])) {
            throw new \moodle_exception('opendatainvalidsearch', 'treasurehunt');
        }
        return $providers[$id];
    }
}
