<?php
// This file is part of Moodle - https://moodle.org/
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
/*
 * This file is part of the GeoJSON package.
 * (c) Camptocamp <info@camptocamp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Point : a Point geometry.
 *
 * @package    GeoJSON
 * @subpackage Geometry
 * @author     Camptocamp <info@camptocamp.com>
 */
class Point extends Geometry
{
    private $position = [2];

    protected $geom_type = 'Point';

    /**
     * Constructor
     *
     * @param float $x The x coordinate (or longitude)
     * @param float $y The y coordinate (or latitude)
     */
    public function __construct($x, $y) {
        if (!is_numeric($x) || !is_numeric($y)) {
            throw new Exception("Bad coordinates: x and y should be numeric");
        }
        $this->position = [$x, $y];
    }

    /**
     * An accessor method which returns the coordinates array
     *
     * @return array The coordinates array
     */
    public function getCoordinates() {
        return $this->position;
    }

    /**
     * Returns X coordinate of the point
     *
     * @return integer The X coordinate
     */
    public function getX() {
        return $this->position[0];
    }

    /**
     * Returns X coordinate of the point
     *
     * @return integer The X coordinate
     */
    public function getY() {
        return $this->position[1];
    }
}
