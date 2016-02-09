<?php

// Standard GPL and phpdocs

namespace mod_scavengerhunt\output;

use renderable;
use renderer_base;
use templatable;
use stdClass;

class index_page implements renderable, templatable {

    /** @var string $sometext Some text to show how to pass data to a template. */
    var $sometext = null;
    var $sometext2 = null;

    public function __construct($sometext,$sometext2) {
        $this->sometext = $sometext;
        $this->sometext2 = $sometext2;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        $data = new stdClass();
        $data->sometext = $this->sometext;
        $data->sometext2 = $this->sometext2;
        return $data;
    }

}
