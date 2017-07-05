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
						}
					});
	$('#id_scanQR').click(function() {
		loadQR(function(value) {
			$('#id_qrtext').val(value);
			unloadQR();
		})
	});
}

function initCanvas(w,h)
{
    gCanvas = document.getElementById("qr-canvas");
    gCanvas.style.width = w + "px";
    gCanvas.style.height = h + "px";
    gCanvas.width = w;
    gCanvas.height = h;
    gCtx = gCanvas.getContext("2d");
    gCtx.clearRect(0, 0, w, h);
}


function captureToCanvas() {
    if(stype!=1)
        return;
    if(gUM)
    {
        try{
            gCtx.drawImage(v,0,0);
            try{
                qrcode.decode();
            }
            catch(e){       
                //console.log(e);
                setTimeout(captureToCanvas, 500);
            };
        }
        catch(e){       
                //console.log(e);
                setTimeout(captureToCanvas, 500);
        };
    }
}



function isCanvasSupported(){
  var elem = document.createElement('canvas');
  return !!(elem.getContext && elem.getContext('2d'));
}

function cameraStop(){
	if (typeof(QRvideostream)!='undefined'){
		var tracks = QRvideostream.getTracks();
		tracks.forEach(function(track){
			track.stop();
		});
	}
	gUM=false;
}
function cameraStart(stream) {
	QRvideostream = stream;
    if(webkit)
        v.src = window.URL.createObjectURL(stream);
    else
    if(moz)
    {
        v.mozSrcObject = stream;
        v.play();
    }
    else
        v.src = stream;
    gUM=true;
    setTimeout(captureToCanvas, 500);
}
		
function error(error) {
    gUM=false;
    return;
}
function unloadQR(){
	cameraStop();
	document.getElementById("outdiv").innerHTML="";
	stype=0;
}
function loadQR(callback)
{
	stype=0;
	if(isCanvasSupported() && window.File && window.FileReader)
	{
		initCanvas(800, 600);
		qrcode.callback = callback;
		document.getElementById("previewQR").style.display="inline";
        setwebcam();
	}
	else
	{
		document.getElementById("previewQR").style.display="inline";
		document.getElementById("previewQR").innerHTML='<p id="mp1">QR code scanner for HTML5 capable browsers</p><br>'+
        '<br><p id="mp2">sorry your browser is not supported</p><br><br>'+
        '<p id="mp1">try <a href="http://www.mozilla.com/firefox"><img src="firefox.png"/></a> or <a href="http://chrome.google.com"><img src="chrome_logo.gif"/></a> or <a href="http://www.opera.com"><img src="Opera-logo.png"/></a></p>';
	}
}

function setwebcam()
{
	
	var options = true;
	if(navigator.mediaDevices && navigator.mediaDevices.enumerateDevices)
	{
		try{
			navigator.mediaDevices.enumerateDevices()
			.then(function(devices) {
			  devices.forEach(function(device) {
				if (device.kind === 'videoinput') {
				  if(device.label.toLowerCase().search("back") >-1)
					options={'deviceId': {'exact':device.deviceId}, 'facingMode':'environment'} ;
				}
				console.log(device.kind + ": " + device.label +" id = " + device.deviceId);
//				alert(device.kind + ": " + device.label +" id = " + device.deviceId);
			  });
			  setwebcam2(options);
			});
		}
		catch(e)
		{
			console.log(e);
		}
	}
	else{
		console.log("no navigator.mediaDevices.enumerateDevices" );
		setwebcam2(options);
	}
	
}

function setwebcam2(options)
{
	console.log(options);
    if(stype==1)
    {
        setTimeout(captureToCanvas, 500);    
        return;
    }
    var n=navigator;
    document.getElementById("outdiv").innerHTML = vidhtml;
    v=document.getElementById("qr_viewport");


    if(n.getUserMedia)
	{
		webkit=true;
        n.getUserMedia({video: options, audio: false}, cameraStart, error);
	}
    else
    if(n.webkitGetUserMedia)
    {
        webkit=true;
        n.webkitGetUserMedia({video:options, audio: false}, cameraStart, error);
    }
    else
    if(n.mozGetUserMedia)
    {
        moz=true;
        n.mozGetUserMedia({video: options, audio: false}, cameraStart, error);
    }
    stype=1;
    setTimeout(captureToCanvas, 500);
}

