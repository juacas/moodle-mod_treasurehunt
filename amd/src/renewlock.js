/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

define(['jquery', 'core/notification', 'core/ajax'], function ($, notification, ajax) {

    var repeat;
    var idLockScavenger;
    var renewLock = {
        renewLockAjax: function (idScavengerhunt, idLock) {
            idLockScavenger = idLock;
            var json = ajax.call([{
                    methodname: 'mod_scavengerhunt_renew_lock',
                    args: {
                        idScavengerhunt: idScavengerhunt,
                        idLock: idLock
                    }
                }]);
            json[0].done(function (response) {
                console.log(response);
                if (response.status.code) {
                    debugger;
                    notification.alert('Error', response.status.msg, 'Continue');
                    renewLock.stopRenewLockScavengerhunt();
                }
            }).fail(function (error) {
                console.log(error);
                notification.exception(error);
            });
        },
        /**Renuevo de continuo el bloqueo de edicion **/
        renewLockScavengerhunt: function (idScavengerhunt, idLock) {
            repeat = setInterval(this.renewLockAjax, 90000, idScavengerhunt, idLock);
        },
        stopRenewLockScavengerhunt: function () {
            clearInterval(repeat);
        },
        getIdLock: function () {
            return idLockScavenger;
        }

    };
    return renewLock;
});