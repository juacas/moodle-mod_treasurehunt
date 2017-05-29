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
 * Page to edit road
 *
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @author Adrian Rodriguez <huorwhisp@gmail.com>
 * @author Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/editroad_form.php');
require_once("$CFG->dirroot/mod/treasurehunt/locallib.php");

global $COURSE, $PAGE, $CFG, $USER;
// You will process some page parameters at the top here and get the info about
// what instance of your module and what course you're in etc. Make sure you
// include hidden variable in your forms which have their defaults set in set_data
// which pass these variables from page to page.
// Setup $PAGE here.
// Print the page header.
$cmid = required_param('cmid', PARAM_INT); // Course_module ID.
$id = optional_param('id', 0, PARAM_INT);  // EntryID.

list ($course, $cm) = get_course_and_cm_from_cmid($cmid, 'treasurehunt');
$treasurehunt = $DB->get_record('treasurehunt', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$url = new moodle_url('/mod/treasurehunt/editroad.php', array('cmid' => $cmid));
if (!empty($id)) {
    $url->param('id', $id);
}
$PAGE->set_url($url);

require_capability('mod/treasurehunt:managetreasurehunt', $context);

$returnurl = new moodle_url('/mod/treasurehunt/edit.php', array('id' => $cmid, 'roadid' => $id));

if (!treasurehunt_is_edition_loked($treasurehunt->id, $USER->id)) {
    $lockid = treasurehunt_renew_edition_lock($treasurehunt->id, $USER->id);
    $renewlocktime = (treasurehunt_get_setting_lock_time() - 5) * 1000;
    $PAGE->requires->js_call_amd('mod_treasurehunt/renewlock', 'renew_edition_lock',
                            array($treasurehunt->id, $lockid, $renewlocktime));
    if ($id) { // If entry is specified.
        require_capability('mod/treasurehunt:editroad', $context);
        $title = get_string('editingroad', 'treasurehunt');
        $sql = 'SELECT id,name,groupid,groupingid FROM {treasurehunt_roads}  WHERE id=?';
        $parms = array('id' => $id);
        if (!$road = $DB->get_record_sql($sql, $parms)) {
            print_error('invalidentry');
        }
    } else { // New entry.
        require_capability('mod/treasurehunt:addroad', $context);
        $title = get_string('addingroad', 'treasurehunt');
        $road = new stdClass();
        $road->id = null;
    }
    $road->cmid = $cmid;

    // Check the type of group.
    if ($cm->groupmode) {
        $selectoptions = groups_get_all_groupings($course->id);
        $grouptype = "groupingid";
        $grouptypecond = "AND groupingid != 0";
    } else {
        $selectoptions = groups_get_all_groups($course->id);
        $grouptype = "groupid";
        $grouptypecond = "AND groupid != 0";
    }
    // Delete busy groups.
    $sql = "SELECT $grouptype as busy FROM {treasurehunt_roads}  WHERE treasurehuntid=? AND id !=? $grouptypecond";
    $parms = array('treasurehuntid' => $treasurehunt->id, 'id' => $id);
    $busy = $DB->get_records_sql($sql, $parms);
    foreach ($busy as $option) {
        unset($selectoptions[$option->busy]);
    }
    // Name of the form you defined in file above.
    $mform = new road_form(null, array('current' => $road, 'selectoptions' => $selectoptions, 'groups' => $cm->groupmode));

    if ($mform->is_cancelled()) {
        // You need this section if you have a cancel button on your form
        // here you tell php what to do if your user presses cancel
        // probably a redirect is called for!
        // PLEASE NOTE: is_cancelled() should be called before get_data().
        if (treasurehunt_get_total_roads($treasurehunt->id) == 0) {
            $returnurl = new moodle_url('/mod/treasurehunt/view.php', array('id' => $cmid));
        }
        redirect($returnurl);
    } else if ($road = $mform->get_data()) {
        // Actualizamos los campos.
        $road->name = trim($road->name);
        $road = treasurehunt_add_update_road($treasurehunt, $road, $context);
        $returnurl->param('roadid', $road->id);
        redirect($returnurl);
    }
} else {
    $returnurl = new moodle_url('/mod/treasurehunt/view.php', array('id' => $cmid));
    print_error('treasurehuntislocked', 'treasurehunt', $returnurl, treasurehunt_get_username_blocking_edition($treasurehunt->id));
}
$PAGE->navbar->add(get_string('edittreasurehunt', 'treasurehunt'), $returnurl);
$PAGE->navbar->add(get_string('editroad', 'treasurehunt'), $url);
$PAGE->set_title($title);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('standard');
echo $OUTPUT->header();
echo $OUTPUT->heading($title);
$mform->display();
echo $OUTPUT->footer();



