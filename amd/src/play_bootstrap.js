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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @module    mod_treasurehunt/play
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @author Adrian Rodriguez <huorwhisp@gmail.com>
 * @author Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
  "jquery",
  "core/url",
  "mod_treasurehunt/ol",
  "core/ajax",
  "mod_treasurehunt/osm-geocoder",
  "mod_treasurehunt/viewgpx",
  "core/str",
  "mod_treasurehunt/webqr",
  "mod_treasurehunt/jquery.truncate",
  "mod_treasurehunt/dropdown",
  // "mod_treasurehunt/jquery.mobile-config",
  // "mod_treasurehunt/jquerymobile"
], function ($, url, ol, ajax, OSMGeocoder, viewgpx, str, webqr) {
  let init = {
    playtreasurehunt: function (
      cmid,
      treasurehuntid,
      playwithoutmoving,
      groupmode,
      lastattempttimestamp,
      lastroadtimestamp,
      gameupdatetime,
      tracking,
      user,
      custommapconfig
    ) {
      // I18n strings.
      let terms = [
        "stageovercome",
        "failedlocation",
        "stage",
        "stagename",
        "stageclue",
        "question",
        "noanswerselected",
        "timeexceeded",
        "searching",
        "continue",
        "noattempts",
        "aerialview",
        "roadview",
        "noresults",
        "startfromhere",
        "nomarks",
        "updates",
        "activitytoendwarning",
        "huntcompleted",
        "discoveredlocation",
        "answerwarning",
        "error",
        "pegmanlabel",
      ];
      // console.log("loading i18n strings");
      let stringsqueried = terms.map((term) => {
        return { key: term, component: "treasurehunt" };
      });
      // i18n = i18nplay; // Use globally passed strings. Moodle 3.8 core/str broke with jquery 2.1.4.
      str.get_strings(stringsqueried).done((strings) => {
        let i18n = [];
        for (let i = 0; i < terms.length; i++) {
          i18n[terms[i]] = strings[i]; // JPC: TODO: Global strings.
        }
        // i18n = i18nplay;
        // Detect custom image.
        if (
          typeof custommapconfig != "undefined" &&
          custommapconfig &&
          custommapconfig.custombackgroundurl
        ) {
          // console.log("Detecting custom background image dimensions.");
          // Detect image size.
          let img = new Image();
          img.addEventListener("load", function () {
            custommapconfig.imgwidth = this.naturalWidth;
            custommapconfig.imgheight = this.naturalHeight;
            // console.log(
            //   "image is " +
            //     this.naturalWidth +
            //     "x" +
            //     this.naturalHeight +
            //     "pixels"
            // );
            initplaytreasurehunt(
              i18n,
              cmid,
              treasurehuntid,
              playwithoutmoving,
              groupmode,
              lastattempttimestamp,
              lastroadtimestamp,
              gameupdatetime,
              tracking,
              user,
              custommapconfig
            );
          });
          img.src = custommapconfig.custombackgroundurl;
        } else {
          initplaytreasurehunt(
            i18n,
            cmid,
            treasurehuntid,
            playwithoutmoving,
            groupmode,
            lastattempttimestamp,
            lastroadtimestamp,
            gameupdatetime,
            tracking,
            user,
            custommapconfig
          );
        }
      });
    }, // End of function playtreasurehunt.
  };
  return init;
  // Initialization function.
  function initplaytreasurehunt(
    strings,
    cmid,
    treasurehuntid,
    playwithoutmoving,
    groupmode,
    lastattempttimestamp,
    lastroadtimestamp,
    gameupdatetime,
    tracking,
    user,
    custommapconfig
  ) {
    // I18n support.
    // console.log("init player Openlayers");
    // $.mobile.loading("show");
    setLoading(true);
    let mapprojection = "EPSG:3857";
    let custombaselayer = null;
    let geographictools = true;
    let defaultzoom = 15;
    let supportsTouch = "ontouchstart" in window || navigator.msMaxTouchPoints;

    // Support customized base layers.
    if (custommapconfig) {
      if (custommapconfig.custombackgroundurl) {
        // console.log("config custom background image");
        let customimageextent = ol.proj.transformExtent(
          custommapconfig.bbox,
          "EPSG:4326",
          mapprojection
        );
        if (!custommapconfig.geographic) {
          // Round bbox and scales to allow vectorial SVG rendering. (Maintain ratio.)
          let bboxheight = customimageextent[3] - customimageextent[1];
          let centerwidth = (customimageextent[2] + customimageextent[0]) / 2;
          let centerheight = (customimageextent[3] + customimageextent[1]) / 2;
          let ratiorealmap = Math.round(bboxheight / custommapconfig.imgheight);
          let adjwidth = Math.round(custommapconfig.imgwidth * ratiorealmap);
          let adjheight = Math.round(custommapconfig.imgheight * ratiorealmap);
          customimageextent = [
            centerwidth - adjwidth / 2,
            centerheight - adjheight / 2,
            centerwidth + adjwidth / 2,
            centerheight + adjheight / 2,
          ];
          defaultzoom = 5;
        }
        custombaselayer = new ol.layer.Image({
          title: custommapconfig.layername,
          name: custommapconfig.layername,
          type: custommapconfig.layertype,
          source: new ol.source.ImageStatic({
            url: custommapconfig.custombackgroundurl,
            imageExtent: customimageextent,
          }),
          opacity: 1.0,
        });
      } else if (custommapconfig.wmsurl) {
        // console.log("config custom wms server: " + custommapconfig.wmsurl);
        let options = {
          source: new ol.source.TileWMS({
            url: custommapconfig.wmsurl,
            params: custommapconfig.wmsparams,
          }),
          type: custommapconfig.layertype,
          title: custommapconfig.layername,
          name: custommapconfig.layername,
        };
        if (
          custommapconfig.bbox[0] &&
          custommapconfig.bbox[1] &&
          custommapconfig.bbox[2] &&
          custommapconfig.bbox[3]
        ) {
          let customwmsextent = ol.proj.transformExtent(
            custommapconfig.bbox,
            "EPSG:4326",
            mapprojection
          );
          options.extent = customwmsextent;
        }
        custombaselayer = new ol.layer.Tile(options);
      }

      geographictools = custommapconfig.geographic;
    }
    if (geographictools === false) {
      // console.log("geographic tools disabled");
      playwithoutmoving = true;
      $("#autolocate").hide();
    }

    let parchmenturl = url.imageUrl("success_mark", "treasurehunt");
    let failureurl = url.imageUrl("failure_mark", "treasurehunt");
    let markerurl = url.imageUrl("bootstrap/my_location_3", "treasurehunt");
    let lastsuccessfulstage = {};
    let interval;
    let infomsgs = [];
    let attemptshistory = [];
    let changesinattemptshistory = false;
    let changesinlastsuccessfulstage = false;
    let changesinquestionstage = false;
    let fitmap = false;
    let roadfinished = false;
    let available = true;
    let qoaremoved = false;
    let osmGeocoderXHR;
    let osmTimer = 0;
    /*-------------------------------Styles-----------------------------------*/
    let text = new ol.style.Text({
      textAlign: "center",
      scale: 1.3,
      fill: new ol.style.Fill({
        color: "#ffffff",
      }),
      stroke: new ol.style.Stroke({
        color: "#000000",
        width: 3.5,
      }),
    });
    let selectText = new ol.style.Text({
      textAlign: "center",
      scale: 1.4,
      fill: new ol.style.Fill({
        color: "#fff",
      }),
      stroke: new ol.style.Stroke({
        color: "#0097a7",
        width: 3.5,
      }),
    });
    let defaultstageStyle = new ol.style.Style({
      image: new ol.style.Icon({
        anchor: [0.5, 1],
        opacity: 1,
        scale: 0.5,
        src: parchmenturl,
      }),
      text: text,
      zIndex: "Infinity",
    });
    let failstageStyle = new ol.style.Style({
      image: new ol.style.Icon({
        anchor: [0.5, 1],
        opacity: 1,
        scale: 0.5,
        src: failureurl,
      }),
      text: text,
      zIndex: "Infinity",
    });
    let defaultSelectstageStyle = new ol.style.Style({
      image: new ol.style.Icon({
        anchor: [0.5, 1],
        opacity: 1,
        scale: 0.75,
        src: parchmenturl,
      }),
      text: selectText,
      zIndex: "Infinity",
    });
    let failSelectstageStyle = new ol.style.Style({
      image: new ol.style.Icon({
        anchor: [0.5, 1],
        opacity: 1,
        scale: 0.75,
        src: failureurl,
      }),
      text: selectText,
      zIndex: "Infinity",
    });
    let positionFeatureStyle = new ol.style.Style({
      image: new ol.style.Circle({
        radius: 6,
        fill: new ol.style.Fill({
          color: [0, 0, 0, 1],
        }),
        stroke: new ol.style.Stroke({
          color: [255, 255, 255, 1],
          width: 2,
        }),
      }),
    });
    let accuracyFeatureStyle = new ol.style.Style({
      fill: new ol.style.Fill({
        color: [255, 255, 255, 0.3],
      }),
      stroke: new ol.style.Stroke({
        color: [0, 0, 0, 0.5],
        width: 1,
      }),
      zIndex: -1,
    });
    let markerFeatureStyle = new ol.style.Style({
      image: new ol.style.Icon({
        anchor: [0.5, 0.9],
        opacity: 1,
        scale: 1,
        src: markerurl,
      }),
    });
    /*-------------------------------Layers-----------------------------------*/
    let layers = [];
    let geoJSONFormat = new ol.format.GeoJSON();
    let source = new ol.source.Vector({
      projection: "EPSG:3857",
    });
    let attemptslayer = new ol.layer.Vector({
      source: source,
      style: style_function,
    });
    let aeriallayer = new ol.layer.Tile({
      visible: false,
      source: new ol.source.BingMaps({
        key: "AmC3DXdnK5sXC_Yp_pOLqssFSaplBbvN68jnwKTEM3CSn2t6G5PGTbYN3wzxE5BR",
        imagerySet: "AerialWithLabels",
        maxZoom: 19,
        // Use maxZoom 19 to see stretched tiles instead of the BingMaps
        // "no photos at this zoom level" tiles
        // maxZoom: 19.
      }),
    });
    aeriallayer.set("name", strings["aerialview"]);
    let roadlayer = new ol.layer.Tile({
      source: new ol.source.OSM(),
    });
    roadlayer.set("name", strings["roadview"]);

    let layersbase = [];
    let layersoverlay = [];
    if (!custommapconfig || custommapconfig.onlybase === false) {
      layersbase = [aeriallayer, roadlayer];
    }
    if (custombaselayer) {
      if (custommapconfig.layertype != "overlay") {
        layersbase.push(custombaselayer);
      } else {
        layersoverlay.push(custombaselayer);
      }
    }
    let layergroup = new ol.layer.Group({ layers: layersbase });
    // Create placement for a popup over user marker.
    var overlay = viewgpx.createCoordsOverlay(
      "#mapplay",
      "css/playerbootstrap/ol-popup.css",
      strings["pegmanlabel"]
    );

    // All layers hidden except last one.
    let toplayer = null;
    layergroup.getLayers().forEach((layer) => {
      layer.setVisible(false);
      toplayer = layer;
    });
    toplayer.setVisible(true);

    let view = new ol.View({
      center: [0, 0],
      zoom: 2,
      minZoom: 2,
    });
    let select = new ol.interaction.Select({
      layers: [attemptslayer],
      style: select_style_function,
      filter: (feature) => {
        if (feature.get("stageposition") === 0) {
          return false;
        }
        return true;
      },
    });
    let accuracyFeature = new ol.Feature();
    accuracyFeature.setProperties({ name: "user_accuracy" });
    accuracyFeature.setStyle(accuracyFeatureStyle);
    let positionFeature = new ol.Feature();
    positionFeature.setProperties({ name: "user_position" });
    positionFeature.setStyle(positionFeatureStyle);
    let userPosition = new ol.layer.Vector({
      source: new ol.source.Vector({
        features: [accuracyFeature, positionFeature],
      }),
    });
    let markerFeature = new ol.Feature();
    markerFeature.setGeometry(null);
    markerFeature.setStyle(markerFeatureStyle);
    let markerVector = new ol.layer.Vector({
      source: new ol.source.Vector({
        features: [markerFeature],
      }),
    });
    layers.push(layergroup);
    layers = layers.concat(layersoverlay);
    layers = layers.concat([attemptslayer, userPosition, markerVector]);
    // New Custom zoom.
    let zoom = new ol.control.Zoom({
      target: "navigation",
      className: "custom-zoom",
    });
    let map = new ol.Map({
      layers: layers,
      overlays: [overlay],
      controls: [zoom], //ol.control.defaults({rotate: false, attribution: false}),
      target: "mapplay",
      view: view,
      loadTilesWhileAnimating: true,
      loadTilesWhileInteracting: true,
    });
    map.addInteraction(select);
    // It initializes the game.
    renew_source(false, true);
    // For the game is updated every gameupdatetime seconds.
    interval = setInterval(() => {
      renew_source(false, false);
    }, gameupdatetime);
    // Initialize the page layers.

    add_layergroup_to_list(layergroup);
    layersoverlay.forEach((overlay) => {
      add_layer_to_list(overlay);
    });
    if (tracking && user) {
      let tracklayergroup = viewgpx.addgpxlayer(
        map,
        cmid,
        treasurehuntid,
        strings,
        user,
        "trackgroup"
      );
      tracklayergroup.set("name", tracklayergroup.get("title"));

      let tracklayer = tracklayergroup.getLayers().item(0);
      let htmltitle = tracklayer.get("title"); // Has a picture and a link.
      tracklayer.set("name", htmltitle);
      tracklayer.setVisible(false);
      add_layer_to_list(tracklayer);
    }
    /*-------------------------------Functions-----------------------------------*/
    function style_function(feature) {
      // Get the income level from the feature properties.
      let stageposition = feature.get("stageposition");
      if (stageposition === 0) {
        let fill = new ol.style.Fill({
          color: "rgba(0,0,0,0.1)",
        });
        let stroke = new ol.style.Stroke({
          color: "#0097a7",
          width: 2,
        });
        let styles = new ol.style.Style({
          image: new ol.style.Circle({
            fill: fill,
            stroke: stroke,
            radius: 5,
          }),
          fill: fill,
          stroke: stroke,
          text: new ol.style.Text({
            text: strings["startfromhere"],
            textAlign: "center",
            fill: new ol.style.Fill({
              color: "rgb(255,255,255)",
            }),
            stroke: new ol.style.Stroke({
              color: "#0097a7",
              width: 2,
            }),
            overflow: true,
            scale: 2,
          }),
        });
        return [styles];
      }
      if (!feature.get("geometrysolved")) {
        // Don't change the scale with the map. This is confusing failstageStyle.getImage().setScale((view.getZoom() / 30));.
        failstageStyle.getText().setText("" + stageposition);
        return [failstageStyle];
      }
      // Don't change the scale with the map. This is confusing  defaultstageStyle.getImage().setScale((view.getZoom() / 100));.
      defaultstageStyle.getText().setText("" + stageposition);
      return [defaultstageStyle];
    }
    function select_style_function(feature) {
      let stageposition = feature.get("stageposition");
      if (!feature.get("geometrysolved")) {
        failSelectstageStyle.getText().setText("" + stageposition);
        return [failSelectstageStyle];
      }
      defaultSelectstageStyle.getText().setText("" + stageposition);
      return [defaultSelectstageStyle];
    }
    function validateposition() {
      if (playwithoutmoving && !markerFeature.getGeometry()) {
        toast(strings["nomarks"]);
      } else {
        renew_source(true, false);
      }
    }
    function autocentermap() {
      const position = geolocation.getPosition();
      fly_to(map, position);
    }
    function fly_to(map, point, extent) {
      let duration = 700;
      let view = map.getView();
      if (extent) {
        view.fit(extent, {
          duration: duration,
        });
      } else {
        view.animate({
          zoom: defaultzoom,
          center: point,
          duration: duration,
        });
      }
    }

    /**
     * Updates the model of the game.
     * Notifies a new location for validation or a new answer to a question.
     * @param {boolean} location requests a location validation.
     * @param {boolean} initialize
     * @param {int} selectedanswerid submits an answer to a question
     * @param {string} qrtext submits a text scanned from a QRCode
     * @returns {undefined}
     */
    function renew_source(location, initialize, selectedanswerid, qrtext) {
      // let position holds the potition to be evaluated. undef if no evaluation requested
      let position;
      let currentposition;
      let coordinates;
      let answerid;

      if (playwithoutmoving) {
        coordinates = markerFeature.getGeometry();
      } else {
        coordinates = positionFeature.getGeometry();
      }
      if (coordinates) {
        currentposition = geoJSONFormat.writeGeometryObject(coordinates, {
          dataProjection: "EPSG:4326",
          featureProjection: "EPSG:3857",
        });
      }
      if (selectedanswerid) {
        setLoading(true);
        answerid = selectedanswerid;
      }
      if (location) {
        position = currentposition;
        setLoading(true);
      }
      let geojson = ajax.call([
        {
          methodname: "mod_treasurehunt_user_progress",
          args: {
            userprogress: {
              treasurehuntid: treasurehuntid,
              attempttimestamp: lastattempttimestamp,
              roadtimestamp: lastroadtimestamp,
              playwithoutmoving: playwithoutmoving,
              groupmode: groupmode,
              initialize: initialize,
              location: position,
              currentposition:
                tracking && !playwithoutmoving ? currentposition : undefined, // only for tracking in mobility.
              selectedanswerid: answerid,
              qoaremoved: qoaremoved,
              qrtext: qrtext,
            },
          },
        },
      ]);
      geojson[0]
        .done((response) => {
          qoaremoved = response.qoaremoved;
          roadfinished = response.roadfinished;
          available = response.available;
          setLoading(false);

          // If I have sent a location or an answer I print out whether it is correct or not.
          if (location || selectedanswerid) {
            if (response.status !== null && available) {
              toast(response.status.msg);
            }
            markerFeature.setGeometry(null);
          }
          // If change the game mode (mobile or static).
          if (playwithoutmoving != response.playwithoutmoving) {
            playwithoutmoving = response.playwithoutmoving;
            if (!playwithoutmoving) {
              markerFeature.setGeometry(null);
            }
          }
          // If change the group mode.
          if (groupmode != response.groupmode) {
            groupmode = response.groupmode;
          }
          if (
            lastattempttimestamp !== response.attempttimestamp ||
            lastroadtimestamp !== response.roadtimestamp ||
            initialize ||
            !available
          ) {
            lastattempttimestamp = response.attempttimestamp;
            lastroadtimestamp = response.roadtimestamp;
            if (response.attempthistory.length > 0) {
              attemptshistory = response.attempthistory;
              changesinattemptshistory = true;
            }
            // Compruebo si es distinto de null, lo que indica que se ha actualizado.
            if (response.attempts || response.firststagegeom) {
              source.clear();
              if (response.firststagegeom) {
                source.addFeatures(
                  geoJSONFormat.readFeatures(response.firststagegeom, {
                    dataProjection: "EPSG:4326",
                    featureProjection: "EPSG:3857",
                  })
                );
              }
              if (response.attempts.features.length > 0) {
                source.addFeatures(
                  geoJSONFormat.readFeatures(response.attempts, {
                    dataProjection: "EPSG:4326",
                    featureProjection: "EPSG:3857",
                  })
                );
              }
            }
            // Check if it exists, which indicates that it has been updated.
            if (response.lastsuccessfulstage) {
              lastsuccessfulstage = response.lastsuccessfulstage;
              changesinlastsuccessfulstage = true;
              openModal("#cluepage");
              $("#validateqr").hide();
              // If the stage is not solved I will notify you that there are changes.
              if (lastsuccessfulstage.question !== "") {
                changesinquestionstage = true;
                $("#validatelocation").hide();
              } else if (!lastsuccessfulstage.activitysolved) {
                $("#validatelocation").hide();
              } else {
                $("#validatelocation").show();
                 if (response.qrexpected) {
                   $("#validateqr").show();
                 }
              }
            }
            // Check if it is the first geometry or it is being initialized and center the map.
            if (response.firststagegeom || initialize) {
              fitmap = true;
            }

            set_lastsuccessfulstage();
            fit_map_to_source();
            set_question();
            set_attempts_history();
          }
          if (response.infomsg.length > 0) {
            let body = "";
            infomsgs.forEach((msg) => {
              body += "<p>" + msg + "</p>";
            });
            response.infomsg.forEach((msg) => {
              infomsgs.push(msg);
              body += "<p>" + msg + "</p>";
            });
            $("#notificationsPopup .update-list").html(body);
            openModal("#notificationsPopup");
          }
          if (!roadfinished) {
            $("#roadended").hide();
          }
          if (roadfinished || !available) {
            $("#validatelocation").hide();
            $("#question_button").hide();
            $("#roadended").show();
            markerFeature.setGeometry(null);
            playwithoutmoving = false;
            clearInterval(interval);
            $("#mapplay").css("opacity", "0.8");
          }
        })
        .fail((error) => {
          $("#errorPopup .play-modal-content").text(error.message);
          openModal("#errorPopup");
          clearInterval(interval);
        });
    }
    function fit_map_to_source() {
      if (fitmap) {
        let features = source.getFeatures();
        if (
          features.length === 1 &&
          features[0].getGeometry() instanceof ol.geom.Point
        ) {
          fly_to(map, features[0].getGeometry().getCoordinates());
        } else {
          fly_to(map, null, source.getExtent());
        }

        fitmap = false;
      }
    }
    function set_lastsuccessfulstage() {
      if (changesinlastsuccessfulstage) {
        // Clean color styles from clue.
        lastsuccessfulstage.clue = lastsuccessfulstage.clue.replace(
          /color/gm,
          "color-disabled"
        );
        $("#lastsuccessfulstageclue").html(lastsuccessfulstage.clue);

        $("#lastsuccessfulstagename").text(lastsuccessfulstage.name);
        $("#lastsuccesfulstagepos").text(
          lastsuccessfulstage.position + " / " + lastsuccessfulstage.totalnumber
        );
        if (lastsuccessfulstage.question !== "") {
          $("#questionbutton").show();
        } else {
          $("#questionbutton").hide();
        }
        changesinlastsuccessfulstage = false;
      }
    }
    function set_question() {
      if (changesinquestionstage) {
        // Clean color tag.
        lastsuccessfulstage.question = lastsuccessfulstage.question.replace(
          /color/gm,
          "color-disabled"
        );

        $("#questionform").html(
          "<legend>" + lastsuccessfulstage.question + "</legend>"
        );
        let counter = 1;
        $.each(lastsuccessfulstage.answers, (key, answer) => {
          let id = "answer" + counter;
          // Clean color tag.
          answer.answertext = answer.answertext.replace(
            /color/gm,
            "color-disabled"
          );

          $("#questionform").append(
            `<div class="form-check">
                <input class="form-check-input" type="radio" name="answers" id="${id}" value="${answer.id}">
                <label class="form-check-label" for="${id}">
                  ${answer.answertext}
                </label>
            </div>`
          );
          counter++;
        });

        // $("#questionform")
        //   .html(questionform)
        //   .scrollTop();
        // // Enhance this with jquery mobile.
        // // JPC: It doesn't work in some cases (i.e. Moodle 3.7) probably some interaction with jquery, jqueryui and jquerymobile.
        // // When the radio controls are not correctly shown its better to show the native controls.
        // $("#questionform").enhanceWithin();
        // setTimeout(
        //   () =>
        //     $("#questionform input").removeClass("ui-helper-hidden-accessible"),
        //   1
        // ); //.controlgroup("refresh");
        changesinquestionstage = false;
      }
    }
    function set_attempts_history() {
      // I'm checking to see if there have been any changes since the last time.
      if (changesinattemptshistory) {
        let $historylist = $("#historylist"); // TODO: Why the variable starts with $?
        // Lo reinicio
        $historylist.html("");
        changesinattemptshistory = false;
        if (attemptshistory.length === 0) {
          $("<li>" + strings["noattempts"] + "</li>").appendTo($historylist);
        } else {
          // Anado cada intento
          attemptshistory.forEach((attempt) => {
            $(
              "<li><span class='ui-btn-icon-left " +
                (attempt.penalty
                  ? "ui-icon-delete failedattempt"
                  : "ui-icon-check successfulattempt") +
                "' style='position:relative'></span>" +
                attempt.string +
                "</li>"
            ).appendTo($historylist);
          });
        }
      }
    }
    function add_layer_to_list(layer) {
      let link = $("<button>", {
        type: "button",
        class: "list-group-item list-group-item-action close-modal",
      }).click(() => {
        layer.setVisible(!layer.getVisible());
      });
      let name = jQuery.parseHTML(layer.get("name"));
      let linkContent = $("<div>", {
        class: "layer-item " + (layer.getVisible() ? "" : "unchecked"),
      }).append(name, $("<i class='fa fa-check-circle'>"));
      link.append(linkContent);
      layer.on("change:visible", () => {
        $(linkContent).toggleClass("unchecked");
      });
      $("#layerslist").prepend(link);
    }

    function add_layergroup_to_list(layergroup) {
      layergroup.getLayers().forEach((layer) => {
        let link = $("<button>", {
          type: "button",
          class: "list-group-item list-group-item-action close-modal",
        }).click(() => {
          layergroup.getLayers().forEach((l) => {
            if (l === layer) {
              l.setVisible(true);
            } else {
              l.setVisible(false);
            }
          });
        });
        let linkContent = $("<div>", {
          text: layer.get("name"),
          class: "layer-item " + (layer.getVisible() ? "" : "unchecked"),
        }).append($("<i class='fa fa-check-circle'>"));
        link.append(linkContent);
        layer.on("change:visible", () => {
          $(linkContent).toggleClass("unchecked");
        });
        $("#layerslist").prepend(link);
      });
    }

    /**
     * Geolocation component
     * @type ol.Geolocation
     */
    let geolocation = new ol.Geolocation(
      /** @type {olx.GeolocationOptions} */ ({
        projection: view.getProjection(),
        trackingOptions: {
          enableHighAccuracy: true,
          maximumAge: 0,
          timeout: 10000,
        },
      })
    );
    /*-------------------------------Events-----------------------------------*/
    geolocation.on("change:position", () => {
      let coordinates = geolocation.getPosition();
      positionFeature.setGeometry(
        coordinates ? new ol.geom.Point(coordinates) : null
      );
      // The map must be re-centered in the new position
      if (geolocation.get("center")) {
        fly_to(map, coordinates);
        geolocation.setProperties({ center: false }); // Disable center request. Deprecated.
      }
      // the new position must be evaluated
      if (geolocation.get("validate_location")) {
        renew_source(true, false);
        geolocation.setProperties({ validate_location: false }); // Disable validate_location request. Deprecated.
      }
    });

    geolocation.on("change:accuracyGeometry", () => {
      accuracyFeature.setGeometry(geolocation.getAccuracyGeometry());
    });

    let trackinggeolocationwarndispatched = false;
    geolocation.on("error", (error) => {
      geolocation.setProperties({ user_denied: true });
      toast(error.message);
      if (
        error.code == error.PERMISSION_DENIED &&
        tracking &&
        !playwithoutmoving &&
        trackinggeolocationwarndispatched == false
      ) {
        setTimeout(() => {
          openModal("#geolocationPopup");
          trackinggeolocationwarndispatched = true;
        }, 500);
      }
    });
    geolocation.setTracking(tracking); // Start position tracking.

    // If it is not a touch screen, show pointer when hovering over an attempt
    if (!supportsTouch) {
      map.on("pointermove", (evt) => {
        if (evt.dragging) {
          return;
        }
        const pixel = map.getEventPixel(evt.originalEvent);
        let hit = false;
        map.forEachFeatureAtPixel(
          pixel,
          (feature) => {
            if (!(feature.getGeometry() instanceof ol.geom.MultiPolygon)) {
              hit = true;
            }
          },
          { layerFilter: (layer) => layer === attemptslayer }
        );
        if (hit) {
          map.getTargetElement().style.cursor = "pointer";
        } else {
          map.getTargetElement().style.cursor = "crosshair";
        }
      });
    }
    // On select location feature
    select.on("select", (features) => {
      if (features.selected.length === 1) {
        let stagename = features.selected[0].get("name");
        let stageclue = features.selected[0].get("clue");
        let info = features.selected[0].get("info");
        let body = "";
        let title = "";
        if (info) {
          body = "<p>" + info + "</p>";
        }
        if (features.selected[0].get("geometrysolved")) {
          if (stagename && stageclue) {
            title = strings["stageovercome"];
            body += get_block_text(strings["stagename"], stagename);
            body += get_block_text(strings["stageclue"], stageclue);
          } else {
            title = strings["discoveredlocation"];
          }
        } else {
          title = strings["failedlocation"];
        }
        $("#infoStagePopup .title").text(title);
        $("#infoStagePopup .play-modal-content").html(body);
        openModal("#infoStagePopup");
      }
    });

    // Change marker feature position on user click if play mode is play without moving
    map.on("click", (evt) => {
      // Check if has a feature on click event position.
      let hasFeature = false;
      map.forEachFeatureAtPixel(
        map.getEventPixel(evt.originalEvent),
        (feature) => {
          if (
            feature.get("stageposition") === 0 ||
            feature.get("name") === "user_position" ||
            feature.get("name") === "user_accuracy"
          ) {
            return false;
          }
          hasFeature = true;
        }
      );
      if (playwithoutmoving && !hasFeature) {
        let coordinates = map.getEventCoordinate(evt.originalEvent);
        markerFeature.setGeometry(
          coordinates ? new ol.geom.Point(coordinates) : null
        );
        // Shorcut to Google Street View.
        if (custommapconfig === null || custommapconfig.geographic) {
          overlay.setPosition(evt.coordinate);
        }
      }
    });

    $("#searchInput").on("input", (ev) => {
      // Abort xhr request if a new one arrives
      if (osmGeocoderXHR) {
        osmGeocoderXHR.abort();
      }
      if (osmTimer) {
        clearTimeout(osmTimer);
      }
      const $searchContainer = $("#searchsResults");
      const value = ev.target.value;
      let html = "";
      $searchContainer.html(html);
      if (value && value.length > 2) {
        $(".search-loading").addClass("active");
        osmTimer = setTimeout(() => {
          osmGeocoderXHR = OSMGeocoder.search(value)
            .done((resp) => {
              $(".search-loading").removeClass("active");
              if (resp.length === 0) {
                $searchContainer.html(
                  "<li class='list-group-item'>" +
                    strings["noresults"] +
                    "</li>"
                );
              } else {
                $.each(resp, (i, place) => {
                  let link = $("<button>", {
                    type: "button",
                    class: "list-group-item list-group-item-action",
                  })
                    .appendTo($searchContainer)
                    .click(() => {
                      let extent = [];
                      extent[0] = parseFloat(place.boundingbox[2]);
                      extent[1] = parseFloat(place.boundingbox[0]);
                      extent[2] = parseFloat(place.boundingbox[3]);
                      extent[3] = parseFloat(place.boundingbox[1]);
                      extent = ol.proj.transformExtent(
                        extent,
                        "EPSG:4326",
                        "EPSG:3857"
                      );
                      fly_to(map, null, extent);
                      closeSidePanel("#searchpanel");
                    });
                  let linkContent = $("<div>", {
                    text: place.display_name,
                    class: "search-option",
                  }).append($("<i class='fa fa-chevron-circle-right'>"));
                  link.append(linkContent);
                });
              }
            })
            .fail(() => {})
            .always(() => {
              osmGeocoderXHR = null;
            });
        }, 400);
      }
    });

    // Clear selected features after info stage popup close.
    $("#infoStagePopup").on("modal:close", () => {
      select.getFeatures().clear();
    });
    // Clear array of info messages after user accept updates
    $("#acceptupdates").on("click", () => {
      infomsgs = [];
    });
    // Load QR on open modal qrpage.
    $("#qrpage").on("modal:open", () => {
      webqr.loadQR(qrReaded, qrReport);
    });
    // Unload selected features after info stage popup close.
    $("#qrpage").on("modal:close", () => {
      webqr.unloadQR(qrReport);
    });

    $("#nextcamera").on("click", () => {
      let detectedCameras = webqr.getDetectedCameras();
      if (detectedCameras !== null) {
        const nextcam = webqr.getnextwebCam();
        toast("Give access to:" + detectedCameras[nextcam].name);
      }
      webqr.setnextwebcam(qrReport);
    });

    // Redraw map on resize window.
    $(window).on("resize", () => {
      map.updateSize();
    });

    //Buttons events.
    if (geographictools) {
      $("#autolocate").on("click", () => {
        if (geolocation.get("user_denied")) {
          openModal("#geolocationPopup");
        } else {
          autocentermap(true);
        }
      });
    }

    $("#infopanel").on("sidebar:close", () => {
      select.getFeatures().clear();
    });

    $("#sendLocation").on("click", () => {
      validateposition(true);
    });

    $("#sendAnswer").on("click", (event) => {
      // Selecciono la respuesta.
      let selected = $("#questionform input[type='radio']:checked");
      if (!available) {
        event.preventDefault();
        toast(strings["timeexceeded"]);
      } else {
        if (selected.length === 0) {
          event.preventDefault();
          toast(strings["noanswerselected"]);
        } else {
          renew_source(false, false, selected.val());
        }
      }
    });

    $("#validatelocation").on("click", (event) => {
      if (roadfinished) {
        event.preventDefault();
        toast(strings["huntcompleted"]);
        return;
      }
      if (!available) {
        event.preventDefault();
        toast(strings["timeexceeded"]);
        return;
      }
      if (lastsuccessfulstage.question !== "") {
        event.preventDefault();
        toast(strings["answerwarning"]);
        openModal("#cluepage");
        return;
      }
      if (!lastsuccessfulstage.activitysolved) {
        event.preventDefault();
        toast(strings["activitytoendwarning"]);
        openModal("#cluepage");
        return;
      }
    });

    /* Sidebar events */
    $(document).on("click", '*[data-rel="sidebar"]', (e) => {
      const target = e.currentTarget;
      const sidebar = $(target.dataset.ref);
      sidebar.trigger("sidebar:open");
      sidebar.addClass("active");
      if (target.dataset.dismissible !== null) {
        $(".sidebar-mask").addClass("active dismissible");
      }
    });

    $(document).on("click", ".close-sidebar, .sidebar-mask", () => {
      const sidebars = $(".sidebar.active");
      sidebars.trigger("sidebar:close");
      sidebars.removeClass("active");
      $(".sidebar-mask").removeClass("active dismissible");
    });

    /* Modal events */
    $(document).on("click", '*[data-rel="modal"]', (e) => {
      closeModal();
      const target = e.currentTarget;
      const modal = $(target.dataset.ref);
      modal.trigger("modal:open");
      modal.addClass("active");
      $(target.dataset.ref + " .modal-mask").addClass("active");
      if (target.dataset.dismissible !== null) {
        $(target.dataset.ref + " .modal-mask").addClass("dismissible");
      }
    });

    $(document).on("click", ".close-modal, .modal-mask.dismissible", () => {
      closeModal();
    });

    /*-------------------------------Help functions -------------*/
    function toast(msg) {
      const toast = $(`<div class='play-toast slide-in-bottom'>${msg}</div>`);
      toast.appendTo($(".play-toast-container"));
      // Out animation after 3s
      setTimeout(() => toast.addClass("slide-out-top"), 3000);
      // Remove element after 3,5s
      setTimeout(() => toast.remove(), 3500);
    }
    function openModal(id) {
      // Close previous modal
      closeModal();
      const modal = $(id);
      modal.trigger("modal:open");
      modal.addClass("active");
      $(`${id} .modal-mask`).addClass("active dismissible");
    }
    function closeModal() {
      const modal = $(".play-modal.active");
      modal.trigger("modal:close");
      modal.removeClass("active");
      $(".modal-mask").removeClass("active dismissible");
    }
    function get_block_text(title, body) {
      return `<div class="card">
          <div class="card-body">
            <h5 class="card-title">${title}</h5>
            <h6 class="card-subtitle text-muted">${body}</h6>
          </div>
      </div>`;
    }
    function openSidePanel(id) {
      $(id).addClass("active");
      $(".sidebar-mask").addClass("active dismissible");
    }
    function closeSidePanel(id) {
      $(id).removeClass("active");
      $(".sidebar-mask").removeClass("active dismissible");
    }
    function setLoading(loading) {
      if (loading) {
        $(".global-loader").addClass("active");
      } else {
        $(".global-loader").removeClass("active");
      }
    }
    // Scan QR.
    function qrReaded(value) {
      closeModal();
      toast("QR code readed: " + value);
      renew_source(false, false, null, value);
    }
    function qrReport(message) {
      if (typeof message == "string") {
        $("#errorQR").text(message);
      } else {
        if (message.cameras[message.camera].name !== null) {
          $("#errorQR").text(message.cameras[message.camera].name);
        }
        // hide/show next camera button.
        if (message.cameras.length > 1) {
          $("#nextcamera").show();
        } else {
          $("#nextcamera").hide();
        }
      }
    }
  }
});
