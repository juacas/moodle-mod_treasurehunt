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
     * @param assign_grading_summary $summary
     * @return string
     */
    public function render_scavengerhunt_grading_summary(scavengerhunt_grading_summary $summary) {
        // Create a table for the data.
        $o = '';
        $o .= $this->output->container_start('gradingsummary');
        $o .= $this->output->heading(get_string('gradingsummary', 'assign'), 3);
        $o .= $this->output->single_button(new moodle_url('/mod/assign/view.php', array("id"=>36)),
                                                       get_string('addsubmission', 'assign'), 'get');
        // Close the container and insert a spacer.
        $o .= $this->output->container_end();
       
        return $o;
    }
}