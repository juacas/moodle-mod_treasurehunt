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
 * Adapter to implement for Dependecy Injection in GeoJSON loader
 *
 * @package    GeoJSON
 * @author     Camptocamp <info@camptocamp.com>
 */
interface GeoJSON_Adapter
{
    /**
     * Returns if a Feature or a FeatureCollection should be created
     *
     * @return boolean
     */
    public function isMultiple($object);

    /**
     * Returns an iterable for muiltiple object
     */
    public function getIterable($object);

    /**
     * Returns WKT string for passed object
     *
     * @return string
     */
    public function getObjectGeometry($object);

    /**
     * Returns passed object identifier
     *
     * @return mixed
     */
    public function getObjectId($object);

    /**
     * Returns passed object attributes
     *
     * @return array
     */
    public function getObjectProperties($object);
}
