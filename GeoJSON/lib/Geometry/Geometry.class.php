<?php
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
abstract class Geometry 
{
  protected $geom_type;

  abstract public function getCoordinates();
  
  /**
   * Accessor for the geometry type
   *
   * @return string The Geometry type.
   */
  public function getGeomType()
  {
    return $this->geom_type;
  }

  /**
   * Returns an array suitable for serialization
   *
   * @return array
   */
  public function getGeoInterface() 
  {
    return array(
      'type'=> $this->getGeomType(),
      'coordinates'=> $this->getCoordinates()
    );
  }

  /**
   * Shortcut to dump geometry as GeoJSON
   *
   * @return string The GeoJSON representation of the geometry
   */
  public function __toString()
  {
    return $this->toGeoJSON();
  }

  /**
   * Dumps Geometry as GeoJSON
   *
   * @return string The GeoJSON representation of the geometry
   */
  public function toGeoJSON()
  {
    return json_encode($this->getGeoInterface());
  }
}
