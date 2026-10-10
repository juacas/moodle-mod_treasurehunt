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
 * Search public geographic OpenData for the treasure hunt editor.
 *
 * @package mod_treasurehunt
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once('../../config.php');
require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');

$id = required_param('id', PARAM_INT);
$action = optional_param('action', 'search', PARAM_ALPHA);
$sourceid = optional_param('source', 'wikidata', PARAM_ALPHA);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'treasurehunt');
require_login($course, false, $cm);
require_sesskey();
require_capability('mod/treasurehunt:managetreasurehunt', context_module::instance($cm->id));
\core\session\manager::write_close();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    $source = \mod_treasurehunt\opendata\sources::get($sourceid);
    if ($action === 'createstage') {
        require_capability('mod/treasurehunt:addstage', context_module::instance($cm->id));
        $roadid = required_param('roadid', PARAM_INT);
        $lockid = required_param('lockid', PARAM_INT);
        $name = trim(required_param('name', PARAM_TEXT));
        $metadatajson = optional_param('metadata', '{}', PARAM_RAW);
        if (strlen($metadatajson) > 20000) {
            throw new moodle_exception('opendatainvalidsearch', 'treasurehunt');
        }
        $metadata = json_decode($metadatajson, true);
        if (!is_array($metadata)) {
            throw new moodle_exception('opendatainvalidsearch', 'treasurehunt');
        }
        $longitude = required_param('longitude', PARAM_FLOAT);
        $latitude = required_param('latitude', PARAM_FLOAT);
        treasurehunt_require_road_in_activity($roadid, $cm->instance);
        if (treasurehunt_check_road_is_blocked($roadid)) {
            throw new moodle_exception('notcreatestage', 'treasurehunt');
        }
        if (!treasurehunt_ensure_editor_lock($lockid, $cm->instance, $USER->id)) {
            throw new moodle_exception('editorlocktaken', 'treasurehunt');
        }
        $stageid = \mod_treasurehunt\opendata\stage_creator::create($roadid, $name,
            $longitude, $latitude, context_module::instance($cm->id), $sourceid, $metadata);
        $result = ['editurl' => (new moodle_url('/mod/treasurehunt/editstage.php',
            ['cmid' => $cm->id, 'id' => $stageid]))->out(false)];
    } else if ($action === 'details') {
        $itemid = required_param('itemid', PARAM_RAW_TRIMMED);
        $result = $source->item_details($itemid, current_language());
    } else if ($action === 'search') {
        $types = optional_param_array('types', [], PARAM_ALPHANUMEXT);
        $cursor = optional_param('cursor', '', PARAM_RAW_TRIMMED);
        $term = optional_param('term', '', PARAM_TEXT);
        $west = required_param('west', PARAM_FLOAT);
        $south = required_param('south', PARAM_FLOAT);
        $east = required_param('east', PARAM_FLOAT);
        $north = required_param('north', PARAM_FLOAT);
        $result = $source->search_page(
            $types,
            $term,
            [$west, $south, $east, $north],
            current_language(),
            $cursor
        );
    } else {
        throw new moodle_exception('opendatainvalidsearch', 'treasurehunt');
    }
    echo json_encode($result, JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR);
} catch (moodle_exception $exception) {
    $providererror = in_array($exception->errorcode,
        ['opendataserviceerror', 'opendataeuropeanaserviceerror'], true);
    http_response_code($providererror ? 502 : 400);
    $error = ['error' => $exception->getMessage(), 'code' => $exception->errorcode, 'source' => $sourceid];
    if ($providererror && $sourceid === 'wikidata' && $exception->debuginfo) {
        $error['diagnostic'] = $exception->debuginfo;
    }
    echo json_encode($error, JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    http_response_code(502);
    echo json_encode(['error' => get_string('opendataserviceerror', 'treasurehunt'),
        'code' => 'unexpected', 'source' => $sourceid], JSON_THROW_ON_ERROR);
}
