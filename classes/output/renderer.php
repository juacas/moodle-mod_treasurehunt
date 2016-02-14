<?php

// Standard GPL and phpdocs
namespace mod_scavengerhunt\output;                                                                                                         
 
defined('MOODLE_INTERNAL') || die;                                                                                                  
 
use plugin_renderer_base;  
 
class renderer extends plugin_renderer_base {
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
}