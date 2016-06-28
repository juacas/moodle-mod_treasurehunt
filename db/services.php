<?php

$services = array(
    'treasurehuntservices' => array(//the name of the web service
        'functions' => array('mod_treasurehunt_fetch_treasurehunt',
            'mod_treasurehunt_update_riddles', 'mod_treasurehunt_delete_riddle',
            'mod_treasurehunt_delete_road', 'mod_treasurehunt_renew_lock',
            'mod_treasurehunt_user_progress'), //web service functions of this service
        'requiredcapability' => '', //if set, the web service user need this capability to access 
        //any function of this service. For example: 'some/capability:specified'                 
        'restrictedusers' => 0, //if enabled, the Moodle administrator must link some user to this service
        //into the administration
        'enabled' => 1, //if enabled, the service can be reachable on a default installation
    )
);

$functions = array(
    'mod_treasurehunt_fetch_treasurehunt' => array(//web service function name
        'classname' => 'mod_treasurehunt_external_fetch_treasurehunt', //class containing the external function
        'methodname' => 'fetch_treasurehunt', //external function name
        'classpath' => 'mod/treasurehunt/externallib.php', //file containing the class/external function
        'description' => 'Fetch all the features of stage.', //human readable description of the web service function
        'type' => 'read', //database rights of the web service function (read, write)
    ),
    'mod_treasurehunt_update_riddles' => array(//web service function name
        'classname' => 'mod_treasurehunt_external_update_riddles', //class containing the external function
        'methodname' => 'update_riddles', //external function name
        'classpath' => 'mod/treasurehunt/externallib.php', //file containing the class/external function
        'description' => 'Creates new groups.', //human readable description of the web service function
        'type' => 'write', //database rights of the web service function (read, write)
    ),
    'mod_treasurehunt_delete_riddle' => array(//web service function name
        'classname' => 'mod_treasurehunt_external_delete_riddle', //class containing the external function
        'methodname' => 'delete_riddle', //external function name
        'classpath' => 'mod/treasurehunt/externallib.php', //file containing the class/external function
        'description' => 'Creates new groups.', //human readable description of the web service function
        'type' => 'write', //database rights of the web service function (read, write)
    ),
    'mod_treasurehunt_delete_road' => array(//web service function name
        'classname' => 'mod_treasurehunt_external_delete_road', //class containing the external function
        'methodname' => 'delete_road', //external function name
        'classpath' => 'mod/treasurehunt/externallib.php', //file containing the class/external function
        'description' => 'Creates new groups.', //human readable description of the web service function
        'type' => 'write', //database rights of the web service function (read, write)
    ),
    'mod_treasurehunt_renew_lock' => array(//web service function name
        'classname' => 'mod_treasurehunt_external_renew_lock', //class containing the external function
        'methodname' => 'renew_lock', //external function name
        'classpath' => 'mod/treasurehunt/externallib.php', //file containing the class/external function
        'description' => 'Creates new groups.', //human readable description of the web service function
        'type' => 'write', //database rights of the web service function (read, write)
    ),
    'mod_treasurehunt_user_progress' => array(//web service function name
        'classname' => 'mod_treasurehunt_external_user_progress', //class containing the external function
        'methodname' => 'user_progress', //external function name
        'classpath' => 'mod/treasurehunt/externallib.php', //file containing the class/external function
        'description' => 'Creates new groups.', //human readable description of the web service function
        'type' => 'write', //database rights of the web service function (read, write)
    ),
);
