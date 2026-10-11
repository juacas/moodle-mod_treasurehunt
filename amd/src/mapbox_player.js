// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Geographic Treasurehunt player backed by Mapbox GL JS.
 *
 * @module mod_treasurehunt/mapbox_player
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import ajax from 'core/ajax';
import {setup as setupLocationSearch} from 'mod_treasurehunt/location_search';
import {setup as setupTouchGestureGuard} from 'mod_treasurehunt/touch_gesture_guard';
import webqr from 'mod_treasurehunt/webqr';

const MAPBOX_URL = 'https://api.mapbox.com/mapbox-gl-js/v3.32.0/mapbox-gl.js';
const EMPTY = {type: 'FeatureCollection', features: []};
let mapboxPromise;

/** @param {unknown} value Setting value. @returns {boolean} Enabled state. */
const enabled = (value) => value === true || value === 1 || value === '1' || value === 'true';

/** @returns {Promise<Object>} Mapbox GL JS namespace. */
const loadMapbox = () => {
    if (window.mapboxgl) {
        return Promise.resolve(window.mapboxgl);
    }
    if (!mapboxPromise) {
        mapboxPromise = new Promise((resolve, reject) => {
            // The Mapbox distribution has an anonymous AMD define; RequireJS must own its load.
            window.requirejs([MAPBOX_URL], (library) => {
                window.mapboxgl = library;
                resolve(library);
            }, reject);
        }).catch((error) => {
            mapboxPromise = null;
            throw error;
        });
    }
    return mapboxPromise;
};

/** @param {Array} coordinates Nested GeoJSON coordinates. @returns {Array<Array<number>>} Geographic pairs. */
const pairs = (coordinates) => {
    if (!Array.isArray(coordinates)) {
        return [];
    }
    return typeof coordinates[0] === 'number' ? [coordinates] : coordinates.flatMap(pairs);
};

/** @param {Object} data GeoJSON collection. @returns {Array<number>|null} Geographic bounds. */
const boundsOf = (data) => {
    const points = (data?.features || []).flatMap((feature) => pairs(feature.geometry?.coordinates));
    if (!points.length) {
        return null;
    }
    return points.reduce((box, point) => [Math.min(box[0], point[0]), Math.min(box[1], point[1]),
        Math.max(box[2], point[0]), Math.max(box[3], point[1])], [180, 90, -180, -90]);
};

/**
 * @param {Array<number>} point Geographic point.
 * @param {Array} ring Polygon ring.
 * @returns {boolean} Inside.
 */
const inRing = (point, ring) => {
    let inside = false;
    for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
        const a = ring[i];
        const b = ring[j];
        if ((a[1] > point[1]) !== (b[1] > point[1]) &&
                point[0] < (b[0] - a[0]) * (point[1] - a[1]) / (b[1] - a[1]) + a[0]) {
            inside = !inside;
        }
    }
    return inside;
};

/**
 * @param {Array<number>} point Geographic point.
 * @param {Object} geometry GeoJSON polygon.
 * @returns {boolean} Inside the geometry.
 */
const inGeometry = (point, geometry) => {
    let polygons = [];
    if (geometry?.type === 'Polygon') {
        polygons = [geometry.coordinates];
    } else if (geometry?.type === 'MultiPolygon') {
        polygons = geometry.coordinates;
    }
    return polygons.some((rings) => rings.length && inRing(point, rings[0]) &&
        !rings.slice(1).some((hole) => inRing(point, hole)));
};

/**
 * @param {Array<number>} point Player position.
 * @param {Object} collection Eligible stage geometries.
 * @returns {Array<number>|null} Closest point on a stage boundary.
 */
const closestStagePoint = (point, collection) => {
    let closest = null;
    let shortest = Infinity;
    const longitudeScale = Math.cos(point[1] * Math.PI / 180);
    (collection?.features || []).forEach((feature) => {
        if (inGeometry(point, feature.geometry)) {
            closest = point;
            shortest = 0;
            return;
        }
        let polygons = [];
        if (feature.geometry?.type === 'Polygon') {
            polygons = [feature.geometry.coordinates];
        } else if (feature.geometry?.type === 'MultiPolygon') {
            polygons = feature.geometry.coordinates;
        }
        polygons.forEach((rings) => rings.forEach((ring) => {
            for (let index = 1; index < ring.length; index++) {
                const start = ring[index - 1];
                const end = ring[index];
                const dx = (end[0] - start[0]) * longitudeScale;
                const dy = end[1] - start[1];
                const lengthSquared = dx * dx + dy * dy;
                const along = lengthSquared ? Math.max(0, Math.min(1,
                    ((point[0] - start[0]) * longitudeScale * dx + (point[1] - start[1]) * dy) / lengthSquared)) : 0;
                const candidate = [start[0] + along * (end[0] - start[0]), start[1] + along * (end[1] - start[1])];
                const distance = ((point[0] - candidate[0]) * longitudeScale) ** 2 +
                    (point[1] - candidate[1]) ** 2;
                if (distance < shortest) {
                    shortest = distance;
                    closest = candidate;
                }
            }
        }));
    });
    return closest || null;
};

/** @param {Object} mapping Activity map configuration. @returns {Object|null} Raster source. */
const customSource = (mapping) => {
    if (mapping?.custombackgroundurl && Array.isArray(mapping.bbox) && mapping.bbox.length === 4) {
        const [west, south, east, north] = mapping.bbox;
        return {type: 'image', url: mapping.custombackgroundurl,
            coordinates: [[west, north], [east, north], [east, south], [west, south]]};
    }
    if (!mapping?.wmsurl) {
        return null;
    }
    if (mapping.layerservicetype === 'tiled') {
        return {type: 'raster', tiles: [mapping.wmsurl.replace(/\{-y\}/g, '{y}')],
            scheme: mapping.wmsurl.includes('{-y}') ? 'tms' : 'xyz', tileSize: 256, maxzoom: 19};
    }
    if (mapping.layerservicetype === 'arcgis') {
        return {type: 'raster', tiles: [mapping.wmsurl.replace(/\/$/, '') + '/tile/{z}/{y}/{x}'],
            tileSize: 256, maxzoom: 19};
    }
    if (mapping.layerservicetype === 'wms') {
        const params = new URLSearchParams(Object.assign({}, mapping.wmsparams || {}, {
            SERVICE: 'WMS', REQUEST: 'GetMap', VERSION: '1.1.1', FORMAT: 'image/png',
            TRANSPARENT: 'TRUE', SRS: 'EPSG:3857', WIDTH: 256, HEIGHT: 256, BBOX: '{bbox-epsg-3857}',
            LAYERS: mapping.wmsparams?.LAYERS || mapping.layername || '',
        }));
        return {type: 'raster', tiles: [mapping.wmsurl + (mapping.wmsurl.includes('?') ? '&' : '?') +
            params.toString().replace('%7Bbbox-epsg-3857%7D', '{bbox-epsg-3857}')], tileSize: 256};
    }
    return null;
};

/**
 * @param {string} kind Base map name.
 * @param {Object} mapping Activity map settings.
 * @returns {Object} Mapbox style.
 */
const mapStyle = (kind, mapping) => {
    if (kind === 'satellite') {
        return 'mapbox://styles/mapbox/satellite-streets-v12';
    }
    if (kind === 'streets') {
        return 'mapbox://styles/mapbox/streets-v12';
    }
    const source = kind === 'custom' ? customSource(mapping) : null;
    return {version: 8, sources: {base: source || {
        type: 'raster', tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'], tileSize: 256,
        maxzoom: 19, attribution: '© OpenStreetMap contributors',
    }}, layers: [{id: 'base', type: 'raster', source: 'base'}]};
};

/** @returns {Promise<void>} Initialisation completion. */
export const init = async() => {
    const root = document.getElementById('cessium-player');
    if (!root) {
        return;
    }
    root.classList.add('mapbox-player');
    const config = JSON.parse(document.getElementById('cessium-config').textContent);
    const labels = config.labels;
    const node = (name) => document.getElementById('cessium-' + name);
    const loading = node('loading');
    const retry = node('retry');
    const playerconfig = config.playerconfig || {};
    let map;
    let mapboxgl;
    let touchGuard;
    let position = null;
    let gpsposition = null;
    let gpswatch = null;
    let playwithoutmoving = enabled(config.playwithoutmoving) || Boolean(config.previewroadid);
    let groupmode = enabled(config.groupmode);
    let attemptid = 0;
    let roadtimestamp = 0;
    let qoaremoved = false;
    let stage = null;
    let nextstage = null;
    let clues = [];
    let selectedClue = 0;
    let roadfinished = false;
    let available = true;
    let qrexpected = false;
    let totalstages = 0;
    let history = [];
    let attemptCollection = EMPTY;
    let pollTimer;
    let toastTimer;
    let requestQueue = Promise.resolve();
    let selectedAttempt = null;
    let firstResponse = true;
    let firstStyle = true;
    let initialMapReady;
    // Start with a public OSM raster style so the map remains usable without Mapbox style access.
    let currentStyle = 'osm';
    if (customSource(config.custommapping)) {
        currentStyle = 'custom';
    } else if (config.mapboxaccesstoken) {
        currentStyle = 'streets';
    }

    /**
     * @param {string} message Message.
     * @param {boolean} error Error state.
     */
    const toast = (message, error = false) => {
        if (!message) {
            return;
        }
        const target = node('toast');
        target.textContent = message;
        target.classList.toggle('is-error', error);
        target.hidden = false;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => {
            target.hidden = true;
        }, 5500);
    };

    /** @param {string} name Dialog suffix. */
    const openDialog = (name) => {
        loading.hidden = true;
        const dialog = node(name + '-dialog');
        if (!dialog.open) {
            dialog.showModal();
        }
    };

    /** @param {HTMLDialogElement} dialog Dialog to close. */
    const closeDialog = (dialog) => {
        if (dialog.id === 'cessium-qr-dialog') {
            webqr.unloadQR(() => undefined);
        }
        if (dialog.open) {
            dialog.close();
        }
    };

    /** Repaint all GeoJSON layers after a style change. */
    const installLayers = () => {
        if (currentStyle !== 'custom' && map.getSource('composite') && !map.getLayer('mapbox-3d-buildings')) {
            const labelLayer = map.getStyle().layers.find((layer) => layer.type === 'symbol' && layer.layout?.['text-field']);
            map.addLayer({id: 'mapbox-3d-buildings', source: 'composite', 'source-layer': 'building',
                filter: ['==', ['get', 'extrude'], 'true'], type: 'fill-extrusion', minzoom: 15,
                paint: {'fill-extrusion-color': '#aebac5',
                    'fill-extrusion-height': ['interpolate', ['linear'], ['zoom'], 15, 0, 15.05, ['get', 'height']],
                    'fill-extrusion-base': ['interpolate', ['linear'], ['zoom'], 15, 0, 15.05, ['get', 'min_height']],
                    'fill-extrusion-opacity': 0.72,
                }}, labelLayer?.id);
        }
        if (!map.getSource('pegman')) {
            map.addSource('pegman', {type: 'geojson', data: EMPTY});
            map.addModel('treasurehunt-pegman', config.pegmanmodelurl);
            map.addLayer({id: 'pegman-model', type: 'model', source: 'pegman',
                layout: {'model-id': 'treasurehunt-pegman'},
                paint: {'model-scale': [12, 12, 12], 'model-type': 'location-indicator'}});
        }
        if (!map.getSource('stage')) {
            map.addSource('stage', {type: 'geojson', data: EMPTY});
            map.addLayer({id: 'stage-fill', type: 'fill', source: 'stage',
                paint: {'fill-color': '#5ce8f6', 'fill-opacity': 0.25}});
            map.addLayer({id: 'stage-outline', type: 'line', source: 'stage',
                paint: {'line-color': '#5ce8f6', 'line-width': 4}});
        }
        if (!map.getSource('attempts')) {
            map.addSource('attempts', {type: 'geojson', data: EMPTY});
            map.addLayer({id: 'attempts-points', type: 'circle', source: 'attempts',
                paint: {'circle-radius': 13, 'circle-color': ['case', ['boolean', ['get', 'geometrysolved'], false],
                    '#41d48a', '#f17575'], 'circle-stroke-width': 3, 'circle-stroke-color': '#fff'}});
            map.addLayer({id: 'attempts-number', type: 'symbol', source: 'attempts',
                layout: {
                    'icon-image': ['concat', 'attempt-number-', ['to-string', ['coalesce', ['get', 'stageposition'], 0]]],
                    'icon-allow-overlap': true,
                    'icon-size': 0.8,
                }});
        }
        if (!map.getSource('gps')) {
            map.addSource('gps', {type: 'geojson', data: EMPTY});
            map.addLayer({id: 'gps-dot', type: 'circle', source: 'gps', paint: {
                'circle-radius': 6, 'circle-color': '#5ce8f6', 'circle-stroke-width': 2,
                'circle-stroke-color': '#fff',
            }});
        }
        if (!map.getSource('navigation')) {
            map.addSource('navigation', {type: 'geojson', data: EMPTY});
            map.addLayer({id: 'navigation-line', type: 'line', source: 'navigation', paint: {
                'line-color': '#39ff72', 'line-width': 4, 'line-dasharray': [2, 1],
            }});
        }
        if (!firstStyle) {
            updateStage();
            updateAttempts(attemptCollection);
            updatePosition();
            updateGps();
            updateNavigation();
        }
        firstStyle = false;
    };

    /** Place the 3D pegman at the selected or device position. */
    const updatePosition = () => {
        if (!map?.getSource('pegman')) {
            return;
        }
        map.getSource('pegman').setData(position ? {type: 'FeatureCollection', features: [{type: 'Feature',
            properties: {}, geometry: {type: 'Point', coordinates: position}}]} : EMPTY);
        updatePegmanScale();
        updateNavigation();
        node('submit').disabled = !position || roadfinished || !available || Boolean(stage?.question) ||
            stage?.activitysolved === false;
    };

    /** Keep the 3D pegman legible while preserving perspective on the map. */
    const updatePegmanScale = () => {
        if (!position || !map?.getLayer('pegman-model')) {
            return;
        }
        const metresPerPixel = 156543.03392 * Math.cos(position[1] * Math.PI / 180) / (2 ** map.getZoom());
        const scale = Math.max(8, Math.min(5000, 90 * metresPerPixel / (0.9 * 4)));
        map.setPaintProperty('pegman-model', 'model-scale', [scale, scale, scale]);
    };

    /** Place the separate GPS point when available. */
    const updateGps = () => {
        if (map?.getSource('gps')) {
            map.getSource('gps').setData(gpsposition ? {type: 'FeatureCollection', features: [{type: 'Feature',
                properties: {}, geometry: {type: 'Point', coordinates: gpsposition}}]} : EMPTY);
        }
    };

    /** @param {Array<number>} box Geographic bounds. */
    const fitBounds = (box) => map.fitBounds([[box[0], box[1]], [box[2], box[3]]], {
        padding: 100, duration: 1100, maxZoom: 17, pitch: 55,
    });

    /** Frame visible stage geometry, latest attempt, or the player's position. */
    const fitMap = () => {
        const visible = (nextstage?.features || []).filter((feature) =>
            Number(feature.properties?.stageposition) === 1 || enabled(playerconfig.shownextareahint));
        const box = boundsOf({features: visible}) || boundsOf(attemptCollection) ||
            (Array.isArray(config.custommapping?.bbox) ? config.custommapping.bbox : null);
        if (box) {
            fitBounds(box);
        } else if (position || gpsposition) {
            map.flyTo({center: position || gpsposition, zoom: 16, pitch: 55, duration: 1100});
        }
    };

    /** Render only geometries that the game permits players to see. */
    const updateStage = () => {
        if (!map?.getSource('stage')) {
            return;
        }
        const visible = (nextstage?.features || []).filter((feature) =>
            Number(feature.properties?.stageposition) === 1 || enabled(playerconfig.shownextareahint));
        map.getSource('stage').setData({type: 'FeatureCollection', features: visible});
        node('stage-number').textContent = String(nextstage?.features?.[0]?.properties?.stageposition ||
            (roadfinished ? '✓' : (Number(stage?.position || 0) + 1)));
        updateNavigation();
    };

    /** Show distance and direction hints only when the activity enables them. */
    const updateNavigation = () => {
        const panel = node('navigation');
        const target = position ? closestStagePoint(position, nextstage) : null;
        const showDistance = enabled(playerconfig.showdistancehint);
        const showHeading = enabled(playerconfig.showheadinghint);
        const showInZone = enabled(playerconfig.showinzonehint);
        panel.hidden = !target || !(showDistance || showInZone);
        let line = EMPTY;
        if (target) {
            const radians = Math.PI / 180;
            const deltaLatitude = (target[1] - position[1]) * radians;
            const deltaLongitude = (target[0] - position[0]) * radians;
            const a = Math.sin(deltaLatitude / 2) ** 2 + Math.cos(position[1] * radians) *
                Math.cos(target[1] * radians) * Math.sin(deltaLongitude / 2) ** 2;
            const distance = 12742000 * Math.asin(Math.min(1, Math.sqrt(a)));
            const inside = distance < 0.5;
            let distanceText = '—';
            if (showInZone && inside) {
                distanceText = labels.insidezone;
            } else if (showDistance) {
                distanceText = distance >= 1000 ? (distance / 1000).toFixed(1) + ' km' : Math.round(distance) + ' m';
            }
            node('distance').textContent = distanceText;
            node('position-status').textContent = playwithoutmoving ? labels.selectedposition : labels.gpsposition;
            if (showHeading && !inside) {
                const from = map.project(position);
                const toward = map.project(target);
                const length = Math.hypot(toward.x - from.x, toward.y - from.y);
                if (length > 1) {
                    const arrowTip = map.unproject([from.x + 60 * (toward.x - from.x) / length,
                        from.y + 60 * (toward.y - from.y) / length]);
                    line = {type: 'FeatureCollection', features: [{type: 'Feature', properties: {},
                        geometry: {type: 'LineString', coordinates: [position, [arrowTip.lng, arrowTip.lat]]}}]};
                }
            }
        }
        if (map?.getSource('navigation')) {
            map.getSource('navigation').setData(line);
        }
    };

    /** @param {Object} collection Attempt locations. */
    const updateAttempts = (collection) => {
        attemptCollection = collection || EMPTY;
        if (map?.getSource('attempts')) {
            (attemptCollection.features || []).forEach((feature) => {
                const number = Number(feature.properties?.stageposition) || 0;
                const imageid = 'attempt-number-' + number;
                if (number > 0 && !map.hasImage(imageid)) {
                    const canvas = document.createElement('canvas');
                    canvas.width = 48;
                    canvas.height = 48;
                    const context = canvas.getContext('2d');
                    context.font = 'bold 30px sans-serif';
                    context.textAlign = 'center';
                    context.textBaseline = 'middle';
                    context.lineJoin = 'round';
                    context.lineWidth = 5;
                    context.strokeStyle = '#163040';
                    context.fillStyle = '#fff';
                    context.strokeText(String(number), 24, 24);
                    context.fillText(String(number), 24, 24);
                    map.addImage(imageid, canvas, {pixelRatio: 2});
                }
            });
            map.getSource('attempts').setData(attemptCollection);
        }
    };

    /** @param {Object} feature Selected attempt feature. */
    const showAttempt = (feature) => {
        selectedAttempt = feature;
        const properties = feature.properties || {};
        const solved = enabled(properties.geometrysolved);
        node('attempt-title').textContent = solved ? labels.stageovercome : labels.failedlocation;
        const content = node('attempt-content');
        content.replaceChildren();
        if (properties.info) {
            const item = document.createElement('div');
            item.innerHTML = properties.info;
            content.appendChild(item);
        }
        [[labels.stagename, solved ? properties.name : null], [labels.stageclue, properties.clue]].forEach(([label, value]) => {
            if (value) {
                const field = document.createElement('div');
                field.className = 'cessium-attempt-field';
                const heading = document.createElement('span');
                heading.textContent = label;
                const body = document.createElement('div');
                body.innerHTML = value;
                field.append(heading, body);
                content.appendChild(field);
            }
        });
        positionAttempt();
    };

    /** Position the attempt card beside its map marker. */
    const positionAttempt = () => {
        if (!selectedAttempt) {
            return;
        }
        const point = map.project(selectedAttempt.geometry.coordinates);
        const card = node('attempt-popup');
        card.hidden = point.x < 0 || point.y < 0 || point.x > map.getCanvas().clientWidth ||
            point.y > map.getCanvas().clientHeight;
        root.classList.toggle('has-attempt-popup', !card.hidden);
        card.classList.toggle('is-below', point.y < 250);
        card.style.left = Math.max(170, Math.min(root.clientWidth - 170, point.x)) + 'px';
        card.style.top = point.y + 'px';
    };

    /**
     * @param {number} index Selected clue.
     * @param {boolean} focus Whether to frame its geometry.
     */
    const selectClue = (index, focus) => {
        selectedClue = index;
        node('clue-tabs').querySelectorAll('[role="tab"]').forEach((tab, number) => {
            tab.classList.toggle('is-active', number === index);
            tab.setAttribute('aria-selected', String(number === index));
            tab.tabIndex = number === index ? 0 : -1;
        });
        Array.from(node('clue-content').children).forEach((pane, number) => {
            pane.classList.toggle('is-active', number === index);
            pane.setAttribute('aria-hidden', String(number !== index));
        });
        if (focus && enabled(playerconfig.shownextareahint)) {
            const feature = nextstage?.features?.find((item) => Number(item.id) === Number(clues[index]?.stageid));
            const box = boundsOf({features: feature ? [feature] : []});
            if (box) {
                fitBounds(box);
            }
        }
    };

    /** Render one tab for each available clue. */
    const updateClues = () => {
        const tabs = node('clue-tabs');
        const content = node('clue-content');
        tabs.replaceChildren();
        content.replaceChildren();
        tabs.hidden = !clues.length;
        node('clue-title').textContent = clues.length > 1 ? labels.nextclues : labels.nextclue;
        if (!clues.length) {
            content.innerHTML = stage?.clue || '';
            if (!content.textContent.trim() && !content.querySelector('img, video, iframe')) {
                content.textContent = labels.nohint;
            }
            return;
        }
        clues.forEach((clue, index) => {
            const tab = document.createElement('button');
            tab.type = 'button';
            tab.id = 'cessium-clue-tab-' + index;
            tab.className = 'cessium-clue-tab';
            tab.setAttribute('role', 'tab');
            tab.setAttribute('aria-controls', 'cessium-clue-panel-' + index);
            tab.textContent = labels.clue + ' ' + clue.position;
            tab.addEventListener('click', () => selectClue(index, true));
            tabs.appendChild(tab);
            const pane = document.createElement('div');
            pane.id = 'cessium-clue-panel-' + index;
            pane.className = 'cessium-clue-pane cessium-richtext';
            pane.setAttribute('role', 'tabpanel');
            pane.setAttribute('aria-labelledby', tab.id);
            pane.innerHTML = clue.html;
            content.appendChild(pane);
        });
        selectClue(Math.min(selectedClue, clues.length - 1), false);
    };

    /** Update question and answers from the latest completed stage. */
    const updateQuestion = () => {
        node('objective-text').textContent = stage?.name || labels.nohint;
        node('question-open').hidden = !stage?.question;
        node('question-content').innerHTML = stage?.question || '';
        const answers = node('answers');
        answers.replaceChildren();
        (stage?.answers || []).forEach((answer) => {
            const label = document.createElement('label');
            label.className = 'cessium-answer';
            const input = document.createElement('input');
            input.type = 'radio';
            input.name = 'mapbox-answer';
            input.value = String(answer.id);
            const text = document.createElement('span');
            text.innerHTML = answer.answertext;
            label.append(input, text);
            answers.appendChild(label);
        });
    };

    /** Refresh progress meter and action availability. */
    const updateActions = () => {
        const complete = roadfinished ? totalstages : Number(stage?.position || 0);
        const total = Math.max(totalstages, complete);
        const percent = total ? Math.round(100 * complete / total) : 0;
        node('progress-label').textContent = complete + ' / ' + (total || '—');
        node('progress-bar').style.width = percent + '%';
        node('progress-track').setAttribute('aria-valuenow', String(percent));
        node('finished').hidden = !roadfinished;
        node('clue').disabled = roadfinished;
        node('qr').hidden = !qrexpected || roadfinished || !available || Boolean(stage?.question) ||
            stage?.activitysolved === false;
        let status = available ? labels.wait : labels.timeexceeded;
        if (roadfinished) {
            status = labels.completed;
        }
        node('sync-status').textContent = status;
        updatePosition();
    };

    /** @param {Object} response Game service response. */
    const updateMode = (response) => {
        attemptid = Number(response.attemptid || 0);
        roadtimestamp = Number(response.roadtimestamp || 0);
        roadfinished = enabled(response.roadfinished);
        available = enabled(response.available);
        qrexpected = enabled(response.qrexpected);
        qoaremoved = enabled(response.qoaremoved);
        groupmode = enabled(response.groupmode);
        Object.assign(playerconfig, response.playerconfig || {});
        if (playwithoutmoving !== enabled(response.playwithoutmoving)) {
            playwithoutmoving = enabled(response.playwithoutmoving);
            if (!playwithoutmoving) {
                startGps();
            } else if (gpswatch !== null) {
                navigator.geolocation.clearWatch(gpswatch);
                gpswatch = null;
            }
        }
    };

    /**
     * @param {Object} response Game service response.
     * @param {Object} action Submitted action.
     */
    const showMessages = (response, action) => {
        if (action.location || action.qrtext || action.selectedanswerid) {
            toast(response.status?.msg, Number(response.status?.code) !== 0);
        }
        if (response.infomsg?.length) {
            const message = response.infomsg.map((item) => {
                const div = document.createElement('div');
                div.innerHTML = item;
                return div.textContent.trim();
            }).filter(Boolean).join(' · ');
            toast(message);
        }
    };

    /** @param {Object} response Game service response. @returns {boolean} Whether clues changed. */
    const updateAvailableClues = (response) => {
        const oldClues = JSON.stringify(clues);
        clues = Array.isArray(response.clues) ? response.clues : [];
        if (oldClues !== JSON.stringify(clues)) {
            selectedClue = 0;
            updateClues();
            return true;
        }
        return false;
    };

    /**
     * @param {Object} response Game service response.
     * @param {Object} action Submitted action.
     * @param {number|undefined} previousStage Previously completed stage ID.
     */
    const updateStageState = (response, action, previousStage) => {
        if (response.lastsuccessfulstage) {
            stage = response.lastsuccessfulstage;
            totalstages = Number(stage.totalnumber || 0);
            updateQuestion();
        } else if (action.initialize) {
            stage = null;
            updateQuestion();
        }
        if (response.nextstage) {
            nextstage = response.nextstage;
        } else if (roadfinished || previousStage !== stage?.id) {
            nextstage = null;
        }
        updateStage();
        if (response.attempts) {
            updateAttempts(response.attempts);
        }
    };

    /**
     * @param {Object} response Game service response.
     * @param {Object} action Submitted action.
     */
    const applyResponse = (response, action) => {
        const previousStage = stage?.id;
        updateMode(response);
        node('finished-stage').innerHTML = roadfinished ? response.endmessage || '' : '';
        node('finished-stage').hidden = !roadfinished || !response.endmessage;
        if (roadfinished && node('clue-dialog').open) {
            closeDialog(node('clue-dialog'));
        }
        updateStageState(response, action, previousStage);
        const cluesChanged = updateAvailableClues(response);
        if (Array.isArray(response.attempthistory)) {
            history = response.attempthistory;
        }
        updateActions();
        if (firstResponse) {
            fitMap();
            firstResponse = false;
        } else if (previousStage !== stage?.id && nextstage) {
            fitMap();
        }
        showMessages(response, action);
        if (!roadfinished && (action.initialize || previousStage !== stage?.id || cluesChanged)) {
            const showClue = () => openDialog('clue');
            if (map.isMoving()) {
                map.once('moveend', showClue);
            } else {
                showClue();
            }
        }
    };

    /** @param {Object} action Game action. @returns {Promise<void>} Service request completion. */
    const request = (action = {}) => {
        clearTimeout(pollTimer);
        requestQueue = requestQueue.catch(() => undefined).then(async() => {
            const blocking = action.initialize || action.location || action.qrtext || action.selectedanswerid;
            if (blocking) {
                loading.hidden = false;
                retry.hidden = true;
            }
            const params = {
                treasurehuntid: config.treasurehuntid,
                previewroadid: config.previewroadid,
                attemptid,
                roadtimestamp,
                playwithoutmoving,
                groupmode,
                initialize: Boolean(action.initialize),
                selectedanswerid: Number(action.selectedanswerid || 0),
                qoaremoved,
            };
            if (action.location) {
                params.location = {type: 'Point', coordinates: action.location};
            }
            if (action.qrtext) {
                params.qrtext = action.qrtext;
            }
            if (config.tracking && !playwithoutmoving && gpsposition && !action.location) {
                params.currentposition = {type: 'Point', coordinates: gpsposition};
            }
            try {
                const response = await Promise.resolve(ajax.call([{
                    methodname: 'mod_treasurehunt_user_progress', args: {userprogress: params},
                }])[0]);
                applyResponse(response, action);
                loading.hidden = true;
            } catch (error) {
                toast(labels.webserviceerror + (error?.message ? ' : ' + error.message : ''), true);
                loading.hidden = true;
                retry.hidden = true;
            } finally {
                pollTimer = setTimeout(() => {
                    request();
                }, Math.max(5000, Number(config.pollinterval) || 10000));
            }
            return true;
        });
        return requestQueue;
    };

    /** Start device location tracking in GPS mode. */
    const startGps = () => {
        if (gpswatch !== null || !navigator.geolocation) {
            return;
        }
        gpswatch = navigator.geolocation.watchPosition((result) => {
            gpsposition = [result.coords.longitude, result.coords.latitude];
            if (!playwithoutmoving) {
                position = gpsposition;
                updatePosition();
            }
            updateGps();
        }, (error) => toast(labels.geolocationproblem + ': ' + error.message, true), {
            enableHighAccuracy: true, maximumAge: 5000, timeout: 20000,
        });
    };

    /** Register game buttons, dialogs, and map interactions. */
    const bindInterface = () => {
        setupLocationSearch(node('search'), node('search-input'), node('search-results'),
            enabled(playerconfig.localizationbuttondisabled), (longitude, latitude) => {
                map.flyTo({center: [longitude, latitude], zoom: 15, pitch: 55, duration: 1600});
            }, labels);
        node('attempt-close').addEventListener('click', () => {
            selectedAttempt = null;
            node('attempt-popup').hidden = true;
            root.classList.remove('has-attempt-popup');
        });
        node('clue').addEventListener('click', () => openDialog('clue'));
        node('question-open').addEventListener('click', () => {
            closeDialog(node('clue-dialog'));
            openDialog('question');
        });
        node('history').addEventListener('click', () => {
            const content = node('history-content');
            content.replaceChildren();
            if (!history.length) {
                content.textContent = labels.noattempts;
            }
            history.forEach((item) => {
                const entry = document.createElement('p');
                entry.innerHTML = item.string;
                content.appendChild(entry);
            });
            openDialog('history');
        });
        node('submit').addEventListener('click', () => {
            if (stage?.question) {
                openDialog('question');
            } else if (stage?.activitysolved === false) {
                toast(labels.activitywarning);
            } else if (position && available && !roadfinished) {
                request({location: position.slice()});
            }
        });
        node('answer-form').addEventListener('submit', (event) => {
            event.preventDefault();
            const answer = node('answer-form').querySelector('input:checked');
            if (!answer) {
                toast(labels.noanswerselected);
                return;
            }
            closeDialog(node('question-dialog'));
            request({selectedanswerid: Number(answer.value)});
        });
        node('qr').addEventListener('click', () => {
            openDialog('qr');
            document.getElementById('previewQR').style.display = '';
            webqr.loadQR((value) => {
                closeDialog(node('qr-dialog'));
                request({qrtext: value});
            }, (report) => {
                node('camera-status').textContent = typeof report === 'string' ? report :
                    (report?.messagetext || report?.message || report?.cameras?.[report.cameraIndex]?.name || '');
                node('next-camera').hidden = !report?.cameras || report.cameras.length < 2;
            });
        });
        node('next-camera').addEventListener('click', () => webqr.setnextwebcam(() => undefined));
        node('fit').addEventListener('click', fitMap);
        node('locate').addEventListener('click', () => {
            if (gpsposition) {
                map.flyTo({center: gpsposition, zoom: 16, duration: 1000});
            } else {
                startGps();
                toast(labels.wait);
            }
        });
        node('viewmode').addEventListener('click', () => map.easeTo({pitch: map.getPitch() > 10 ? 0 : 60,
            duration: 700}));
        root.querySelectorAll('dialog').forEach((dialog) => {
            dialog.querySelector('.cessium-close').addEventListener('click', () => closeDialog(dialog));
            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) {
                    closeDialog(dialog);
                }
            });
            dialog.addEventListener('cancel', (event) => {
                if (dialog.id === 'cessium-qr-dialog') {
                    event.preventDefault();
                    closeDialog(dialog);
                }
            });
        });
        map.on('click', (event) => {
            if (touchGuard?.shouldIgnoreClick()) {
                return;
            }
            const attempts = map.queryRenderedFeatures(event.point, {layers: ['attempts-points']});
            if (attempts.length) {
                showAttempt(attempts[0]);
                return;
            }
            selectedAttempt = null;
            node('attempt-popup').hidden = true;
            root.classList.remove('has-attempt-popup');
            if (playwithoutmoving && available && !roadfinished) {
                position = [event.lngLat.lng, event.lngLat.lat];
                updatePosition();
                toast(labels.selectedposition);
            }
        });
        map.on('mousemove', (event) => {
            let cursor = playwithoutmoving ? 'crosshair' : 'grab';
            if (map.queryRenderedFeatures(event.point, {layers: ['attempts-points']}).length) {
                cursor = 'pointer';
            }
            map.getCanvas().style.cursor = cursor;
        });
        map.on('move', positionAttempt);
        map.on('moveend', updateNavigation);
        window.addEventListener('pagehide', () => {
            clearTimeout(pollTimer);
            if (gpswatch !== null) {
                navigator.geolocation.clearWatch(gpswatch);
            }
            map.remove();
        }, {once: true});
    };

    /** Create the Mapbox map and start the game service. */
    const start = async() => {
        loading.hidden = false;
        retry.hidden = true;
        try {
            mapboxgl = await loadMapbox();
            if (!map) {
                map = new mapboxgl.Map({container: 'cessium-globe', accessToken: config.mapboxaccesstoken,
                    style: mapStyle(currentStyle, config.custommapping), center: [0, 25], zoom: 2,
                    pitch: 55, antialias: true, maxZoom: 19, performanceMetricsCollection: false,
                    collectResourceTiming: false});
                touchGuard = setupTouchGestureGuard(map.getCanvas());
                initialMapReady = new Promise((resolve) => {
                    map.once('load', () => {
                        map.resize();
                        resolve();
                    });
                    // A failed tile request should not prevent the game service and OSM fallback from starting.
                    window.setTimeout(resolve, 10000);
                });
                map.on('style.load', installLayers);
                map.on('zoomend', updatePegmanScale);
                map.addControl(new mapboxgl.NavigationControl(), 'bottom-left');
                const picker = document.createElement('select');
                picker.className = 'mapbox-layer-select';
                picker.setAttribute('aria-label', labels.layercustom);
                if (config.mapboxaccesstoken) {
                    picker.add(new Option('Mapbox satellite', 'satellite'));
                    picker.add(new Option('Mapbox streets', 'streets'));
                }
                picker.add(new Option('OpenStreetMap', 'osm'));
                if (customSource(config.custommapping)) {
                    picker.add(new Option(labels.layercustom, 'custom'));
                }
                picker.value = currentStyle;
                picker.addEventListener('change', () => {
                    currentStyle = picker.value;
                    map.setStyle(mapStyle(currentStyle, config.custommapping));
                });
                node('layer-picker').appendChild(picker);
                bindInterface();
                if (!playwithoutmoving) {
                    startGps();
                }
            }
            await initialMapReady;
            // The embedded Moodle layout can settle after Mapbox creates its canvas.
            // Resize once the full-screen player has reached its final viewport dimensions.
            window.requestAnimationFrame(() => map?.resize());
            await request({initialize: true});
        } catch (error) {
            loading.hidden = true;
            retry.hidden = true;
            toast(error.message || labels.webserviceerror, true);
            node('sync-status').textContent = error.message || labels.webserviceerror;
        }
    };

    await start();
};
