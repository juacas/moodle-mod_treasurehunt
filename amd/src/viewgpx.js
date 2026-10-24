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
import {progressAt} from 'mod_treasurehunt/replay_progress';

let init = {
    refreshTracksInterval: 60,
    refreshLocked: false,
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
            'trackviewerheatmap', 'trackviewerrefreshdisabled', 'trackviewerrefreshseconds',
            'trackviewerrefreshinterval', 'trackviewerreplaying', 'trackviewerreplay', 'trackviewerpause',
            'trackviewertimeline'
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
            init.refreshLocked = refreshinterval === 0;
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
}, 5000);
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
    var replay = null;
    loadGpx(users, cmid, map, tracks, layerSwitcher, scheduleHeatmapUpdate, !hasStageBounds, function(entries) {
        if (replay) {
            replay.updateEntries(entries);
        }
    });

    /** Rebuild the heatmap from every loaded GPX line, bounded to avoid excessive canvas work. */
    function rebuildHeatmap() {
        var coordinates = [];
        tracks.getLayers().forEach(function(layer) {
            (layer.get('fullSource') || layer.getSource()).getFeatures().forEach(function(feature) {
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
    var popupTimeout = null;
    /**
     * Show popup with feature info.
     * @param {ol.event} evt
     */
    function showpopup(evt) {
        var element = popup.getElement();
        var coordinate = evt.coordinate;
        window.clearTimeout(popupTimeout);
        $(element).popover('dispose'); // For jquery 4+ it is dispose instead of destroy!
        popup.setPosition(coordinate);
        $(element).popover({
            'placement': 'top',
            'animation': false,
            'html': true,
            'content': displayFeatureInfo(map.getEventPixel(evt.originalEvent), map, coordinate, strings.pegmanlabel)
        });
        $(element).popover('show');
        popupTimeout = window.setTimeout(function() {
            $(element).popover('dispose');
            popup.setPosition(undefined);
        }, 5000);
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
    var refreshlabel = $('#refreshtrackslabel');
    var previousRefreshValue = refreshslider.val();
    replay = createTrackReplay(ol, strings, function(active) {
        if (active) {
            previousRefreshValue = refreshslider.val();
            refreshslider.attr('max', 60).val(60).prop('disabled', false);
        } else {
            refreshslider.attr('max', 70).val(init.refreshLocked ? 70 : previousRefreshValue);
        }
        updateRefreshControl();
    });
    /** Update the refresh interval from the slider and restart its countdown. */
    function updateRefreshControl() {
        if (init.countdownTimer) {
            clearInterval(init.countdownTimer);
            init.countdownTimer = null;
        }
        if (replay.active) {
            var duration = parseInt(refreshslider.val(), 10);
            var replaylabel = strings.trackviewerrefreshseconds.replace(/\{\$a\}/g, duration);
            refreshlabel.text(strings.trackviewerreplaying);
            refreshvalue.text(replaylabel);
            refreshslider.attr('aria-valuetext', replaylabel);
            replay.setDuration(duration);
            $('#timecircle').hide();
            return;
        }
        refreshlabel.text(strings.trackviewerrefreshinterval);
        refreshslider.prop('disabled', init.refreshLocked);
        if (init.refreshLocked) {
            refreshslider.val(70);
        }
        var sliderposition = parseInt(refreshslider.val(), 10);
        var seconds = sliderposition === 70 ? 0 : sliderposition;
        init.refreshTracksInterval = seconds;
        var label = seconds ? strings.trackviewerrefreshseconds.replace(/\{\$a\}/g, seconds) : strings.trackviewerrefreshdisabled;
        refreshvalue.text(label);
        refreshslider.attr('aria-valuetext', label);
        if (!seconds) {
            $('#timecircle').hide();
            return;
        }

        var remaining = seconds;
        $('#timecircle').text(remaining).show();
        init.countdownTimer = setInterval(async function() {
            remaining--;
            if (remaining <= 0) {
                if (init.refreshBusy) {
                    return;
                }
                init.refreshBusy = true;
                try {
                    var response = await fetch('track_status.php?id=' + cmid, {credentials: 'same-origin'});
                    if (response.ok && !(await response.json()).refresh) {
                        init.refreshLocked = true;
                        updateRefreshControl();
                        return;
                    }
                    init.refreshSources.forEach(function(source) {
                        source.clear();
                        source.refresh();
                    });
                } catch (error) {
                    // A temporary status failure should not stop track updates.
                    init.refreshSources.forEach(function(source) {
                        source.clear();
                        source.refresh();
                    });
                } finally {
                    init.refreshBusy = false;
                }
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
 * @param {Function|null} onTracksLoaded Callback with timestamped tracks.
 */
function loadGpx(users, cmid, map, tracksGroup, layerSwitcher, onSourceLoaded = null, fitTracks = false,
    onTracksLoaded = null) {
    var maxExtent = null;
    var pending = users.length;
    var entries = [];
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
        vector.set('fullSource', gpxsource);
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
        var entry = {user, vector, source: gpxsource, markerOverlay, points: []};
        entries.push(entry);
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
            entry.points = readTimedTrackPoints(gpxsource.getFeatures());
            if (onTracksLoaded) {
                onTracksLoaded(entries);
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
 * Extract ordered track coordinates and GPX timestamps from OpenLayers XYM geometries.
 *
 * @param {Array<ol.Feature>} features Loaded GPX features.
 * @returns {Array<Object>} Timed projected coordinates.
 */
function readTimedTrackPoints(features) {
    var points = [];
    features.forEach(function(feature) {
        var geometry = feature.getGeometry();
        if (!geometry) {
            return;
        }
        var layout = geometry.getLayout();
        if (layout !== 'XYM' && layout !== 'XYZM') {
            return;
        }
        var lines = [];
        if (geometry.getType() === 'MultiLineString') {
            lines = geometry.getCoordinates();
        } else if (geometry.getType() === 'LineString') {
            lines = [geometry.getCoordinates()];
        }
        var timeIndex = layout === 'XYZM' ? 3 : 2;
        lines.forEach(function(line) {
            line.forEach(function(point) {
                var time = Number(point[timeIndex]);
                if (Number.isFinite(time) && time > 0 && Number.isFinite(point[0]) && Number.isFinite(point[1])) {
                    points.push({time, coordinate: point.slice(0, 2)});
                }
            });
        });
    });
    points.sort((a, b) => a.time - b.time);
    var totalDistance = 0;
    points.forEach((point, index) => {
        if (index > 0) {
            var from = ol.proj.toLonLat(points[index - 1].coordinate);
            var to = ol.proj.toLonLat(point.coordinate);
            var latitude = (to[1] - from[1]) * Math.PI / 180;
            var longitude = (to[0] - from[0]) * Math.PI / 180;
            var haversine = Math.sin(latitude / 2) ** 2 + Math.cos(from[1] * Math.PI / 180) *
                Math.cos(to[1] * Math.PI / 180) * Math.sin(longitude / 2) ** 2;
            totalDistance += 2 * 6371000 * Math.asin(Math.min(1, Math.sqrt(haversine)));
        }
        point.distance = totalDistance;
    });
    return points;
}

/**
 * Replay all timestamped tracks on a shared clock and timeline.
 *
 * @param {Object} openlayers OpenLayers namespace.
 * @param {Object} strings Translated labels.
 * @param {Function} onModeChange Toggle the refresh or replay slider.
 * @returns {Object} Replay controls.
 */
function createTrackReplay(openlayers, strings, onModeChange) {
    var button = document.getElementById('track-replay');
    var label = document.getElementById('track-replay-label');
    var timeline = document.getElementById('th-participant-progress-trackviewer');
    var track = document.createElement('div');
    track.className = 'th-participant-progress-track th-replay-track';
    track.setAttribute('role', 'slider');
    track.setAttribute('tabindex', '0');
    track.setAttribute('aria-label', strings.trackviewertimeline);
    track.setAttribute('aria-valuemin', '0');
    track.setAttribute('aria-valuemax', '100');
    var elapsed = document.createElement('div');
    elapsed.className = 'th-replay-elapsed';
    var clock = document.createElement('span');
    clock.className = 'th-replay-clock';
    clock.setAttribute('aria-live', 'off');
    track.append(elapsed, clock);
    timeline.replaceChildren(track);
    var entries = [];
    var actors = [];
    var first = 0;
    var last = 0;
    var fraction = 1;
    var duration = 60;
    var anchorFraction = 0;
    var anchorTime = 0;
    var animationFrame = 0;
    var state = {active: false, playing: false};

    /** @param {boolean} playing Whether the replay clock is running. */
    function updateButton(playing) {
        label.textContent = playing ? strings.trackviewerpause : strings.trackviewerreplay;
        button.querySelector('.fa').classList.toggle('fa-play', !playing);
        button.querySelector('.fa').classList.toggle('fa-pause', playing);
    }

    /**
     * Find the last point at or before a timestamp.
     *
     * @param {Array<Object>} points Timestamped points.
     * @param {number} time Current replay time.
     * @returns {number} Index, or -1 if the track has not started.
     */
    function findPoint(points, time) {
        var low = 0;
        var high = points.length;
        while (low < high) {
            var middle = Math.floor((low + high) / 2);
            if (points[middle].time <= time) {
                low = middle + 1;
            } else {
                high = middle;
            }
        }
        return low - 1;
    }

    /**
     * Draw the recorded route up to a timestamp and move its avatar.
     *
     * @param {Object} entry Participant's track.
     * @param {number} time Current replay time.
     */
    function drawEntry(entry, time) {
        var points = entry.points;
        var index = findPoint(points, time);
        if (index < 0) {
            if (state.active) {
                entry.replayGeometry.setCoordinates([]);
                entry.markerOverlay.setPosition(undefined);
            }
            return;
        }
        var point = points[index];
        if (!state.active) {
            return;
        }
        var position = point.coordinate;
        if (index + 1 < points.length && points[index + 1].time > time) {
            var next = points[index + 1];
            var ratio = (time - point.time) / (next.time - point.time);
            position = [
                point.coordinate[0] + (next.coordinate[0] - point.coordinate[0]) * ratio,
                point.coordinate[1] + (next.coordinate[1] - point.coordinate[1]) * ratio,
            ];
        }
        var visible = points.slice(0, index + 1).map((record) => record.coordinate);
        visible.push(position);
        entry.replayGeometry.setCoordinates(visible);
        entry.markerOverlay.setPosition(position);
    }

    /**
     * Move team and individual markers on the game-progress axis.
     *
     * @param {number} time Replay timestamp.
     * @returns {void}
     */
    function drawActors(time) {
        actors.forEach((actor) => {
            if (!actor.totalstages) {
                actor.marker.hidden = true;
                return;
            }
            var progress = progressAt(actor, time);
            actor.marker.hidden = false;
            actor.marker.style.left = (5 + progress.fraction * 90) + '%';
            actor.marker.dataset.state = progress.status;
            actor.marker.title = actor.name + ' · ' + progress.completed + '/' + actor.totalstages;
        });
    }

    /** @param {number} position Fraction of the global time range. */
    function render(position) {
        fraction = Math.max(0, Math.min(1, position));
        var time = first + (last - first) * fraction;
        drawActors(time);
        elapsed.style.setProperty('--th-replay-time-fraction', String(fraction));
        track.setAttribute('aria-valuenow', String(Math.round(fraction * 100)));
        var date = first ? new Date(time * 1000).toLocaleString() : '';
        track.setAttribute('aria-valuetext', date);
        clock.textContent = date;
        entries.forEach((entry) => drawEntry(entry, time));
    }

    /** Restore complete tracks after playback. */
    function stop() {
        if (!state.active) {
            return;
        }
        window.cancelAnimationFrame(animationFrame);
        state.active = false;
        state.playing = false;
        entries.forEach((entry) => {
            entry.vector.setSource(entry.source);
            if (entry.points.length) {
                entry.markerOverlay.setPosition(entry.points[entry.points.length - 1].coordinate);
            }
        });
        render(1);
        updateButton(false);
        onModeChange(false);
    }

    /** Advance the shared replay clock. */
    function frame() {
        if (!state.playing) {
            return;
        }
        var next = anchorFraction + (performance.now() - anchorTime) / (duration * 1000);
        render(next);
        if (fraction >= 1) {
            stop();
        } else {
            animationFrame = window.requestAnimationFrame(frame);
        }
    }

    /** Start or resume playback. */
    function play() {
        if (last <= first) {
            return;
        }
        if (!state.active) {
            state.active = true;
            entries.forEach((entry) => entry.vector.setSource(entry.replaySource));
            onModeChange(true);
            render(0);
        } else if (fraction >= 1) {
            render(0);
        }
        state.playing = true;
        anchorFraction = fraction;
        anchorTime = performance.now();
        updateButton(true);
        animationFrame = window.requestAnimationFrame(frame);
    }

    /** Pause playback without discarding the selected time. */
    function pause() {
        state.playing = false;
        window.cancelAnimationFrame(animationFrame);
        updateButton(false);
    }

    /**
     * Seek by pointing at the timeline, including touch drags.
     *
     * @param {PointerEvent} event Pointer event.
     */
    function seekPointer(event) {
        var bounds = track.getBoundingClientRect();
        // Use the fill's untransformed box so the pointer matches its visible end.
        var target = Math.max(0, Math.min(1, (event.clientX - bounds.left - elapsed.offsetLeft) /
            elapsed.offsetWidth));
        if (!state.active) {
            state.active = true;
            entries.forEach((entry) => entry.vector.setSource(entry.replaySource));
            onModeChange(true);
        }
        render(target);
        if (state.playing) {
            anchorFraction = fraction;
            anchorTime = performance.now();
        }
    }

    button.addEventListener('click', () => state.playing ? pause() : play());
    track.addEventListener('pointerdown', (event) => {
        if (last <= first) {
            return;
        }
        event.preventDefault();
        track.setPointerCapture(event.pointerId);
        seekPointer(event);
    });
    track.addEventListener('pointermove', (event) => {
        if (track.hasPointerCapture(event.pointerId)) {
            seekPointer(event);
        }
    });
    track.addEventListener('keydown', (event) => {
        if (last <= first) {
            return;
        }
        var step = 0;
        if (event.key === 'ArrowLeft' || event.key === 'ArrowDown') {
            step = -0.01;
        } else if (event.key === 'ArrowRight' || event.key === 'ArrowUp') {
            step = 0.01;
        } else if (event.key === 'Home') {
            step = -1;
        } else if (event.key === 'End') {
            step = 1;
        } else {
            return;
        }
        event.preventDefault();
        if (!state.active) {
            state.active = true;
            entries.forEach((entry) => entry.vector.setSource(entry.replaySource));
            onModeChange(true);
        }
        if (event.key === 'Home') {
            render(0);
        } else if (event.key === 'End') {
            render(1);
        } else {
            render(fraction + step);
        }
        if (state.playing) {
            anchorFraction = fraction;
            anchorTime = performance.now();
        }
    });
    window.addEventListener('pagehide', () => window.cancelAnimationFrame(animationFrame), {once: true});

    /** @param {Array<Object>} loadedEntries Tracks with their current GPX points. */
    state.updateEntries = (loadedEntries) => {
        entries = loadedEntries;
        var previousActors = new Map(actors.map((actor) => [actor.key, actor]));
        var byKey = new Map();
        entries.forEach((entry) => {
            var user = entry.user;
            var key = (user.teamid ? 'g' + user.teamid : 'u' + user.id) + 'r' + user.roadid;
            var actor = byKey.get(key);
            if (!actor) {
                actor = previousActors.get(key) || {
                    key,
                    name: user.teamname || user.fullname,
                    marker: null,
                };
                actor.members = [];
                actor.attempts = user.attempts || [];
                actor.totalstages = Number(user.totalstages || 0);
                byKey.set(key, actor);
            }
            actor.members.push(entry);
        });
        actors = Array.from(byKey.values());
        previousActors.forEach((actor, key) => {
            if (!byKey.has(key)) {
                actor.marker.remove();
            }
        });
        first = Infinity;
        last = -Infinity;
        entries.forEach((entry) => entry.points.forEach((point) => {
            first = Math.min(first, point.time);
            last = Math.max(last, point.time);
        }));
        actors.forEach((actor) => {
            actor.firstTrackTime = Infinity;
            actor.lastTrackTime = -Infinity;
            actor.members.forEach((entry) => {
                if (entry.points.length) {
                    actor.firstTrackTime = Math.min(actor.firstTrackTime, entry.points[0].time);
                    actor.lastTrackTime = Math.max(actor.lastTrackTime, entry.points[entry.points.length - 1].time);
                }
            });
            if (!Number.isFinite(actor.firstTrackTime)) {
                actor.firstTrackTime = 0;
                actor.lastTrackTime = 0;
            }
            actor.attempts.forEach((attempt) => {
                first = Math.min(first, attempt.time);
                last = Math.max(last, attempt.time);
            });
        });
        if (!Number.isFinite(first)) {
            first = 0;
            last = 0;
        }
        button.disabled = last <= first;
        track.setAttribute('aria-disabled', String(last <= first));
        actors.forEach((actor, index) => {
            if (!actor.marker) {
                var marker = document.createElement('span');
                marker.className = 'th-participant-marker';
                marker.setAttribute('aria-hidden', 'true');
                var avatar = document.createElement('span');
                avatar.className = 'th-participant-avatar';
                var user = actor.members[0].user;
                avatar.style.background = user.teamid ? teamColor(user.teamid) : trackColor(user.id);
                if (user.pictureurl && !user.teamid) {
                    var picture = document.createElement('img');
                    picture.src = user.pictureurl;
                    picture.alt = '';
                    avatar.appendChild(picture);
                } else {
                    avatar.textContent = user.initials || '?';
                }
                marker.appendChild(avatar);
                track.appendChild(marker);
                actor.marker = marker;
            }
            actor.marker.style.setProperty('--th-marker-top', ['50%', '22%', '78%'][index % 3]);
        });
        entries.forEach((entry) => {
            if (!entry.replayGeometry) {
                entry.replayGeometry = new openlayers.geom.LineString([]);
                entry.replaySource = new openlayers.source.Vector({
                    features: [new openlayers.Feature(entry.replayGeometry)]
                });
            }
        });
        render(state.active ? fraction : 1);
    };
    state.setDuration = (seconds) => {
        if (state.playing) {
            anchorFraction = fraction;
            anchorTime = performance.now();
        }
        duration = Math.max(1, seconds);
    };
    return state;
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
