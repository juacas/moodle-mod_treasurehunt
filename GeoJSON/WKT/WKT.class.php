<?php
/*
 * This file is part of the GeoJSON package.
 * (c) Camptocamp <info@camptocamp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * PHP Geometry/WKT encoder/decoder
 *
 * Mainly inspired/adapted from OpenLayers( http://www.openlayers.org ) 
 *   Openlayers/format/WKT.js
 *
 * @package    GeoJSON
 * @subpackage WKT
 * @author     Camptocamp <info@camptocamp.com>
 */
class WKT
{

  private $regExes = array(
    'typeStr'               => '/^\s*(\w+)\s*\(\s*(.*)\s*\)\s*$/',
    'spaces'                => '/\s+/',
    'parenComma'            => '/\)\s*,\s*\(/',
    'doubleParenComma'      => '/\)\s*\)\s*,\s*\(\s*\(/',
    'trimParens'            => '/^\s*\(?(.*?)\)?\s*$/'
  );

  const POINT               = 'point';
  const MULTIPOINT          = 'multipoint';
  const LINESTRING          = 'linestring';
  const MULTILINESTRING     = 'multilinestring';
  const LINEARRING          = 'linearring';
  const POLYGON             = 'polygon';
  const MULTIPOLYGON        = 'multipolygon';
  const GEOMETRYCOLLECTION  = 'geometrycollection';

  /**
   * Read WKT string into geometry objects
   *
   * @param string $WKT A WKT string
   *
   * @return Geometry|GeometryCollection|null
   */
  public function read($WKT)
  {
    $matches = array();
    if (!$WKT || !preg_match($this->regExes['typeStr'], $WKT, $matches))
    {
      return null;
    }

    return $this->parse(strtolower($matches[1]), $matches[2]);
  }

  /**
   * Parse WKT string into geometry objects
   *
   * @param string $WKT A WKT string
   *
   * @return Geometry|GeometryCollection
   */
  public function parse($type, $str)
  {
    $matches = array();
    $components = array();

    switch ($type)
    {
      case self::POINT:
        $coords = $this->pregExplode('spaces', $str);
        return new Point((float)$coords[0],(float)$coords[1]);

      case self::MULTIPOINT:
        foreach (explode(',', trim($str)) as $point)
        {
          $components[] = $this->parse(self::POINT, $point);
        }
        return new MultiPoint($components);

      case self::LINESTRING:
        foreach (explode(',', trim($str)) as $point)
        {
          $components[] = $this->parse(self::POINT, $point);
        }
        return new LineString($components);

      case self::MULTILINESTRING:
        $lines = $this->pregExplode('parenComma', $str);
        foreach ($lines as $l)
        {
          $line = preg_replace($this->regExes['trimParens'], '$1', $l);
          $components[] = $this->parse(self::LINESTRING, $line);
        }
        return new MultiLineString($components);

      case self::POLYGON:
        $rings= $this->pregExplode('parenComma', $str);
        foreach ($rings as $r)
        {
          $ring = preg_replace($this->regExes['trimParens'], '$1', $r);
          $linestring = $this->parse(self::LINESTRING, $ring);
          $components[] = new LinearRing($linestring->getComponents());
        }
        return new Polygon($components);

      case self::MULTIPOLYGON:
        $polygons = $this->pregExplode('doubleParenComma', $str);
        foreach ($polygons as $p)
        {
          $polygon = preg_replace($this->regExes['trimParens'], '$1', $p);
          $components[] = $this->parse(self::POLYGON, $polygon);
        }
        return new MultiPolygon($components);

      case self::GEOMETRYCOLLECTION:
        $str = preg_replace('/,\s*([A-Za-z])/', '|$1', $str);
        $wktArray = explode('|', trim($str));
        foreach ($wktArray as $wkt)
        {
          $components[] = $this->read($wkt);
        }
        return new GeometryCollection($components);

      default:
        return null;
    }
  }

  /**
   * Split string according to first match of passed regEx index of $regExes
   *
   */
  protected function pregExplode($regEx, $str)
  {
    $matches = array();
    preg_match($this->regExes[$regEx], $str, $matches);
    return empty($matches)?array(trim($str)):explode($matches[0], trim($str));
  }

  /**
   * Serialize geometries into a WKT string.
   *
   * @param Geometry $geometry
   *
   * @return string The WKT string representation of the input geometries
   */
  public function write(Geometry $geometry)
  {
    $type = strtolower(get_class($geometry));

    if (is_null($data = $this->extract($geometry)))
    {
      return null;
    }

    return strtoupper($type).'('.$data.')';
  }

  /**
   * Extract geometry to a WKT string
   *
   * @param Geometry $geometry A Geometry object
   *
   * @return strin
   */
  public function extract(Geometry $geometry)
  {
    $array = array();
    switch (strtolower(get_class($geometry)))
    {
      case self::POINT:
        return $geometry->getX().' '.$geometry->getY();
      case self::MULTIPOINT:
      case self::LINESTRING:
      case self::LINEARRING:
        foreach ($geometry as $geom)
        {
          $array[] = $this->extract($geom);
        }
        return implode(',', $array);
      case self::MULTILINESTRING:
      case self::POLYGON:
      case self::MULTIPOLYGON:
        foreach ($geometry as $geom)
        {
          $array[] = '('.$this->extract($geom).')';
        }
        return implode(',', $array);
      case self::GEOMETRYCOLLECTION:
        foreach ($geometry as $geom)
        {
          $array[] = strtoupper(get_class($geom)).'('.$this->extract($geom).')';
        }
        return implode(',', $array);
      default:
        return null;
    }
  }

  /**
   * Loads a WKT string into a Geometry Object
   *
   * @param string $WKT
   *
   * @return  Geometry
   */
  static public function load($WKT)
  {
    $instance = new self;
    return $instance->read($WKT);
  }

  /**
   * Dumps a Geometry Object into a  WKT string
   *
   * @param Geometry $geometry
   *
   * @return String A WKT string corresponding to passed object
   */
  static public function dump(Geometry $geometry)
  {
    $instance = new self;
    return $instance->write($geometry);
  }

}