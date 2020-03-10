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
 * Model for an attempt 
 * @author juacas
 */
class attempt extends stdClass
{
    /** @var int */
    var $id;
    /** @var int id of the parent road */
    var $stageid;
    /** @var int */
    var $timecreated;
    /** @var int */
    var $userid;
    /** @var int */
    var $groupid = 0;
    /** @var boolean */
    var $success = 0;
    /** @var int */
    var $penalty = 0;
    /** @var string */
    var $type;
    /** @var boolean */
    var $questionsolved = 0;
    /** @var boolean */
    var $activitysolved = 0;
    /** @var boolean */
    var $geometrysolved = 0;
    /** @var string WKT representation of the geometry (@see treasurehunt_geometry_to_wkt) */
    var $location = null;

    public function __construct ($stageid, $userid, $type) {
        $this->stageid = $stageid;
        $this->userid = $userid;
        $this->type = $type;
        $this->timecreated = time();
    }
}
