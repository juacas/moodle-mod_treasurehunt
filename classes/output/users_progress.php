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
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class users_progress implements renderable {
    /** @var array participantcount - The number of users who can submit to this assignment */
    public $roadsusersprogress = [];
    /** @var bool is groupmode? */
    public $groupmode = 0;
    /** @var int cm id */
    public $coursemoduleid = 0;
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

    /**
     * constructor
     *
     */
    public function __construct(
        $roadsusersprogress,
        $groupmode,
        $coursemoduleid,
        $duplicategroupsingroupings,
        $duplicateusersingroups,
        $unassignedusers,
        $viewpermission,
        $managepermission
    ) {
        $this->roadsusersprogress = $roadsusersprogress;
        $this->groupmode = $groupmode;
        $this->coursemoduleid = $coursemoduleid;
        $this->duplicategroupsingroupings = $duplicategroupsingroupings;
        $this->duplicateusersingroups = $duplicateusersingroups;
        $this->unassignedusers = $unassignedusers;
        $this->viewpermission = $viewpermission;
        $this->managepermission = $managepermission;
    }
}
