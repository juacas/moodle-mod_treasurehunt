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
 * @module    mod_treasurehunt/edit
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'jqueryui', 'mod_treasurehunt/intro'],
        function ($, jqui, introJS) {


            var init = {
                editpage: function (strings) {
                    var intro = introJS();
                    //data-step="2" data-intro="Each road must have two or more stages. Each stage gives a clue to find out the next."
                    //data-step="1" data-intro="Add one or more roads to be followed by your students."
                    intro.setOptions({
                        steps: [
                            {
                                element: '#treasurehunt-editor',
                                intro: strings['welcome_edit_tour'],
                                position: 'floating'
                            },
                            {
                                element: '#mapedit',
                                intro: strings['map_tour'],
                                position: 'floating'
                            },
                            {
                                element: '#roadlistpanel',
                                intro: strings['roads_tour'],
                                position: 'top'
                            },
                            {
                                element: '#stagelistpanel',
                                intro: strings['stages_tour'],
                                position: 'right'
                            },
                            {
                                element: '#addroad',
                                intro: strings['addroad_tour'],
                                position: 'bottom'
                            },
                            {
                                element: '#addstage',
                                intro: strings['addstage_tour'],
                                position: 'bottom'
                            },
                            {
                                element: '#savestage',
                                intro: strings['save_tour'],
                                position: 'bottom'
                            },
                            {
                                element: '#treasurehunt-editor',
                                intro: strings['editend_tour'],
                                position: 'floating'
                            }
                        ]
                    });
                    intro.oncomplete(function (target) {
                        document.cookie = "introEditProgress = Done";
                    });
                    intro.onchange(function (target) {
                        document.cookie = "introEditStep = "+target.name;
                    });
                    var cook = {};
                    document.cookie.split(';').forEach(function (x) {
                        var arr = x.split('=');
                        arr[1] && (cook[arr[0].trim()] = arr[1].trim());
                    });
                    if (cook["introEditProgress"] != 'Done') {
                        setTimeout(function () {
                            intro.start();
                        }, 1000);
                    }


                }, // end of editpage function
                playpage: function (strings) {
                    var intro = introJS();
                    //data-step="2" data-intro="Each road must have two or more stages. Each stage gives a clue to find out the next."
                    //data-step="1" data-intro="Add one or more roads to be followed by your students."
                    intro.setOptions({
                        steps: [
                            {
                                element: '#treasurehunt-editor',
                                intro: strings['welcome_edit_tour'],
                                position: 'floating'
                            },
                            {
                                element: '#mapedit',
                                intro: strings['map_tour'],
                                position: 'floating'
                            },
                            {
                                element: '#roadlistpanel',
                                intro: strings['roads_tour'],
                                position: 'top'
                            },
                            {
                                element: '#stagelistpanel',
                                intro: strings['stages_tour'],
                                position: 'right'
                            },
                            {
                                element: '#addroad',
                                intro: strings['addroad_tour'],
                                position: 'bottom'
                            },
                            {
                                element: '#addstage',
                                intro: strings['addstage_tour'],
                                position: 'bottom'
                            },
                            {
                                element: '#savestage',
                                intro: strings['save_tour'],
                                position: 'bottom'
                            },
                            {
                                element: '#treasurehunt-editor',
                                intro: strings['editend_tour'],
                                position: 'floating'
                            }
                        ]
                    });
                    intro.oncomplete(function (target) {
                        document.cookie = "introEditProgress = Done";
                    });
                    intro.onchange(function (target) {
                        document.cookie = "introEditStep = "+target.name;
                    });
                    var cook = {};
                    document.cookie.split(';').forEach(function (x) {
                        var arr = x.split('=');
                        arr[1] && (cook[arr[0].trim()] = arr[1].trim());
                    });
                    if (cook["introEditProgress"] != 'Done') {
                        setTimeout(function () {
                            intro.start();
                        }, 1000);
                    }


                }, // end of playpage function
            }; // end of init var
            return init;
        }
 ); // end of module define function