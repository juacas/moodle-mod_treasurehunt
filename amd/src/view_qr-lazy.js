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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Load and start the QR test when the activity page needs it.
 *
 * @module mod_treasurehunt/view_qr-lazy
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/notification', 'require'], function($, notification, require) {
    'use strict';

    /**
     * Load a scanner dependency once.
     * @param {string} src Script URL.
     * @returns {Promise<void>} Resolves when the script is ready.
     */
    function loadScript(src) {
        return new Promise(function(resolve, reject) {
            var script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            script.onerror = function() {
                script.remove();
                reject(new Error('QR dependency failed to load'));
            };
            document.head.appendChild(script);
        });
    }

    /**
     * Load the QR reader as an AMD module. Its UMD bundle selects AMD when RequireJS is present.
     * @param {string} src Script URL.
     * @returns {Promise<void>} Resolves when the reader is ready.
     */
    function loadReader(src) {
        return new Promise(function(resolve, reject) {
            if (window.VueQrcodeReader) {
                resolve();
                return;
            }
            if (!require.defined('vue')) {
                window.define('vue', [], function() {
                    return window.Vue;
                });
            }
            require([src], function(reader) {
                window.VueQrcodeReader = reader;
                resolve();
            }, reject);
        });
    }

    /**
     * Open the test panel and load its scanner for automatic or manual tests.
     * @param {string} vueurl URL of Vue.
     * @param {string} readerurl URL of the QR reader.
     * @param {string} errormessage Localised load error.
     */
    function init(vueurl, readerurl, errormessage) {
        var card = $('#treasurehunt-qr-status');
        var panel = $('#QRStatusDiv');
        var loading = false;
        card.on('click', function() {
            if (loading || card.hasClass('is-active')) {
                return;
            }
            panel.prop('hidden', false).show();
            card.attr('aria-expanded', 'true');
            loading = true;
            Promise.resolve()
                .then(function() {
                    return window.Vue ? null : loadScript(vueurl);
                })
                .then(function() {
                    return loadReader(readerurl);
                })
                .then(function() {
                    return new Promise(function(resolve, reject) {
                        require(['mod_treasurehunt/webqr'], resolve, reject);
                    });
                })
                .then(function(webqr) {
                    webqr.setTestStatus('pending');
                    return webqr.enableTest();
                })
                .catch(function(error) {
                    window.console.error('Treasure Hunt QR scanner failed to load', error);
                    var failed = card.attr('data-qr-failed-label');
                    var feature = card.find('.treasurehunt-stage-status-label').text();
                    card.removeClass('is-pending is-active').addClass('is-danger')
                        .attr({'data-treasurehunt-tooltip': errormessage, 'aria-expanded': 'false',
                            'aria-label': feature + ': ' + failed + '. ' + errormessage});
                    card.find('.treasurehunt-stage-status-detail').text(failed);
                    panel.prop('hidden', true).hide();
                    notification.addNotification({message: errormessage, type: 'error'});
                })
                .finally(function() {
                    loading = false;
                });
        });
        if (card.length && !card.hasClass('is-active')) {
            card.trigger('click');
        }
    }

    return {init: init};
});
