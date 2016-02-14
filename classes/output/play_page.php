<?php

// Standard GPL and phpdocs

namespace mod_scavengerhunt\output;

use renderable;
use renderer_base;
use templatable;
use stdClass;

class play_page implements renderable, templatable {

    /** @var string $sometext Some text to show how to pass data to a template. */
    var $scavengerhunt = null;
    var $riddle = null;

    public function __construct($scavengerhunt,$riddle) {
        $this->scavengerhunt = $scavengerhunt;
        $this->riddle = $riddle;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        GLOBAL $USER;
        $data = new stdClass();
        //$output->user_picture($USER);
        $data->user->name = $USER->firstname.' '.$USER->lastname ;
        $data->user->picture = $output->user_picture($USER);
        $data->scavengerhunt->name = $this->scavengerhunt[name];
        $data->scavengerhunt->description = $this->scavengerhunt[description];
        return $data;
    }

}
