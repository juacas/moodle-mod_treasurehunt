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
use context_module;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/mod/treasurehunt/externalcompatibility.php');

require_once("$CFG->dirroot/mod/treasurehunt/locallib.php");

class renew_lock extends external_api
{
   
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters()
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
    public static function execute_returns()
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

    public static function execute($treasurehuntid, $lockid)
    {
        global $USER;
        $params = self::validate_parameters(
            self::execute_parameters(),
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
            if (!treasurehunt_is_edition_loked($params['treasurehuntid'], $USER->id)) {
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
}
