<?php
// This file is part of Moodle - http:// moodle.org/
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
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Web service for mod treasurehunt.
 *
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http:// www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$services = array(
    'treasurehuntservices' => array(// The name of the web service.
        'functions' => array('mod_treasurehunt_fetch_treasurehunt',
            'mod_treasurehunt_update_stages', 'mod_treasurehunt_delete_stage',
            'mod_treasurehunt_delete_road', 'mod_treasurehunt_renew_lock',
            'mod_treasurehunt_user_progress'), // Web service functions of this service.
        'requiredcapability' => '', // If set, the web service user need this capability to access .
        // Any function of this service. For example: 'some/capability:specified'.
        'restrictedusers' => 0, // If enabled, the Moodle administrator must link some user to this service.
        // Into the administration.
        'enabled' => true, // If enabled, the service can be reachable on a default installation.
    )
);

$functions = array(
    'mod_treasurehunt_fetch_treasurehunt' => array(// Web service function name.
        'classname' => 'mod_treasurehunt_external', // Class containing the external function.
        'methodname' => 'fetch_treasurehunt', // External function name.
        'classpath' => 'mod/treasurehunt/externallib.php', // File containing the class/external function.
        'description' => 'Fetch all the roads and stages of instance.', // Human readable description of the web service function.
        'type' => 'read', // Database rights of the web service function (read, write).
        'capabilities' => 'mod/treasurehunt:managetreasurehunt',
        'ajax' => true, // Allowed from ajax.
    ),
    'mod_treasurehunt_update_stages' => array(// Web service function name.
        'classname' => 'mod_treasurehunt_external', // Class containing the external function.
        'methodname' => 'update_stages', // External function name.
        'classpath' => 'mod/treasurehunt/externallib.php', // File containing the class/external function.
        'description' => 'Updates all stages position and geometry given.', // Human readable description of the web service function.
        'type' => 'write', // Database rights of the web service function (read, write).
        'capabilities' => 'mod/treasurehunt:managetreasurehunt, mod/treasurehunt:editstage',
        'ajax' => true, // Allowed from ajax.
    ),
    'mod_treasurehunt_delete_stage' => array(// Web service function name.
        'classname' => 'mod_treasurehunt_external', // Class containing the external function.
        'methodname' => 'delete_stage', // External function name.
        'classpath' => 'mod/treasurehunt/externallib.php', // File containing the class/external function.
        'description' => 'Delete a stage given.', // Human readable description of the web service function.
        'type' => 'write', // Database rights of the web service function (read, write).
        'capabilities' => 'mod/treasurehunt:managetreasurehunt, mod/treasurehunt:editstage',
        'ajax' => true, // Allowed from ajax.
    ),
    'mod_treasurehunt_delete_road' => array(// Web service function name.
        'classname' => 'mod_treasurehunt_external', // Class containing the external function.
        'methodname' => 'delete_road', // External function name.
        'classpath' => 'mod/treasurehunt/externallib.php', // File containing the class/external function.
        'description' => 'Delete a road given.', // Human readable description of the web service function.
        'type' => 'write', // Database rights of the web service function (read, write).
        'capabilities' => 'mod/treasurehunt:managetreasurehunt, mod/treasurehunt:editroad',
        'ajax' => true, // Allowed from ajax.
    ),
    'mod_treasurehunt_renew_lock' => array(// Web service function name.
        'classname' => 'mod_treasurehunt_external', // Class containing the external function.
        'methodname' => 'renew_lock', // External function name.
        'classpath' => 'mod/treasurehunt/externallib.php', // File containing the class/external function.
        'description' => 'Renew user edition lock of instance.', // Human readable description of the web service function.
        'type' => 'write', // Database rights of the web service function (read, write).
        'capabilities' => 'mod/treasurehunt:managetreasurehunt',
        'ajax' => true, // Allowed from ajax.
    ),
    'mod_treasurehunt_user_progress' => array(// Web service function name.
        'classname' => 'mod_treasurehunt_external', // Class containing the external function.
        'methodname' => 'user_progress', // External function name.
        'classpath' => 'mod/treasurehunt/externallib.php', // File containing the class/external function.
        'description' => 'Check user progress in a game.', // Human readable description of the web service function.
        'type' => 'write', // Database rights of the web service function (read, write).
        'capabilities' => 'mod/treasurehunt:play',
        'ajax' => true, // Allowed from ajax.
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
);
