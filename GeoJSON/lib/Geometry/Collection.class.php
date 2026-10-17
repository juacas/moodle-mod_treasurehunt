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
 * Collection : abstract class which represents a collection of components.
 *
 * @package    GeoJSON
 * @subpackage Geometry
 * @author     Camptocamp <info@camptocamp.com>
 */
abstract class Collection extends Geometry implements Iterator
{
    protected $components = [];

    /**
     * Constructor
     *
     * @param array $components The components array
     */
    public function __construct(array $components) {
        foreach ($components as $component) {
            $this->add($component);
        }
    }

    private function add($component) {
        $this->components[] = $component;
    }

    /**
     * An accessor method which recursively calls itself to build the coordinates array
     *
     * @return array The coordinates array
     */
    public function getCoordinates() {
        $coordinates = [];
        foreach ($this->components as $component) {
            $coordinates[] = $component->getCoordinates();
        }
        return $coordinates;
    }

    /**
     * Returns Colection components
     *
     * @return array
     */
    public function getComponents() {
        return $this->components;
    }

    // Iterator Interface functions

    public function rewind(): void {
        reset($this->components);
    }

    public function current(): mixed {
        return current($this->components);
    }

    public function key(): int|string|null {
        return key($this->components);
    }

    public function next(): void {
        // return next($this->components);
        next($this->components);
    }

    public function valid(): bool {
        return $this->current() !== false;
    }
}
