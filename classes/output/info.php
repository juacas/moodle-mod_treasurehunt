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
 * Renderable info
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class info implements renderable
{
    /**
     * Treasurehunt record.
     * @var object
     */
    public $treasurehunt = null;
    /**
     * Time to report.
     * @var int unix timestamp.
     */
    public $timenow = 0;
    /**
     * Id course.
     * @var int
     */
    public $courseid = 0;
    /**
     * Array of road records.
     * @var array[object]
     */
    public $roads = [];
    /**
     * number of QRs in the road.
     * @var int
     */
    public $numqrs = 0;

    /**
     * constructor
     */
    public function __construct($treasurehunt, $timenow, $courseid, $roads, $numqrs) {
        $this->treasurehunt = $treasurehunt;
        $this->timenow = $timenow;
        $this->courseid = $courseid;
        $this->roads = $roads;
        $this->numqrs = $numqrs;
    }
}
