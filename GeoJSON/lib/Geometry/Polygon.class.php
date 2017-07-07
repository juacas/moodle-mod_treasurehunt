<?php


/*
 * This file is part of the GeoJSON package.
 * (c) Camptocamp <info@camptocamp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Polygon : a Polygon geometry.
 *
 * @package GeoJSON
 * @subpackage Geometry
 * @author Camptocamp <info@camptocamp.com>
 */
class Polygon extends Collection {

    protected $geom_type = 'Polygon';

    /**
     * Constructor
     *
     * The first linestring is the outer ring
     * The subsequent ones are holes
     * All linestrings should be linearrings
     *
     * @param array $linestrings The LineString array
     */
    public function __construct(array $linestrings) {
        // the GeoJSON spec (http://geojson.org/geojson-spec.html) says nothing about linestring
        // count.
        // What should we do ?
        if (count($linestrings) > 0) {
            parent::__construct($linestrings);
        } else {
            throw new Exception("Polygon without an exterior ring");
        }
    }

    /**
     * For a given point, determine whether it's bounded by the given polygon.
     * Adapted from http://www.assemblysys.com/dataServices/php_pointinpolygon.php
     *
     * @see http://en.wikipedia.org/wiki/Point%5Fin%5Fpolygon
     *
     * @param Point $point
     * @param boolean $pointOnBoundary - whether a boundary should be considered "in" or not
     * @param boolean $pointOnVertex - whether a vertex should be considered "in" or not
     * @return boolean
     */
    public function pointInPolygon(Point $point, $pointOnBoundary = true, $pointOnVertex = true) {
        $vertices = $this->getComponents()[0]->getComponents();
        
        // Check if the point sits exactly on a vertex
        if ($this->pointOnVertex($point, $vertices)) {
            return $pointOnVertex ? true : false;
        }
        
        // Check if the point is inside the polygon or on the boundary
        $intersections = 0;
        $vertices_count = count($vertices);
        
        for ($i = 1; $i < $vertices_count; $i++) {
            $vertex1 = $vertices[$i - 1];
            $vertex2 = $vertices[$i];
            if ($vertex1->getY() == $vertex2->getY() && $vertex1->getY() == $point->getY() &&
                     $point->getX() > min($vertex1->getX(), $vertex2->getX()) &&
                     $point->getX() < max($vertex1->getX(), $vertex2->getX())) {
                // Check if point is on an horizontal polygon boundary
                return $pointOnBoundary ? true : false;
            }
            if ($point->getY() > min($vertex1->getY(), $vertex2->getY()) && $point->getY() <= max($vertex1->getY(), $vertex2->getY()) &&
                 $point->getX() <= max($vertex1->getX(), $vertex2->getX()) && $vertex1->getY() != $vertex2->getY()) {
                        $xinters = ($point->getY() - $vertex1->getY()) * ($vertex2->getX() - $vertex1->getX()) /
                         ($vertex2->getY() - $vertex1->getY()) + $vertex1->getX();
                if ($xinters == $point->getX()) {
                    // Check if point is on the polygon boundary (other than horizontal)
                    return $pointOnBoundary ? true : false;
                }
                if ($vertex1->getX() == $vertex2->getX() || $point->getX() <= $xinters) {
                    $intersections++;
                }
            }
        }
        // If the number of edges we passed through is even, then it's in the polygon.
        if ($intersections % 2 != 0) {
            return true;
        } else {
            return false;
        }
    }

    public function pointOnVertex($point, $vertices) {
        foreach ($vertices as $vertex) {
            if ($point->getX() == $vertex->getX() && $point->getY() == $vertex->getY()) {
                return true;
            }
        }
    }
}