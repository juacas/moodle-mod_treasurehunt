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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Report whether the track viewer should continue polling.
 *
 * @package mod_treasurehunt
 * @author Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @copyright 2026 Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');

$id = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($id, 'treasurehunt');
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/treasurehunt:viewusershistoricalattempts', $context);
$treasurehunt = $DB->get_record('treasurehunt', ['id' => $cm->instance], '*', MUST_EXIST);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['refresh' => treasurehunt_tracks_need_refresh($treasurehunt, $cm, $context)]);
