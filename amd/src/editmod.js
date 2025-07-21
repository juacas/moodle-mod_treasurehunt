// This file is part of Moodle - http:// moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify.
// it under the terms of the GNU General Public License as published by.
// the Free Software Foundation, either version 3 of the License, or.
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,.
// but WITHOUT ANY WARRANTY; without even the implied warranty of.
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <http:// www.gnu.org/licenses/>.
/**
 * @module mod_treasurehunt/editmod
 * @package
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>,
 *            Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @author Adrian Rodriguez <huorwhisp@gmail.com>
 * @author Juan Pablo de Castro <jpdecastro@tel.uva.es>*
 * @license http:// www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
import $ from "jquery";
import ol from "mod_treasurehunt/ol";
import ajax from "core/ajax";
import notification from "core/notification";
import OSMGeocoder from "mod_treasurehunt/osm-geocoder";
import viewgpx from "mod_treasurehunt/viewgpx";
import { get_strings as str } from "core/str";

let init = {
  edittreasurehunt: function (idModule, treasurehuntid, selectedroadid, lockid, custommapconfig) {
      // I18n strings.
      var terms = [
        "stage", "road", "aerialmap", "roadmap", "basemaps", "add", "modify", "save",
        "remove", "searchlocation", "savewarning", "removewarning", "areyousure",
        "removeroadwarning", "confirm", "cancel", "pegmanlabel", "custommapimageerror",
      ];
      var stringsqueried = terms.map(function (term) {
        let comp = 'treasurehunt';
        let stringobj = { key: term, component: comp };
        if (term == "none") {
          return null;
        }
        return stringobj;
      });
      str(stringsqueried).then(function (strings) {
        var i18n = [];
        for (var i = 0; i < terms.length; i++) {
          i18n[terms[i]] = strings[i];
        }
        // Detect custom image.
        if (typeof custommapconfig != "undefined" && custommapconfig !== null && custommapconfig.custombackgroundurl !== null) {
          // Detect image size.
          var img = new Image();
          img.onload = function () {
            custommapconfig.imgwidth = this.naturalWidth;
            custommapconfig.imgheight = this.naturalHeight;
            initedittreasurehunt(idModule, treasurehuntid, i18n, selectedroadid, lockid, custommapconfig);
          };
          img.onerror = function () {
            notification.alert("Error", i18n["custommapimageerror"], "Continue");
            initedittreasurehunt(idModule, treasurehuntid, i18n, selectedroadid, lockid, custommapconfig);
          };
          img.src = custommapconfig.custombackgroundurl;
        } else {
          initedittreasurehunt(idModule, treasurehuntid, i18n, selectedroadid, lockid, custommapconfig);
        }
      });
    }, // End of function edittreasurehunt.
}; // End Init.
/**
 * Create map and ui.
 * @param {integer} idModule
 * @param {integer} treasurehuntid
 * @param {array} strings
 * @param {integer} selectedroadid
 * @param {integer} lockid
 * @param {object} custommapconfig
 */
function initedittreasurehunt(idModule, treasurehuntid, strings, selectedroadid, lockid, custommapconfig) {
    var mapprojection = "EPSG:3857";
    var mapprojobj = new ol.proj.Projection(mapprojection);
    var custombaselayer = null;
    var geographictools = true;
    // Support customized base layers.
    if (typeof (custommapconfig) != 'undefined' && custommapconfig !== null) {
      if (custommapconfig.custombackgroundurl !== null) {
        var customimageextent = calculateCustomImageExtent(custommapconfig, mapprojection, false);
        custombaselayer = new ol.layer.Image({
          title: custommapconfig.layername,
          type: custommapconfig.layertype,
          source: new ol.source.ImageStatic({
            url: custommapconfig.custombackgroundurl,
            imageExtent: customimageextent
          }),
          opacity: 1.0
        });
      } else if (custommapconfig.wmsurl !== null) {
        let options = {
          type: custommapconfig.layertype,
          title: custommapconfig.layername,
          name: custommapconfig.layername,
        };
        if (custommapconfig.layerservicetype === "wms") {
          options.source = new ol.source.TileWMS({
            url: custommapconfig.wmsurl,
            params: custommapconfig.wmsparams,
          });
        } else if (custommapconfig.layerservicetype === "tiled") {
          options.source = new ol.source.XYZ({ url: custommapconfig.wmsurl });
        } else if (custommapconfig.layerservicetype === "arcgis") {
          options.source = new ol.source.TileArcGISRest({ url: custommapconfig.wmsurl });
        }

        if (custommapconfig.bbox[0] && custommapconfig.bbox[1] && custommapconfig.bbox[2] && custommapconfig.bbox[3]) {
          let customwmsextent = ol.proj.transformExtent(custommapconfig.bbox, "EPSG:4326", mapprojection);
          options.extent = customwmsextent;
        }
        custombaselayer = new ol.layer.Tile(options);
        custombaselayer.set('name', custommapconfig.layername);
      }
      geographictools = custommapconfig.geographic;
    }

    var treasurehunt = { roads: {} },
      dirtyStages = new ol.source.Vector({ projection: mapprojection }),
      originalStages = new ol.source.Vector({ projection: mapprojection }),
      dirty = false,
      abortDrawing = false,
      drawStarted = false,
      stageposition,
      roadid,
      stageid,
      selectedFeatures,
      selectedstageFeatures = {},
      idNewFeature = 1,
      vectorSelected = new ol.layer.Vector({
        source: new ol.source.Vector({
          projection: mapprojection,
        }),
      });
    let osmGeocoderXHR;
    // Load the control pane, treasurehunt and road list.
    if (geographictools) {
      $('<div id="searchcontainer">').appendTo($("#controlpanel"));
      $(
        '<input type="search" placeholder="' +
        strings["searchlocation"] +
        '" class="searchaddress"/>'
      ).appendTo($("#searchcontainer"));
      $('<span class="ui-icon  ui-icon-search searchicon"></span>').prependTo(
        $("#searchcontainer")
      );
      $(
        '<span class="ui-icon  ui-icon-closethick closeicon invisible"></span>'
      ).appendTo($("#searchcontainer"));
    }

    // Creo el stagelist.
    $('<ul id="stagelist"/>').prependTo($("#stagelistpanel"));
    // Lo cargo como un sortable.
    $("#stagelist")
      .sortable({
        handle: ".handle",
        tolerance: "pointer",
        zIndex: 9999,
        opacity: 0.5,
        forcePlaceholderSize: true,
        cursorAt: {
          top: -7,
        },
        cursor: "n-resize",
        axis: "y",
        items: "li:not(:hidden , .blocked)",
        helper: "clone",
        start: function (event, ui) {
          var roadid = ui.item.attr("roadid"),
            start_pos = ui.item.index('li[roadid="' + roadid + '"]'),
            scrollParent = $(this).data("ui-sortable").scrollParent,
            maxScrollTop =
              scrollParent[0].scrollHeight -
              scrollParent[0].clientHeight -
              ui.helper.height();
          ui.item.data("start_pos", start_pos);
          // Set max scrollTop for sortable
          // scrolling.
          $(this).data("maxScrollTop", maxScrollTop);
        },
        sort: function (/*e, ui*/) {
          // Check if scrolling is out of
          // boundaries.
          var scrollParent = $(this).data("ui-sortable").scrollParent,
            maxScrollTop = $(this).data("maxScrollTop");
          if (scrollParent.scrollTop() > maxScrollTop) {
            scrollParent.scrollTop(maxScrollTop);
          }
        },
        update: function (event, ui) {
          var start_pos = ui.item.data("start_pos"),
            roadid = ui.item.attr("roadid"),
            end_pos = ui.item.index('li[roadid="' + roadid + '"]'),
            $listitems = $(this).children('li[roadid="' + roadid + '"]'),
            $listlength = $($listitems).length,
            i;
          if (start_pos === end_pos) {
            return;
          }
          if (start_pos < end_pos) {
            for (i = start_pos; i <= end_pos; i++) {
              relocatestageList(
                $listitems,
                $listlength,
                i,
                dirtyStages,
                originalStages,
                treasurehunt.roads[roadid].vector
              );
            }
          } else {
            for (i = end_pos; i <= start_pos; i++) {
              relocatestageList(
                $listitems,
                $listlength,
                i,
                dirtyStages,
                originalStages,
                treasurehunt.roads[roadid].vector
              );
            }
          }
          activateSaveButton();
          dirty = true;
        },
      })
      .disableSelection();

    /**
     * Relocates and updates stage positions in a list of treasure hunt stages.
     * @param {jQuery} $listitems - jQuery collection of list items representing stages
     * @param {number} $listlength - Total length of the list
     * @param {number} i - Current index being processed
     * @param {Array} dirtyStages - Array tracking modified stages
     * @param {Array} originalStages - Array containing original stage data
     * @param {Array} vector - Vector used for stage position calculations
     * @returns {void}
     *
     * @description
     * This function:
     * 1. Calculates new position value for a stage
     * 2. Updates stage position attribute and display number
     * 3. Updates global stageposition if item is selected
     * 4. Calls relocatenostage to update internal stage data
     */
    function relocatestageList(
      $listitems,
      $listlength,
      i,
      dirtyStages,
      originalStages,
      vector
    ) {
      var newVal,
        $item = $($listitems).get([i]),
        roadid = $($item).attr("roadid");
      newVal = Math.abs(
        $($item).index('li[roadid="' + roadid + '"]') - $listlength
      );
      $($item).attr("stageposition", newVal);
      $($item).find(".sortable-number").text(newVal);
      // Si esta seleccionado cambiamos el valor de
      // stageposition.
      if ($($item).hasClass("ui-selected")) {
        stageposition = newVal;
      }
      relocatenostage(
        parseInt($($item).attr("stageid"), 10),
        newVal,
        parseInt($($item).attr("roadid"), 10),
        dirtyStages,
        originalStages,
        vector
      );
    }

    // Creo el roadlistpanel.
    $('<ul id="roadlist"/>').appendTo($("#roadlistpanel"));
    /*
     * Set control
     *
     * @type edit_L27.ol.style.Style
     */
    window.app = {};
    var app = window.app;
    /**
     * @constructor
     * @extends {ol.control.Control}
     * @param {Object} opt_options Control options.
     */
    app.generateResizableControl = function (opt_options) {
      var options = opt_options || {},
        button = document.createElement("button"),
        element = document.createElement("div");
      button.innerHTML = "<>";
      button.id = "egrip";
      element.className = "ol-control ol-unselectable egrip-container";
      element.appendChild(button);
      ol.control.Control.call(this, {
        element: element,
        target: options.target,
      });
    };
    ol.inherits(app.generateResizableControl, ol.control.Control);
    // Get style, vectors, map and interactions.
    var defaultstageStyle = new ol.style.Style({
      fill: new ol.style.Fill({
        color: "rgba(100, 100, 255, 0.2)",
      }),
      stroke: new ol.style.Stroke({
        color: "rgba(100, 100, 255, 0.5)",
        width: 2,
      }),
      image: new ol.style.Circle({
        radius: 5,
        fill: new ol.style.Fill({
          color: "#ffcc33",
        }),
        stroke: new ol.style.Stroke({
          color: "#000000",
          width: 2,
        }),
      }),
      text: new ol.style.Text({
        textAlign: "center",
        scale: 1.3,
        fill: new ol.style.Fill({
          color: "#fff",
        }),
        stroke: new ol.style.Stroke({
          color: "#6C0492",
          width: 3.5,
        }),
      }),
    });
    // Selected stage style.
    var selectedstageStyle = new ol.style.Style({
      fill: new ol.style.Fill({
        color: "rgba(200, 100, 100, 0.2)",
      }),
      stroke: new ol.style.Stroke({
        color: "rgba(255, 0, 0, 0.5)",
        width: 3,
      }),
      image: new ol.style.Circle({
        radius: 5,
        fill: new ol.style.Fill({
          color: "#ffcc33",
        }),
        stroke: new ol.style.Stroke({
          color: "#000000",
          width: 2,
        }),
      }),
      text: new ol.style.Text({
        textAlign: "center",
        scale: 1.3,
        fill: new ol.style.Fill({
          color: "#fff",
        }),
        stroke: new ol.style.Stroke({
          color: "#C3000B",
          width: 3.5,
        }),
      }),
      zIndex: "Infinity",
    });
    var vectorDraw = new ol.layer.Vector({
      source: new ol.source.Vector({
        projection: "EPSG:3857",
      }),
      visible: false,
    });
     /**
     * Use World Imagery de Esri (free for non commercial use).
     */
    let aeriallayer = new ol.layer.Tile({
      type: "base",
      visible: false,
      title: strings['aerialmap'],
      source: new ol.source.XYZ({
        url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        attributions: [
          'Tiles © Esri — Fuente: Esri, DigitalGlobe, Earthstar Geographics, CNES/Airbus DS, USDA,' +
          ' USGS, AeroGRID, IGN, and the GIS User Community'
        ]
      })
    });
    let roadlayer = new ol.layer.Tile({
          title: strings["roadmap"],
          type: "base",
          visible: true,
          source: new ol.source.OSM(),
        });
    var basemaps = new ol.layer.Group({
      title: strings["basemaps"],
      layers: [ aeriallayer, roadlayer ],
    });
    if (custombaselayer !== null) {
      if (custommapconfig.onlybase) {
        basemaps.getLayers().clear();
      }
      basemaps.getLayers().push(custombaselayer);
    }
    // Popup showing the position the user clicked.
    // Elements that make up the popup.
    // var container = document.getElementById("popup");
    // var content = document.getElementById("popup-content");
    // var closer = document.getElementById("popup-closer");
    /**
     * Create an overlay to anchor the popup to the map.
     */
    // Create placement for a popup over user marker.
    var overlay = viewgpx.createCoordsOverlay(
      "#mapedit",
      null,
      strings["pegmanlabel"]
    );

    // Layer selector...
    var layerSwitcher = new ol.control.LayerSwitcher();
    // Map viewer...
    var map = new ol.Map({
      layers: [basemaps, vectorDraw],
      overlays: [overlay],
      projection: mapprojobj,
      renderer: "canvas",
      target: "mapedit",
      view: new ol.View({
        center: [0, 0],
        zoom: 2,
        minZoom: 2,
      }),
      controls: ol.control.defaults().extend([
        layerSwitcher,
        new app.generateResizableControl({
          target: document.getElementById("stagelistpanel"),
        }),
      ]),
    });

    map.on("click", function (evt) {
      if (
        !Draw.getActive() &&
        !Modify.getActive() &&
        (custommapconfig === null || custommapconfig.geographic)
      ) {
        overlay.setPosition(evt.coordinate);
      }
    });
    layerSwitcher.showPanel();
    // Creo el resizable.
    $("#stagelistpanel").resizable({
      handles: {
        e: $("#egrip"),
      },
      resize: function (event, ui) {
        // param event not used.
        ui.size.height = ui.originalSize.height;
      },
      stop: function () {
        // params event, ui not used.
        map.updateSize();
      },
      cancel: "",
    });
    var Modify = {
      init: function () {
        this.select = new ol.interaction.Select({
          // Si una feature puede ser seleccionada
          // o no.
          filter: function (feature) {
            if (selectedstageFeatures[feature.getId()]) {
              return true;
            }
            return false;
          },
          style: function (feature) {
            var fill = new ol.style.Fill({
              color: "rgba(255,100,100,0.4)",
            });
            var stroke = new ol.style.Stroke({
              color: "rgba(255, 0, 0, 1)",
              width: 4,
            });
            var styles = [
              new ol.style.Style({
                image: new ol.style.Circle({
                  fill: fill,
                  stroke: stroke,
                  radius: 5,
                }),
                fill: fill,
                stroke: stroke,
                text: new ol.style.Text({
                  text: "" + feature.get("stageposition"),
                  textAlign: "center",
                  scale: 1.3,
                  fill: new ol.style.Fill({
                    color: "#fff",
                  }),
                  stroke: new ol.style.Stroke({
                    color: "rgba(255, 0, 0, 1)",
                    width: 3.5,
                  }),
                }),
                zIndex: "Infinity",
              }),
            ];
            return styles;
          },
        });
        map.addInteraction(this.select);
        this.modify = new ol.interaction.Modify({
          features: this.select.getFeatures(),
          style: new ol.style.Style({
            image: new ol.style.Circle({
              radius: 5,
              fill: new ol.style.Fill({
                color: "#3399CC",
              }),
              stroke: new ol.style.Stroke({
                color: "#000000",
                width: 2,
              }),
            }),
          }),
          deleteCondition: function (event) {
            return (
              ol.events.condition.shiftKeyOnly(event) &&
              ol.events.condition.singleClick(event)
            );
          },
        });
        map.addInteraction(this.modify);
        this.setEvents();
      },
      setEvents: function () {
        // Remove the feature selection when you switch to off.
        selectedFeatures = this.select.getFeatures();
        this.select.on("change:active", function () {
          selectedFeatures.clear();
          deactivateDeleteButton();
        });
        // Enable or disable the delete button depending on whether I have
        // a selected feature or not.
        this.select.on("select", function () {
          if (selectedFeatures.getLength() > 0) {
            activateDeleteButton();
          } else {
            deactivateDeleteButton();
          }
        });
        // Activate the save button as soon as you have
        // modified sth. or not.
        this.modify.on("modifyend", function (e) {
          activateSaveButton();
          modifyFeatureToDirtySource(
            e.features,
            originalStages,
            dirtyStages,
            treasurehunt.roads[roadid].vector
          );
          dirty = true;
        });
      },
      getActive: function () {
        return this.select.getActive() && this.modify.getActive()
          ? true
          : false;
      },
      setActive: function (active) {
        this.select.setActive(active);
        this.modify.setActive(active);
      },
    };
    Modify.init();
    var Draw = {
      init: function () {
        map.addInteraction(this.Polygon);
        this.Polygon.setActive(false);
        this.setEvents();
      },
      Polygon: new ol.interaction.Draw({
        source: vectorDraw.getSource(),
        type: /** @type {ol.geom.GeometryType} */ ("Polygon"),
        style: new ol.style.Style({
          fill: new ol.style.Fill({
            color: "rgba(0, 0, 0, 0.05)",
          }),
          stroke: new ol.style.Stroke({
            color: "#FAC30B",
            width: 2,
          }),
          image: new ol.style.Circle({
            radius: 5,
            fill: new ol.style.Fill({
              color: "#ffcc33",
            }),
            stroke: new ol.style.Stroke({
              color: "#000000",
              width: 2,
            }),
          }),
          zIndex: "Infinity",
        }),
      }),
      setEvents: function () {
        // Set the treasurehunt they belong to and activate
        // the save button .
        // depending on whether something has been changed or not.
        this.Polygon.on("drawend", function (e) {
          drawStarted = false;
          if (abortDrawing) {
            vectorDraw.getSource().clear();
            abortDrawing = false;
          } else {
            e.feature.setProperties({
              roadid: roadid,
              stageid: stageid,
              stageposition: stageposition,
            });
            selectedstageFeatures[idNewFeature] = true;
            e.feature.setId(idNewFeature);
            idNewFeature++;
            // Add the new feature to the
            // corresponding polygon vector.
            treasurehunt.roads[roadid].vector.getSource().addFeature(e.feature);
            // Adding the feature to the collection of
            // dirty multi-polygons.
            addNewFeatureToDirtySource(e.feature, originalStages, dirtyStages);
            // Clean the drawing vector.
            vectorDraw.getSource().clear();
            activateSaveButton();
            dirty = true;
          }
        });
        this.Polygon.on("drawstart", function () {
          drawStarted = true;
        });
      },
      getActive: function () {
        return this.Polygon.getActive();
      },
      setActive: function (active) {
        if (active) {
          this.Polygon.setActive(true);
        } else {
          this.Polygon.setActive(false);
        }
        map.getTargetElement().style.cursor = active ? "none" : "";
      },
    };
    $(document).keyup(function (e) {
      // If I press the esc key I stop drawing.
      if (e.keyCode === 27 && drawStarted) {
        // Esc.
        abortDrawing = true;
        Draw.Polygon.finishDrawing();
      }
    });
    Draw.init();
    // Enable Navmode.
    activateNavigationMode();
    deactivateEdition();
    // The snap interaction must be added after the Modify and
    // Draw interactions.
    // in order for its map browser event handlers to be fired
    // first. Its handlers.
    // are responsible of doing the snapping.
    var snap = new ol.interaction.Snap({
      source: vectorDraw.getSource(),
    });
    map.addInteraction(snap);
    // I load the features.
    fetchTreasureHunt(treasurehuntid);

    /**
     * Adds a new feature to the dirty source and updates the associated stage properties
     * @param {ol.Feature} dirtyFeature - The feature to be added to the dirty source
     * @param {ol.source.Vector} originalStages - The original source containing the stages
     * @param {ol.source.Vector} dirtySource - The destination source where the feature will be added
     * @description This function:
     * 1. Gets or creates a stage feature in the dirty source
     * 2. Updates the stage's idFeaturesPolygons property by appending the new feature's ID
     * 3. Appends the geometry of the dirty feature to the stage's geometry
     * 4. Removes warning if the stage was empty
     */
    function addNewFeatureToDirtySource(dirtyFeature, originalStages,dirtySource) {
      var stageid = dirtyFeature.get("stageid");
      var roadid = dirtyFeature.get("roadid");
      var feature = dirtySource.getFeatureById(stageid);
      if (!feature) {
        feature = originalStages.getFeatureById(stageid).clone();
        feature.setId(stageid);
        dirtySource.addFeature(feature);
      }
      if (feature.get("idFeaturesPolygons") === "empty") {
        feature.setProperties({
          idFeaturesPolygons: "" + dirtyFeature.getId(),
        });
        // I remove the warning.
        notEmptystage(stageid, roadid);
      } else {
        feature.setProperties({
          idFeaturesPolygons:
            feature.get("idFeaturesPolygons") + "," + dirtyFeature.getId(),
        });
      }
      feature.getGeometry().appendPolygon(dirtyFeature.getGeometry());
    }

    /**
     * Modifies features in the dirty source by updating their geometries based on vector features
     * @param {Array<ol.Feature>} dirtyFeatures - Array of OpenLayers features that need to be modified
     * @param {ol.source.Vector} originalStages - Original source containing the stage features
     * @param {ol.source.Vector} dirtySource - Destination source where modified features will be stored
     * @param {ol.layer.Vector} vector - Vector layer containing the polygon features
     */
    function modifyFeatureToDirtySource(dirtyFeatures, originalStages, dirtySource, vector) {
      dirtyFeatures.forEach(function (dirtyFeature) {
        var stageid = dirtyFeature.get("stageid");
        var feature = dirtySource.getFeatureById(stageid);
        var idFeaturesPolygons;
        if (!feature) {
          feature = originalStages.getFeatureById(stageid).clone();
          feature.setId(stageid);
          dirtySource.addFeature(feature);
        }
        var multipolygon = new ol.geom.MultiPolygon([]);
        // Get those multipolygons of vector layer .
        idFeaturesPolygons = feature.get("idFeaturesPolygons").split(",");
        for (var i = 0, j = idFeaturesPolygons.length; i < j; i++) {
          multipolygon.appendPolygon(
            vector
              .getSource()
              .getFeatureById(idFeaturesPolygons[i])
              .getGeometry()
              .clone()
          );
        }
        feature.setGeometry(multipolygon);
      });
    }

    /**
     * Updates the geometry and properties of features in a dirty source based on dirty features and a vector layer.
     * Ensures that features are cloned from the original stages if missing, and modifies their geometry and properties
     * based on the provided dirty features and vector layer.
     *
     * @param {Array<ol.Feature>} dirtyFeatures - An array of features that are marked as dirty and need processing.
     * @param {ol.source.Vector} originalStages - The original vector source containing the unmodified features.
     * @param {ol.source.Vector} dirtySource - The vector source containing the dirty features to be updated.
     * @param {ol.layer.Vector} vector - The vector layer used to retrieve geometry for updating features.
     */
    function removefeatureToDirtySource(dirtyFeatures, originalStages, dirtySource, vector) {
      dirtyFeatures.forEach(function (dirtyFeature) {
        var stageid = dirtyFeature.get("stageid");
        var roadid = dirtyFeature.get("roadid");
        var feature = dirtySource.getFeatureById(stageid);
        var idFeaturesPolygons;
        var remove;
        if (!feature) {
          feature = originalStages.getFeatureById(stageid).clone();
          feature.setId(stageid);
          dirtySource.addFeature(feature);
        }
        var multipolygon = new ol.geom.MultiPolygon([]);
        // Get those multipolygons of vector layer
        // which stageid isn't id of dirtyFeature.
        idFeaturesPolygons = feature.get("idFeaturesPolygons").split(",");
        for (var i = 0, j = idFeaturesPolygons.length; i < j; i++) {
          if (idFeaturesPolygons[i] != dirtyFeature.getId()) {
            multipolygon.appendPolygon(
              vector
                .getSource()
                .getFeatureById(idFeaturesPolygons[i])
                .getGeometry()
                .clone()
            );
          } else {
            remove = i;
          }
        }
        feature.setGeometry(multipolygon);
        if (multipolygon.getPolygons().length) {
          idFeaturesPolygons.splice(remove, 1);
          feature.setProperties({
            idFeaturesPolygons: idFeaturesPolygons.join(),
          });
        } else {
          feature.setProperties({
            idFeaturesPolygons: "empty",
          });
          emptystage(stageid, roadid);
        }
      });
    }

    /**
     * Defines the style to apply to a feature based on its properties and selection state.
     *
     * @param {ol.Feature} feature - The feature object from which the style is determined.
     * @returns {Array<ol.style.Style>} An array containing the appropriate style for the feature.
     *
     * The function checks the `stageposition` property of the feature and updates the text
     * of the `selectedstageStyle` and `defaultstageStyle` accordingly. If the feature is
     * selected (exists in `selectedstageFeatures`), it returns the `selectedstageStyle`.
     * Otherwise, it returns the `defaultstageStyle`.
     */
    function styleFunction(feature) {
      // Get the position from the feature properties.
      var stageposition = feature.get("stageposition");
      if (!isNaN(stageposition)) {
        selectedstageStyle.getText().setText("" + stageposition);
        defaultstageStyle.getText().setText("" + stageposition);
      }
      // if there is no level or its one we don't recognize,.
      // return the default style (in an array!).
      if (selectedstageFeatures[feature.getId()]) {
        return [selectedstageStyle];
      }
      // check the cache and create a new style for the stage.
      // level if its not been created before.
      // at this point, the style for the current level is in
      // the cache so return it (as an array!).
      return [defaultstageStyle];
    }

    /**
     * Fetches the treasure hunt data from the server and processes it to initialize the treasure hunt editor.
     *
     * @param {number} treasurehuntid - The ID of the treasure hunt to fetch.
     *
     * This function performs the following:
     * - Sends an AJAX request to fetch treasure hunt data using the provided ID.
     * - Handles the response to populate the treasure hunt editor with stages and roads.
     * - Converts road data into OpenLayers features and adds them to the map.
     * - Updates the UI with the fetched data, including the list panel and map layers.
     * - Handles errors and displays notifications if the fetch fails.
     */
    function fetchTreasureHunt(treasurehuntid) {
      var geojson = ajax.call([
        {
          methodname: "mod_treasurehunt_fetch_treasurehunt",
          args: {
            treasurehuntid: treasurehuntid,
          },
        },
      ]);
      geojson[0]
        .done(function (response) {
          $(".treasurehunt-editor-loader").hide();
          if (response.status.code) {
            notification.alert("Error", response.status.msg, "Continue");
          } else {
            var vector;
            // var geoJSONFeatures = response.treasurehunt.stages;
            var geoJSON = new ol.format.GeoJSON();
            var features;
            var roads = response.treasurehunt.roads;
            // Moodle 2 returns an object
            // with indexed properties
            // instead an array...
            if (!Array.isArray(roads)) {
              roads = Object.values(roads);
            }
            // I need to index every path
            // in the global object
            // treasurehunt.
            roads.forEach(function (road) {
              // I add the vectors to each road.
              // Cast string "0" or "1" to boolean.
              road.blocked = road.blocked == true;
              addroad2ListPanel(road.id, road.name, road.blocked);
              features = geoJSON.readFeatures(road.stages, {
                dataProjection: "EPSG:4326",
                featureProjection: mapprojection,
              });
              originalStages.addFeatures(features);
              delete road.stages;
              vector = new ol.layer.Vector({
                source: new ol.source.Vector({
                  projection: mapprojection,
                }),
                updateWhileAnimating: true,
                style: styleFunction,
              });
              features.forEach(function (feature) {
                if (feature.getGeometry() === null) {
                  feature.setGeometry(new ol.geom.MultiPolygon([]));
                }
                var polygons = feature.getGeometry().getPolygons();
                var idNewFeatures = "empty";
                var stageposition = feature.get("stageposition");
                var name = feature.get("name");
                var clue = feature.get("clue");
                var stageid = feature.getId();
                var blocked = road.blocked;
                for (var i = 0; i < polygons.length; i++) {
                  var newFeature = new ol.Feature(feature.getProperties());
                  newFeature.setProperties({
                    stageid: stageid,
                  });
                  var polygon = polygons[i];
                  newFeature.setGeometry(polygon);
                  newFeature.setId(idNewFeature);
                  if (i === 0) {
                    idNewFeatures = idNewFeature;
                  } else {
                    idNewFeatures = idNewFeatures + "," + idNewFeature;
                  }
                  idNewFeature++;
                  vector.getSource().addFeature(newFeature);
                }
                feature.setProperties({
                  idFeaturesPolygons: "" + idNewFeatures,
                });
                addstage2ListPanel(stageid, road.id, stageposition, name, clue, blocked);
                if (polygons.length === 0) {
                  emptystage(stageid);
                }
              });
              road.vector = vector;
              map.addLayer(vector);
              treasurehunt.roads[road.id] = road;
            });

            // Ordeno la lista de etapas.
            sortList();
            // I select the path of the URL if it exists or if not the first.
            if (typeof treasurehunt.roads[selectedroadid] !== "undefined") {
              roadid = selectedroadid;
              if (treasurehunt.roads[roadid].blocked) {
                deactivateAddstage();
              } else {
                activateAddstage();
              }
              selectRoad(roadid, treasurehunt.roads[roadid].vector, map);
            } else {
              selectfirstroad(treasurehunt.roads, map);
            }
          }
        })
        .fail(function (error) {
          $(".treasurehunt-editor-loader").hide();
          // console.log(error);
          notification.exception(error);
        });
    }

    // Panel functions .
    /**
     * Removes the specified features from the given vector layer and clears the selection.
     *
     * @param {Array} selectedFeatures - An array of features to be removed.
     * @param {ol.layer.Vector} vector - The vector layer from which the features will be removed.
     */
    function removefeatures(selectedFeatures, vector) {
      selectedFeatures.forEach(function (feature) {
        vector.getSource().removeFeature(feature);
      });
      selectedFeatures.clear();
    }
    /**
     * Selects the first road from the provided list of roads and updates the map accordingly.
     * If no roads are available, it disables the ability to add a stage and updates the UI.
     *
     * @param {Object} roads - An object containing road data, where each key is a road ID and the value is an object
     *  with road properties.
     * @param {Object} map - The map instance to update and interact with.
     */
    function selectfirstroad(roads, map) {
      var noroads = 0;
      for (var road in roads) {
        if (treasurehunt.roads.hasOwnProperty(road)) {
          noroads = 1;
          roadid = road;
          if (roads[roadid].blocked) {
            deactivateAddstage();
          } else {
            activateAddstage();
          }
          selectRoad(roadid, roads[roadid].vector, map);
          break;
        }
      }
      if (noroads === 0) {
        deactivateAddstage();
        $("#addroad").addClass("highlightbutton").blur();
        $("#stagelistpanel").addClass("invisible");
        map.updateSize();
      }
    }

    /**
     * Adds a stage to the list panel if it does not already exist.
     *
     * @param {number} stageid - The unique identifier for the stage.
     * @param {number} roadid - The unique identifier for the road associated with the stage.
     * @param {number} stageposition - The position of the stage in the list.
     * @param {string} name - The name of the stage.
     * @param {string} clue - The clue or description associated with the stage.
     * @param {boolean} blocked - Indicates whether the stage is blocked or not.
     *
     * This function dynamically creates a list item (`<li>`) element representing a stage
     * and appends it to the `#stagelist` element. If the stage is blocked, it adds a locked
     * icon and disables drag-and-drop functionality. If the stage is not blocked, it adds
     * drag-and-drop functionality and a delete icon. Additionally, it creates a dialog
     * element for displaying the stage's clue, which can be opened by clicking the info icon.
     * If a stage with the same `stageid` already exists, the function logs a message to the console.
     */
    function addstage2ListPanel(stageid, roadid, stageposition, name, clue, blocked) {
      if ($('#stagelist li[stageid="' + stageid + '"]').length < 1) {
        var li = $(
          '<li stageid="' + stageid + '" roadid="' + roadid + '" stageposition="' + stageposition + '"/>')
          .appendTo($("#stagelist"));
        li.addClass("ui-corner-all")
          .append("<div class='stagename'>" + name + "</div>")
          .append(
            "<div class='modifystage'>" +
            "<span class='ui-icon ui-icon-pencil'></span>" +
            "<span class='ui-icon ui-icon-info' data-id='#dialoginfo" +
            stageid +
            "'>" +
            "<div id='dialoginfo" + stageid + "' title='" + $($.parseHTML(name)).text() + "'>" +
            clue +
            "</div></span></div>"
          );
        if (blocked) {
          li.addClass("blocked").prepend(
            "<div class='nohandle validstage'>" +
            "<span class='ui-icon ui-icon-locked'></span>" +
            "<span class='sortable-number'>" +
            stageposition +
            "</span></div>"
          );
        } else {
          li.prepend(
            "<div class='handle validstage'>" +
            "<span class='ui-icon ui-icon-arrowthick-2-n-s'></span>" +
            "<span class='sortable-number'>" +
            stageposition +
            "</span></div>"
          );
          li.children(".modifystage").prepend(
            "<span class='ui-icon ui-icon-trash'></span>"
          );
        }
        $("#dialoginfo" + stageid).dialog({
          maxHeight: 500,
          autoOpen: false,
        });
      } else {
        // console.log(
        //   "El li con " + stageid + " no ha podido crearse porque ya existia uno"
        // );
      }
    }

    /**
     * Adds a road to the list panel if it does not already exist.
     *
     * @param {integer} roadid - The unique identifier for the road.
     * @param {string} name - The name of the road to be displayed.
     * @param {boolean} blocked - Indicates whether the road is blocked.
     */
    function addroad2ListPanel(roadid, name, blocked) {
      // If it doesn't exist I'll add it.
      if ($('#roadlist li[roadid="' + roadid + '"]').length < 1) {
        var li = $(
          '<li roadid="' + roadid + '" blocked="' + blocked + '"/>'
        ).appendTo($("#roadlist"));
        li.addClass("ui-corner-all")
          .append("<div class='roadname'>" + name + "</div>")
          .append(
            "<div class='modifyroad'><span class='ui-icon ui-icon-trash'></span>" +
            "<span class='ui-icon ui-icon-pencil'></span></div>"
          );
      }
    }
    /**
     * Deletes a road and its associated stages from the respective lists in the DOM.
     *
     * This function removes the `<li>` element corresponding to the specified road ID
     * from the road list (`#roadlist`) and all `<li>` elements with the same road ID
     * from the stage list (`#stagelist`).
     *
     * @param {integer} roadid - The ID of the road to be removed from the lists.
     */
    function deleteRoad2ListPanel(roadid) {
      var $li = $('#roadlist li[roadid="' + roadid + '"]');
      if ($li.length > 0) {
        var $lis = $('#stagelist li[roadid="' + roadid + '"]');
        // I remove the li from the road list.
        $li.remove();
        // I remove all li from the stagelist.
        $lis.remove();
      }
    }
    /**
     * Deletes a stage from the list panel and updates the remaining stages accordingly.
     *
     * @param {number} stageid - The ID of the stage to be deleted.
     * @param {boolean} dirtySource - Indicates whether the source data is marked as dirty.
     * @param {Array} originalStages - The original list of stages before any modifications.
     * @param {Array} vectorOfPolygons - A collection of polygons associated with the stages.
     *
     * This function removes the specified stage from the list panel, checks the remaining stages,
     * and relocates them as necessary to maintain the integrity of the list.
     */
    function deletestage2ListPanel(stageid, dirtySource, originalStages, vectorOfPolygons) {
      var $li = $('#stagelist li[stageid="' + stageid + '"]');
      if ($li.length > 0) {
        var roadid = $li.attr("roadid");
        var start_pos = $li.index('li[roadid="' + roadid + '"]');
        // I remove the li.
        $li.remove();
        var $stagelist = $("#stagelist li[roadid='" + roadid + "']");
        // I check the rest of the stages on the list.
        check_stage_list($stagelist);
        var $listlength = $stagelist.length;
        // I collect the rest.
        for (var i = 0; i <= start_pos - 1; i++) {
          relocatestageList(
            $stagelist,
            $listlength,
            i,
            dirtySource,
            originalStages,
            vectorOfPolygons
          );
        }
      }
    }
    /**
     * Sorts the list of items within the element with ID "stagelist" based on the
     * numerical value of the "stageposition" attribute in descending order.
     *
     * The function retrieves all <li> elements within the #stagelist container,
     * compares their "stageposition" attributes, and rearranges them accordingly.
     *
     * Note: This function assumes that the "stageposition" attribute contains
     * valid integer values for all <li> elements.
     */
    function sortList() {
      // I order the list .
      $("#stagelist li")
        .sort(function (a, b) {
          var contentA = parseInt($(a).attr("stageposition"));
          var contentB = parseInt($(b).attr("stageposition"));
          return contentA < contentB ? 1 : contentA > contentB ? -1 : 0;
        })
        .appendTo($("#stagelist"));
    }

    /**
     * Marks a stage as invalid and performs additional checks based on the road ID.
     *
     * This function updates the visual state of a stage by marking it as invalid.
     * If a road ID is provided, it highlights a button and checks if there are
     * multiple stages on the same road without geometry, displaying an error message if necessary.
     *
     * @param {integer} stageid - The ID of the stage to be marked as invalid.
     * @param {integer} [roadid] - The ID of the road associated with the stage. Optional.
     */
    function emptystage(stageid, roadid) {
      var $treasurehunt = $('#stagelist li[stageid="' + stageid + '"]');
      $treasurehunt
        .children(".handle,.nohandle")
        .addClass("invalidstage")
        .removeClass("validstage");
      // I check if there are any stages on this road without
      // geometry.
      if (roadid) {
        $("label[for='addradio']").addClass("highlightbutton");
        var $stagelist = $("#stagelist li[roadid='" + roadid + "']");
        if ($stagelist.length >= 2) {
          $("#erremptystage").removeClass("invisible");
        }
      }
    }

    /**
     * Updates the visual state of a stage and road in the treasure hunt editor.
     * Marks a stage as valid and removes invalid markers. If a road ID is provided,
     * checks if all stages on the road have valid geometry and updates the error message visibility.
     *
     * @param {integer} stageid - The ID of the stage to update.
     * @param {integer} [roadid] - The ID of the road to check for stages without geometry (optional).
     */
    function notEmptystage(stageid, roadid) {
      var $treasurehunt = $('#stagelist li[stageid="' + stageid + '"]');
      $treasurehunt
        .children(".handle, .nohandle")
        .addClass("validstage")
        .removeClass("invalidstage");
      if (roadid) {
        // I check if there are any stages on this road without
        // geometry.
        $("label[for='addradio']").removeClass("highlightbutton");
        var $stagelist = $("#stagelist li[roadid='" + roadid + "']");
        if ($stagelist.find(".invalidstage").length === 0) {
          $("#erremptystage").addClass("invisible");
        }
      }
    }

    /**
     * Enables the delete button by removing the disabled property from the element
     * with the ID "removefeature".
     */
    function activateDeleteButton() {
      $("#removefeature").prop("disabled", false);
    }
    /**
     * Disables the delete button with the ID "removefeature".
     */
    function deactivateDeleteButton() {
      $("#removefeature").prop("disabled", true);
    }
    /**
     * Enables the "Add Stage" button by removing the disabled property.
     */
    function activateAddstage() {
      $("#addstage").prop("disabled", false);
    }
    /**
     * Disables the "Add Stage" button by setting its "disabled" property to true.
     */
    function deactivateAddstage() {
      $("#addstage").prop("disabled", true);
    }
    /**
     * Disables the edit and draw modes by disabling their respective buttons
     * and activates the navigation mode.
     */
    function deactivateEdition() {
      $("#editmode").prop("disabled", true);
      $("#drawmode").prop("disabled", true);
      activateNavigationMode();
    }
    /**
     * Activates the navigation mode by updating the UI and disabling other modes.
     * This function modifies the CSS classes and properties of the mode buttons
     * to visually indicate the active navigation mode. It also disables the
     * drawing and modifying functionalities.
     *
     * @function activateNavigationMode
     * @returns {void}
     */
    function activateNavigationMode() {
      $("#editmode").removeClass("selectedbutton").prop("z-index", "Infinity");
      $("#drawmode").removeClass("selectedbutton").prop("z-index", "Infinity");
      $("#navmode")
        .prop("disabled", false)
        .addClass("selectedbutton")
        .blur()
        .prop("z-index", 999);
      Draw.setActive(false);
      Modify.setActive(false);
    }
    /**
     * Activate buttons and tools into Modify mode.
     */
    function activateModify() {
      $("#editmode").addClass("selectedbutton").blur().prop("z-index", 999);
      $("#drawmode").removeClass("selectedbutton").prop("z-index", "Infinity");
      $("#navmode").removeClass("selectedbutton").prop("z-index", "Infinity");
      Draw.setActive(false);
      Modify.setActive(true);
    }
    /**
     * Activate Draw mode. Update UI and tools.
     */
    function activateDraw() {
      $("#drawmode")
        .addClass("selectedbutton")
        .blur()
        .prop("z-index", "Infinity");
      $("#editmode").removeClass("selectedbutton").prop("z-index", "auto");
      $("#navmode").removeClass("selectedbutton").prop("z-index", "auto");
      Modify.setActive(false);
      Draw.setActive(true);
    }
    /**
     * Activate Edit mode. Update UI and tools.
     */
    function activateEdition() {
      $("#drawmode").prop("disabled", false);
      $("#editmode").prop("disabled", false);
      activateModify();
    }
    /**
     * Activate Save button.
     */
    function activateSaveButton() {
      $("#savestage").prop("disabled", false);
    }
    /**
     * Deactivate Save Button.
     */
    function deactivateSaveButton() {
      $("#savestage").prop("disabled", true);
    }
    /**
     * Move map to a poitn gracefully.
     * @param {ol.map} map
     * @param {ol.geom.point} point
     * @param {ol.extent} extent
     */
    function flyTo(map, point, extent) {
      var duration = 700;
      var view = map.getView();
      if (extent) {
        view.fit(extent, {
          duration: duration,
        });
      } else {
        view.animate({
          zoom: 19,
          center: point,
          duration: duration,
        });
      }
    }
    /**
     *
     * @param {array} $stagelist
     */
    function check_stage_list($stagelist) {
      if ($stagelist.length > 0) {
        $("#stagelistpanel").removeClass("invisible");
        map.updateSize();
      } else {
        $("#stagelistpanel").addClass("invisible");
        map.updateSize();
      }
      if ($stagelist.length < 2) {
        $("#addstage").addClass("highlightbutton").blur();
        $("#errvalidroad").removeClass("invisible");
        $("#erremptystage").addClass("invisible");
      } else if ($stagelist.find(".invalidstage").length > 0) {
        $("#addstage").removeClass("highlightbutton");
        $("#errvalidroad").addClass("invisible");
        $("#erremptystage").removeClass("invisible");
      } else {
        $("#addstage").removeClass("highlightbutton");
        $("#errvalidroad").addClass("invisible");
        $("#erremptystage").addClass("invisible");
      }
    }
    /**
     * Update the map to show a Road.
     * @param {integer} roadid
     * @param {array} vectorOfPolygons
     * @param {ol.map} map
     */
    function selectRoad(roadid, vectorOfPolygons, map) {
      // I clean all the selected features, hide all
      // the li and I only show the ones with the roadid.
      $("#stagelist li").removeClass("ui-selected").hide();
      var $stagelist = $("#stagelist li[roadid='" + roadid + "']");
      $stagelist.show();
      check_stage_list($stagelist);
      // If the li road is not marked I mark it.
      $("#roadlist li[roadid='" + roadid + "']").addClass("ui-selected");
      // I leave only the vector with the visible roadid .
      map.getLayers().forEach(function (layer) {
        if (layer instanceof ol.layer.Vector) {
          layer.setVisible(false);
        }
      });
      vectorOfPolygons.setVisible(true);
      if (vectorOfPolygons.getSource().getFeatures().length > 0) {
        flyTo(map, null, vectorOfPolygons.getSource().getExtent());
      }
    }
    /**
     *
     * @param {ol.layer} vectorOfPolygons
     * @param {ol.layer} vectorSelected
     * @param {ol.feature} selected
     * @param {ol.source.Vector} selectedFeatures
     * @param {ol.source.Vector} dirtySource
     * @param {*} originalStages
     * @returns
     */
    function selectstageFeatures(vectorOfPolygons, vectorSelected, selected,
                                selectedFeatures, dirtySource, originalStages) {
      vectorSelected.getSource().clear();
      // I deselect any previous feature.
      selectedFeatures.clear();
      // I reset the object.
      selectedstageFeatures = {};
      var feature = dirtySource.getFeatureById(selected);
      if (!feature) {
        feature = originalStages.getFeatureById(selected);
        if (!feature) {
          // I increase the version so that it reloads the
          // map and deselect the marked one
          // before.
          vectorOfPolygons.changed();
          return;
        }
      }
      if (feature.get("idFeaturesPolygons") === "empty") {
        // I increase the version so that it reloads the
        // map and deselect the one marked above.
        vectorOfPolygons.changed();
        return;
      }
      // I add the polygons to the object that stores the
      // selected polygons .
      // and I also add the vector to the object that does the
      // animation.
      var idFeaturesPolygons = feature.get("idFeaturesPolygons").split(",");
      for (var i = 0, j = idFeaturesPolygons.length; i < j; i++) {
        vectorSelected
          .getSource()
          .addFeature(
            vectorOfPolygons
              .getSource()
              .getFeatureById(idFeaturesPolygons[i])
              .clone()
          );
        selectedstageFeatures[idFeaturesPolygons[i]] = true;
      }
      // I place the map in the position of the stages
      // selected if the stage contains any features and .
      // delaying the time  to select the new
      // feature.
      if (vectorSelected.getSource().getFeatures().length) {
        flyTo(map, null, vectorSelected.getSource().getExtent());
      }
    }
    /**
     *
     * @param {integer} stageid
     * @param {integer} stageposition
     * @param {integer} roadid
     * @param {ol.source.Vector} dirtySource
     * @param {ol.source.Vector} originalStages
     * @param {*} vector
     */
    function relocatenostage( stageid, stageposition, roadid,
                              dirtySource, originalStages, vector) {
      var feature = dirtySource.getFeatureById(stageid);
      var idFeaturesPolygons;
      if (!feature) {
        feature = originalStages.getFeatureById(stageid).clone();
        feature.setId(stageid);
        dirtySource.addFeature(feature);
      }
      feature.setProperties({
        stageposition: stageposition,
      });
      if (feature.get("idFeaturesPolygons") !== "empty") {
        idFeaturesPolygons = feature.get("idFeaturesPolygons").split(",");
        for (var i = 0, j = idFeaturesPolygons.length; i < j; i++) {
          vector
            .getSource()
            .getFeatureById(idFeaturesPolygons[i])
            .setProperties({
              stageposition: stageposition,
            });
        }
      }
    }
    /**
     * Forms
     * @param {integer} stageid
     * @param {integer} idModule
     */
    function editFormstageEntry(stageid, idModule) {
      var url = "editstage.php?cmid=" + idModule + "&id=" + stageid;
      window.location.href = url;
    }
    /**
     * Forms
     * @param {integer} roadid
     * @param {integer} idModule
     */
    function newFormstageEntry(roadid, idModule) {
      var url = "editstage.php?cmid=" + idModule + "&roadid=" + roadid;
      window.location.href = url;
    }
    /**
     * Navigate to edit road page.
     * @param {integer} roadid
     * @param {integer} idModule
     */
    function editFormRoadEntry(roadid, idModule) {
      var url = "editroad.php?cmid=" + idModule + "&id=" + roadid;
      window.location.href = url;
    }
    /**
     * Forms
     * @param {integer} idModule
     */
    function newFormRoadEntry(idModule) {
      var url = "editroad.php?cmid=" + idModule;
      window.location.href = url;
    }
    /**
     * Delete a road in the server via AJAX.
     * @param {integer} roadid
     * @param {ol.source.Vector} dirtySource
     * @param {ol.source.Vector} originalStages
     * @param {integer} treasurehuntid
     * @param {integer} lockid
     */
    function deleteRoad(roadid, dirtySource, originalStages, treasurehuntid, lockid) {
      $(".treasurehunt-editor-loader").show();
      var json = ajax.call([
        {
          methodname: "mod_treasurehunt_delete_road",
          args: {
            roadid: roadid,
            treasurehuntid: treasurehuntid,
            lockid: lockid,
          },
        },
      ]);
      json[0]
        .done(function (response) {
          $(".treasurehunt-editor-loader").hide();
          if (response.status.code) {
            notification.alert("Error", response.status.msg, "Continue");
          } else {
            // I remove both the li from the road
            // as well all stage li
            // associates.
            deleteRoad2ListPanel(roadid);
            // I remove the feature of
            // dirtySource if I had it, .
            // of the originalStages and removed
            // the road of treasurehunt and
            // the map layer.
            map.removeLayer(treasurehunt.roads[roadid].vector);
            delete treasurehunt.roads[roadid];
            selectfirstroad(treasurehunt.roads, map);
            deactivateEdition();
            var features = originalStages.getFeatures();
            for (var i = 0; i < features.length; i++) {
              if (roadid === features[i].get("roadid")) {
                var dirtyFeature = dirtySource.getFeatureById(
                  features[i].getId()
                );
                if (dirtyFeature) {
                  dirtySource.removeFeature(dirtyFeature);
                }
                originalStages.removeFeature(features[i]);
              }
            }
          }
        })
        .fail(function (error) {
          $(".treasurehunt-editor-loader").hide();
          // console.log(error);
          notification.exception(error);
        });
    }
    /**
     * Delete stage from the server via ajax.
     * @param {integer} stageid
     * @param {ol.source.Vector} dirtySource
     * @param {ol.source.Vector} originalStages
     * @param {ol.source.Vector} vectorOfPolygons
     * @param {integer} treasurehuntid
     * @param {integer} lockid
     */
    function deletestage(stageid, dirtySource, originalStages,
                        vectorOfPolygons, treasurehuntid, lockid) {
      $(".treasurehunt-editor-loader").show();
      var json = ajax.call([
        {
          methodname: "mod_treasurehunt_delete_stage",
          args: {
            stageid: stageid,
            treasurehuntid: treasurehuntid,
            lockid: lockid,
          },
        },
      ]);
      json[0]
        .done(function (response) {
          $(".treasurehunt-editor-loader").hide();
          if (response.status.code) {
            notification.alert("Error", response.status.msg, "Continue");
          } else {
            var idFeaturesPolygons = false;
            var polygonFeature;
            var feature = dirtySource.getFeatureById(stageid);
            // Remove and relocate.
            deletestage2ListPanel( stageid, dirtySource, originalStages, vectorOfPolygons);
            // I remove the feature of
            // dirtySource if I had it and
            // all the polygons of the
            // polygon vector.
            if (!feature) {
              feature = originalStages.getFeatureById(stageid);
              if (feature.get("idFeaturesPolygons") !== "empty") {
                idFeaturesPolygons = feature
                  .get("idFeaturesPolygons")
                  .split(",");
              }
              originalStages.removeFeature(feature);
            } else {
              if (feature.get("idFeaturesPolygons") !== "empty") {
                idFeaturesPolygons = feature
                  .get("idFeaturesPolygons")
                  .split(",");
              }
              dirtySource.removeFeature(feature);
            }
            if (idFeaturesPolygons) {
              for (var i = 0, j = idFeaturesPolygons.length; i < j; i++) {
                polygonFeature = vectorOfPolygons
                  .getSource()
                  .getFeatureById(idFeaturesPolygons[i]);
                vectorOfPolygons.getSource().removeFeature(polygonFeature);
              }
            }
          }
        })
        .fail(function (error) {
          $(".treasurehunt-editor-loader").hide();
          // console.log(error);
          notification.exception(error);
        });
    }
    /**
     * Save stages.
     * @param {ol.source.Vector} dirtySource
     * @param {ol.source.Vector} originalStages
     * @param {integer} treasurehuntid
     * @param {function} callback
     * @param {array} options
     * @param {integer} lockid
     */
    function savestages(dirtySource, originalStages, treasurehuntid, callback, options, lockid) {
      $(".treasurehunt-editor-loader").show();
      var geojsonformat = new ol.format.GeoJSON();
      var dirtyfeatures = dirtySource.getFeatures();
      var features = [];
      var auxfeature;
      // Remove unnecessary feature properties .
      dirtyfeatures.forEach(function (dirtyfeature) {
        auxfeature = dirtyfeature.clone();
        auxfeature.unset("idFeaturesPolygons");
        auxfeature.unset("name");
        auxfeature.unset("clue");
        auxfeature.unset("treasurehuntid");
        auxfeature.setId(dirtyfeature.getId());
        features.push(auxfeature);
      });
      var geojsonstages = geojsonformat.writeFeaturesObject(features, {
        dataProjection: "EPSG:4326",
        featureProjection: mapprojection,
      });
      var json = ajax.call([
        {
          methodname: "mod_treasurehunt_update_stages",
          args: {
            stages: geojsonstages,
            treasurehuntid: treasurehuntid,
            lockid: lockid,
          },
        },
      ]);
      json[0]
        .done(function (response) {
          $(".treasurehunt-editor-loader").hide();
          if (response.status.code) {
            notification.alert("Error", response.status.msg, "Continue");
          } else {
            var originalFeature;
            // I pass the "dirty" features to the object
            // with the original features.
            dirtySource.forEachFeature(function (feature) {
              originalFeature = originalStages.getFeatureById(feature.getId());
              originalFeature.setProperties(feature.getProperties());
              originalFeature.setGeometry(feature.getGeometry());
            });
            // I clean my object that keeps the
            // dirty features.
            dirtySource.clear();
            // Disable the save button.
            deactivateSaveButton();
            dirty = false;
            if (typeof callback === "function" && options instanceof Array) {
              callback.apply(null, options);
            }
          }
        })
        .fail(function (error) {
          $(".treasurehunt-editor-loader").hide();
          // console.log(error);
          notification.alert("Error", error.message, "Continue");
        });
    }

    $(".searchaddress")
      .autocomplete({
        minLength: 4,
        source: function (request, response) {
          var term = request.term;
          // Abort xhr request if a new one arrives
          if (osmGeocoderXHR) {
            osmGeocoderXHR.abort();
          }
          osmGeocoderXHR = OSMGeocoder.search(term)
            .done((data) => {
              if (data.length === 0) {
                response();
                return;
              }
              var total = [];
              for (var i = 0, l = data.length; i < l; i++) {
                var latitude;
                var longitude;
                latitude = data[i].lat;
                longitude = data[i].lon;
                var result = {
                  value: data[i].display_name,
                  latitude: latitude,
                  longitude: longitude,
                  boundingbox: data[i].boundingbox,
                };
                total[i] = result;
              }
              response(total);
            })
            .fail(() => {
              response();
            })
            .always(() => {
              osmGeocoderXHR = null;
            });
        },
        select: function (event, ui) {
          if (ui.item.boundingbox) {
            var extend = [];
            extend[0] = parseFloat(ui.item.boundingbox[2]);
            extend[1] = parseFloat(ui.item.boundingbox[0]);
            extend[2] = parseFloat(ui.item.boundingbox[3]);
            extend[3] = parseFloat(ui.item.boundingbox[1]);
            extend = ol.proj.transformExtent(
              extend,
              "EPSG:4326",
              mapprojection
            );
            flyTo(map, null, extend);
          } else {
            var point = ol.proj.fromLonLat([
              ui.item.longitude,
              ui.item.latitude,
            ]);
            flyTo(map, point);
          }
        },
        autoFocus: true,
      })
      .on("click", function () {
        $(this).autocomplete("search", $(this).value);
      });
    // Necessary for regulating the width of the
    // autocomplete.
    $.ui.autocomplete.prototype._resizeMenu = function () {
      var ul = this.menu.element;
      ul.outerWidth(this.element.outerWidth());
    };
    $("#drawmode").css("position", "relative").on("click", activateDraw);
    $("#editmode").css("position", "relative").on("click", activateModify);
    $("#navmode")
      .css("position", "relative")
      .on("click", activateNavigationMode);
    $("#addstage").on("click", function () {
      if (dirty) {
        savestages(
          dirtyStages,
          originalStages,
          treasurehuntid,
          newFormstageEntry,
          [roadid, idModule],
          lockid
        );
      } else {
        newFormstageEntry(roadid, idModule);
      }
    });
    $("#addroad").on("click", function () {
      if (dirty) {
        savestages(dirtyStages, originalStages, treasurehuntid,
                  newFormRoadEntry, [idModule], lockid);
      } else {
        newFormRoadEntry(idModule);
      }
    });
    $("#removefeature").on("click", function () {
      notification.confirm(
        strings["areyousure"],
        strings["removewarning"],
        strings["confirm"],
        strings["cancel"],
        function () {
          removefeatureToDirtySource(selectedFeatures, originalStages, dirtyStages, treasurehunt.roads[roadid].vector);
          removefeatures(selectedFeatures, treasurehunt.roads[roadid].vector);
          // Disable the delete button and active on save changes.
          deactivateDeleteButton();
          activateSaveButton();
          dirty = true;
        }
      );
    });
    $("#savestage").on("click", function () {
      savestages(dirtyStages, originalStages, treasurehuntid, null, null, lockid);
    });
    $("#stagelist").on("click", ".ui-icon-info, .ui-icon-alert", function () {
      var id = $(this).data("id");
      // Open dialogue.
      $(id).dialog("open");
      // Remove focus from the buttons.
      $(".ui-dialog :button").blur();
    });
    $("#stagelist").on("click", ".ui-icon-trash", function () {
      var $this_li = $(this).parents("li");
      notification.confirm(
        strings["areyousure"],
        strings["removewarning"],
        strings["confirm"],
        strings["cancel"],
        function () {
          var stageid = parseInt($this_li.attr("stageid"));
          deletestage(stageid, dirtyStages, originalStages, treasurehunt.roads[roadid].vector, treasurehuntid, lockid);
        }
      );
    });
    $("#stagelist").on("click", ".ui-icon-pencil", function () {
      // I'm looking for the stageid of the li containing the
      // selected trash can.

      var stageid = parseInt($(this).parents("li").attr("stageid"));
      // If it's dirty I save the stage.
      if (dirty) {
        savestages(dirtyStages, originalStages, treasurehuntid, editFormstageEntry, [stageid, idModule], lockid);
      } else {
        editFormstageEntry(stageid, idModule);
      }
    });

    $("#stagelist").on("click", "li", function (e) {
      if ($(e.target).is(".handle ,.nohandle, .ui-icon , .sortable-number")) {
        e.preventDefault();
        return;
      }
      $(this).addClass("ui-selected").siblings().removeClass("ui-selected");
      // I select the stageid of my attribute
      // custom.
      stageposition = parseInt($(this).attr("stageposition"));
      stageid = parseInt($(this).attr("stageid"));
      // I delete the previous selection of
      // features and look for the same kind.
      selectstageFeatures(treasurehunt.roads[roadid].vector, vectorSelected, stageid,
                          selectedFeatures, dirtyStages, originalStages);
      activateEdition();
      // If the stage has no geometry I highlight the add button.
      if ($(this).find(".invalidstage").length > 0) {
        $("label[for='addradio']").addClass("highlightbutton");
      } else {
        $("label[for='addradio']").removeClass("highlightbutton");
      }
      // Stop drawing if I change stage.
      if (drawStarted) {
        abortDrawing = true;
        Draw.Polygon.finishDrawing();
      }
    });
    $("#roadlist").on("click", "li", function (e) {
      if ($(e.target).is(".ui-icon")) {
        e.preventDefault();
        return;
      }
      $(this).addClass("ui-selected").siblings().removeClass("ui-selected");
      // Selecciono el stageid de mi atributo
      // custom.
      // Borro las etapas seleccionadas.
      selectedstageFeatures = {};
      // Paro de dibujar si cambio de camino.
      if (drawStarted) {
        abortDrawing = true;
        Draw.Polygon.finishDrawing();
      }
      roadid = $(this).attr("roadid");
      var blocked = $(this).attr("blocked");
      if (parseInt(blocked) || blocked === "true") {
        deactivateAddstage();
      } else {
        activateAddstage();
      }
      selectRoad(roadid, treasurehunt.roads[roadid].vector, map);
      deactivateEdition();
      // Scroll to editor.
      var scrolltop;
      if ($("header[role=banner]").css("position") === "fixed") {
        scrolltop =
          parseInt($(".treasurehunt-editor").offset().top) -
          parseInt($("header[role=banner]").outerHeight(true));
      } else {
        scrolltop = parseInt($(".treasurehunt-editor").offset().top);
      }
      $("html, body").animate(
        {
          scrollTop: scrolltop,
        },
        500
      );
    });
    $("#roadlist").on("click", ".ui-icon-pencil", function () {
      // Busco el roadid del li que contiene el
      // lapicero seleccionado.
      var roadid = parseInt($(this).parents("li").attr("roadid"));
      // Si esta sucio guardo el escenario.
      if (dirty) {
        savestages(dirtyStages, originalStages, treasurehuntid,
                    editFormRoadEntry, [roadid, idModule], lockid);
      } else {
        editFormRoadEntry(roadid, idModule);
      }
    });
    $("#roadlist").on("click", ".ui-icon-trash", function () {
      var $this_li = $(this).parents("li");
      notification.confirm(
        strings["areyousure"],
        strings["removeroadwarning"],
        strings["confirm"],
        strings["cancel"],
        function () {
          var roadid = parseInt($this_li.attr("roadid"));
          deleteRoad(
            roadid,
            dirtyStages,
            originalStages,
            treasurehuntid,
            lockid
          );
        }
      );
    });
    map.on("pointermove", function (evt) {
      if (evt.dragging || Draw.getActive() || !Modify.getActive()) {
        return;
      }
      var pixel = map.getEventPixel(evt.originalEvent);
      var hit = map.forEachFeatureAtPixel(pixel, function (feature, layer) {
        if (selectedstageFeatures[feature.getId()]) {
          if (layer === null) {
            return false;
          }
          var selected = false;
          selectedFeatures.forEach(function (featureSelected) {
            if (feature === featureSelected) {
              selected = true;
            }
          });
          return selected ? false : true;
        }
        return false;
      });
      map.getTargetElement().style.cursor = hit ? "pointer" : "";
    });
    // Evento para que funcione bien el boton de cerrar en
    // dispositivos tactiles.
    $(document).on("touchend", ".ui-dialog-titlebar-close", function () {
      $(this).parent().siblings(".ui-dialog-content").dialog("close");
    });
    /**
     * Toggle class.
     * @param {*} v
     * @returns action to do.
     */
    function tog(v) {
      return v ? "removeClass" : "addClass";
    }
    $(".searchaddress").on("input", function () {
      $(".closeicon")[tog(this.value)]("invisible");
    });
    $(".closeicon").on("touchstart click", function (ev) {
      ev.preventDefault();
      $(this).addClass("invisible");
      $(".searchaddress").val("").change().autocomplete("close");
    });
    // Al salirse.
    window.onbeforeunload = function (e) {
      var message = strings["savewarning"],
        e = e || window.event;
      if (dirty) {
        // For IE and Firefox.
        if (e) {
          e.returnValue = message;
        }

        // For Safari.
        return message;
      }
    };
  }
  /**
   * Calculate bbox and scales.
   * @param {object} custommapconfig
   * @param {ol.projection|string} mapprojection
   * @param {boolean} referencetocenter
   * @returns extent info.
   */
  function calculateCustomImageExtent(custommapconfig, mapprojection, referencetocenter) {
    var customimageextent = ol.proj.transformExtent(custommapconfig.bbox, 'EPSG:4326', mapprojection);
    if (custommapconfig.preserveaspectratio == true) {
      // Round bbox and scales to allow vectorial SVG rendering. (Maintain ratio.)
      // var bboxwidth = customimageextent[2] - customimageextent[0];
      var bboxheight = customimageextent[3] - customimageextent[1];
      var centerwidth = (customimageextent[2] + customimageextent[0]) / 2;
      var centerheight = (customimageextent[3] + customimageextent[1]) / 2;

      var ratiorealmap = Math.round(bboxheight / custommapconfig.imgheight);
      var adjwidth = Math.round(custommapconfig.imgwidth * ratiorealmap);
      var adjheight = Math.round(custommapconfig.imgheight * ratiorealmap);
      if (referencetocenter) {
        // Use center point as reference.
        customimageextent = [centerwidth - adjwidth / 2, centerheight - adjheight / 2,
        centerwidth + adjwidth / 2, centerheight + adjheight / 2];
      } else {
        // Use bottom-left point as reference.ç
        customimageextent = [customimageextent[0], customimageextent[1],
        customimageextent[0] + adjwidth, customimageextent[1] + adjheight];
        // console.log('Using bottom-left as reference.' + customimageextent);
      }
    }
    return customimageextent;
  }
export default init;
