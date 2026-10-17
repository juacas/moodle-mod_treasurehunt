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
 * Validate and select OpenData geometry using the module's GeoJSON/WKT library.
 *
 * @package mod_treasurehunt
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_treasurehunt\opendata;

/**
 * Keep provider shapes bounded and compatible with the stage editor.
 */
final class geometry {
    /**
     * Maximum size of a provider geometry string.
     */
    private const MAX_CHARS = 200000;

    /**
     * Maximum number of coordinates in a geometry.
     */
    private const MAX_POINTS = 10000;

    /**
     * Maximum number of polygon components accepted from a provider.
     */
    private const MAX_POLYGONS = 100;

    /**
     * Load the existing WKT/GeoJSON adapters and their geometry classes.
     */
    private static function load_library(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');
    }

    /**
     * Read a provider WKT literal and return a validated stage geometry descriptor.
     *
     * @param mixed $value WKT literal, optionally prefixed by the CRS84 IRI.
     * @param string $source Predicate shown in the editor.
     * @return array|null Geometry descriptor.
     */
    public static function from_wkt($value, string $source): ?array {
        if (!is_string($value) || strlen($value) > self::MAX_CHARS) {
            return null;
        }
        $value = preg_replace('~^<http://www\.opengis\.net/def/crs/OGC/1\.3/CRS84>\s*~i', '', trim($value));
        if (!preg_match('/^(?:POINT|POLYGON|MULTIPOLYGON)\s*\([\dEe+.,()\s-]+\)$/iD', $value)) {
            return null;
        }
        self::load_library();
        try {
            return self::describe(\treasurehunt_wkt_to_object($value), $source);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /**
     * Read polygon features from Commons map data with the module's GeoJSON adapter.
     *
     * @param array $data GeoJSON geometry, feature, collection, or map config.
     * @param string $source Predicate shown in the editor.
     * @return array|null Geometry descriptor.
     */
    public static function from_geojson(array $data, string $source): ?array {
        self::load_library();
        if (is_array($data['data'] ?? null)) {
            $data = $data['data'];
        }
        $polygons = [];
        $firstpoint = null;
        self::collect($data, $source, $polygons, $firstpoint, 0);
        if ($polygons) {
            try {
                return self::describe(new \MultiPolygon($polygons), $source);
            } catch (\Throwable $exception) {
                return null;
            }
        }
        return $firstpoint;
    }

    /**
     * Traverse GeoJSON containers; the existing adapter parses their geometry leaves.
     *
     * @param array $data Current GeoJSON node.
     * @param string $source Geometry source.
     * @param array $polygons Collected polygon objects.
     * @param array|null $firstpoint First valid point descriptor.
     * @param int $depth Nesting limit.
     */
    private static function collect(
        array $data,
        string $source,
        array &$polygons,
        ?array &$firstpoint,
        int $depth
    ): void {
        if ($depth > 5 || count($polygons) > self::MAX_POLYGONS) {
            return;
        }
        switch ($data['type'] ?? '') {
            case 'FeatureCollection':
                $children = $data['features'] ?? [];
                break;
            case 'GeometryCollection':
                $children = $data['geometries'] ?? [];
                break;
            case 'Feature':
                $children = [$data['geometry'] ?? null];
                break;
            default:
                $children = null;
        }
        if ($children !== null) {
            if (!is_array($children)) {
                return;
            }
            foreach (array_slice($children, 0, self::MAX_POLYGONS + 1) as $child) {
                if (is_array($child)) {
                    self::collect($child, $source, $polygons, $firstpoint, $depth + 1);
                }
            }
            return;
        }
        if (!in_array($data['type'] ?? '', ['Point', 'Polygon', 'MultiPolygon'], true)) {
            return;
        }
        try {
            $object = \treasurehunt_geojson_to_object($data);
            if ($object instanceof \Point) {
                $firstpoint ??= self::describe($object, $source);
            } else if ($object instanceof \Polygon) {
                $polygons[] = $object;
            } else if ($object instanceof \MultiPolygon) {
                array_push($polygons, ...$object->getComponents());
            }
        } catch (\Throwable $exception) {
            debugging(
                'Ignoring malformed provider geometry: ' . $exception->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }

    /**
     * Validate geographic coordinates and serialize a normalized library geometry.
     *
     * @param mixed $object Parsed geometry object.
     * @param string $source Predicate shown in the editor.
     * @return array|null Geometry descriptor.
     */
    private static function describe($object, string $source): ?array {
        if ($object instanceof \Point) {
            [$longitude, $latitude] = $object->getCoordinates();
            if (!self::valid_coordinate($longitude, $latitude)) {
                return null;
            }
            $point = new \Point(sprintf('%.8F', $longitude), sprintf('%.8F', $latitude));
            return ['source' => $source, 'type' => 'Point', 'wkt' => \treasurehunt_geometry_to_wkt($point),
                'coordinates' => [(float)$longitude, (float)$latitude]];
        }
        if ($object instanceof \Polygon) {
            $polygons = [$object];
        } else if ($object instanceof \MultiPolygon) {
            $polygons = $object->getComponents();
        } else {
            return null;
        }
        if (!$polygons || count($polygons) > self::MAX_POLYGONS) {
            return null;
        }
        $normalized = [];
        $pointcount = 0;
        foreach ($polygons as $polygon) {
            if (!$polygon instanceof \Polygon) {
                return null;
            }
            $rings = $polygon->getCoordinates();
            if (!$rings || count($rings) > 20) {
                return null;
            }
            $newrings = [];
            foreach ($rings as $ring) {
                if (!is_array($ring) || count($ring) < 4) {
                    return null;
                }
                $points = [];
                $area = 0.0;
                $previous = null;
                foreach ($ring as $coordinate) {
                    if (
                        !is_array($coordinate) || count($coordinate) !== 2 ||
                            !self::valid_coordinate($coordinate[0], $coordinate[1]) ||
                            ++$pointcount > self::MAX_POINTS
                    ) {
                        return null;
                    }
                    $point = [(float)$coordinate[0], (float)$coordinate[1]];
                    if ($previous !== null) {
                        $area += $previous[0] * $point[1] - $point[0] * $previous[1];
                    }
                    $previous = $point;
                    $points[] = new \Point(sprintf('%.8F', $point[0]), sprintf('%.8F', $point[1]));
                }
                if ($ring[0] != $ring[count($ring) - 1] || abs($area) < 1e-12) {
                    return null;
                }
                $newrings[] = new \LinearRing($points);
            }
            $normalized[] = new \Polygon($newrings);
        }
        $wkt = \treasurehunt_geometry_to_wkt(new \MultiPolygon($normalized));
        return strlen($wkt) <= self::MAX_CHARS ?
            ['source' => $source, 'type' => count($normalized) === 1 ? 'Polygon' : 'MultiPolygon', 'wkt' => $wkt] : null;
    }

    /**
     * Check one WGS84 longitude/latitude pair.
     */
    private static function valid_coordinate($longitude, $latitude): bool {
        return is_numeric($longitude) && is_numeric($latitude) && is_finite((float)$longitude) &&
            is_finite((float)$latitude) && abs((float)$longitude) <= 180 && abs((float)$latitude) <= 90;
    }

    /**
     * Pick a valid area across an original item and its sameAs attachments.
     * Polygons outrank points; for equal geometry types the original item wins.
     *
     * @param array $metadata Original metadata, including collected resources.
     * @return array|null Normalized geometry descriptor.
     */
    public static function best_for_stage(array $metadata): ?array {
        $best = null;
        $bestscore = -1;
        $candidates = [['geometries' => $metadata['geometries'] ?? [], 'priority' => 1]];
        foreach (array_slice(is_array($metadata['collected'] ?? null) ? $metadata['collected'] : [], 0, 12) as $item) {
            if (
                is_array($item) && ($item['relation'] ?? '') === 'owl:sameAs' &&
                    is_array($item['metadata']['geometries'] ?? null)
            ) {
                $candidates[] = ['geometries' => $item['metadata']['geometries'], 'priority' => 0];
            }
        }
        foreach ($candidates as $candidate) {
            if (!is_array($candidate['geometries'])) {
                continue;
            }
            foreach (array_slice($candidate['geometries'], 0, 8) as $raw) {
                if (!is_array($raw) || !is_string($raw['wkt'] ?? null)) {
                    continue;
                }
                $shape = self::from_wkt($raw['wkt'], is_string($raw['source'] ?? null) ? $raw['source'] : '');
                if ($shape === null || ($shape['type'] === 'Point' && abs($shape['coordinates'][1]) > 85)) {
                    continue;
                }
                $score = ($shape['type'] === 'Point' ? 0 : 2) + $candidate['priority'];
                if ($score > $bestscore) {
                    $best = $shape;
                    $bestscore = $score;
                }
            }
        }
        return $best;
    }
}
