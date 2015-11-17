<?php

// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Internal library of functions for module scavengerhunt
 *
 * All the scavengerhunt specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package    mod_scavengerhunt
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once("$CFG->dirroot/mod/scavengerhunt/lib.php");
require_once (dirname(__FILE__) . '/GeoJSON/GeoJSON.class.php');
       

        //Cargo las clases necesarias de un objeto GeoJSON
        spl_autoload_register(array('GeoJSON', 'autoload'));
/*
 * Does something really useful with the passed things
 *
 * @param array $things
 * @return object
 * function scavengerhunt_do_something_useful(array $things) {
 *    return new stdClass();
 * }
 */
function geojson_to_wkt($text) {
    $WKT = new WKT();
    return $WKT->write($text);
}
function wkt_to_geojson ($text) {
    $WKT = new WKT();
    return $WKT->read($text);
}
function geojson_to_object($text){
    $GeoJSON = new GeoJSON();
    return $GeoJSON->load($text);
}
function object_to_geojson($text){
    $GeoJSON = new GeoJSON();
    return $GeoJSON->dump($text);
}