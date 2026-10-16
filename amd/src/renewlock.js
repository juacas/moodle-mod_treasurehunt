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
 * @module    mod_treasurehunt/renewlock
 * @package
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>
 * @author Adrian Rodriguez <huorwhisp@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/notification', 'core/ajax'], function(notification, ajax) {
    let timer;
    let treasurehuntid;
    let lockid;
    let renewtime;
    let active = false;
    let inFlight = false;
    let retries = 0;
    let listenersInstalled = false;

    /**
     * Schedule the next renewal without overlapping requests.
     *
     * @param {number} delay Time until the next attempt in milliseconds.
     */
    const schedule = function(delay) {
        clearTimeout(timer);
        if (active) {
            timer = setTimeout(function() {
                renewLock.renewLockAjax();
            }, delay);
        }
    };

    const renewLock = {
        /** Renew the current lock and retry temporary request failures. */
        renewLockAjax: function() {
            if (!active || inFlight) {
                return;
            }
            inFlight = true;
            ajax.call([{
                methodname: 'mod_treasurehunt_renew_lock',
                args: {treasurehuntid: treasurehuntid, lockid: lockid}
            }])[0].done(function(response) {
                inFlight = false;
                if (response.status.code) {
                    renewLock.stoprenew_edition_lock();
                    notification.alert('Error', response.status.msg, 'Continue');
                    return;
                }
                retries = 0;
                if (response.lockid !== lockid) {
                    lockid = response.lockid;
                    document.dispatchEvent(new CustomEvent('treasurehunt:lockrenewed', {
                        detail: {treasurehuntid: treasurehuntid, lockid: lockid}
                    }));
                }
                schedule(renewtime);
            }).fail(function(error) {
                inFlight = false;
                retries++;
                if (retries === 3) {
                    notification.exception(error);
                }
                schedule(Math.min(renewtime, retries * 5000));
            });
        },

        /**
         * Start renewing while the page remains open.
         *
         * @param {number} activityid Activity instance id.
         * @param {number} initiallockid Current lock id.
         * @param {number} interval Renewal interval in milliseconds.
         */
        renew_edition_lock: function(activityid, initiallockid, interval) {
            treasurehuntid = activityid;
            lockid = initiallockid;
            renewtime = interval;
            active = true;
            retries = 0;
            schedule(renewtime);
            if (!listenersInstalled) {
                document.addEventListener('visibilitychange', function() {
                    if (!document.hidden && active) {
                        schedule(0);
                    }
                });
                window.addEventListener('focus', function() {
                    if (active) {
                        schedule(0);
                    }
                });
                listenersInstalled = true;
            }
        },

        /** Stop renewing after another editor takes the lock. */
        stoprenew_edition_lock: function() {
            active = false;
            clearTimeout(timer);
        },

        /**
         * @return {number} Current lock id.
         */
        getlockid: function() {
            return lockid;
        }
    };
    return renewLock;
});
