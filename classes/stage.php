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

defined('MOODLE_INTERNAL') || die;
/** 
 * Model for a stage 
 * @author juacas
 */
class stage
{
    var $id;
    /** @var int id of the parent road */
    var $roadid;
    var $timecreated;
    var $timemodified;
    /** @var string stage name */
    var $name;
    /** @var string text to discover the next stage */
    var $cluetext;
    var $cluetextformat = FORMAT_HTML;
    var $cluetexttrust = 0;
    var $questiontext = '';
    var $questiontextformat = FORMAT_HTML;
    var $questiontexttrust = 0;
    var $activitytoend = 0;
    var $qrtext = null;
    /** @var string WKT representation of the geometry (@see treasurehunt_geometry_to_wkt) */
    var $geom = null;
    var $playstagewithoutmoving = false;

    public function __construct ($name, $cluetext) {
        $this->name = $name;
        $this->cluetext = $cluetext;
        $this->timecreated = time();
    }
}
