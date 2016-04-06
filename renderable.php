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
    public $participantcount = 0;
    /** @var bool submissiondraftsenabled - Allow submission drafts */
    public $submissiondraftsenabled = false;
    /** @var int submissiondraftscount - The number of submissions in draft status */
    public $submissiondraftscount = 0;
    /** @var bool submissionsenabled - Allow submissions */
    public $submissionsenabled = false;
    /** @var int submissionssubmittedcount - The number of submissions in submitted status */
    public $submissionssubmittedcount = 0;
    /** @var int submissionsneedgradingcount - The number of submissions that need grading */
    public $submissionsneedgradingcount = 0;
    /** @var int duedate - The assignment due date (if one is set) */
    public $duedate = 0;
    /** @var int cutoffdate - The assignment cut off date (if one is set) */
    public $cutoffdate = 0;
    /** @var int coursemoduleid - The assignment course module id */
    public $coursemoduleid = 0;
    /** @var boolean teamsubmission - Are team submissions enabled for this assignment */
    public $teamsubmission = false;
    /** @var boolean warnofungroupedusers - Do we need to warn people that there are users without groups */
    public $warnofungroupedusers = false;

    /**
     * constructor
     *
     * @param int $participantcount
     * @param bool $submissiondraftsenabled
     * @param int $submissiondraftscount
     * @param bool $submissionsenabled
     * @param int $submissionssubmittedcount
     * @param int $cutoffdate
     * @param int $duedate
     * @param int $coursemoduleid
     * @param int $submissionsneedgradingcount
     * @param bool $teamsubmission
     */
    public function __construct($participantcount = 0,
                                $submissiondraftsenabled = 0,
                                $submissiondraftscount = 0,
                                $submissionsenabled = 0,
                                $submissionssubmittedcount = 0,
                                $cutoffdate = 0,
                                $duedate = 0,
                                $coursemoduleid = 0,
                                $submissionsneedgradingcount = 0,
                                $teamsubmission = 0,
                                $warnofungroupedusers = 0) {
        $this->participantcount = $participantcount;
        $this->submissiondraftsenabled = $submissiondraftsenabled;
        $this->submissiondraftscount = $submissiondraftscount;
        $this->submissionsenabled = $submissionsenabled;
        $this->submissionssubmittedcount = $submissionssubmittedcount;
        $this->duedate = $duedate;
        $this->cutoffdate = $cutoffdate;
        $this->coursemoduleid = $coursemoduleid;
        $this->submissionsneedgradingcount = $submissionsneedgradingcount;
        $this->teamsubmission = $teamsubmission;
        $this->warnofungroupedusers = $warnofungroupedusers;
    }
}