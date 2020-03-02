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
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>
 * @author Adrian Rodriguez <huorwhisp@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/notification', 'core/ajax'], function ($, notification, ajax) {

    var repeat;
    var lockidTreasure;
    var renewLock = {
        renewLockAjax: function (treasurehuntid, lockid) {
            lockidTreasure = lockid;
            var json = ajax.call([{
                    methodname: 'mod_treasurehunt_renew_lock',
                    args: {
                        treasurehuntid: treasurehuntid,
                        lockid: lockid
                    }
                }]);
            json[0].done(function (response) {
                console.log(response);
                if (response.status.code) {
                    notification.alert('Error', response.status.msg, 'Continue');
                    renewLock.stoprenew_edition_lock();
                }
            }).fail(function (error) {
                console.log(error);
                notification.exception(error);
                renewLock.stoprenew_edition_lock();
            });
        },
        /**Renuevo de continuo el bloqueo de edicion **/
        renew_edition_lock: function (treasurehuntid, lockid, renewtime) {
            repeat = setInterval(this.renewLockAjax, renewtime, treasurehuntid, lockid);
        },
        stoprenew_edition_lock: function () {
            clearInterval(repeat);
        },
        getlockid: function () {
            return lockidTreasure;
        }

    };
    return renewLock;
});