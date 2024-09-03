<?php
// This file is part of Treasurehunt for Moodle
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
 * Defines the renderer for the treasurehunt module.
 *
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @author Adrian Rodriguez <huorwhisp@gmail.com>
 * @author Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');

class mod_treasurehunt_renderer extends plugin_renderer_base
{

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
    private function add_table_row(html_table $table, array $text, $header, array $class = null, array $colspan = null)
    {
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
     * @global moodle_page $PAGE
     * @return string html for the page
     */
    public function render_treasurehunt_play_page_classic(treasurehunt_play_page_classic $renderablepage)
    {
        global $PAGE, $CFG;
        $cm = $renderablepage->cm;
        $treasurehunt = $renderablepage->treasurehunt;
        // Moodle 3.8 uses now Babel to compile and compress the js files.
        // This broke this page because it uses jquery2 and jquerymobile.
        // The only workaround we found is to disable Babel cache for this only page.
        $CFG->cachejs = false;
        $PAGE->requires->js('/mod/treasurehunt/js/jquery2/jquery-2.1.4.min.js');
        $PAGE->requires->js_call_amd(
            'mod_treasurehunt/play_classic',
            'playtreasurehunt',
            array(
                $cm->id, $cm->instance,
                intval($treasurehunt->playwithoutmoving),
                intval($treasurehunt->groupmode),
                $renderablepage->lastattempttimestamp,
                $renderablepage->lastroadtimestamp,
                $renderablepage->gameupdatetime,
                $treasurehunt->tracking,
                $renderablepage->user,
                $renderablepage->custommapping
            )
        );
        // Adds support for QR scan.
        treasurehunt_qr_support($PAGE, 'setup', []);
        $PAGE->requires->js_call_amd('mod_treasurehunt/tutorial_classic', 'playpage');
        $PAGE->requires->js_call_amd('mod_treasurehunt/dyndates', 'init', ['span[data-timestamp']);
        $PAGE->requires->css('/mod/treasurehunt/css/playerclassic/introjs.css');
        $PAGE->requires->css('/mod/treasurehunt/css/playerclassic/jquerymobile.css');
        $PAGE->requires->css('/mod/treasurehunt/css/playerclassic/treasure.css');
        $PAGE->set_pagelayout('embedded');

        /*
 * Other things you may want to set - remove if not needed.
 * $PAGE->set_cacheable(false);
 * $PAGE->set_focuscontrol('some-html-id');
 * $PAGE->add_body_class('treasurehunt-'.$somevar);
 */
        // Output starts here.
        $pageheader = $this->header();
        // Polyfill service adds compatibility to old browsers like IOS WebKit for requestAnimationFrame.
        $pageheader .= '<script src="https://cdnjs.cloudflare.com/polyfill/v2/polyfill.min.js?features=fetch,requestAnimationFrame,Element.prototype.classList,URL"></script>';

        $data = $renderablepage->export_for_template($this);
        $pagerendered = parent::render_from_template('mod_treasurehunt/play_page_classic', $data);
        // Finish the page.
        $pagefooter = $this->footer();

        // JPC: Generate a global variable with strings. Moodle 3.8 broke compatibility of core/str with jquery 2.1.4.
        $terms = [
            "stageovercome", "failedlocation", "stage", "stagename",
            "stageclue", "question", "noanswerselected", "timeexceeded",
            "searching", "continue", "noattempts", "aerialview", "roadview",
            "noresults", "startfromhere", "nomarks", "updates", "activitytoendwarning",
            "huntcompleted", "discoveredlocation", "answerwarning", "error", "pegmanlabel"
        ];
        $strings = [];
        foreach ($terms as $term) {
            $strings[$term] = get_string($term, 'treasurehunt');
        }
        $i18n = json_encode($strings);
        $i18nfragment  =  <<<I18N
<!-- Internationalization strings for the player -->
<script type="text/javascript">
i18nplay = $i18n;
</script>
I18N;

        // Patch: disable modules that are jquery 2.1.4 uncompatible/unnecesary
        $disable = [
            'core/notification',
            'block_navigation/navblock',
            'block_settings/settingsblock',
            'core/log',
            'core/page_global',
        ];
        foreach ($disable as $module) {
            $pagefooter = str_replace("M.util.js_pending('$module')", "//M.util.js_pending('$module')", $pagefooter);
        }
        return $pageheader . $pagerendered . $i18nfragment . $pagefooter;
    }
    /**
     * Defer to template.
     *
     * @param treasurehunt_play_page $page
     * @global moodle_page $PAGE
     * @return string html for the page
     */
    public function render_treasurehunt_play_page_fancy(treasurehunt_play_page_fancy $renderablepage)
    {
        global $PAGE, $CFG;
        $cm = $renderablepage->cm;
        $treasurehunt = $renderablepage->treasurehunt;
        // Moodle 3.8 uses now Babel to compile and compress the js files.
        // This broke this page because it uses jquery2 and jquerymobile.
        // The only workaround we found is to disable Babel cache for this only page.
        $CFG->cachejs = false;
        $PAGE->requires->js('/mod/treasurehunt/js/jquery2/jquery-2.1.4.min.js');
        $PAGE->requires->js_call_amd(
            'mod_treasurehunt/play_fancy',
            'playtreasurehunt',
            array(
                $cm->id, $cm->instance,
                intval($treasurehunt->playwithoutmoving),
                intval($treasurehunt->groupmode),
                $renderablepage->lastattempttimestamp,
                $renderablepage->lastroadtimestamp,
                $renderablepage->gameupdatetime,
                $treasurehunt->tracking,
                $renderablepage->user,
                $renderablepage->custommapping
            )
        );
        // Adds support for QR scan.
        treasurehunt_qr_support($PAGE, 'setup', []);
        $PAGE->requires->js_call_amd('mod_treasurehunt/tutorial_fancy', 'playpage');
        $PAGE->requires->js_call_amd('mod_treasurehunt/dyndates', 'init', ['span[data-timestamp']);
        $PAGE->requires->css('/mod/treasurehunt/css/playerfancy/introjs.css');
        $PAGE->requires->css('/mod/treasurehunt/css/playerfancy/jquerymobile.css');
        $PAGE->requires->css('/mod/treasurehunt/css/playerfancy/treasure.css');
        $PAGE->requires->css('/mod/treasurehunt/css/playerfancy/styles.css');
        $PAGE->set_pagelayout('embedded');

        /*
 * Other things you may want to set - remove if not needed.
 * $PAGE->set_cacheable(false);
 * $PAGE->set_focuscontrol('some-html-id');
 * $PAGE->add_body_class('treasurehunt-'.$somevar);
 */
        // Output starts here.
        $pageheader = $this->header();
        // Polyfill service adds compatibility to old browsers like IOS WebKit for requestAnimationFrame.
        $pageheader .= '<script src="https://cdnjs.cloudflare.com/polyfill/v2/polyfill.min.js?features=fetch,requestAnimationFrame,Element.prototype.classList,URL"></script>';

        $data = $renderablepage->export_for_template($this);
        $pagerendered = parent::render_from_template('mod_treasurehunt/play_page_fancy', $data);
        // Finish the page.
        $pagefooter = $this->footer();

        // JPC: Generate a global variable with strings. Moodle 3.8 broke compatibility of core/str with jquery 2.1.4.
        $terms = [
            "stageovercome", "failedlocation", "stage", "stagename",
            "stageclue", "question", "noanswerselected", "timeexceeded",
            "searching", "continue", "noattempts", "aerialview", "roadview",
            "noresults", "startfromhere", "nomarks", "updates", "activitytoendwarning",
            "huntcompleted", "discoveredlocation", "answerwarning", "error", "pegmanlabel"
        ];
        $strings = [];
        foreach ($terms as $term) {
            $strings[$term] = get_string($term, 'treasurehunt');
        }
        $i18n = json_encode($strings);
        $i18nfragment  =  <<<I18N
<!-- Internationalization strings for the player -->
<script type="text/javascript">
i18nplay = $i18n;
</script>
I18N;

        // Patch: disable modules that are jquery 2.1.4 uncompatible/unnecesary
        $disable = [
            'core/notification',
            'block_navigation/navblock',
            'block_settings/settingsblock',
            'core/log',
            'core/page_global',
        ];
        foreach ($disable as $module) {
            $pagefooter = str_replace("M.util.js_pending('$module')", "//M.util.js_pending('$module')", $pagefooter);
        }
        return $pageheader . $pagerendered . $i18nfragment . $pagefooter;
    }

    public function render_treasurehunt_play_page_bootstrap(treasurehunt_play_page_bootstrap $renderablepage)
    {
        global $PAGE, $CFG;
        $cm = $renderablepage->cm;
        $treasurehunt = $renderablepage->treasurehunt;
        $PAGE->requires->js_call_amd(
            'mod_treasurehunt/play_bootstrap',
            'playtreasurehunt',
            array(
                $cm->id, $cm->instance,
                intval($treasurehunt->playwithoutmoving),
                intval($treasurehunt->groupmode),
                $renderablepage->lastattempttimestamp,
                $renderablepage->lastroadtimestamp,
                $renderablepage->gameupdatetime,
                $treasurehunt->tracking,
                $renderablepage->user,
                $renderablepage->custommapping
            )
        );
        // Adds support for QR scan.
        treasurehunt_qr_support($PAGE, 'setup', []);
        $PAGE->requires->js_call_amd('mod_treasurehunt/tutorial_bootstrap', 'playpage');
        $PAGE->requires->js_call_amd('mod_treasurehunt/dyndates', 'init', ['span[data-timestamp']);
        $PAGE->requires->css('/mod/treasurehunt/css/playerbootstrap/introjs.css');
        $PAGE->requires->css('/mod/treasurehunt/css/playerbootstrap/loading-animation.css');
        $PAGE->requires->css('/mod/treasurehunt/css/playerbootstrap/play.css');

        $PAGE->set_pagelayout('embedded');

        // Output starts here.
        $pageheader = $this->header();
        // Polyfill service adds compatibility to old browsers like IOS WebKit for requestAnimationFrame.
        $pageheader .= '<script src="https://cdnjs.cloudflare.com/polyfill/v2/polyfill.min.js?features=fetch,requestAnimationFrame,Element.prototype.classList,URL"></script>';

        $data = $renderablepage->export_for_template($this);
        $pagerendered = parent::render_from_template('mod_treasurehunt/play_page_bootstrap', $data);
        // Finish the page.
        $pagefooter = $this->footer();
        return $pageheader . $pagerendered . $pagefooter;
    }
    /**
     * Defer to template.
     *
     * @param treasurehunt_play_page $page
     *
     * @return string html for the page
     */
    public function render_treasurehunt_edit_page(treasurehunt_edit_page $page)
    {
        $data = $page->export_for_template($this);
        return parent::render_from_template('mod_treasurehunt/edit_page', $data);
    }

    /**
     * Render a table containing the current status of the user attempts.
     *
     * @param treasurehunt_user_attempt_history  $historical
     * @return string
     */
    public function render_treasurehunt_user_attempt_history(treasurehunt_user_attempt_history $historical)
    {
        // Create a table for the data.
        $o = '';

        $o .= $this->output->container_start('attempthistory');
        $o .= $this->output->heading(get_string('userattempthistory', 'treasurehunt', $historical->username), 3);
        $o .= $this->output->box_start('boxaligncenter gradingsummarytable');
        // Status.
        if (count($historical->attempts)) {
            $numattempt = 1;
            $t = new html_table();
            $col1 = new html_table_cell(get_string('attempt', 'treasurehunt'));
            $col2 = new html_table_cell(get_string('state', 'treasurehunt'));
            $col1->attributes = ['width'=> '1%', 'class' => ''];
            $col2->attributes = ['width' => '100%', 'class' => ''];
            $t->head = [$col1, $col2];
            foreach ($historical->attempts as $attempt) {
                if (!$attempt->penalty) {
                    $class = 'successfulattempt';
                } else {
                    $class = 'failedattempt';
                }
                $cell1 = new html_table_cell($numattempt++);
                $cell1->attributes = ['class' => $class];
                $cell2 = new html_table_cell($attempt->string);

                $t->data[] = new html_table_row([$cell1, $cell2]);
            }
            // All done - write the table.
            $o .= html_writer::table($t);
        } else {
            if ($historical->teacherreview) {
                $o .= $this->output->notification(get_string('nouserattempts', 'treasurehunt', $historical->username), 'notifymessage');
            } else {
                $o .= $this->output->notification(get_string('noattempts', 'treasurehunt'), 'notifymessage');
            }
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
    public function render_treasurehunt_users_progress(treasurehunt_users_progress $progress)
    {
        // Create a table for the data.
        $o = '';
        $s = '';
        if (!count($progress->roadsusersprogress) && $progress->managepermission) {
            $s .= $this->output->notification(get_string('noroads', 'treasurehunt'));
        } else {
            if (count($progress->duplicategroupsingroupings) && $progress->managepermission) {
                $s .= $this->output->notification(get_string(
                    'warnusersgrouping',
                    'treasurehunt',
                    implode(', ', $progress->duplicategroupsingroupings)
                ));
            }
            if (count($progress->duplicateusersingroups) && $progress->managepermission) {
                $s .= $this->output->notification(get_string(
                    'warnusersgroup',
                    'treasurehunt',
                    implode(', ', $progress->duplicateusersingroups)
                ));
            }
            if (count($progress->unassignedusers) && $progress->managepermission) {
                $s .= $this->output->notification(get_string(
                    'warnusersoutside',
                    'treasurehunt',
                    implode(', ', $progress->unassignedusers)
                ));
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
                                $this->add_table_row(
                                    $t,
                                    array(
                                        $title,
                                        get_string('totaltime', 'treasurehunt'),
                                        get_string('start', 'treasurehunt'),
                                        get_string('stages', 'treasurehunt')
                                    ),
                                    true,
                                    null,
                                    array(null, null, null, $roadusersprogress->totalstages)
                                );
                                $hasprogress = true;
                            }
                            $row = new html_table_row();
                            if ($progress->groupmode) {
                                $name = $userorgroup->name;
                                if ($progress->viewpermission) {
                                    $params = array('id' => $progress->coursemoduleid, 'groupid' => $userorgroup->id);
                                    $url = new moodle_url('/mod/treasurehunt/view.php', $params);
                                    $icon = $this->output->pix_icon('t/preview', get_string('userattempthistory', 'treasurehunt', $name));
                                    $name = $name . ' ' . html_writer::link($url, $icon);
                                }
                                $elapsed = treasurehunt_get_hunt_duration($progress->coursemoduleid, null, $userorgroup->id);
                            } else {
                                $fullname = fullname($userorgroup);
                                $userpic = $this->output->user_picture($userorgroup, array('size' => 32));
                                $userurl = new moodle_url('/user/view.php', array('id' => $userorgroup->id, 'courseid' => $this->page->course->id));
                                $name = $userpic . html_writer::link($userurl, $fullname);
                                if ($progress->viewpermission) {
                                    $params = array('id' => $progress->coursemoduleid, 'userid' => $userorgroup->id);
                                    $url = new moodle_url('/mod/treasurehunt/view.php', $params);
                                    $icon = $this->output->pix_icon('t/preview', get_string('userattempthistory', 'treasurehunt', $fullname));
                                    $name .= ' ' . html_writer::link($url, $icon);
                                }
                                $elapsed = treasurehunt_get_hunt_duration($progress->coursemoduleid, $userorgroup->id, null);
                            }
                            $cells = array($name);
                            $cells[] = treasurehunt_get_nice_duration($elapsed->duration);
                            $cells[] = treasurehunt_get_nice_date($elapsed->first);

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
                            $s .= $this->output->notification(get_string('nousersprogress', 'treasurehunt'), 'notifymessage');
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
    public function render_treasurehunt_info(treasurehunt_info $info)
    {
        $o = '';
        // Warn about the use of QR scanner.
        if ($info->numqrs > 0) {
            global $PAGE;
            $params = ['qrTestSuccessString' => get_string('warnqrscannersuccess', 'treasurehunt', $info->numqrs)];
            treasurehunt_qr_support($PAGE, 'enableTest', $params);
            $o .= $this->output->container_start(null, 'QRStatusDiv');
            $warnqr = get_string('warnqrscanner', 'treasurehunt', $info->numqrs);
            $o .= $this->output->notification($warnqr, core\notification::WARNING) . "\n";
            $o .= '<div  id="previewQR" width = "100%" style="min-height:200px; max-height:500px">
            <center><video playsinline id="previewQRvideo" style="display:none" height="200"></video></center>
            </div>
            <div id="QRvalue"></div>' .
                '<button style="display:none" onclick="setnextwebcam(testFormReport)" id="idbuttonnextcam">' .
                get_string('changecamera', 'treasurehunt') . '</button>';
            $o .= '</div>';
            $o .= '<div id="errorQR" style="display: none" >' . get_string('warnqrscannererror', 'treasurehunt', $info->numqrs) ;
            $o .= $this->output->container_end();
        }
        // Create a table for the data.
        $notavailable = false;
        $o .= $this->output->container_start('treasurehuntinfo');
        if ($info->timenow < $info->treasurehunt->allowattemptsfromdate) {
            $notavailable = true;
            $message = get_string('treasurehuntnotavailable', 'treasurehunt',
                treasurehunt_get_nice_date($info->treasurehunt->allowattemptsfromdate, 30, 1/48));
            $o .= html_writer::tag('p', $message) . "\n";
            if ($info->treasurehunt->cutoffdate) {
                $message = get_string(
                    'treasurehuntcloseson',
                    'treasurehunt',
                    treasurehunt_get_nice_date($info->treasurehunt->cutoffdate, 30, 1/48)
                );
                $o .= html_writer::tag('p', $message) . "\n";
            }
        } elseif ($info->treasurehunt->cutoffdate && $info->timenow > $info->treasurehunt->cutoffdate) {
            $message = get_string(
                'treasurehuntclosed',
                'treasurehunt',
                treasurehunt_get_nice_date($info->treasurehunt->cutoffdate, 30, 1/48)
            );
            $o .= html_writer::tag('p', $message) . "\n";
        } else {
            if ($info->treasurehunt->allowattemptsfromdate) {
                $message = get_string(
                    'treasurehuntopenedon',
                    'treasurehunt',
                    treasurehunt_get_nice_date($info->treasurehunt->allowattemptsfromdate)
                );
                $o .= html_writer::tag('p', $message) . "\n";
            }
            if ($info->treasurehunt->cutoffdate) {
                $message = get_string(
                    'treasurehuntcloseson',
                    'treasurehunt',
                    treasurehunt_get_nice_date($info->treasurehunt->cutoffdate, 30, 1/48)
                );
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
            } elseif ($road->groupingid > 0) {
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
            $o .= $this->output->single_button(
                new moodle_url('/course/view.php', $urlparams),
                get_string('backtocourse', 'treasurehunt'),
                'get',
                array('class' => 'continuebutton')
            );
        }
        // Close the container and insert a spacer.
        $o .= $this->output->container_end();

        return $o;
    }
}
