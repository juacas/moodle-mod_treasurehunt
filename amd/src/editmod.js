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
 *            Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @author Adrian Rodriguez <huorwhisp@gmail.com>
 * @author Juan Pablo de Castro <juanpablo.decastro@uva.es>*
 * @license http:// www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
import $ from "jquery";
import ol from "mod_treasurehunt/ol";
import ajax from "core/ajax";
import notification from "core/notification";
import { add as addToast } from "core/toast";
import * as Bootstrap from "bootstrap";
import OSMGeocoder from "mod_treasurehunt/osm-geocoder";
import initAddressAutocomplete from "mod_treasurehunt/addressautocomplete";
import viewgpx from "mod_treasurehunt/viewgpx";
import initOpenData from "mod_treasurehunt/opendata_editor";
import initEditorFullscreen from "mod_treasurehunt/editor_fullscreen";
import { get_strings as str } from "core/str";

let init = {
  edittreasurehunt: function (idModule, treasurehuntid, selectedroadid, lockid, custommapconfig) {
      var lockState = {id: lockid};
      document.addEventListener('treasurehunt:lockrenewed', function(event) {
        if (Number(event.detail.treasurehuntid) === Number(treasurehuntid)) {
          lockState.id = event.detail.lockid;
        }
      });
      // I18n strings.
      var terms = [
        "stage", "road", "aerialmap", "roadmap", "basemaps", "add", "modify", "save",
        "remove", "searchlocation", "savewarning", "removewarning", "areyousure",
        "removeroadwarning", "confirm", "cancel", "pegmanlabel", "custommapimageerror",
        "editorstatussaved", "editorstatusunsaved", "editorroaddeleted", "editorstagedeleted",
        "errvalidroad", "erremptystage", "editorinvalidstage", "reorderstage", "preview",
        "playstagewithqr", "playstagewithoutmoving", "discoveroutofsequence", "activitytoend",
        "addsimplequestion", "editorinverserestrictions", "editordirectrestrictions",
        "editorenabled", "editordisabled", "editornone", "editorclueshort", "editorqrshort",
        "editorpreviousshort", "editorblockedshort", "editorwithoutgpsshort", "editoroutofsequenceshort",
        "editorcopysource", "editorcopyfrom", "editorcopycount", "editorcopyexistingcount",
        "editorcopytarget", "editorcopyappenddesc",
        "editorcopyreplacedesc", "editorcopysuccess", "editorcopyempty",
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
            initedittreasurehunt(idModule, treasurehuntid, i18n, selectedroadid, lockState, custommapconfig);
          };
          img.onerror = function () {
            notification.alert("Error", i18n["custommapimageerror"], "Continue");
            initedittreasurehunt(idModule, treasurehuntid, i18n, selectedroadid, lockState, custommapconfig);
          };
          img.src = custommapconfig.custombackgroundurl;
        } else {
          initedittreasurehunt(idModule, treasurehuntid, i18n, selectedroadid, lockState, custommapconfig);
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
 * @param {Object} lockState Current lock id, updated when the renewal replaces a deleted row.
 * @param {object} custommapconfig
 */
function initedittreasurehunt(idModule, treasurehuntid, strings, selectedroadid, lockState, custommapconfig) {
    var copyNotice = sessionStorage.getItem("treasurehuntCopySuccess");
    if (copyNotice) {
      sessionStorage.removeItem("treasurehuntCopySuccess");
      addToast(copyNotice, {type: "success"});
    }
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
    // Load the control pane, treasurehunt and road list.
    if (geographictools) {
      var searchgroup = $("<div>", {class: "treasurehunt-editor-actions-group treasurehunt-editor-search"})
        .appendTo($(".treasurehunt-editor-actions"));
      $("<label>", {class: "visually-hidden", for: "searchaddress"})
        .text(strings.searchlocation).appendTo(searchgroup);
      var searchcontainer = $("<div>", {id: "searchcontainer"}).appendTo(searchgroup);
      $("<input>", {type: "text", inputmode: "search", enterkeyhint: "search",
        id: "searchaddress", class: "searchaddress"})
        .attr("placeholder", strings.searchlocation).appendTo(searchcontainer);
      $('<i class="fa fa-search searchicon" aria-hidden="true"></i>').prependTo(
        searchcontainer
      );
      $(
        '<i class="fa fa-times closeicon invisible" aria-hidden="true"></i>'
      ).appendTo(searchcontainer);
      $("#opendataopen").appendTo(searchgroup);
    }

    // Creo el stagelist.
    $("<ul>", {id: "stagelist", role: "listbox", class: "list-group list-group-flush"})
      .attr("aria-label", strings.stage).prependTo($("#stagelistpanel"));
    $("#stagelistpanel").attr({role: "region", "aria-label": strings.stage});
    $("#editorworkspace").attr({role: "tabpanel", "aria-labelledby": "treasurehunt-map-label"});
    setupStageReordering();

    /**
     * Renumber a road's stages from N to 1 in their visible order.
     * @param {jQuery} $listitems - jQuery collection of list items representing stages
     * @param {Array} dirtyStages - Array tracking modified stages
     * @param {Array} originalStages - Array containing original stage data
     * @param {Array} vector - Vector used for stage position calculations
     * @returns {boolean} Whether any position changed.
     */
    function renumberStages($listitems, dirtyStages, originalStages, vector) {
      var changed = false;
      $listitems.each(function (index, item) {
        var $item = $(item);
        var newPosition = $listitems.length - index;
        if (parseInt($item.attr("stageposition"), 10) === newPosition) {
          return;
        }
        changed = true;
        $item.attr("stageposition", newPosition);
        $item.find(".sortable-number").text(newPosition);
        if ($item.hasClass("ui-selected")) {
          stageposition = newPosition;
        }
        relocatenostage(parseInt($item.attr("stageid"), 10), newPosition,
          parseInt($item.attr("roadid"), 10), dirtyStages, originalStages, vector);
      });
      return changed;
    }

    /** Reorder stages with pointer and keyboard events, without jQuery UI sortable. */
    function setupStageReordering() {
      var list = document.getElementById("stagelist");
      var scrollpanel = document.getElementById("stagelistpanel");
      var drag = null;
      var suppressClickUntil = 0;

      /**
       * Return the visible stages belonging to one road.
       * @param {string} roadid Road identifier.
       * @returns {Array<HTMLElement>} Stage rows in DOM order.
       */
      function visibleStages(roadid) {
        return Array.from(list.children).filter(function (item) {
          return item.matches('li[roadid="' + roadid + '"]') && item.getClientRects().length > 0 &&
            !item.classList.contains("treasurehunt-stage-placeholder");
        });
      }

      /**
       * Persist the positions represented by the current DOM order.
       * @param {string} roadid Road identifier.
       */
      function saveOrder(roadid) {
        var $items = $(list).children('li[roadid="' + roadid + '"]');
        if (renumberStages($items, dirtyStages, originalStages, treasurehunt.roads[roadid].vector)) {
          activateSaveButton();
          dirty = true;
        }
      }

      /**
       * Move the drop placeholder to the position under the pointer.
       * @param {number} clientY Pointer position in the viewport.
       */
      function movePlaceholder(clientY) {
        var stages = visibleStages(drag.roadid).filter(function (item) {
          return item !== drag.row;
        });
        var before = stages.find(function (item) {
          var bounds = item.getBoundingClientRect();
          return clientY < bounds.top + bounds.height / 2;
        });
        if (before) {
          list.insertBefore(drag.placeholder, before);
        } else if (stages.length) {
          list.insertBefore(drag.placeholder, stages[stages.length - 1].nextSibling);
        }
      }

      /** Scroll the stage panel when the pointer is near an edge. */
      function scrollWhileDragging() {
        if (!drag || !drag.active) {
          return;
        }
        var bounds = scrollpanel.getBoundingClientRect();
        var scroll = drag.clientY < bounds.top + 32 ? -12 :
          drag.clientY > bounds.bottom - 32 ? 12 : 0;
        if (scroll) {
          var previous = scrollpanel.scrollTop;
          scrollpanel.scrollTop += scroll;
          if (scrollpanel.scrollTop !== previous) {
            movePlaceholder(drag.clientY);
          }
        }
        drag.frame = window.requestAnimationFrame(scrollWhileDragging);
      }

      /** Create a placeholder and float the dragged stage above the list. */
      function startDrag() {
        var bounds = drag.row.getBoundingClientRect();
        drag.placeholder = document.createElement("li");
        drag.placeholder.className = "treasurehunt-stage-placeholder";
        drag.placeholder.setAttribute("aria-hidden", "true");
        drag.placeholder.style.height = bounds.height + "px";
        drag.row.before(drag.placeholder);
        drag.originalStyle = drag.row.getAttribute("style");
        Object.assign(drag.row.style, {
          position: "fixed",
          top: bounds.top + "px",
          left: bounds.left + "px",
          width: bounds.width + "px",
          height: bounds.height + "px",
          zIndex: "1000",
        });
        drag.row.classList.add("treasurehunt-stage-dragging");
        drag.active = true;
        drag.frame = window.requestAnimationFrame(scrollWhileDragging);
      }

      /**
       * Finish or cancel a pointer reorder.
       * @param {PointerEvent} event Pointer event.
       * @param {boolean} cancelled Whether to restore the original position.
       */
      function finishDrag(event, cancelled) {
        if (!drag || event.pointerId !== drag.pointerId) {
          return;
        }
        var completed = drag;
        drag = null;
        if (completed.frame) {
          window.cancelAnimationFrame(completed.frame);
        }
        if (completed.active) {
          if (cancelled) {
            completed.placeholder.remove();
          } else {
            completed.placeholder.replaceWith(completed.row);
          }
          completed.row.classList.remove("treasurehunt-stage-dragging");
          if (completed.originalStyle === null) {
            completed.row.removeAttribute("style");
          } else {
            completed.row.setAttribute("style", completed.originalStyle);
          }
          suppressClickUntil = Date.now() + 250;
          if (!cancelled) {
            saveOrder(completed.roadid);
          }
        }
        if (completed.handle.hasPointerCapture(completed.pointerId)) {
          completed.handle.releasePointerCapture(completed.pointerId);
        }
      }

      list.addEventListener("pointerdown", function (event) {
        var handle = event.target.closest(".handle");
        if (drag || !handle || !list.contains(handle) || !event.isPrimary || event.button !== 0) {
          return;
        }
        var row = handle.closest("li");
        if (!row || row.classList.contains("blocked") || !row.getClientRects().length) {
          return;
        }
        var bounds = row.getBoundingClientRect();
        drag = {
          row: row,
          handle: handle,
          roadid: row.getAttribute("roadid"),
          pointerId: event.pointerId,
          startY: event.clientY,
          offsetY: event.clientY - bounds.top,
          clientY: event.clientY,
          active: false,
        };
        handle.setPointerCapture(event.pointerId);
      });

      list.addEventListener("pointermove", function (event) {
        if (!drag || event.pointerId !== drag.pointerId) {
          return;
        }
        drag.clientY = event.clientY;
        if (!drag.active && Math.abs(event.clientY - drag.startY) > 5) {
          startDrag();
        }
        if (drag.active) {
          event.preventDefault();
          drag.row.style.top = event.clientY - drag.offsetY + "px";
          movePlaceholder(event.clientY);
        }
      });

      list.addEventListener("pointerup", function (event) {
        finishDrag(event, false);
      });
      list.addEventListener("pointercancel", function (event) {
        finishDrag(event, true);
      });
      list.addEventListener("lostpointercapture", function (event) {
        finishDrag(event, true);
      });
      list.addEventListener("click", function (event) {
        if (Date.now() < suppressClickUntil && event.target.closest(".handle")) {
          event.preventDefault();
          event.stopImmediatePropagation();
        }
      }, true);

      list.addEventListener("keydown", function (event) {
        if (event.key !== "ArrowUp" && event.key !== "ArrowDown") {
          return;
        }
        var handle = event.target.closest(".handle");
        if (!handle || !list.contains(handle)) {
          return;
        }
        var row = handle.closest("li");
        var roadid = row.getAttribute("roadid");
        var stages = visibleStages(roadid);
        var target = stages[stages.indexOf(row) + (event.key === "ArrowUp" ? -1 : 1)];
        if (!target) {
          return;
        }
        event.preventDefault();
        event.stopPropagation();
        list.insertBefore(row, event.key === "ArrowUp" ? target : target.nextSibling);
        saveOrder(roadid);
        handle.focus({preventScroll: true});
      });
    }

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
      controls: ol.control.defaults().extend([layerSwitcher]),
    });
    initEditorFullscreen(map);
    if (geographictools) {
      initOpenData(map, ol, layerSwitcher, new Bootstrap.Modal(document.getElementById("opendatamodal")), {
        stageContext: () => ({roadid, lockid: lockState.id, canCreate: !$("#addstage").prop("disabled")}),
        beforeCreate: (ready, failed) => {
          if (dirty) {
            savestages(dirtyStages, originalStages, treasurehuntid, ready, [], lockState.id, failed);
          } else {
            ready();
          }
        },
      });
    } else {
      document.getElementById("opendataopen").hidden = true;
      document.getElementById("opendataopen").style.display = "none";
    }

    var openStagePopover = null;
    var openStagePopoverButton = null;
    /** Close the currently open stage information popover. */
    function closeStagePopover() {
      if (openStagePopover) {
        openStagePopover.dispose();
        openStagePopover = null;
        openStagePopoverButton = null;
      }
    }

    map.on("click", function (evt) {
      var opendatahit = map.forEachFeatureAtPixel(evt.pixel, function (feature, layer) {
        return layer && layer.get("treasurehuntOpenData") ? feature : null;
      });
      if (opendatahit) {
        overlay.setPosition(undefined);
        return;
      }
      if (
        !Draw.getActive() &&
        !Modify.getActive() &&
        (custommapconfig === null || custommapconfig.geographic)
      ) {
        overlay.setPosition(evt.coordinate);
      }
    });
    layerSwitcher.showPanel();
    $("#toggleleftpanel").on("click", function () {
      var $workspace = $("#editorworkspace");
      var $aside = $("#editoraside");
      if (!$workspace.hasClass("is-collapsed")) {
        $aside.css("height", $aside.outerHeight() + "px");
      } else {
        $aside.css("height", "");
      }
      var collapsed = $workspace.toggleClass("is-collapsed").hasClass("is-collapsed");
      var label = $(this).attr(collapsed ? "data-expand-label" : "data-collapse-label");
      $(this).attr({"aria-expanded": String(!collapsed), "aria-label": label, title: label});
      $(this).find("i").toggleClass("fa-angle-double-left fa-angle-double-right");
      window.requestAnimationFrame(function () {
        map.updateSize();
      });
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
            $("#editmode").prop("disabled", !stageHasGeometry(stageid));
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
              road.stagecount = road.stages.features.length;
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
                addstage2ListPanel(stageid, road.id, stageposition, name, clue, blocked, feature.getProperties());
                if (polygons.length === 0) {
                  emptystage(stageid);
                }
              });
              road.vector = vector;
              map.addLayer(vector);
              treasurehunt.roads[road.id] = road;
              updateRoadValidation(road.id);
            });

            $("#copystages").prop("hidden", roads.length < 2);

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
        $("#editorworkspace").attr("aria-labelledby", "treasurehunt-map-label");
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
     * @param {Object} summary - Additional stage settings shown in the information card.
     *
     * This function dynamically creates a list item (`<li>`) element representing a stage
     * and appends it to the `#stagelist` element. If the stage is blocked, it adds a locked
     * icon and disables drag-and-drop functionality. If the stage is not blocked, it adds
     * drag-and-drop functionality and a delete icon. Additionally, it creates a dialog
     * element for displaying the stage's clue, which can be opened by clicking the info icon.
     * If a stage with the same `stageid` already exists, the function logs a message to the console.
     */
    function addstage2ListPanel(stageid, roadid, stageposition, name, clue, blocked, summary) {
      if ($('#stagelist li[stageid="' + stageid + '"]').length < 1) {
        var plainName = $("<div>").html(name).text();
        var li = $(
          '<li stageid="' + stageid + '" roadid="' + roadid + '" stageposition="' + stageposition + '"/>')
          .appendTo($("#stagelist"));
        li.addClass("list-group-item").attr({tabindex: 0, role: "option", "aria-selected": "false"});
        $("<div>", {class: "stagename"}).html(name).appendTo(li);
        var controls = $("<div>", {class: "modifystage"}).appendTo(li);
        $("<button>", {type: "button", class: "treasurehunt-icon-button treasurehunt-edit-item"})
          .attr("aria-label", strings.modify + " " + plainName)
          .append('<i class="fa fa-pencil" aria-hidden="true"></i>')
          .appendTo(controls);
        $("<button>", {
          type: "button",
          class: "treasurehunt-icon-button treasurehunt-info-item",
        }).attr({"aria-label": strings.stage + " " + plainName, "aria-haspopup": "dialog"})
          .data("stageInfo", {
            title: plainName,
            clue: clue,
            hasqr: summary.hasqr,
            discoveroutofsequence: summary.discoveroutofsequence,
            withoutgps: summary.playstagewithoutmoving,
            activitytoendname: summary.activitytoendname,
            hasquestion: summary.hasquestion,
            inverserestrictions: summary.inverserestrictions,
          })
          .append('<i class="fa fa-info-circle" aria-hidden="true"></i>')
          .appendTo(controls);
        if (blocked) {
          li.addClass("blocked").prepend(
            "<div class='nohandle validstage'>" +
            "<i class='fa fa-lock' aria-hidden='true'></i>" +
            "<span class='sortable-number'>" +
            stageposition +
            "</span></div>"
          );
        } else {
          li.prepend(
            "<div class='handle validstage'>" +
            "<i class='fa fa-arrows-v' aria-hidden='true'></i>" +
            "<span class='sortable-number'>" +
            stageposition +
            "</span></div>"
          );
          li.find(".handle").attr({
            tabindex: 0,
            role: "button",
            "aria-label": strings.reorderstage + " " + plainName,
          });
          $("<button>", {type: "button", class: "treasurehunt-icon-button treasurehunt-delete-item"})
            .attr("aria-label", strings.remove + " " + plainName)
            .append('<i class="fa fa-trash" aria-hidden="true"></i>')
            .prependTo(controls);
        }
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
        li.addClass("nav-item nav-link")
          .attr({id: "roadtab" + roadid, tabindex: -1, role: "tab",
            "aria-selected": "false", "aria-controls": "editorworkspace"})
          .append($("<div>", {class: "roadname"}).text(name));
        var controls = $("<div>", {class: "modifyroad"}).appendTo(li);
        $("<button>", {type: "button", "class": "btn btn-sm btn-outline-success treasurehunt-play-road", disabled: true})
          .attr("aria-label", strings.preview + " " + name)
          .append('<i class="fa fa-play" aria-hidden="true"></i> ' + strings.preview)
          .appendTo(controls);
        $("<button>", {type: "button", class: "treasurehunt-icon-button treasurehunt-delete-item"})
          .attr("aria-label", strings.remove + " " + name)
          .append('<i class="fa fa-trash" aria-hidden="true"></i>')
          .appendTo(controls);
        $("<button>", {type: "button", class: "treasurehunt-icon-button treasurehunt-edit-item"})
          .attr("aria-label", strings.modify + " " + name)
          .append('<i class="fa fa-pencil" aria-hidden="true"></i>')
          .appendTo(controls);
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
      closeStagePopover();
      var $li = $('#roadlist li[roadid="' + roadid + '"]');
      if ($li.length > 0) {
        var $lis = $('#stagelist li[roadid="' + roadid + '"]');
        setIssueTooltip($li, "");
        $lis.each(function () {
          setIssueTooltip($(this), "");
        });
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
      closeStagePopover();
      var $li = $('#stagelist li[stageid="' + stageid + '"]');
      if ($li.length > 0) {
        var roadid = $li.attr("roadid");
        setIssueTooltip($li, "");
        // I remove the li.
        $li.remove();
        var $stagelist = $("#stagelist li[roadid='" + roadid + "']");
        // I check the rest of the stages on the list.
        check_stage_list($stagelist, roadid);
        if (renumberStages($stagelist, dirtySource, originalStages, vectorOfPolygons)) {
          activateSaveButton();
          dirty = true;
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
     * Attach a Bootstrap tooltip to an invalid stage or road.
     * @param {jQuery} $target Target element.
     * @param {string} message Validation message, or empty to clear it.
     */
    function setIssueTooltip($target, message) {
      if (!$target.length || $target.data("issue") === message) {
        return;
      }
      var tooltip = Bootstrap.Tooltip.getInstance($target[0]);
      if (tooltip) {
        tooltip.dispose();
      }
      $target.data("issue", message).removeAttr("title data-bs-original-title");
      if (message) {
        $target.attr("title", message);
        new Bootstrap.Tooltip($target[0], {trigger: "hover focus", container: "body"});
      }
    }

    /**
     * Show whether a road has enough valid stages and no empty stages.
     * @param {number} currentRoadId Road identifier.
     */
    function updateRoadValidation(currentRoadId) {
      var $stages = $('#stagelist li[roadid="' + currentRoadId + '"]');
      var invalidCount = $stages.filter(".invalidstage").length;
      var validCount = $stages.length - invalidCount;
      var problem = validCount < 2 ? strings.errvalidroad :
        invalidCount > 0 ? strings.erremptystage : "";
      var $tab = $('#roadlist li[roadid="' + currentRoadId + '"]');
      $tab.toggleClass("invalidroad", Boolean(problem));
      setIssueTooltip($tab, problem);
      $tab.find(".treasurehunt-play-road").prop("disabled", Boolean(problem));
    }

    /**
     * Mark an empty stage and update the corresponding road.
     * @param {integer} stageid The stage identifier.
     * @param {integer} [roadid] The road identifier.
     */
    function emptystage(stageid, roadid) {
      var $treasurehunt = $('#stagelist li[stageid="' + stageid + '"]');
      $treasurehunt.addClass("invalidstage");
      setIssueTooltip($treasurehunt, strings.editorinvalidstage);
      $treasurehunt
        .children(".handle,.nohandle")
        .addClass("invalidstage")
        .removeClass("validstage");
      // I check if there are any stages on this road without
      // geometry.
      if (roadid) {
        $("label[for='addradio']").addClass("highlightbutton");
        updateRoadValidation(roadid);
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
      $treasurehunt.removeClass("invalidstage");
      setIssueTooltip($treasurehunt, "");
      $treasurehunt
        .children(".handle, .nohandle")
        .addClass("validstage")
        .removeClass("invalidstage");
      if (roadid) {
        // I check if there are any stages on this road without
        // geometry.
        $("label[for='addradio']").removeClass("highlightbutton");
        updateRoadValidation(roadid);
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
     * Check whether the selected stage currently contains at least one polygon.
     * @param {number} selectedstageid The stage identifier.
     * @returns {boolean} Whether the stage has geometry.
     */
    function stageHasGeometry(selectedstageid) {
      var feature = dirtyStages.getFeatureById(selectedstageid) || originalStages.getFeatureById(selectedstageid);
      return Boolean(feature && feature.getGeometry() && feature.getGeometry().getPolygons().length);
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
      $("#drawmode, #editmode").attr("aria-pressed", "false");
      $("#navmode").attr("aria-pressed", "true");
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
      $("#drawmode, #navmode").attr("aria-pressed", "false");
      $("#editmode").attr("aria-pressed", "true");
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
      $("#editmode, #navmode").attr("aria-pressed", "false");
      $("#drawmode").attr("aria-pressed", "true");
    }
    /**
     * Activate Edit mode. Update UI and tools.
     */
    function activateEdition() {
      $("#drawmode").prop("disabled", false);
      var hasgeometry = stageHasGeometry(stageid);
      $("#editmode").prop("disabled", !hasgeometry);
      if (hasgeometry) {
        activateModify();
      } else {
        activateNavigationMode();
      }
    }
    /**
     * Activate Save button.
     */
    function activateSaveButton() {
      if ($("#savestage").prop("disabled")) {
        addToast(strings.editorstatusunsaved, {type: "warning"});
      }
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
     * @param {number} currentRoadId Road identifier.
     */
    function check_stage_list($stagelist, currentRoadId) {
      if ($stagelist.length > 0) {
        $("#stagelistpanel").removeClass("invisible");
        map.updateSize();
      } else {
        $("#stagelistpanel").addClass("invisible");
        map.updateSize();
      }
      if ($stagelist.length === 0) {
        $("#addstage").addClass("highlightbutton").blur();
      } else {
        $("#addstage").removeClass("highlightbutton");
      }
      if (currentRoadId) {
        updateRoadValidation(currentRoadId);
      }
    }
    /**
     * Update the map to show a Road.
     * @param {integer} roadid
     * @param {array} vectorOfPolygons
     * @param {ol.map} map
     */
    function selectRoad(roadid, vectorOfPolygons, map) {
      closeStagePopover();
      $("#copystages").prop("disabled", Boolean(treasurehunt.roads[roadid].blocked));
      // I clean all the selected features, hide all
      // the li and I only show the ones with the roadid.
      $("#stagelist li").removeClass("ui-selected").attr("aria-selected", "false").hide();
      var $stagelist = $("#stagelist li[roadid='" + roadid + "']");
      $stagelist.show();
      check_stage_list($stagelist, roadid);
      // If the li road is not marked I mark it.
      $("#roadlist li").removeClass("ui-selected active")
        .attr({"aria-selected": "false", tabindex: -1});
      $("#roadlist li[roadid='" + roadid + "']").addClass("ui-selected active")
        .attr({"aria-selected": "true", tabindex: 0});
      $("#editorworkspace").attr("aria-labelledby", "roadtab" + roadid);
      // I leave only the vector with the visible roadid .
      map.getLayers().forEach(function (layer) {
        if (layer instanceof ol.layer.Vector && !layer.get('treasurehuntOpenData')) {
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
            addToast(strings.editorroaddeleted, {type: "success"});
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
            addToast(strings.editorstagedeleted, {type: "success"});
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
     * @param {function} errorcallback Optional callback when saving fails.
     */
    function savestages(dirtySource, originalStages, treasurehuntid, callback, options, lockid, errorcallback) {
      $(".treasurehunt-editor-loader").show();
      var geojsonformat = new ol.format.GeoJSON();
      var dirtyfeatures = dirtySource.getFeatures();
      var features = [];
      var auxfeature;
      // Send only the properties accepted by update_stages. The editor's
      // feature also contains read-only data used by the stage summary.
      dirtyfeatures.forEach(function (dirtyfeature) {
        auxfeature = new ol.Feature({
          roadid: Number(dirtyfeature.get("roadid")),
          stageposition: Number(dirtyfeature.get("stageposition")),
          geometry: dirtyfeature.getGeometry() ? dirtyfeature.getGeometry().clone() : null,
        });
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
            if (errorcallback) {
              errorcallback();
            }
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
            addToast(strings.editorstatussaved, {type: "success"});
            if (typeof callback === "function" && options instanceof Array) {
              callback.apply(null, options);
            }
          }
        })
        .fail(function (error) {
          $(".treasurehunt-editor-loader").hide();
          // console.log(error);
          notification.alert("Error", error.message, "Continue");
          if (errorcallback) {
            errorcallback();
          }
        });
    }

    const addressAutocomplete = initAddressAutocomplete(
      document.getElementById("searchaddress"),
      term => OSMGeocoder.search(term),
      result => {
        if (result.boundingbox && result.boundingbox.length === 4) {
          const bounds = [result.boundingbox[2], result.boundingbox[0],
            result.boundingbox[3], result.boundingbox[1]].map(Number);
          if (bounds.every(Number.isFinite)) {
            flyTo(map, null, ol.proj.transformExtent(bounds, "EPSG:4326", mapprojection));
          }
        } else {
          const point = [Number(result.lon), Number(result.lat)];
          if (point.every(Number.isFinite)) {
            flyTo(map, ol.proj.fromLonLat(point));
          }
        }
      }
    );
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
          lockState.id
        );
      } else {
        newFormstageEntry(roadid, idModule);
      }
    });
    $("#addroad").on("click", function () {
      if (dirty) {
        savestages(dirtyStages, originalStages, treasurehuntid,
                  newFormRoadEntry, [idModule], lockState.id);
      } else {
        newFormRoadEntry(idModule);
      }
    });
    var copyModalElement = document.getElementById("copystagesmodal");
    var copyModal = copyModalElement ? new Bootstrap.Modal(copyModalElement) : null;
    var sourceRoadId = null;
    /** Update the description and available action for the chosen source road. */
    function updateCopySummary() {
      var source = treasurehunt.roads[sourceRoadId];
      var target = treasurehunt.roads[roadid];
      var $summary = $("#copystagessummary").empty();
      if (!source || !target) {
        $("#copystagessave").prop("disabled", true);
        return;
      }
      $("<div>", {class: "fw-bold mb-2"}).text(source.name).appendTo($summary);
      $("<p>", {class: "mb-2"}).text(strings.editorcopycount + " " + source.stagecount)
        .appendTo($summary);
      $("<p>", {class: "mb-2"}).text(strings.editorcopytarget + " " + target.name)
        .appendTo($summary);
      $("<p>", {class: "mb-2"}).text(strings.editorcopyexistingcount + " " + target.stagecount)
        .appendTo($summary);
      $("<div>", {class: "alert alert-info small mb-0", role: "note"})
        .text($("#copystagesreplace").prop("checked") ?
          strings.editorcopyreplacedesc : strings.editorcopyappenddesc).appendTo($summary);
      if (source.stagecount === 0) {
        $("<p>", {class: "text-muted small mt-2 mb-0"}).text(strings.editorcopyempty).appendTo($summary);
      }
      $("#copystagessave").prop("disabled", source.stagecount === 0 || target.blocked);
    }
    $("#copystages").on("click", function () {
      var target = treasurehunt.roads[roadid];
      if (!target || target.blocked) {
        return;
      }
      sourceRoadId = null;
      $("#copystagesreplace").prop("checked", false);
      var $options = $("#copystagesroads").empty();
      Object.values(treasurehunt.roads).forEach(function (source) {
        if (String(source.id) === String(roadid)) {
          return;
        }
        var $label = $("<label>", {class: "list-group-item list-group-item-action p-3 border rounded mb-2 " +
          "d-flex align-items-center"}).appendTo($options);
        $("<input>", {type: "radio", name: "copystagessource", class: "form-check-input me-3 flex-shrink-0"})
          .val(source.id).appendTo($label);
        $("<span>").text(strings.editorcopyfrom + " " + source.name).appendTo($label);
      });
      $("#copystagessummary").text(strings.editorcopysource);
      $("#copystagessave").prop("disabled", true);
      copyModal.show();
    });
    $("#copystagesroads").on("change", "input", function () {
      sourceRoadId = this.value;
      $("#copystagesroads .list-group-item").removeClass("active");
      $(this).closest("label").addClass("active");
      updateCopySummary();
    });
    $("#copystagesreplace").on("change", updateCopySummary);
    $("#copystagessave").on("click", function () {
      if (!sourceRoadId || !treasurehunt.roads[roadid] || treasurehunt.roads[roadid].blocked) {
        return;
      }
      var source = treasurehunt.roads[sourceRoadId];
      var selectedSourceId = sourceRoadId;
      var targetid = roadid;
      var replace = $("#copystagesreplace").prop("checked");
      var copy = function () {
        $("#copystagessave").prop("disabled", true);
        ajax.call([{
          methodname: "mod_treasurehunt_copy_stages",
          args: {
            treasurehuntid: treasurehuntid,
            sourceroadid: Number(selectedSourceId),
            targetroadid: Number(targetid),
            replaceexisting: replace,
            lockid: lockState.id,
          },
        }])[0].done(function () {
          copyModal.hide();
          sessionStorage.setItem("treasurehuntCopySuccess", strings.editorcopysuccess);
          window.location.assign("edit.php?id=" + encodeURIComponent(idModule) +
            "&roadid=" + encodeURIComponent(targetid));
        }).fail(function (error) {
          $("#copystagessave").prop("disabled", false);
          notification.exception(error);
        });
      };
      if (dirty) {
        savestages(dirtyStages, originalStages, treasurehuntid, copy, [], lockState.id);
      } else if (source.stagecount > 0) {
        copy();
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
          if (!stageHasGeometry(stageid)) {
            $("#editmode").prop("disabled", true);
            activateNavigationMode();
          }
          // Disable the delete button and active on save changes.
          deactivateDeleteButton();
          activateSaveButton();
          dirty = true;
        }
      );
    });
    $("#savestage").on("click", function () {
      savestages(dirtyStages, originalStages, treasurehuntid, null, null, lockState.id);
    });
    $("#stagelist").on("click", ".treasurehunt-info-item", function () {
      if (openStagePopoverButton === this) {
        closeStagePopover();
        return;
      }
      closeStagePopover();
      var info = $(this).data("stageInfo");
      var $card = $("<div>", {class: "card border-0 treasurehunt-stage-summary"});
      $("<div>", {class: "card-header fw-semibold"}).text(info.title).appendTo($card);
      var $body = $("<div>", {class: "card-body"}).appendTo($card);
      $("<div>", {class: "treasurehunt-stage-clue-label"}).text(strings.editorclueshort).appendTo($body);
      var $clue = $("<div>", {class: "treasurehunt-stage-clue"}).appendTo($body);
      /**
       * Copy a short HTML preview with only safe formatting tags.
       * @param {Node} node Source HTML node.
       * @param {Node} target Destination node.
       * @param {Object} state Remaining character count and truncation state.
       */
      function copyPreview(node, target, state) {
        if (node.nodeType === Node.ELEMENT_NODE &&
            ["script", "style", "iframe", "object", "svg", "img", "video", "audio", "form"]
              .includes(node.nodeName.toLowerCase())) {
          return;
        }
        if (!state.remaining) {
          state.truncated = state.truncated || Boolean(node.textContent.trim());
          return;
        }
        if (node.nodeType === Node.TEXT_NODE) {
          var chars = Array.from(node.textContent.replace(/\s+/g, " "));
          var available = state.remaining;
          target.appendChild(document.createTextNode(chars.slice(0, available).join("")));
          state.remaining = Math.max(0, available - chars.length);
          state.truncated = state.truncated || chars.length > available;
          return;
        }
        if (node.nodeType !== Node.ELEMENT_NODE) {
          return;
        }
        var tag = node.nodeName.toLowerCase();
        var allowed = ["p", "br", "strong", "b", "em", "i", "u", "ul", "ol", "li", "blockquote", "code"];
        var wrapper = allowed.includes(tag) ? document.createElement(tag) : target;
        if (tag === "br") {
          target.appendChild(wrapper);
          return;
        }
        Array.from(node.childNodes).forEach(function (child) {
          copyPreview(child, wrapper, state);
        });
        if (wrapper !== target && wrapper.textContent.trim()) {
          target.appendChild(wrapper);
        }
      }
      var cluehtml = new DOMParser().parseFromString(info.clue || "", "text/html").body;
      var previewstate = {remaining: 160, truncated: false};
      Array.from(cluehtml.childNodes).forEach(function (node) {
        copyPreview(node, $clue[0], previewstate);
      });
      if (!$clue.text().trim()) {
        $clue.text(strings.editornone);
      } else if (previewstate.truncated) {
        $clue.append(document.createTextNode("…"));
      }

      var direct = [];
      if (info.activitytoendname) {
        direct.push(strings.activitytoend + ": " + info.activitytoendname);
      }
      if (info.hasquestion) {
        direct.push(strings.addsimplequestion);
      }
      var inverse = info.inverserestrictions || [];
      var $statuses = $("<div>", {class: "treasurehunt-stage-statuses"}).appendTo($body);
      /**
       * Add one icon led and its short description.
       * @param {Array<string>} icons Font Awesome icon names.
       * @param {string} label Short visible label.
       * @param {string} fullLabel Accessible description.
       * @param {boolean} enabled Whether the setting is active.
       * @param {string} detail Visible setting value or activity names.
       */
      function addStatus(icons, label, fullLabel, enabled, detail) {
        var $status = $("<div>", {
          class: "treasurehunt-stage-status " + (enabled ? "is-active" : "is-inactive"),
        }).attr({role: "group", "aria-label": fullLabel + ": " + detail, title: fullLabel + ": " + detail})
          .appendTo($statuses);
        var $icons = $("<div>", {class: "treasurehunt-stage-status-icons", "aria-hidden": "true"})
          .appendTo($status);
        icons.forEach(function (icon) {
          $("<i>", {class: "fa fa-" + icon}).appendTo($icons);
        });
        $("<span>", {class: "treasurehunt-stage-status-label"}).text(label).appendTo($status);
        $("<span>", {class: "treasurehunt-stage-status-detail"}).text(detail).appendTo($status);
      }
      addStatus(["qrcode"], strings.editorqrshort, strings.playstagewithqr, Boolean(info.hasqr),
        info.hasqr ? strings.editorenabled : strings.editordisabled);
      addStatus(["random"], strings.editoroutofsequenceshort, strings.discoveroutofsequence,
        Boolean(info.discoveroutofsequence), info.discoveroutofsequence ? strings.editorenabled : strings.editordisabled);
      addStatus(["window-maximize", "arrow-right", "lock"], strings.editorpreviousshort,
        strings.editordirectrestrictions, direct.length > 0, direct.join(" · ") || strings.editornone);
      addStatus(["lock", "arrow-right", "window-maximize"], strings.editorblockedshort,
        strings.editorinverserestrictions, inverse.length > 0, inverse.join(" · ") || strings.editornone);
      addStatus(["mouse-pointer", "hand-pointer-o"], strings.editorwithoutgpsshort,
        strings.playstagewithoutmoving, Boolean(info.withoutgps),
        info.withoutgps ? strings.editorenabled : strings.editordisabled);
      openStagePopover = Bootstrap.Popover.getOrCreateInstance(this, {
        content: $card[0],
        html: true,
        trigger: "manual",
        container: "body",
        placement: "auto",
        customClass: "treasurehunt-stage-popover",
      });
      openStagePopoverButton = this;
      openStagePopover.show();
    });
    $(document).on("pointerdown.treasurehunt-stage-info", function (event) {
      if (!$(event.target).closest(".treasurehunt-info-item, .popover").length) {
        closeStagePopover();
      }
    }).on("keydown.treasurehunt-stage-info", function (event) {
      if (event.key === "Escape") {
        closeStagePopover();
      }
    });
    $("#stagelist").on("click", ".treasurehunt-delete-item", function () {
      var $this_li = $(this).parents("li");
      notification.confirm(
        strings["areyousure"],
        strings["removewarning"],
        strings["confirm"],
        strings["cancel"],
        function () {
          var stageid = parseInt($this_li.attr("stageid"));
          deletestage(stageid, dirtyStages, originalStages, treasurehunt.roads[roadid].vector,
            treasurehuntid, lockState.id);
        }
      );
    });
    $("#stagelist").on("click", ".treasurehunt-edit-item", function () {
      // I'm looking for the stageid of the li containing the
      // selected trash can.

      var stageid = parseInt($(this).parents("li").attr("stageid"));
      // If it's dirty I save the stage.
      if (dirty) {
        savestages(dirtyStages, originalStages, treasurehuntid, editFormstageEntry,
          [stageid, idModule], lockState.id);
      } else {
        editFormstageEntry(stageid, idModule);
      }
    });

    $("#stagelist, #roadlist").on("keydown", "li", function (e) {
      if (e.target === this && (e.key === "Enter" || e.key === " ")) {
        e.preventDefault();
        $(this).trigger("click");
      }
    });
    $("#roadlist").on("keydown", "li", function (event) {
      if (event.key !== "ArrowLeft" && event.key !== "ArrowRight") {
        return;
      }
      event.preventDefault();
      var tabs = $("#roadlist li");
      var offset = event.key === "ArrowRight" ? 1 : -1;
      var next = (tabs.index(this) + offset + tabs.length) % tabs.length;
      tabs.eq(next).trigger("click")[0].focus({preventScroll: true});
    });
    $("#stagelist").on("click", "li", function (e) {
      if ($(e.target).closest(".treasurehunt-icon-button").length ||
          (!$("#editorworkspace").hasClass("is-collapsed") &&
           $(e.target).closest(".handle, .nohandle").length)) {
        e.preventDefault();
        return;
      }
      $(this).addClass("ui-selected").attr("aria-selected", "true")
        .siblings().removeClass("ui-selected").attr("aria-selected", "false");
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
      if ($(e.target).closest(".treasurehunt-icon-button").length) {
        e.preventDefault();
        return;
      }
      $(this).addClass("ui-selected").attr("aria-selected", "true")
        .siblings().removeClass("ui-selected").attr("aria-selected", "false");
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
    });
    $("#roadlist").on("click", ".treasurehunt-edit-item", function () {
      // Busco el roadid del li que contiene el
      // lapicero seleccionado.
      var roadid = parseInt($(this).parents("li").attr("roadid"));
      // Si esta sucio guardo el escenario.
      if (dirty) {
        savestages(dirtyStages, originalStages, treasurehuntid,
                    editFormRoadEntry, [roadid, idModule], lockState.id);
      } else {
        editFormRoadEntry(roadid, idModule);
      }
    });
    $("#roadlist").on("click", ".treasurehunt-play-road", function(event) {
      event.stopPropagation();
      var previewroadid = Number($(this).closest("li").attr("roadid"));
      var url = "play.php?id=" + encodeURIComponent(idModule) +
        "&previewroadid=" + encodeURIComponent(previewroadid);
      if (dirty) {
        savestages(dirtyStages, originalStages, treasurehuntid, function() {
          window.location.assign(url);
        }, [], lockState.id);
      } else {
        window.location.assign(url);
      }
    });
    $("#roadlist").on("click", ".treasurehunt-delete-item", function () {
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
            lockState.id
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
    $(".searchaddress").on("input", function () {
      $(".closeicon").toggleClass("invisible", !this.value);
    });
    $(".closeicon").on("touchstart click", function (ev) {
      ev.preventDefault();
      $(this).addClass("invisible");
      addressAutocomplete.clear();
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
