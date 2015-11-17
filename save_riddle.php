<?php

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/riddle_form.php');
require_once("$CFG->dirroot/mod/scavengerhunt/locallib.php");

global $COURSE, $PAGE, $CFG;
// You will process some page parameters at the top here and get the info about
// what instance of your module and what course you're in etc. Make sure you
// include hidden variable in your forms which have their defaults set in set_data
// which pass these variables from page to page.
// Setup $PAGE here.
// Print the page header.
$cmid = required_param('cmid', PARAM_INT); // Course_module ID
$id   = optional_param('id', 0, PARAM_INT);           // EntryID
$newFeature = optional_param('newFeature', 0, PARAM_BOOL); //New Feature


if ($cmid) {
    $cm = get_coursemodule_from_id('scavengerhunt', $cmid, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $scavengerhunt = $DB->get_record('scavengerhunt', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    print_error('invalidcoursemodule');
}
require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$url = new moodle_url('/mod/scavengerhunt/save_riddle.php', array('cmid' => $cmid));
if (!empty($id)) {
    $url->param('id', $id);
}
$PAGE->set_url($url);

$returnurl = 'view.php?id=' . $cm->id;

//Cargo las clases necesarias de un objeto GeoJSON
spl_autoload_register(array('GeoJSON', 'autoload'));

if ($id) { // if entry is specified
 
    $sql = 'SELECT id,name,description,descriptionformat,descriptiontrust FROM mdl_scavengerhunt_riddle  WHERE id=?';
    $parms = array('id'=>$id);
    if (!$entry = $DB->get_record_sql($sql, $parms)) {
        print_error('invalidentry');
    }
    $entry->cmid = $cmid;
}
if (!$newFeature && !$id) {
    //Compruebo que existe el json
    $json = required_param('json', PARAM_RAW);
    //Lo convierto a un objeto php
    $geojson = new GeoJSON();
    $features = $geojson->load($json);
    //Recorro las features
    while ($current = $features->current()) {
        if ($current->getProperty('idRiddle') === -1) {
            $entry = new stdClass();
            $entry->id = null;
            $entry->cmid = $cmid;
            $entry->geom = geojson_to_wkt($geojson->dump($current));
            $entry->num_riddle = $current->getProperty('numRiddle');
            $entry->road_id = 1;
            //$entry->road_id = $current->getProperty('idRoad'); 
            $entry->newFeature = 1;
            $newFeature = 1;
        } else {
            updateFeatureBD($current);
        }
        $features->next();
    }
} else {
    //Debería hacer algo pero no he plateado bien el flujo? 
}

if ($newFeature || $id) {

    if (!isset($entry)) {
        $entry = new stdClass();
        $entry->id = null;
    }
    $maxbytes = get_user_max_upload_file_size($PAGE->context, $CFG->maxbytes, $COURSE->maxbytes);
    $descriptionoptions = array('trusttext' => true, 'maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes' => $maxbytes, 'context' => $context,
        'subdirs' => false);
    $entry = file_prepare_standard_editor($entry, 'description', $descriptionoptions, $context, 'mod_scavengerhunt', 'entry', $entry->id);


    $mform = new riddle_form(null, array('current' => $entry, 'descriptionoptions' => $descriptionoptions)); //name of the form you defined in file above.

    if ($mform->is_cancelled()) {
// You need this section if you have a cancel button on your form
// here you tell php what to do if your user presses cancel
// probably a redirect is called for!
// PLEASE NOTE: is_cancelled() should be called before get_data().
        redirect($returnurl);
    } else if ($entry = $mform->get_data()) {


        //Actualizamos los campos
        $entry->name = trim($entry->name);
        $entry->description = '';          // updated later
        $entry->descriptionformat = FORMAT_HTML; // updated later
        $entry->descriptiontrust = 0;           // updated later
        $entry->question_id = null;
        if (empty($entry->id)) {
             $entry->id = insertFeatureBD($entry);
        }
        // This branch is where you process validated data.
        // Do stuff ...
        // Typically you finish up by redirecting to somewhere where the user
        // can see what they did.
        // save and relink embedded images and save attachments
        $entry = file_postupdate_standard_editor($entry, 'description', $descriptionoptions, $context, 'mod_scavengerhunt', 'entry', $entry->id);
        // store the updated value values
        updateEntryBD($entry);
        redirect($returnurl);
    }
    $PAGE->set_title('Caza del tesoro');
    $PAGE->set_heading('Prueba');
    $PAGE->set_pagelayout('standard');
    echo $OUTPUT->header();
    echo $OUTPUT->heading("A heading here");
    $mform->display();
    echo $OUTPUT->footer();
} else {
    //Se acabó
}

function insertFeatureBD(stdClass $entry) {
    GLOBAL $DB;
    $timenow = time();
    $idRiddle = $entry->id;
    $name = $entry->name;
    $road_id = $entry->road_id;
    $num_riddle = $entry->num_riddle;
    $description = $entry->description;
    $descriptionformat = $entry->descriptionformat;
    $descriptiontrust = $entry->descriptiontrust;
    $timecreated = $timenow;
    $timemodified = $timenow;
    $question_id = $entry->question_id;
    $geometryWKT = $entry->geom;
    $sql = 'INSERT INTO mdl_scavengerhunt_riddle (id, name, road_id, num_riddle, description, descriptionformat, descriptiontrust, '
            . 'timecreated, timemodified, question_id, geom) VALUES ((?),(?),(?),(?),(?),(?),(?),(?),(?),(?),GeomFromText((?)))';
    $parms = array($idRiddle, $name, $road_id, $num_riddle, $description,
        $descriptionformat, $descriptiontrust, $timecreated, $timemodified, $question_id, $geometryWKT);
    $id = $DB->execute($sql,$parms);
    //Como no tengo nada para saber el id, tengo que hacer otra consulta
    $sql = 'SELECT id FROM mdl_scavengerhunt_riddle  WHERE name= ? AND road_id = ? AND num_riddle = ? AND description = ? AND '
            . 'descriptionformat = ? AND descriptiontrust = ? AND timecreated = ? AND timemodified = ?';
    $parms = array($name, $road_id, $num_riddle, $description, $descriptionformat,
        $descriptiontrust, $timecreated, $timemodified);
    //Como nos devuelve un objeto lo convierto en una variable
    $result = $DB->get_record_sql($sql, $parms);
    $id = $result->id;
    return $id;
}

function updateEntryBD(stdClass $entry) {
    GLOBAL $DB;
    $name = $entry->name;
    $description = $entry->description;
    $descriptionformat = $entry->descriptionformat;
    $descriptiontrust = $entry->descriptiontrust;
    $timemodified = time();
    $question_id = $entry->question_id;
    $idRiddle = $entry->id;
    $sql = 'UPDATE mdl_scavengerhunt_riddle SET name=(?), description = (?), descriptionformat=(?), descriptiontrust=(?),timemodified=(?),question_id=(?) WHERE mdl_scavengerhunt_riddle.id = (?)';
    $parms = array($name, $description, $descriptionformat, $descriptiontrust, $timemodified, $question_id, $idRiddle);
    $DB->execute($sql, $parms);
}

function updateFeatureBD(Feature $feature) {
    GLOBAL $DB;
    $geojson = new GeoJSON();
    $numRiddle = $feature->getProperty('numRiddle');
    $geometryWKT = geojson_to_wkt($geojson->dump($feature));
    $timemodified = time();
    $idRiddle = $feature->getProperty('idRiddle');
    $sql = 'UPDATE mdl_scavengerhunt_riddle SET num_riddle=(?), geom = GeomFromText((?)), timemodified=(?) WHERE mdl_scavengerhunt_riddle.id = (?)';
    $parms = array($numRiddle, $geometryWKT, $timemodified, $idRiddle);
    $DB->execute($sql, $parms);
}

