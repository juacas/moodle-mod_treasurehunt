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
 * @copyright  2025 Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import $ from "jquery";
import url from "core/url";
import ol from "mod_treasurehunt/ol";
import ajax from "core/ajax";
import OSMGeocoder from "mod_treasurehunt/osm-geocoder";
import viewgpx from "mod_treasurehunt/viewgpx";
import { get_strings as getStrings, get_string as getString } from "core/str";
import webqr from "mod_treasurehunt/webqr";
// Side‑effect only imports
import "mod_treasurehunt/dropdown";

/**
 * @module    mod_treasurehunt/play
 * @package
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @author Adrian Rodriguez <huorwhisp@gmail.com>
 * @author Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
let init = {
  playtreasurehunt: function (cmid, treasurehuntid, playwithoutmoving, groupmode,
    lastattempttimestamp, lastroadtimestamp, gameupdatetime, tracking, user,
    custommapconfig, customplayerconfig = null) {

    // I18n strings.
    let terms = ["stageovercome", "failedlocation", "stage", "stagename", "stageclue",
      "question", "noanswerselected", "timeexceeded", "searching", "continue", "noattempts",
      "aerialview", "roadview", "noresults", "startfromhere", "nomarks", "updates", "activitytoendwarning",
      "huntcompleted", "discoveredlocation", "answerwarning", "error", "pegmanlabel", "webserviceerror"
    ];
    // console.log("loading i18n strings");
    let stringsqueried = terms.map((term) => {
      return { key: term, component: "treasurehunt" };
    });
    // i18n = i18nplay; // Use globally passed strings. Moodle 3.8 core/str broke with jquery 2.1.4.
    getStrings(stringsqueried).then((strings) => {
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

          initplaytreasurehunt($, i18n, cmid, treasurehuntid, playwithoutmoving, groupmode,
            lastattempttimestamp, lastroadtimestamp, gameupdatetime, tracking,
            user, custommapconfig, customplayerconfig);
        });
        img.src = custommapconfig.custombackgroundurl;
      } else {
        initplaytreasurehunt(
          $, i18n, cmid, treasurehuntid, playwithoutmoving, groupmode,
          lastattempttimestamp, lastroadtimestamp, gameupdatetime, tracking,
          user, custommapconfig, customplayerconfig);
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
      // console.log('Using bottom-left as reference.' + customimageextent);
    }
  }
  return customimageextent;
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
 * @param {int} lastattempttimestamp The last attempt timestamp.
 * @param {int} lastroadtimestamp The last road timestamp.
 * @param {int} gameupdatetime The game update time in milliseconds.
 * @param {boolean} tracking If true, the player is being tracked.
 * @param {Object} user The user object.
 * @param {Object} custommapconfig The custom map configuration.
 * @param {Object} playerconfig The custom player configurations for extension.
 */
function initplaytreasurehunt(
  $, strings, cmid, treasurehuntid, playwithoutmoving, groupmode, lastattempttimestamp,
  lastroadtimestamp, gameupdatetime, tracking, user, custommapconfig, playerconfig = null) {

  setLoading(true);
  // Cast to boolean.
  playwithoutmoving = playwithoutmoving == true;
  groupmode = groupmode == true;
  tracking = tracking == true;
  let isfirststage = false;
  let nextstagefeature = null;
  let mapprojection = "EPSG:3857";
  let custombaselayer = null;
  let usegeographictools = true;
  let defaultzoom = 15;
  let supportsTouch = "ontouchstart" in window || navigator.msMaxTouchPoints;
  let customplayerconfig;
  set_player_config(playerconfig);
  // Check custom player configuration.
  if (customplayerconfig && customplayerconfig.defaultzoom) {
    defaultzoom = customplayerconfig.defaultzoom;
  }
  // Support customized base layers.
  if (custommapconfig) {
    if (custommapconfig.custombackgroundurl) {
      var customimageextent = calculateCustomImageExtent(custommapconfig, mapprojection, false);

      defaultzoom = 5;
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

    usegeographictools = custommapconfig.geographic;
  }
  if (usegeographictools === false) {
    // console.log("geographic tools disabled");
    playwithoutmoving = true;
    $("#autolocate").hide();
  }

  let successurl = url.imageUrl("success_mark", "treasurehunt");
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
    // let shouldrender = feature.get("shouldrender");
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
        text = strings["startfromhere"];
      } else {
        text = strings["stage"] + " " + stageposition;
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
    let geom2 = nextstagefeature ? nextstagefeature.getGeometry() : null;
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
    let geomtarget = nextstagefeature ? nextstagefeature.getGeometry() : null;
    let geomfeature = feature.getGeometry();
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
    if (!nextstagefeature) {
      return null;
    }
    let rotation = calculateHeading(feature.getGeometry(), nextstagefeature.getGeometry()) ?? null;
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
  /*-------------------------------Layers---------------------------------*/
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
  if (!custommapconfig || custommapconfig.onlybase === false) {
    // Default layers


    let roadlayer = new ol.layer.Tile({
      source: new ol.source.OSM(),
    });
    // BingMaps layer's token expires and breaks the service.
    // let aeriallayer = new ol.layer.Tile({
    //   visible: false,
    //   preload: Infinity,
    //   source: new ol.source.BingMaps({
    //     key: "AmC3DXdnK5sXC_Yp_pOLqssFSaplBbvN68jnwKTEM3CSn2t6G5PGTbYN3wzxE5BR",
    //     imagerySet: "AerialWithLabels",
    //     maxZoom: 19,
    //     // Use maxZoom 19 to see stretched tiles instead of the BingMaps
    //     // "no photos at this zoom level" tiles
    //     // maxZoom: 19.
    //   }),
    // });
    /**
     * Use World Imagery de Esri (free for non commercial use).
     */
    let aeriallayer = new ol.layer.Tile({
      source: new ol.source.XYZ({
        url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        attributions: [
          'Tiles © Esri — Fuente: Esri, DigitalGlobe, Earthstar Geographics, CNES/Airbus DS, USDA,' +
          ' USGS, AeroGRID, IGN, and the GIS User Community'
        ]
      })
    });

    aeriallayer.set("name", strings["aerialview"]);
    roadlayer.set("name", strings["roadview"]);

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
    zoom: 4,
    minZoom: 3,
    maxZoom: 20,
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
  accuracyFeature.setProperties({ name: "user_accuracy" });
  accuracyFeature.setStyle(accuracyFeatureStyle);
  let positionFeature = new ol.Feature();
  positionFeature.setProperties({ name: "user_position" });
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
  /**
   * Check if there is a usable position.
   */
  function validateposition() {
    if (playwithoutmoving && !markerFeature.getGeometry()) {
      toast(strings["nomarks"]);
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
    // let position holds the potition to be evaluated. undef if no evaluation requested
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
      setLoading(true);
      answerid = selectedanswerid;
    }
    if (location) {
      position = currentposition;
      setLoading(true);
    }
    let currentpositionarg = tracking && !playwithoutmoving ? currentposition : null; // only for tracking in mobility.
    let params = {
      treasurehuntid: treasurehuntid,
      attempttimestamp: lastattempttimestamp,
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
    let geojson = ajax.call([
      {
        methodname: "mod_treasurehunt_user_progress",
        args: {
          userprogress: params,
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
          //markerFeature.setGeometry(null);
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
        let nextstagefeatures = null;
        if (response && response.nextstage) {
          nextstagefeatures = geoJSONFormat.readFeatures(response.nextstage,
                                              {
                                                dataProjection: "EPSG:4326",
                                                featureProjection: "EPSG:3857",
                                              });
        }
        let newnextstagefeature = (nextstagefeatures && nextstagefeatures[0]) ?? null;
        let stagepositionold = nextstagefeature ? nextstagefeature.get("stageposition") : null;
        let stagepositionnew = newnextstagefeature ? newnextstagefeature.get("stageposition") : null;
        isfirststage = newnextstagefeature && newnextstagefeature.get("stageposition") === 1;
        let isfirstload = nextstagefeature === null;
        // If the next stage is first or different from the previous one, update it.
        if (newnextstagefeature !== null
          && (nextstagefeature === null || stagepositionold != stagepositionnew)) {

          nextstagefeature = newnextstagefeature;
          stageSource.clear();
          // Add the feature to be shown in the map.
          let shouldrender = isfirststage || customplayerconfig.shownextareahint;
          if (shouldrender) {
            nextstagefeature.set("shouldrender", true);
          } else {
            nextstagefeature.set("shouldrender", false);
          }
          // Render as stage, not as attempt.
          nextstagefeature.set("is_stage", true);
          stageSource.addFeatures([nextstagefeature]);
          // If the stage is the first one, center the map on it.
          if (isfirststage === true && (isfirstload || initialize)) {
            fitmap = true;
            fit_map_to_sources([stageSource]);
          }
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
            // fitmap = true;
            // fit_map_to_sources([attemptsource]);
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
          if (!response.lastsuccessfulstage && isfirststage || initialize) {
            fitmap = true;
            if (isfirststage && isfirstload) {
              fit_map_to_sources([stageSource]);
            } else {
              // If it is not the first stage, fit the map to the attempts.
              fit_map_to_sources([attemptSource]);
            }
          }
          apply_lastsuccessfulstage();
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
          // On close the modal, show the clue model.
          $("#notificationsPopup").on("modal:close", function () {
            // Show the clue modal.
            openModal("#cluepage");
          });
        }
        if (!roadfinished) {
          $("#roadended").hide();
        }
        if (roadfinished || !available) {
          $("#validatelocation").hide();
          $("#question_button").hide();
          $("#roadended").show();
          //markerFeature.setGeometry(null);
          playwithoutmoving = false;
          clearInterval(interval);
          $("#mapplay").css("opacity", "0.8");
        }
      })
      .fail((error) => {
        let message;
        if (error.errorcode === "generalexceptionmessage") {
          message = "System Error: " + error.error;
        } else {
          message = strings['webserviceerror'] + " : " + error.error;
        }
        // If the error is unknown, show a generic message.
        $("#errorPopup .play-modal-content").text(message);
        openModal("#errorPopup");
        //clearInterval(interval);
      });
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
      let size = extent[2] - extent[0];
      let p = [(extent[0] + extent[2]) / 2, (extent[1] + extent[3]) / 2];
      if (extent[0] !== Infinity && size > 0) {
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
  /**
   * Add a Layer to the layerlist container.
   * It adds a button to the layer list that toggles the visibility of the layer.
   * @param {ol.layer.Base} layer
   */
  function add_layer_to_list(layer) {
    let link = $("<button>", {
      type: "button",
      class: "list-group-item list-group-item-action close-modal",
    }).click(() => {
      layer.setVisible(!layer.getVisible());
    });
    let name = $("<div>").append($.parseHTML(layer.get("name")));
    let checkboxContent = $("<i>", {
      class: "fa fa-check-circle" + (layer.getVisible() ? "" : " unchecked"),
    });
    let linkContent = $("<div>", {
      class: "layer-item",
    }).append(name, checkboxContent);
    link.append(linkContent);
    layer.on("change:visible", () => {
      $(checkboxContent).toggleClass("unchecked");
    });
    $("#layerslist").prepend(link);
  }
  /**
   * Add a group of layers to the layerlist container.
   * It adds a button to the layer list that toggles the visibility to one layer in the group.
   * @param {ol.layer.Group} layergroup
   */
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
      let checkboxContent = $("<i>", {
        class: "fa fa-check-circle" + (layer.getVisible() ? "" : " unchecked"),
      });
      let linkContent = $("<div>", {
        text: layer.get("name"),
        class: "layer-item " //+ (layer.getVisible() ? "" : "unchecked"),
      }).append(checkboxContent);
      link.append(linkContent);
      layer.on("change:visible", () => {
        $(checkboxContent).toggleClass("unchecked");
      });
      $("#layerslist").prepend(link);
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
  /*-------------------------------Events-----------------------------------*/
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
      geolocation.setProperties({ center: false }); // Disable center request. Deprecated.
    }
    // The new position must be evaluated
    if (geolocation.get("validate_location")) {
      renew_source(true, false);
      geolocation.setProperties({ validate_location: false }); // Disable validate_location request. Deprecated.
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
        { layerFilter: (layer) => layer === attemptslayer || layer === stagelayer }
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
  $('#fitmap').on('click', () => {
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
  //Buttons events.
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

  /**
   * Show a toast message.
   * It shows a message in the bottom of the screen.
   * The message is removed after 3.5 seconds.
   * @param {string} msg
   */
  function toast(msg) {
    const toast = $(`<div class='play-toast slide-in-bottom'>${msg}</div>`);
    toast.appendTo($(".play-toast-container"));
    // Out animation after 3s
    setTimeout(() => toast.addClass("slide-out-top"), 3000);
    // Remove element after 3,5s
    setTimeout(() => toast.remove(), 3500);
  }
  /**
   * Open a modal with the given id.
   * It closes the previous modal if exists.
   * @param {string} id The id of the modal to open.
   */
  function openModal(id) {
    // Close previous modal
    closeModal();
    const modal = $(id);
    modal.addClass("active");
    $(`${id} .modal-mask`).addClass("active dismissible");
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
      if (modals.hasClass(".play-modal.active") === false) {
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
            <h6 class="card-subtitle text-muted">${body}</h6>
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

export default init;