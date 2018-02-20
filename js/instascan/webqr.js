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
		});
     } else {
    	$('#QRStatusDiv').html(successString);
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
							$('#outdiv').prepend($('<img>', {
								id : 'theQRImg',
								src : qrurl,
								width : '150px',
								align : 'left'
							}))
						} else {
							$('#QRStatusDiv').text("Enter text in QRText field.");
						}
						return false;
					});
	$('#id_stopQR').click(function(){
						unloadQR();
						$('#id_stopQR').hide();
						$('#id_scanQR').show();
						return false;});
	$('#id_scanQR').click(function() {
		loadQR(function(value) {
				$('#id_qrtext').val(value);
				unloadQR();
				$('#QRStatusDiv').text("");
				$('#id_stopQR').hide();
				$('#id_scanQR').show();
		}, function(msg){
			$('#QRStatusDiv').text("<p>" + msg + "</p>");
		})
		$('#id_stopQR').show();
		$('#id_scanQR').hide();
		return false;
	});
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
function loadQR(callback, errorcallback)
{
	let videopreview = $('#previewQRvideo');
    scanner = new Instascan.Scanner({ video: videopreview.get(0) , mirror: false});
	scanner.addListener('scan',callback);
	try {
		setnextwebcam(errorcallback);
	} catch (e){
		errorcallback(e);
	};
}

var camera = -1;
var numcameras = 0;
function setnextwebcam(errorcallback)
{
	var nextcamera = -1;
	if (numcameras > camera +1) {
		nextcamera++;
	} else {
		nextcamera = 0;
	}
	if (camera != nextcamera) {
		scanner.stop().then(function () {
			Instascan.Camera.getCameras().then(function (cameras) {
				numcameras = cameras.length;
		        if (cameras.length > 0) {
		          camera = nextcamera;
		          scanner.start(cameras[camera]).then(function() {
		        	  
		          let videopreview = $('#previewQRvideo');
		          let maxwidth = videopreview.parent().width();
		          let maxheight = videopreview.parent().height();
		          videopreview.width(maxwidth - 10);//.height(maxheight - 10);
		      	  videopreview.css('display','inline-block');
		          });
		        } else {
		          console.error('No cameras found.');
		          errorcallback("No cameras found.");
		        }
		      }).catch(function (e) {
		          console.error(e);
		          errorcallback(e.message);
		      });	
		});
	}
		
}

