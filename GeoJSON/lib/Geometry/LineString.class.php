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
 * LineString : a LineString geometry.
 *
 * @package GeoJSON
 * @subpackage Geometry
 * @author Camptocamp <info@camptocamp.com>
 */
class LineString extends Collection {
    protected $geom_type = 'LineString';

    /**
     * Constructor
     *
     * @param array $positions The Point array
     */
    public function __construct(array $positions) {
        if (count($positions) > 1) {
            parent::__construct($positions);
        } else {
            throw new Exception("Linestring with less than two points");
        }
    }
}
