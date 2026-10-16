<?php
// This file is part of Moodle - http://moodle.org/.
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
 * AJAX endpoint for copying stages between roads.
 *
 * @package mod_treasurehunt
 * @copyright 2026
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_treasurehunt\external;

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/mod/treasurehunt/externalcompatibility.php');
require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');

/**
 * Copy stages from another road in the same activity.
 */
class copy_stages extends external_api {
    /**
     * Describe the input parameters.
     *
     * @return external_function_parameters Input description.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'treasurehuntid' => new external_value(PARAM_INT, 'Activity instance id'),
            'sourceroadid' => new external_value(PARAM_INT, 'Source road id'),
            'targetroadid' => new external_value(PARAM_INT, 'Destination road id'),
            'replaceexisting' => new external_value(PARAM_BOOL, 'Remove destination stages first'),
            'lockid' => new external_value(PARAM_INT, 'Editor lock id'),
        ]);
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure Output description.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'copied' => new external_value(PARAM_INT, 'Number of copied stages'),
        ]);
    }

    /**
     * Copy stages between roads.
     *
     * @param int $treasurehuntid Activity instance id.
     * @param int $sourceroadid Source road id.
     * @param int $targetroadid Destination road id.
     * @param bool $replaceexisting Delete existing target stages.
     * @param int $lockid Editor lock id.
     * @return array Copy result.
     */
    public static function execute($treasurehuntid, $sourceroadid, $targetroadid, $replaceexisting, $lockid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), compact(
            'treasurehuntid',
            'sourceroadid',
            'targetroadid',
            'replaceexisting',
            'lockid'
        ));
        $cm = get_coursemodule_from_instance('treasurehunt', $params['treasurehuntid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/treasurehunt:managetreasurehunt', $context);
        require_capability('mod/treasurehunt:editstage', $context);
        if (!treasurehunt_ensure_editor_lock($params['lockid'], $params['treasurehuntid'], $USER->id)) {
            throw new \moodle_exception('editorlockchanged', 'treasurehunt');
        }
        return ['copied' => treasurehunt_copy_stages(
            $params['sourceroadid'],
            $params['targetroadid'],
            $params['treasurehuntid'],
            $params['replaceexisting'],
            $context
        )];
    }

    /**
     * Allow the service to be called through AJAX.
     *
     * @return bool Whether AJAX calls are permitted.
     */
    public static function execute_is_allowed_from_ajax(): bool {
        return true;
    }
}
