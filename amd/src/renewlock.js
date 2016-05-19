/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

define(['jquery', 'core/notification', 'core/ajax'], function ($, notification, ajax) {

    var repeat;
    var idLockTreasure;
    var renewLock = {
        renewLockAjax: function (treasurehuntid, idLock) {
            idLockTreasure = idLock;
            var json = ajax.call([{
                    methodname: 'mod_treasurehunt_renew_lock',
                    args: {
                        treasurehuntid: treasurehuntid,
                        idLock: idLock
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
        renew_edition_lock: function (treasurehuntid, idLock) {
            repeat = setInterval(this.renewLockAjax, 90000, treasurehuntid, idLock);
        },
        stoprenew_edition_lock: function () {
            clearInterval(repeat);
        },
        getIdLock: function () {
            return idLockTreasure;
        }

    };
    return renewLock;
});