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
 * Scans page for timestamps and format them as relative-dynamic dates.
 *
 * @package   mod_treasurehunt
 * @copyright 2018 Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @author Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/str'], function ($, str) {
    var strings = [];
    var dyndates = {
        /**
         * Starts a task that updates the dates.
         * Timestamps should be in data-timestamp and data-endtime attributes.
         * @param {string} selector jquery selector of elements. 
         * @param {float} longdate threshold for far dates
         * @param {float} mediumdate threshold for more near dates
         * @param {int} refreshtime seconds between each refresh.
         */
        init: function (selector, longdate = 200, mediumdate = 4, refreshtime = 60000) {
            // Load static strings.
            var keys = ['now', 'month', 'months', 'day', 'days', 'hour', 'hours', 'minute', 'minutes'];
            var keyobjs = keys.map((key) => { return { key: key, component: 'core' }; });
            keyobjs.push({ key: 'strftimedatetime', component: 'core_langconfig' });
            var strprom = []; // Promises for waiting for resolves.
            str.get_strings(keyobjs).then((strs) => {
                strs.forEach((val) => {
                    strprom.push(val);
                });
                // Wait again for all strings became resolved. (With jquery 2 individual strings are not resolved at first time.)
                Promise.all(strprom).then( values => {
                    var i = 0;
                    // Cache locally the static strings.
                    values.forEach((val) => {
                        strings[keyobjs[i++].key] = val;
                    });
                    // Start a timer of 1 second for updating the interface.
                    setInterval(() => {
                        var elements = $(selector);
                        elements.each((index, element) => {
                            var timestamp = element.dataset.timestamp;
                            var endtime = element.dataset.endtime;
                            var timestr = this.get_nice_date(timestamp, longdate, mediumdate, endtime);
                            timestr.then((timestrlocal) => {
                                var elem = $(element);
                                if (elem.text() != timestrlocal) {
                                    $(element).fadeOut('slow', () => { $(element).text(timestrlocal).fadeIn(); });
                                }
                            });
                        });
                    }, refreshtime);
                });
            });
        },
        /**
         * Generates a friendly string representation of the date relative to current time.
         * It uses i18n strings for building a sentence part:
         * - timeago for '23 hours ago'
         * - timeagolong for '23 hours ago (february, 23 2020)'
         * - timetocome for 'in 2 months 3 days'
         * - tiemtocomelong for 'in 3 months 3 days (february, 23 2020)'
         * - timeat for 'at febrary the 23th 2020'
         * - strftimedatetime for a localized long time-date format.
         * 
         * 
         * Example with longdate = 200
         *
         * |----------------|------------------------------------------------------|
         * | elapsed time   | formatted                                            |
         * |----------------|------------------------------------------------------|
         * |  t < 2 mins    |  now                                                 |
         * |  t < mediumdate|  3 days 23 hours ago                                 |
         * |  t < longdate  |  20 days ago (6 February 2020, 5:46 PM)              |
         * |  t > longdate  |  at 6 February 2020, 5:46 PM                         |
         * |----------------|------------------------------------------------------|
         * 
         * @param integer time unix timestamp (in seconds) to be formatted.
         * @param float|null mediumdate threshold (days) to use the short format without full date.
         * @param float|null longdate threshold (days) to use the longer format with full date.
         * @param int|null cur_tm unix timestamp to compare to. If null current time.
         * @return string formatted interval
         */
        get_nice_date: async function (time, longdate = 200, mediumdate = 4, cur_tm = null) {
            var timestringkey;

            if (cur_tm === null) {
                cur_tm = Date.now() / 1000; // To seconds.
            }
            var dif = cur_tm - time;
            var now = new Date();
            var date = new Date(time * 1000);

            if (cur_tm > time) {
                timestringkey = 'timeago';
            } else {
                timestringkey = 'timetocome';
                dif = -dif;
            }
            var elapsed = this.get_nice_duration(dif).then( text => {
                var el = 
                {
                    shortduration: text,
                    date: date.toLocaleString() //time, get_string('strftimedatetime', 'core_langconfig'));
                };
                return el;
            });
            

            if (dif < 2 * 60) {
                x = this.get_string('now');
            } else if (dif >= 0 && dif < (mediumdate * 3600 * 24) && dif < (longdate * 3600 * 24)) {
                x = this.get_string(timestringkey, 'treasurehunt', elapsed);
            } else if (dif < (longdate * 3600 * 24)) {
                elapsed.shortduration = this.get_nice_duration(dif, true, true);
                x = this.get_string(timestringkey + 'long', 'treasurehunt', elapsed);
            } else {
                x = this.get_string('timeat', 'treasurehunt', elapsed);
            }
            return x;
        },
        get_string: async function (key, component, param) {
            if (key in strings) {
                return Promise.resolve(strings[key]);
            }
            // Retrieve strings.
            return param.then((param) => {
                var string = str.get_string(key, component, param);
                return string;
            });
        },
        /**
         * Format a human-readable format for a duration in months or days and below.
         * calculates from seconds to months.
         * trim the details to the two more significant units
         * @param int durationinseconds
         * @param boolean usemonths if false render in days.
         * @param boolean shortprecission if true only the most significative unit.
         * @return string
         */
        get_nice_duration: async function (durationinseconds, usemonths = true, shortprecission = false) {
            var durationstring = '';
            var durationproms = [];
            var stop = false;
            var durationinseconds;
            var months;
            if (usemonths) {
                months = Math.floor(durationinseconds / (3600 * 24 * 30));
                durationinseconds -= months * (3600 * 24 * 30);
            }
            var days = Math.floor(durationinseconds / (3600 * 24));
            durationinseconds -= days * 3600 * 24;
            var hours = Math.floor(durationinseconds / 3600);
            durationinseconds -= hours * 3600;
            var minutes = Math.floor(durationinseconds / 60);
            var seconds = Math.round(durationinseconds - minutes * 60);
            
            if (usemonths && months > 0) {
                durationproms.push(this.get_string('month' + (months > 1 ? 's' : '')).then((strmonth) => { return months + strmonth }));
                //durationstring += months + strings['month' + (months > 1 ? 's' : '')];
                hours = 0;
                minutes = 0;
                seconds = 0;
                if (shortprecission) {
                    stop = true;
                }
            }
            if (days > 0 && stop === false) {
                durationproms.push( this.get_string('day' + (days > 1 ? 's' : '')).then( (strloc) => {
                    return ' ' + days + ' ' + strloc
                }));
                //                durationstring += ' ' + days + ' ' + strings['day' + (days > 1 ? 's' : '')];
                // Trim details less significant.
                minutes = 0;
                seconds = 0;
                if (shortprecission) {
                    stop = true;
                }
            }
            if (hours > 0 && stop === false) {
                durationproms.push(this.get_string('hour' + (hours > 1 ? 's' : '')).then((strloc) => { return ' ' + hours + ' ' + strloc }));
                // durationstring += ' ' + hours + ' ' + strings['hour' + (hours > 1 ? 's' : '')];
                seconds = false;
                if (shortprecission) {
                    stop = true;
                }
            }
            if (minutes > 0 && stop === false) {
                durationproms.push(this.get_string('minute' + (minutes > 1 ? 's' : ''))
                    .then((strloc) => {
                        return ' ' + minutes + ' ' + strloc
                    }));
                // durationstring += ' ' + minutes + ' ' + strings['minute' + (minutes > 1 ? 's' : '')];
                if (shortprecission) {
                    stop = true;
                }
            }
            if (seconds > 0 && stop === false) {
                durationproms.push(' ' + seconds + ' s.');
            }
            if (durationproms.length === 0) {
                durationproms.push('-');
            }
            return Promise.all(durationproms).then(parts => {
                return parts.join('')
            });
        }
    }
    return dyndates;
});