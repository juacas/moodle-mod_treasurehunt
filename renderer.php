<?php

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
     * @param array $colspan Array with the class of each cell
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
     * @param index_page $page                                                                                                      
     *                                                                                                                              
     * @return string html for the page                                                                                             
     */
    public function render_play_page(\mod_treasurehunt\output\play_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('mod_treasurehunt/play', $data);
    }

    /**
     * Render a table containing the current status of the grading process.
     *
     * @param treasurehunt_user_historical_riddles  $historical
     * @return string
     */
    public function render_treasurehunt_user_historical_attempts(treasurehunt_user_historical_attempts $historical) {
        // Create a table for the data.
        $o = '';
        $o .= $this->output->container_start('historicalattempts');
        $o .= $this->output->heading(get_string('historicalattempts', 'treasurehunt'), 3);
        $o .= $this->output->box_start('boxaligncenter gradingsummarytable');
        // Status.
        if (count($historical->attempts)) {
            $numattempt = 1;
            $t = new html_table();
            $this->add_table_row($t, array(get_string('attempt', 'treasurehunt'), get_string('state', 'treasurehunt')), true);
            foreach ($historical->attempts as $attempt) {
                if ($attempt->success) {
                    $class = 'successfulattempt';
                } else {
                    $class = 'failedattempt';
                }
                $this->add_table_row($t, array($numattempt++, $attempt->string), false, array($class, ''));
            }
            // All done - write the table.
            $o .= html_writer::table($t);
        } else {
            $o .= $this->output->notification(get_string('noattempts', 'treasurehunt'));
        }
        // Si no ha finalizado pongo el botón de jugar
        $urlparams = array('id' => $historical->coursemoduleid);
        $o .= $this->output->single_button(new moodle_url('/mod/treasurehunt/play.php', $urlparams), get_string('play', 'treasurehunt'), 'get');
        $o .= $this->output->box_end();

        // Close the container and insert a spacer.
        $o .= $this->output->container_end();

        return $o;
    }

    /**
     * Render a table containing the current status of the grading process.
     *
     * @param treasurehunt_user_progress $progress
     * @return string
     */
    public function render_treasurehunt_users_progress(treasurehunt_users_progress $progress) {
        // Create a table for the data.
        $o = '';
        $o .= $this->output->container_start('usersprogress');
        $o .= $this->output->heading(get_string('usersprogress', 'treasurehunt'), 3);
        if (!count($progress->roadsusersprogress)) {
            $o .= $this->output->notification(get_string('noroads', 'treasurehunt'));
        } else {
            if ($progress->warngroupedusers) {
                if ($progress->groupmode) {
                    $o .= $this->output->notification(get_string('warnusersgrouping', 'treasurehunt'));
                } else {
                    $o .= $this->output->notification(get_string('warnusersgroup', 'treasurehunt'));
                }
            }
            foreach ($progress->roadsusersprogress as $roadusersprogress) {
                $o .= $this->output->heading($roadusersprogress->name, 4);
                if ($roadusersprogress->validated) {
                    if (count($roadusersprogress->userlist)) {
                        $o .= $this->output->box_start('boxaligncenter usersprogresstable');
                        $t = new html_table();
                        if ($progress->groupmode) {
                            $title = get_string('group', 'treasurehunt');
                        } else {
                            $title = get_string('user', 'treasurehunt');
                        }
                        $this->add_table_row($t, array($title, get_string('riddles', 'treasurehunt')), true, null, array(null, $roadusersprogress->totalriddles - 1));
                        foreach ($roadusersprogress->userlist as $user) {
                            $row = new html_table_row();
                            if ($progress->groupmode) {
                                $name = $user->name;
                            } else {
                                $name = fullname($user);
                            }
                            $cells = array($name);
                            for ($i = 1; $i < $roadusersprogress->totalriddles; $i++) {
                                $cell = new html_table_cell($i);
                                if (isset($user->ratings[$i])) {
                                    $cell->attributes['class'] = $user->ratings[$i]->class;
                                } else {
                                    $cell->attributes['class'] = 'noattempt';
                                }
                                array_push($cells, $cell);
                            }
                            $row->cells = $cells;
                            $t->data[] = $row;
                        }
                        // All done - write the table.
                        $o .= html_writer::table($t);
                        $o .= $this->output->box_end();
                    } else {
                        if ($progress->groupmode) {
                            $notification = get_string('nogroupassigned', 'treasurehunt');
                        } else {
                            $notification = get_string('nouserassigned', 'treasurehunt');
                        }
                        $o .= $this->output->notification($notification);
                    }
                } else {
                    $o .= $this->output->notification(get_string('invalroadid', 'treasurehunt'));
                }
            }
        }
        $urlparams = array('id' => $progress->coursemoduleid);
        $o .= $this->output->single_button(new moodle_url('/mod/treasurehunt/edit.php', $urlparams), get_string('edittreasurehunt', 'treasurehunt'), 'get');
        // Close the container and insert a spacer.
        $o .= $this->output->container_end();

        return $o;
    }

}
