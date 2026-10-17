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
 * GeoJSON class : a geojson reader/writer.
 *
 * This singleton is used to convert GeoJSON strings into PHP objects, and vice-versa
 *
 * @package    GeoJSON
 * @author     Camptocamp <info@camptocamp.com>
 */
class GeoJSON {
    /**
     * Load Classes needed for GeoJSON class
     *
     *
     * Usage:
     *   <?php spl_autoload_register(array('GeoJSON', 'autoload')); ?>
     *
     * @param string $className A class name to load
     */
    public static function autoload($className) {

        $i = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__FILE__)));
        foreach ($i as $file) {
            if ($className === basename($file->getFileName(), '.class.php')) {
                require_once($file->getPathName());
            }
        }
    }

    /**
     * Serializes an object into a geojson string
     *
     *
     * @param Feature|FeatureCollection $obj The object to serialize
     *
     * @return string The GeoJSON string
     */
    public static function dump($obj) {
        if (is_null($obj)) {
            return null;
        }

        if (!in_array(get_class($obj), ['Feature', 'FeatureCollection'])) {
            throw new Exception('Input should be a Feature or a FeatureCollection');
        }

        return json_encode($obj->getGeoInterface());
    }

    /**
     * Deserializes a geojson string into an object
     *
     *
     * @param string $string The GeoJSON string
     *
     * @return object The PHP equivalent object
     */
    public static function load($string) {
        if (!($object = json_decode($string))) {
            throw new Exception('Invalid JSON');
        }
        return self::toInstance($object);
    }

    /**
     * Deserializes a geojson array into an object
     *
     *
     * @param string $array The GeoJSON array
     *
     * @return object The PHP equivalent object
     */
    public static function loadArray($array) {
        return self::toInstance($array);
    }

    /**
     * returns a Feature|FeatureCollection instance build from $object through $adapter
     *
     * @param mixed $object The data to load
     * @param GeoJSON_Adapter The adapter through which data will be extracted
     *
     * @return Feature|FeatureCollection A Feature|FeatureCollection instance
     */
    public static function loadFrom($object, GeoJSON_Adapter $adapter) {
        if ($adapter->isMultiple($object)) {
            $result = new FeatureCollection();
            foreach ($adapter->getIterable($object) as $feature) {
                $result->addFeature(self::loadFeatureFrom($feature, $adapter));
            }
        } else {
            $result = self::loadFeatureFrom($object, $adapter);
        }

        return $result;
    }

    /**
     * returns a GeoJSON instance build from $object through $adapter
     *
     * @param mixed $object The data to load
     * @param GeoJSON_Adapter The adapter through which data will be extracted
     *
     * @return GeoJSON A GeoJSON instance
     */
    protected static function loadFeatureFrom($object, GeoJSON_Adapter $adapter) {
        $geometry = WKT::load($adapter->getObjectGeometry($object));
        $feature = new Feature(
            $adapter->getObjectId($object),
            $geometry,
            $adapter->getObjectProperties($object),
            $adapter->getObjectBBox($object)
        );

        return $feature;
    }

    /**
     * Converts an stdClass object into a Feature or a FeatureCollection or a Geometry based on its 'type' property
     * Converts an stdClass object into a Feature or a FeatureCollection or a Geometry, based on its 'type' property
     *
     * @param stdClass $obj Object resulting from json decoding a GeoJSON string
     *
     * @return object Object from class: Feature, FeatureCollection or Geometry
     */
    private static function toInstance($obj) {
        if (is_null($obj)) {
            return null;
        }
        if (is_array($obj)) {
            $obj = (object) $obj;
        }
        if (!isset($obj->type)) {
            self::checkType($obj);
        }

        switch ($obj->type) {
            case 'FeatureCollection':
                $features = [];
                self::checkExists($obj, 'features', false, 'array');
                foreach ($obj->features as $feature) {
                    $features[] = self::toFeatureInstance($feature, true);
                }
                $instance = new FeatureCollection($features);
                break;
            case 'Feature':
                $instance = self::toFeatureInstance($obj, false);
                break;

            default:
                $instance = self::toGeomInstance($obj);
        }
        return $instance;
    }

    /**
     * Converts an stdClass object into a Feature
     *
     * @param stdClass $obj Object to convert
     * @param boolean $testType Should we test that $obj is really a Feature ?
     * @return object Object from class Feature
     */
    private static function toFeatureInstance($obj, $testType) {
        if (is_array($obj)) {
            $obj = (object) $obj;
            if (is_array($obj->geometry)) {
                $obj->geometry = (object) $obj->geometry;
            }
            if (is_array($obj->properties)) {
                $obj->properties = (object) $obj->properties;
            }
        }
        if ($testType) {
            self::checkType($obj, 'Feature');
        }
        self::checkExists($obj, 'geometry', true, 'object');
        $geometry = self::toGeomInstance($obj->geometry);
        self::checkExists($obj, 'properties', true, 'object');
        $properties = get_object_vars($obj->properties);
        return new Feature($obj->id, $geometry, $properties);
    }

    /**
     * Converts an stdClass object into a Geometry based on its 'type' property
     *
     * @param stdClass $obj Object resulting from json decoding a GeoJSON string
     * @param boolean $allowGeometryCollection Do we allow $obj to be a GeometryCollection ?
     *
     * @return object Object from class Geometry
     */
    private static function toGeomInstance($obj, $allowGeometryCollection = true) {
        if (is_null($obj)) {
            return null;
        }
        if (is_array($obj)) {
            $obj = (object) $obj;
        }
        self::checkType($obj);

        switch ($obj->type) {
            case 'FeatureCollection':
                $features = [];
                self::checkArray($obj, 'features');
                foreach ($obj->features as $feature) {
                    $features[] = self::toInstance($feature);
                }
                $instance = new FeatureCollection($features);
                break;

            case 'Feature':
                self::checkExists($obj, 'geometry', true);
                $geometry = self::toInstance($obj->geometry);
                self::checkExists($obj, 'properties', true);
                // TODO : more tests (either array or object ???) see geojson spec
                $properties = (is_object($obj->properties)) ? get_object_vars($obj->properties) : $obj->properties;
                self::checkExists($obj, 'id');
                $instance = new Feature($obj->id, $geometry, $properties);
                break;

            case 'Point':
            case 'LineString':
            case 'Polygon':
                self::checkExists($obj, 'coordinates', false, 'array');
                $instance = call_user_func([self::class, 'to' . $obj->type], $obj->coordinates);
                break;

            case 'MultiPoint':
            case 'MultiLineString':
            case 'MultiPolygon':
                self::checkExists($obj, 'coordinates', false, 'array');
                $items = [];
                foreach ($obj->coordinates as $item) {
                    $items[] = call_user_func([self::class, 'to' . substr($obj->type, 5)], $item);
                }
                $instance = new $obj->type($items);
                break;

            case 'GeometryCollection':
                if ($allowGeometryCollection) {
                    self::checkExists($obj, 'geometries', false, 'array');
                    $geometries = [];
                    foreach ($obj->geometries as $geometry) {
                        $geometries[] = self::toGeomInstance($geometry, false);
                    }
                    $instance = new GeometryCollection($geometries);
                } else {
                    throw new Exception("Bad geojson: a GeometryCollection should not contain another GeometryCollection");
                }
                break;

            default:
                throw new Exception("Unsupported object type");
        }
        return $instance;
    }

    /**
     * Checks an object for type
     *
     * @param object $obj A geometry object
     * @param string $typeValue Value expected for 'type' property
     */
    private static function checkType(object $obj, ?string $typeValue = null) {
        if (!is_object($obj) || get_class($obj) != 'stdClass') {
            throw new Exception("Bad geojson");
        }

        if (!isset($obj->type)) {
            throw new Exception("Bad geojson: Missing 'type' property");
        }

        if (!is_null($typeValue) && $obj->type != $typeValue) {
            throw new Exception("Bad geojson: Unexpected 'type' value");
        }
    }

    /**
     * Checks if a property exists inside an object
     *
     * @param object $obj An object
     * @param string $property The property to check
     * @param boolean $allowNull Whether to allow a null value or not (defaults to false)
     * @param string $type Check also $property type (object, array ...)
     */
    private static function checkExists(object $obj, string $property, bool $allowNull = false, ?string $type = null) {
        if (!property_exists($obj, $property)) {
            throw new Exception("Bad geojson: Missing '$property' property");
        }

        if (is_null($obj->$property)) {
            if (!$allowNull) {
                throw new Exception("Bad geojson: Null value for '$property' property");
            }
        } else {
            switch ($type) {
                case null:
                    break;
                case 'array':
                    if (!is_array($obj->$property)) {
                        throw new Exception("Bad geojson: Unexpected type for '$property' property");
                    }
                    break;
                case 'object':
                    if (!is_object($obj->$property)) {
                        throw new Exception("Bad geojson: Unexpected type for '$property' property");
                    }
                    break;
                default:
                    throw new Exception("Unexpected error");
            }
        }
    }

    /**
     * Converts an array of coordinates into a Point Feature
     *
     * @param array $coordinates The X/Y coordinates
     *
     * @return Point A Point object
     */
    private static function toPoint(array $coordinates) {
        if (count($coordinates) == 2 && isset($coordinates[0]) && isset($coordinates[1])) {
            return new Point($coordinates[0], $coordinates[1]);
        }
        throw new Exception("Bad geojson: wrong point coordinates array");
    }

    /**
     * Converts an array of coordinate arrays into a LineString Feature
     *
     * @param array $coordinates The array of coordinates arrays (aka positions)
     * @return LineString A LineString object
     */
    private static function toLineString(array $coordinates) {
        $positions = [];
        foreach ($coordinates as $position) {
            $positions[] = self::toPoint($position);
        }
        return new LineString($positions);
    }

    /**
     * Converts an array of linestring coordinates into a Polygon Feature
     *
     * @param array $coordinates The linestring coordinates
     * @return Polygon A Polygon object
     */
    private static function toPolygon(array $coordinates) {
        $linestrings = [];
        foreach ($coordinates as $linestring) {
            $linestrings[] = self::toLineString($linestring);
        }
        return new Polygon($linestrings);
    }
}
