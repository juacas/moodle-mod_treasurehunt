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

namespace mod_treasurehunt\model;

/**
 * Model for a stage
 * @author Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @package mod_treasurehunt
 * @copyright 2024 Juan Pablo de Castro
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stage
{
    /** @var int id of the parent road */
    public $id;
    /** @var int The ID of the road this stage belongs to */
    public $roadid;
    /** @var int Position number of the stage. The position/order of the stage in the treasure hunt game. */
    public $position;
    /** @var int Unix timestamp. The timestamp when the stage was created. */
    public $timecreated;
    /** @var int Unix timestamp. The timestamp when the stage was last modified. */
    public $timemodified;
    /** @var string stage name */
    public $name;
    /** @var string text to discover the next stage */
    public $cluetext;
    /** @var int The format for the clue text field, using constant FORMAT_HTML (default value) */
    public $cluetextformat = FORMAT_HTML;
    /** @var int $cluetexttrust Trust level of clue text (see moodle api) */
    public $cluetexttrust = 0;
    /** @var string The text content of the question for this stage. */
    public $questiontext = '';
    /** @var int $questiontextformat HTML format constant for the question text (default: FORMAT_HTML) */
    public $questiontextformat = FORMAT_HTML;
    /** @var int $questiontexttrust Flag indicating the trustworthiness of question text (see Moodle API) */
    public $questiontexttrust = 0;
    /** @var int $activitytoend Indicates if this stage needs an activity to be completed (0=no, 1=yes) */
    public $activitytoend = 0;
    /** @var string|null QR text content for the stage */
    public $qrtext = null;
    /** @var string WKT representation of the geometry (@see treasurehunt_geometry_to_wkt) */
    public $geom = null;
    /** @var bool Whether this stage can be played without moving */
    public $playstagewithoutmoving = false;

    /**
     * Creates a new stage instance.
     *
     * @param string $name The name of the stage
     * @param string $cluetext The clue text for this stage
     */
    public function __construct($name, $cluetext) {
        $this->name = $name;
        $this->cluetext = $cluetext;
        $this->timecreated = time();
    }
}
