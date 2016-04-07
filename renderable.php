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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains the definition for the renderable classes for the assignment
 *
 * @package   mod_assign
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Renderable grading summary
 * @package   mod_assign
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scavengerhunt_grading_summary implements renderable {

    /** @var int participantcount - The number of users who can submit to this assignment */
    public $riddleid = 0;

    /** @var int participantcount - The number of users who can submit to this assignment */
    public $riddlenum = 0;

    /** @var int participantcount - The number of users who can submit to this assignment */
    public $groupmode = 0;

    /**
     * constructor
     *
     * @param int $participantcount
     */
    public function __construct($riddleid, $riddlenum,$groupmode) {
        $this->riddleid = $riddleid;
        $this->riddlenum = $riddlenum;
    }

}
