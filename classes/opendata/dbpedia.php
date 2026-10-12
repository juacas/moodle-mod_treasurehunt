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
 * Geographic DBpedia source for the OpenData editor.
 *
 * @package mod_treasurehunt
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_treasurehunt\opendata;

/** Query the public DBpedia SPARQL endpoint and normalize its resources. */
final class dbpedia implements source {
    /** Public endpoint and page size. */
    private const ENDPOINT = 'https://dbpedia.org/sparql';
    public const PAGE_SIZE = 100;

    /** Only server-defined ontology classes can enter a query. */
    private const THEMES = [
        'all' => [],
        'historic' => ['HistoricPlace'],
        'monument' => ['Monument'],
        'art' => ['Artwork', 'Museum'],
        'nature' => ['NaturalPlace', 'Park'],
    ];

    /** @inheritDoc */
    public function id(): string {
        return 'dbpedia';
    }

    /** @inheritDoc */
    public function website(): string {
        return 'https://www.dbpedia.org/';
    }

    /** @inheritDoc */
    public function themes(): array {
        return [
            'all' => 'opendataall',
            'historic' => 'opendatahistoric',
            'monument' => 'opendatamonument',
            'art' => 'opendataart',
            'nature' => 'opendataeuropeananature',
        ];
    }

    /** @inheritDoc */
    public function available(): bool {
        return true;
    }

    /**
     * Build a bounded query with controlled classes and an integer page cursor.
     *
     * @param array $themes Exactly one theme.
     * @param string $term Optional name fragment.
     * @param array $bounds Buffered WGS84 bounds.
     * @param string $cursor Decimal result offset.
     * @param string $category Optional DBpedia category URI for related items.
     * @return string SPARQL query.
     */
    public static function build_query(array $themes, string $term, array $bounds, string $cursor,
            string $category = ''): string {
        if (count($themes) !== 1 || !is_string($themes[0]) || !array_key_exists($themes[0], self::THEMES) ||
                count($bounds) !== 4 || \core_text::strlen($term) > 100 ||
                ($cursor !== '' && (!preg_match('/^(0|[1-9][0-9]*)$/D', $cursor) || (int)$cursor > 9900)) ||
                ($category !== '' && !self::valid_categoryid($category))) {
            throw new \moodle_exception('opendatainvalidsearch', 'treasurehunt');
        }
        [$west, $south, $east, $north] = $bounds;
        if (!is_finite((float)$west) || !is_finite((float)$south) || !is_finite((float)$east) ||
                !is_finite((float)$north) || $west < -180 || $east > 180 || $south < -90 || $north > 90 ||
                $west >= $east || $south >= $north) {
            throw new \moodle_exception('opendatainvalidarea', 'treasurehunt');
        }
        $type = '';
        if (self::THEMES[$themes[0]]) {
            $classes = array_map(static fn($name) => 'dbo:' . $name, self::THEMES[$themes[0]]);
            $type = 'VALUES ?class { ' . implode(' ', $classes) . ' } ?item a ?class. ';
        }
        $categoryfilter = $category === '' ? '' : '?item dct:subject <' . $category . '>. ';
        $term = trim($term);
        $label = 'OPTIONAL { ?item rdfs:label ?label. FILTER(LANG(?label) = "en") } ';
        if ($term !== '') {
            $literal = json_encode($term, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $label = '?item rdfs:label ?label. FILTER(LANG(?label) = "en" && ' .
                'CONTAINS(LCASE(STR(?label)), LCASE(' . $literal . '))) ';
        }
        return 'PREFIX geo: <http://www.w3.org/2003/01/geo/wgs84_pos#> ' .
            'PREFIX dbo: <http://dbpedia.org/ontology/> ' .
            'PREFIX dct: <http://purl.org/dc/terms/> ' .
            'PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#> ' .
            'SELECT DISTINCT ?item ?lat ?lon ?label WHERE { ?item geo:lat ?lat; geo:long ?lon. ' .
            'FILTER(?lat >= ' . sprintf('%.6F', $south) . ' && ?lat <= ' . sprintf('%.6F', $north) .
            ' && ?lon >= ' . sprintf('%.6F', $west) . ' && ?lon <= ' . sprintf('%.6F', $east) . ') ' .
            $type . $categoryfilter . $label . '} ORDER BY ?item LIMIT ' . (self::PAGE_SIZE + 1) .
            ' OFFSET ' . ($cursor === '' ? '0' : (string)(int)$cursor);
    }

    /**
     * Normalize a page and use the raw result offset even when records are discarded.
     *
     * @param array $response SPARQL JSON response.
     * @param array $bounds Buffered area.
     * @param int $offset Current offset.
     * @return array Search page.
     */
    public static function parse_results(array $response, array $bounds, int $offset): array {
        $bindings = $response['results']['bindings'] ?? [];
        $features = [];
        foreach (array_slice($bindings, 0, self::PAGE_SIZE) as $binding) {
            $id = $binding['item']['value'] ?? '';
            $lat = $binding['lat']['value'] ?? null;
            $lon = $binding['lon']['value'] ?? null;
            if (!self::valid_itemid($id) || !is_numeric($lat) || !is_numeric($lon)) {
                continue;
            }
            $lat = (float)$lat;
            $lon = (float)$lon;
            if (!is_finite($lat) || !is_finite($lon) || $lon < $bounds[0] || $lon > $bounds[2] ||
                    $lat < $bounds[1] || $lat > $bounds[3] || isset($features[$id])) {
                continue;
            }
            $slug = substr($id, strlen('http://dbpedia.org/resource/'));
            $name = $binding['label']['value'] ?? str_replace('_', ' ', rawurldecode($slug));
            $features[$id] = [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => [$lon, $lat]],
                'properties' => [
                    'source' => 'dbpedia',
                    'id' => $id,
                    'name' => \core_text::substr(is_string($name) ? $name : $slug, 0, 200),
                    'description' => '',
                    'url' => 'https://dbpedia.org/page/' . $slug,
                    'detailsready' => false,
                ],
            ];
        }
        $next = count($bindings) > self::PAGE_SIZE && $offset + self::PAGE_SIZE <= 9900 ?
            (string)($offset + self::PAGE_SIZE) : '';
        return ['geojson' => ['type' => 'FeatureCollection', 'features' => array_values($features)],
            'nextcursor' => $next];
    }

    /** Accept only resource IRIs at DBpedia's fixed host. */
    private static function valid_itemid($itemid): bool {
        return is_string($itemid) && strlen($itemid) <= 600 &&
            (bool)preg_match('~^http://dbpedia\.org/resource/[^\x00-\x20<>"{}|^`\\\\?#]{1,500}$~Du', $itemid);
    }

    /** Accept only the fixed DBpedia category namespace as a search filter. */
    private static function valid_categoryid($category): bool {
        return is_string($category) && strlen($category) <= 600 &&
            (bool)preg_match('~^http://dbpedia\.org/resource/Category:[^\x00-\x20<>"{}|^`\\\\?#]{1,500}$~Du',
                $category);
    }

    /** @inheritDoc */
    public function search_page(array $themes, string $term, array $viewport,
            string $language, string $cursor): array {
        $bounds = wikidata::buffered_bounds($viewport);
        $query = self::build_query($themes, $term, $bounds, $cursor);
        $page = self::parse_results(self::request_query($query), $bounds, $cursor === '' ? 0 : (int)$cursor);
        foreach ($page['geojson']['features'] as &$feature) {
            $feature['properties']['theme'] = $themes[0];
        }
        unset($feature);
        return $page + ['bounds' => $bounds];
    }

    /** Search resources in a selected category within the current map area. */
    public function search_category_page(string $category, array $viewport, string $cursor): array {
        $bounds = wikidata::buffered_bounds($viewport);
        $query = self::build_query(['all'], '', $bounds, $cursor, $category);
        $page = self::parse_results(self::request_query($query), $bounds, $cursor === '' ? 0 : (int)$cursor);
        foreach ($page['geojson']['features'] as &$feature) {
            $feature['properties']['theme'] = 'all';
        }
        unset($feature);
        return $page + ['bounds' => $bounds];
    }

    /** @inheritDoc */
    public function item_details(string $itemid, string $language): array {
        return self::parse_details(self::request_query(self::build_details_query($itemid)), $language);
    }

    /** Build the detail query from one validated DBpedia resource. */
    public static function build_details_query(string $itemid): string {
        if (!self::valid_itemid($itemid)) {
            throw new \moodle_exception('opendatainvalidsearch', 'treasurehunt');
        }
        return 'PREFIX dbo: <http://dbpedia.org/ontology/> ' .
            'PREFIX dct: <http://purl.org/dc/terms/> ' .
            'PREFIX foaf: <http://xmlns.com/foaf/0.1/> ' .
            'PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#> ' .
            'SELECT ?kind ?value ?valueLabel WHERE { VALUES ?item { <' . $itemid . '> } ' .
            '{ ?item dbo:description ?value. BIND("description" AS ?kind) } ' .
            'UNION { ?item dbo:abstract ?value. FILTER(LANG(?value) = "en") BIND("abstract" AS ?kind) } ' .
            'UNION { ?item dbo:thumbnail ?value. BIND("image" AS ?kind) } ' .
            'UNION { ?item foaf:depiction ?value. BIND("depiction" AS ?kind) } ' .
            'UNION { ?item foaf:isPrimaryTopicOf ?value. BIND("primary" AS ?kind) } ' .
            'UNION { ?item dct:subject ?value. ' .
            'FILTER(STRSTARTS(STR(?value), "http://dbpedia.org/resource/Category:")) ' .
            'BIND("category" AS ?kind) } ' .
            'UNION { ?item a ?value. FILTER(STRSTARTS(STR(?value), "http://dbpedia.org/ontology/")) ' .
            'OPTIONAL { ?value rdfs:label ?valueLabel. FILTER(LANG(?valueLabel) = "en") } ' .
            'BIND("type" AS ?kind) } } LIMIT 500';
    }

    /** Only display images served by Wikimedia Commons or its thumbnail host. */
    public static function thumbnail_url($url): string {
        if (!is_string($url) || strlen($url) > 2000) {
            return '';
        }
        $commons = wikidata::image_url($url);
        if ($commons !== '') {
            return $commons;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) ||
                strtolower($parts['host'] ?? '') !== 'upload.wikimedia.org' ||
                !str_starts_with($parts['path'] ?? '', '/wikipedia/') || isset($parts['user']) || isset($parts['pass'])) {
            return '';
        }
        return 'https://upload.wikimedia.org' . $parts['path'];
    }

    /** Convert the detail bindings to the editor's card schema. */
    public static function parse_details(array $response, string $language = 'en'): array {
        $details = ['description' => '', 'image' => '', 'images' => [], 'types' => [],
            'categories' => [], 'websites' => []];
        $descriptions = [];
        $abstract = '';
        $thumbnail = '';
        $primarywebsites = [];
        foreach ($response['results']['bindings'] ?? [] as $binding) {
            $kind = $binding['kind']['value'] ?? '';
            $value = $binding['value']['value'] ?? '';
            if (!is_string($value) || $value === '') {
                continue;
            }
            if ($kind === 'description') {
                $lang = $binding['value']['xml:lang'] ?? '';
                if ($lang !== '' && !isset($descriptions[$lang])) {
                    $descriptions[$lang] = \core_text::substr($value, 0, 500);
                }
            } else if ($kind === 'abstract' && $abstract === '') {
                $abstract = \core_text::substr($value, 0, 500);
            } else if ($kind === 'image' && $thumbnail === '') {
                $thumbnail = self::thumbnail_url($value);
            } else if ($kind === 'depiction') {
                $image = self::thumbnail_url($value);
                if ($image !== '' && !in_array($image, $details['images'], true)) {
                    $details['images'][] = $image;
                }
            } else if ($kind === 'category' && self::valid_categoryid($value) &&
                    count($details['categories']) < 20) {
                $slug = substr($value, strlen('http://dbpedia.org/resource/Category:'));
                $label = str_replace('_', ' ', rawurldecode($slug));
                $details['categories'][$value] = ['id' => $value, 'label' => \core_text::substr($label, 0, 100)];
            } else if ($kind === 'type' && count($details['types']) < 12) {
                $label = $binding['valueLabel']['value'] ?? substr($value, strlen('http://dbpedia.org/ontology/'));
                if (is_string($label) && trim($label) !== '') {
                    $details['types'][$value] = ['label' => \core_text::substr($label, 0, 100), 'url' => ''];
                }
            } else if ($kind === 'primary' &&
                    filter_var($value, FILTER_VALIDATE_URL) &&
                    in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true) &&
                    !isset(parse_url($value)['user']) && !isset(parse_url($value)['pass'])) {
                if (count($primarywebsites) < 2) {
                    $primarywebsites[$value] = ['type' => 'primary', 'url' => $value];
                }
            }
        }
        $details['description'] = $descriptions[$language] ?? $descriptions['en'] ?? $descriptions['es'] ?? '';
        if ($details['description'] === '' && $descriptions) {
            $details['description'] = reset($descriptions);
        }
        if ($details['description'] === '') {
            $details['description'] = $abstract;
        }
        $details['image'] = $details['images'][0] ?? $thumbnail;
        $details['types'] = array_values($details['types']);
        $details['categories'] = array_values($details['categories']);
        $details['websites'] = array_values($primarywebsites);
        return $details;
    }

    /** Query only the fixed public endpoint, with bounded time and response size. */
    private static function request_query(string $query): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $curl->setHeader('Accept: application/sparql-results+json');
        $curl->setHeader('User-Agent: MoodleTreasurehunt/1.0');
        $body = $curl->get(self::ENDPOINT, ['query' => $query, 'format' => 'application/sparql-results+json'],
            ['CURLOPT_CONNECTTIMEOUT' => 5, 'CURLOPT_TIMEOUT' => 20, 'CURLOPT_FOLLOWLOCATION' => 0]);
        $info = $curl->get_info();
        if ($curl->get_errno() || (int)($info['http_code'] ?? 0) !== 200 || strlen((string)$body) > 2000000) {
            throw new \moodle_exception('opendatadbpediaerror', 'treasurehunt');
        }
        $response = json_decode($body, true);
        if (!is_array($response) || !is_array($response['results']['bindings'] ?? null)) {
            throw new \moodle_exception('opendatadbpediaerror', 'treasurehunt');
        }
        return $response;
    }
}
