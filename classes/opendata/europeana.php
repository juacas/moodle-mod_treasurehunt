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
 * Europeana Search API source for georeferenced cultural heritage.
 *
 * @package mod_treasurehunt
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_treasurehunt\opendata;

/**
 * Query Europeana with a server-held API key and normalize its result items.
 */
final class europeana implements source {
    /** Search API endpoint. */
    private const ENDPOINT = 'https://api.europeana.eu/record/v2/search.json';

    /** Record API endpoint for complete type and subject metadata. */
    private const RECORD_ENDPOINT = 'https://api.europeana.eu/record/v2';

    /** Maximum API records requested in one page. */
    public const PAGE_SIZE = 100;

    /**
     * Return the stable source ID.
     */
    public function id(): string {
        return 'europeana';
    }

    /** Return the public Europeana website. */
    public function website(): string {
        return 'https://www.europeana.eu/en';
    }

    /**
     * Return available theme IDs and language keys.
     */
    public function themes(): array {
        return [
            'all' => 'opendataall',
            'archaeology' => 'opendataeuropeanaarchaeology',
            'art' => 'opendataeuropeanaart',
            'audiovisual' => 'opendataeuropeanaaudiovisual',
            'fashion' => 'opendataeuropeanafashion',
            'industrial' => 'opendataeuropeanaindustrial',
            'manuscript' => 'opendataeuropeanamanuscript',
            'map' => 'opendataeuropeanamap',
            'migration' => 'opendataeuropeanamigration',
            'music' => 'opendataeuropeanamusic',
            'nature' => 'opendataeuropeananature',
            'newspaper' => 'opendataeuropeananewspaper',
            'photography' => 'opendataeuropeanaphotography',
            'sport' => 'opendataeuropeanasport',
            'ww1' => 'opendataeuropeanaww1',
        ];
    }

    /**
     * Whether this source can be queried.
     */
    public function available(): bool {
        return trim((string)get_config('mod_treasurehunt', 'europeanaapikey')) !== '';
    }

    /**
     * Build the API request without accepting arbitrary fields or endpoints.
     *
     * @param array $themes Exactly one selected Europeana theme.
     * @param string $term Optional text.
     * @param array $bounds Buffered bounds.
     * @param string $cursor Europeana cursor, empty for the first page.
     * @return array API query parameters without the secret key.
     */
    public function request_params(array $themes, string $term, array $bounds, string $cursor): array {
        if (count($themes) !== 1 || !is_string($themes[0]) ||
                !array_key_exists($themes[0], $this->themes()) || count($bounds) !== 4 ||
                \core_text::strlen($term) > 100 || strlen($cursor) > 2048) {
            throw new \moodle_exception('opendatainvalidsearch', 'treasurehunt');
        }
        [$west, $south, $east, $north] = $bounds;
        $geo = 'pl_wgs84_pos_lat:[' . sprintf('%.6F', $south) . ' TO ' . sprintf('%.6F', $north) .
            '] AND pl_wgs84_pos_long:[' . sprintf('%.6F', $west) . ' TO ' . sprintf('%.6F', $east) . ']';
        $theme = $themes[0];
        if ($theme === 'audiovisual') {
            $geo .= ' AND (TYPE:VIDEO OR TYPE:SOUND)';
        }
        $term = trim($term);
        $params = [
            'query' => $term === '' ? '*' : json_encode($term,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'qf' => $geo,
            'rows' => self::PAGE_SIZE,
            'profile' => 'standard',
            'cursor' => $cursor === '' ? '*' : $cursor,
        ];
        if (!in_array($theme, ['all', 'audiovisual'], true)) {
            $params['theme'] = $theme;
        }
        return $params;
    }

    /**
     * Search one page of georeferenced items.
     */
    public function search_page(array $themes, string $term, array $viewport,
            string $language, string $cursor): array {
        global $CFG;
        if (!$this->available()) {
            throw new \moodle_exception('opendataeuropeananokey', 'treasurehunt');
        }
        $bounds = wikidata::buffered_bounds($viewport);
        $params = $this->request_params($themes, $term, $bounds, $cursor);
        $params['wskey'] = (string)get_config('mod_treasurehunt', 'europeanaapikey');
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $curl->setHeader('Accept: application/json');
        $body = $curl->get(self::ENDPOINT, $params,
            ['CURLOPT_CONNECTTIMEOUT' => 5, 'CURLOPT_TIMEOUT' => 20, 'CURLOPT_FOLLOWLOCATION' => 0]);
        $info = $curl->get_info();
        if ($curl->get_errno() || ($info['http_code'] ?? 0) !== 200 || strlen($body) > 3000000) {
            throw new \moodle_exception('opendataeuropeanaserviceerror', 'treasurehunt');
        }
        $response = json_decode($body, true);
        if (!is_array($response) || ($response['success'] ?? false) !== true || !is_array($response['items'] ?? null)) {
            throw new \moodle_exception('opendataeuropeanaserviceerror', 'treasurehunt');
        }
        $page = self::parse_results($response, $bounds, $cursor);
        foreach ($page['geojson']['features'] as &$feature) {
            $feature['properties']['theme'] = $themes[0];
        }
        unset($feature);
        return $page;
    }

    /**
     * Return optional metadata for one item.
     */
    public function item_details(string $itemid, string $language): array {
        global $CFG;
        if (!$this->available() || !preg_match('~^/[^/\s?#<>.][^/\s?#<>]{0,99}/[^/\s?#<>.][^/\s?#<>]{0,299}$~D', $itemid)) {
            throw new \moodle_exception('opendatainvalidsearch', 'treasurehunt');
        }
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $curl->setHeader('Accept: application/json');
        $body = $curl->get(self::RECORD_ENDPOINT . $itemid . '.json',
            ['wskey' => (string)get_config('mod_treasurehunt', 'europeanaapikey')],
            ['CURLOPT_CONNECTTIMEOUT' => 5, 'CURLOPT_TIMEOUT' => 20, 'CURLOPT_FOLLOWLOCATION' => 0]);
        $info = $curl->get_info();
        if ($curl->get_errno() || ($info['http_code'] ?? 0) !== 200 || strlen((string)$body) > 3000000) {
            throw new \moodle_exception('opendataeuropeanaserviceerror', 'treasurehunt');
        }
        $response = json_decode($body, true);
        if (!is_array($response) || ($response['success'] ?? false) !== true ||
                !is_array($response['object'] ?? null)) {
            throw new \moodle_exception('opendataeuropeanaserviceerror', 'treasurehunt');
        }
        return self::parse_details($response['object'], $language);
    }

    /**
     * Select a readable label in the requested language or a suitable fallback.
     *
     * @param array $labels Language to label-list map.
     * @param string $language Requested language.
     * @return array Labels from one language.
     */
    private static function localized_labels(array $labels, string $language): array {
        foreach (array_unique([$language, 'en', 'es', 'ca']) as $code) {
            if (!empty($labels[$code]) && is_array($labels[$code])) {
                return $labels[$code];
            }
        }
        foreach ($labels as $code => $values) {
            if ($code !== 'def' && is_array($values) && $values) {
                return $values;
            }
        }
        return is_array($labels['def'] ?? null) ? $labels['def'] : [];
    }

    /**
     * Keep only known Europeana thumbnail URLs for display and stage clues.
     *
     * @param mixed $url Candidate image URL.
     * @return string Valid image URL or an empty string.
     */
    public static function thumbnail_url($url): string {
        if (!is_string($url) || strlen($url) > 2000 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' ||
                isset($parts['user']) || isset($parts['pass'])) {
            return '';
        }
        $host = strtolower($parts['host'] ?? '');
        if ($host === 'api.europeana.eu' && str_starts_with($parts['path'] ?? '', '/thumbnail/')) {
            return $url;
        }
        return $host === 'images.europeana.eu' ? $url : '';
    }

    /**
     * Normalize a Europeana record into labels and trusted links for the card.
     *
     * @param array $record Record API object.
     * @param string $language Requested language.
     * @return array Card details.
     */
    public static function parse_details(array $record, string $language): array {
        $references = [];
        $concepts = is_array($record['concepts'] ?? null) ? $record['concepts'] : [];
        $places = is_array($record['places'] ?? null) ? $record['places'] : [];
        foreach (array_merge($concepts, $places) as $concept) {
            if (is_array($concept) && is_string($concept['about'] ?? null)) {
                $labels = is_array($concept['prefLabel'] ?? null) ? $concept['prefLabel'] : [];
                $references[$concept['about']] = self::localized_labels($labels, $language)[0] ?? '';
            }
        }
        $tags = ['types' => [], 'subjects' => []];
        $seen = ['types' => [], 'subjects' => []];
        $proxies = is_array($record['proxies'] ?? null) ? $record['proxies'] : [];
        foreach ($proxies as $proxy) {
            if (!is_array($proxy)) {
                continue;
            }
            foreach (['dcType' => 'types', 'dcSubject' => 'subjects'] as $field => $group) {
                $values = $proxy[$field] ?? [];
                if (!is_array($values)) {
                    continue;
                }
                $labels = self::localized_labels($values, $language);
                // Subjects place linked concepts first, then provider text; types keep their literal order.
                $defaultvalues = is_array($values['def'] ?? null) ? $values['def'] : [];
                $entries = $group === 'types' ? array_merge($labels, $defaultvalues) :
                    array_merge($defaultvalues, $labels);
                foreach ($entries as $value) {
                    if (!is_string($value)) {
                        continue;
                    }
                    $label = $references[$value] ?? $value;
                    if (filter_var($label, FILTER_VALIDATE_URL)) {
                        continue;
                    }
                    $label = trim(preg_replace('/\s+/', ' ', $label));
                    $key = \core_text::strtolower($label);
                    if ($label === '' || isset($seen[$group][$key]) || count($tags[$group]) >= 15) {
                        continue;
                    }
                    $seen[$group][$key] = true;
                    $url = '';
                    if (preg_match('~^https?://data\.europeana\.eu/(concept|place)/([0-9]+)$~', $value, $match)) {
                        $url = 'https://www.europeana.eu/en/collections/' .
                            ($match[1] === 'concept' ? 'topic' : 'place') . '/' . $match[2];
                    }
                    $tags[$group][] = ['label' => \core_text::substr($label, 0, 100), 'url' => $url];
                }
            }
        }
        $preview = $record['europeanaAggregation']['edmPreview'] ?? '';
        $description = '';
        foreach ($proxies as $proxy) {
            if (!is_array($proxy)) {
                continue;
            }
            $descriptions = is_array($proxy['dcDescription'] ?? null) ? $proxy['dcDescription'] : [];
            $description = self::localized_labels($descriptions, $language)[0] ?? '';
            if (is_string($description) && $description !== '') {
                break;
            }
        }
        $fields = [];
        $recordfields = [
            'dcCreator' => 'creator',
            'dcDate' => 'date',
            'dcCoverage' => 'location',
            'dcPublisher' => 'publisher',
            'dcLanguage' => 'language',
            'dcRights' => 'rights',
        ];
        foreach ($proxies as $proxy) {
            if (!is_array($proxy)) {
                continue;
            }
            foreach ($recordfields as $key => $field) {
                $values = is_array($proxy[$key] ?? null) ? $proxy[$key] : [];
                foreach (self::localized_labels($values, $language) as $value) {
                    if (is_string($value) && trim($value) !== '' && count($fields[$field] ?? []) < 8) {
                        $fields[$field][trim($value)] = \core_text::substr(trim($value), 0, 200);
                    }
                }
            }
        }
        foreach (['edmDataProvider' => 'provider', 'edmProvider' => 'provider'] as $key => $field) {
            $values = $record['europeanaAggregation'][$key] ?? [];
            $values = is_array($values) ? $values : [$values];
            foreach ($values as $value) {
                if (is_string($value) && trim($value) !== '' && count($fields[$field] ?? []) < 8) {
                    $fields[$field][trim($value)] = \core_text::substr(trim($value), 0, 200);
                }
            }
        }
        return [
            'image' => self::thumbnail_url($preview),
            'types' => $tags['types'],
            'subjects' => $tags['subjects'],
            'description' => is_string($description) ? \core_text::substr($description, 0, 500) : '',
            'fields' => array_map('array_values', $fields),
        ];
    }

    /**
     * Convert a Europeana response to the shared feature schema.
     *
     * @param array $response Search API response.
     * @param array $bounds Buffered geographic bounds.
     * @param string $cursor Cursor used for this page.
     * @return array Normalized page.
     */
    public static function parse_results(array $response, array $bounds, string $cursor): array {
        $features = [];
        foreach ($response['items'] ?? [] as $item) {
            if (!is_array($item) || !is_string($item['id'] ?? null) ||
                    !preg_match('~^/[^/\s?#<>]{1,100}/[^/\s?#<>]{1,300}$~D', $item['id'])) {
                continue;
            }
            $latitude = $item['edmPlaceLatitude'][0] ?? null;
            $longitude = $item['edmPlaceLongitude'][0] ?? null;
            if (!is_numeric($latitude) || !is_numeric($longitude)) {
                continue;
            }
            $latitude = (float)$latitude;
            $longitude = (float)$longitude;
            if ($longitude < $bounds[0] || $longitude > $bounds[2] ||
                    $latitude < $bounds[1] || $latitude > $bounds[3]) {
                continue;
            }
            $id = $item['id'];
            if (isset($features[$id])) {
                continue;
            }
            $titles = is_array($item['title'] ?? null) ? $item['title'] : [];
            $title = $titles[0] ?? $id;
            $aliases = [];
            foreach (array_slice($titles, 1) as $alias) {
                if (is_string($alias) && trim($alias) !== '' && !in_array($alias, $aliases, true)) {
                    $aliases[] = \core_text::substr($alias, 0, 100);
                }
                if (count($aliases) >= 8) {
                    break;
                }
            }
            $description = $item['dcDescription'][0] ?? '';
            $image = self::thumbnail_url($item['edmPreview'][0] ?? '');
            $url = $item['guid'] ?? '';
            $urlparts = is_string($url) ? parse_url($url) : false;
            if (!is_array($urlparts) || ($urlparts['scheme'] ?? '') !== 'https' ||
                    !in_array($urlparts['host'] ?? '', ['www.europeana.eu', 'europeana.eu'], true)) {
                $url = 'https://www.europeana.eu/en/item' . $id;
            }
            $websites = [];
            $providerurl = $item['edmIsShownAt'][0] ?? '';
            if (is_string($providerurl) && filter_var($providerurl, FILTER_VALIDATE_URL) &&
                    in_array(parse_url($providerurl, PHP_URL_SCHEME), ['http', 'https'], true)) {
                $websites[] = ['type' => 'about', 'url' => $providerurl];
            }
            $features[$id] = [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => [$longitude, $latitude]],
                'properties' => [
                    'source' => 'europeana',
                    'id' => $id,
                    'name' => \core_text::substr(is_string($title) ? $title : $id, 0, 200),
                    'description' => \core_text::substr(is_string($description) ? $description : '', 0, 500),
                    'url' => $url,
                    'image' => $image,
                    'aliases' => $aliases,
                    'websites' => $websites,
                    'detailsready' => false,
                ],
            ];
        }
        $nextcursor = $response['nextCursor'] ?? '';
        if (!is_string($nextcursor) || $nextcursor === $cursor || !($response['items'] ?? [])) {
            $nextcursor = '';
        }
        return [
            'geojson' => ['type' => 'FeatureCollection', 'features' => array_values($features)],
            'nextcursor' => $nextcursor,
        ];
    }
}
