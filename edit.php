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
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @author Adrian Rodriguez <huorwhisp@gmail.com>
 * @author Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("$CFG->dirroot/mod/treasurehunt/locallib.php");

global $USER, $PAGE;

$id = required_param('id', PARAM_INT);
$roadid = optional_param('roadid', 0, PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($id, 'treasurehunt');
$treasurehunt = $DB->get_record('treasurehunt', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);

require_capability('mod/treasurehunt:managetreasurehunt', $context);

$url = new moodle_url('/mod/treasurehunt/edit.php', ['id' => $cm->id]);
if (!empty($roadid)) {
    $url->param('roadid', $roadid);
    $PAGE->navbar->add(get_string('edittreasurehunt', 'treasurehunt'), $url);
}
// Print the page header.
$title = get_string('editingtreasurehunt', 'treasurehunt') . ': ' . format_string($treasurehunt->name);
$PAGE->set_url($url);
$PAGE->set_title($title);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');
$PAGE->activityheader->disable();
$isbootstrap5 = $CFG->version >= 2025041400;
$bootstrapdataprefix = $isbootstrap5 ? 'data-bs-' : 'data-';
$visuallyhiddenclass = $isbootstrap5 ? 'visually-hidden' : 'sr-only';
$asidebackgroundclass = $isbootstrap5 ? 'bg-body' : 'bg-white';
$boldclass = $isbootstrap5 ? 'fw-bold' : 'font-weight-bold';
$marginendclass = $isbootstrap5 ? 'me-2' : 'mr-2';
$noguttersclass = $isbootstrap5 ? 'g-0' : 'no-gutters';
$borderendclass = $isbootstrap5 ? 'border-end' : 'border-right';

if (!treasurehunt_is_edition_locked($treasurehunt->id, $USER->id)) {
    // Si no hay ningún camino redirijo para crearlo.
    if (treasurehunt_get_total_roads($treasurehunt->id) == 0) {
        $roadurl = new moodle_url('/mod/treasurehunt/editroad.php', ['cmid' => $id]);
        redirect($roadurl);
    }
    $lockid = treasurehunt_try_renew_edition_lock($treasurehunt->id, $USER->id);
    if (!$lockid) {
        throw new moodle_exception('editorlocktaken', 'treasurehunt');
    }
    $renewlocktime = max(1000, (int)floor(treasurehunt_get_setting_lock_time() * 500));
    $PAGE->requires->js_call_amd(
        'mod_treasurehunt/renewlock',
        'renew_edition_lock',
        [$treasurehunt->id, $lockid, $renewlocktime]
    );
    $PAGE->requires->jquery();
    $custommapping = treasurehunt_get_custommappingconfig($treasurehunt, $context);
    $PAGE->requires->js_call_amd(
        $isbootstrap5 ? 'mod_treasurehunt/editmod' : 'mod_treasurehunt/editmod405',
        'edittreasurehunt',
        [ $id, $treasurehunt->id, $roadid, $lockid, $custommapping]
    );
    $PAGE->requires->js_call_amd('mod_treasurehunt/tutorial_edit', 'editpage');
    $PAGE->requires->css('/mod/treasurehunt/css/introjs.css');
    $PAGE->requires->css('/mod/treasurehunt/css/ol.css');
    $PAGE->requires->css('/mod/treasurehunt/css/ol3-layerswitcher.css');
    $PAGE->requires->css('/mod/treasurehunt/css/ol-popup.css');
} else {
    $returnurl = new moodle_url('/mod/treasurehunt/view.php', ['id' => $id]);
    throw new moodle_exception(
        'treasurehuntislocked',
        'treasurehunt',
        $returnurl,
        treasurehunt_get_username_blocking_edition($treasurehunt->id)
    );
}
/** @global core_renderer $OUTPUT */ // phpcs:ignore
echo $OUTPUT->header();
echo '<span id="edition_mainhelp" class="' . $visuallyhiddenclass . '">'
    . $OUTPUT->help_icon('edition', 'treasurehunt') . '</span>';

treasurehunt_notify_info(get_string('editactivity_help', 'treasurehunt'));
echo $OUTPUT->container_start("treasurehunt-editor", "treasurehunt-editor");
echo $OUTPUT->container_start("treasurehunt-editor-loader");
echo $OUTPUT->box(null, 'loader-circle-outside');
echo $OUTPUT->box(null, 'loader-circle-inside');
echo $OUTPUT->container_end();
$buttons = '<div class="treasurehunt-map-tools treasurehunt-editor-actions d-flex flex-nowrap align-items-center'
    . ($isbootstrap5 ? ' gap-2' : '') . '"'
    . ' role="toolbar" aria-label="'
    . s(get_string('editortools', 'treasurehunt')) . '">';
$buttons .= '<div class="treasurehunt-editor-actions-group" role="group" aria-label="'
    . s(get_string('editormap', 'treasurehunt')) . '">';
$buttons .= '<span class="treasurehunt-tool-tooltip" ' . $bootstrapdataprefix . 'toggle="tooltip"'
    . ' ' . $bootstrapdataprefix . 'trigger="hover"'
    . ' ' . $bootstrapdataprefix . 'container="body" ' . $bootstrapdataprefix . 'placement="bottom"'
    . ' title="' . s(get_string('navmodetooltip', 'treasurehunt')) . '">'
    . '<button type="button" id="navmode" class="btn btn-outline-secondary btn-sm" disabled>'
    . s(get_string('browsemode', 'treasurehunt')) . '</button></span>';
$buttons .= '<div class="btn-group btn-group-sm treasurehunt-geometry-mode-group" role="group" aria-label="'
    . s(get_string('editorgeometry', 'treasurehunt')) . '">';
foreach (['drawmode' => 'drawmode', 'editmode' => 'editmode'] as $buttonid => $stringkey) {
    $buttons .= '<span class="treasurehunt-tool-tooltip" ' . $bootstrapdataprefix . 'toggle="tooltip"'
        . ' ' . $bootstrapdataprefix . 'trigger="hover"'
        . ' ' . $bootstrapdataprefix . 'container="body" ' . $bootstrapdataprefix . 'placement="bottom"'
        . ' title="' . s(get_string($stringkey . 'tooltip', 'treasurehunt')) . '">'
        . '<button type="button" id="' . $buttonid . '" class="btn btn-outline-secondary" disabled>'
        . s(get_string($stringkey, 'treasurehunt')) . '</button></span>';
}
$buttons .= '</div>';
$buttons .= '<span class="treasurehunt-tool-tooltip" ' . $bootstrapdataprefix . 'toggle="tooltip"'
    . ' ' . $bootstrapdataprefix . 'trigger="hover"'
    . ' ' . $bootstrapdataprefix . 'container="body" ' . $bootstrapdataprefix . 'placement="bottom"'
    . ' title="' . s(get_string('removefeaturetooltip', 'treasurehunt')) . '">'
    . '<button type="button" id="removefeature" class="btn btn-outline-danger btn-sm" disabled>'
    . s(get_string('remove', 'treasurehunt')) . '</button></span>';
$buttons .= $OUTPUT->help_icon('editorgeometry', 'treasurehunt');
$buttons .= '</div><div class="treasurehunt-editor-actions-group" role="group" aria-label="'
    . s(get_string('editorsave', 'treasurehunt')) . '">';
$buttons .= '<button type="button" id="savestage" class="btn btn-primary btn-sm" disabled>'
    . s(get_string('save', 'treasurehunt')) . '</button>';
$buttons .= $OUTPUT->help_icon('editorsave', 'treasurehunt');
$buttons .= '</div></div>';
echo '<div id="roadlistpanel" class="treasurehunt-road-tabs border-bottom" role="navigation" aria-label="'
    . s(get_string('editorroads', 'treasurehunt')) . '">'
    . '<div id="roadtabscontainer">'
    . '<ul id="roadlist" class="nav nav-tabs flex-nowrap" role="tablist"'
    . ' aria-label="' . s(get_string('road', 'treasurehunt')) . '"></ul>'
    . '<button type="button" id="addroad" class="btn btn-outline-primary btn-sm treasurehunt-add-road"'
    . ' ' . $bootstrapdataprefix . 'toggle="tooltip" ' . $bootstrapdataprefix . 'trigger="hover"'
    . ' ' . $bootstrapdataprefix . 'placement="top"'
    . ' title="' . s(get_string('treasurehunt:addroad', 'treasurehunt')) . '"'
    . ' aria-label="' . s(get_string('treasurehunt:addroad', 'treasurehunt')) . '">+</button></div>'
    . $OUTPUT->help_icon('editorroads', 'treasurehunt') . '</div>';
echo $OUTPUT->container_start('treasurehunt-editor-workspace', 'editorworkspace');
echo $buttons;
echo '<span class="' . $visuallyhiddenclass . '" id="treasurehunt-map-label">'
    . s(get_string('editormap', 'treasurehunt')) . '</span>';
echo $OUTPUT->box(null, null, 'mapedit');
echo $OUTPUT->container_start('treasurehunt-editor-aside border rounded ' . $asidebackgroundclass, 'editoraside');
echo '<div class="treasurehunt-editor-aside-heading">'
    . '<span class="treasurehunt-panel-title">' . s(get_string('stages', 'treasurehunt')) . '</span>'
    . $OUTPUT->help_icon('editorstages', 'treasurehunt') . '</div>';
echo $OUTPUT->box(null, 'invisible', 'stagelistpanel');
echo '<div class="treasurehunt-stage-actions">'
    . '<button type="button" id="addstage" class="btn btn-outline-primary btn-sm" disabled'
    . ' aria-label="' . s(get_string('treasurehunt:addstage', 'treasurehunt')) . '"'
    . ' title="' . s(get_string('treasurehunt:addstage', 'treasurehunt')) . '">'
    . '<i class="fa fa-plus" aria-hidden="true"></i>'
    . '<span class="treasurehunt-stage-action-label">'
    . s(get_string('treasurehunt:addstage', 'treasurehunt')) . '</span></button>'
    . '<button type="button" id="copystages" class="btn btn-outline-primary btn-sm" hidden'
    . ' aria-label="' . s(get_string('editorcopystages', 'treasurehunt')) . '"'
    . ' title="' . s(get_string('editorcopystages', 'treasurehunt')) . '">'
    . '<i class="fa fa-copy" aria-hidden="true"></i>'
    . '<span class="treasurehunt-stage-action-label">'
    . s(get_string('editorcopybutton', 'treasurehunt')) . '</span></button>'
    . '</div>';
echo '<button type="button" id="toggleleftpanel" class="btn btn-outline-secondary btn-sm"'
    . ' aria-controls="editoraside" aria-expanded="true"'
    . ' aria-label="' . s(get_string('editorcollapsepanel', 'treasurehunt')) . '"'
    . ' title="' . s(get_string('editorcollapsepanel', 'treasurehunt')) . '"'
    . ' data-collapse-label="' . s(get_string('editorcollapsepanel', 'treasurehunt')) . '"'
    . ' data-expand-label="' . s(get_string('editorexpandpanel', 'treasurehunt')) . '">'
    . '<i class="fa fa-angle-double-left" aria-hidden="true"></i>'
    . '</button>';
echo $OUTPUT->container_end();
echo $OUTPUT->container_end();
echo $OUTPUT->container_end();
echo '<div class="modal fade" id="copystagesmodal" tabindex="-1" aria-labelledby="copystagesmodaltitle" aria-hidden="true">'
    . '<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">'
    . '<div class="modal-content shadow border-0">'
    . '<div class="modal-header bg-light border-bottom">'
    . '<h5 class="modal-title ' . $boldclass . '" id="copystagesmodaltitle">'
    . '<i class="fa fa-copy text-primary ' . $marginendclass . '" aria-hidden="true"></i>'
    . s(get_string('editorcopystages', 'treasurehunt')) . '</h5>'
    . '<button type="button" class="' . ($isbootstrap5 ? 'btn-close' : 'close') . '"'
    . ' ' . $bootstrapdataprefix . 'dismiss="modal" aria-label="'
    . s(get_string('closebuttontitle', 'moodle')) . '">'
    . ($isbootstrap5 ? '' : '<span aria-hidden="true">&times;</span>') . '</button></div>'
    . '<div class="modal-body p-0"><div class="row ' . $noguttersclass . '">'
    . '<div class="col-md-5 ' . $borderendclass . ' bg-light p-3">'
    . '<div class="text-uppercase small ' . $boldclass . ' text-dark mb-2 px-1">'
    . s(get_string('editorcopysource', 'treasurehunt')) . '</div>'
    . '<div id="copystagesroads" class="list-group"></div></div>'
    . '<div class="col-md-7 p-4"><div id="copystagessummary" class="mb-3" role="status"></div>'
    . '<div class="form-check"><input class="form-check-input" type="checkbox" id="copystagesreplace">'
    . '<label class="form-check-label" for="copystagesreplace">'
    . s(get_string('editorcopyreplace', 'treasurehunt')) . '</label></div>'
    . '</div></div></div><div class="modal-footer bg-light border-top">'
    . '<button type="button" class="btn btn-secondary btn-sm"'
    . ' ' . $bootstrapdataprefix . 'dismiss="modal">'
    . s(get_string('cancel', 'treasurehunt')) . '</button>'
    . '<button type="button" id="copystagessave" class="btn btn-primary btn-sm" disabled>'
    . s(get_string('save', 'treasurehunt')) . '</button></div></div></div></div>';

// Finish the page.
echo $OUTPUT->footer();
