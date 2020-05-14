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
 * integrates teh QRSCanner.
 *
 * @package   mod_treasurehunt
 * @copyright 2018 Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @author Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/notification', 'core/str', 'mod_treasurehunt/instascan'], function ($, notification, str, Instascan) {
	var camera = -1;
	var detectedCameras = null;
	var scanner = null;
	// webQR functions.
	var webqr = {
		getDetectedCameras: () => detectedCameras,
		setup: function () {
		},
		// Enable QR test procedure.
		enableTest: function (successString) {
			var cook = {};
			document.cookie.split(';').forEach(function (x) {
				var arr = x.split('=');
				arr[1] && (cook[arr[0].trim()] = arr[1].trim());
			});
			if (cook["QRScanPassed"] != 'Done') {
				var qr_callback = function (value) {
					// Set the cookie flag.
					document.cookie = "QRScanPassed = Done";
					// Disable QR scanner.
					this.unloadQR(function () {
						$('#QRStatusDiv').html(successString);
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
				if (info.name == 'NotAllowedError' || info.code == 0) { // Error with cam.
					notification.addNotification({ message: $('#errorQR').text() , type: "error"});
					$('#previewQR').hide();
					$('#QRStatusDiv').hide();					
				} else { // Camera ok.	
					let videopreview = $('#previewQRvideo');
					let parent = videopreview.closest('div');
					let maxwidth = parent.width();
					let maxheight = parent.height();

					let width = videopreview.width();
					let height = videopreview.height();
					if (width / height > maxwidth / maxheight) {
						videopreview.width(maxwidth);
					} else {
						videopreview.height(maxheight);
					}
					videopreview.css('display', 'block');
					let camera = info.camera;
					if (info.cameras[camera].name !== null) {
						$('#QRvalue').text(info.cameras[camera].name);
					}
					$('#previewQR').show();
					let nextcamera = this.getnextwebCam();
					if (nextcamera != camera) {
						if (info.cameras[nextcamera].name !== null) {
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
			$('#id_scanQR').click( this.handleScanEditStage.bind(this));
			$('#idbuttonnextcam').click(() => this.setnextwebcam(this.editFormReport.bind(this)));
		},
		handleGenerateQR: function () {
			// unloadQR();
			var val = $('#id_qrtext').val();
			if (val != '') {
				var qrurl = 'https://chart.googleapis.com/chart?cht=qr&chs=500x500&chl='
					+ $('#id_qrtext').val();
				$('#outQRCode').text('');
				$('#outQRCode').prepend($('<img>', {
					id: 'theQRImg',
					src: qrurl,
					width: '150px',
					align: 'left',
					title: val,
				}))
			} else {
				$('#QRStatusDiv').text("Enter text in QRText field.");
			}
			return false;
		}.bind(this),
		handleStopQR: function () {
			this.unloadQR();
			$('#QRvalue').text("");
			$('#previewQR').hide();
			$('#id_stopQR').hide();
			$('#id_scanQR').show();
			return false;
		},
		handleScanEditStage: function handleScanEditStage() {
			this.loadQR(function (value) {
					$('#id_qrtext').val(value);
					this.unloadQR();
					$('#QRvalue').text("");
					$('#id_stopQR').hide();
					$('#id_scanQR').show();
					$('#previewQR').hide();
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
			} else if(typeof (info) === 'object') {
				if (info.code == 0) { // Exception.
					str.get_string('warnqrscannererror', 'treasurehunt');
					$('#outQRCode').text('Camera access error!');
				} else { // Camera ok.
					let camera = info.camera;
					$('#QRvalue').text(camera + ":" + info.cameras[camera].name);
					let nextcamera = (camera + 1) % info.cameras.length;
					if (nextcamera != camera) {
						
						$('#idbuttonnextcam')
							.text(nextcamera + ":" + info.cameras[nextcamera].name);
						$('#idbuttonnextcam').show();
					} else {
						$('#idbuttonnextcam').hide();
					}
				}

			}
		},
		unloadQR: function unloadQR(errorcallback) {
			if (typeof (scanner) == 'undefined') {
				return;
			}
			scanner.stop().then(function () {
				console.info("camera stopped");
			});
			camera = -1;
			let videopreview = $('#previewQRvideo');
			videopreview.hide();
			if (typeof (errorcallback) == 'function') {
				errorcallback("");
			}
		},
		loadQR: function (scancallback, reportcallback) {
			let videopreview = $('#previewQRvideo');
			videopreview.show();
			scanner = new Instascan.Scanner({ video: videopreview.get(0), mirror: false });
			scanner.addListener('scan', scancallback);
			Instascan.Camera.getCameras().then(function (cameras) {
				detectedCameras = cameras;
				if (detectedCameras.length > 0) {
					camera = 0; //getbackCam(); JPC: Some devices can't initialize first the back camera.
					scanner.start(detectedCameras[camera])
						.then(function () {
							reportcallback({ camera: camera, cameras: detectedCameras });
						})
						.catch(function (e) {
							reportcallback(e);
						});
				} else {
					console.error('No cameras found.');
				}
			}).catch(function (e) {
				reportcallback(e);
			});
		},
		getnextwebCam: function () {
			return detectedCameras === null || camera === -1 ? 0 : (camera + 1) % detectedCameras.length;
		},
		getbackCam: function () {
			var nextcamera = this.getnextwebCam();
			// Try to select back camera by name.
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
					Instascan.Camera.getCameras().then(function (cameras) {
						this.selectCamera(cameras, nextcamera, reportcallback);
					}).catch(function (e) {
						console.error(e);
						reportcallback(e.message);
					});
				}
			}
		},
		selectCamera: function (cameras, nextcamera, reportcallback) {
					//	For testing camera switching use: cameras[1] = cameras[0];
					if (cameras.length > 0) {
				// Try to select back camera by name.
				if (camera == -1 && cameras.length > 1) {
					for (var i = 0; i < cameras.length; i++) {
						if (cameras[i].name !== null
							&& cameras[i].name.toLowerCase().indexOf('back') != -1) {
							nextcamera = i;
						}
					}
				}
				camera = nextcamera;

				scanner.start(cameras[camera]).then(function () {
					let videopreview = $('#previewQRvideo');
					let parent = videopreview.closest('div');
					let maxwidth = parent.width();
					let maxheight = parent.height();

					let width = videopreview.width();
					let height = videopreview.height();
					if (width / height > maxwidth / maxheight) {
						videopreview.width(maxwidth);
					} else {
						videopreview.height(maxheight);
					}
					videopreview.css('display', 'block');
					reportcallback({ camera: camera, cameras: cameras });
				}).catch(function (e) {
					scanner.stop();
					console.error(e);
					reportcallback("Error activating camera: " + e.message);
				});

			} else {
				console.error('No cameras found.');
				reportcallback("No cameras found.");
			}
		}
	}
	return webqr;
});




