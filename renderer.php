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
 * Defines the renderer for the quiz module.
 *
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @author Adrian Rodriguez <huorwhisp@gmail.com>
 * @author Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');

class mod_treasurehunt_renderer extends plugin_renderer_base {

    /**
     * Utility function to add a row of data to a table with 2 columns. Modified
     * the table param and does not return a value
     *
     * @param html_table $table The table to append the row of data to
     * @param array $text Array with the text of each cell
     * @param bool $header If cells are header or not
     * @param array $class Array with the class of each cell
     * @param array $colspan Array with the colspan of each cell
     * @return void
     */
    private function add_table_row(html_table $table, array $text, $header, array $class = null, array $colspan = null) {
        $row = new html_table_row();
        $cells = array();
        for ($i = 0, $f = count($text); $i < $f; $i++) {
            $cell = new html_table_cell($text[$i]);
            if ($header) {
                $cell->header = true;
            }
            if (isset($class)) {
                $cell->attributes['class'] = $class[$i];
            }
            if (isset($colspan)) {
                $cell->colspan = $colspan[$i];
            }
            array_push($cells, $cell);
        }
        $row->cells = $cells;
        $table->data[] = $row;
    }

    /**
     * Defer to template.                                                                                                           
     *                                                                                                                              
     * @param treasurehunt_play_page $page                                                                                                      
     *                                                                                                                              
     * @return string html for the page                                                                                             
     */
    public function render_treasurehunt_play_page(treasurehunt_play_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('mod_treasurehunt/play_page', $data);
    }

    /**
     * Render a table containing the current status of the user attempts.
     *
     * @param treasurehunt_user_historical_stages  $historical
     * @return string
     */
    public function render_treasurehunt_user_historical_attempts(treasurehunt_user_historical_attempts $historical) {
        // Create a table for the data.
        $o = '';
        $o .= $this->output->container_start('historicalattempts');
        $o .= $this->output->heading(get_string('historicalattempts', 'treasurehunt', $historical->username), 3);
        $o .= $this->output->box_start('boxaligncenter gradingsummarytable');
        // Status.
        if (count($historical->attempts)) {
            $numattempt = 1;
            $t = new html_table();
            $this->add_table_row($t, array(get_string('attempt', 'treasurehunt'), get_string('state', 'treasurehunt')), true);
            foreach ($historical->attempts as $attempt) {
                if (!$attempt->penalty) {
                    $class = 'successfulattempt';
                } else {
                    $class = 'failedattempt';
                }
                $this->add_table_row($t, array($numattempt++, $attempt->string), false, array($class, ''));
            }
            // All done - write the table.
            $o .= html_writer::table($t);
        } else {
            if ($historical->teacherreview) {
                $o .= $this->output->notification(get_string('nouserattempts', 'treasurehunt', $historical->username));
            } else {
                $o .= $this->output->notification(get_string('noattempts', 'treasurehunt'));
            }
        }
        // Si no ha finalizado pongo el botón de jugar
        $urlparams = array('id' => $historical->coursemoduleid);
        if ($historical->outoftime || $historical->roadfinished) {
            $string = get_string('reviewofplay', 'treasurehunt');
        } else {
            $string = get_string('play', 'treasurehunt');
        }
        if ((count($historical->attempts) || !$historical->outoftime) && !$historical->teacherreview) {
            $o .= $this->output->single_button(new moodle_url('/mod/treasurehunt/play.php', $urlparams), $string, 'get');
        }
        $o .= $this->output->box_end();

        // Close the container and insert a spacer.
        $o .= $this->output->container_end();

        return $o;
    }

    /**
     * Render a table containing the current status of the users progress.
     *
     * @param treasurehunt_user_progress $progress
     * @return string
     */
    public function render_treasurehunt_users_progress(treasurehunt_users_progress $progress) {
        // Create a table for the data.
        $o = '';
        $s = '';
        if (!count($progress->roadsusersprogress) && $progress->managepermission) {
            $s .= $this->output->notification(get_string('noroads', 'treasurehunt'));
        } else {
            if (count($progress->duplicategroupsingroupings) && $progress->managepermission) {
                $s .= $this->output->notification(get_string('warnusersgrouping', 'treasurehunt', implode(",", $progress->duplicategroupsingroupings)));
            }
            if (count($progress->duplicateusersingroups) && $progress->managepermission) {
                $s .= $this->output->notification(get_string('warnusersgroup', 'treasurehunt', implode(",", $progress->duplicateusersingroups)));
            }
            if (count($progress->unassignedusers) && $progress->managepermission) {
                $s .= $this->output->notification(get_string('warnusersoutside', 'treasurehunt', implode(",", $progress->unassignedusers)));
            }

            foreach ($progress->roadsusersprogress as $roadusersprogress) {
                if ($roadusersprogress->validated) {
                    if (count($roadusersprogress->userlist)) {
                        $s .= $this->output->heading($roadusersprogress->name, 4);
                        $s .= $this->output->box_start('boxaligncenter usersprogresstable');
                        $t = new html_table();
                        if ($progress->groupmode) {
                            $title = get_string('group', 'treasurehunt');
                        } else {
                            $title = get_string('user', 'treasurehunt');
                        }
                        $hasprogress = false;
                        foreach ($roadusersprogress->userlist as $userorgroup) {
                            if (!count($userorgroup->ratings)) {
                                continue;
                            }
                            if (!$hasprogress) {
                                $this->add_table_row($t, array($title, get_string('stages', 'treasurehunt')), true, null, array(null, $roadusersprogress->totalstages + 1));
                                $hasprogress = true;
                            }
                            $row = new html_table_row();
                            if ($progress->groupmode) {
                                $name = $userorgroup->name;
                                if ($progress->viewpermission) {
                                    $params = array('id' => $progress->coursemoduleid, 'groupid' => $userorgroup->id);
                                    $url = new moodle_url('/mod/treasurehunt/view.php', $params);
                                    $icon = $this->output->pix_icon('t/preview', get_string('historicalattempts', 'treasurehunt', $name));
                                    $name = $name . ' ' . html_writer::link($url, $icon);
                                }
                                 $elapsed = treasurehunt_get_hunt_duration($progress->coursemoduleid,null,$userorgroup->id);
                            } else {
                                $fullname = fullname($userorgroup);
                                $userpic = $this->output->user_picture($userorgroup, array('size' => 32));
                                $userurl = new moodle_url('/user/view.php', array('id' => $userorgroup->id, 'courseid' => $this->page->course->id));
                                $name = $userpic . html_writer::link($userurl, $fullname);
                                if ($progress->viewpermission) {
                                    $params = array('id' => $progress->coursemoduleid, 'userid' => $userorgroup->id);
                                    $url = new moodle_url('/mod/treasurehunt/view.php', $params);
                                    $icon = $this->output->pix_icon('t/preview', get_string('historicalattempts', 'treasurehunt', $fullname));
                                    $name .= ' ' . html_writer::link($url, $icon);
                                }
                                $elapsed = treasurehunt_get_hunt_duration($progress->coursemoduleid,$userorgroup->id,null);
                            }
                            $cells = array($name);
                           
                            $cells[] = treasurehunt_get_nice_duration($elapsed);
                            for ($i = 1; $i <= $roadusersprogress->totalstages; $i++) {
                                $cell = new html_table_cell($i);
                                if (isset($userorgroup->ratings[$i])) {
                                    $cell->attributes['class'] = $userorgroup->ratings[$i]->class;
                                } else {
                                    $cell->attributes['class'] = 'noattempt';
                                }
                                array_push($cells, $cell);
                            }
                            $row->cells = $cells;
                            $t->data[] = $row;
                        }
                        if (!$hasprogress) {
                            $s .= $this->output->notification(get_string('nousersprogress', 'treasurehunt'));
                        } else {
                            // All done - write the table.
                            $s .= html_writer::table($t);
                        }
                        $s .= $this->output->box_end();
                    } else {
                        if ($progress->managepermission) {
                            $s .= $this->output->heading($roadusersprogress->name, 4);
                            if ($progress->groupmode) {
                                $notification = get_string('nogroupassigned', 'treasurehunt');
                            } else {
                                $notification = get_string('nouserassigned', 'treasurehunt');
                            }
                            $s .= $this->output->notification($notification);
                        }
                    }
                } else {
                    if ($progress->managepermission) {
                        $s .= $this->output->heading($roadusersprogress->name, 4);
                        $s .= $this->output->notification(get_string('invalroadid', 'treasurehunt'));
                    }
                }
            }
        }
        if ($progress->managepermission) {
            $urlparams = array('id' => $progress->coursemoduleid);
            $s .= $this->output->single_button(new moodle_url('/mod/treasurehunt/edit.php', $urlparams), get_string('edittreasurehunt', 'treasurehunt'), 'get');
            $s .= $this->output->single_button(new moodle_url('/mod/treasurehunt/clearhunt.php', $urlparams), get_string('cleartreasurehunt', 'treasurehunt'), 'get');
            $s .= $this->output->single_button(new moodle_url('/mod/treasurehunt/gpx_viewer.php', $urlparams), get_string('trackviewer', 'treasurehunt'), 'get');
        }
        if ($s !== '') {
            $o .= $this->output->container_start('usersprogress');
            $o .= $this->output->heading_with_help(get_string('usersprogress', 'treasurehunt'), 'usersprogress', 'treasurehunt', null, null, 3);
            $o .= $s;
            // Close the container and insert a spacer.
            $o .= $this->output->container_end();
        }


        return $o;
    }

    /**
     * Render the info containing the current status of the treasure hunt.
     *
     * @param render_treasurehunt_info $info
     * @return string
     */
    public function render_treasurehunt_info(treasurehunt_info $info) {
        // Create a table for the data.
        $o = '';
        $notavailable = false;
        $o .= $this->output->container_start('treasurehuntinfo');
        if ($info->timenow < $info->treasurehunt->allowattemptsfromdate) {
            $notavailable = true;
            $message = get_string('treasurehuntnotavailable', 'treasurehunt', userdate($info->treasurehunt->allowattemptsfromdate));
            $o .= html_writer::tag('p', $message) . "\n";
            if ($info->treasurehunt->cutoffdate) {
                $message = get_string('treasurehuntcloseson', 'treasurehunt', userdate($info->treasurehunt->cutoffdate));
                $o .= html_writer::tag('p', $message) . "\n";
            }
        } else if ($info->treasurehunt->cutoffdate && $info->timenow > $info->treasurehunt->cutoffdate) {
            $message = get_string('treasurehuntclosed', 'treasurehunt', userdate($info->treasurehunt->cutoffdate));
            $o .= html_writer::tag('p', $message) . "\n";
        } else {
            if ($info->treasurehunt->allowattemptsfromdate) {
                $message = get_string('treasurehuntopenedon', 'treasurehunt', userdate($info->treasurehunt->allowattemptsfromdate));
                $o .= html_writer::tag('p', $message) . "\n";
            }
            if ($info->treasurehunt->cutoffdate) {
                $message = get_string('treasurehuntcloseson', 'treasurehunt', userdate($info->treasurehunt->cutoffdate));
                $o .= html_writer::tag('p', $message) . "\n";
            }
        }
        // Type of geolocation: GPS or Desktop.
        if ($info->treasurehunt->playwithoutmoving) {
            $gamemode = get_string('playwithoutmoving', 'treasurehunt');
        } else {
            $gamemode = get_string('movingplay', 'treasurehunt');
        }
        // Group or individual playing.
        if ($info->treasurehunt->groupmode) {
            $gamemode = get_string('groupmode', 'treasurehunt') . '. ' . $gamemode;
        }
        $message = get_string('gamemodeinfo', 'treasurehunt', $gamemode);
        // Information about the groups/groupings involved in the game.
        $groupsmessages = [];
        foreach ($info->roads as $road) {

            if ($info->treasurehunt->groupmode == 0) {
                if ($road->groupid > 0) {
                    $gname = groups_get_group_name($road->groupid);
                    $link = new moodle_url('/group/overview.php', ['id' => $info->treasurehunt->course, 'group' => $road->groupid, 'grouping' => 0]);
                    $groupsmessages[] = html_writer::link($link, $gname);
                }
            } else if ($road->groupingid > 0) {
                $gname = groups_get_grouping_name($road->groupingid);
                $link = new moodle_url('/group/overview.php', ['id' => $info->treasurehunt->course, 'group' => 0, 'grouping' => $road->groupingid]);
                $groupsmessages[] = html_writer::link($link, $gname);
            }
        }
        if (count($groupsmessages) != 0) {
            $message .= '. ' . get_string('groups', 'treasurehunt') . ': ' . implode(', ', $groupsmessages);
        }
        $o .= html_writer::tag('p', $message);

        // Grading method.
        if ($info->treasurehunt->grade > 0) {
            $options = treasurehunt_get_grading_options();
            $a = new stdClass();
            $a->type = $options[$info->treasurehunt->grademethod];
            $a->gradepenlocation = number_format($info->treasurehunt->gradepenlocation);
            $a->gradepenanswer = number_format($info->treasurehunt->gradepenanswer);
            $message = get_string('grademethodinfo', 'treasurehunt', $a);
            $o .= html_writer::tag('p', $message . $this->help_icon('grademethod', 'treasurehunt')) . "\n";
        }
        if ($notavailable) {
            $urlparams = array('id' => $info->courseid);
            $o .= $this->output->single_button(new moodle_url('/course/view.php', $urlparams), get_string('backtocourse', 'treasurehunt'), 'get', array('class' => 'continuebutton'));
        }
        // Close the container and insert a spacer.
        $o .= $this->output->container_end();

        return $o;
    }

}
