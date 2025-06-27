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

use \core_external\external_function_parameters;
use \core_external\external_single_structure;
use \core_external\external_multiple_structure;
use \core_external\external_value;
use \core_external\external_api;
use stdClass;
use context_module;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/mod/treasurehunt/externalcompatibility.php');

require_once("$CFG->dirroot/mod/treasurehunt/locallib.php");

class update_stages extends external_api
{

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
            'stages' => new external_single_structure(
                array(
                'type' => new external_value(PARAM_TEXT, 'FeatureColletion'),
                'features' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                    'type' => new external_value(PARAM_TEXT, 'Feature'),
                    'id' => new external_value(PARAM_INT, 'Feature id'),
                    'geometry' => new external_single_structure(
                        array(
                        'type' => new external_value(PARAM_TEXT, 'Geometry type'),
                        'coordinates' => new external_multiple_structure(
                            new external_multiple_structure(
                                new external_multiple_structure(
                                        new external_single_structure(
                                        array(
                            new external_value(PARAM_FLOAT, "Longitude"),
                            new external_value(PARAM_FLOAT, "Latitude"))
                                    )
                                    )
                            ),
                            'Coordinates definition in geojson format for multipolygon'
                        )
                            ),
                        'Geometry definition in geojson format',
                        VALUE_OPTIONAL
                    ),
                        'properties' => new external_single_structure(
                            array(
                        'roadid' => new external_value(PARAM_INT, "Associated road id"),
                        'stageposition' => new external_value(PARAM_INT, "Position of associated stage")
                            )
                        )
                        )
                    ),
                    'Features definition in geojson format'
                )
                    ),
                'All stages to update of an instance in geojson format'
            ),
                'treasurehuntid' => new external_value(PARAM_INT, 'id of treasurehunt'),
                'lockid' => new external_value(PARAM_INT, 'id of lock')
                )
        );
    }

    /**
     * Describes the update_stages return value
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure
    {
        return new external_single_structure(
            array(
            'status' => new external_single_structure(
                array(
                'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                'msg' => new external_value(PARAM_RAW, 'message explain code'))
            )
        )
        );
    }

    public static function execute($stages, $treasurehuntid, $lockid)
    {
        global $DB;
        $params = self::validate_parameters(
            self::execute_parameters(),
            array('stages' => $stages,
                                                'treasurehuntid' => $treasurehuntid,
                                                'lockid' => $lockid)
        );
        $cm = get_coursemodule_from_instance('treasurehunt', $params['treasurehuntid']);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/treasurehunt:managetreasurehunt', $context);
        require_capability('mod/treasurehunt:editstage', $context);
        $features = treasurehunt_geojson_to_object($params['stages']);
        $status = array();
        if (treasurehunt_edition_lock_id_is_valid($params['lockid'])) {
            try {
                $transaction = $DB->start_delegated_transaction();
                foreach ($features as $feature) {
                    treasurehunt_update_geometry_and_position_of_stage($feature, $context);
                }
                $transaction->allow_commit();
                $status['code'] = 0;
                $status['msg'] = 'La actualización de las etapas se ha realizado con éxito';
            } catch (Exception $e) {
                $transaction->rollback($e);
                $status['code'] = 1;
                $status['msg'] = $e;
            }
        } else {
            $status['code'] = 1;
            $status['msg'] = 'Se ha editado esta caza del tesoro, recargue esta página';
        }
        $result = array();
        $result['status'] = $status;
        return $result;
    }

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function delete_stage_is_allowed_from_ajax()
    {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function delete_stage_parameters()
    {
        return new external_function_parameters(
            array(
            'stageid' => new external_value(PARAM_RAW, 'id of stage'),
            'treasurehuntid' => new external_value(PARAM_INT, 'id of treasurehunt'),
            'lockid' => new external_value(PARAM_INT, 'id of lock')
                )
        );
    }

    /**
     * Describes the delete_stage return value
     * @return external_single_structure
     */
    public static function delete_stage_returns()
    {
        return new external_single_structure(
            array(
            'status' => new external_single_structure(
                array(
                'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                'msg' => new external_value(PARAM_RAW, 'message explain code'))
            )
        )
        );
    }

    public static function delete_stage($stageid, $treasurehuntid, $lockid)
    {
        $params = self::validate_parameters(
            self::delete_stage_parameters(),
            array('stageid' => $stageid, 'treasurehuntid' => $treasurehuntid, 'lockid' => $lockid)
        );
        $cm = get_coursemodule_from_instance('treasurehunt', $params['treasurehuntid']);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/treasurehunt:managetreasurehunt', $context);
        require_capability('mod/treasurehunt:editstage', $context);
        $status = array();
        if (treasurehunt_edition_lock_id_is_valid($params['lockid'])) {
            treasurehunt_delete_stage($params['stageid'], $context);
            $status['code'] = 0;
            $status['msg'] = 'La eliminación de la etapa se ha realizado con éxito';
        } else {
            $status['code'] = 1;
            $status['msg'] = 'Se ha editado esta caza del tesoro, recargue esta página';
        }

        $result = array();
        $result['status'] = $status;
        return $result;
    }

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function delete_road_is_allowed_from_ajax()
    {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function delete_road_parameters()
    {
        return new external_function_parameters(
            array(
            'roadid' => new external_value(PARAM_INT, 'id of road'),
            'treasurehuntid' => new external_value(PARAM_INT, 'id of treasurehunt'),
            'lockid' => new external_value(PARAM_INT, 'id of lock')
                )
        );
    }

    /**
     * Describes the delete_road return value
     * @return external_single_structure
     */
    public static function delete_road_returns()
    {
        return new external_single_structure(
            array(
            'status' => new external_single_structure(
                array(
                'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                'msg' => new external_value(PARAM_RAW, 'message explain code'))
            )
        )
        );
    }

    public static function delete_road($roadid, $treasurehuntid, $lockid)
    {
        global $DB;
        $params = self::validate_parameters(
            self::delete_road_parameters(),
            array('roadid' => $roadid, 'treasurehuntid' => $treasurehuntid, 'lockid' => $lockid)
        );
        $cm = get_coursemodule_from_instance('treasurehunt', $params['treasurehuntid']);
        $treasurehunt = $DB->get_record('treasurehunt', array('id' => $cm->instance), '*', MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/treasurehunt:managetreasurehunt', $context);
        require_capability('mod/treasurehunt:editroad', $context);
        $status = array();
        if (treasurehunt_edition_lock_id_is_valid($params['lockid'])) {
            treasurehunt_delete_road($params['roadid'], $treasurehunt, $context);
            $status['code'] = 0;
            $status['msg'] = 'El camino se ha eliminado con ÃƒÂ©xito';
        } else {
            $status['code'] = 1;
            $status['msg'] = 'Se ha editado esta caza del tesoro, recargue esta página';
        }

        $result = array();
        $result['status'] = $status;
        return $result;
    }

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function renew_lock_is_allowed_from_ajax()
    {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function renew_lock_parameters()
    {
        return new external_function_parameters(
            array(
            'treasurehuntid' => new external_value(PARAM_INT, 'id of treasurehunt'),
            'lockid' => new external_value(PARAM_INT, 'id of lock', VALUE_OPTIONAL, null, NULL_ALLOWED)
                )
        );
    }

    /**
     * Describes the renew_lock return values
     * @return external_single_structure
     */
    public static function renew_lock_returns()
    {
        return new external_single_structure(
            array(
            'lockid' => new external_value(PARAM_INT, 'id of lock'),
            'status' => new external_single_structure(
                array(
                'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                'msg' => new external_value(PARAM_RAW, 'message explain code'))
            )
                )
        );
    }

    public static function renew_lock($treasurehuntid, $lockid)
    {
        global $USER;
        $params = self::validate_parameters(
            self::renew_lock_parameters(),
            array('treasurehuntid' => $treasurehuntid, 'lockid' => $lockid)
        );
        $cm = get_coursemodule_from_instance('treasurehunt', $params['treasurehuntid']);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/treasurehunt:managetreasurehunt', $context);
        $status = array();
        if (isset($params['lockid'])) {
            if (treasurehunt_edition_lock_id_is_valid($params['lockid'])) {
                $lockid = treasurehunt_renew_edition_lock($params['treasurehuntid'], $USER->id);
                $status['code'] = 0;
                $status['msg'] = 'Se ha renovado el bloqueo con exito';
            } else {
                $status['code'] = 1;
                $status['msg'] = 'Se ha editado esta caza del tesoro, recargue esta página';
            }
        } else {
            if (!treasurehunt_is_edition_locked($params['treasurehuntid'], $USER->id)) {
                $lockid = treasurehunt_renew_edition_lock($params['treasurehuntid'], $USER->id);
                $status['code'] = 0;
                $status['msg'] = 'Se ha creado el bloqueo con exito';
            } else {
                $status['code'] = 1;
                $status['msg'] = 'La caza del tesoro está siendo editada';
            }
        }
        $result = array();
        $result['status'] = $status;
        $result['lockid'] = $lockid;
        return $result;
    }

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function user_progress_is_allowed_from_ajax()
    {
        return true;
    }
}