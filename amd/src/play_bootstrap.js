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
 * TODO describe module play_bootstrap_new
 *
 * @module     mod_treasurehunt/play_bootstrap_new
 * @author Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @copyright  2025 Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import $ from "jquery";
import url from "core/url";
import ol from "mod_treasurehunt/ol";
import ajax from "core/ajax";
import OSMGeocoder from "mod_treasurehunt/osm-geocoder";
import viewgpx from "mod_treasurehunt/viewgpx";
import {get_strings as getStrings, get_string as getString} from "core/str";
import webqr from "mod_treasurehunt/webqr";
import {setup as setupTouchGestureGuard} from "mod_treasurehunt/touch_gesture_guard";
import {init as initParticipantProgress} from "mod_treasurehunt/participant_progress";
// Side‑effect only imports
import "mod_treasurehunt/dropdown";

/**
 * @module    mod_treasurehunt/play
 * @package
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @author Adrian Rodriguez <huorwhisp@gmail.com>
 * @author Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
let init = {
  playtreasurehunt: function(cmid, treasurehuntid, playwithoutmoving, groupmode,
    gameupdatetime, tracking, user,
    custommapconfig, customplayerconfig = null, previewroadid = 0) {

    // I18n strings.
    let terms = ["stageovercome", "failedlocation", "stage", "stagename", "stageclue",
      "question", "noanswerselected", "timeexceeded", "searching", "continue", "noattempts",
      "aerialview", "roadview", "noresults", "startfromhere", "nomarks", "updates", "activitytoendwarning",
      "huntcompleted", "discoveredlocation", "answerwarning", "error", "pegmanlabel", "webserviceerror",
      "successlocation", "clue", "nextclue", "nextclues", "baselayers", "overlaylayers",
      "basemap_arcgis_desc", "basemap_bing", "basemap_bing_desc", "basemap_google_road",
      "basemap_google_road_desc", "basemap_google_satellite", "basemap_google_satellite_desc",
      "basemap_loaderror", "basemap_osm_desc", "basemap_stadia_dark", "basemap_stadia_dark_desc",
      "basemap_stadia_smooth", "basemap_stadia_smooth_desc", "basemap_stadia_toner",
      "basemap_stadia_toner_desc", "basemap_stadia_watercolor", "basemap_stadia_watercolor_desc",
    ];
    // Console.log("loading i18n strings");
    let stringsqueried = terms.map((term) => {
      return {key: term, component: "treasurehunt"};
    });
    // I18n = i18nplay; // Use globally passed strings. Moodle 3.8 core/str broke with jquery 2.1.4.
    getStrings(stringsqueried).then((strings) => {
      let i18n = [];
      for (let i = 0; i < terms.length; i++) {
        i18n[terms[i]] = strings[i]; // JPC: TODO: Global strings.
      }
      // I18n = i18nplay;
      // Detect custom image.
      if (
        typeof custommapconfig != "undefined" &&
        custommapconfig &&
        custommapconfig.custombackgroundurl
      ) {
        // Console.log("Detecting custom background image dimensions.");
        // Detect image size.
        let img = new Image();
        img.addEventListener("load", function() {
          custommapconfig.imgwidth = this.naturalWidth;
          custommapconfig.imgheight = this.naturalHeight;

          initplaytreasurehunt($, i18n, cmid, treasurehuntid, playwithoutmoving, groupmode,
            gameupdatetime, tracking,
            user, custommapconfig, customplayerconfig, previewroadid);
        });
        img.src = custommapconfig.custombackgroundurl;
      } else {
        initplaytreasurehunt(
          $, i18n, cmid, treasurehuntid, playwithoutmoving, groupmode,
          gameupdatetime, tracking,
          user, custommapconfig, customplayerconfig, previewroadid);
      }
    });
  }, // End of function playtreasurehunt.
};

/**
 * Calculate customimageextent.
 * @param {Object} custommapconfig The custom map configuration.
 * @param {string} mapprojection The map projection to use.
 * @param {boolean} referencetocenter If true, the extent is calculated from the center of the image.
 */
function calculateCustomImageExtent(custommapconfig, mapprojection, referencetocenter = false) {
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
      // Console.log('Using bottom-left as reference.' + customimageextent);
    }
  }
  return customimageextent;
}

/**
 * Create an OpenLayers canvas source that maps an image to four geographic corners.
 * @param {Array} corners Image corners in NW, NE, SE, SW order, as longitude/latitude pairs.
 * @param {HTMLImageElement} image Loaded image element.
 * @param {string} projection The map projection.
 * @returns {Object} OpenLayers canvas image source.
 */
function createAffineImageSource(corners, image, projection) {
  var source;
  source = new ol.source.ImageCanvas({
    projection: projection,
    ratio: 1,
    canvasFunction: function(extent, resolution, pixelRatio, size) {
      var canvas = document.createElement('canvas');
      // ImageCanvas already passes size in device pixels.
      canvas.width = Math.max(1, Math.round(size[0]));
      canvas.height = Math.max(1, Math.round(size[1]));
      if (!image.complete || !image.naturalWidth) {
        return canvas;
      }
      var projected = corners.map(function(point) {
        return ol.proj.transform(point, 'EPSG:4326', projection);
      });
      var nw = projected[0];
      var ne = projected[1];
      var sw = projected[3];
      var toCanvas = function(point) {
        return [(point[0] - extent[0]) / resolution * pixelRatio,
          (extent[3] - point[1]) / resolution * pixelRatio];
      };
      var origin = toCanvas(nw);
      var right = toCanvas(ne);
      var bottom = toCanvas(sw);
      var context = canvas.getContext('2d');
      context.setTransform(
        (right[0] - origin[0]) / image.naturalWidth,
        (right[1] - origin[1]) / image.naturalWidth,
        (bottom[0] - origin[0]) / image.naturalHeight,
        (bottom[1] - origin[1]) / image.naturalHeight,
        origin[0], origin[1]
      );
      context.drawImage(image, 0, 0);
      return canvas;
    }
  });
  image.addEventListener('load', function() {
    source.changed();
  }, {once: true});
  return source;
}
/**
 * Initialization function.
 * Init map, layers, styles, interactions and functions.
 * @param {jQuery} $ The jQuery object.
 * @param {Array} strings The i18n strings.
 * @param {int} cmid The course module id.
 * @param {int} treasurehuntid The treasure hunt id.
 * @param {boolean} playwithoutmoving If true, the player does not move.
 * @param {boolean} groupmode If true, the game is played in group mode.
 * @param {int} gameupdatetime The game update time in milliseconds.
 * @param {boolean} tracking If true, the player is being tracked.
 * @param {Object} user The user object.
 * @param {Object} custommapconfig The custom map configuration.
 * @param {Object} playerconfig The custom player configurations for extension.
 * @param {number} previewroadid Road selected for a manager's preview.
 */
function initplaytreasurehunt(
  $, strings, cmid, treasurehuntid, playwithoutmoving, groupmode, gameupdatetime,
  tracking, user, custommapconfig, playerconfig = null, previewroadid = 0) {

  setLoading(true);
  // The initialize response supplies the current cursors for later polls.
  let lastattemptid = 0;
  let lastroadtimestamp = 0;
  // Cast to boolean.
  playwithoutmoving = playwithoutmoving == true;
  groupmode = groupmode == true;
  tracking = tracking == true;
  let isfirststage = false;
  let nextstagefeature = null;
  let nextstagefeatures = [];
  let availableclues = [];
  let selectedclueindex = 0;
  let mapprojection = "EPSG:3857";
  let custombaselayer = null;
  let usegeographictools = true;
  let defaultzoom = 15;
  let supportsTouch = "ontouchstart" in window || navigator.msMaxTouchPoints;
  let basemapConfig = {};
  try {
    basemapConfig = JSON.parse(document.getElementById("treasurehunt-basemap-config").textContent);
  } catch (error) {
    basemapConfig = {};
  }
  let customplayerconfig;
  set_player_config(playerconfig);
  initParticipantProgress({
    enabled: Boolean(customplayerconfig?.showparticipantprogress),
    treasurehuntid,
    previewroadid,
    roadid: Number(customplayerconfig?.participantroadid || previewroadid || 0),
  });
  // Check custom player configuration.
  if (customplayerconfig && customplayerconfig.defaultzoom) {
    defaultzoom = customplayerconfig.defaultzoom;
  }
  // Support customized base layers.
  if (custommapconfig) {
    if (custommapconfig.custombackgroundurl) {
      var customimageextent = calculateCustomImageExtent(custommapconfig, mapprojection, false);
      var customimagesource;
      if (Array.isArray(custommapconfig.imagecorners) && custommapconfig.imagecorners.length === 4) {
        var customimage = new Image();
        customimage.src = custommapconfig.custombackgroundurl;
        customimagesource = createAffineImageSource(custommapconfig.imagecorners, customimage, mapprojection);
      } else {
        customimagesource = new ol.source.ImageStatic({
          url: custommapconfig.custombackgroundurl,
          imageExtent: customimageextent,
        });
      }

      defaultzoom = 5;
      custombaselayer = new ol.layer.Image({
        title: custommapconfig.layername,
        name: custommapconfig.layername,
        type: custommapconfig.layertype,
        source: customimagesource,
        opacity: 1.0,
      });
    } else if (custommapconfig.wmsurl) {
      // Console.log("config custom wms server: " + custommapconfig.wmsurl);
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
        options.source = new ol.source.XYZ({url: custommapconfig.wmsurl});
      } else if (custommapconfig.layerservicetype === "arcgis") {
        options.source = new ol.source.TileArcGISRest({url: custommapconfig.wmsurl});
      }

      if (custommapconfig.bbox[0] && custommapconfig.bbox[1] && custommapconfig.bbox[2] && custommapconfig.bbox[3]) {
        let customwmsextent = ol.proj.transformExtent(custommapconfig.bbox, "EPSG:4326", mapprojection);
        options.extent = customwmsextent;
      }
      custombaselayer = new ol.layer.Tile(options);
      custombaselayer.set('name', custommapconfig.layername);
    }

    usegeographictools = custommapconfig.geographic;
  }
  if (usegeographictools === false) {
    // Console.log("geographic tools disabled");
    playwithoutmoving = true;
    $("#autolocate").hide();
  }

  let successurl = url.imageUrl("success_mark", "treasurehunt");
  let failureurl = url.imageUrl("failure_mark", "treasurehunt");
  let markerurl = url.imageUrl("bootstrap/my_location_3", "treasurehunt");
  let treasurechesturl = url.imageUrl("treasurechest_loading", "treasurehunt");
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
  const loadingRequests = new Set();
  /* -------------------------------Styles-----------------------------------*/
  let textStyle = new ol.style.Text({
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
  let successAttemptStyle = new ol.style.Style({
    image: new ol.style.Icon({
      anchor: [0.5, 1],
      opacity: 1,
      scale: 0.5,
      src: successurl,
    }),
    text: textStyle,
    zIndex: "Infinity",
  });
  let failedAttemptStyle = new ol.style.Style({
    image: new ol.style.Icon({
      anchor: [0.5, 1],
      opacity: 1,
      scale: 0.5,
      src: failureurl,
    }),
    text: textStyle,
    zIndex: "Infinity",
  });
  let defaultSuccessAttemptStyle = new ol.style.Style({
    image: new ol.style.Icon({
      anchor: [0.5, 1],
      opacity: 1,
      scale: 0.75,
      src: successurl,
    }),
    text: selectText,
    zIndex: "Infinity",
  });
  let failSelectAttemptStyle = new ol.style.Style({
    image: new ol.style.Icon({
      anchor: [0.5, 1],
      opacity: 1,
      scale: 0.75,
      src: failureurl,
    }),
    text: selectText,
    zIndex: "Infinity",
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
  /**
   * Style function for the attemp features.
   * @param {ol.Feature} feature The feature to style.
   */
  function attemptStyle(feature) {
    let stageposition = feature.get("stageposition");
    if (!feature.get("geometrysolved")) {
      // Don't change the scale with the map. This is confusing failstageStyle.getImage().setScale((view.getZoom() / 30));.
      failedAttemptStyle.getText().setText("" + stageposition);
      return [failedAttemptStyle];
    }
    // Don't change the scale with the map. This is confusing  defaultstageStyle.getImage().setScale((view.getZoom() / 100));.
    successAttemptStyle.getText().setText("" + stageposition);
    return [successAttemptStyle];
  }
  /**
   * Style function for the stage features.
   * @param {ol.Feature} feature The feature to style.
   */
  function stageStyle(feature) {
    let stageposition = feature.get("stageposition");
    // Let shouldrender = feature.get("shouldrender");
    if (customplayerconfig.shownextareahint || stageposition == 1) {
      let fill = new ol.style.Fill({
        color: "rgba(0,0,0,0.1)",
      });
      let stroke = new ol.style.Stroke({
        color: "#0097a7",
        width: 2,
      });
      let text;
      if (feature.get("stageposition") === 1) {
        text = strings.startfromhere;
      } else {
        text = strings.stage + " " + stageposition;
      }
      let styles = new ol.style.Style({
        image: new ol.style.Circle({
          fill: fill,
          stroke: stroke,
          radius: 5,
        }),
        fill: fill,
        stroke: stroke,
        text: new ol.style.Text({
          text: text,
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
    } else {
      return [];
    }
  }
  /**
   * Selected feature style.
   * @param {ol.Feature} feature
   * @returns
   */
  function select_style_function(feature) {
    let stageposition = feature.get("stageposition");
    if (!feature.get("geometrysolved")) {
      failSelectAttemptStyle.getText().setText("" + stageposition);
      return [failSelectAttemptStyle];
    }
    defaultSuccessAttemptStyle.getText().setText("" + stageposition);
    return [defaultSuccessAttemptStyle];
  }

  /**
   * Style for manual marker.
   * @returns {Array<ol.style.Style>|ol.style.Style} An array of styles for the marker.
   */
  function markerFeatureStyle() {
    let styles = [];
    let positionstyle = new ol.style.Style({
      fill: new ol.style.Fill({
        color: 'rgba(255, 0, 0, 0.2)',
      }),
      image: new ol.style.Icon({
        src: './pix/bootstrap/position_marker.png',
        rotateWithView: true,
        scale: 0.10,
        anchor: [0.5, 0.5],
      }),
    });
    let geom1 = markerFeature.getGeometry();
    let target = nearestStageFeature(geom1);
    let geom2 = target ? target.getGeometry() : null;
    // Check if the marker is in the zone.
    let isinzone = geom2 && geom1 && geom2.intersectsCoordinate(geom1.getFirstCoordinate());
    // If in-zone hint is enabled and if the marker is in the zone change the style.
    if (customplayerconfig.showinzonehint == true && isinzone) {
      positionstyle = new ol.style.Style({
        fill: new ol.style.Fill({
          color: 'rgba(0, 0, 255, 0.2)',
        }),
        image: new ol.style.Icon({
          src: './pix/bootstrap/position_marker_in_zone.png',
          rotateWithView: true,
          scale: 0.10,
          anchor: [0.5, 0.5],
        }),
      });
    }
    styles.push(positionstyle);
    // If the marker is not in the zone, show the heading hint.
    if (customplayerconfig.showheadinghint == true && !isinzone) {
      let style = headingFeatureStyle(markerFeature);
      if (style) {
        styles.push(style);
      }
    }
    // If the distance hint is enabled, add the distance label.
    if (customplayerconfig.showdistancehint == true && !isinzone) {
      let style = distanceLabelStyle(markerFeature);
      if (style) {
        styles.push(style);
      }
    }
    // Add pegman marker.
    let pegman = new ol.style.Style({
      image: new ol.style.Icon({
        anchor: [0.5, 0.9],
        opacity: 1,
        scale: 1,
        src: markerurl,
      }),
    });
    styles.push(pegman);
    return styles;
  }
  /**
   * Style for distance label.
   * @param {ol.Feature} feature The feature to style.
   */
  function distanceLabelStyle(feature) {
    let geomfeature = feature.getGeometry();
    let target = nearestStageFeature(geomfeature);
    let geomtarget = target ? target.getGeometry() : null;
    // Calculate distance to the target.
    let originpoint = geomfeature.getFirstCoordinate();
    let closestpoint = geomtarget && geomfeature ? geomtarget.getClosestPoint(originpoint) : null;
    let linestring = geomtarget && geomfeature ? new ol.geom.LineString([originpoint, closestpoint]) : null;
    let distance = linestring ? ol.Sphere.getLength(linestring) : null;
    if (distance === null) {
      // No distance to show.
      return null;
    }
    let distanceText = "";
    // Format the distance text in correct units.
    if (distance > 1000) {
      distanceText = (distance / 1000).toFixed(2) + " km";
    } else if (distance > 0) {
      distanceText = distance.toFixed(0) + " m";
    }
    let textStyle = new ol.style.Style({
      placement: "point",
      fill: new ol.style.Fill({
        color: '#000',
      }),
      stroke: new ol.style.Stroke({
        color: '#fff',
        width: 2,
      }),
      text: new ol.style.Text({
        padding: [5, 5, 5, 5],
        offsetY: 25,
        text: distanceText,
        scale: 2,
        fill: new ol.style.Fill({
          color: '#000',
        }),
        stroke: new ol.style.Stroke({
          color: '#fff',
          width: 2,
        }),
        backgroundFill: new ol.style.Fill({
          color: 'rgba(255, 255, 255, 0.7)',
        }),
        backgroundStroke: new ol.style.Stroke({
          color: '#000',
          width: 1,
        }),
      })
    });
    return textStyle;
  }

  /**
   * Style for position marker.
   * @param {ol.Feature} feature  The feature to style.
   * @returns {Array<ol.style.Style>|ol.style.Style} An array of styles for the marker.
   */
  function positionLayerStyle(feature) {
    if (feature) {
      let style = new ol.style.Style({
        fill: new ol.style.Fill({
          color: 'rgba(0, 0, 255, 0.2)',
        }),
        image: new ol.style.Icon({
          src: './pix/bootstrap/position_marker.png',
          rotateWithView: true,
          scale: 0.10,
          anchor: [0.5, 0.5],
        }),
      });
      return [style];
    } else {
      return null;
    }
  }
  /**
   * Style for heading marker.
   * @param {ol.Feature} feature
   * @returns {ol.style.Style} An array of styles for the marker.
   */
  function headingFeatureStyle(feature) {
    let target = nearestStageFeature(feature.getGeometry());
    if (!target) {
      return null;
    }
    let rotation = calculateHeading(feature.getGeometry(), target.getGeometry()) ?? null;
    if (rotation === null) {
      return null;
    }
    let style = new ol.style.Style({
      image: new ol.style.Icon({
        src: './pix/bootstrap/position_marker_heading_large.png',
        rotateWithView: true,
        rotation: rotation,
        scale: 0.10,
        anchor: [0.5, 0.67],
      }),
    });
    return style;
  }
  /**
   * Find the closest eligible stage to a point on the map.
   * @param {ol.geom.Geometry} geometry Current position geometry.
   * @returns {ol.Feature|null} Closest stage.
   */
  function nearestStageFeature(geometry) {
    if (!geometry || nextstagefeatures.length === 0) {
      return null;
    }
    const origin = geometry.getFirstCoordinate();
    let closest = null;
    let minimum = Infinity;
    nextstagefeatures.forEach((candidate) => {
      const point = candidate.getGeometry().getClosestPoint(origin);
      const squared = (point[0] - origin[0]) ** 2 + (point[1] - origin[1]) ** 2;
      if (squared < minimum) {
        minimum = squared;
        closest = candidate;
      }
    });
    return closest;
  }
  /* -------------------------------Layers---------------------------------*/
  let layers = [];
  let geoJSONFormat = new ol.format.GeoJSON();
  let attemptSource = new ol.source.Vector({
    projection: "EPSG:3857",
  });
  let stageSource = new ol.source.Vector({
    projection: "EPSG:3857",
  });
  let attemptslayer = new ol.layer.Vector({
    source: attemptSource,
    style: attemptStyle,
  });
  let stagelayer = new ol.layer.Vector({
    source: stageSource,
    style: stageStyle,
  });
  let layersbase = [];
  let layersoverlay = [];
  const basemapEntries = [];
  const allowedBasemaps = new Set(basemapConfig.allowedbasemaps || []);
  const thumbnails = "js/cesium-1.145.0/Widgets/Images/ImageryProviders/";
  const stadiaEnabled = Boolean(basemapConfig.stadiadomainauth || basemapConfig.stadiakey);
  const googleSessions = {};
  const googleSessionRequests = {};
  const googleLayers = new Map();
  let defaultBaseLayer = null;
  let basemapSelectionRevision = 0;

  /**
   * Add an authorized and available base map to the catalog.
   *
   * @param {string} id Stable base map identifier.
   * @param {string} name Layer name.
   * @param {string} description Layer description.
   * @param {string} thumbnail Local thumbnail filename.
   * @param {Object|null} source OpenLayers source.
   * @param {boolean} available Whether the provider is configured.
   * @param {Function|null} prepare Work to perform before selecting the layer.
   * @returns {Object|null} The base layer, if available.
   */
  const addBaseMap = (id, name, description, thumbnail, source, available = true, prepare = null) => {
    if (!available || !allowedBasemaps.has(id)) {
      return null;
    }
    const layer = new ol.layer.Tile({source: source || undefined, visible: false});
    layer.set("name", name);
    layersbase.push(layer);
    basemapEntries.push({name, description, thumbnail: thumbnails + thumbnail, layer, prepare});
    return layer;
  };

  /**
   * Request a Google Map Tiles session only when the player selects that layer.
   *
   * @param {string} mapType Google map type.
   * @returns {Promise<Object>} A valid session.
   */
  const getGoogleSession = (mapType) => {
    const cached = googleSessions[mapType];
    if (cached && Number(cached.expiry) * 1000 > Date.now() + 60000) {
      return Promise.resolve(cached);
    }
    if (!googleSessionRequests[mapType]) {
      const locale = (document.documentElement.lang || navigator.language || "en-US").replace("_", "-");
      googleSessionRequests[mapType] = fetch("https://tile.googleapis.com/v1/createSession?key=" +
        encodeURIComponent(basemapConfig.googlekey), {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify({mapType, language: locale, region: (locale.split("-")[1] || "US").toUpperCase()}),
      }).then((response) => {
        if (!response.ok) {
          throw new Error("Google Map Tiles session unavailable");
        }
        return response.json();
      }).then((session) => {
        if (!session.session || !session.expiry) {
          throw new Error("Invalid Google Map Tiles session");
        }
        googleSessions[mapType] = session;
        return session;
      }).finally(() => {
        delete googleSessionRequests[mapType];
      });
    }
    return googleSessionRequests[mapType];
  };

  /**
   * Add a Google layer that requests its tile session when selected.
   *
   * @param {string} id Stable base map identifier.
   * @param {string} mapType Google map type.
   * @param {string} name Layer name.
   * @param {string} description Layer description.
   * @param {string} thumbnail Local thumbnail filename.
   */
  const addGoogleMap = (id, mapType, name, description, thumbnail) => {
    let layer;
    const prepare = async() => {
      const session = await getGoogleSession(mapType);
      const previous = googleLayers.get(layer);
      if (previous && previous.session.session === session.session) {
        return;
      }
      const source = new ol.source.XYZ({
        url: "https://tile.googleapis.com/v1/2dtiles/{z}/{x}/{y}?session=" +
          encodeURIComponent(session.session) + "&key=" + encodeURIComponent(basemapConfig.googlekey),
        maxZoom: 22,
        attributions: ["© Google Maps"],
      });
      layer.setSource(source);
      googleLayers.set(layer, {source, session});
    };
    layer = addBaseMap(id, name, description, thumbnail, null, Boolean(basemapConfig.googlekey), prepare);
  };

  /**
   * Add Stadia raster tiles when the domain or API key is authorized.
   *
   * @param {string} id Stable base map identifier.
   * @param {string} style Stadia style name.
   * @param {string} name Layer name.
   * @param {string} description Layer description.
   * @param {string} thumbnail Local thumbnail filename.
   * @param {string} extension Raster image extension.
   * @param {number} maxZoom Maximum native zoom level.
   */
  const addStadiaMap = (id, style, name, description, thumbnail, extension, maxZoom = 20) => {
    if (!stadiaEnabled || !allowedBasemaps.has(id)) {
      return;
    }
    const key = basemapConfig.stadiadomainauth || !basemapConfig.stadiakey ? "" :
      "?api_key=" + encodeURIComponent(basemapConfig.stadiakey);
    const source = stadiaEnabled ? new ol.source.XYZ({
      url: "https://tiles.stadiamaps.com/tiles/" + style + "/{z}/{x}/{y}." + extension + key,
      maxZoom,
      attributions: [
        '© <a href="https://stadiamaps.com/attribution/">Stadia Maps</a>',
        '© <a href="https://openmaptiles.org/">OpenMapTiles</a>',
        '© <a href="https://openstreetmap.org/copyright">OpenStreetMap</a>',
        ...(style.startsWith("stamen_") ? ['© <a href="https://stamen.com/">Stamen Design</a>'] : []),
      ],
    }) : null;
    addBaseMap(id, name, description, thumbnail, source);
  };

  /**
   * Wait for Bing imagery metadata before changing the visible base map.
   *
   * @param {Object} source Bing Maps source.
   * @returns {Promise<void>} Resolves when the source is ready.
   */
  const waitForBing = (source) => new Promise((resolve, reject) => {
    if (source.getState() === "ready") {
      resolve();
      return;
    }
    if (source.getState() === "error") {
      reject(new Error("Bing imagery metadata unavailable"));
      return;
    }
    let listener;
    const finish = (error) => {
      clearTimeout(timeout);
      ol.Observable.unByKey(listener);
      if (error) {
        reject(error);
      } else {
        resolve();
      }
    };
    const timeout = setTimeout(() => finish(new Error("Bing imagery metadata timed out")), 12000);
    listener = source.on("change", () => {
      if (source.getState() === "ready") {
        finish();
      } else if (source.getState() === "error") {
        finish(new Error("Bing imagery metadata unavailable"));
      }
    });
  });

  if (!custommapconfig || custommapconfig.onlybase === false) {
    defaultBaseLayer = addBaseMap("osm", "OpenStreetMap", strings.basemap_osm_desc,
      "openStreetMap.png", new ol.source.OSM());
    const bingSource = basemapConfig.bingkey && allowedBasemaps.has("bing_aerial_labels") ? new ol.source.BingMaps({
      key: basemapConfig.bingkey,
      imagerySet: "AerialWithLabels",
      maxZoom: 19,
    }) : null;
    addBaseMap("bing_aerial_labels", strings.basemap_bing,
      strings.basemap_bing_desc,
      "bingAerialLabels.png", bingSource, Boolean(bingSource),
      bingSource ? () => waitForBing(bingSource) : null);
    addGoogleMap("google_satellite", "satellite", strings.basemap_google_satellite,
      strings.basemap_google_satellite_desc,
      "googleSatellite.png");
    addGoogleMap("google_roadmap", "roadmap", strings.basemap_google_road,
      strings.basemap_google_road_desc,
      "googleRoadmap.png");
    addBaseMap("arcgis_world_imagery", "ArcGIS World Imagery", strings.basemap_arcgis_desc,
      "ArcGisMapServiceWorldImagery.png", new ol.source.XYZ({
        url: "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
        attributions: [
          "Tiles © Esri — Esri, DigitalGlobe, Earthstar Geographics, CNES/Airbus DS, USDA," +
          " USGS, AeroGRID, IGN, and the GIS User Community",
        ],
      }));
    addStadiaMap("stadia_alidade_smooth", "alidade_smooth", strings.basemap_stadia_smooth,
      strings.basemap_stadia_smooth_desc, "stadiaAlidadeSmooth.png", "png");
    addStadiaMap("stadia_alidade_smooth_dark", "alidade_smooth_dark", strings.basemap_stadia_dark,
      strings.basemap_stadia_dark_desc, "stadiaAlidadeSmoothDark.png", "png");
    addStadiaMap("stadia_stamen_toner", "stamen_toner", strings.basemap_stadia_toner,
      strings.basemap_stadia_toner_desc, "stamenToner.png", "png");
    addStadiaMap("stadia_stamen_watercolor", "stamen_watercolor", strings.basemap_stadia_watercolor,
      strings.basemap_stadia_watercolor_desc, "stamenWatercolor.png", "jpg", 16);
  }
  if (custombaselayer) {
    if (custommapconfig.layertype != "overlay") {
      layersbase.push(custombaselayer);
      defaultBaseLayer = custombaselayer;
      basemapEntries.push({
        name: custommapconfig.layername,
        description: strings.baselayers,
        thumbnail: custommapconfig.custombackgroundurl || "pix/basemaps/road.jpg",
        layer: custombaselayer,
      });
    } else {
      layersoverlay.push(custombaselayer);
    }
  }
  if (!defaultBaseLayer && basemapEntries.length) {
    defaultBaseLayer = basemapEntries[0].layer;
    if (basemapEntries[0].prepare) {
      basemapEntries[0].prepare().catch(() => toast(strings.basemap_loaderror));
    }
  }
  let layergroup = new ol.layer.Group({layers: layersbase});
  // Create placement for a popup over user marker.
  var overlay = viewgpx.createCoordsOverlay(
    "#mapplay",
    "css/playerbootstrap/ol-popup.css",
    strings.pegmanlabel
  );

  // Select the configured base map, or OpenStreetMap when there is none.
  layergroup.getLayers().forEach((layer) => {
    layer.setVisible(layer === defaultBaseLayer);
  });

  let view = new ol.View({
    center: [0, 0],
    zoom: 4,
    minZoom: 3,
    maxZoom: 23,
  });
  let select = new ol.interaction.Select({
    layers: [attemptslayer],
    style: select_style_function,
    filter: (feature) => {
      if (feature.get("is_stage") === true) {
        return false;
      }
      return true;
    },
  });
  let accuracyFeature = new ol.Feature();
  accuracyFeature.setProperties({name: "user_accuracy"});
  accuracyFeature.setStyle(accuracyFeatureStyle);
  let positionFeature = new ol.Feature();
  positionFeature.setProperties({name: "user_position"});
  let userPositionSource = new ol.source.Vector({
      features: [accuracyFeature, positionFeature],
    });
  let userPositionLayer = new ol.layer.Vector({
    source: userPositionSource,
    style: positionLayerStyle
  });
  let markerFeature = new ol.Feature();
  markerFeature.setGeometry(null);
  markerFeature.setStyle(markerFeatureStyle);
  let markerSource = new ol.source.Vector({
      features: [markerFeature],
    });
  let markerLayer = new ol.layer.Vector({
    source: markerSource,
  });
  layers.push(layergroup);
  layers = layers.concat(layersoverlay);
  layers = layers.concat([stagelayer, userPositionLayer, markerLayer, attemptslayer]);
  // New Custom zoom.
  let zoom = new ol.control.Zoom({
    target: "navigation",
    className: "custom-zoom",
  });

  let map = new ol.Map({
    layers: layers,
    overlays: [overlay],
    controls: [zoom, new ol.control.Attribution({collapsed: false})],
    target: "mapplay",
    view: view,
    loadTilesWhileAnimating: true,
    loadTilesWhileInteracting: true,
  });
  let googleAttributionTimer;
  let googleAttributionRevision = 0;
  /** Refresh the provider's required copyright notice for the visible Google viewport. */
  const updateGoogleAttribution = () => {
    const active = [...googleLayers.keys()].find((layer) => layer.getVisible());
    if (!active) {
      return;
    }
    const {source, session} = googleLayers.get(active);
    const extent = ol.proj.transformExtent(view.calculateExtent(map.getSize()), "EPSG:3857", "EPSG:4326");
    const values = {
      session: session.session,
      key: basemapConfig.googlekey,
      zoom: Math.min(22, Math.max(0, Math.round(view.getZoom()))),
      north: Math.min(85, extent[3]),
      south: Math.max(-85, extent[1]),
      east: Math.min(180, extent[2]),
      west: Math.max(-180, extent[0]),
    };
    const revision = ++googleAttributionRevision;
    fetch("https://tile.googleapis.com/tile/v1/viewport?" + new URLSearchParams(values)).then((response) => {
      if (!response.ok) {
        throw new Error("Google Maps viewport unavailable");
      }
      return response.json();
    }).then((details) => {
      if (revision === googleAttributionRevision && active.getVisible()) {
        source.setAttributions(["© Google Maps", details.copyright || ""]);
      }
      return details;
    }).catch(() => {
      // Retain the basic Google Maps attribution when viewport metadata is unavailable.
    });
  };
  map.on("moveend", () => {
    clearTimeout(googleAttributionTimer);
    googleAttributionTimer = setTimeout(updateGoogleAttribution, 400);
  });
  // Do not let a completed touch gesture place the virtual GPS marker.
  // OpenLayers still receives all pointer events and handles pinch/rotate itself.
  const touchGuard = setupTouchGestureGuard(map.getViewport());
  map.addInteraction(select);
  // It initializes the game.
  renew_source(false, true);
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
  /**
   * Check if there is a usable position.
   */
  function validateposition() {
    if (playwithoutmoving && !markerFeature.getGeometry()) {
      toast(strings.nomarks);
    } else {
      renew_source(true, false);
    }
  }
  /**
   * Center the map on the current position.
   */
  function autocentermap() {
    const position = geolocation.getPosition();
    if (position) {
      fly_to(map, position);
    }
  }
  /**
   * Move the view animating to a point or extent.
   * @param {ol.Map} map
   * @param {ol.Coordinate} point
   * @param {ol.Extent} extent
   */
  function fly_to(map, point, extent) {
    let duration = 700;
    let view = map.getView();
    if (extent && extent[0] !== Infinity) {
      view.fit(extent, {
        duration: duration,
      });
    } else if (point) {
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
    // Let position holds the potition to be evaluated. undef if no evaluation requested
    let position;
    let currentposition;
    let markLocation;
    let answerid;
    // Get the position from the marker (it may be synced with geolocation).
    // if (playwithoutmoving) {
    //   coordinates = markerFeature.getGeometry();
    // } else {
    //   coordinates = positionFeature.getGeometry();
    // }
    markLocation = markerFeature.getGeometry();
    if (markLocation) {
      currentposition = geoJSONFormat.writeGeometryObject(markLocation, {
        dataProjection: "EPSG:4326",
        featureProjection: "EPSG:3857",
      });
    }
    if (selectedanswerid) {
      answerid = selectedanswerid;
    }
    if (location) {
      position = currentposition;
    }
    const loadingRequest = (initialize || selectedanswerid || location) ? beginLoading() : null;
    let currentpositionarg = tracking && !playwithoutmoving ? currentposition : null; // Only for tracking in mobility.
    let params = {
      treasurehuntid: treasurehuntid,
      previewroadid: previewroadid,
      attemptid: lastattemptid,
      roadtimestamp: lastroadtimestamp,
      playwithoutmoving: playwithoutmoving,
      groupmode: groupmode,
      initialize: initialize,
      location: position,
      selectedanswerid: answerid,
      qoaremoved: qoaremoved,
      qrtext: qrtext,
    };
    if (currentpositionarg) {
      params.currentposition = currentpositionarg;
    }
    let geojson;
    try {
      geojson = ajax.call([
        {
          methodname: "mod_treasurehunt_user_progress",
          args: {
            userprogress: params,
          },
        },
      ]);
    } catch (error) {
      finishLoading(loadingRequest);
      showCommunicationError(error);
      return;
    }
    geojson[0]
      .always(() => {
        finishLoading(loadingRequest);
        // Wait for the first response before polling with its returned cursors.
        if (initialize && !interval) {
          interval = setInterval(() => renew_source(false, false), gameupdatetime);
        }
      })
      .done((response) => {
        qoaremoved = response.qoaremoved;
        roadfinished = response.roadfinished;
        $("#cluebutton").prop("disabled", roadfinished);
        if (roadfinished) {
          closeModal("#cluepage");
        }
        $("#roadendedstage").html(response.endmessage || "").toggleClass("d-none", !response.endmessage);
        available = response.available;

        // If I have sent a location or an answer I print out whether it is correct or not.
        if (location || selectedanswerid) {
          if (response.status !== null && available) {
            toast(response.status.msg, response.status.msg === strings.successlocation);
          }
          // MarkerFeature.setGeometry(null);
        }
        // If change the game mode (mobile or static).
        if (playwithoutmoving != response.playwithoutmoving) {
          playwithoutmoving = response.playwithoutmoving;
          if (!playwithoutmoving) {
            // Enable geolocation.
            geolocation.setTracking(true);
            // Move marker to GPS position.
            let position = positionFeature.getGeometry();
            if (position) {
              markerFeature.setGeometry(position);
            }
          }
        }
        // If change the group mode.
        if (groupmode != response.groupmode) {
          groupmode = response.groupmode;
        }
        // Update player configuration.
        set_player_config(response.playerconfig);

        // Update stages layer.
        let loadedstagefeatures = [];
        if (response && response.nextstage) {
          loadedstagefeatures = geoJSONFormat.readFeatures(response.nextstage,
                                              {
                                                dataProjection: "EPSG:4326",
                                                featureProjection: "EPSG:3857",
                                              });
        }
        let newnextstagefeature = loadedstagefeatures[0] ?? null;
        let stagepositionold = nextstagefeatures.map((stage) => stage.get("stageposition")).join(",");
        let stagepositionnew = loadedstagefeatures.map((stage) => stage.get("stageposition")).join(",");
        isfirststage = newnextstagefeature && newnextstagefeature.get("stageposition") === 1;
        let isfirstload = nextstagefeature === null;
        // If the next stage is first or different from the previous one, update it.
        if (stagepositionold !== stagepositionnew || (newnextstagefeature && nextstagefeature === null)) {

          nextstagefeature = newnextstagefeature;
          nextstagefeatures = loadedstagefeatures;
          stageSource.clear();
          // Add the feature to be shown in the map.
          nextstagefeatures.forEach((stage) => {
            stage.set("shouldrender", (isfirststage && stage.get("stageposition") === 1)
              || customplayerconfig.shownextareahint);
            stage.set("is_stage", true);
          });
          stageSource.addFeatures(nextstagefeatures);
          // If the stage is the first one, center the map on it.
          if (isfirststage === true && (isfirstload || initialize)) {
            fitmap = true;
            map.getView().fit(nextstagefeature.getGeometry().getExtent(),
              {size: map.getSize(), padding: [40, 40, 40, 40], maxZoom: 18});
          }
        }
        if (response.clues) {
          const selectedclue = availableclues[selectedclueindex] ?? null;
          availableclues = response.clues;
          selectedclueindex = Math.max(0,
            availableclues.findIndex((clue) => selectedclue && clue.stageid === selectedclue.stageid
              && clue.html === selectedclue.html));
        }

        if (
          lastattemptid !== response.attemptid ||
          lastroadtimestamp !== response.roadtimestamp ||
          initialize ||
          !available
        ) {
          lastattemptid = response.attemptid;
          lastroadtimestamp = response.roadtimestamp;
          if (response.attempthistory.length > 0) {
            attemptshistory = response.attempthistory;
            changesinattemptshistory = true;
          }

          // If it is different from null, which indicates that it has been updated.
          if (response.attempts && response.attempts.features.length > 0) {
            if (response.attempts.features.length > 0) {
              attemptSource.clear();
              attemptSource.addFeatures(
                geoJSONFormat.readFeatures(response.attempts, {
                  dataProjection: "EPSG:4326",
                  featureProjection: "EPSG:3857",
                })
              );
            }
            // Fitmap = true;
            // fit_map_to_sources([attemptsource]);
          }
          // Check if it exists, which indicates that it has been updated.
          if (response.lastsuccessfulstage) {
            lastsuccessfulstage = response.lastsuccessfulstage;
            changesinlastsuccessfulstage = true;
            if (!roadfinished) {
              openModal("#cluepage");
            }
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
          // Center on previous attempts when loading, unless the clue already revealed the next area.
          if ((!response.lastsuccessfulstage && isfirststage || initialize)
              && !(response.lastsuccessfulstage && !roadfinished && customplayerconfig.shownextareahint
                  && nextstagefeature)) {
            fitmap = true;
            if (isfirststage && isfirstload) {
              map.getView().fit(nextstagefeature.getGeometry().getExtent(),
                {size: map.getSize(), padding: [40, 40, 40, 40], maxZoom: 18});
            } else if (initialize && response.lastsuccessfulstage?.stagegeometry) {
              const completedstagefeatures = geoJSONFormat.readFeatures(
                JSON.parse(response.lastsuccessfulstage.stagegeometry),
                {dataProjection: "EPSG:4326", featureProjection: "EPSG:3857"}
              );
              const completedstageextent = ol.extent.createEmpty();
              completedstagefeatures.forEach((feature) => {
                ol.extent.extend(completedstageextent, feature.getGeometry().getExtent());
              });
              if (!ol.extent.isEmpty(completedstageextent)) {
                map.getView().fit(completedstageextent, {
                  size: map.getSize(), padding: [40, 40, 40, 40], maxZoom: 18,
                });
                fitmap = false;
              } else {
                fit_map_to_sources([attemptSource]);
              }
            } else {
              // If it is not the first stage, fit the map to the attempts.
              fit_map_to_sources([attemptSource]);
            }
          }
          apply_lastsuccessfulstage();
          set_question();
          set_attempts_history();
        }
        renderAvailableClues();
        const dialogmessages = response.infomsg.filter((msg) => msg !== strings.successlocation);
        if (dialogmessages.length > 0) {
          let body = "";
          infomsgs.forEach((msg) => {
            body += "<p>" + msg + "</p>";
          });
          dialogmessages.forEach((msg) => {
            infomsgs.push(msg);
            body += "<p>" + msg + "</p>";
          });
          $("#notificationsPopup .update-list").html(body);
          $("#notificationsPopup").off("modal:close.treasurehuntclue");
          openModal("#notificationsPopup");
          if (!roadfinished) {
            // Show the clue after the notification only while the road is in progress.
            $("#notificationsPopup").one("modal:close.treasurehuntclue", () => {
              if (!roadfinished) {
                openModal("#cluepage");
              }
            });
          }
        }
        if (!roadfinished) {
          $("#roadended").hide();
        }
        if (roadfinished || !available) {
          $("#validatelocation").hide();
          $("#question_button").hide();
          $("#roadended").show();
          // MarkerFeature.setGeometry(null);
          playwithoutmoving = false;
          clearInterval(interval);
          $("#mapplay").css("opacity", "0.8");
        }
      })
      .fail((error) => {
        showCommunicationError(error);
        // ClearInterval(interval);
      });
  }
  /**
   * Show a service failure after releasing any loading overlay.
   *
   * @param {Object} error The service error.
   */
  function showCommunicationError(error) {
    const detail = error?.error || error?.message || error?.errorcode || "";
    const message = error?.errorcode === "generalexceptionmessage"
      ? "System Error: " + detail : strings.webserviceerror + " : " + detail;
    $("#errorPopup .play-modal-content").text(message);
    openModal("#errorPopup");
  }
  /**
   * Update player configuration.
   * @param {Object} playerconfig The player configuration object.
   */
  function set_player_config(playerconfig) {
    // Update the player configuration with the new values.
    if (playerconfig) {
      // Merge the player configuration with the custom player configuration.
      customplayerconfig = Object.assign(customplayerconfig || {}, playerconfig);
      // Enable of disable tools.
      if (customplayerconfig.searchpaneldisabled == true) {
        $("#searchbutton").hide();
      } else {
        $("#searchbutton").show();
      }
      if (customplayerconfig.localizationbuttondisabled == true) {
        $("#autolocate").hide();
      } else if (usegeographictools) {
        $("#autolocate").show();
      }
    } else {
      // Initialize with defaults.
      customplayerconfig = {
        searchpaneldisabled: false,
        localizationbuttondisabled: false,
        showdistancehint: false,
        showheadinghint: false,
        showinzonehint: false,
        shownextareahint: false,
        defaultzoom: 15,
      };
    }
  }

  /**
   * Fit viewport to features in feature collection.
   * global fitmap shortcut to avoid races of fitting the map to sources.
   * global attemptsource source of the attempt features.
   * global stagesource source of the stage features.
   */
  // function fit_map_to_all_sources() {
  //   fit_map_to_sources([attemptsource, stagesource]);
  // }
  /**
   * Fit map to stagesource.
   * @param {Array<ol.sources.Vector>} sources
   */
  function fit_map_to_sources(sources) {
    if (fitmap) {
      let extent = ol.extent.createEmpty();
      sources.forEach((source) => {
        extent = ol.extent.extend(extent, source.getExtent());
      });
      if (extent[0] === Infinity) {
        fitmap = false;
        return;
      }
      let size = Math.max(extent[2] - extent[0], extent[3] - extent[1]);
      let p = [(extent[0] + extent[2]) / 2, (extent[1] + extent[3]) / 2];
      if (size > 0) {
        // Buffer the extent to better fit the map.
        extent = ol.extent.buffer(extent, size * 0.25);
        fly_to(map, null, extent);
      } else {
        fly_to(map, p);
      }
      // Reset the fitmap flag.
      fitmap = false;
    }
  }
  /**
   * Fill the details of the last successful stage.
   */
  function apply_lastsuccessfulstage() {
    if (changesinlastsuccessfulstage) {
      // Clean color styles from clue.
      lastsuccessfulstage.clue = lastsuccessfulstage.clue.replace(
        /color/gm,
        "color-disabled"
      );
      $("#lastsuccessfulstagelegacy").html(lastsuccessfulstage.clue);

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
  /** Render the available clues as accessible Bootstrap tabs. */
  function renderAvailableClues() {
    const container = $("#availableclues");
    const clueBody = $("#lastsuccessfulstageclue");
    const legacyClue = $("#lastsuccessfulstagelegacy");
    const tabs = $("#availablecluetabs").empty();
    const content = $("#availablecluecontent").empty();
    if (availableclues.length === 0) {
      const showLegacy = roadfinished || !available || Boolean(lastsuccessfulstage.clue);
      container.addClass("d-none");
      legacyClue.toggle(showLegacy);
      clueBody.toggle(showLegacy);
      $("#lastsuccessfulstageheading").toggle(showLegacy);
      $("#cluetitle").text(strings.nextclue);
      return;
    }
    container.removeClass("d-none");
    legacyClue.hide();
    clueBody.show();
    $("#lastsuccessfulstageheading").hide();
    $("#cluetitle").text(availableclues.length > 1 ? strings.nextclues : strings.nextclue);
    availableclues.forEach((clue, index) => {
      $("<button>", {
        type: "button",
        id: "cluetab-" + index,
        "class": "nav-link",
        role: "tab",
        text: strings.clue + " " + clue.position,
        "aria-controls": "cluepanel-" + index,
      }).on("click", () => {
        selectedclueindex = index;
        tabs.children().removeClass("active").attr("aria-selected", "false");
        tabs.children().eq(index).addClass("active").attr("aria-selected", "true");
        content.children().removeClass("is-active").attr("aria-hidden", "true");
        content.children().eq(index).addClass("is-active").attr("aria-hidden", "false");
        fitMapToSelectedClue();
      }).appendTo(tabs);
      $("<div>", {
        id: "cluepanel-" + index,
        "class": "treasurehunt-clue-pane" + (index === selectedclueindex ? " is-active" : ""),
        role: "tabpanel",
        "aria-labelledby": "cluetab-" + index,
        "aria-hidden": index === selectedclueindex ? "false" : "true",
      }).html(clue.html).appendTo(content);
    });
    tabs.children().eq(selectedclueindex).addClass("active").attr("aria-selected", "true");
  }
  /** Frame the geometry associated with the selected clue, if the map may reveal it. */
  function fitMapToSelectedClue() {
    if (!customplayerconfig.shownextareahint) {
      return;
    }
    const clue = availableclues[selectedclueindex];
    const target = clue ? nextstagefeatures.find((stage) => Number(stage.getId()) === Number(clue.stageid))
      : nextstagefeature;
    if (target) {
      const extent = target.getGeometry().getExtent();
      map.getView().fit(extent, {size: map.getSize(), padding: [40, 40, 40, 40], maxZoom: 18});
    }
  }
  /**
   * Fill in the question form with the last successful stage question.
   */
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
      changesinquestionstage = false;
    }
  }
  /**
   * Fill the attempts history in historylist DIV.
   */
  function set_attempts_history() {
    // I'm checking to see if there have been any changes since the last time.
    if (changesinattemptshistory) {
      let $historylist = $("#historylist"); // TODO: Why the variable starts with $?
      // Lo reinicio
      $historylist.html("");
      changesinattemptshistory = false;
      if (attemptshistory.length === 0) {
        $("<li>" + strings.noattempts + "</li>").appendTo($historylist);
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
  /**
   * Add a Layer to the layerlist container.
   * It adds a button to the layer list that toggles the visibility of the layer.
   * @param {ol.layer.Base} layer
   */
  function add_layer_to_list(layer) {
    let link = $("<button>", {
      type: "button",
      "class": "list-group-item list-group-item-action close-modal",
    }).click(() => {
      layer.setVisible(!layer.getVisible());
    });
    let name = $("<div>", {"class": "th-basemap-copy"}).append(
      $("<strong>").append($.parseHTML(layer.get("name"))),
      $("<small>", {text: strings.overlaylayers})
    );
    let checkboxContent = $("<i>", {
      "class": "fa fa-check-circle" + (layer.getVisible() ? "" : " unchecked"),
      "aria-hidden": "true",
    });
    let linkContent = $("<div>", {
      "class": "layer-item",
    }).append($("<img>", {src: "pix/basemaps/road.jpg", alt: "", width: 72, height: 72,
      "class": "th-basemap-thumb"}),
      name, checkboxContent);
    link.append(linkContent);
    layer.on("change:visible", () => {
      $(checkboxContent).toggleClass("unchecked", !layer.getVisible());
    });
    $("#overlaylayersheading").prop("hidden", false);
    $("#overlaylayerslist").append(link);
  }
  /**
   * Add a group of layers to the layerlist container.
   * It adds a button to the layer list that toggles the visibility to one layer in the group.
   * @param {ol.layer.Group} layergroup
   */
  function add_layergroup_to_list(layergroup) {
    basemapEntries.forEach((entry) => {
      const layer = entry.layer;
      let link = $("<button>", {
        type: "button",
        "class": "th-basemap-card",
        "aria-pressed": layer && layer.getVisible() ? "true" : "false",
      });
      let check = $("<i>", {"class": "fa fa-check-circle", "aria-hidden": "true"});
      link.append(
        $("<img>", {src: entry.thumbnail, alt: "", loading: "lazy", width: 72, height: 72,
          "class": "th-basemap-thumb"}),
        $("<span>", {"class": "th-basemap-copy"}).append(
          $("<strong>", {text: entry.name, title: entry.name}),
          $("<small>", {text: entry.description})
        ),
        check
      );
      const update = () => {
        const selected = layer && layer.getVisible();
        link.toggleClass("is-selected", Boolean(selected));
        link.attr("aria-pressed", selected ? "true" : "false");
        check.toggleClass("unchecked", !selected);
      };
      layer.on("change:visible", update);
      link.on("click", async() => {
        const revision = ++basemapSelectionRevision;
        if (entry.prepare) {
          link.prop("disabled", true).addClass("is-loading");
          try {
            await entry.prepare();
          } catch (error) {
            toast(strings.basemap_loaderror);
            link.prop("disabled", false).removeClass("is-loading");
            return;
          }
          link.prop("disabled", false).removeClass("is-loading");
        }
        if (revision !== basemapSelectionRevision) {
          return;
        }
        layergroup.getLayers().forEach((candidate) => candidate.setVisible(candidate === layer));
        googleAttributionRevision++;
        if (googleLayers.has(layer)) {
          updateGoogleAttribution();
        }
        closeModal("#layerspage");
      });
      update();
      $("#layerslist").append(link);
    });
  }

  /**
   * Geolocation component. Controller for GPS positioning.
   * @type ol.Geolocation
   */
  let geolocation = new ol.Geolocation(
      /** @type {olx.GeolocationOptions} */({
      projection: view.getProjection(),
      trackingOptions: {
        enableHighAccuracy: true,
        maximumAge: 0,
        timeout: 10000,
      },
    })
  );
  /* -------------------------------Events-----------------------------------*/
  geolocation.on("change:position", () => {
    let coordinates = geolocation.getPosition();
    if (!coordinates) {
      // If no coordinates, show a toast message and return.
      getString('geolocation_problem', "mod_treasurehunt").then((msg) => {
        toast(msg);
      });
      return;
    }
    // Clear the user denied flag.
    geolocation.set("user_denied", false);
    positionFeature.setGeometry(
      coordinates ? new ol.geom.Point(coordinates) : null
    );
    if (playwithoutmoving == false) {
      // Move marker to the new position.
      if (coordinates) {
        markerFeature.setGeometry(
          new ol.geom.Point(coordinates)
        );
      }
    }
    // Close the modal in case of previous geolocation error.
    closeModal("#geolocationPopup");
    // The map must be re-centered in the new position
    if (geolocation.get("center")) {
      fly_to(map, coordinates);
      geolocation.setProperties({center: false}); // Disable center request. Deprecated.
    }
    // The new position must be evaluated
    if (geolocation.get("validate_location")) {
      renew_source(true, false);
      geolocation.setProperties({validate_location: false}); // Disable validate_location request. Deprecated.
    }
  });

  geolocation.on("change:accuracyGeometry", () => {
    accuracyFeature.setGeometry(geolocation.getAccuracyGeometry());
  });

  geolocation.on("error", (error) => {
    // GeolocationPositionError.PERMISSION_DENIED = 1
    // GeolocationPositionError.POSITION_UNAVAILABLE = 2
    // GeolocationPositionError.TIMEOUT = 3
    if (error.code == 1) {
      geolocation.set("user_denied", true);
    } else {
      geolocation.set("user_denied", false);
    }
    // If the geolocation times out, it is not an error. Just toast a warning.
    getString('geolocation_problem', "mod_treasurehunt").then((msg) => {
      toast(msg + ": " + error.message);
    });
    // Show instructions about the geolocation permissions.
    if (
      error.code == 1 && // USER DENIED.
      tracking &&
      !playwithoutmoving) {
      Promise.resolve().then(() => {
        openModal("#geolocationPopup");
      });
    }
    // Reactivate the tracking. Deferred to avoid error avalanche.
    if (tracking && !playwithoutmoving) {
      setTimeout(() => {
        geolocation.setTracking(true);
      }, 10000);
    }
  });
  // Ensure that the geolocation is enabled after the error: ol.Geolocation disables it.
  geolocation.setTracking(true);
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
        {layerFilter: (layer) => layer === attemptslayer || layer === stagelayer}
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
        if (stagename) {
          title = strings.stageovercome;
          body += get_block_text(strings.stagename, stagename);
        } else {
          title = strings.discoveredlocation;
        }
      } else {
        title = strings.failedlocation;
      }
      if (stageclue) {
        body += get_block_text(strings.stageclue, stageclue);
      }
      $("#infoStagePopup .title").text(title);
      $("#infoStagePopup .play-modal-content").html(body);
      openModal("#infoStagePopup");
    }
  });

  // Change marker feature position on user click if play mode is play without moving
  map.on("click", (evt) => {
    if (touchGuard.shouldIgnoreClick()) {
      return;
    }
    // Check if has a feature on click event position.
    let hasFeature = false;
    map.forEachFeatureAtPixel(
      map.getEventPixel(evt.originalEvent),
      (feature) => {
        if (
          feature.get("is_stage") === true ||
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
                strings.noresults +
                "</li>"
              );
            } else {
              $.each(resp, (i, place) => {
                let link = $("<button>", {
                  type: "button",
                  "class": "list-group-item list-group-item-action",
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
                  "class": "search-option",
                }).append($("<i class='fa fa-chevron-circle-right'>"));
                link.append(linkContent);
              });
            }
          })
          .fail(() => { })
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
    const nextcamIndex = webqr.getnextwebCamIndex();
    toast("Changing to:" + webqr.getCamName(nextcamIndex));
    webqr.setnextwebcam(qrReport);
  });

  // Redraw map on resize window.
  $(window).on("resize", () => {
    map.updateSize();
  });
  $('#fitmap').on('click', () => {
    if (isfirststage && !customplayerconfig.shownextareahint && nextstagefeature) {
      map.getView().fit(nextstagefeature.getGeometry().getExtent(),
        {size: map.getSize(), padding: [40, 40, 40, 40], maxZoom: 18});
      return;
    }
    let attentionSources = [attemptSource, markerSource];
    if (usegeographictools) {
      attentionSources.push(userPositionSource);
    }
    if (customplayerconfig.shownextareahint || isfirststage) {
      attentionSources.push(stageSource);
    }
    fitmap = true;
    fit_map_to_sources(attentionSources);
  });
  // Buttons events.
  if (usegeographictools) {
    $("#autolocate").on("click", () => {
      // Enable tracking if not already enabled.
      geolocation.setTracking(true);
      // If the geolocation is not enabled, show a modal with instructions.
      if (geolocation.get("user_denied")) {
        openModal("#geolocationPopup");
      } else {
        // If the geolocation is enabled, center the map on the current position.
        // Center the map in the next tick.
        Promise.resolve().then(() => {
          autocentermap(true);
        });
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
    // Select the answer.
    let selected = $("#questionform input[type='radio']:checked");
    if (!available) {
      event.preventDefault();
      toast(strings.timeexceeded);
    } else {
      if (selected.length === 0) {
        event.preventDefault();
        toast(strings.noanswerselected);
      } else {
        renew_source(false, false, selected.val());
      }
    }
  });

  $("#validatelocation").on("click", (event) => {
    if (roadfinished) {
      event.preventDefault();
      toast(strings.huntcompleted);
      return;
    }
    if (!available) {
      event.preventDefault();
      toast(strings.timeexceeded);
      return;
    }
    if (lastsuccessfulstage.question !== "") {
      event.preventDefault();
      toast(strings.answerwarning);
      openModal("#cluepage");
      return;
    }
    if (!lastsuccessfulstage.activitysolved) {
      event.preventDefault();
      toast(strings.activitytoendwarning);
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
    const target = e.currentTarget;
    openModal(target.dataset.ref);
  });

  $(document).on("click", ".close-modal, .modal-mask.dismissible", () => {
    closeModal();
  });

  /**
   * Show a toast message.
   * It shows a message in the bottom of the screen.
   * A successful location remains visible for 6.5 seconds.
   * @param {string} msg Message to display.
   * @param {boolean} showchest Show the animated treasure chest for a correct location.
   */
  function toast(msg, showchest = false) {
    const toast = $(`<div class='play-toast slide-in-bottom'>${msg}</div>`);
    if (showchest) {
      toast.addClass("play-toast-success");
      $("<img>", {src: treasurechesturl, alt: "", "aria-hidden": "true"}).prependTo(toast);
    }
    toast.appendTo($(".play-toast-container"));
    const duration = showchest ? 6500 : 3500;
    setTimeout(() => toast.addClass("slide-out-top"), duration - 500);
    setTimeout(() => toast.remove(), duration);
  }
  /**
   * Open a modal with the given id.
   * It closes the previous modal if exists.
   * @param {string} id The id of the modal to open.
   */
  function openModal(id) {
    clearLoading();
    if (id === "#cluepage" && roadfinished) {
      return;
    }
    // Close previous modal
    closeModal();
    const modal = $(id);
    modal.addClass("active");
    $(`${id} .modal-mask`).addClass("active dismissible");
    if (id === "#cluepage" && !roadfinished) {
      fitMapToSelectedClue();
    }
    modal.trigger("modal:open");
  }
  /**
   * Close a modal or all active modals.
   * @param {string} id The id of the modal to close. If not provided, closes all active modals.
   */
  function closeModal(id) {
    let modals = null;
    if (id) {
      modals = $(id);
      if (!modals.hasClass("play-modal") || !modals.hasClass("active")) {
        // If the modal is not active, do nothing.
        return;
      }
    } else {
      modals = $(".play-modal.active");
    }
    // Remove active class from all modals.
    modals.removeClass("active");
    $(".modal-mask").removeClass("active dismissible");
    // Trigger close event for each modal.
    modals.each((index, modal) => {
      $(modal).trigger("modal:close");
    });
  }
  /**
   * Format a card block text.
   * It returns a string with the HTML code for a card block.
   * @param {string} title
   * @param {string} body
   * @returns {string}
   */
  function get_block_text(title, body) {
    return `<div class="card">
          <div class="card-body">
            <h5 class="card-title">${title}</h5>
            <div class="card-text">${body}</div>
          </div>
      </div>`;
  }
  /**
   * Open a side panel with the given id.
   * @param {string} id
   */
  // function openSidePanel(id) {
  //   $(id).addClass("active");
  //   $(".sidebar-mask").addClass("active dismissible");
  // }
  /**
   * Close a side panel with the given id.
   * @param {string} id
   */
  function closeSidePanel(id) {
    $(id).removeClass("active");
    $(".sidebar-mask").removeClass("active dismissible");
  }
  /**
   * Show spinner loader.
   * @param {boolean} loading
   */
  function setLoading(loading) {
    if (loading) {
      $(".global-loader").addClass("active");
    } else {
      $(".global-loader").removeClass("active");
    }
  }

  /**
   * Start a request that owns the loading overlay.
   *
   * @returns {Object} Request token.
   */
  function beginLoading() {
    const token = {};
    loadingRequests.add(token);
    setLoading(true);
    return token;
  }

  /**
   * Release the loading overlay when the last owning request ends.
   *
   * @param {Object|null} token Request token, or null for a background poll.
   */
  function finishLoading(token) {
    if (token) {
      loadingRequests.delete(token);
      if (loadingRequests.size === 0) {
        setLoading(false);
      }
    }
  }

  /**
   * A dialog takes precedence over any pending loading operation.
   */
  function clearLoading() {
    loadingRequests.clear();
    setLoading(false);
  }

  /**
   * Calculate the heading from two geometries.
   * @param {ol.geom.Point} start The start point geometry.
   * @param {ol.geom.Geometry} end The end point geometry.
   * @returns {number} The heading in radians.
   */
  function calculateHeading(start, end) {
    // Get closest point to the end geometry.
    let closestPoint = end.getClosestPoint(start.getCoordinates());
    // Calculate the heading from start to closest point.
    let deltaX = closestPoint[0] - start.getCoordinates()[0];
    let deltaY = closestPoint[1] - start.getCoordinates()[1];
    let heading = Math.atan2(deltaY, deltaX);
    // Normalize the heading to be between 0 and 2*PI.
    if (heading < 0) {
      heading += 2 * Math.PI;
    }
    return -heading + Math.PI / 2; // Add PI/2 to align with the icon orientation.
  }
  /**
   * Scan QR event handler.
   * @param {string} value The value of the QR code.
   */
  function qrReaded(value) {
    closeModal();
    toast("QR code readed: " + value);
    renew_source(false, false, null, value);
  }
  /**
   * Report QR code status and manage the Scan button.
   * @param {Object} message The message object with the camera information.
   */
  function qrReport(message) {
    if (typeof message == "string") {
      $("#errorQR").text(message);
    } else if (message.cameraIndex !== undefined) {
      if (message.cameras[message.cameraIndex].name !== null) {
        $("#errorQR").text(message.cameras[message.cameraIndex].name);
      }
      // Hide/show next camera button.
      if (message.cameras.length > 1) {
        $("#nextcamera").show();
      } else {
        $("#nextcamera").hide();
      }
    } else {
      getString('warnqrscannererror', 'treasurehunt', '').then((msg) => {
        $("#errorQR").text(msg);
        $("#previewQRdiv").html(message.messagetext);
      });
    }
  }
}

export default init;
