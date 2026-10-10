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
echo $OUTPUT->image_icon(
    'treasurechest_loading',
    get_string('loading', 'treasurehunt'),
    'treasurehunt',
    ['class' => 'treasurehunt-loading-icon']
);
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
$buttons .= '</div><button type="button" id="opendataopen" class="btn btn-outline-info btn-sm"'
    . ' title="' . s(get_string('opendatatitle', 'treasurehunt')) . '"'
    . ' aria-label="' . s(get_string('opendatatitle', 'treasurehunt')) . '">'
    . '<i class="fa fa-magic" aria-hidden="true"></i></button>'
    . '<div class="treasurehunt-editor-actions-group" role="group" aria-label="'
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
echo '<button type="button" id="togglefullscreen" class="btn btn-light btn-sm treasurehunt-editor-fullscreen-toggle"'
    . ' data-cmid="' . (int)$id . '" data-userid="' . (int)$USER->id . '"'
    . ' aria-pressed="false" aria-label="' . s(get_string('editormaximize', 'treasurehunt')) . '"'
    . ' title="' . s(get_string('editormaximize', 'treasurehunt')) . '"'
    . ' data-maximize-label="' . s(get_string('editormaximize', 'treasurehunt')) . '"'
    . ' data-restore-label="' . s(get_string('editorrestore', 'treasurehunt')) . '">'
    . '<i class="fa fa-arrows-alt" aria-hidden="true"></i></button>';
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

$opendataurl = new moodle_url('/mod/treasurehunt/opendata.php');
$opendatafieldlabels = [];
foreach (['creator', 'date', 'location', 'publisher', 'provider', 'language', 'rights'] as $field) {
    $opendatafieldlabels[$field] = get_string('opendatafield' . $field, 'treasurehunt');
}
echo '<div class="modal fade" id="opendatamodal" tabindex="-1" aria-labelledby="opendatamodaltitle" aria-hidden="true"'
    . ' data-search-url="' . s($opendataurl->out(false)) . '" data-cmid="' . (int)$id . '"'
    . ' data-treasurehuntid="' . (int)$treasurehunt->id . '" data-userid="' . (int)$USER->id . '"'
    . ' data-sesskey="' . s(sesskey()) . '"'
    . ' data-results-label="' . s(get_string('opendataresults', 'treasurehunt')) . '"'
    . ' data-empty-label="' . s(get_string('opendataresultsnone', 'treasurehunt')) . '"'
    . ' data-loading-more-label="' . s(get_string('opendataloadingmore', 'treasurehunt')) . '"'
    . ' data-searching-label="' . s(get_string('opendatasearching', 'treasurehunt')) . '"'
    . ' data-source-searching="' . s(get_string('opendatasourcesearching', 'treasurehunt')) . '"'
    . ' data-source-waiting="' . s(get_string('opendatasourcewaiting', 'treasurehunt')) . '"'
    . ' data-source-done="' . s(get_string('opendatasourcedone', 'treasurehunt')) . '"'
    . ' data-source-failed="' . s(get_string('opendatasourcefailed', 'treasurehunt')) . '"'
    . ' data-layer-label="' . s(get_string('opendatalayer', 'treasurehunt')) . '"'
    . ' data-added-label="' . s(get_string('opendataadded', 'treasurehunt')) . '"'
    . ' data-replaced-label="' . s(get_string('opendatareplaced', 'treasurehunt')) . '"'
    . ' data-storage-error="' . s(get_string('opendatastorageerror', 'treasurehunt')) . '"'
    . ' data-area-error="' . s(get_string('opendatainvalidarea', 'treasurehunt')) . '"'
    . ' data-service-error="' . s(get_string('opendataserviceerror', 'treasurehunt')) . '"'
    . ' data-select-type-error="' . s(get_string('opendataselecttype', 'treasurehunt')) . '"'
    . ' data-aliases-label="' . s(get_string('opendataaliases', 'treasurehunt')) . '"'
    . ' data-item-types-label="' . s(get_string('opendataitemtypes', 'treasurehunt')) . '"'
    . ' data-subjects-label="' . s(get_string('opendatasubjects', 'treasurehunt')) . '"'
    . ' data-categories-label="' . s(get_string('opendatacategories', 'treasurehunt')) . '"'
    . ' data-field-labels="' . s(json_encode($opendatafieldlabels, JSON_THROW_ON_ERROR)) . '"'
    . ' data-official-label="' . s(get_string('opendataofficialwebsite', 'treasurehunt')) . '"'
    . ' data-about-label="' . s(get_string('opendataaboutwebsite', 'treasurehunt')) . '"'
    . ' data-details-loading="' . s(get_string('opendatadetailsloading', 'treasurehunt')) . '"'
    . ' data-create-stage-label="' . s(get_string('opendatacreatestage', 'treasurehunt')) . '"'
    . ' data-creating-stage-label="' . s(get_string('opendatacreatingstage', 'treasurehunt')) . '"'
    . ' data-cannot-create-stage="' . s(get_string('opendatacannotcreatestage', 'treasurehunt')) . '"'
    . ' data-details-unavailable="' . s(get_string('opendatadetailsunavailable', 'treasurehunt')) . '"'
    . ' data-close-label="' . s(get_string('closebuttontitle', 'moodle')) . '">'
    . '<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">'
    . '<div class="modal-content shadow border-0">'
    . '<div class="modal-header bg-light border-bottom">'
    . '<h5 class="modal-title ' . $boldclass . '" id="opendatamodaltitle">'
    . '<i class="fa fa-magic text-primary ' . $marginendclass . '" aria-hidden="true"></i>'
    . s(get_string('opendatatitle', 'treasurehunt')) . '</h5>'
    . '<button type="button" class="' . ($isbootstrap5 ? 'btn-close' : 'close') . '"'
    . ' ' . $bootstrapdataprefix . 'dismiss="modal" aria-label="'
    . s(get_string('closebuttontitle', 'moodle')) . '">'
    . ($isbootstrap5 ? '' : '<span aria-hidden="true">&times;</span>') . '</button></div>'
    . '<div class="modal-body p-0"><div class="row ' . $noguttersclass . '">'
    . '<div class="col-md-5 ' . $borderendclass . ' bg-light p-3">'
    . '<div class="text-uppercase small ' . $boldclass . ' mb-2">'
    . s(get_string('opendatatype', 'treasurehunt')) . '</div>'
    . '<div id="opendatasources">';
foreach (\mod_treasurehunt\opendata\sources::all() as $source) {
    $sourceid = $source->id();
    $available = $source->available();
    $sourcename = get_string('opendata' . $sourceid, 'treasurehunt');
    echo '<section class="card mb-2" data-opendata-source="' . s($sourceid) . '">'
        . '<div class="card-header p-2 d-flex align-items-center justify-content-between">'
        . '<label class="mb-0 ' . $boldclass . '">'
        . '<input type="checkbox" name="opendatasource" value="' . s($sourceid) . '" class="' . $marginendclass . '"'
        . ($sourceid === 'wikidata' ? ' checked' : '') . ($available ? '' : ' disabled') . '> '
        . s($sourcename) . '</label>'
        . '<a class="btn btn-link btn-sm p-0" href="' . s($source->website()) . '"'
        . ' target="_blank" rel="noopener noreferrer"'
        . ' aria-label="' . s(get_string('opendatavisitsource', 'treasurehunt', $sourcename)) . '"'
        . ' title="' . s(get_string('opendatavisitsource', 'treasurehunt', $sourcename)) . '">'
        . '<i class="fa fa-external-link" aria-hidden="true"></i></a></div>';
    if (!$available) {
        echo '<div class="card-body p-2 small text-muted">'
            . s(get_string('opendataeuropeananokey', 'treasurehunt')) . '</div>';
    } else {
        echo '<details class="card-body p-2"' . ($sourceid === 'wikidata' ? ' open' : '') . '>'
            . '<summary class="small text-primary">'
            . s(get_string('opendatacategories', 'treasurehunt')) . '</summary>'
            . '<div class="treasurehunt-opendata-themes mt-2">';
        foreach ($source->themes() as $themeid => $stringkey) {
            echo '<label class="d-block small mb-1"><input type="checkbox" name="opendatatype"'
                . ' data-source="' . s($sourceid) . '" value="' . s($themeid) . '"'
                . ' class="' . $marginendclass . '"' . ($themeid === 'all' ? ' checked' : '') . '> '
                . s(get_string($stringkey, 'treasurehunt')) . '</label>';
        }
        echo '</div></details>';
    }
    echo '</section>';
}
echo '</div><label class="d-block mt-3" for="opendataterm">'
    . s(get_string('opendataterm', 'treasurehunt')) . '</label>'
    . '<input type="search" id="opendataterm" class="form-control" maxlength="100"'
    . ' placeholder="' . s(get_string('opendatatermplaceholder', 'treasurehunt')) . '">'
    . '</div><div class="col-md-7 p-4">'
    . '<h6 class="' . $boldclass . '"><i class="fa fa-map-o text-primary ' . $marginendclass . '"'
    . ' aria-hidden="true"></i>' . s(get_string('opendataoperation', 'treasurehunt')) . '</h6>'
    . '<p>' . s(get_string('opendataoperationdesc', 'treasurehunt')) . '</p>'
    . '<div class="alert alert-info small">' . s(get_string('opendataareadesc', 'treasurehunt')) . '</div>'
    . '<p class="small text-muted">' . s(get_string('opendatasource', 'treasurehunt')) . '</p>'
    . '<div id="opendatastatus" role="status" aria-live="polite"></div>'
    . '<div id="opendatasourceprogress" class="mt-2" role="status" aria-live="polite"></div>'
    . '<ul id="opendatapreview" class="list-group treasurehunt-opendata-preview mt-2"'
    . ' aria-label="' . s(get_string('opendataresults', 'treasurehunt')) . '"></ul>'
    . '</div></div></div><div class="modal-footer bg-light border-top">'
    . '<button type="button" class="btn btn-secondary btn-sm" ' . $bootstrapdataprefix . 'dismiss="modal">'
    . s(get_string('cancel', 'treasurehunt')) . '</button>'
    . '<button type="button" id="opendataloadmore" class="btn btn-outline-secondary btn-sm" hidden>'
    . s(get_string('opendataloadmore', 'treasurehunt')) . '</button>'
    . '<button type="button" id="opendatasearch" class="btn btn-outline-primary btn-sm">'
    . s(get_string('opendatasearch', 'treasurehunt')) . '</button>'
    . '<button type="button" id="opendataadd" class="btn btn-primary btn-sm" disabled>'
    . s(get_string('opendataaddtomap', 'treasurehunt')) . '</button>'
    . '<button type="button" id="opendatareplace" class="btn btn-outline-danger btn-sm" disabled>'
    . s(get_string('opendatareplace', 'treasurehunt')) . '</button>'
    . '</div></div></div></div>';

// Finish the page.
echo $OUTPUT->footer();
