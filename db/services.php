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

$services = [
    'treasurehuntservices' => [// The name of the web service.
        'functions' => [
            'mod_treasurehunt_fetch_treasurehunt',
            'mod_treasurehunt_update_stages',
            'mod_treasurehunt_delete_stage',
            'mod_treasurehunt_delete_road',
            'mod_treasurehunt_renew_lock',
            'mod_treasurehunt_user_progress',
        ],
        'requiredcapability' => '', // If set, the web service user need this capability to access .
        // Any function of this service. For example: 'some/capability:specified'.
        'restrictedusers' => 0, // If enabled, the Moodle administrator must link some user to this service.
        // Into the administration.
        'enabled' => true, // If enabled, the service can be reachable on a default installation.
    ],
];

$functions = [
    'mod_treasurehunt_fetch_treasurehunt' => [// Web service function name.
        'classname' => \mod_treasurehunt\external\fetch_treasurehunt::class, // Class containing the external function.
        'description' => 'Fetch all the roads and stages of instance.', // Human readable description of the web service function.
        'type' => 'read', // Database rights of the web service function (read, write).
        'capabilities' => 'mod/treasurehunt:managetreasurehunt',
        'ajax' => true, // Allowed from ajax.
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'mod_treasurehunt_update_stages' => [// Web service function name.
        // Class containing the external function.
        'classname' => \mod_treasurehunt\external\update_stages::class,
        // Human readable description of the web service function.
        'description' => 'Updates all stages position and geometry given.',
        'type' => 'write', // Database rights of the web service function (read, write).
        'capabilities' => 'mod/treasurehunt:managetreasurehunt, mod/treasurehunt:editstage',
        'ajax' => true, // Allowed from ajax.
    ],
    'mod_treasurehunt_delete_stage' => [// Web service function name.
        'classname' => \mod_treasurehunt\external\delete_stage::class, // Class containing the external function.
        'description' => 'Delete a stage given.', // Human readable description of the web service function.
        'type' => 'write', // Database rights of the web service function (read, write).
        'capabilities' => 'mod/treasurehunt:managetreasurehunt, mod/treasurehunt:editstage',
        'ajax' => true, // Allowed from ajax.
    ],
    'mod_treasurehunt_delete_road' => [// Web service function name.
        'classname' => \mod_treasurehunt\external\delete_road::class, // Class containing the external function.
        'description' => 'Delete a road given.', // Human readable description of the web service function.
        'type' => 'write', // Database rights of the web service function (read, write).
        'capabilities' => 'mod/treasurehunt:managetreasurehunt, mod/treasurehunt:editroad',
        'ajax' => true, // Allowed from ajax.
    ],
    'mod_treasurehunt_renew_lock' => [// Web service function name.
        'classname' => \mod_treasurehunt\external\renew_lock::class, // Class containing the external function.
        'description' => 'Renew user edition lock of instance.', // Human readable description of the web service function.
        'type' => 'write', // Database rights of the web service function (read, write).
        'capabilities' => 'mod/treasurehunt:managetreasurehunt',
        'ajax' => true, // Allowed from ajax.
    ],
    'mod_treasurehunt_user_progress' => [// Web service function name.
        'classname' => \mod_treasurehunt\external\user_progress::class, // Class containing the external function.
        'description' => 'Check user progress in a game.', // Human readable description of the web service function.
        'type' => 'write', // Database rights of the web service function (read, write).
        'capabilities' => 'mod/treasurehunt:play',
        'ajax' => true, // Allowed from ajax.
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];
