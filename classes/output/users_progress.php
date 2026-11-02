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
 * Renderable users_progress
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class users_progress implements renderable {
    /** @var array participantcount - The number of users who can submit to this assignment */
    public $roadsusersprogress = [];
    /** @var bool is groupmode? */
    public $groupmode = 0;
    /** @var int cm id */
    public $coursemoduleid = 0;
    /** @var int Treasure hunt instance id. */
    public $treasurehuntid = 0;
    /** @var bool is there duplicated groups? */
    public $duplicategroupsingroupings = [];
    /** @var array[object] list of users in more than one group */
    public $duplicateusersingroups = [];
    /** @var array[object] list of users in no group */
    public $unassignedusers = [];
    /** @var bool can view? */
    public $viewpermission = false;
    /** @var bool can manage activity? */
    public $managepermission = false;
    /** @var array roads with resettable activity, keyed by road ID */
    public $showclearactions = [];
    /** @var bool Whether the live participant progress strips are enabled. */
    public $showparticipantprogress = false;

    /**
     * constructor
     *
     * @param array $roadsusersprogress Per-road participant progress used by the summary table.
     * @param bool $groupmode Whether progress is shown for teams instead of individuals.
     * @param int $coursemoduleid Course module ID used to build links to this activity.
     * @param array $duplicategroupsingroupings Groups assigned to more than one road grouping.
     * @param array $duplicateusersingroups Users belonging to more than one eligible group.
     * @param array $unassignedusers Eligible users who do not belong to any road group.
     * @param bool $viewpermission Whether the viewer may inspect participant progress.
     * @param bool $managepermission Whether the viewer may manage the hunt and clear attempts.
     * @param array $showclearactions Road IDs with activity that the viewer may clear.
     * @param bool $showparticipantprogress Whether to show the live participant progress strip.
     * @param int $treasurehuntid Hunt instance ID used for the progress strip.
     */
    public function __construct(
        $roadsusersprogress,
        $groupmode,
        $coursemoduleid,
        $duplicategroupsingroupings,
        $duplicateusersingroups,
        $unassignedusers,
        $viewpermission,
        $managepermission,
        $showclearactions = [],
        $showparticipantprogress = false,
        $treasurehuntid = 0
    ) {
        $this->roadsusersprogress = $roadsusersprogress;
        $this->groupmode = $groupmode;
        $this->coursemoduleid = $coursemoduleid;
        $this->treasurehuntid = $treasurehuntid;
        $this->duplicategroupsingroupings = $duplicategroupsingroupings;
        $this->duplicateusersingroups = $duplicateusersingroups;
        $this->unassignedusers = $unassignedusers;
        $this->viewpermission = $viewpermission;
        $this->managepermission = $managepermission;
        $this->showclearactions = $showclearactions;
        $this->showparticipantprogress = $showparticipantprogress;
    }
}
