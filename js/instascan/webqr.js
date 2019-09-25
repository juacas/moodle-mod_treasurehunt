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
function enableTest3(Y, successString){
	$(document).ready(function () {
			enableTest2(Y, successString);
			});
}
function enableTest(Y,successString){
     var cook = {};
     document.cookie.split(';').forEach(function (x) {
         var arr = x.split('=');
         arr[1] && (cook[arr[0].trim()] = arr[1].trim());
     });
     if (cook["QRScanPassed"] != 'Done') {
		loadQR(function (value){
		document.cookie = "QRScanPassed = Done";
		unloadQR(function () {
			$('#QRStatusDiv').html(successString);	        			
			});
		}, testFormReport );
     } else {
    	$('#QRStatusDiv').html(successString);
     }
}
function testFormReport(info) {
	if (typeof(info) === 'string') {
		$('#QRvalue').text(info);
		$('#previewQR').hide();
	} else {

		      let videopreview = $('#previewQRvideo');
		      let parent = videopreview.closest('div');
		      let maxwidth = parent.width();
		      let maxheight = parent.height();

		      let width = videopreview.width();
		      let height = videopreview.height();
		      if (width/height > maxwidth/maxheight) {
		          videopreview.width(maxwidth);
		      } else {
		          videopreview.height(maxheight);
		      }
      		      videopreview.css('display','block');



		let camera = info.camera;
		
		if (info.cameras[camera].name !== null) {
			$('#QRvalue').text(info.cameras[camera].name);			
		}
		$('#previewQR').show();
		let nextcamera = getnextwebCam();
		if (nextcamera != camera) {
			if (info.cameras[nextcamera].name !== null) {
				$('#idbuttonnextcam').text(nextcamera + ":" + info.cameras[nextcamera].name);
			}
			$('#idbuttonnextcam').show();
		} else {
			$('#idbuttonnextcam').hide();
		}
	}
}
function enableForm() {
	$('#id_generateQR')
			.click(
					function() {
						unloadQR();
						var val = $('#id_qrtext').val();
						if (val != '') {
							var qrurl = 'https://chart.googleapis.com/chart?cht=qr&chs=500x500&chl='
									+ $('#id_qrtext').val();
							$('#outQRCode').prepend($('<img>', {
								id : 'theQRImg',
								src : qrurl,
								width : '150px',
								align : 'left',
								title : val,
							}))
						} else {
							$('#QRStatusDiv').text("Enter text in QRText field.");
						}
						return false;
					});
	$('#id_stopQR').click(function(){
						unloadQR();
						$('#QRvalue').text("");
						$('#previewQR').hide();
						$('#id_stopQR').hide();
						$('#id_scanQR').show();
						return false;});
	$('#id_scanQR').click(function() {
		loadQR(function(value) {
				$('#id_qrtext').val(value);
				unloadQR();
				$('#QRvalue').text("");
				$('#id_stopQR').hide();
				$('#id_scanQR').show();
				$('#previewQR').hide();
		}, editFormReport);
		$('#previewQR').show();
		$('#previewQRdiv').show();
		$('#id_stopQR').show();
		$('#id_scanQR').hide();
		return false;
	});
}
function editFormReport(info) {
	if (typeof(info) === 'string') {
		$('#QRvalue').text(info);
		$('#id_stopQR').hide();
		$('#id_scanQR').show();
		$('#previewQRdiv').hide();
		$('#idbuttonnextcam').hide();
	} else {	
		let camera = info.camera;
		$('#QRvalue').text(camera + ":" + info.cameras[camera].name);
		let nextcamera = (camera+1) % info.cameras.length;
		if (nextcamera != camera) {
			$('#idbuttonnextcam').text(nextcamera + ":" + info.cameras[nextcamera].name);
			$('#idbuttonnextcam').show();
		} else {
			$('#idbuttonnextcam').hide();
		}
	}
}	
function unloadQR(errorcallback){
	if (typeof(scanner) == 'undefined') {
		return;
	}
	scanner.stop().then(function(){
		console.info("camera stopped");
	});
	camera = -1;
	let videopreview = $('#previewQRvideo');
	videopreview.hide();
	if (typeof(errorcallback) == 'function') {
		errorcallback("");
	}
}
function loadQR(scancallback, reportcallback)
{
	let videopreview = $('#previewQRvideo');
	scanner = new Instascan.Scanner({ video: videopreview.get(0) , mirror: false});
	scanner.addListener('scan',scancallback);
  	Instascan.Camera.getCameras().then(function (cameras) {
		detectedCameras = cameras;
        	if (detectedCameras.length > 0) {
		   camera = 0; //getbackCam(); JPC: Some devices can't initialize first the back camera.
		   scanner.start(detectedCameras[camera])
 			.then(function() {
 	           		reportcallback({camera:camera, cameras: detectedCameras});			    
			})
			.catch(function(e) {
 	           		reportcallback(e);			    
			});
	        } else {
        	  console.error('No cameras found.');
       		 }
      }).catch(function (e) {
        console.error(e);
      });
}

camera = -1;
detectedCameras = null;

function getnextwebCam() {
	return detectedCameras === null || camera === -1 ? 0 : (camera+1) % detectedCameras.length;
}
function getbackCam() {
  var nextcamera = getnextwebCam();
 // Try to select back camera by name.
  if (detectedCameras !== null && detectedCameras.length > 1) {
          for(var i = 0; i < detectedCameras.length; i++) {
                  if (detectedCameras[i].name !== null
                          && detectedCameras[i].name.toLowerCase().indexOf('back') != -1) {
                          nextcamera = i;
                  }
          }
  }
  return nextcamera;
}
function setnextwebcam(reportcallback)
{
	let nextcamera = getnextwebCam();
	if (camera != nextcamera) {
		if (detectedCameras !== null) {
			try {
				selectCamera(detectedCameras, nextcamera, reportcallback);
			} catch (e) {
				console.error(e);
			        reportcallback(e.message);
				}
		} else {
			Instascan.Camera.getCameras().then(function (cameras) {
				selectCamera(cameras, nextcamera, reportcallback);
				}).catch(function (e) {
			          console.error(e);
		        	  reportcallback(e.message);
		      		});	
			}
	}
}
/**
 * TODO don't use global var camera
 * @param cameras
 * @returns
 */
function selectCamera(cameras, nextcamera, reportcallback) {
//	For testing camera switching use: cameras[1] = cameras[0];
if (cameras.length > 0) {
  // Try to select back camera by name.
  if (camera == -1 && cameras.length > 1) {
	  for(var i = 0; i < cameras.length; i++) {
		  if (cameras[i].name !== null
			  && cameras[i].name.toLowerCase().indexOf('back') != -1) {
			  nextcamera = i;
		  }
	  }
  }
  camera = nextcamera;

  scanner.start(cameras[camera]).then(function() {		        	  
      let videopreview = $('#previewQRvideo');
      let parent = videopreview.closest('div');
      let maxwidth = parent.width();
      let maxheight = parent.height();
      
      let width = videopreview.width();
      let height = videopreview.height();
      if (width/height > maxwidth/maxheight) {
    	  videopreview.width(maxwidth);
      } else {
    	  videopreview.height(maxheight);		        	  
      }
      videopreview.css('display','block');
  	  reportcallback({camera:camera, cameras: cameras});
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

