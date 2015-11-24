<?php

$services = array(
      'scavengerservices' => array(                                                //the name of the web service
          'functions' => array ('mod_scavengerhunt_fetchstage'), //web service functions of this service
          'requiredcapability' => '',                //if set, the web service user need this capability to access 
                                                                              //any function of this service. For example: 'some/capability:specified'                 
          'restrictedusers' =>0,                                             //if enabled, the Moodle administrator must link some user to this service
                                                                              //into the administration
          'enabled'=>1,                                                       //if enabled, the service can be reachable on a default installation
       )
  );

$functions = array(
    'mod_scavengerhunt_fetchstage' => array(         //web service function name
        'classname'   => 'mod_scavengerhunt_external_fetchstage',  //class containing the external function
        'methodname'  => 'fetchstage',          //external function name
        'classpath'   => 'mod/scavengerhunt/externallib.php',  //file containing the class/external function
        'description' => 'Fetch all the features of stage.',    //human readable description of the web service function
        'type'        => 'read',                  //database rights of the web service function (read, write)
    ),
    'mod_scavengerhunt_savestage' => array(         //web service function name
        'classname'   => 'mod_scavengerhunt_external_savestage',  //class containing the external function
        'methodname'  => 'savestage',          //external function name
        'classpath'   => 'mod/scavengerhunt/externallib.php',  //file containing the class/external function
        'description' => 'Creates new groups.',    //human readable description of the web service function
        'type'        => 'write',                  //database rights of the web service function (read, write)
    ),
);