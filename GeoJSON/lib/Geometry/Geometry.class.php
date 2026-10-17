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
 * Geometry : abstract class which represents a geometry.
 *
 * @package    GeoJSON
 * @subpackage Geometry
 * @author     Camptocamp <info@camptocamp.com>
 */
abstract class Geometry{
    protected $geom_type;

    abstract public function getCoordinates();

    /**
     * Accessor for the geometry type
     *
     * @return string The Geometry type.
     */
    public function getGeomType() {
        return $this->geom_type;
    }

    /**
     * Returns an array suitable for serialization
     *
     * @return array
     */
    public function getGeoInterface() {
        return [
        'type' => $this->getGeomType(),
        'coordinates' => $this->getCoordinates(),
        ];
    }

    /**
     * Shortcut to dump geometry as GeoJSON
     *
     * @return string The GeoJSON representation of the geometry
     */
    public function __toString() {
        return $this->toGeoJSON();
    }

    /**
     * Dumps Geometry as GeoJSON
     *
     * @return string The GeoJSON representation of the geometry
     */
    public function toGeoJSON() {
        return json_encode($this->getGeoInterface());
    }
}
