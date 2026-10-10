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
     * Accept ordinary external links while keeping markup supplied by providers inert.
     *
     * @param mixed $url Candidate URL.
     * @return string Safe link or empty string.
     */
    private static function link_url($url): string {
        if (!is_string($url) || strlen($url) > 2000 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }
        $parts = parse_url($url);
        return is_array($parts) && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) &&
            !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass']) ? $url : '';
    }

    /**
     * Render normalized provider metadata for the teacher's editable stage clue.
     *
     * @param string $name Item title.
     * @param string $sourceid Provider ID.
     * @param array $metadata Search item and detail metadata.
     * @return string Safe HTML.
     */
    public static function clue_html(string $name, string $sourceid, array $metadata): string {
        $description = is_string($metadata['description'] ?? null) ?
            trim(\core_text::substr($metadata['description'], 0, 2000)) : '';
        $html = '<p>' . s($description !== '' ? $description : $name) . '</p>';
        $image = is_string($metadata['image'] ?? null) ? $metadata['image'] : '';
        $image = $sourceid === 'wikidata' ? wikidata::image_url($image) :
            ($sourceid === 'europeana' ? europeana::thumbnail_url($image) : '');
        if ($image !== '') {
            $html .= '<p><img src="' . s($image) . '" alt="' . s($name) . '" class="img-fluid"></p>';
        }
        $groups = [
            'types' => 'opendataitemtypes',
            'subjects' => 'opendatasubjects',
            'categories' => 'opendatacategories',
            'aliases' => 'opendataaliases',
        ];
        foreach ($groups as $key => $labelkey) {
            $values = is_array($metadata[$key] ?? null) ? array_slice($metadata[$key], 0, 20) : [];
            $items = [];
            foreach ($values as $value) {
                $label = is_array($value) ? ($value['label'] ?? '') : $value;
                if (!is_string($label) || trim($label) === '') {
                    continue;
                }
                $item = s(\core_text::substr(trim($label), 0, 200));
                $url = is_array($value) ? self::link_url($value['url'] ?? '') : '';
                $items[] = $url !== '' ? '<a href="' . s($url) . '">' . $item . '</a>' : $item;
            }
            if ($items) {
                $html .= '<p><strong>' . s(get_string($labelkey, 'treasurehunt')) . ':</strong> ' .
                    implode(', ', $items) . '</p>';
            }
        }
        $fields = is_array($metadata['fields'] ?? null) ? $metadata['fields'] : [];
        foreach (['creator', 'date', 'location', 'publisher', 'provider', 'language', 'rights'] as $field) {
            $values = is_array($fields[$field] ?? null) ? array_slice($fields[$field], 0, 8) : [];
            $values = array_filter($values, static fn($value) => is_string($value) && trim($value) !== '');
            if ($values) {
                $values = array_map(static fn($value) => s(\core_text::substr(trim($value), 0, 200)), $values);
                $html .= '<p><strong>' . s(get_string('opendatafield' . $field, 'treasurehunt')) . ':</strong> ' .
                    implode(', ', $values) . '</p>';
            }
        }
        $links = [];
        $itemurl = self::link_url($metadata['url'] ?? '');
        if ($itemurl !== '') {
            $links[$itemurl] = get_string('opendata' . $sourceid, 'treasurehunt');
        }
        $websites = is_array($metadata['websites'] ?? null) ? array_slice($metadata['websites'], 0, 8) : [];
        foreach ($websites as $website) {
            $url = is_array($website) ? self::link_url($website['url'] ?? '') : '';
            if ($url !== '') {
                $key = ($website['type'] ?? '') === 'official' ? 'opendataofficialwebsite' : 'opendataaboutwebsite';
                $links[$url] = get_string($key, 'treasurehunt');
            }
        }
        if ($links) {
            $html .= '<p><strong>' . s(get_string('opendatalinks', 'treasurehunt')) . ':</strong> ';
            $parts = [];
            foreach ($links as $url => $label) {
                $parts[] = '<a href="' . s($url) . '">' . s($label) . '</a>';
            }
            $html .= implode(' · ', $parts) . '</p>';
        }
        return $html;
    }

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
     * @param float $longitude WGS84 longitude.
     * @param float $latitude WGS84 latitude.
     * @param \context_module $context Activity context.
     * @param string $sourceid Provider ID.
     * @param array $metadata Normalized item details to render for the teacher.
     * @return int New stage ID.
     */
    public static function create(int $roadid, string $name, float $longitude, float $latitude,
            \context_module $context, string $sourceid, array $metadata): int {
        global $CFG, $DB;
        $name = trim($name);
        if ($name === '' || \core_text::strlen($name) > 255 || !in_array($sourceid, ['wikidata', 'europeana'], true)) {
            throw new \moodle_exception('opendatainvalidsearch', 'treasurehunt');
        }
        $clue = self::clue_html($name, $sourceid, $metadata);
        require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');
        $stage = (object)[
            'roadid' => $roadid,
            'name' => $name,
            'cluetext' => '',
            'cluetextformat' => FORMAT_HTML,
            'cluetexttrust' => 0,
            'clueforstage' => $clue,
            'clueforstageformat' => FORMAT_HTML,
            'clueforstagetrust' => 0,
            'questiontext' => '',
            'questiontextformat' => FORMAT_HTML,
            'questiontexttrust' => 0,
            'playstagewithoutmoving' => 0,
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
