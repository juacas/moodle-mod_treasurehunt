// webqr file is part of Treasurehunt for Moodle
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
define([
  "jquery",
  "core/notification",
  "core/str",
  "mod_treasurehunt/instascan",
], function ($, notification, str, Instascan) {
  // webQR functions.
  let webqr = {
    // Public variables
    camera: -1,
    scanner: null,
    detectedCameras: null,
    setup: () => {},
    // Enable QR test procedure.
    enableTest: (successString) => {
      if (!localStorage.getItem("QRScanPassed")) {
        webqr.loadQR(() => {
          // Set the localstorage flag.
          localStorage.setItem("QRScanPassed", "Done");
          // Disable QR scanner.
          webqr.unloadQR(() => {
            $("#QRStatusDiv").html(successString);
          });
        }, webqr.testFormReport);
      } else {
        $("#QRStatusDiv").html(successString);
      }
    },
    testFormReport: (info) => {
      if (typeof info === "string") {
        $("#QRvalue").text(info);
        $("#previewQR").hide();
      } else if (typeof info === "object") {
        if (info.name == "NotAllowedError" || info.code == 0) {
          // Error with cam.
          notification.addNotification({
            message: $("#errorQR").text(),
            type: "error",
          });
          $("#previewQR").hide();
          $("#QRStatusDiv").hide();
        } else {
          // Camera ok.
          let videopreview = $("#previewQRvideo");
          let parent = videopreview.closest("div");
          let maxwidth = parent.width();
          let maxheight = parent.height();

          let width = videopreview.width();
          let height = videopreview.height();
          if (width / height > maxwidth / maxheight) {
            videopreview.width(maxwidth);
          } else {
            videopreview.height(maxheight);
          }
          videopreview.css("display", "block");
          let camera = info.camera;
          if (info.cameras[camera].name !== null) {
            $("#QRvalue").text(info.cameras[camera].name);
          }
          $("#previewQR").show();
          let nextcamera = webqr.getnextwebCam();
          if (nextcamera != camera) {
            if (info.cameras[nextcamera].name !== null) {
              $("#idbuttonnextcam").text(
                nextcamera + ":" + info.cameras[nextcamera].name
              );
            }
            $("#idbuttonnextcam").show();
          } else {
            $("#idbuttonnextcam").hide();
          }
        }
      }
    },
    enableForm: () => {
      $("#id_generateQR").click(webqr.handleGenerateQR);
      $("#id_stopQR").click(webqr.handleStopQR);
      $("#id_scanQR").click(webqr.handleScanEditStage);
    },
    handleGenerateQR: () => {
      // unloadQR();
      let val = $("#id_qrtext").val();
      if (val != "") {
        let qrurl =
          "https://chart.googleapis.com/chart?cht=qr&chs=500x500&chl=" +
          $("#id_qrtext").val();
        $("#outQRCode").text("");
        $("#outQRCode").prepend(
          $("<img>", {
            id: "theQRImg",
            src: qrurl,
            width: "150px",
            align: "left",
            title: val,
          })
        );
      } else {
        $("#QRStatusDiv").text("Enter text in QRText field.");
      }
      return false;
    },
    handleStopQR: () => {
      webqr.unloadQR();
      $("#QRvalue").text("");
      $("#previewQR").hide();
      $("#id_stopQR").hide();
      $("#id_scanQR").show();
      return false;
    },
    handleScanEditStage: () => {
      webqr.loadQR((value) => {
        $("#id_qrtext").val(value);
        webqr.unloadQR();
        $("#QRvalue").text("");
        $("#id_stopQR").hide();
        $("#id_scanQR").show();
        $("#previewQR").hide();
      }, webqr.editFormReport);
      $("#previewQR").show();
      $("#previewQRdiv").show();
      $("#id_stopQR").show();
      $("#id_scanQR").hide();
      return false;
    },
    editFormReport: (info) => {
      if (typeof info === "string") {
        $("#QRvalue").text(info);
        $("#id_stopQR").hide();
        $("#id_scanQR").show();
        $("#previewQRdiv").hide();
        $("#idbuttonnextcam").hide();
      } else if (typeof info === "object") {
        if (info.code == 0) {
          // Exception.
          str.get_string("warnqrscannererror", "treasurehunt");
          $("#outQRCode").text("Camera access error!");
        } else {
          // Camera ok.
          let camera = info.camera;
          $("#QRvalue").text(camera + ":" + info.cameras[camera].name);
          let nextcamera = (camera + 1) % info.cameras.length;
          if (nextcamera != camera) {
            $("#idbuttonnextcam").text(
              nextcamera + ":" + info.cameras[nextcamera].name
            );
            $("#idbuttonnextcam").show();
          } else {
            $("#idbuttonnextcam").hide();
          }
        }
      }
    },
    unloadQR: (errorcallback) => {
      if (typeof webqr.scanner == "undefined") {
        return;
      }
      webqr.scanner.stop();
      webqr.camera = -1;
      let videopreview = $("#previewQRvideo");
      videopreview.hide();
      if (typeof errorcallback == "function") {
        errorcallback("");
      }
    },
    loadQR: (scancallback, reportcallback) => {
      let videopreview = $("#previewQRvideo");
      videopreview.show();
      webqr.scanner = new Instascan.Scanner({
        video: videopreview.get(0),
        mirror: false,
      });
      webqr.scanner.addListener("scan", scancallback);
      Instascan.Camera.getCameras()
        .then((cameras) => {
          webqr.detectedCameras = cameras;
          if (webqr.detectedCameras.length > 0) {
            webqr.camera = 0; //getbackCam(); JPC: Some devices can't initialize first the back camera.
            webqr.scanner
              .start(webqr.detectedCameras[webqr.camera])
              .then(() => {
                reportcallback({
                  camera: webqr.camera,
                  cameras: webqr.detectedCameras,
                });
              })
              .catch((e) => {
                reportcallback(e);
              });
          }
        })
        .catch((e) => {
          reportcallback(e);
        });
    },
    getnextwebCam: () => {
      return webqr.detectedCameras === null || webqr.camera === -1
        ? 0
        : (webqr.camera + 1) % webqr.detectedCameras.length;
    },
    getbackCam: () => {
      let nextcamera = webqr.getnextwebCam();
      // Try to select back camera by name.
      if (webqr.detectedCameras !== null && webqr.detectedCameras.length > 1) {
        for (let i = 0; i < webqr.detectedCameras.length; i++) {
          if (
            webqr.detectedCameras[i].name !== null &&
            webqr.detectedCameras[i].name.toLowerCase().indexOf("back") != -1
          ) {
            nextcamera = i;
          }
        }
      }
      return nextcamera;
    },
    setnextwebcam: (reportcallback) => {
      let nextcamera = webqr.getnextwebCam();
      if (webqr.camera != nextcamera) {
        if (webqr.detectedCameras !== null) {
          try {
            webqr.selectCamera(
              webqr.detectedCameras,
              nextcamera,
              reportcallback
            );
          } catch (e) {
            reportcallback(e.message);
          }
        } else {
          Instascan.Camera.getCameras()
            .then((cameras) => {
              webqr.selectCamera(cameras, nextcamera, reportcallback);
            })
            .catch((e) => {
              reportcallback(e.message);
            });
        }
      }
    },
    selectCamera: (cameras, nextcamera, reportcallback) => {
      // For testing camera switching use: cameras[1] = cameras[0];
      if (cameras.length > 0) {
        // Try to select back camera by name.
        if (webqr.camera == -1 && cameras.length > 1) {
          for (let i = 0; i < cameras.length; i++) {
            if (
              cameras[i].name !== null &&
              cameras[i].name.toLowerCase().indexOf("back") != -1
            ) {
              nextcamera = i;
            }
          }
        }
        webqr.camera = nextcamera;

        webqr.scanner
          .start(cameras[webqr.camera])
          .then(() => {
            let videopreview = $("#previewQRvideo");
            let parent = videopreview.closest("div");
            let maxwidth = parent.width();
            let maxheight = parent.height();

            let width = videopreview.width();
            let height = videopreview.height();
            if (width / height > maxwidth / maxheight) {
              videopreview.width(maxwidth);
            } else {
              videopreview.height(maxheight);
            }
            videopreview.css("display", "block");
            reportcallback({ camera: webqr.camera, cameras: cameras });
          })
          .catch((e) => {
            webqr.scanner.stop();
            reportcallback("Error activating camera: " + e.message);
          });
      } else {
        reportcallback("No cameras found.");
      }
    },
  };
  return webqr;
});
