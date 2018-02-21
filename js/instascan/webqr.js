// QRCODE reader Copyright 2011 Lazar Laszlo
// http://www.webqr.com

var gCtx = null;
var gCanvas = null;
var c=0;
var stype=0;
var gUM=false;
var webkit=false;
var moz=false;
var v=null;

var vidhtml = '<video width="320px" id="qr_viewport" autoplay></video>';
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
		let camera = info.camera;
		$('#QRvalue').text(camera + ":" + info.cameras[camera].name);
		$('#previewQR').show();
		let nextcamera = (camera+1) % info.cameras.length;
		if (nextcamera != camera) {
			$('#idbuttonnextcam').text(nextcamera + ":" + info.cameras[nextcamera].name);
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
function error(error) {
    gUM=false;
    return;
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
	try {
		setnextwebcam(reportcallback);
	} catch (e){
		reportcallback(e);
	};
}

var camera = -1;
var numcameras = 0;
function setnextwebcam(reportcallback)
{
	let nextcamera = camera == -1 ? 0 : (camera+1) % numcameras;
	if (camera != nextcamera) {
		scanner.stop().then(function () {
			Instascan.Camera.getCameras().then(function (cameras) {
//	For testing camera switching use: 	cameras[1] = cameras[0];
				numcameras = cameras.length;
		        if (cameras.length > 0) {
		          // Try to select back camera.
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
			          reportcallback(e.message);
		          });
		        } else {
		          console.error('No cameras found.');
		          reportcallback("No cameras found.");
		        }
		      }).catch(function (e) {
		          console.error(e);
		          reportcallback(e.message);
		      });	
		});
	}
		
}

