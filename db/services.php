<?php

$services = array(
    'scavengerservices' => array(//the name of the web service
        'functions' => array('mod_scavengerhunt_fetch_scavengerhunt', 'mod_scavengerhunt_update_riddles', 'mod_scavengerhunt_delete_riddle', 'mod_scavengerhunt_delete_road', 'mod_scavengerhunt_renew_lock','mod_scavengerhunt_send_location','mod_scavengerhunt_user_progress'), //web service functions of this service
        'requiredcapability' => '', //if set, the web service user need this capability to access 
        //any function of this service. For example: 'some/capability:specified'                 
        'restrictedusers' => 0, //if enabled, the Moodle administrator must link some user to this service
        //into the administration
        'enabled' => 1, //if enabled, the service can be reachable on a default installation
    )
);

$functions = array(
    'mod_scavengerhunt_fetch_scavengerhunt' => array(//web service function name
        'classname' => 'mod_scavengerhunt_external_fetch_scavengerhunt', //class containing the external function
        'methodname' => 'fetch_scavengerhunt', //external function name
        'classpath' => 'mod/scavengerhunt/externallib.php', //file containing the class/external function
        'description' => 'Fetch all the features of stage.', //human readable description of the web service function
        'type' => 'read', //database rights of the web service function (read, write)
    ),
    'mod_scavengerhunt_update_riddles' => array(//web service function name
        'classname' => 'mod_scavengerhunt_external_update_riddles', //class containing the external function
        'methodname' => 'update_riddles', //external function name
        'classpath' => 'mod/scavengerhunt/externallib.php', //file containing the class/external function
        'description' => 'Creates new groups.', //human readable description of the web service function
        'type' => 'write', //database rights of the web service function (read, write)
    ),
    'mod_scavengerhunt_delete_riddle' => array(//web service function name
        'classname' => 'mod_scavengerhunt_external_delete_riddle', //class containing the external function
        'methodname' => 'delete_riddle', //external function name
        'classpath' => 'mod/scavengerhunt/externallib.php', //file containing the class/external function
        'description' => 'Creates new groups.', //human readable description of the web service function
        'type' => 'write', //database rights of the web service function (read, write)
    ),
    'mod_scavengerhunt_delete_road' => array(//web service function name
        'classname' => 'mod_scavengerhunt_external_delete_road', //class containing the external function
        'methodname' => 'delete_road', //external function name
        'classpath' => 'mod/scavengerhunt/externallib.php', //file containing the class/external function
        'description' => 'Creates new groups.', //human readable description of the web service function
        'type' => 'write', //database rights of the web service function (read, write)
    ),
    'mod_scavengerhunt_renew_lock' => array(//web service function name
        'classname' => 'mod_scavengerhunt_external_renew_lock', //class containing the external function
        'methodname' => 'renew_lock', //external function name
        'classpath' => 'mod/scavengerhunt/externallib.php', //file containing the class/external function
        'description' => 'Creates new groups.', //human readable description of the web service function
        'type' => 'write', //database rights of the web service function (read, write)
    ),
    'mod_scavengerhunt_send_location' => array(//web service function name
        'classname' => 'mod_scavengerhunt_external_send_location', //class containing the external function
        'methodname' => 'send_location', //external function name
        'classpath' => 'mod/scavengerhunt/externallib.php', //file containing the class/external function
        'description' => 'Creates new groups.', //human readable description of the web service function
        'type' => 'write', //database rights of the web service function (read, write)
    ),
        'mod_scavengerhunt_user_progress' => array(//web service function name
        'classname' => 'mod_scavengerhunt_external_user_progress', //class containing the external function
        'methodname' => 'user_progress', //external function name
        'classpath' => 'mod/scavengerhunt/externallib.php', //file containing the class/external function
        'description' => 'Creates new groups.', //human readable description of the web service function
        'type' => 'read', //database rights of the web service function (read, write)
    ),
);
