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
 * The mod_glossary entry created event.
 *
 * @package    mod_scavengerthunt
 * @copyright  2015 Adrian Rodriguez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_scavengerhunt\event;
defined('MOODLE_INTERNAL') || die();

/**
 * The mod_scavengerhunt entry created event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - string concept: (optional) the concept of created entry.
 * }
 *
 * @package    mod_glossary
 * @since      Moodle 2.7
 * @copyright  2014 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class road_created extends \core\event\base {
    /**
     * Init method
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'scavengerhunt_roads';
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventroadcreated', 'mod_scavengerhunt');
    }

    /**
     * Returns non-localised event description with id's for admin use only.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has created the scavengerhunt road with id '$this->objectid' for " .
            "the scavengerhunt activity with course module id '$this->contextinstanceid'.";
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url("/mod/scavengerhunt/edit.php",
                array('id' => $this->contextinstanceid));
    }

    /**
     * Return the legacy event log data.
     *
     * @return array|null
     */
    public function get_legacy_logdata() {
        return array($this->courseid, 'scavengerhunt', 'add road',
            "view.php?id={$this->contextinstanceid}",
            $this->objectid, $this->contextinstanceid);
    }


}

