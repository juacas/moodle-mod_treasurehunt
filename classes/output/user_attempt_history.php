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

namespace mod_treasurehunt\output;
use renderable;
/**
 * Renderable user_attempt_history
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_attempt_history implements renderable
{
    /**
     * List of attempts.
     * @var array[object]
     */
    public $attempts = [];
    /**
     * cm->id of the activity.
     * @var int
     */
    public $coursemoduleid = 0;
    /**
     * Username
     * @var string
     */
    public $username = '';
    /**
     * Whether the treasurehunt is over.
     * @var bool
     */
    public $outoftime = 0;
    /**
     * Whether the road is over.
     * @var bool
     */
    public $roadfinished = 0;
    /**
     * Teacher review.
     * @var int
     */
    public $teacherreview = 0;

    /**
     * constructor
     *
     */
    public function __construct($attempts, $coursemoduleid, $username, $outoftime, $roadfinished, $teacherreview) {
        $this->attempts = $attempts;
        $this->coursemoduleid = $coursemoduleid;
        $this->username = $username;
        $this->outoftime = $outoftime;
        $this->roadfinished = $roadfinished;
        $this->teacherreview = $teacherreview;
    }
}
