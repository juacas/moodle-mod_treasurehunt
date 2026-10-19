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
 * @module  mod_treasurehunt/viewgpx
 * @package
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @author Adrian Rodriguez <huorwhisp@gmail.com>
 * @author Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import $ from 'jquery';
import ol from 'mod_treasurehunt/ol';
import 'mod_treasurehunt/ol3-layerswitcher';
import {get_strings as str} from 'core/str';
import notification from 'core/notification';
import {create as createCachedHeatmap} from 'mod_treasurehunt/cached_heatmap';

let init = {
    refreshTracksInterval: 60,
    refreshSources: [],
    countdownTimer: null,
    addgpxlayer: function(map, cmid, treasurehuntid, strings, user, trackgroupname) {

        var trackgroup = findOrAddLayerGroup(map, trackgroupname);

        loadGpx([user], cmid, map, trackgroup, null);
        return trackgroup;
    },
    // @param users = "global" obtain data from users_param. The list of user structures.
    creategpxviewer: function(cmid, treasurehuntid, users, custommapconfig, refreshinterval) {

        // Console.log('Creating gpxviewer.');
        // I18n strings.
        var terms = [
            'aerialmap', 'roadmap', 'basemaps', 'searchlocation', 'trackviewer', 'pegmanlabel',
            'trackviewerheatmap', 'trackviewerrefreshdisabled', 'trackviewerrefreshseconds'
        ];
        var stringsqueried = terms.map(function(term) {
            return {key: term, component: 'treasurehunt'};
        });
        this.refreshTracksInterval = refreshinterval;
        if (users == 'global') {
            // Get users from global (due to excessive size for AMD api).
            users = window.users_param;
        }
        return str(stringsqueried).then(function(strings) {
            // Console.log('I18N strings loaded.');
            var i18n = [];
            for (var i = 0; i < terms.length; i++) {
                i18n[terms[i]] = strings[i];
            }
            init.refreshSources = [];
            init.countdownTimer = null;
            init.refreshTracksInterval = refreshinterval;
            // Detect custom image.
            if (typeof (custommapconfig) != 'undefined' &&
                custommapconfig !== null &&
                custommapconfig.custombackgroundurl !== null) {

                // Detect image size.
                var img = new Image();
                img.addEventListener("load", function() {
                    custommapconfig.imgwidth = this.naturalWidth;
                    custommapconfig.imgheight = this.naturalHeight;
                    initcreategpxviewer(cmid, treasurehuntid, i18n, users, custommapconfig);
                });
                img.src = custommapconfig.custombackgroundurl;
            } else {
                initcreategpxviewer(cmid, treasurehuntid, i18n, users, custommapconfig);
            }
            return strings;
        }).catch(notification.exception);
    }, // End of function creategpxviewer.
/**
 * Create a coordinates overlay.
 * @param {string} selector
 * @param {string} cssurl
 * @param {string} label
 * @returns {ol.Overlay}
 */
    createCoordsOverlay: function(selector = '#mappage', cssurl = 'css/ol-popup.css', label = '') {
        // Popup showing the position the user clicked.
        // Elements that make up the popup.
        $('<link>')
            .appendTo('head')
            .attr({
                type: 'text/css',
                rel: 'stylesheet',
                href: cssurl
            });
        var popupmarker = $('<div id="popupmarker" class="ol-popup">'
            + '<a href="#" id="popup-closer" class="ol-popup-closer"></a>'
            + '<div id="popup-content"></div></div>');
        $(selector).append(popupmarker);
        var container = $('#popupmarker');
        // Var content = $('#popup-content');
        var closer = $('#popup-closer');
        var containernode = container.get(0);
        var timeouthandler = null;
        var createlink = createPegmanLink; // Function for events.
        /**
         * Create an overlay to anchor the popup to the map.
         */
        var overlay = new ol.Overlay(/** @type {olx.OverlayOptions} */
            ({
                element: containernode,
                autoPan: true,
                autoPanAnimation: {
                    duration: 250
                }
            }));
        /**
         * Add a click handler to hide the popup.
         *
         * @return {boolean} Don't follow the href.
         */
        closer.click(function() {
            overlay.setPosition(undefined);
            clearTimeout(timeouthandler);
            closer.blur();
            $('#popup-content').html('');
            return false;
        });
        ol.events.listen(
            overlay, ol.Object.getChangeEventType(ol.Overlay.Property.POSITION),
            () => {
                var latlon = overlay.getPosition();
                clearTimeout(timeouthandler);
                if (latlon) {
                    var pegmanlink = createlink(latlon, overlay.getMap(), label);
                    $('#popup-content').html(pegmanlink);
                    timeouthandler = setTimeout(() => {
 $('#popup-closer').trigger('click');
}, 2000);
                }
            }, overlay);
        // Disable timeout if hovering.
        container.hover(function() {
            clearTimeout(timeouthandler);
        }
        );
        return overlay;

    }
};
export default init;
/**
 * Initialize the GPX viewer.
 *
 * @param {int} cmid
 * @param {int} treasurehuntid
 * @param {Array} strings
 * @param {Array} users
 * @param {Object} custommapconfig
 */
function initcreategpxviewer(cmid, treasurehuntid, strings, users, custommapconfig) {
    // Console.log('Init gpx viewer.');
    var mapprojection = 'EPSG:3857';
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
        geographictools = custommapconfig.geographic;
    }
    var basemaps = new ol.layer.Group({
        'title': strings.basemaps,
        layers: [
            new ol.layer.Tile({
                title: strings.aerialmap,
                type: 'base',
                visible: false,
                source: new ol.source.XYZ({
                    url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
                    attributions: [
                        'Tiles © Esri — Fuente: Esri, DigitalGlobe, Earthstar Geographics, CNES/Airbus DS, USDA,' +
                        ' USGS, AeroGRID, IGN, and the GIS User Community'
                    ]
                })
            }), new ol.layer.Tile({
                title: strings.roadmap,
                type: 'base',
                visible: true,
                source: new ol.source.OSM({maxZoom: 19})
            })
        ]
    });
    if (custombaselayer !== null) {
        if (custommapconfig.onlybase) {
            basemaps.getLayers().clear();
        }
        basemaps.getLayers().push(custombaselayer);
    }
    var tracks = new ol.layer.Group({
        'title': strings.trackviewer,
        layers: []
    });
    // TODO: If geographic tools are enabled, add the geolocation control.
    if (geographictools) {
        // Console.log('Adding geolocation control.');
    }
    // Reuse the loaded GPX points and cache their rasterised heatmap by map tile.
    var heatmapRadiusInMapUnits = 50;
    var heatmapBlurInMapUnits = 100;
    var cachedHeatmap = createCachedHeatmap(ol, strings.trackviewerheatmap,
        heatmapRadiusInMapUnits, heatmapBlurInMapUnits);
    var heatmap = cachedHeatmap.layer;

    var layerSwitcher = new ol.control.LayerSwitcher();
    var map = new ol.Map({
        layers: [basemaps, heatmap, tracks],
        renderer: 'canvas',
        target: document.getElementById('mapgpx'),
        view: new ol.View({
            center: [0, 0],
            zoom: 2,
            minZoom: 2,
            maxZoom: 19
        }),
        controls: ol.control.defaults().extend([layerSwitcher])
    });
    // Console.log('Map created in mapgpx!');
    layerSwitcher.showPanel();
    var stagebounds = document.getElementById('mapgpx').dataset.stageBounds;
    var hasStageBounds = false;
    if (stagebounds) {
        try {
            var bounds = JSON.parse(stagebounds);
            if (getValidExtent(bounds)) {
                map.getView().fit(ol.proj.transformExtent(bounds, 'EPSG:4326', mapprojection), {
                    padding: [36, 36, 36, 36],
                    maxZoom: 17
                });
                hasStageBounds = true;
            }
        } catch (error) {
            // An invalid stored extent should not prevent the tracks from loading.
        }
    }
    var heatmapUpdatePending = false;
    /** Batch GPX refreshes that finish in the same browser frame. */
    function scheduleHeatmapUpdate() {
        if (heatmapUpdatePending) {
            return;
        }
        heatmapUpdatePending = true;
        window.requestAnimationFrame(function() {
            heatmapUpdatePending = false;
            rebuildHeatmap();
        });
    }
    loadGpx(users, cmid, map, tracks, layerSwitcher, scheduleHeatmapUpdate, !hasStageBounds);

    /** Rebuild the heatmap from every loaded GPX line, bounded to avoid excessive canvas work. */
    function rebuildHeatmap() {
        var coordinates = [];
        tracks.getLayers().forEach(function(layer) {
            layer.getSource().getFeatures().forEach(function(feature) {
                var geometry = feature.getGeometry();
                if (geometry && geometry.getType() === 'LineString') {
                    geometry.getCoordinates().forEach(function(point) {
                        coordinates.push(point);
                    });
                } else if (geometry && geometry.getType() === 'MultiLineString') {
                    geometry.getCoordinates().forEach(function(line) {
                        line.forEach(function(point) {
                            coordinates.push(point);
                        });
                    });
                }
            });
        });
        var stride = Math.max(1, Math.ceil(coordinates.length / 15000));
        var points = [];
        for (var i = 0; i < coordinates.length; i += stride) {
            points.push(coordinates[i]);
        }
        cachedHeatmap.setPoints(points);
    }
    // Popup showing the position the user clicked.
    var popup = new ol.Overlay({
        element: document.getElementById('info')
    });
    map.addOverlay(popup);

    /**
     * Calculate customimageextent.
     * @param {Object} custommapconfig custom map configuration.
     * @param {string} mapprojection map projection to use EPSG code.
     * @param {boolean} referencetocenter if true, use center point as reference for the extent.
     * @returns {Array} custom image extent in map projection.
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
     * Display feature info at the given pixel.
     * @param {Array} pixel pixel coordinates.
     * @param {ol.Map} themap map to search features.
     * @param {Array} coordinates coordinates of the clicked point.
     * @param {string} label label to show in the pegman link.
     * @returns {string} message with the feature info or pegman link.
     */
    function displayFeatureInfo(pixel, themap, coordinates, label) {
        var features = [];
        var msg = '';
        themap.forEachFeatureAtPixel(pixel, function(feature, layer) {
            if (layer && layer.get('trackUserId')) {
                features.push(layer.get('participantName'));
            }
        });
        if (features.length > 0) {
            var info = [];
            var i, ii;
            for (i = 0, ii = features.length; i < ii; ++i) {
                info.push($('<div>').text(features[i]).html());
            }
            msg = info.join(', ') || '(unknown)';
            map.getTarget().style.cursor = 'pointer';
        } else {
            msg = createPegmanLink(coordinates, map, label); // TODO: Show pegman.
            map.getTarget().style.cursor = '';
        }
        return msg;
    }
    /**
     * Show popup with feature info.
     * @param {ol.event} evt
     */
    function showpopup(evt) {
        var element = popup.getElement();
        var coordinate = evt.coordinate;
        $(element).popover('dispose'); // For jquery 4+ it is dispose instead of destroy!
        popup.setPosition(coordinate);
        $(element).popover({
            'placement': 'top',
            'animation': false,
            'html': true,
            'content': displayFeatureInfo(map.getEventPixel(evt.originalEvent), map, coordinate, strings.pegmanlabel)
        });
        $(element).popover('show');
    }
    var hoverElement = document.createElement('div');
    hoverElement.className = 'track-hover-tooltip';
    var hoverPopup = new ol.Overlay({
        element: hoverElement,
        offset: [0, -12],
        positioning: 'bottom-center',
        stopEvent: false
    });
    map.addOverlay(hoverPopup);
    var activeTrack = null;
    var selectedTrack = null;
    /**
     * Highlight one participant while dimming the other tracks.
     * @param {ol.layer.Vector|null} layer The hovered or selected track layer.
     */
    function setActiveTrack(layer) {
        if (activeTrack === layer) {
            return;
        }
        activeTrack = layer;
        tracks.getLayers().forEach(function(track) {
            track.setOpacity(layer && track !== layer ? 0.16 : 1);
            track.set('hovered', layer === track);
            if (track.markerElement) {
                track.markerElement.style.opacity = layer && track !== layer ? '0.16' : '1';
            }
            track.changed();
        });
        hoverElement.textContent = layer ? layer.get('participantName') : '';
        if (!layer) {
            hoverPopup.setPosition(undefined);
        }
        map.getTargetElement().style.cursor = layer ? 'pointer' : '';
    }
    map.on('pointermove', function(evt) {
        if (evt.dragging) {
            hoverPopup.setPosition(undefined);
            setActiveTrack(selectedTrack);
            return;
        }
        var hovered = map.forEachFeatureAtPixel(evt.pixel, function(feature, layer) {
            return layer && layer.get('trackUserId') ? layer : undefined;
        });
        setActiveTrack(hovered || selectedTrack);
        if (hovered) {
            hoverPopup.setPosition(evt.coordinate);
        } else {
            hoverPopup.setPosition(undefined);
        }
    });
    map.getViewport().addEventListener('mouseleave', function() {
        hoverPopup.setPosition(undefined);
        setActiveTrack(selectedTrack);
    });
    map.on('click', function(evt) {
        selectedTrack = map.forEachFeatureAtPixel(evt.pixel, function(feature, layer) {
            return layer && layer.get('trackUserId') ? layer : undefined;
        }) || null;
        setActiveTrack(selectedTrack);
        hoverPopup.setPosition(undefined);
        showpopup(evt);
    });
    var refreshslider = $('#refreshtracks');
    var refreshvalue = $('#refreshtracksvalue');
    /** Update the refresh interval from the slider and restart its countdown. */
    function updateRefreshControl() {
        var sliderposition = parseInt(refreshslider.val(), 10);
        var seconds = sliderposition === 70 ? 0 : sliderposition;
        init.refreshTracksInterval = seconds;
        var label = seconds ? strings.trackviewerrefreshseconds.replace(/\{\$a\}/g, seconds) : strings.trackviewerrefreshdisabled;
        refreshvalue.text(label);
        refreshslider.attr('aria-valuetext', label);
        if (init.countdownTimer) {
            clearInterval(init.countdownTimer);
            init.countdownTimer = null;
        }
        if (!seconds) {
            $('#timecircle').hide();
            return;
        }

        var remaining = seconds;
        $('#timecircle').text(remaining).show();
        init.countdownTimer = setInterval(function() {
            remaining--;
            if (remaining <= 0) {
                init.refreshSources.forEach(function(source) {
                    source.clear();
                    source.refresh();
                });
                remaining = seconds;
            }
            $('#timecircle').text(remaining);
        }, 1000);
    }
    refreshslider.on('input change', updateRefreshControl);
    updateRefreshControl();
} // End of function initcreategpxviewer.
/**
 *
 * @param {*} map
 * @param {*} trackgroupname
 * @returns
 */
function findOrAddLayerGroup(map, trackgroupname) {
    var layergroup = null;
    var layers = map.getLayers();
    layers.forEach(function(layer) {
        if (layer.get('title') == trackgroupname) {
            layergroup = layer;
        }
    });

    if (!layergroup) {
        layergroup = new ol.layer.Group({
            'title': trackgroupname,
            layers: []
        });
        map.addLayer(layergroup);
    }
    return layergroup;
}
/**
 *
 * @param {ol.map} map
 * @param {Array} point center coordinates
 * @param {Array} extent bounding box coordinates
 */
function flyTo(map, point, extent) {
    var duration = 700;
    var view = map.getView();
    if (extent) {
        view.fit(extent, {
            duration: duration
        });
    } else {
        view.animate({
            zoom: 19,
            center: point,
            duration: duration
        });
    }
}
/**
 * Load GPX tracks for the given users.
 * @param {Array} users
 * @param {int} cmid
 * @param {ol.Map} map
 * @param {ol.layer.Group} tracksGroup
 * @param {LayerSwitcher} layerSwitcher
 * @param {Function} onSourceLoaded Callback to update the heatmap.
 * @param {boolean} fitTracks Whether to fit tracks when stage geometry is unavailable.
 */
function loadGpx(users, cmid, map, tracksGroup, layerSwitcher, onSourceLoaded = null, fitTracks = false) {
    var maxExtent = null;
    var pending = users.length;
    users.forEach(function(user) {
        var gpxsource = new ol.source.Vector({
            url: 'gpx.php?id=' + cmid + '&userid=' + user.id,
            format: new ol.format.GPX()
        });
        var color = user.teamid ? teamColor(user.teamid) : trackColor(user.id);
        var participantName = user.teamname ? user.fullname + ' · ' + user.teamname : user.fullname;
        var vector = new ol.layer.Vector({
            source: gpxsource,
            title: user.pic + ' ' + $('<div>').text(participantName).html(),
            style: function() {
                return trackStyleFunction(color, Boolean(vector.get('hovered')));
            }
        });
        vector.set('trackUserId', user.id);
        vector.set('participantName', participantName);
        var marker = document.createElement('div');
        marker.className = 'track-participant-avatar';
        marker.style.setProperty('--track-color', color);
        marker.title = participantName;
        if (user.pictureurl) {
            var picture = document.createElement('img');
            picture.src = user.pictureurl;
            picture.alt = '';
            marker.appendChild(picture);
        } else {
            marker.textContent = user.initials || '?';
        }
        var markerOverlay = new ol.Overlay({
            element: marker,
            positioning: 'center-center',
            stopEvent: false
        });
        vector.markerElement = marker;
        map.addOverlay(markerOverlay);
        init.refreshSources.push(gpxsource);
        vector.on('change:visible', function() {
            marker.style.display = this.getVisible() ? '' : 'none';
            if (onSourceLoaded) {
                onSourceLoaded();
            }
            if (this.getVisible()) {
                var extent = this.getSource().getExtent();
                extent = getValidExtent(extent);
                if (extent !== null) {
                    flyTo(map, null, extent);
                }
            }
        });
        var initiallyLoaded = false;
        gpxsource.on('change', function() {
            if (gpxsource.getState() !== 'ready') {
                return;
            }
            var extent = getValidExtent(gpxsource.getExtent());
            if (extent !== null) {
                maxExtent = maxExtent === null ? extent.slice() : ol.extent.extend(maxExtent, extent);
                var coordinates = null;
                gpxsource.getFeatures().forEach(function(feature) {
                    var geometry = feature.getGeometry();
                    if (geometry && geometry.getType() === 'LineString') {
                        coordinates = geometry.getLastCoordinate();
                    } else if (geometry && geometry.getType() === 'MultiLineString') {
                        var lines = geometry.getCoordinates();
                        if (lines.length && lines[lines.length - 1].length) {
                            coordinates = lines[lines.length - 1][lines[lines.length - 1].length - 1];
                        }
                    }
                });
                markerOverlay.setPosition(coordinates || undefined);
            } else {
                markerOverlay.setPosition(undefined);
            }
            if (onSourceLoaded) {
                onSourceLoaded();
            }
            if (!initiallyLoaded) {
                initiallyLoaded = true;
                pending--;
                if (fitTracks && pending === 0 && maxExtent) {
                    map.getView().fit(maxExtent, {padding: [36, 36, 36, 36], maxZoom: 17});
                }
            }
        }, this);
        tracksGroup.getLayers().push(vector);
        if (layerSwitcher) {
            layerSwitcher.renderPanel();
        }
    });
}
/**
 *
 * @param {Array} extent boundingbox 2 coordinates
 */
function getValidExtent(extent) {
    if (!(extent instanceof Array) || extent.length != 4) {
        return null;
    }
    if (!extent.every(Number.isFinite) || extent[0] > extent[2] || extent[1] > extent[3]) {
        return null;
    }
    return extent;
}
/**
 * Track decoration function.
 * @param {string} color
 * @param {boolean} selected
 * @returns {Array<ol.style.Style>}
 */
function trackStyleFunction(color, selected) {
    var styles = [
        // ...shadow.
        new ol.style.Style({
            stroke: new ol.style.Stroke({
                color: 'rgba(255,255,255,0.95)',
                width: 9 + 3 * selected,
                lineCap: 'round',
                lineJoin: 'round'
            }),
            zIndex: 1 + selected * 4
        }),
        new ol.style.Style({
            stroke: new ol.style.Stroke({
                color: color,
                width: 4 + 2 * selected,
                lineCap: 'round',
                lineJoin: 'round',
            }),
            fill: new ol.style.Fill({
                color: color
            }),
            zIndex: 2 + selected * 4
        })
    ];
    return styles;
}
/**
 * Choose a stable, legible color for one participant's track.
 * @param {number} userid User id.
 * @returns {string} CSS color.
 */
function trackColor(userid) {
    var sector = ((Number(userid) * 137.508) % 360) / 60;
    var chroma = (1 - Math.abs(2 * 0.37 - 1)) * 0.7;
    var secondary = chroma * (1 - Math.abs(sector % 2 - 1));
    var wheel = [
        [chroma, secondary, 0], [secondary, chroma, 0], [0, chroma, secondary],
        [0, secondary, chroma], [secondary, 0, chroma], [chroma, 0, secondary]
    ];
    var channels = wheel[Math.floor(sector)];
    var offset = 0.37 - chroma / 2;
    return 'rgb(' + channels.map(function(channel) {
        return Math.round((channel + offset) * 255);
    }).join(',') + ')';
}
/**
 * Match the team palette used by the participant progress bar.
 * @param {number} groupid Moodle group id.
 * @returns {string} CSS color.
 */
function teamColor(groupid) {
    var colors = ['#3478c5', '#7a4db3', '#008f87', '#c06a28', '#b14b78', '#587d32', '#5363a6'];
    return colors[Number(groupid) % colors.length];
}
/**
 * Create a pegman link to Google Maps Street View.
 * @param {ol.Coordinate} coordinate - The coordinate to link to.
 * @param {ol.Map} map - The map to use for projection.
 * @param {string} label - The label to show in the link.
 * @returns {string} - The HTML link to the pegman location.
 */
function createPegmanLink(coordinate, map, label) {
    var latlon = ol.proj.toLonLat(coordinate, map.getView().getProjection());
    var hdms = ol.coordinate.toStringHDMS(latlon);
    // Format HDMS to remove inner spaces on each coordinate.
    // 47° 58′ 60″ N 7° 50′ 60″ E  should be 47°58′60″N 7°50′60″E
    hdms = hdms.replace(/(\d+)° (\d+)′ (\d+)″ ([NS]) (\d+)° (\d+)′ (\d+)″ ([EW])/, '$1°$2′$3″$4 $5°$6′$7″$8');
    var pegmanlink = '<a target="street" href="http://maps.google.com/?cbll='
        + latlon[1]
        + ','
        + latlon[0]
        + '&cbp=12,20.09,,0,5&layer=c"><img src="pix/my_location.png" width="16" />'
        + label + '<br/></a><code>' + hdms + '</code>';
    return pegmanlink;
}
