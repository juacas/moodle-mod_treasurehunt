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
 * Page to edit instances
 *
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @author Adrian Rodriguez <huorwhisp@gmail.com>
 * @author Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http:// www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("$CFG->dirroot/mod/treasurehunt/locallib.php");

global $USER, $PAGE;

$id = required_param('id', PARAM_INT);
$roadid = optional_param('roadid', 0, PARAM_INT);
list($course, $cm) = get_course_and_cm_from_cmid($id, 'treasurehunt');
$treasurehunt = $DB->get_record('treasurehunt', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);

require_capability('mod/treasurehunt:managetreasurehunt', $context);

$url = new moodle_url('/mod/treasurehunt/edit.php', array('id' => $cm->id));
if (!empty($roadid)) {
    $url->param('roadid', $roadid);
    $PAGE->navbar->add(get_string('edittreasurehunt', 'treasurehunt'), $url);
}
// Print the page header.
$title = get_string('editingtreasurehunt', 'treasurehunt') . ': ' . format_string($treasurehunt->name);
$PAGE->set_url($url);
$PAGE->set_title($title);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('standard');
$PAGE->activityheader->disable();

if (!treasurehunt_is_edition_locked($treasurehunt->id, $USER->id)) {
    // Si no hay ningún camino redirijo para crearlo.
    if (treasurehunt_get_total_roads($treasurehunt->id) == 0) {
        $roadurl = new moodle_url('/mod/treasurehunt/editroad.php', array('cmid' => $id));
        redirect($roadurl);
    }
    $lockid = treasurehunt_renew_edition_lock($treasurehunt->id, $USER->id);
    $renewlocktime = (treasurehunt_get_setting_lock_time() - 5) * 1000;
    $PAGE->requires->js_call_amd(
        'mod_treasurehunt/renewlock',
        'renew_edition_lock',
        array($treasurehunt->id, $lockid, $renewlocktime)
    );
    $PAGE->requires->jquery();
    $PAGE->requires->jquery_plugin('ui');
    $PAGE->requires->jquery_plugin('ui-css');
    $custommapping = treasurehunt_get_custommappingconfig($treasurehunt, $context);
    $PAGE->requires->js_call_amd(
        'mod_treasurehunt/edit',
        'edittreasurehunt',
        array(
            $id, $treasurehunt->id, $roadid, $lockid,
            $custommapping
        )
    );
    $PAGE->requires->js_call_amd('mod_treasurehunt/tutorial_edit', 'editpage');
    $PAGE->requires->css('/mod/treasurehunt/css/introjs.css');
    $PAGE->requires->css('/mod/treasurehunt/css/ol.css');
    $PAGE->requires->css('/mod/treasurehunt/css/ol3-layerswitcher.css');
    $PAGE->requires->css('/mod/treasurehunt/css/ol-popup.css');
} else {
    $returnurl = new moodle_url('/mod/treasurehunt/view.php', array('id' => $id));
    throw new moodle_exception('treasurehuntislocked', 'treasurehunt', $returnurl, treasurehunt_get_username_blocking_edition($treasurehunt->id));
}
/** @var core_renderer $OUTPUT */
echo $OUTPUT->header();
echo $OUTPUT->container_start('', 'edition_maintitle'); // For locating the help icon and override it in tutorial.js.
echo $OUTPUT->heading_with_help($title, 'edition', 'treasurehunt');
echo $OUTPUT->container_end();
// Conditions to show the intro can change to look for own settings or whatever.
// if ($treasurehunt->intro) {
//     echo $OUTPUT->box(format_module_intro('treasurehunt', $treasurehunt, $cm->id), 'generalbox mod_introbox', 'treasurehuntintro');
// }
treasurehunt_notify_info(get_string('editactivity_help', 'treasurehunt'), 'info');
echo $OUTPUT->container_start("treasurehunt-editor", "treasurehunt-editor");
echo $OUTPUT->container_start("treasurehunt-editor-loader");
echo $OUTPUT->box(null, 'loader-circle-outside');
echo $OUTPUT->box(null, 'loader-circle-inside');
echo $OUTPUT->container_end();
echo $OUTPUT->box(get_string('errvalidroad', 'treasurehunt'), 'alert alert-error invisible', 'errvalidroad');
echo $OUTPUT->box(get_string('erremptystage', 'treasurehunt'), 'alert alert-error invisible', 'erremptystage');
//echo $OUTPUT->box($OUTPUT->help_icon('edition', 'treasurehunt', ''), 'invisible', 'controlpanel');
$buttons = "<button id=\"addroad\" class=\"btn btn-secondary\" >" . get_string('treasurehunt:addroad', 'treasurehunt') ."</button>";
$buttons .= "<button id=\"addstage\" class=\"btn btn-secondary\" disabled=\"disabled\">" . get_string('treasurehunt:addstage', 'treasurehunt') . "</button>";
$buttons .= "<button id=\"drawmode\" class=\"btn btn-secondary\" disabled=\"disabled\">" . get_string('drawmode', 'treasurehunt') . "</button>";
$buttons .= "<button id=\"editmode\" class=\"btn btn-secondary\" disabled=\"disabled\">" . get_string('editmode', 'treasurehunt') . "</button>";
$buttons .= "<button id=\"navmode\" class=\"btn btn-secondary\" disabled=\"disabled\">" . get_string('browsemode', 'treasurehunt') . "</button>";
$buttons .= "<button id=\"savestage\" class=\"btn btn-secondary\" disabled=\"disabled\">" . get_string('save', 'treasurehunt') . "</button>";
$buttons .= "<button id=\"removefeature\" class=\"btn btn-secondary\" disabled=\"disabled\">" . get_string('remove', 'treasurehunt') . "</button>";
echo $OUTPUT->box($buttons, "box py-3 ui-widget-header ui-corner-all", 'controlpanel');
echo $OUTPUT->box(null, 'invisible', 'stagelistpanel');
echo $OUTPUT->box(null, null, 'mapedit');
echo $OUTPUT->box(null, null, 'roadlistpanel');
echo $OUTPUT->container_end();

// Finish the page.
echo $OUTPUT->footer();
