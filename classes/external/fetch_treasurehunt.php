<?php
// This file is part of Treasurehunt for Moodle
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * External treasurehunt API
 *
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @author Adrian Rodriguez <huorwhisp@gmail.com>
 * @author Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_treasurehunt\external;

use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;
use core_external\external_api;
use stdClass;
use context_module;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/mod/treasurehunt/externalcompatibility.php');

require_once("$CFG->dirroot/mod/treasurehunt/locallib.php");
/**
 * Service definition. Retrieve full info.
 */
class fetch_treasurehunt extends external_api {
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'treasurehuntid' => new external_value(PARAM_INT, 'id of treasurehunt'),
        ]);
    }

    /**
     * Describes the fetch_treasurehunt return values
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(
            [
            'treasurehunt' => new external_single_structure(
                [
                'roads' => new external_multiple_structure(
                    new external_single_structure(
                        [
                        'id' => new external_value(PARAM_INT, 'The id of the road'),
                        'name' => new external_value(PARAM_RAW, 'The name of the road'),
                        'blocked' => new external_value(PARAM_BOOL, 'If true the road is blocked'),
                        'stages' => new external_single_structure(
                            ['type' => new external_value(PARAM_TEXT, 'FeatureColletion'),
                            'features' => new external_multiple_structure(
                                new external_single_structure(
                                    [
                                    'type' => new external_value(PARAM_TEXT, 'Feature'),
                                    'id' => new external_value(PARAM_INT, 'Feature id'),
                                    'geometry' => new external_single_structure(
                                        [
                                        'type' => new external_value(PARAM_TEXT, 'Geometry type'),
                                        'coordinates' => new external_multiple_structure(
                                            new external_multiple_structure(
                                                new external_multiple_structure(
                                                    new external_single_structure(
                                                        [
                                                        new external_value(PARAM_FLOAT, "Longitude"),
                                                        new external_value(PARAM_FLOAT, "Latitude")]
                                                    )
                                                )
                                            ),
                                            'Coordinates definition in geojson format for multipolygon'
                                        ),
                                        ],
                                        'Geometry definition in geojson format',
                                        VALUE_OPTIONAL
                                    ),
                                    'properties' => new external_single_structure(
                                        [
                                        'roadid' => new external_value(PARAM_INT, "Associated road id"),
                                        'stageposition' => new external_value(PARAM_INT, "Position of associated stage"),
                                        'name' => new external_value(PARAM_RAW, "Name of associated stage"),
                                        'treasurehuntid' => new external_value(PARAM_INT, "Associated treasurehunt id"),
                                        'clue' => new external_value(PARAM_RAW, "Clue of associated stage"),
                                        ]
                                    ),
                                    ]
                                ),
                                'Features definition in geojson format'
                            ),
                            ],
                            'All stages of the road in geojson format'
                        ),
                        ],
                        'Array with all roads in the instance.'
                    )
                ),
                    ]
            ),
                'status' => new external_single_structure(
                    [
                    'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                    'msg' => new external_value(PARAM_RAW, 'message explain code')]
                ),
                ]
        );
    }
    /**
     * Get the definition information of the treasurehunt.
     * @param mixed $treasurehuntid
     * @return array<array|stdClass>
     */
    public static function execute($treasurehuntid) {
        $params = self::validate_parameters(self::execute_parameters(), [
            'treasurehuntid' => $treasurehuntid,
        ]);
        $status = [];
        $treasurehunt = new stdClass();
        $cm = get_coursemodule_from_instance('treasurehunt', $params['treasurehuntid']);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/treasurehunt:managetreasurehunt', $context);
        $treasurehunt->roads = treasurehunt_get_all_roads_and_stages($params['treasurehuntid'], $context);
        $status['code'] = 0;
        $status['msg'] = 'La caza del tesoro se ha cargado con éxito';

        $result = [];
        $result['treasurehunt'] = $treasurehunt;
        $result['status'] = $status;
        return $result;
    }
    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function execute_is_allowed_from_ajax() {
        return true;
    }
}
