<?php
/*
 * This file is part of the GeoJSON package.
 * (c) Camptocamp <info@camptocamp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * MultiLineString : a MultiLineString geometry.
 *
 * @package    GeoJSON
 * @subpackage Geometry
 * @author     Camptocamp <info@camptocamp.com>
 */
class MultiLineString extends Collection 
{
  protected $geom_type = 'MultiLineString';
  
  /**
   * Constructor
   *
   * @param array $linestrings The LineString array
   */
  public function __construct(array $linestrings) 
  {
    parent::__construct($linestrings);
  }
}
