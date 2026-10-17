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
 * GeometryCollection : a GeometryCollection geometry.
 *
 * @package    GeoJSON
 * @subpackage Geometry
 * @author     Camptocamp <info@camptocamp.com>
 */
class GeometryCollection extends Collection
{
    protected $geom_type = 'GeometryCollection';

    /**
     * Constructor
     *
     * @param array $geometries The Geometries array
     */
    public function __construct(?array $geometries = null) {
        parent::__construct($geometries);
    }

    /**
     * Returns an array suitable for serialization
     *
     * Overrides the one defined in parent class
     *
     * @return array
     */
    public function getGeoInterface() {
        $geometries = [];
        foreach ($this->components as $geometry) {
            $geometries[] = $geometry->getGeoInterface();
        }
        return [
        'type' => $this->getGeomType(),
        'geometries' => $geometries,
        ];
    }
}
