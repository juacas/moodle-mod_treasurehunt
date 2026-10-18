// This file is part of Treasurehunt for Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Formats page timestamps as dynamically updated relative dates.
 *
 * @copyright 2018 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @author Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/str'], function($, str) {
    const strings = Object.create(null);

    return {
        /**
         * Start updating date elements on the page.
         *
         * @param {string} selector jQuery selector for timestamp elements.
         * @param {number} longdate Days before showing an absolute date.
         * @param {number} mediumdate Days before showing the longer relative date.
         * @param {number} refreshtime Milliseconds between updates.
         * @returns {Promise} Resolves after the date refresh timer is started.
         */
        init: function(selector, longdate = 200, mediumdate = 4, refreshtime = 60000) {
            const keys = ['now', 'month', 'months', 'day', 'days', 'hour', 'hours', 'minute', 'minutes'];
            const keyobjects = keys.map((key) => ({key: key, component: 'core'}));

            return str.get_strings(keyobjects).then((stringpromises) => {
                return Promise.all(stringpromises);
            }).then((values) => {
                values.forEach((value, index) => {
                    strings[keys[index]] = value;
                });

                return setInterval(() => {
                    $(selector).each((index, element) => {
                        return this.getNiceDate(
                            Number(element.dataset.timestamp),
                            longdate,
                            mediumdate,
                            Number(element.dataset.endtime)
                        ).then((formatted) => {
                            const target = $(element);
                            if (target.text() !== formatted) {
                                target.fadeOut('slow', () => {
                                    target.text(formatted).fadeIn();
                                });
                            }
                            return formatted;
                        });
                    });
                }, refreshtime);
            });
        },

        /**
         * Format a timestamp relative to the current time.
         *
         * @param {number} time Unix timestamp in seconds.
         * @param {number} longdate Days before showing an absolute date.
         * @param {number} mediumdate Days before showing the longer relative date.
         * @param {?number} currenttime Unix timestamp used as the comparison time.
         * @returns {Promise<string>} The formatted date.
         */
        getNiceDate: async function(time, longdate = 200, mediumdate = 4, currenttime = null) {
            if (currenttime === null || Number.isNaN(currenttime)) {
                currenttime = Date.now() / 1000;
            }

            const difference = Math.abs(currenttime - time);
            const past = currenttime > time;
            const date = new Date(time * 1000);
            const duration = await this.getNiceDuration(difference);
            const parameters = {
                shortduration: duration,
                date: date.toLocaleString(),
            };

            if (difference < 2 * 60) {
                return this.getString('now');
            }

            const direction = past ? 'timeago' : 'timetocome';
            if (difference < mediumdate * 86400) {
                return this.getString(direction, 'treasurehunt', parameters);
            }
            if (difference < longdate * 86400) {
                parameters.shortduration = await this.getNiceDuration(difference, true, true);
                return this.getString(direction + 'long', 'treasurehunt', parameters);
            }
            return this.getString('timeat', 'treasurehunt', parameters);
        },

        /**
         * Retrieve a cached or localized language string.
         *
         * @param {string} key Language string identifier.
         * @param {string} component Language component.
         * @param {Object} parameters Language string parameters.
         * @returns {Promise<string>} The localized string.
         */
        getString: function(key, component = 'treasurehunt', parameters = null) {
            if (Object.prototype.hasOwnProperty.call(strings, key)) {
                return Promise.resolve(strings[key]);
            }
            return str.get_string(key, component, parameters);
        },

        /**
         * Format a duration using its two most significant units.
         *
         * @param {number} durationinseconds Duration to format.
         * @param {boolean} usemonths Whether to include months.
         * @param {boolean} shortprecision Whether to show only the most significant unit.
         * @returns {Promise<string>} The formatted duration.
         */
        getNiceDuration: async function(durationinseconds, usemonths = true, shortprecision = false) {
            let remaining = Math.max(0, durationinseconds);
            let stop = false;
            const parts = [];
            const units = [];

            if (usemonths) {
                units.push({name: 'month', seconds: 30 * 86400});
            }
            units.push(
                {name: 'day', seconds: 86400},
                {name: 'hour', seconds: 3600},
                {name: 'minute', seconds: 60}
            );

            for (const unit of units) {
                const count = Math.floor(remaining / unit.seconds);
                remaining -= count * unit.seconds;
                if (count > 0 && !stop) {
                    const key = unit.name + (count > 1 ? 's' : '');
                    parts.push(count + ' ' + await this.getString(key, 'core'));
                    stop = shortprecision;
                }
            }

            const seconds = Math.round(remaining);
            if (seconds > 0 && !stop) {
                parts.push(seconds + ' s.');
            }
            return parts.length ? parts.join(' ') : '-';
        },
    };
});
