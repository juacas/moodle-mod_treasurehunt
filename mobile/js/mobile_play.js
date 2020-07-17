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
 * Register a link handler to open mod/treasurehunt/view.php links in the app
 *
 * @package    mod_treasurehunt
 * @copyright  2018 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class TreasureHuntPlayMobile {
  constructor(playConfig) {
    this.playConfig = playConfig;

    this.map = null;
    this.mapInteractions = {
      select: null,
    };
    this.mapSources = {
      totalLayers: [],
      baseLayers: [],
      attemptsLayer: null,
      positionFeature: null,
      accuracyFeature: null,
      markerFeature: null,
    };
    this.mapProperties = {
      defaultAnimationDuration: 700,
      maxAnimationZoom: 15,
      defaultProjection: "EPSG:3857",
    };
    this.gameStatus = {
      qoaremoved: false,
      roadFinished: false,
      available: true,
      attemptshistory: [],
      infoMsgs: [],
      renewSourceInterval: null,
      lastSuccessfulStage: {},
    };
    this.allEvents = [];
    this.geolocation = null;
    this.resources = {};
    this.loadingModal = null;
  }

  init(initialResources) {
    this.showLoading();

    this.resources = initialResources;
    this.initMap();

    // It initializes the game.
    this.renewSource(false, true);
    // The game is updated every gameupdatetime seconds.
    this.gameStatus.renewSourceInterval = setInterval(() => {
      this.renewSource(false, false);
    }, this.playConfig.gameupdatetime);
  }

  initMap() {
    this.initMapLayers();
    this.map = new ol.Map({
      target: "map",
      layers: this.mapSources.totalLayers,
      view: new ol.View({
        center: [0, 0],
        zoom: 2,
        minZoom: 2,
      }),
      controls: ol.control.defaults({
        attribution: false,
        zoom: false,
      }),
      loadTilesWhileAnimating: true,
      loadTilesWhileInteracting: true,
    });
    this.initMapInteractions();
    this.initMapEvents();

    // Required to draw the map after calculating container height
    setTimeout(() => {
      this.map.updateSize();
    }, 500);
  }

  initMapLayers() {
    // Attemtps layer style
    const defaultText = new ol.style.Text({
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

    const defaultStageStyle = new ol.style.Style({
      image: new ol.style.Icon({
        anchor: [0.5, 1],
        opacity: 1,
        scale: 0.5,
        src: this.resources.successMark,
      }),
      text: defaultText,
      zIndex: "Infinity",
    });

    const failStageStyle = new ol.style.Style({
      image: new ol.style.Icon({
        anchor: [0.5, 1],
        opacity: 1,
        scale: 0.5,
        src: this.resources.failureMark,
      }),
      text: defaultText,
      zIndex: "Infinity",
    });

    const fill = new ol.style.Fill({
      color: "rgba(0,0,0,0.1)",
    });

    const stroke = new ol.style.Stroke({
      color: "#0097a7",
      width: 2,
    });

    const startStageStyle = new ol.style.Style({
      image: new ol.style.Circle({
        fill: fill,
        stroke: stroke,
        radius: 5,
      }),
      fill: fill,
      stroke: stroke,
      text: new ol.style.Text({
        text: that.TranslateService.instant(
          "plugin.mod_treasurehunt.startfromhere"
        ),
        textAlign: "center",
        fill: new ol.style.Fill({
          color: "rgb(255,255,255)",
        }),
        stroke: new ol.style.Stroke({
          color: "#0097a7",
          width: 5,
        }),
      }),
    });

    // Attemps layer
    this.mapSources.attemptsLayer = new ol.layer.Vector({
      source: new ol.source.Vector({
        projection: this.mapProperties.defaultProjection,
      }),
      style: (feature) => {
        // Get the income level from the feature properties.
        const stageposition = feature.get("stageposition");
        if (stageposition === 0) {
          return startStageStyle;
        }

        if (!feature.get("geometrysolved")) {
          failStageStyle.getText().setText("" + stageposition);
          return failStageStyle;
        } else {
          defaultStageStyle.getText().setText("" + stageposition);
          return defaultStageStyle;
        }
      },
    });

    // Base Layers
    const aerialLayer = new ol.layer.Tile({
      visible: false,
      source: new ol.source.TileImage({
        url: "https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}",
      }),
    });
    aerialLayer.set("name", "plugin.mod_treasurehunt.aerialview");

    const roadLayer = new ol.layer.Tile({
      visible: true,
      source: new ol.source.OSM(),
    });
    roadLayer.set("name", "plugin.mod_treasurehunt.roadview");

    this.mapSources.baseLayers = [roadLayer, aerialLayer];

    // User position layer
    this.mapSources.accuracyFeature = new ol.Feature({
      name: "userAccuracy",
    });
    this.mapSources.accuracyFeature.setStyle(
      new ol.style.Style({
        fill: new ol.style.Fill({
          color: [255, 255, 255, 0.3],
        }),
        stroke: new ol.style.Stroke({
          color: [0, 0, 0, 0.5],
          width: 1,
        }),
        zIndex: -1,
      })
    );

    this.mapSources.positionFeature = new ol.Feature({
      name: "userPosition",
    });
    this.mapSources.positionFeature.setStyle(
      new ol.style.Style({
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
      })
    );

    const userPosition = new ol.layer.Vector({
      source: new ol.source.Vector({
        features: [
          this.mapSources.accuracyFeature,
          this.mapSources.positionFeature,
        ],
      }),
    });

    // Marker layer
    this.mapSources.markerFeature = new ol.Feature();
    this.mapSources.markerFeature.setStyle(
      new ol.style.Style({
        image: new ol.style.Icon({
          anchor: [0.5, 0.9],
          opacity: 1,
          scale: 1,
          src: this.resources.locationMark,
        }),
      })
    );

    const markerPosition = new ol.layer.Vector({
      source: new ol.source.Vector({
        features: [this.mapSources.markerFeature],
      }),
    });

    // Total Layers
    this.mapSources.totalLayers = [
      ...this.mapSources.baseLayers,
      this.mapSources.attemptsLayer,
      userPosition,
      markerPosition,
    ];
  }

  initMapInteractions() {
    // Select Styles
    const selectText = new ol.style.Text({
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

    const defaultSelectstageStyle = new ol.style.Style({
      image: new ol.style.Icon({
        anchor: [0.5, 1],
        opacity: 1,
        scale: 0.75,
        src: this.resources.successMark,
      }),
      text: selectText,
      zIndex: "Infinity",
    });

    const failSelectstageStyle = new ol.style.Style({
      image: new ol.style.Icon({
        anchor: [0.5, 1],
        opacity: 1,
        scale: 0.75,
        src: this.resources.failureMark,
      }),
      text: selectText,
      zIndex: "Infinity",
    });

    this.mapInteractions.select = new ol.interaction.Select({
      layers: [this.mapSources.attemptsLayer],
      style: (feature) => {
        let stageposition = feature.get("stageposition");
        if (!feature.get("geometrysolved")) {
          failSelectstageStyle.getText().setText("" + stageposition);
          return failSelectstageStyle;
        }
        defaultSelectstageStyle.getText().setText("" + stageposition);
        return defaultSelectstageStyle;
      },
      filter: (feature) => {
        if (feature.get("stageposition") === 0) {
          return false;
        }
        return true;
      },
    });

    this.map.addInteraction(this.mapInteractions.select);
  }

  initMapEvents() {
    window.onresize = () => {
      this.map.updateSize();
    };

    this.geolocation = new ol.Geolocation({
      projection: this.map.getView().getProjection(),
      tracking: !this.playConfig.playwithoutmoving,
      trackingOptions: {
        enableHighAccuracy: true,
        maximumAge: 0,
        timeout: 20000,
      },
    });

    this.allEvents.push(
      this.geolocation.on("change:position", () => {
        const coordinates = this.geolocation.getPosition();
        const point = coordinates ? new ol.geom.Point(coordinates) : null;

        this.mapSources.positionFeature.setGeometry(point);
        // The map must be re-centered in the new position
        if (this.geolocation.get("center")) {
          this.flyTo(point);
          this.geolocation.setProperties({ center: false }); // Disable center request. Deprecated.
        }
        // the new position must be evaluated
        if (this.geolocation.get("validate_location")) {
          this.renewSource(true, false);
          this.geolocation.setProperties({ validate_location: false }); // Disable validate_location request. Deprecated.
        }
      })
    );

    this.allEvents.push(
      this.geolocation.on("change:accuracyGeometry", () => {
        this.mapSources.accuracyFeature.setGeometry(
          this.geolocation.getAccuracyGeometry()
        );
      })
    );

    let trackinggeolocationwarndispatched = false;
    this.allEvents.push(
      this.geolocation.on("error", (error) => {
        this.geolocation.setProperties({ user_denied: true });
        that.CoreDomUtilsProvider.showToast(error.message, false);
        if (
          error.code == error.PERMISSION_DENIED &&
          this.playConfig.tracking &&
          !this.playConfig.playwithoutmoving &&
          trackinggeolocationwarndispatched == false
        ) {
          setTimeout(() => {
            $("#popupgeoloc").popup("open", { positionTo: "window" });
            trackinggeolocationwarndispatched = true;
          }, 500);
        }
      })
    );

    // On select location feature
    this.allEvents.push(
      this.mapInteractions.select.on("select", (features) => {
        if (features.selected.length === 1) {
          const stagename = features.selected[0].get("name");
          const stageclue = features.selected[0].get("clue");
          const info = features.selected[0].get("info");
          let body = "";
          let title = "";
          if (info) {
            body = "<p>" + info + "</p>";
          }
          if (features.selected[0].get("geometrysolved")) {
            if (stagename && stageclue) {
              title = that.TranslateService.instant(
                "plugin.mod_treasurehunt.stageovercome"
              );
              body += this.getCardTemplate(
                that.TranslateService.instant(
                  "plugin.mod_treasurehunt.stagename"
                ),
                stagename
              );
              body += this.getCardTemplate(
                that.TranslateService.instant(
                  "plugin.mod_treasurehunt.stageclue"
                ),
                stageclue
              );
            } else {
              title = that.TranslateService.instant(
                "plugin.mod_treasurehunt.discoveredlocation"
              );
            }
          } else {
            title = that.TranslateService.instant(
              "plugin.mod_treasurehunt.failedlocation"
            );
          }
          that.CoreDomUtilsProvider.showAlertWithOptions({
            title: title,
            message: body,
            buttons: [that.TranslateService.instant("core.ok")],
            cssClass: "treasurehunt-modal",
          }).then((alert) => {
            const subscription = alert.willDismiss.subscribe(() => {
              subscription.unsubscribe();
              this.mapInteractions.select.getFeatures().clear();
            });
          });
        }
      })
    );

    // Change marker feature position on user click if play mode is play without moving
    this.allEvents.push(
      this.map.on("click", (evt) => {
        // Check if has a feature on click event position.
        let hasFeature = false;
        this.map.forEachFeatureAtPixel(
          this.map.getEventPixel(evt.originalEvent),
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
        if (this.playConfig.playwithoutmoving && !hasFeature) {
          let coordinates = this.map.getEventCoordinate(evt.originalEvent);
          this.mapSources.markerFeature.setGeometry(
            coordinates ? new ol.geom.Point(coordinates) : null
          );
          // Shorcut to Google Street View.
          // if (custommapconfig === null || custommapconfig.geographic) {
          //   overlay.setPosition(evt.coordinate);
          // }
        }
      })
    );
  }

  /**
   * Updates the model of the game.
   * Notifies a new location for validation or a new answer to a question.
   * @param {boolean} location requests a location validation.
   * @param {boolean} initialize
   * @param {number} selectedanswerid submits an answer to a question
   * @param {string} qrtext submits a text scanned from a QRCode
   */
  renewSource(location, initialize, selectedanswerid, qrtext) {
    // let position holds the potition to be evaluated. undef if no evaluation requested
    let position;
    let currentposition;
    let coordinates;
    let answerid;
    let geoJSONFormat = new ol.format.GeoJSON({
      dataProjection: "EPSG:4326",
      featureProjection: this.mapProperties.defaultProjection,
    });

    if (this.playConfig.playwithoutmoving) {
      coordinates = this.mapSources.markerFeature.getGeometry();
    } else {
      coordinates = this.mapSources.positionFeature.getGeometry();
    }
    if (coordinates) {
      currentposition = geoJSONFormat.writeGeometryObject(coordinates);
    }
    if (selectedanswerid) {
      this.showLoading();
      answerid = selectedanswerid;
    }
    if (location) {
      position = currentposition;
      this.showLoading();
    }

    // Call WS
    that.CoreSitePluginsProvider.callWS(
      "mod_treasurehunt_user_progress",
      {
        userprogress: {
          treasurehuntid: this.playConfig.treasurehuntid,
          attempttimestamp: this.playConfig.lastattempttimestamp,
          roadtimestamp: this.playConfig.lastroadtimestamp,
          playwithoutmoving: this.playConfig.playwithoutmoving,
          groupmode: this.playConfig.groupmode,
          initialize: initialize,
          location: position,
          currentposition:
            this.playConfig.tracking && !this.playConfig.playwithoutmoving
              ? currentposition
              : undefined, // only for tracking in mobility.
          selectedanswerid: answerid,
          qoaremoved: this.gameStatus.qoaremoved,
          qrtext: qrtext,
        },
      },
      { getFromCache: false }
    )
      .then((response) => {
        this.gameStatus.qoaremoved = response.qoaremoved;
        this.gameStatus.roadfinished = response.roadfinished;
        this.gameStatus.available = response.available;
        this.hideLoading();

        // If I have sent a location or an answer I print out whether it is correct or not.
        if (location || selectedanswerid) {
          if (response.status !== null && response.available) {
            that.CoreDomUtilsProvider.showToast(response.status.msg, false);
          }
          this.mapSources.markerFeature.setGeometry(null);
        }
        if (response.qrexpected) {
          // $("#validateqr").show();
        } else {
          // $("#validateqr").hide();
        }
        // If change the game mode (mobile or static).
        if (this.playConfig.playwithoutmoving != response.playwithoutmoving) {
          this.playConfig.playwithoutmoving = response.playwithoutmoving;
          if (!this.playConfig.playwithoutmoving) {
            this.mapSources.markerFeature.setGeometry(null);
          }
        }
        // If change the group mode.
        if (this.playConfig.groupmode != response.groupmode) {
          this.playConfig.groupmode = response.groupmode;
        }
        if (
          this.playConfig.lastattempttimestamp !== response.attempttimestamp ||
          this.playConfig.lastroadtimestamp !== response.roadtimestamp ||
          initialize ||
          !response.available
        ) {
          this.playConfig.lastattempttimestamp = response.attempttimestamp;
          this.playConfig.lastroadtimestamp = response.roadtimestamp;
          if (response.attempthistory.length > 0) {
            this.gameStatus.attemptshistory = response.attempthistory;
          }
          // Compruebo si es distinto de null, lo que indica que se ha actualizado.
          if (response.attempts || response.firststagegeom) {
            const source = this.mapSources.attemptsLayer.getSource();
            source.clear();
            if (response.firststagegeom) {
              source.addFeatures(
                geoJSONFormat.readFeatures(response.firststagegeom)
              );
            }
            if (response.attempts.features.length > 0) {
              source.addFeatures(geoJSONFormat.readFeatures(response.attempts));
            }
          }
          // Check if it exists, which indicates that it has been updated.
          if (response.lastsuccessfulstage) {
            this.gameStatus.lastSuccessfulStage = response.lastsuccessfulstage;
            // changesinlastsuccessfulstage = true;
            // openModal("#cluepage");
            // If the stage is not solved I will notify you that there are changes.
            if (response.lastsuccessfulstage.question !== "") {
              // $("#validatelocation").hide();
            } else if (!response.lastsuccessfulstage.activitysolved) {
              // $("#validatelocation").hide();
            } else {
              // $("#validatelocation").show();
            }
          }
          // Check if it is the first geometry or it is being initialized and center the map.
          if (response.firststagegeom || initialize) {
            this.fitMapToSource();
          }

          // set_lastsuccessfulstage();
          // set_question();
          // set_attempts_history();
        }
        if (response.infomsg.length > 0) {
          let body = "";
          this.gameStatus.infoMsgs.forEach((msg) => {
            body += "<p>" + msg + "</p>";
          });
          response.infomsg.forEach((msg) => {
            this.gameStatus.infoMsgs.push(msg);
            body += "<p>" + msg + "</p>";
          });
          // $("#notificationsPopup .update-list").html(body);
          // openModal("#notificationsPopup");
        }
        if (!this.gameStatus.roadfinished) {
          // $("#roadended").hide();
        }
        if (this.gameStatus.roadfinished || !this.gameStatus.available) {
          // $("#validatelocation").hide();
          // $("#question_button").hide();
          // $("#roadended").show();
          this.mapSources.markerFeature.setGeometry(null);
          this.playConfig.playwithoutmoving = false;
          this.clearRenewInterval();
          // $("#mapplay").css("opacity", "0.8");
        }
      })
      .catch((error) => {
        /* $("#errorPopup .play-modal-content").text(error.message);
        openModal("#errorPopup"); */
        this.clearRenewInterval();
      });
  }

  flyTo(extent) {
    const view = this.map.getView();
    view.fit(extent, {
      padding: [0, 30, 0, 30],
      duration: this.mapProperties.defaultAnimationDuration,
      maxZoom: this.mapProperties.maxAnimationZoom,
    });
  }

  fitMapToSource() {
    const source = this.mapSources.attemptsLayer.getSource();
    this.flyTo(source.getExtent());
  }

  centerOnUserPosition() {
    const accuracy = this.mapSources.accuracyFeature.getGeometry();
    if (accuracy) {
      this.flyTo(accuracy);
    }
  }

  clearRenewInterval() {
    clearInterval(this.gameStatus.renewSourceInterval);
  }

  removeEvents() {
    this.allEvents.forEach((key) => {
      ol.Observable.unByKey(key);
    });
  }

  scanQR() {
    that.CoreUtilsProvider.scanQR().then((val) => {
      if (val) {
        that.CoreDomUtilsProvider.showToast("QR code readed: " + val, false);
        this.renewSource(false, false, null, value);
      }
    });
  }

  showValidateLocation() {
    that.CoreDomUtilsProvider.showConfirm(
      that.TranslateService.instant(
        "plugin.mod_treasurehunt.sendlocationcontent"
      ),
      that.TranslateService.instant(
        "plugin.mod_treasurehunt.sendlocationtitle"
      ),
      that.TranslateService.instant("plugin.mod_treasurehunt.send"),
      that.TranslateService.instant("plugin.mod_treasurehunt.cancel")
    ).then(
      () => {
        if (
          this.playConfig.playwithoutmoving &&
          !this.mapSources.markerFeature.getGeometry()
        ) {
          that.CoreDomUtilsProvider.showToast(
            "plugin.mod_treasurehunt.nomarks",
            true,
            5000
          );
        } else {
          this.renewSource(true, false);
        }
      },
      () => {}
    );
  }

  showLoading(text) {
    this.loadingModal = that.CoreDomUtilsProvider.showModalLoading(text);
  }

  hideLoading() {
    if (this.loadingModal) {
      this.loadingModal.dismiss();
    }
  }

  getCardTemplate(title, body) {
    return `<div class="modal-card">
          <h5 class="modal-card-title">${title}</h5>
          ${body}
      </div>`;
  }
}

var that = this;

this.treasureHuntPlayMobile = new TreasureHuntPlayMobile(
  this.CONTENT_OTHERDATA.playconfig
);

// Needed for set jsData object to Search Page
this.searchPageData = {
  flyToCallback: this.treasureHuntPlayMobile.flyTo.bind(
    this.treasureHuntPlayMobile
  ),
};

this.layersPageData = {
  flyToCallback: this.treasureHuntPlayMobile.flyTo.bind(
    this.treasureHuntPlayMobile
  ),
};

// Remove events on exit
this.ionViewWillUnload = () => {
  // Clear interval
  this.treasureHuntPlayMobile.clearRenewInterval();
  // Remove events
  this.treasureHuntPlayMobile.removeEvents();
  // Remove events listener
  this.treasureHuntPlayMobile.map
    .getTargetElement()
    .removeEventListener("touchmove", processTouchmove, false);
  // Remove gps tracking
  this.treasureHuntPlayMobile.geolocation.setTracking(false);
};

/**
 * Check if script if loaded
 *
 * @param {*} url
 * @returns
 */
function checkIfScriptIsLoaded(url) {
  let scripts = document.getElementsByTagName("script");
  for (let i = scripts.length; i--; ) {
    if (scripts[i].src == url) return true;
  }
  return false;
}

/**
 *
 *
 * @param {*} url
 * @returns
 */
function loadOlScript(localUrl) {
  return new Promise((resolve, reject) => {
    // Check if script if loaded
    if (checkIfScriptIsLoaded(localUrl)) {
      resolve();
    } else {
      //load script
      let script = document.createElement("script");
      script.onload = () => {
        resolve();
      };
      script.onerror = (error) => reject(error);
      script.type = "text/javascript";
      script.src = localUrl;
      document.body.appendChild(script);
    }
  });
}

function loadInitialResources() {
  return new Promise((resolve, reject) => {
    const promises = [];

    const site = that.CoreFilepoolProvider.sitesProvider.getCurrentSite();
    const initialResourcesUrl = {
      successMark: "pix/success_mark.png",
      failureMark: "pix/failure_mark.png",
      locationMark: "pix/bootstrap/my_location_3.png",
      olScript: "js/ol/ol.js",
    };

    Object.keys(initialResourcesUrl).forEach((key) => {
      promises.push(
        that.CoreFilepoolProvider.downloadUrl(
          site.id,
          site.siteUrl + "/mod/treasurehunt/" + initialResourcesUrl[key]
        ).then((localUrl) => {
          initialResourcesUrl[key] = localUrl;
        })
      );
    });

    Promise.all(promises).then(
      () => {
        resolve(initialResourcesUrl);
      },
      (error) => reject(error)
    );
  });
}

// touchmove handler
function processTouchmove(ev) {
  ev.stopPropagation();
}

// Load initial resources and initilize map
loadInitialResources().then((initialResources) => {
  loadOlScript(initialResources.olScript).then(() => {
    // Init map
    this.treasureHuntPlayMobile.init(initialResources);
    // Cancel pull to request event handler
    this.treasureHuntPlayMobile.map
      .getTargetElement()
      .addEventListener("touchmove", processTouchmove, false);
  });
});
