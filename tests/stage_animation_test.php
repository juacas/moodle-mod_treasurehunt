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
 * Stage event video configuration tests.
 *
 * @package mod_treasurehunt
 * @copyright 2026 Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @author Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_treasurehunt;

/**
 * Check activity preferences and administrator restrictions.
 *
 * @coversNothing
 */
final class stage_animation_test extends \advanced_testcase {
    /**
     * Administrator choices take priority over a previously saved activity choice.
     */
    public function test_site_restrictions_and_activity_fallback(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');

        unset_config('allowedstageanimations', 'mod_treasurehunt');
        $this->assertSame(['chest', 'safe'], treasurehunt_get_allowed_stage_animations());
        $this->assertSame('chest', treasurehunt_get_effective_stage_animation(null));
        $this->assertSame('none', treasurehunt_get_effective_stage_animation('none'));

        set_config('allowedstageanimations', 'safe', 'mod_treasurehunt');
        $this->assertSame(['safe'], treasurehunt_get_allowed_stage_animations());
        $this->assertSame('safe', treasurehunt_get_effective_stage_animation(null));
        $this->assertSame('none', treasurehunt_get_effective_stage_animation('chest'));
        $this->assertSame('safe', treasurehunt_get_effective_stage_animation('safe'));

        $hunt = (object)['customplayerconfig' => '{"stageanimation":"chest"}'];
        $this->assertSame('none', treasurehunt_get_customplayerconfig($hunt)->stageanimation);
        set_config('allowedstageanimations', '', 'mod_treasurehunt');
        $this->assertSame([], treasurehunt_get_allowed_stage_animations());
        $this->assertSame('none', treasurehunt_get_effective_stage_animation(null));
    }

    /**
     * The chosen animation is stored with the player settings.
     */
    public function test_activity_configuration_stores_video_choice(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');
        set_config('allowedstageanimations', 'chest,safe', 'mod_treasurehunt');

        $data = (object)[
            'searchpaneldisabled' => 0,
            'localizationbuttondisabled' => 0,
            'showheadinghint' => 0,
            'showinzonehint' => 0,
            'showdistancehint' => 0,
            'shownextareahint' => 0,
            'allowedbasemaps' => [],
            'stageanimation' => 'safe',
        ];
        $config = treasurehunt_build_customplayerconfig($data);
        $this->assertSame('safe', $config->stageanimation);
        $hunt = (object)['customplayerconfig' => json_encode($config)];
        $this->assertSame('safe', treasurehunt_get_customplayerconfig($hunt)->stageanimation);
    }
}
