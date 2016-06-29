/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
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
                    debugger;
                    notification.alert('Error', response.status.msg, 'Continue');
                    renewLock.stoprenew_edition_lock();
                }
            }).fail(function (error) {
                console.log(error);
                notification.exception(error);
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