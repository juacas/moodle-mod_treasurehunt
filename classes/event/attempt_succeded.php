<?php
// This file is part of Treasurehunt for Moodle
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
 * The mod_treasurehunt attempt submitted event class.
 *
 * @package    mod_treasurehunt
 * @copyright  2018 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_treasurehunt\event;

/**
 * The mod_treasurehunt attempt submitted event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int groupid: id of group.
 * }
 *
 */
class attempt_succeded extends \core\event\base {
    /**
     * Init method
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'treasurehunt_attempts';
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventattemptsucceded', 'mod_treasurehunt');
    }

    /**
     * Returns non-localised event description with id's for admin use only.
     *
     * @return string
     */
    public function get_description() {
        $descriptionstring = "The user with id '$this->userid' has submitted the attempt with id '$this->objectid' for " .
                "the treasure hunt activity with course module id '$this->contextinstanceid'";
        if (!empty($this->other['groupid'])) {
            $descriptionstring .= " and the group with id '{$this->other['groupid']}'.";
        } else {
            $descriptionstring .= ".";
        }
        return $descriptionstring;
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        $url = new \moodle_url("/mod/treasurehunt/view.php", ['id' => $this->contextinstanceid]);
        if (!empty($this->other['groupid'])) {
            $url->param('groupid', $this->other['groupid']);
        } else {
            $url->param('userid', $this->userid);
        }
        return $url;
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['groupid'])) {
            throw new \coding_exception('The \'groupid\' value must be set in other.');
        }
    }
    /**
     * DB mapping.
     * @return array{db: string, restore: string}
     */
    public static function get_objectid_mapping() {
        return ['db' => 'treasurehunt_attempts', 'restore' => 'treasurehunt_attempt'];
    }
    /**
     * DB mapping.
     * @return array{db: string, restore: string[]}
     */
    public static function get_other_mapping() {
        $othermapped = [];
        $othermapped['groupid'] = ['db' => 'groups', 'restore' => 'group'];

        return $othermapped;
    }
}
