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
 * QR scanner integration using vue-qrcode-reader.
 *
 * @package
 * @copyright 2018-2025 Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @author Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/notification', 'core/str'], function ($, notification, str) {
    'use strict';

    var camera = -1;
    var detectedCameras = null;
    var vueApp = null;
    var scannerapp = null;
    //var currentStream = null;

    /**
     * Check if Vue and vue-qrcode-reader are available
     */
    function isVueQRAvailable() {
        return (typeof window !== 'undefined') &&
               window.Vue &&
               window.VueQrcodeReader;
    }
    /**
     * Helper to enumerate video input devices
     */
    function enumerateVideoInputs() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
            return Promise.resolve([]);
        }
        return navigator.mediaDevices.enumerateDevices()
            .then(function(devices) {
                return devices.filter(function(d) {
                    return d.kind === 'videoinput' && d.label.indexOf('OBS') == -1;
                }).map(function(d, i) {
                    return {
                        id: d.deviceId,
                        name: d.label || ('Camera ' + (i + 1))
                    };
                });
            })
            .catch(function() {
                return [];
            });
    }

/**
 * Load QR scanner using Vue.
 * @param {Function} scancallback - Callback to handle scanned QR content.
 * @param {Function} reportcallback - Callback to report camera status and errors.
 */
    function loadQRWithVue(scancallback, reportcallback) {
        var container = document.getElementById('previewQRdiv');
        if (!container) {
            console.error('previewQRvideo element not found for Vue QR mount');
            reportcallback({ code: 0, name: 'DomMissing' });
            return false;
        }
        var Vue = window.Vue;
        var VueQrcodeReader = window.VueQrcodeReader;
        const wasmFile = './js/zxing_reader.wasm';
        VueQrcodeReader.setZXingModuleOverrides({
            locateFile: () => {
                return wasmFile;
            },
            instantiateWasm: (imports, successCallback) => {
                fetch(wasmFile, {
                    mode: 'no-cors',
                    credentials: 'omit',
                })
                    .then((response) => {
                    console.log('response', response);
                    if (!response.ok && response.type !== 'opaque') {
                        throw new Error(`Failed to fetch wasm: ${response.statusText || 'CORS error'}`);
                    }
                    return response.arrayBuffer();
                    })
                    .then((buffer) => WebAssembly.instantiate(buffer, imports))
                    .then((output) => {
                    console.log('WASM loaded');
                    successCallback(output.instance);
                    })
                    .catch((error) => {
                    console.error('WASM error:', error);
                    });
                return {};
                },
        });

        // Create Vue app
        scannerapp = Vue.createApp({
            data: function() {
                return {
                    devices: [],
                    selectedDevice: null,
                    currentCameraIndex: 0,
                    isLoading: true,
                    error: null
                };
            },
            template: `
                <div style="position: relative; width: 100%; height: 100%;">
                    <qrcode-stream
                        v-if="!error && selectedDevice"
                        :constraints="{ deviceId: selectedDevice.id }"
                        @detect="onDecode"
                        @init="onInit"
                        @error="onError"
                        :track="paintCenterText"
                        style="width: 100%; height: 100%;"
                    />
                    <div v-if="isLoading"
style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
                        Loading camera...
                    </div>
<div v-if="error" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: red;">
                        {{ error }}
                    </div>
                </div>
            `,
            mounted: function() {
                this.initCameras();
            },
            methods: {
                initCameras: function() {
                    var self = this;
                    enumerateVideoInputs()
                        .then(function(devices) {
                            // Add default vue-qrcode.reader cameras.
                            // devices.splice(0, 0, { id: 'environment', name: 'Environment Camera' });
                            // devices.splice(1, 0, { id: 'user', name: 'User Camera' });
                            self.devices = devices;

                            detectedCameras = devices;
                            if (devices.length > 0) {
                                // Try to find back camera first
                                var backCameraIndex = -1;
                                for (var i = 0; i < devices.length; i++) {
                                    if (devices[i].name && devices[i].name.toLowerCase().indexOf('back') !== -1) {
                                        backCameraIndex = i;
                                        break;
                                    }
                                }
                                var initialIndex = backCameraIndex !== -1 ? backCameraIndex : 0;

                                self.selectedDevice = devices[initialIndex];
                                self.currentCameraIndex = initialIndex;
                                camera = initialIndex;
                                self.isLoading = false;
                                reportcallback({
                                    camera: initialIndex,
                                    cameras: devices
                                });
                            } else {
                                self.error = 'No cameras found';
                                reportcallback({ code: 0, name: 'NoCameras' });
                            }
                        })
                        .catch(function(err) {
                            self.error = 'Failed to enumerate cameras';
                            console.error('Camera enumeration error:', err);
                            reportcallback(err);
                        });
                },
                onDecode: function(content) {
                    scancallback(content[0].rawValue);
                },
                onInit: function(promise) {
                    var self = this;
                    promise
                        .then(function() {
                            self.isLoading = false;
                            self.error = null;
                        })
                        .catch(function(error) {
                            self.isLoading = false;
                            if (error.name === 'NotAllowedError') {
                                self.error = 'Camera access denied';
                            } else if (error.name === 'NotFoundError') {
                                self.error = 'No camera found';
                            } else if (error.name === 'NotSupportedError') {
                                self.error = 'Camera not supported';
                            } else {
                                self.error = 'Camera error: ' + error.message;
                            }
                            reportcallback(error);
                        });
                },
                onError: function(err) {
                    this.error = 'Scanner error: ' + err.message;
                    reportcallback(err);
                },
                switchCamera: function(deviceIndex) {
                    if (this.devices[deviceIndex]) {
                        this.selectedDevice = this.devices[deviceIndex];
                        this.currentCameraIndex = deviceIndex;
                        camera = deviceIndex;
                        reportcallback({
                            camera: deviceIndex,
                            cameras: this.devices
                        });
                    }
                },
                paintBoundingBox: function(detectedCodes, ctx) {
                    for (const detectedCode of detectedCodes) {
                        const {
                            boundingBox: { x, y, width, height }
                        } = detectedCode;

                        ctx.lineWidth = 2;
                        ctx.strokeStyle = '#007bff';
                        ctx.strokeRect(x, y, width, height);
                        }
                },
                paintOutline: function(detectedCodes, ctx) {
                    for (const detectedCode of detectedCodes) {
                        const [firstPoint, ...otherPoints] = detectedCode.cornerPoints;
                        ctx.strokeStyle = 'red';

                        ctx.beginPath();
                        ctx.moveTo(firstPoint.x, firstPoint.y);
                        for (const { x, y } of otherPoints) {
                            ctx.lineTo(x, y);
                        }
                        ctx.lineTo(firstPoint.x, firstPoint.y);
                        ctx.closePath();
                        ctx.stroke();
                    }
                },
                paintCenterText: function(detectedCodes, ctx) {
                    this.paintOutline(detectedCodes, ctx);
                    for (const detectedCode of detectedCodes) {
                        const { boundingBox, rawValue } = detectedCode;

                        const centerX = boundingBox.x + boundingBox.width / 2;
                        const centerY = boundingBox.y + boundingBox.height / 2;

                        const fontSize = Math.max(12, (50 * boundingBox.width) / ctx.canvas.width);

                        ctx.font = `bold ${fontSize}px sans-serif`;
                        ctx.textAlign = 'center';

                        ctx.lineWidth = 3;
                        ctx.strokeStyle = '#35495e';
                        ctx.strokeText(detectedCode.rawValue, centerX, centerY);

                        ctx.fillStyle = '#5cb984';
                        ctx.fillText(rawValue, centerX, centerY);
                    }
                }
            },
            components: {
                'qrcode-stream': VueQrcodeReader.QrcodeStream
            }
        });

        try {
            vueApp = scannerapp.mount(container);
            console.debug('Loaded Vue QR app successfully');
            return true;
        } catch (e) {
            console.error('Failed to mount Vue QR component', e);
            container.innerHTML = '';
            return false;
        }
    }

    // webQR functions
    var webqr = {
        getDetectedCameras: function() {
            return detectedCameras;
        },

        setup: function () {
            // Check for Vue availability
            if (!isVueQRAvailable()) {
                console.warn('Vue or vue-qrcode-reader not available. QR scanning will not work.');
            }
        },

        enableTest: function (successString) {
            var cook = {};
            document.cookie.split(';').forEach(function (x) {
                var arr = x.split('=');
                if (arr[1]) {
                   cook[arr[0].trim()] = arr[1].trim();
                }
            });
            if (cook["QRScanPassed"] != 'Done') {
                var qr_callback = function (value) {
                    document.cookie = "QRScanPassed = Done";
                    this.unloadQR(function () {
                        $('#QRStatusDiv').html(successString + " - " + value);
                    });
                }.bind(this);
                $('#idbuttonnextcam').click(() => this.setnextwebcam(this.testFormReport.bind(this)));
                this.loadQR(qr_callback, this.testFormReport.bind(this));
            } else {
                $('#QRStatusDiv').html(successString);
            }
        },

        testFormReport: function (info) {
            if (typeof (info) === 'string') {
                $('#QRvalue').text(info);
                $('#previewQR').hide();
            } else if (typeof (info) === 'object') {
                if (info.name == 'NotAllowedError' || info.code == 0) {
                    notification.addNotification({
                        message: $('#errorQR').text(),
                        type: "error"
                    });
                    $('#previewQR').hide();
                    $('#QRStatusDiv').hide();
                } else {
                    this.updateVideoPreview();
                    let cam = info.camera;
                    if (info.cameras && info.cameras[cam] && info.cameras[cam].name !== null) {
                        $('#QRvalue').text(info.cameras[cam].name);
                    }
                    $('#previewQR').show();
                    let nextcamera = this.getnextwebCam();
                    if (nextcamera != cam) {
                        if (info.cameras[nextcamera] && info.cameras[nextcamera].name !== null) {
                            $('#idbuttonnextcam')
                                .text(nextcamera + ":" + info.cameras[nextcamera].name);
                        }
                        $('#idbuttonnextcam').show();
                    } else {
                        $('#idbuttonnextcam').hide();
                    }
                }
            }
        },

        enableEditForm: function () {
            $('#id_generateQR').click(this.handleGenerateQR.bind(this));
            $('#id_stopQR').click(this.handleStopQR.bind(this));
            $('#id_scanQR').click(this.handleScanEditStage.bind(this));
            $('#idbuttonnextcam').click(() => this.setnextwebcam(this.editFormReport.bind(this)));
        },

        handleGenerateQR: function () {
            var val = $('#id_qrtext').val();
            if (val != '') {
                var qrurl = 'https://quickchart.io/qr?size=500&text=' + encodeURIComponent(val);
                $('#outQRCode').text('');
                $('#outQRCode').prepend($('<img>', {
                    id: 'theQRImg',
                    src: qrurl,
                    width: '150px',
                    align: 'left',
                    title: val,
                }));
            } else {
                $('#QRStatusDiv').text("Enter text in QRText field.");
            }
            return false;
        },

        handleStopQR: function () {
            this.unloadQR();
            $('#QRvalue').text("");
            $('#previewQR').hide();
            $('#id_stopQR').hide();
            $('#id_scanQR').show();
            return false;
        },

        handleScanEditStage: function () {
            this.loadQR(function (value) {
                            $('#id_qrtext').val(value);
                        }.bind(this),
            this.editFormReport.bind(this));

            $('#previewQR').show();
            $('#previewQRdiv').show();
            $('#id_stopQR').show();
            $('#id_scanQR').hide();
            return false;
        },

        editFormReport: function (info) {
            if (typeof (info) === 'string') {
                $('#QRvalue').text(info);
                $('#id_stopQR').hide();
                $('#id_scanQR').show();
                $('#previewQRdiv').hide();
                $('#idbuttonnextcam').hide();
            } else if (typeof (info) === 'object') {
                if (info.code == 0) {
                    str.get_string('warnqrscannererror', 'treasurehunt');
                    $('#outQRCode').text('Camera access error!');
                } else {
                    let cam = info.camera;
                    $('#QRvalue').text(cam + ":" + info.cameras[cam].name);
                    let nextcamera = (cam + 1) % info.cameras.length;
                    if (nextcamera != cam) {
                        $('#idbuttonnextcam')
                            .text(nextcamera + ":" + info.cameras[nextcamera].name);
                        $('#idbuttonnextcam').show();
                    } else {
                        $('#idbuttonnextcam').hide();
                    }
                }
            }
        },

        updateVideoPreview: function() {
            // let videopreview = $('#previewQRvideo');
            // let parent = videopreview.closest('div');
            // let maxwidth = parent.width();
            // let maxheight = parent.height();

            // let width = videopreview.width();
            // let height = videopreview.height();
            // if (width / height > maxwidth / maxheight) {
            //     videopreview.width(maxwidth);
            // } else {
            //     videopreview.height(maxheight);
            // }
            // videopreview.css('display', 'block');
        },

        unloadQR: function (errorcallback) {
            // Vue cleanup
            if (scannerapp) {
                try {
                    scannerapp.unmount();
                } catch (e) {
                    console.warn('Error unmounting Vue QR app', e);
                }
                scannerapp = null;
            }
            console.debug('Vue QR app unloaded');

            // Reset state
            camera = -1;
            detectedCameras = null;

            let videopreview = $('#previewQR');
            videopreview.hide();

            if (typeof (errorcallback) == 'function') {
                errorcallback("");
            }
        },

        loadQR: function (scancallback, reportcallback) {
            if (!isVueQRAvailable()) {
                console.error('Vue or vue-qrcode-reader not available');
                reportcallback({
                    code: 0,
                    name: 'VueNotAvailable',
                    message: 'Vue or vue-qrcode-reader library not loaded'
                });
                return;
            }

            if (!loadQRWithVue(scancallback, reportcallback)) {
                reportcallback({
                    code: 0,
                    name: 'VueMountFailed',
                    message: 'Failed to mount Vue QR component'
                });
            }
        },

        getnextwebCam: function () {
            return detectedCameras === null || camera === -1 ? 0 : (camera + 1) % detectedCameras.length;
        },

        getbackCam: function () {
            var nextcamera = this.getnextwebCam();
            if (detectedCameras !== null && detectedCameras.length > 1) {
                for (var i = 0; i < detectedCameras.length; i++) {
                    if (detectedCameras[i].name !== null
                        && detectedCameras[i].name.toLowerCase().indexOf('back') != -1) {
                        nextcamera = i;
                    }
                }
            }
            return nextcamera;
        },

        setnextwebcam: function (reportcallback) {
            let nextcamera = this.getnextwebCam();
            if (camera != nextcamera) {
                if (detectedCameras !== null) {
                    try {
                        this.selectCamera(detectedCameras, nextcamera, reportcallback);
                    } catch (e) {
                        console.error(e);
                        reportcallback(e.message);
                    }
                } else {
                    enumerateVideoInputs().then(function (cameras) {
                        detectedCameras = cameras;
                        try {
                            webqr.selectCamera(cameras, nextcamera, reportcallback);
                        } catch (e) {
                            console.error(e);
                            reportcallback(e.message);
                        }
                    }).catch(function(e) {
                        console.error(e);
                        reportcallback(e.message);
                    });
                }
            }
        },

        selectCamera: function (cameras, nextcamera, reportcallback) {
            if (cameras.length > 0) {
                // Try to select back camera by name if starting fresh
                if (camera == -1 && cameras.length > 1) {
                    for (var i = 0; i < cameras.length; i++) {
                        if (cameras[i].name !== null
                            && cameras[i].name.toLowerCase().indexOf('back') != -1) {
                            nextcamera = i;
                        }
                    }
                }
                camera = nextcamera;

                // Switch camera using Vue component
                if (vueApp && vueApp.switchCamera) {
                    vueApp.switchCamera(nextcamera);
                } else {
                    console.warn('Vue app or switchCamera method not available');
                    reportcallback("Error: Vue component not properly initialized");
                }
            } else {
                console.error('No cameras found.');
                reportcallback("No cameras found.");
            }
        }
    };

    return webqr;
});




