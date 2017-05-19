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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Web service for mod treasurehunt.
 *
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$services = array(
    'treasurehuntservices' => array(//the name of the web service
        'functions' => array('mod_treasurehunt_fetch_treasurehunt',
            'mod_treasurehunt_update_stages', 'mod_treasurehunt_delete_stage',
            'mod_treasurehunt_delete_road', 'mod_treasurehunt_renew_lock',
            'mod_treasurehunt_user_progress'), //web service functions of this service
        'requiredcapability' => '', //if set, the web service user need this capability to access 
        //any function of this service. For example: 'some/capability:specified'                 
        'restrictedusers' => 0, //if enabled, the Moodle administrator must link some user to this service
        //into the administration
        'enabled' => true, //if enabled, the service can be reachable on a default installation
    )
);

$functions = array(
    'mod_treasurehunt_fetch_treasurehunt' => array(//web service function name
        'classname' => 'mod_treasurehunt_external', //class containing the external function
        'methodname' => 'fetch_treasurehunt', //external function name
        'classpath' => 'mod/treasurehunt/externallib.php', //file containing the class/external function
        'description' => 'Fetch all the roads and stages of instance.', //human readable description of the web service function
        'type' => 'read', //database rights of the web service function (read, write)
        'capabilities' => 'mod/treasurehunt:managetreasurehunt',
        'ajax' => true, // allowed from ajax.
    ),
    'mod_treasurehunt_update_stages' => array(//web service function name
        'classname' => 'mod_treasurehunt_external', //class containing the external function
        'methodname' => 'update_stages', //external function name
        'classpath' => 'mod/treasurehunt/externallib.php', //file containing the class/external function
        'description' => 'Updates all stages position and geometry given.', //human readable description of the web service function
        'type' => 'write', //database rights of the web service function (read, write)
        'capabilities' => 'mod/treasurehunt:managetreasurehunt, mod/treasurehunt:editstage',
        'ajax' => true, // allowed from ajax.
    ),
    'mod_treasurehunt_delete_stage' => array(//web service function name
        'classname' => 'mod_treasurehunt_external', //class containing the external function
        'methodname' => 'delete_stage', //external function name
        'classpath' => 'mod/treasurehunt/externallib.php', //file containing the class/external function
        'description' => 'Delete a stage given.', //human readable description of the web service function
        'type' => 'write', //database rights of the web service function (read, write)
        'capabilities' => 'mod/treasurehunt:managetreasurehunt, mod/treasurehunt:editstage',
        'ajax' => true, // allowed from ajax.
    ),
    'mod_treasurehunt_delete_road' => array(//web service function name
        'classname' => 'mod_treasurehunt_external', //class containing the external function
        'methodname' => 'delete_road', //external function name
        'classpath' => 'mod/treasurehunt/externallib.php', //file containing the class/external function
        'description' => 'Delete a road given.', //human readable description of the web service function
        'type' => 'write', //database rights of the web service function (read, write)
        'capabilities' => 'mod/treasurehunt:managetreasurehunt, mod/treasurehunt:editroad',
        'ajax' => true, // allowed from ajax.
    ),
    'mod_treasurehunt_renew_lock' => array(//web service function name
        'classname' => 'mod_treasurehunt_external', //class containing the external function
        'methodname' => 'renew_lock', //external function name
        'classpath' => 'mod/treasurehunt/externallib.php', //file containing the class/external function
        'description' => 'Renew user edition lock of instance.', //human readable description of the web service function
        'type' => 'write', //database rights of the web service function (read, write)
        'capabilities' => 'mod/treasurehunt:managetreasurehunt',
        'ajax' => true, // allowed from ajax.
    ),
    'mod_treasurehunt_user_progress' => array(//web service function name
        'classname' => 'mod_treasurehunt_external', //class containing the external function
        'methodname' => 'user_progress', //external function name
        'classpath' => 'mod/treasurehunt/externallib.php', //file containing the class/external function
        'description' => 'Check user progress in a game.', //human readable description of the web service function
        'type' => 'write', //database rights of the web service function (read, write)
        'capabilities' => 'mod/treasurehunt:play',
        'ajax' => true, // allowed from ajax.
    ),
);
