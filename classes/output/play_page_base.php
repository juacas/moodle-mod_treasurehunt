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

namespace mod_treasurehunt\output;
use renderable;
use templatable;
use renderer_base;
use cm_info;
use stdClass;
/**
 * Class play_page_base
 *
 * @package    mod_treasurehunt
 * @copyright  2025 Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class play_page_base implements renderable, templatable {
    /**
     * Treasurehunt data record.
     * @var object
     */
    public $treasurehunt = null;
    /**
     * Course mod.
     * @var \cm_info
     */
    public $cm = null;
    /**
     * Parameters for custom maps.
     * @var <object data="" type=""></object>
     */
    public $custommapping = '';
    /**
     * Parameters for custom player UIs.
     * @var object
     */
    public $customplayerconfig = null;
    /**
     * user object
     * @var object
     */
    public $user = null;
    /**
     * Last attempt.
     * @var int unix timestamp.
     */
    public $lastattempttimestamp;
    /**
     * Last road attempt.
     * @var int unix timestamp.
     */
    public $lastroadtimestamp;
    /**
     * Las changes in game's state.
     * @var int unix timestamp.
     */
    public $gameupdatetime;
    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $USER;
        $data = new stdClass();
        $user = new stdClass();
        $user->name = fullname($USER);
        $user->picture = $output->user_picture($USER, ['link' => false]);
        $data->user = $user;
        $data->cmid = $this->cm->id;
        $data->treasurehunt = $this->treasurehunt;
        $data->customplayerconfig = $this->customplayerconfig;
        if (empty($this->treasurehunt->description)) {
            $hasdescription = false;
        } else {
            $hasdescription = true;
        }
        $data->hasdescription = $hasdescription;
        return $data;
    }
    /**
     * Construct this renderable from treasurehunt record and course module info.
     * @param object $treasurehunt
     * @param \cm_info $cm
     */
    public function __construct($treasurehunt, cm_info $cm) {
        $this->treasurehunt = $treasurehunt;
        $this->cm = $cm;
        $this->customplayerconfig = treasurehunt_get_customplayerconfig($this->treasurehunt);
    }
    /**
     * Setter for user.
     * @param object $user
     * @return void
     */
    public function set_user($user) {
        $this->user = $user;
    }
    /**
     * Setter for custom mapping.
     * @param object $custommapping
     * @return void
     */
    public function set_custommapping($custommapping) {
        $this->custommapping = $custommapping;
    }
}
