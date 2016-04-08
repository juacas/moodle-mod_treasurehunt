<?php

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/mod/scavengerhunt/locallib.php');

class mod_scavengerhunt_renderer extends plugin_renderer_base {

    /**
     * Utility function to add a row of data to a table with 2 columns. Modified
     * the table param and does not return a value
     *
     * @param html_table $table The table to append the row of data to
     * @param string $first The first column text
     * @param string $second The second column text
     * @param string $third The third column text
     * @return void
     */
    private function add_table_row_triple(html_table $table, $first, $second, $third) {
        $row = new html_table_row();
        $cell1 = new html_table_cell($first);
        $cell2 = new html_table_cell($second);
        $cell3 = new html_table_cell($third);
        $row->cells = array($cell1, $cell2,$cell3);
        $table->data[] = $row;
    }
        /**
     * Utility function to add a row of data to a table with 2 columns. Modified
     * the table param and does not return a value
     *
     * @param html_table $table The table to append the row of data to
     * @param string $first The first column text
     * @param string $second The second column text
     * @return void
     */
    private function add_table_row_tuple(html_table $table, $first, $second) {
        $row = new html_table_row();
        $cell1 = new html_table_cell($first);
        $cell2 = new html_table_cell($second);
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;
    }
        /**
     * Utility function to add a row of data to a table with 2 columns. Modified
     * the table param and does not return a value
     *
     * @param html_table $table The table to append the row of data to
     * @param string $first The row column text
     * @return void
     */
    private function add_table_row_single(html_table $table, $first) {
        $row = new html_table_row();
        $cell1 = new html_table_cell($first);
        $row->cells = array($cell1);
        $table->data[] = $row;
    }
    /**
     * Defer to template.                                                                                                           
     *                                                                                                                              
     * @param index_page $page                                                                                                      
     *                                                                                                                              
     * @return string html for the page                                                                                             
     */
    public function render_play_page(\mod_scavengerhunt\output\play_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('mod_scavengerhunt/play', $data);
    }

    /**
     * Render a table containing the current status of the grading process.
     *
     * @param scavengerhunt_user_historical_riddles  $historical
     * @return string
     */
    public function render_scavengerhunt_user_historical_riddles(scavengerhunt_user_historical_riddles $historical) {
        // Create a table for the data.
        $o = '';
        $o .= $this->output->container_start('historicalriddles');
        $o .= $this->output->heading(get_string('gradingsummary', 'scavengerhunt'), 3);
        $o .= $this->output->box_start('boxaligncenter gradingsummarytable');
        $t = new html_table();
        // Status.
        if ($summary->teamsubmission) {
            if ($summary->warnofungroupedusers) {
                $o .= $this->output->notification(get_string('ungroupedusers', 'assign'));
            }

            $this->add_table_row_tuple($t, get_string('numberofteams', 'assign'), $summary->participantcount);
        } else {
            $this->add_table_row_tuple($t, get_string('numberofparticipants', 'assign'), $summary->participantcount);
        }

        // Drafts count and dont show drafts count when using offline assignment.
        if ($summary->submissiondraftsenabled && $summary->submissionsenabled) {
            $this->add_table_row_tuple($t, get_string('numberofdraftsubmissions', 'assign'), $summary->submissiondraftscount);
        }

        // Submitted for grading.
        if ($summary->submissionsenabled) {
            $this->add_table_row_tuple($t, get_string('numberofsubmittedassignments', 'assign'), $summary->submissionssubmittedcount);
            if (!$summary->teamsubmission) {
                $this->add_table_row_tuple($t, get_string('numberofsubmissionsneedgrading', 'assign'), $summary->submissionsneedgradingcount);
            }
        }

        $time = time();
        if ($summary->duedate) {
            // Due date.
            $duedate = $summary->duedate;
            $this->add_table_row_tuple($t, get_string('duedate', 'assign'), userdate($duedate));

            // Time remaining.
            $due = '';
            if ($duedate - $time <= 0) {
                $due = get_string('assignmentisdue', 'assign');
            } else {
                $due = format_time($duedate - $time);
            }
            $this->add_table_row_tuple($t, get_string('timeremaining', 'assign'), $due);

            if ($duedate < $time) {
                $cutoffdate = $summary->cutoffdate;
                if ($cutoffdate) {
                    if ($cutoffdate > $time) {
                        $late = get_string('latesubmissionsaccepted', 'assign', userdate($summary->cutoffdate));
                    } else {
                        $late = get_string('nomoresubmissionsaccepted', 'assign');
                    }
                    $this->add_table_row_tuple($t, get_string('latesubmissions', 'assign'), $late);
                }
            }
        }
        // All done - write the table.
        $o .= html_writer::table($t);
        $urlparams = array('id' => $summary->coursemoduleid);
        $o .= $this->output->single_button(new moodle_url('/mod/assign/play.php', $urlparams), get_string('addsubmission', 'assign'), 'get');

        $o .= $this->output->box_end();

        // Close the container and insert a spacer.
        $o .= $this->output->container_end();

        return $o;
    }
     /**
     * Render a table containing the current status of the grading process.
     *
     * @param scavengerhunt_grading_summary $summary
     * @return string
     */
    public function render_scavengerhunt_grading_summary(scavengerhunt_grading_summary $summary) {
        // Create a table for the data.
        $o = '';
        $o .= $this->output->container_start('gradingsummary');
        $o .= $this->output->heading(get_string('gradingsummary', 'scavengerhunt'), 3);
        $o .= $this->output->box_start('boxaligncenter gradingsummarytable');
        $t = new html_table();
        // Status.
        if ($summary->teamsubmission) {
            if ($summary->warnofungroupedusers) {
                $o .= $this->output->notification(get_string('ungroupedusers', 'assign'));
            }

            $this->add_table_row_tuple($t, get_string('numberofteams', 'assign'), $summary->participantcount);
        } else {
            $this->add_table_row_tuple($t, get_string('numberofparticipants', 'assign'), $summary->participantcount);
        }

        // Drafts count and dont show drafts count when using offline assignment.
        if ($summary->submissiondraftsenabled && $summary->submissionsenabled) {
            $this->add_table_row_tuple($t, get_string('numberofdraftsubmissions', 'assign'), $summary->submissiondraftscount);
        }

        // Submitted for grading.
        if ($summary->submissionsenabled) {
            $this->add_table_row_tuple($t, get_string('numberofsubmittedassignments', 'assign'), $summary->submissionssubmittedcount);
            if (!$summary->teamsubmission) {
                $this->add_table_row_tuple($t, get_string('numberofsubmissionsneedgrading', 'assign'), $summary->submissionsneedgradingcount);
            }
        }

        $time = time();
        if ($summary->duedate) {
            // Due date.
            $duedate = $summary->duedate;
            $this->add_table_row_tuple($t, get_string('duedate', 'assign'), userdate($duedate));

            // Time remaining.
            $due = '';
            if ($duedate - $time <= 0) {
                $due = get_string('assignmentisdue', 'assign');
            } else {
                $due = format_time($duedate - $time);
            }
            $this->add_table_row_tuple($t, get_string('timeremaining', 'assign'), $due);

            if ($duedate < $time) {
                $cutoffdate = $summary->cutoffdate;
                if ($cutoffdate) {
                    if ($cutoffdate > $time) {
                        $late = get_string('latesubmissionsaccepted', 'assign', userdate($summary->cutoffdate));
                    } else {
                        $late = get_string('nomoresubmissionsaccepted', 'assign');
                    }
                    $this->add_table_row_tuple($t, get_string('latesubmissions', 'assign'), $late);
                }
            }
        }
        // All done - write the table.
        $o .= html_writer::table($t);
        $urlparams = array('id' => $summary->coursemoduleid);
        $o .= $this->output->single_button(new moodle_url('/mod/assign/play.php', $urlparams), get_string('addsubmission', 'assign'), 'get');

        $o .= $this->output->box_end();

        // Close the container and insert a spacer.
        $o .= $this->output->container_end();

        return $o;
    }

}
