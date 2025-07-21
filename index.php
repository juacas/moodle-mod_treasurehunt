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
 * Displays information about all the assignment modules in the requested course
 *
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Replace treasurehunt with the name of your module and remove this line.

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');

$id = required_param('id', PARAM_INT); // Course.
// From moodle 5.0 onwards, the index.php is redirected to the overview page.
if ($CFG->version >= 2025041400) {
    \core_courseformat\activityoverviewbase::redirect_to_overview_page($id, 'treasurehunt');
}

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_course_login($course);

$params = [
    'context' => context_course::instance($course->id),
];
$event = \mod_treasurehunt\event\course_module_instance_list_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->trigger();

$strname = get_string('modulenameplural', 'treasurehunt');
$PAGE->set_url('/mod/treasurehunt/index.php', ['id' => $id]);
$PAGE->navbar->add($strname);
$PAGE->set_title("$course->shortname: $strname");
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading($strname);

if (!$treasurehunts = get_all_instances_in_course('treasurehunt', $course)) {
    notice(get_string('notreasurehunts', 'treasurehunt'), new moodle_url('/course/view.php', ['id' => $course->id]));
}

$usesections = course_format_uses_sections($course->format);

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($usesections) {
    $strsectionname = get_string('sectionname', 'format_' . $course->format);
    $table->head = [$strsectionname, $strname];
    $table->align = ['center', 'left'];
} else {
    $table->head = [$strname];
    $table->align = ['left'];
}

$modinfo = get_fast_modinfo($course);
$currentsection = '';
foreach ($modinfo->instances['treasurehunt'] as $cm) {
    $row = [];
    if ($usesections) {
        if ($cm->sectionnum !== $currentsection) {
            if ($cm->sectionnum) {
                $row[] = get_section_name($course, $cm->sectionnum);
            }
            $currentsection = $cm->sectionnum;
        } else {
            $row[] = '';
        }
    }

    $class = $cm->visible ? null : ['class' => 'dimmed'];

    $row[] = html_writer::link(new moodle_url('view.php', ['id' => $cm->id]), $cm->get_formatted_name(), $class);
    $table->data[] = $row;
}

echo html_writer::table($table);

echo $OUTPUT->footer();
