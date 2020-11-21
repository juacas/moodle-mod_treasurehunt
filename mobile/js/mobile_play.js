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
 * This file is part of the Moodle apps support for the treasurehunt plugin.
 * Define classes and game functions for treasure hunt
 *
 * @package   mod_treasurehunt
 * @copyright 2020 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http:// www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
      overlayLayers: [],
      attemptsLayer: null,
      positionFeature: null,
      accuracyFeature: null,
      markerFeature: null,
      coordsOverlay: null,
    };

    this.layersConfig = {
      baseLayers: [],
      overlayLayers: [],
    };

    this.mapProperties = {
      defaultAnimationDuration: 700,
      maxAnimationZoom: 18,
      defaultProjection: "EPSG:3857",
    };

    this.gameStatus = {
      qoaremoved: false,
      roadFinished: false,
      available: true,
      attemptshistory: [],
      renewSourceInterval: null,
      lastSuccessfulStage: {},
      showingTutorial: false,
      showValidateLocationButton: true,
      showQRButton: false,
      shakeClueButton: false,
    };

    this.langSubscription = null;

    this.allEvents = [];

    this.geolocation = null;

    this.resources = {};

    this.loadingModal = null;

    this.infoPopup = {
      show: false,
      title: null,
      content: "",
    };

    this.notificationsPopup = {
      show: false,
      content: "",
    };

    this.geolocationPopup = {
      show: false,
    };

    this.errorPopup = {
      show: false,
      content: "",
    };

    this.coordsOverlay = {
      timeoutHandler: null,
      hdms: null,
      gsvUrl: null,
    };
  }

  init(initialResources) {
    this.showLoading();
    // Handle app language changes
    this.langSubscription = that.TranslateService.onLangChange.subscribe(() => {
      this.renewSource(false, false, null, null, true);
    });

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
    this.initMapOverlays();
    this.map = new ol.Map({
      target: "map",
      layers: this.mapSources.totalLayers,
      overlays: [this.mapSources.coordsOverlay],
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

    const introPlayProgress = localStorage.getItem("introPlayProgress");
    if (introPlayProgress != "Done") {
      this.launchTutorial();
    }
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
        text: this.translate("plugin.mod_treasurehunt.startfromhere"),
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

    // Custom layer
    let customLayer;
    if (this.playConfig.custommapconfig) {
      if (this.playConfig.custommapconfig.custombackgroundurl) {
        let customImageExtent = ol.proj.transformExtent(
          this.playConfig.custommapconfig.bbox,
          "EPSG:4326",
          this.mapProperties.defaultProjection
        );
        if (!this.playConfig.custommapconfig.geographic) {
          // Round bbox and scales to allow vectorial SVG rendering. (Maintain ratio.)
          const bboxHeight = customImageExtent[3] - customImageExtent[1];
          const centerWidth = (customImageExtent[2] + customImageExtent[0]) / 2;
          const centerHeight =
            (customImageExtent[3] + customImageExtent[1]) / 2;
          const ratioRealMap = Math.round(
            bboxHeight / this.playConfig.custommapconfig.imgheight
          );
          const adjWidth = Math.round(
            this.playConfig.custommapconfig.imgwidth * ratioRealMap
          );
          const adjHeight = Math.round(
            this.playConfig.custommapconfig.imgheight * ratioRealMap
          );
          customImageExtent = [
            centerWidth - adjWidth / 2,
            centerHeight - adjHeight / 2,
            centerWidth + adjWidth / 2,
            centerHeight + adjHeight / 2,
          ];
          this.mapProperties.maxAnimationZoom = 5;
        }
        customLayer = new ol.layer.Image({
          title: this.playConfig.custommapconfig.layername,
          name: this.playConfig.custommapconfig.layername,
          type: this.playConfig.custommapconfig.layertype,
          source: new ol.source.ImageStatic({
            url: this.resources.customBackgroundUrl,
            imageExtent: customImageExtent,
          }),
          img: this.playConfig.custommapconfig.custombackgroundurl,
          visible: true,
          opacity: 1.0,
        });
      } else if (this.playConfig.custommapconfig.wmsurl) {
        const options = {
          source: new ol.source.TileWMS({
            url: this.playConfig.custommapconfig.wmsurl,
            params: this.playConfig.custommapconfig.wmsparams,
          }),
          visible: true,
          type: this.playConfig.custommapconfig.layertype,
          title: this.playConfig.custommapconfig.layername,
          name: this.playConfig.custommapconfig.layername,
        };
        if (
          this.playConfig.custommapconfig.bbox[0] &&
          this.playConfig.custommapconfig.bbox[1] &&
          this.playConfig.custommapconfig.bbox[2] &&
          this.playConfig.custommapconfig.bbox[3]
        ) {
          const customWmsExtent = ol.proj.transformExtent(
            this.playConfig.custommapconfig.bbox,
            "EPSG:4326",
            this.mapProperties.defaultProjection
          );
          options.extent = customWmsExtent;
        }
        customLayer = new ol.layer.Tile(options);
      }
    }

    // Base Layers
    if (
      !this.playConfig.custommapconfig ||
      this.playConfig.custommapconfig.onlybase === false
    ) {
      const roadLayer = new ol.layer.Tile({
        visible:
          !customLayer ||
          (this.playConfig.custommapconfig &&
            this.playConfig.custommapconfig.layertype === "overlay"),
        source: new ol.source.OSM(),
        name: "road",
        title: this.translate("plugin.mod_treasurehunt.roadview"),
        img:
          that.CoreFilepoolProvider.sitesProvider.getCurrentSite().siteUrl +
          "/mod/treasurehunt/pix/basemaps/road.jpg",
      });

      const aerialLayer = new ol.layer.Tile({
        visible: false,
        source: new ol.source.TileImage({
          url: "https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}",
        }),
        name: "aerial",
        title: this.translate("plugin.mod_treasurehunt.aerialview"),
        img:
          that.CoreFilepoolProvider.sitesProvider.getCurrentSite().siteUrl +
          "/mod/treasurehunt/pix/basemaps/aerial.jpg",
      });

      this.mapSources.baseLayers = [roadLayer, aerialLayer];
    }

    if (customLayer) {
      if (this.playConfig.custommapconfig.layertype !== "overlay") {
        this.mapSources.baseLayers.push(customLayer);
      } else {
        this.mapSources.overlayLayers.push(customLayer);
      }
    }

    this.setLayersConfig();
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
      ...this.mapSources.overlayLayers,
      this.mapSources.attemptsLayer,
      userPosition,
      markerPosition,
    ];
  }

  initMapOverlays() {
    this.mapSources.coordsOverlay = new ol.Overlay({
      element: document.getElementById("coordsOverlay"),
      autoPan: true,
      autoPanAnimation: {
        duration: 250,
      },
    });
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
          !this.playConfig.playwithoutmoving &&
          trackinggeolocationwarndispatched == false
        ) {
          setTimeout(() => {
            this.geolocationPopup.show = true;
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
              title = this.translate("plugin.mod_treasurehunt.stageovercome");
              body += this.getCardTemplate(
                this.translate("plugin.mod_treasurehunt.stagename"),
                stagename
              );
              body += this.getCardTemplate(
                this.translate("plugin.mod_treasurehunt.stageclue"),
                stageclue
              );
            } else {
              title = this.translate(
                "plugin.mod_treasurehunt.discoveredlocation"
              );
            }
          } else {
            title = this.translate("plugin.mod_treasurehunt.failedlocation");
          }
          this.infoPopup.show = body;
          this.infoPopup.title = title;
          this.infoPopup.content = body;
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
          if (
            this.playConfig.custommapconfig === null ||
            this.playConfig.custommapconfig.geographic
          ) {
            this.clearTimeOutCoordsOverlay();
            this.mapSources.coordsOverlay.setPosition(evt.coordinate);
            const latlon = ol.proj.toLonLat(
              evt.coordinate,
              this.map.getView().getProjection()
            );
            this.coordsOverlay.hdms = ol.coordinate.toStringHDMS(latlon);
            this.coordsOverlay.gsvUrl = `http://maps.google.com/?cbll=${latlon[1]},${latlon[0]}&cbp=12,20.09,,0,5&layer=c`;
            this.coordsOverlay.timeoutHandler = setTimeout(() => {
              this.closeCoordsOverlay();
            }, 3000);
          }
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
   * @param {boolean} changedapplang changed app language
   */
  renewSource(
    location,
    initialize,
    selectedanswerid,
    qrtext,
    changedapplang = false
  ) {
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
          applang: that.TranslateService.currentLang,
          changedapplang: changedapplang,
        },
      },
      { getFromCache: false }
    )
      .then((response) => {
        this.gameStatus.qoaremoved = response.qoaremoved;
        this.gameStatus.roadFinished = response.roadfinished;
        this.gameStatus.available = response.available;
        this.hideLoading();

        // If I have sent a location or an answer I print out whether it is correct or not.
        if (location || selectedanswerid) {
          if (response.status !== null && response.available) {
            that.CoreDomUtilsProvider.showToast(response.status.msg, false);
          }
          if (
            selectedanswerid &&
            response.lastsuccessfulstage &&
            !response.lastsuccessfulstage.question
          ) {
            // Go back 2 pages (to play page)
            that.NavController.popTo(
              that.NavController.getByIndex(that.NavController.length() - 3)
            );
          }
          this.mapSources.markerFeature.setGeometry(null);
        }

        // If change the game mode (mobile or static).
        if (this.playConfig.playwithoutmoving != response.playwithoutmoving) {
          this.playConfig.playwithoutmoving = response.playwithoutmoving;
          this.geolocation.setTracking(!this.playConfig.playwithoutmoving);
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
          !response.available ||
          response.attempts
        ) {
          this.playConfig.lastattempttimestamp = response.attempttimestamp;
          this.playConfig.lastroadtimestamp = response.roadtimestamp;
          if (response.attempthistory.length > 0) {
            this.gameStatus.attemptshistory = response.attempthistory;
          }
          // Update attempts layer
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
            if (!this.gameStatus.showingTutorial && !initialize) {
              setTimeout(() => this.openCluePage(), 1500);
            }
            // If the stage is not solved notify changes.
            this.gameStatus.showQRButton = false;

            if (response.lastsuccessfulstage.question !== "") {
              this.gameStatus.showValidateLocationButton = false;
            } else if (!response.lastsuccessfulstage.activitysolved) {
              this.gameStatus.showValidateLocationButton = false;
            } else {
              this.gameStatus.showValidateLocationButton = true;
              if (response.qrexpected) {
                this.gameStatus.showQRButton = true;
              }
            }
          }
          // Check if it is the first geometry or it is being initialized and center the map.
          if (response.firststagegeom || initialize) {
            this.fitMapToSource();
            // Shake clue button
            this.gameStatus.shakeClueButton = true;
            setTimeout(() => (this.gameStatus.shakeClueButton = false), 3000);
          }
        }
        if (response.infomsg.length > 0) {
          this.showNotifications(response.infomsg);
        }
        if (this.gameStatus.roadFinished || !this.gameStatus.available) {
          this.gameStatus.showValidateLocationButton = false;
          this.gameStatus.showQRButton = false;
          this.mapSources.markerFeature.setGeometry(null);
          this.playConfig.playwithoutmoving = false;
          this.clearRenewInterval();
        }
      })
      .catch((error) => {
        this.hideLoading();
        this.closeAllPopups();
        this.errorPopup.content = error.message;
        this.errorPopup.show = true;
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
    if (this.geolocation.get("user_denied")) {
      this.geolocationPopup.show = true;
    } else {
      const accuracy = this.mapSources.accuracyFeature.getGeometry();
      if (accuracy) {
        this.flyTo(accuracy);
      }
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
        that.CoreDomUtilsProvider.showToast(
          this.translate("plugin.mod_treasurehunt.qrreaded") + val,
          false
        );
        this.renewSource(false, false, null, val);
      }
    });
  }

  showNotifications(notifications) {
    notifications.forEach((msg) => {
      this.notificationsPopup.content += "<p>" + msg + "</p>";
    });
    // Before open notification popup, close others.
    this.closeAllPopups();
    this.notificationsPopup.show = true;
  }

  acceptNotifications() {
    this.notificationsPopup.content = "";
    this.notificationsPopup.show = false;
  }

  closeAllPopups() {
    this.notificationsPopup.show = false;
    this.infoPopup.show = false;
    this.errorPopup.show = false;
  }

  showValidateLocation() {
    that.CoreDomUtilsProvider.showConfirm(
      this.translate("plugin.mod_treasurehunt.sendlocationcontent"),
      this.translate("plugin.mod_treasurehunt.sendlocationtitle"),
      this.translate("plugin.mod_treasurehunt.send"),
      this.translate("plugin.mod_treasurehunt.cancel")
    ).then(
      () => {
        if (
          this.playConfig.playwithoutmoving &&
          !this.mapSources.markerFeature.getGeometry()
        ) {
          that.CoreDomUtilsProvider.showToast(
            "plugin.mod_treasurehunt.nomarksmobile",
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

  setOverlayLayerVisibility(layerName, visible) {
    const selectedLayer = this.mapSources.overlayLayers.find(
      (layer) => layer.get("name") === layerName
    );
    if (selectedLayer) {
      selectedLayer.setVisible(visible);
    }
  }

  setActiveBaseLayer(layerName) {
    const selectedLayer = this.mapSources.baseLayers.find(
      (layer) => layer.get("name") === layerName
    );
    if (selectedLayer) {
      this.mapSources.baseLayers.forEach((baseLayer) => {
        if (baseLayer === selectedLayer) {
          selectedLayer.setVisible(true);
        } else {
          baseLayer.setVisible(false);
        }
      });
    }
  }

  sendAnswer(selectedAnswer) {
    if (selectedAnswer) {
      this.renewSource(false, false, selectedAnswer);
    }
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
    return `<div  class="modal-card">
          <h5 class="modal-card-title">${title}</h5>
          <div core-external-content>${body}</div>
      </div>`;
  }

  closeInfoPopup() {
    this.mapInteractions.select.getFeatures().clear();
    this.infoPopup.show = false;
  }

  closeErrorPopup() {
    this.errorPopup.show = false;
  }

  openCluePage() {
    // Workaround to open clue page
    const clueButton = document.getElementById("clue-button");
    if (clueButton) {
      clueButton.click();
    }
  }

  setLayersConfig() {
    this.layersConfig.baseLayers = this.mapSources.baseLayers.map((layer) =>
      this.getLayerConfig(layer)
    );

    this.layersConfig.overlayLayers = this.mapSources.overlayLayers.map(
      (layer) => this.getLayerConfig(layer)
    );
  }

  getLayerConfig(layer) {
    return {
      title: layer.get("title"),
      name: layer.get("name"),
      visible: layer.getVisible(),
      img: layer.get("img"),
    };
  }

  openGoogleStreetViewUrl() {
    that.CoreUtilsProvider.openInBrowser(this.coordsOverlay.gsvUrl);
  }

  closeCoordsOverlay() {
    this.mapSources.coordsOverlay.setPosition(undefined);
  }

  clearTimeOutCoordsOverlay() {
    if (this.coordsOverlay.timeoutHandler) {
      clearTimeout(this.coordsOverlay.timeoutHandler);
    }
  }

  launchTutorial() {
    this.gameStatus.showingTutorial = true;
    const intro = introJs();

    intro.setOptions({
      nextLabel: this.translate("plugin.mod_treasurehunt.nextstep"),
      prevLabel: this.translate("plugin.mod_treasurehunt.prevstep"),
      skipLabel: this.translate("plugin.mod_treasurehunt.skiptutorial"),
      doneLabel: this.translate("plugin.mod_treasurehunt.donetutorial"),
    });

    const steps = [
      {
        intro: this.translate("plugin.mod_treasurehunt.welcome_play_tour"),
        position: "floating",
      },
      {
        element: "#clue-button",
        intro: this.translate(
          "plugin.mod_treasurehunt.lastsuccessfulstage_tour"
        ),
        position: "top",
      },
      {
        element: "#map",
        intro: this.translate("plugin.mod_treasurehunt.mapplaymobile_tour", {
          $a: {
            successurl: this.resources.successMark,
            failureurl: this.resources.failureMark,
          },
        }),
        position: "floating",
      },
      {
        element: "#validate-location",
        intro: this.translate("plugin.mod_treasurehunt.validatelocation_tour"),
        position: "auto",
      },
      {
        element: "#autolocate",
        intro: this.translate("plugin.mod_treasurehunt.autolocate_tour"),
        position: "auto",
        condition: !this.playConfig.playwithoutmoving,
      },
      {
        element: "#treasurehunt-play-page",
        intro: this.translate("plugin.mod_treasurehunt.playend_tour"),
        position: "floating",
      },
    ];

    // Add all the steps that have no condition or those that meet them
    steps.forEach((step) => {
      if (!step.hasOwnProperty("condition") || step.condition) {
        intro.addStep(step);
      }
    });

    intro.onexit(() => this.onFinishedTutorial());
    intro.oncomplete(() => this.onFinishedTutorial());

    intro.start();
  }

  onFinishedTutorial() {
    localStorage.setItem("introPlayProgress", "Done");
    this.gameStatus.showingTutorial = false;
  }

  translate(string, context = null) {
    return that.TranslateService.instant(string, context);
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
  layersConfig: this.treasureHuntPlayMobile.layersConfig,
  setActiveBaseLayer: this.treasureHuntPlayMobile.setActiveBaseLayer.bind(
    this.treasureHuntPlayMobile
  ),
  setOverlayLayerVisibility: this.treasureHuntPlayMobile.setOverlayLayerVisibility.bind(
    this.treasureHuntPlayMobile
  ),
};

this.cluePageData = {
  gameStatus: this.treasureHuntPlayMobile.gameStatus,
  sendAnswer: this.treasureHuntPlayMobile.sendAnswer.bind(
    this.treasureHuntPlayMobile
  ),
};

// Remove events on exit
this.ionViewWillUnload = () => {
  // Clear interval
  this.treasureHuntPlayMobile.clearRenewInterval();
  // Remove events
  this.treasureHuntPlayMobile.removeEvents();
  // Remove gps tracking
  this.treasureHuntPlayMobile.geolocation.setTracking(false);
  // Remove change language subscription
  this.treasureHuntPlayMobile.langSubscription.unsubscribe();
};

// Cancel pull to request stopping the touchmove event propagation
this.cancelPullToRequest = (ev) => {
  ev.stopPropagation();
};

/**
 * Load css style and insert into head
 *
 * @param {*} url
 * @returns
 */
function loadStyle(localUrl, id) {
  return new Promise((resolve, reject) => {
    // Check if css is loaded
    if (document.getElementById(id)) {
      resolve();
    } else {
      //load style
      const link = document.createElement("link");
      link.onload = () => {
        resolve();
      };
      link.onerror = (error) => reject(error);
      link.id = id;
      link.rel = "stylesheet";
      link.type = "text/css";
      link.href = localUrl;
      link.media = "all";
      document.head.appendChild(link);
    }
  });
}

/**
 * Load script and insert into body
 *
 * @param {*} url
 * @returns
 */
function loadScript(localUrl, id) {
  return new Promise((resolve, reject) => {
    // Check if script is loaded
    if (document.getElementById(id)) {
      resolve();
    } else {
      //load script
      let script = document.createElement("script");
      script.onload = () => {
        resolve();
      };
      script.onerror = (error) => reject(error);
      script.id = id;
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
    let initialResourcesUrl = {
      successMark: {
        url: "pix/success_mark.png",
        version: 2020091600,
      },
      failureMark: {
        url: "pix/failure_mark.png",
        version: 2020091600,
      },
      locationMark: {
        url: "pix/bootstrap/my_location_3.png",
        version: 2020091600,
      },
      olScript: {
        url: "js/ol/ol.js",
        version: 2020091600,
      },
      olCss: {
        url: "css/ol.css",
        version: 2020091600,
      },
      olPopupCss: {
        url: "css/playerbootstrap/ol-popup.css",
        version: 2020091600,
      },
      instroJsScript: {
        url: "amd/build/intro.min.js",
        version: 2020091600,
      },
      introJsCss: {
        url: "css/introjs.css",
        version: 2020091600,
      },
    };

    Object.keys(initialResourcesUrl).forEach((key) => {
      promises.push(
        that.CoreFilepoolProvider.downloadUrl(
          site.id,
          site.siteUrl + "/mod/treasurehunt/" + initialResourcesUrl[key].url,
          null,
          null,
          null,
          initialResourcesUrl[key].version
        ).then((localUrl) => {
          initialResourcesUrl[key] = localUrl;
        })
      );
    });

    // custom background image as default map
    if (
      that.CONTENT_OTHERDATA.playconfig.custommapconfig &&
      that.CONTENT_OTHERDATA.playconfig.custommapconfig.custombackgroundurl
    ) {
      promises.push(
        that.CoreFilepoolProvider.downloadUrl(
          site.id,
          that.CONTENT_OTHERDATA.playconfig.custommapconfig.custombackgroundurl
        ).then((localUrl) => {
          initialResourcesUrl.customBackgroundUrl = localUrl;
          return new Promise((resolve, reject) => {
            const img = new Image();
            img.addEventListener("load", () => {
              that.CONTENT_OTHERDATA.playconfig.custommapconfig.imgwidth =
                img.naturalWidth;
              that.CONTENT_OTHERDATA.playconfig.custommapconfig.imgheight =
                img.naturalHeight;
              resolve();
            });
            img.src = localUrl;
          });
        })
      );
    }

    Promise.all(promises).then(
      () => {
        resolve(initialResourcesUrl);
      },
      (error) => reject(error)
    );
  });
}

// Load initial resources and initilize map
loadInitialResources().then((initialResources) => {
  // Once scripts are loaded, initialize map
  Promise.all([
    loadScript(initialResources.olScript, "mod-treasurehunt-ol-script"),
    loadScript(
      initialResources.instroJsScript,
      "mod-treasurehunt-introjs-script"
    ),
    loadStyle(initialResources.olCss, "mod-treasurehunt-ol-css"),
    loadStyle(initialResources.introJsCss, "mod-treasurehunt-introjs-css"),
    loadStyle(initialResources.olPopupCss, "mod-treasurehunt-olpopup-css"),
  ]).then(() => {
    // Init map
    this.treasureHuntPlayMobile.init(initialResources);
  });
});
