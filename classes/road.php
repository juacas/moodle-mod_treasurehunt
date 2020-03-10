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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>. 
namespace mod_treasurehunt\model;

use stdClass;

defined('MOODLE_INTERNAL') || die;
/** 
 * Model for a road 
 * @author juacas
 */
class road extends stdClass {
    var $id;
    /** @var int id of the parent road */
    var $treasurehuntid;
    var $name;
    var $timecreated;
    var $timemodified;
    var $groupid = 0;
    var $groupingid = 0;
    var $validated = false;
    public function __construct(string $name)
    {
        $this->name = $name;
    }
}