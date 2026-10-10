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
 * Create a playable stage from a georeferenced OpenData item.
 *
 * @package mod_treasurehunt
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_treasurehunt\opendata;

/**
 * Build a small discovery area and insert the stage using the normal road revision flow.
 */
final class stage_creator {
    /**
     * Make a roughly 40 metre square around a geographic point.
     *
     * @param float $longitude WGS84 longitude.
     * @param float $latitude WGS84 latitude.
     * @return string Multipolygon WKT accepted by the editor and player.
     */
    public static function geometry_for_point(float $longitude, float $latitude): string {
        if (!is_finite($longitude) || !is_finite($latitude) || abs($longitude) > 180 || abs($latitude) > 85) {
            throw new \moodle_exception('opendatainvalidsearch', 'treasurehunt');
        }
        $halfheight = 20 / 111320;
        $halfwidth = 20 / (111320 * cos(deg2rad($latitude)));
        $west = max(-180, $longitude - $halfwidth);
        $east = min(180, $longitude + $halfwidth);
        $south = $latitude - $halfheight;
        $north = $latitude + $halfheight;
        $coordinates = [[$west, $south], [$east, $south], [$east, $north], [$west, $north], [$west, $south]];
        $points = array_map(static fn($point) => sprintf('%.8F %.8F', $point[0], $point[1]), $coordinates);
        return 'MULTIPOLYGON(((' . implode(',', $points) . ')))';
    }

    /**
     * Insert the stage and its initial clue, then announce its creation.
     *
     * @param int $roadid Destination road, already authorized by the caller.
     * @param string $name Stage name.
     * @param string $description Source description, used as the initial clue.
     * @param float $longitude WGS84 longitude.
     * @param float $latitude WGS84 latitude.
     * @param \context_module $context Activity context.
     * @return int New stage ID.
     */
    public static function create(int $roadid, string $name, string $description, float $longitude,
            float $latitude, \context_module $context): int {
        global $CFG, $DB;
        $name = trim($name);
        $description = trim($description);
        if ($name === '' || \core_text::strlen($name) > 255 || \core_text::strlen($description) > 2000) {
            throw new \moodle_exception('opendatainvalidsearch', 'treasurehunt');
        }
        require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');
        $stage = (object)[
            'roadid' => $roadid,
            'name' => $name,
            'cluetext' => '',
            'cluetextformat' => FORMAT_HTML,
            'cluetexttrust' => 0,
            'clueforstage' => '<p>' . s($description !== '' ? $description : $name) . '</p>',
            'clueforstageformat' => FORMAT_HTML,
            'clueforstagetrust' => 0,
            'questiontext' => '',
            'questiontextformat' => FORMAT_HTML,
            'questiontexttrust' => 0,
            'timecreated' => time(),
            'geom' => self::geometry_for_point($longitude, $latitude),
        ];
        $transaction = $DB->start_delegated_transaction();
        $stage->id = \treasurehunt_insert_stage_form($stage);
        $event = \mod_treasurehunt\event\stage_created::create([
            'context' => $context,
            'objectid' => $stage->id,
            'other' => $stage->name,
        ]);
        $event->add_record_snapshot('treasurehunt_stages',
            $DB->get_record('treasurehunt_stages', ['id' => $stage->id], '*', MUST_EXIST));
        $event->trigger();
        $transaction->allow_commit();
        return $stage->id;
    }
}
