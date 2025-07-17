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
 * @copyright  2025 YOUR NAME <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class play_page_base implements renderable, templatable {
    public $treasurehunt = null;
    public $cm = null;
    public $custommapping = '';
    public $customplayerconfig = null;
    public $user = null;
    public $lastattempttimestamp;
    public $lastroadtimestamp;
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
    public function __construct($treasurehunt, cm_info $cm) {
        $this->treasurehunt = $treasurehunt;
        $this->cm = $cm;
        $this->customplayerconfig = treasurehunt_get_customplayerconfig($this->treasurehunt);
    }
    public function set_user($user) {
        $this->user = $user;
    }
    public function set_custommapping($custommapping) {
        $this->custommapping = $custommapping;
    }
}
