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

namespace mod_treasurehunt\courseformat;

use core_courseformat\local\overview\overviewitem;
use core\output\action_link;
use core\output\local\properties\button;
use core\output\local\properties\text_align;
use core\url;
use core\output\pix_icon;
use theme_classic\output\core_renderer;

require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');
/**
 * Class overview
 *
 * @package    mod_treasurehunt
 * @copyright  2025 Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overview extends \core_courseformat\activityoverviewbase {
    #[\Override]
    public function get_extra_overview_items(): array {
        return [
            // TODO: add extra info such as 'status' => $this->get_extra_status_overview(),
        ];
    }

    #[\Override]
    public function get_actions_overview(): ?overviewitem {
        global $CFG, $USER, $OUTPUT;

        if (!has_capability('mod/treasurehunt:view', $this->context)) {
            return null;
        }
        $treasurehunt = $this->cm->get_instance_record();
        // View for teachers.
        if (has_capability('mod/treasurehunt:managetreasurehunt', $this->context)) {
            // Get number of participants.
            list(
                $roads, $duplicategroupsingroupings, $duplicateusersingroups,
                $unassignedusers, $availablegroups
            )  = treasurehunt_get_list_participants_and_attempts_in_roads($this->cm, $this->course->id, $this->context);
            // Process participants by roads.
            $potentialparticipants = [];
            $participants = treasurehunt_get_users_with_attempts($treasurehunt->id);
            $count = count($participants);
            $content = '';
            foreach ($roads as $road) {
                $potentialparticipants = $road->userlist;
                $total = count($potentialparticipants);
                // Count users with ratings.
                $count = 0;
                foreach ($potentialparticipants as $potentialparticipant) {
                    if (count($potentialparticipant->ratings) > 0) {
                        $count++;
                    }
                }
                $button = new action_link(
                    url: new url('/mod/treasurehunt/view.php', ['id' => $this->cm->id]),
                    text: get_string(
                        'count_of_total',
                        'core',
                        ['count' => $count, 'total' => $total]
                    ),
                    attributes: ['class' => button::SECONDARY_OUTLINE->classes()],
                );
                $content .= $road->name . ': ' . $OUTPUT->render($button) . '<br>';
            }
            return new overviewitem(
                name: get_string('usersprogress', 'mod_treasurehunt'),
                value: $total,
                content: $content,
                textalign: text_align::END,
            );
           
        } else {
            // Get user group and road.
            $userparams = treasurehunt_get_user_group_and_road($USER->id, $treasurehunt, $this->cm->id);
            // Get number of stages.
             // Get the total number of stages of the road of the user.
            $total = treasurehunt_get_total_stages($userparams->roadid);
            // Get usr progress in the treasure hunt.
            $currentstage = treasurehunt_get_last_successful_attempt($USER->id, $userparams->groupid, $userparams->roadid, $this->context);                                 
            $stagesuccessed = $currentstage->success?? 0;
            $button = new action_link(
                url: new url('/mod/treasurehunt/view.php', ['id' => $this->cm->id, 'userid' => $USER->id]),
                text: get_string(
                    'count_of_total',
                    'core',
                    ['count' => $stagesuccessed, 'total' => $total]
                ),
                attributes: ['class' => button::SECONDARY_OUTLINE->classes()],
            );
            $content = $OUTPUT->render($button);
    
            return new overviewitem(
                name: get_string('stage', 'mod_treasurehunt'),
                value: $stagesuccessed,
                content: $content,
                textalign: text_align::CENTER,
            );
        }
    }

    #[\Override]
    public function get_due_date_overview(): ?overviewitem {
        $duedate = null;
        if (isset($this->cm->customdata['timeclose'])) {
            $duedate = $this->cm->customdata['timeclose'];
        }

        if (empty($duedate)) {
            return new overviewitem(
                name: get_string('cutoffdate', 'mod_treasurehunt'),
                value: null,
                content: '-',
            );
        }
        return new overviewitem(
            name: get_string('cutoffdate', 'mod_treasurehunt'),
            value: $duedate,
            content: userdate($duedate),
        );
    }

    /**
     * Get the submitted status overview item.
     * TODO: Define extra info for the overview.
     * @return overviewitem|null The overview item (or null if the user cannot complete the treasurehunt).
     */
    private function get_extra_status_overview(): ?overviewitem {
        global $USER;
        
        $treasurehunt = $this->cm->get_instance_record();
        // Get status of the treasure hunt.
        list($status, $nextevent) = treasurehunt_get_time_status($treasurehunt, $now);
        // Get icon.
        $icon = treasurehunt_get_proper_icon($treasurehunt, time());
        // Generate content with icon plus status.
        global $OUTPUT;
        $content = $OUTPUT->pix_icon($icon, $status);
        $content .= \html_writer::tag('span', get_string($status, 'mod_treasurehunt'), ['class' => 'd-inline-block']);
        $value = $status;

        return new overviewitem(
            name: get_string('Status', 'mod_treasurehunt'),
            value: $value,
            content: $content,
            textalign: text_align::CENTER,
        );
    }
}
