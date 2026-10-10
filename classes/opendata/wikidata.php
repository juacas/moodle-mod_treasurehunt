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
 * Bounded geographic searches in the public Wikidata Query Service.
 *
 * @package mod_treasurehunt
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_treasurehunt\opendata;

/**
 * Fetch georeferenced Wikidata items for a temporary editor layer.
 */
final class wikidata {
    /** Maximum number of items returned to the editor. */
    public const MAX_RESULTS = 100;

    /** Semantic category roots; IDs are controlled here, never supplied as SPARQL by the browser. */
    private const TYPES = [
        'all' => [],
        'historic' => ['Q1081138', 'Q839954'],
        'industrial' => ['Q1569871'],
        'monument' => ['Q4989906'],
        'art' => ['Q838948'],
        'period' => [],
        'archaeology' => ['Q839954'],
    ];

    /**
     * Expand the visible bounds by 100 percent of their width and height on each side.
     *
     * @param array $viewport [west, south, east, north] in geographic degrees.
     * @return array Buffered bounds.
     */
    public static function buffered_bounds(array $viewport): array {
        if (count($viewport) !== 4) {
            throw new \moodle_exception('opendatainvalidarea', 'treasurehunt');
        }
        [$west, $south, $east, $north] = array_map('floatval', $viewport);
        if (!is_finite($west) || !is_finite($south) || !is_finite($east) || !is_finite($north) ||
                $west < -180 || $east > 180 || $south < -90 || $north > 90 || $west >= $east || $south >= $north) {
            throw new \moodle_exception('opendatainvalidarea', 'treasurehunt');
        }
        $width = $east - $west;
        $height = $north - $south;
        return [max(-180.0, $west - $width), max(-90.0, $south - $height),
            min(180.0, $east + $width), min(90.0, $north + $height)];
    }

    /**
     * Build a SPARQL query from bounded coordinates and controlled categories.
     *
     * @param array $types Semantic categories.
     * @param string $term Optional label fragment.
     * @param array $bounds Buffered geographic bounds.
     * @param string $language Preferred language.
     * @return string SPARQL query.
     */
    public static function build_query(array $types, string $term, array $bounds, string $language,
            string $cursor = ''): string {
        if (!$types || count($types) > count(self::TYPES) || count($bounds) !== 4 ||
                \core_text::strlen($term) > 100 || ($cursor !== '' && !preg_match('/^Q[1-9][0-9]*$/D', $cursor))) {
            throw new \moodle_exception('opendatainvalidsearch', 'treasurehunt');
        }
        foreach ($types as $type) {
            if (!is_string($type) || !array_key_exists($type, self::TYPES)) {
                throw new \moodle_exception('opendatainvalidsearch', 'treasurehunt');
            }
        }
        $types = array_values(array_unique($types));
        $language = in_array($language, ['es', 'el', 'en'], true) ? $language : 'en';
        [$west, $south, $east, $north] = array_map(static fn($value) => sprintf('%.6F', $value), $bounds);
        $term = trim($term);
        $literal = json_encode($term, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $labelmatch = static function(string $target) use ($term, $literal): string {
            return $term === '' ? '' : $target . ' rdfs:label ?searchLabel. ' .
                'FILTER(LANG(?searchLabel) IN ("es", "en", "el") && ' .
                'CONTAINS(LCASE(STR(?searchLabel)), LCASE(' . $literal . '))) ';
        };
        $branches = [];
        if (in_array('all', $types, true)) {
            $branches[] = $labelmatch('?item');
        } else {
            $roots = [];
            foreach ($types as $type) {
                $roots = array_merge($roots, self::TYPES[$type]);
            }
            $roots = array_unique($roots);
            if ($roots) {
                $branches[] = 'VALUES ?root { ' . implode(' ', array_map(static fn($id) => 'wd:' . $id, $roots)) .
                    ' } ?item wdt:P31/wdt:P279* ?root. ' . $labelmatch('?item');
            }
            if (in_array('period', $types, true)) {
                $branches[] = '?item wdt:P2348 ?period. ' . $labelmatch('?period');
            }
        }
        $typefilter = count($branches) > 1 ? '{ ' . implode(' } UNION { ', $branches) . ' }' : $branches[0];
        return "SELECT DISTINCT ?item ?itemLabel ?itemDescription ?location WHERE {\n  " .
            "SERVICE wikibase:box {\n    ?item wdt:P625 ?location.\n    " .
            'bd:serviceParam wikibase:cornerWest "Point(' . $west . ' ' . $south . ')"^^geo:wktLiteral. ' .
            'bd:serviceParam wikibase:cornerEast "Point(' . $east . ' ' . $north . ')"^^geo:wktLiteral.' .
            "\n  }\n  " . $typefilter . "\n  " .
            ($cursor === '' ? '' : 'FILTER(STR(?item) > STR(wd:' . $cursor . ')) ') .
            'SERVICE wikibase:label { bd:serviceParam wikibase:language "' . $language . ',es,en". }' .
            "\n} ORDER BY ?item LIMIT " . (self::MAX_RESULTS + 1);
    }

    /**
     * Convert the service's SPARQL JSON into a constrained GeoJSON layer.
     *
     * @param array $response Decoded SPARQL response.
     * @return array GeoJSON and a truncation flag.
     */
    public static function parse_results(array $response): array {
        $features = [];
        $bindings = $response['results']['bindings'] ?? [];
        foreach (array_slice($bindings, 0, self::MAX_RESULTS) as $binding) {
            $item = $binding['item']['value'] ?? '';
            $point = $binding['location']['value'] ?? '';
            if (!preg_match('~^https?://www\.wikidata\.org/entity/(Q[0-9]+)$~', $item, $match) ||
                    !preg_match('/^Point\((-?[0-9]+(?:\.[0-9]+)?) (-?[0-9]+(?:\.[0-9]+)?)\)$/', $point, $coords)) {
                continue;
            }
            $longitude = (float)$coords[1];
            $latitude = (float)$coords[2];
            if ($longitude < -180 || $longitude > 180 || $latitude < -90 || $latitude > 90) {
                continue;
            }
            $id = $match[1];
            if (isset($features[$id])) {
                continue;
            }
            $features[$id] = [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => [$longitude, $latitude]],
                'properties' => [
                    'source' => 'wikidata',
                    'id' => $id,
                    'name' => \core_text::substr($binding['itemLabel']['value'] ?? $id, 0, 200),
                    'description' => \core_text::substr($binding['itemDescription']['value'] ?? '', 0, 400),
                    'url' => 'https://www.wikidata.org/wiki/' . $id,
                    'detailsready' => false,
                ],
            ];
            if (count($features) >= self::MAX_RESULTS) {
                break;
            }
        }
        return [
            'geojson' => ['type' => 'FeatureCollection', 'features' => array_values($features)],
            'truncated' => count($bindings) > self::MAX_RESULTS,
            'nextcursor' => count($bindings) > self::MAX_RESULTS ? (string)array_key_last($features) : '',
        ];
    }

    /**
     * Build a bounded detail query for one Wikidata item.
     *
     * @param string $itemid Wikidata Q identifier.
     * @return string SPARQL query.
     */
    public static function build_details_query(string $itemid): string {
        if (!preg_match('/^Q[1-9][0-9]*$/', $itemid)) {
            throw new \moodle_exception('opendatainvalidsearch', 'treasurehunt');
        }
        return "SELECT ?kind ?value ?valueLabel WHERE {\n  VALUES ?item { wd:" . $itemid . " }\n  " .
            '{ ?item wdt:P18 ?value. BIND("image" AS ?kind) }' . "\n  UNION " .
            '{ ?item wdt:P856 ?value. BIND("official" AS ?kind) }' . "\n  UNION " .
            '{ ?item wdt:P973 ?value. BIND("about" AS ?kind) }' . "\n  UNION " .
            '{ ?item skos:altLabel ?value. FILTER(LANG(?value) IN ("es", "en", "el")) ' .
            'BIND("alias" AS ?kind) }' . "\n  UNION " .
            '{ ?item schema:description ?value. FILTER(LANG(?value) IN ("es", "en", "el")) ' .
            'BIND("description" AS ?kind) }' . "\n  UNION " .
            '{ ?item wdt:P31 ?value. BIND("type" AS ?kind) }' . "\n  UNION " .
            '{ ?item wdt:P373 ?value. BIND("category" AS ?kind) }' . "\n  UNION " .
            '{ ?item wdt:P571 ?value. BIND("date" AS ?kind) }' . "\n  UNION " .
            '{ ?item wdt:P131 ?value. BIND("location" AS ?kind) }' . "\n  UNION " .
            '{ ?item wdt:P170 ?value. BIND("creator" AS ?kind) }' . "\n  " .
            'SERVICE wikibase:label { bd:serviceParam wikibase:language "es,en,el". }' . "\n}" .
            ' ORDER BY (IF(?kind = "image", 0, IF(?kind = "official", 1, ' .
            'IF(?kind = "about", 2, IF(?kind = "description", 3, 4))))) LIMIT 200';
    }

    /**
     * Use a small Commons thumbnail URL instead of an unbounded original image.
     *
     * @param string $url Commons FilePath URL returned by Wikidata.
     * @return string Thumbnail URL, or empty string for unsupported values.
     */
    public static function image_url(string $url): string {
        $parts = parse_url($url);
        $prefix = '/wiki/Special:FilePath/';
        if (!is_array($parts) || strtolower($parts['host'] ?? '') !== 'commons.wikimedia.org' ||
                !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) ||
                !str_starts_with($parts['path'] ?? '', $prefix)) {
            return '';
        }
        $filename = rawurldecode(substr($parts['path'], strlen($prefix)));
        if ($filename === '' || strlen($filename) > 255 || str_contains($filename, '/') ||
                str_contains($filename, '\\') || !preg_match('/\.(jpe?g|png|gif|webp|svg)$/i', $filename)) {
            return '';
        }
        return 'https://commons.wikimedia.org/wiki/Special:FilePath/' . rawurlencode($filename) . '?width=480';
    }

    /**
     * Restrict external links to ordinary public web URLs.
     *
     * @param string $url Wikidata URL value.
     * @return bool Whether it is suitable for a browser link.
     */
    private static function valid_website(string $url): bool {
        $parts = parse_url($url);
        return strlen($url) <= 1000 && filter_var($url, FILTER_VALIDATE_URL) !== false &&
            is_array($parts) && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) &&
            !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass']);
    }

    /**
     * Collect aliases, a thumbnail, websites and the best available description.
     *
     * @param array $response Decoded SPARQL response.
     * @param string $language Preferred language.
     * @return array Card metadata.
     */
    public static function parse_details(array $response, string $language): array {
        $aliases = [];
        $officialwebsites = [];
        $aboutwebsites = [];
        $image = '';
        $descriptions = [];
        $types = [];
        $categories = [];
        $fields = [];
        foreach ($response['results']['bindings'] ?? [] as $binding) {
            $kind = $binding['kind']['value'] ?? '';
            $value = $binding['value']['value'] ?? '';
            if (!is_string($value) || $value === '') {
                continue;
            }
            switch ($kind) {
                case 'image':
                    if ($image === '') {
                        $image = self::image_url($value);
                    }
                    break;
                case 'alias':
                    $alias = trim(\core_text::substr($value, 0, 100));
                    if ($alias !== '' && !in_array($alias, $aliases, true) && count($aliases) < 8) {
                        $aliases[] = $alias;
                    }
                    break;
                case 'description':
                    $lang = $binding['value']['xml:lang'] ?? '';
                    if (in_array($lang, ['es', 'en', 'el'], true)) {
                        $descriptions[$lang] = \core_text::substr($value, 0, 500);
                    }
                    break;
                case 'type':
                case 'category':
                case 'date':
                case 'location':
                case 'creator':
                    $label = $binding['valueLabel']['value'] ?? $value;
                    if ($kind === 'date') {
                        $label = substr($value, 0, 10);
                    }
                    if (!is_string($label) || $label === '' || \core_text::strlen($label) > 200) {
                        break;
                    }
                    if ($kind === 'type' && count($types) < 12) {
                        $url = preg_match('~^https?://www\.wikidata\.org/entity/(Q[1-9][0-9]*)$~D', $value, $match) ?
                            'https://www.wikidata.org/wiki/' . $match[1] : '';
                        $types[$label] = ['label' => $label, 'url' => $url];
                    } else if ($kind === 'category' && count($categories) < 12) {
                        $categories[$label] = ['label' => $label, 'url' => ''];
                    } else if (in_array($kind, ['date', 'location', 'creator'], true) && count($fields[$kind] ?? []) < 8) {
                        $fields[$kind][$label] = $label;
                    }
                    break;
                case 'official':
                case 'about':
                    if (!self::valid_website($value) || isset($officialwebsites[$value]) ||
                            isset($aboutwebsites[$value])) {
                        break;
                    }
                    if ($kind === 'official' && count($officialwebsites) < 2) {
                        $officialwebsites[$value] = ['type' => $kind, 'url' => $value];
                    } else if ($kind === 'about' && count($aboutwebsites) < 2) {
                        $aboutwebsites[$value] = ['type' => $kind, 'url' => $value];
                    }
                    break;
            }
        }
        $language = in_array($language, ['es', 'en', 'el'], true) ? $language : 'en';
        return [
            'aliases' => $aliases,
            'image' => $image,
            'websites' => array_values($officialwebsites + $aboutwebsites),
            'description' => $descriptions[$language] ?? $descriptions['es'] ?? $descriptions['en'] ??
                $descriptions['el'] ?? '',
            'types' => array_values($types),
            'categories' => array_values($categories),
            'fields' => array_map('array_values', $fields),
        ];
    }

    /**
     * Retrieve the metadata of one item when its card opens.
     *
     * @param string $itemid Wikidata Q identifier.
     * @param string $language Preferred language.
     * @return array Card metadata.
     */
    public static function details(string $itemid, string $language): array {
        return self::parse_details(self::request_query(self::build_details_query($itemid)), $language);
    }

    /**
     * Query Wikidata from the server so the editor needs no cross-origin browser requests.
     *
     * @param array $types Semantic categories.
     * @param string $term Optional label fragment.
     * @param array $viewport Visible map bounds.
     * @param string $language Preferred language.
     * @return array GeoJSON, bounds and truncation flag.
     */
    public static function search(array $types, string $term, array $viewport, string $language,
            string $cursor = ''): array {
        $bounds = self::buffered_bounds($viewport);
        $query = self::build_query($types, $term, $bounds, $language, $cursor);
        return self::parse_results(self::request_query($query)) + ['bounds' => $bounds];
    }

    /**
     * Run a fixed-endpoint query with a short timeout and limited response size.
     *
     * @param string $query Controlled SPARQL query.
     * @return array Decoded SPARQL response.
     */
    private static function request_query(string $query): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $curl->setHeader('Accept: application/sparql-results+json');
        $curl->setHeader('User-Agent: MoodleTreasurehunt/1.0 (https://github.com/jpdecastro/moodle-mod_treasurehunt)');
        $body = $curl->get('https://query.wikidata.org/sparql', ['query' => $query],
            ['CURLOPT_CONNECTTIMEOUT' => 5, 'CURLOPT_TIMEOUT' => 20]);
        $info = $curl->get_info();
        $httpcode = (int)($info['http_code'] ?? 0);
        if ($curl->get_errno() || $httpcode !== 200 || strlen((string)$body) > 2000000) {
            $diagnostic = 'Wikidata Query Service HTTP ' . $httpcode . ', cURL ' . $curl->get_errno();
            if ($httpcode === 400) {
                // The query service explains malformed SPARQL in its response body.
                $detail = trim(preg_replace('/\s+/', ' ', strip_tags(substr((string)$body, 0, 1500))));
                $diagnostic .= ': ' . \core_text::substr($detail, 0, 400);
            }
            throw new \moodle_exception('opendataserviceerror', 'treasurehunt', '', null, $diagnostic);
        }
        $response = json_decode($body, true);
        if (!is_array($response) || !isset($response['results']['bindings'])) {
            throw new \moodle_exception('opendataserviceerror', 'treasurehunt', '', null,
                'Wikidata Query Service returned invalid SPARQL JSON');
        }
        return $response;
    }
}
