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
 * Experimental Cesium player for geographic treasure hunts.
 *
 * @module mod_treasurehunt/cessium_player
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import ajax from 'core/ajax';
import webqr from 'mod_treasurehunt/webqr';

const CESIUM_BASE_URL = 'https://cesium.com/downloads/cesiumjs/releases/1.145/Build/Cesium/';
const SATELLITE_URL = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer';
let cesiumPromise;

/**
 * Interpret Moodle boolean settings, including their string form in stored JSON.
 *
 * @param {boolean|number|string} value Setting value.
 * @returns {boolean} Whether the setting is enabled.
 */
const isEnabled = (value) => value === true || value === 1 || value === '1' || value === 'true';

/**
 * Load the pinned Cesium build and its widget stylesheet once.
 *
 * @returns {Promise<Object>} Cesium global.
 */
const loadCesium = () => {
    if (window.Cesium) {
        return Promise.resolve(window.Cesium);
    }
    if (cesiumPromise) {
        return cesiumPromise;
    }
    window.CESIUM_BASE_URL = CESIUM_BASE_URL;
    if (!document.getElementById('cessium-widgets-css')) {
        const css = document.createElement('link');
        css.id = 'cessium-widgets-css';
        css.rel = 'stylesheet';
        css.href = CESIUM_BASE_URL + 'Widgets/widgets.css';
        document.head.appendChild(css);
    }
    cesiumPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = CESIUM_BASE_URL + 'Cesium.js';
        // Cesium's bundled protobuf.js calls define() when Moodle's RequireJS is present.
        // It is an internal module, so do not register it as an anonymous Moodle module.
        const originalDefine = window.define;
        let guardedDefine;
        if (typeof originalDefine === 'function') {
            guardedDefine = (...args) => {
                if (document.currentScript !== script) {
                    return originalDefine(...args);
                }
                return undefined;
            };
            guardedDefine.amd = originalDefine.amd;
            window.define = guardedDefine;
        }
        const restoreDefine = () => {
            if (guardedDefine && window.define === guardedDefine) {
                window.define = originalDefine;
            }
        };
        script.onload = () => {
            restoreDefine();
            if (window.Cesium) {
                resolve(window.Cesium);
            } else {
                reject(new Error('Cesium.js unavailable'));
            }
        };
        script.onerror = () => {
            restoreDefine();
            script.remove();
            reject(new Error('Cesium.js unavailable'));
        };
        document.head.appendChild(script);
    }).catch((error) => {
        cesiumPromise = null;
        throw error;
    });
    return cesiumPromise;
};

/**
 * Create an imagery layer without requiring a Cesium ion token.
 *
 * @param {Object} Cesium Cesium namespace.
 * @param {Object} mapping Activity map configuration.
 * @returns {Promise<Object>} Cesium imagery layer.
 */
const createBaseLayer = async(Cesium, mapping) => {
    let provider;
    if (mapping && mapping.custombackgroundurl && Array.isArray(mapping.bbox)) {
        provider = await Cesium.SingleTileImageryProvider.fromUrl(mapping.custombackgroundurl, {
            rectangle: Cesium.Rectangle.fromDegrees(...mapping.bbox),
        });
    } else if (mapping && mapping.wmsurl && mapping.layerservicetype === 'tiled') {
        // OpenLayers accepts {-y} for TMS tiles; Cesium calls the same coordinate {reverseY}.
        const url = mapping.wmsurl.replace(/\{-y\}/g, '{reverseY}');
        provider = new Cesium.UrlTemplateImageryProvider({url, maximumLevel: 19});
    } else if (mapping && mapping.wmsurl && mapping.layerservicetype === 'wms') {
        provider = new Cesium.WebMapServiceImageryProvider({
            url: mapping.wmsurl,
            layers: mapping.wmsparams?.LAYERS || mapping.wmsparams?.layers || mapping.layername,
            parameters: mapping.wmsparams || {},
        });
    } else if (mapping && mapping.wmsurl && mapping.layerservicetype === 'arcgis') {
        provider = await Cesium.ArcGisMapServerImageryProvider.fromUrl(mapping.wmsurl);
    } else {
        provider = new Cesium.OpenStreetMapImageryProvider({
            url: 'https://tile.openstreetmap.org/',
            maximumLevel: 19,
            credit: '© OpenStreetMap contributors',
        });
    }
    return new Cesium.ImageryLayer(provider);
};

/**
 * Create the photographic globe layer; Cesium's sun lighting supplies its day/night shading.
 *
 * @param {Object} Cesium Cesium namespace.
 * @returns {Promise<Object>} Satellite imagery layer.
 */
const createSatelliteLayer = async(Cesium) => new Cesium.ImageryLayer(
    await Cesium.ArcGisMapServerImageryProvider.fromUrl(SATELLITE_URL)
);

/**
 * Get all longitude/latitude pairs from a GeoJSON geometry.
 *
 * @param {Array} coordinates Nested coordinates.
 * @returns {Array<Array<number>>} Geographic pairs.
 */
const coordinatePairs = (coordinates) => {
    if (!Array.isArray(coordinates)) {
        return [];
    }
    if (typeof coordinates[0] === 'number') {
        return [coordinates];
    }
    return coordinates.flatMap(coordinatePairs);
};

/**
 * Find a useful map target from the geometry of the next stage.
 *
 * @param {Object} collection GeoJSON FeatureCollection.
 * @returns {Array<number>|null} Longitude and latitude.
 */
const geometryCentre = (collection) => {
    const pairs = (collection?.features || []).flatMap((feature) => coordinatePairs(feature.geometry?.coordinates));
    if (!pairs.length) {
        return null;
    }
    const bounds = pairs.reduce((result, pair) => [
        Math.min(result[0], pair[0]), Math.min(result[1], pair[1]),
        Math.max(result[2], pair[0]), Math.max(result[3], pair[1]),
    ], [180, 90, -180, -90]);
    return [(bounds[0] + bounds[2]) / 2, (bounds[1] + bounds[3]) / 2];
};

/**
 * Return polygon coordinate groups from a GeoJSON geometry.
 *
 * @param {Object} geometry GeoJSON geometry.
 * @returns {Array} Polygon coordinate groups.
 */
const geometryPolygons = (geometry) => {
    if (geometry?.type === 'MultiPolygon') {
        return geometry.coordinates;
    }
    if (geometry?.type === 'Polygon') {
        return [geometry.coordinates];
    }
    return [];
};

/**
 * Find the nearest point on the visible stage boundary for a position hint.
 *
 * @param {Array<number>} point Player longitude and latitude.
 * @param {Object} collection Visible or hinted GeoJSON features.
 * @returns {Array<number>|null} Nearest longitude and latitude.
 */
const closestStagePoint = (point, collection) => {
    let closest = null;
    let shortest = Infinity;
    const longitudeScale = Math.cos(point[1] * Math.PI / 180);
    (collection?.features || []).forEach((feature) => {
        const geometry = feature.geometry;
        if (inGeometry(point, geometry)) {
            closest = point;
            shortest = 0;
            return;
        }
        const polygons = geometryPolygons(geometry);
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
                const distance = ((point[0] - candidate[0]) * longitudeScale) ** 2 + (point[1] - candidate[1]) ** 2;
                if (distance < shortest) {
                    shortest = distance;
                    closest = candidate;
                }
            }
        }));
    });
    return closest || geometryCentre(collection);
};

/**
 * Compute a camera rectangle with breathing space around all stage geometries.
 *
 * @param {Object} collection GeoJSON features.
 * @returns {Array<number>|null} West, south, east and north in degrees.
 */
const geometryBounds = (collection) => {
    const pairs = (collection?.features || []).flatMap((feature) => coordinatePairs(feature.geometry?.coordinates));
    if (!pairs.length) {
        return null;
    }
    const bounds = pairs.reduce((result, pair) => [
        Math.min(result[0], pair[0]), Math.min(result[1], pair[1]),
        Math.max(result[2], pair[0]), Math.max(result[3], pair[1]),
    ], [180, 90, -180, -90]);
    const margin = Math.max(bounds[2] - bounds[0], bounds[3] - bounds[1], 0.002) * 0.18;
    return [bounds[0] - margin, Math.max(-89, bounds[1] - margin),
        bounds[2] + margin, Math.min(89, bounds[3] + margin)];
};

/**
 * Test a point against a GeoJSON ring using ray casting.
 *
 * @param {Array<number>} point Geographic pair.
 * @param {Array<Array<number>>} ring Polygon ring.
 * @returns {boolean} Whether the point is in the ring.
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
 * Test a geographic point against polygons, including holes.
 *
 * @param {Array<number>} point Geographic pair.
 * @param {Object} geometry GeoJSON geometry.
 * @returns {boolean} Whether the point is inside.
 */
const inGeometry = (point, geometry) => {
    if (!geometry || !['Polygon', 'MultiPolygon'].includes(geometry.type)) {
        return false;
    }
    const polygons = geometry.type === 'Polygon' ? [geometry.coordinates] : geometry.coordinates;
    return polygons.some((rings) => rings.length && inRing(point, rings[0]) &&
        !rings.slice(1).some((hole) => inRing(point, hole)));
};

/**
 * Initialise the Cesium view and the existing treasure hunt game service.
 *
 * @returns {Promise<void>} Initialisation completion.
 */
export const init = async() => {
    const root = document.getElementById('cessium-player');
    if (!root) {
        return;
    }
    const config = JSON.parse(document.getElementById('cessium-config').textContent);
    const element = (id) => document.getElementById('cessium-' + id);
    const labels = config.labels;
    const playerconfig = config.playerconfig || {};
    const loading = element('loading');
    const retry = element('retry');
    let viewer;
    let Cesium;
    const mapLayers = {};
    let stageSource;
    let visibleStageBounds;
    let nextstage = null;
    let stageSignature = '';
    let attempts = [];
    let marker;
    let gpsmarker;
    let selectedAttempt;
    let position = null;
    let gpsposition = null;
    let geowatch = null;
    let stage = null;
    let stageposition = 1;
    let roadfinished = false;
    let available = true;
    let qrexpected = false;
    let qoaremoved = false;
    let playwithoutmoving = isEnabled(config.playwithoutmoving) || Boolean(config.previewroadid);
    let groupmode = config.groupmode;
    let attempttimestamp = config.attempttimestamp;
    let roadtimestamp = config.roadtimestamp;
    let history = [];
    let pollTimer;
    let toastTimer;
    let requestQueue = Promise.resolve();
    let activeMapLayer = 'osm';
    let layerRequest = 0;

    /**
     * Display an accessible status message without blocking the map.
     *
     * @param {string} message Message text.
     * @param {boolean} error Whether this is an error.
     */
    const toast = (message, error = false) => {
        if (!message) {
            return;
        }
        const target = element('toast');
        target.textContent = message;
        target.classList.toggle('is-error', error);
        target.hidden = false;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => {
            target.hidden = true;
        }, 5500);
    };

    /**
     * Change the visible map without recreating the globe or its game markers.
     *
     * @param {string} layerId OSM, satellite or custom layer identifier.
     */
    const selectMapLayer = async(layerId) => {
        const requestId = ++layerRequest;
        try {
            if (layerId === 'satellite' && !mapLayers.satellite) {
                const layer = await createSatelliteLayer(Cesium);
                layer.show = false;
                viewer.imageryLayers.add(layer);
                mapLayers.satellite = layer;
            }
            if (requestId !== layerRequest) {
                return;
            }
            if (mapLayers.satellite) {
                mapLayers.satellite.show = layerId === 'satellite';
            }
            if (mapLayers.custom) {
                mapLayers.custom.show = layerId === 'custom';
            }
            viewer.scene.globe.enableLighting = layerId === 'satellite';
            viewer.clock.shouldAnimate = layerId === 'satellite';
            if (layerId === 'satellite') {
                viewer.clock.currentTime = Cesium.JulianDate.now();
            }
            viewer.scene.requestRender();
            activeMapLayer = layerId;
        } catch (error) {
            if (requestId === layerRequest) {
                element('layer-select').value = activeMapLayer;
                toast(labels.layererror + ': ' + error.message, true);
            }
        }
    };

    /**
     * Hide the loading layer before presenting a dialog.
     *
     * @param {string} id Dialog identifier suffix.
     */
    const openDialog = (id) => {
        loading.hidden = true;
        const dialog = element(id + '-dialog');
        if (!dialog.open) {
            dialog.showModal();
        }
    };

    /**
     * Close a dialog and stop its camera if it is the QR dialog.
     *
     * @param {HTMLDialogElement} dialog Dialog to close.
     */
    const closeDialog = (dialog) => {
        if (dialog.id === 'cessium-qr-dialog') {
            webqr.unloadQR(() => undefined);
        }
        dialog.close();
    };

    /** Update the coordinate marker on the globe. */
    const updateMarker = () => {
        if (!viewer || !position) {
            return;
        }
        if (!marker) {
            marker = viewer.entities.add({
                name: labels.selectedposition,
                billboard: {
                    image: './pix/bootstrap/my_location_3.png',
                    verticalOrigin: Cesium.VerticalOrigin.BOTTOM,
                    heightReference: Cesium.HeightReference.CLAMP_TO_GROUND,
                    disableDepthTestDistance: Number.POSITIVE_INFINITY,
                },
                label: {
                    show: false,
                    text: '',
                    font: 'bold 15px sans-serif',
                    fillColor: Cesium.Color.WHITE,
                    showBackground: true,
                    backgroundColor: Cesium.Color.fromCssColorString('#071d2f').withAlpha(0.9),
                    backgroundPadding: new Cesium.Cartesian2(10, 7),
                    verticalOrigin: Cesium.VerticalOrigin.TOP,
                    pixelOffset: new Cesium.Cartesian2(0, 11),
                    heightReference: Cesium.HeightReference.CLAMP_TO_GROUND,
                    disableDepthTestDistance: Number.POSITIVE_INFINITY,
                },
            });
        }
        marker.position = Cesium.Cartesian3.fromDegrees(position[0], position[1]);
        element('submit').disabled = roadfinished || !available || !position ||
            Boolean(stage?.question) || stage?.activitysolved === false;
        updateNavigation();
        viewer.scene.requestRender();
    };

    /** Update optional navigation hints from the current position. */
    const updateNavigation = () => {
        const panel = element('navigation');
        const target = position && closestStagePoint(position, nextstage);
        if (!position || !target || !(
            playerconfig.showdistancehint || playerconfig.showheadinghint || playerconfig.showinzonehint)) {
            panel.hidden = true;
            if (marker) {
                marker.label.show = false;
            }
            return;
        }
        panel.hidden = false;
        const first = Cesium.Cartographic.fromDegrees(position[0], position[1]);
        const second = Cesium.Cartographic.fromDegrees(target[0], target[1]);
        const line = new Cesium.EllipsoidGeodesic(first, second);
        const distance = line.surfaceDistance;
        const inside = playerconfig.showinzonehint && nextstage.features.some((feature) =>
            inGeometry(position, feature.geometry));
        let distanceText = '—';
        if (inside) {
            distanceText = labels.insidezone;
        } else if (playerconfig.showdistancehint) {
            distanceText = distance >= 1000 ? (distance / 1000).toFixed(1) + ' km' : Math.round(distance) + ' m';
        }
        element('distance').textContent = distanceText;
        element('position-status').textContent = playwithoutmoving ? labels.selectedposition : labels.gpsposition;
        const dx = (target[0] - position[0]) * Math.cos(first.latitude);
        const dy = target[1] - position[1];
        const bearing = Math.atan2(dx, dy) * 180 / Math.PI;
        element('bearing-icon').style.transform = playerconfig.showheadinghint ? 'rotate(' + bearing + 'deg)' : '';
        if (marker) {
            const arrows = ['↑', '↗', '→', '↘', '↓', '↙', '←', '↖'];
            const arrow = arrows[Math.round(((bearing + 360) % 360) / 45) % arrows.length];
            const hint = inside ? labels.insidezone : [
                playerconfig.showheadinghint ? arrow : '',
                playerconfig.showdistancehint ? distanceText : '',
            ].filter(Boolean).join(' ');
            marker.label.text = hint;
            marker.label.show = Boolean(hint);
            viewer.scene.requestRender();
        }
    };

    /** Fly to the visible stage, a recent attempt or the player's marker. */
    const fitMap = () => {
        if (visibleStageBounds) {
            viewer.camera.flyTo({destination: Cesium.Rectangle.fromDegrees(...visibleStageBounds), duration: 1.2});
        } else if (attempts.length) {
            viewer.flyTo(attempts[attempts.length - 1], {duration: 1.2});
        } else if (marker || gpsmarker) {
            viewer.flyTo(marker || gpsmarker, {duration: 1.2});
        } else {
            viewer.camera.flyHome(1.2);
        }
    };

    /**
     * Replace the visible target area only when this activity permits it.
     *
     * @param {Object|null} collection GeoJSON features.
     * @param {boolean} shouldFit Whether to focus the map on this area.
     * @returns {Promise<void>} Completion of geometry loading.
     */
    const renderStage = async(collection, shouldFit) => {
        if (stageSource) {
            viewer.dataSources.remove(stageSource, true);
            stageSource = null;
        }
        visibleStageBounds = null;
        nextstage = collection?.features?.length ? collection : null;
        stageposition = Number(nextstage?.features?.[0]?.properties?.stageposition || stageposition);
        element('stage-number').textContent = String(stageposition);
        const visibleFeatures = (nextstage?.features || []).filter((feature) =>
            Number(feature.properties?.stageposition) === 1 || playerconfig.shownextareahint);
        if (visibleFeatures.length) {
            stageSource = new Cesium.CustomDataSource('Treasurehunt stage areas');
            const outline = Cesium.Color.fromCssColorString('#5ce8f6');
            visibleFeatures.forEach((feature) => {
                const polygons = geometryPolygons(feature.geometry);
                polygons.forEach((rings) => {
                    if (!rings.length || !rings[0].length) {
                        return;
                    }
                    const positions = (ring) => Cesium.Cartesian3.fromDegreesArray(ring.flatMap(
                        (pair) => [pair[0], pair[1]]));
                    const hierarchy = new Cesium.PolygonHierarchy(positions(rings[0]), rings.slice(1).map(
                        (hole) => new Cesium.PolygonHierarchy(positions(hole))));
                    stageSource.entities.add({
                        polygon: {
                            hierarchy,
                            height: 8,
                            material: outline.withAlpha(0.26),
                            outline: true,
                            outlineColor: outline,
                        },
                    });
                    rings.forEach((ring) => stageSource.entities.add({
                        polyline: {
                            positions: positions(ring),
                            width: 4,
                            clampToGround: true,
                            material: outline,
                        },
                    }));
                });
                const centre = geometryCentre({features: [feature]});
                if (centre) {
                    stageSource.entities.add({
                        position: Cesium.Cartesian3.fromDegrees(...centre),
                        label: {
                            text: Number(feature.properties?.stageposition) === 1 ? labels.startfromhere : labels.stage + ' ' +
                                (feature.properties?.stageposition || stageposition),
                            font: 'bold 17px sans-serif',
                            fillColor: Cesium.Color.WHITE,
                            outlineColor: Cesium.Color.fromCssColorString('#07384b'),
                            outlineWidth: 4,
                            style: Cesium.LabelStyle.FILL_AND_OUTLINE,
                            heightReference: Cesium.HeightReference.CLAMP_TO_GROUND,
                            disableDepthTestDistance: Number.POSITIVE_INFINITY,
                        },
                    });
                }
            });
            visibleStageBounds = geometryBounds({features: visibleFeatures});
            await viewer.dataSources.add(stageSource);
            viewer.scene.requestRender();
            if (shouldFit) {
                fitMap();
            }
        }
        updateNavigation();
    };

    /**
     * Render the user's successful and failed location attempts.
     *
     * @param {Object} collection GeoJSON attempts.
     */
    const renderAttempts = (collection) => {
        selectedAttempt = null;
        element('attempt-popup').hidden = true;
        attempts.forEach((entity) => viewer.entities.remove(entity));
        attempts = [];
        (collection?.features || []).forEach((feature) => {
            const coordinates = feature.geometry?.coordinates;
            if (!Array.isArray(coordinates) || coordinates.length < 2) {
                return;
            }
            const solved = isEnabled(feature.properties?.geometrysolved);
            const entity = viewer.entities.add({
                name: feature.properties?.name || labels.stage,
                position: Cesium.Cartesian3.fromDegrees(coordinates[0], coordinates[1]),
                billboard: {
                    image: solved ? './pix/success_mark.png' : './pix/failure_mark.png',
                    scale: 0.5,
                    verticalOrigin: Cesium.VerticalOrigin.BOTTOM,
                    heightReference: Cesium.HeightReference.CLAMP_TO_GROUND,
                    disableDepthTestDistance: Number.POSITIVE_INFINITY,
                },
                label: {
                    text: String(feature.properties?.stageposition || ''),
                    font: 'bold 14px sans-serif',
                    fillColor: Cesium.Color.WHITE,
                    style: Cesium.LabelStyle.FILL_AND_OUTLINE,
                    outlineColor: Cesium.Color.BLACK,
                    outlineWidth: 3,
                    pixelOffset: new Cesium.Cartesian2(0, -37),
                    disableDepthTestDistance: Number.POSITIVE_INFINITY,
                },
            });
            entity.treasurehuntAttempt = feature.properties;
            attempts.push(entity);
        });
        viewer.scene.requestRender();
    };

    /** Keep the attempt popup attached to its marker while the globe moves. */
    const positionAttemptPopup = () => {
        if (!selectedAttempt) {
            return;
        }
        const popup = element('attempt-popup');
        const world = selectedAttempt.position.getValue(viewer.clock.currentTime);
        const screen = Cesium.SceneTransforms.worldToWindowCoordinates(viewer.scene, world);
        if (!screen) {
            popup.hidden = true;
            return;
        }
        const canvas = viewer.scene.canvas.getBoundingClientRect();
        const viewport = root.getBoundingClientRect();
        const x = screen.x + canvas.left - viewport.left;
        const y = screen.y + canvas.top - viewport.top;
        popup.hidden = x < 0 || x > viewport.width || y < 0 || y > viewport.height;
        popup.classList.toggle('is-below', y < 250);
        popup.style.left = Math.max(170, Math.min(viewport.width - 170, x)) + 'px';
        popup.style.top = y + 'px';
    };

    /**
     * Show Moodle-formatted attempt details next to its map marker.
     *
     * @param {Object} entity Selected Cesium attempt entity.
     */
    const showAttemptPopup = (entity) => {
        const properties = entity.treasurehuntAttempt;
        const solved = isEnabled(properties.geometrysolved);
        let title = labels.failedlocation;
        if (solved) {
            title = properties.name && properties.clue ? labels.stageovercome : labels.discoveredlocation;
        }
        element('attempt-title').textContent = title;
        const content = element('attempt-content');
        content.replaceChildren();
        if (properties.info) {
            const info = document.createElement('div');
            info.innerHTML = properties.info;
            content.appendChild(info);
        }
        if (solved) {
            [[labels.stagename, properties.name], [labels.stageclue, properties.clue]].forEach(([label, value]) => {
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
        }
        selectedAttempt = entity;
        positionAttemptPopup();
    };

    /** Update clue and question dialogs from a resolved stage. */
    const renderClue = () => {
        const content = element('clue-content');
        content.innerHTML = stage?.clue || '';
        if (!content.textContent.trim() && !content.querySelector('img, video, iframe')) {
            content.textContent = labels.nohint;
        }
        const questionOpen = element('question-open');
        questionOpen.hidden = !stage?.question;
        const question = element('question-content');
        question.innerHTML = stage?.question || '';
        const answers = element('answers');
        answers.replaceChildren();
        (stage?.answers || []).forEach((answer) => {
            const choice = document.createElement('label');
            choice.className = 'cessium-answer';
            const input = document.createElement('input');
            input.type = 'radio';
            input.name = 'cessium-answer';
            input.value = String(answer.id);
            const body = document.createElement('span');
            body.innerHTML = answer.answertext;
            choice.append(input, body);
            answers.appendChild(choice);
        });
        element('objective-text').textContent = stage?.name || labels.nohint;
    };

    /** Show the current progress and the possible actions. */
    const updateActions = () => {
        const completed = roadfinished ? config.totalstages : Number(stage?.position || 0);
        const total = Math.max(Number(stage?.totalnumber || config.totalstages || 0), completed);
        element('progress-label').textContent = completed + ' / ' + (total || '—');
        const percent = total ? Math.round(100 * completed / total) : 0;
        element('progress-bar').style.width = percent + '%';
        element('progress-track').setAttribute('aria-valuenow', String(percent));
        element('finished').hidden = !roadfinished;
        element('submit').disabled = roadfinished || !available || !position ||
            Boolean(stage?.question) || stage?.activitysolved === false;
        element('qr').hidden = !qrexpected || roadfinished || !available ||
            Boolean(stage?.question) || stage?.activitysolved === false;
        if (roadfinished) {
            element('sync-status').textContent = labels.completed;
        } else {
            element('sync-status').textContent = available ? labels.wait : labels.timeexceeded;
        }
    };

    /**
     * Apply the changing play mode and clue settings.
     *
     * @param {Object} response Service result.
     */
    const updateMode = (response) => {
        attempttimestamp = response.attempttimestamp;
        roadtimestamp = response.roadtimestamp;
        roadfinished = isEnabled(response.roadfinished);
        available = isEnabled(response.available);
        qrexpected = isEnabled(response.qrexpected);
        qoaremoved = isEnabled(response.qoaremoved);
        groupmode = isEnabled(response.groupmode);
        playerconfig.showdistancehint = isEnabled(response.playerconfig?.showdistancehint ?? playerconfig.showdistancehint);
        playerconfig.showheadinghint = isEnabled(response.playerconfig?.showheadinghint ?? playerconfig.showheadinghint);
        playerconfig.showinzonehint = isEnabled(response.playerconfig?.showinzonehint ?? playerconfig.showinzonehint);
        playerconfig.shownextareahint = isEnabled(response.playerconfig?.shownextareahint ?? playerconfig.shownextareahint);
        if (playwithoutmoving !== isEnabled(response.playwithoutmoving)) {
            playwithoutmoving = isEnabled(response.playwithoutmoving);
            if (!playwithoutmoving) {
                startGps();
            } else if (geowatch !== null) {
                navigator.geolocation.clearWatch(geowatch);
                geowatch = null;
            }
        }
    };

    /**
     * Replace stage geometry only when it changes.
     *
     * @param {Object} response Service result.
     * @param {Object} action Request that produced this result.
     * @param {number|undefined} previousStage Previously resolved stage ID.
     * @returns {Promise<void>} Geometry update completion.
     */
    const updateGeometry = async(response, action, previousStage) => {
        if (response.nextstage) {
            const signature = JSON.stringify([response.nextstage, playerconfig.shownextareahint]);
            if (action.initialize || signature !== stageSignature) {
                stageSignature = signature;
                await renderStage(response.nextstage, action.initialize || previousStage !== stage?.id);
            }
        } else if (previousStage !== stage?.id || roadfinished) {
            stageSignature = '';
            await renderStage(null, false);
            stageposition = Math.min(Number(stage?.position || 0) + 1, config.totalstages || Infinity);
            element('stage-number').textContent = roadfinished ? '✓' : String(stageposition);
        }
    };

    /**
     * Report action results and asynchronous game updates.
     *
     * @param {Object} response Service result.
     * @param {Object} action Request that produced this result.
     */
    const showMessages = (response, action) => {
        if (action.location || action.qrtext || action.selectedanswerid) {
            toast(response.status?.msg, Number(response.status?.code) !== 0);
        }
        if (response.infomsg?.length) {
            const message = response.infomsg.map((item) => {
                const node = document.createElement('div');
                node.innerHTML = item;
                return node.textContent.trim();
            }).filter(Boolean).join(' · ');
            toast(message);
        }
    };

    /**
     * Reflect a service response without revealing hidden stage geometry.
     *
     * @param {Object} response Service result.
     * @param {Object} action Request that produced this result.
     * @returns {Promise<void>} UI update completion.
     */
    const applyResponse = async(response, action) => {
        const previousStage = stage?.id;
        updateMode(response);
        if (response.attempts) {
            renderAttempts(response.attempts);
        }
        if (response.lastsuccessfulstage) {
            stage = response.lastsuccessfulstage;
            renderClue();
        } else if (action.initialize) {
            stage = null;
            renderClue();
        }
        await updateGeometry(response, action, previousStage);
        if (Array.isArray(response.attempthistory) && (response.attempthistory.length || action.initialize)) {
            history = response.attempthistory;
        }
        updateActions();
        updateNavigation();
        showMessages(response, action);
        if (stage && previousStage !== stage.id) {
            openDialog('clue');
        }
    };

    /**
     * Queue service calls so a poll cannot overwrite an action response.
     *
     * @param {Object} action Location, answer, QR or initialization request.
     * @returns {Promise<boolean>} Service call completion.
     */
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
                attempttimestamp,
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
                const result = await Promise.resolve(ajax.call([{
                    methodname: 'mod_treasurehunt_user_progress',
                    args: {userprogress: params},
                }])[0]);
                await applyResponse(result, action);
                loading.hidden = true;
            } catch (error) {
                const detail = error?.message || error?.error || '';
                toast(labels.webserviceerror + (detail ? ' : ' + detail : ''), true);
                if (action.initialize && !viewer?.dataSources?.length) {
                    loading.hidden = false;
                    retry.hidden = false;
                } else {
                    loading.hidden = true;
                }
            } finally {
                pollTimer = setTimeout(() => request(), Math.max(5000, Number(config.pollinterval) || 10000));
            }
            return true;
        });
        return requestQueue;
    };

    /** Acquire continuous device position in moving mode. */
    const startGps = () => {
        if (geowatch !== null || !navigator.geolocation) {
            return;
        }
        geowatch = navigator.geolocation.watchPosition((result) => {
            gpsposition = [result.coords.longitude, result.coords.latitude];
            if (!playwithoutmoving) {
                position = gpsposition;
                updateMarker();
            }
            if (!gpsmarker) {
                gpsmarker = viewer.entities.add({
                    name: labels.gpsposition,
                    point: {
                        pixelSize: 9,
                        color: Cesium.Color.fromCssColorString('#5ce8f6'),
                        outlineColor: Cesium.Color.WHITE,
                        outlineWidth: 2,
                        heightReference: Cesium.HeightReference.CLAMP_TO_GROUND,
                    },
                });
            }
            gpsmarker.position = Cesium.Cartesian3.fromDegrees(...gpsposition);
            viewer.scene.requestRender();
        }, (error) => toast(labels.geolocationproblem + ': ' + error.message, true), {
            enableHighAccuracy: true,
            maximumAge: 5000,
            timeout: 20000,
        });
    };

    /** Attach all player buttons and modal controls. */
    const bindInterface = () => {
        element('layer-select').addEventListener('change', (event) => selectMapLayer(event.target.value));
        element('attempt-close').addEventListener('click', () => {
            selectedAttempt = null;
            element('attempt-popup').hidden = true;
        });
        element('clue').addEventListener('click', () => openDialog('clue'));
        element('question-open').addEventListener('click', () => {
            element('clue-dialog').close();
            openDialog('question');
        });
        element('history').addEventListener('click', () => {
            const content = element('history-content');
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
        element('submit').addEventListener('click', () => {
            if (!available || roadfinished) {
                return;
            }
            if (stage?.question) {
                toast(labels.answerwarning);
                openDialog('question');
            } else if (stage?.activitysolved === false) {
                toast(labels.activitywarning);
            } else if (!position) {
                toast(labels.nomarks);
            } else {
                request({location: position.slice()});
            }
        });
        element('answer-form').addEventListener('submit', (event) => {
            event.preventDefault();
            const choice = element('answer-form').querySelector('input:checked');
            if (!choice) {
                toast(labels.noanswerselected);
                return;
            }
            closeDialog(element('question-dialog'));
            request({selectedanswerid: Number(choice.value)});
        });
        element('qr').addEventListener('click', () => {
            openDialog('qr');
            document.getElementById('previewQR').style.display = '';
            webqr.loadQR((value) => {
                closeDialog(element('qr-dialog'));
                request({qrtext: value});
            }, (report) => {
                element('camera-status').textContent = typeof report === 'string' ? report :
                    (report?.messagetext || report?.message || report?.cameras?.[report.cameraIndex]?.name || '');
                element('next-camera').hidden = !report?.cameras || report.cameras.length < 2;
            });
        });
        element('next-camera').addEventListener('click', () => webqr.setnextwebcam(() => undefined));
        element('fit').addEventListener('click', fitMap);
        element('locate').addEventListener('click', () => {
            if (!gpsposition) {
                startGps();
                toast(labels.wait);
            } else {
                viewer.camera.flyTo({destination: Cesium.Cartesian3.fromDegrees(...gpsposition, 1800), duration: 1.1});
            }
        });
        element('viewmode').addEventListener('click', () => {
            if (viewer.scene.mode === Cesium.SceneMode.SCENE2D) {
                viewer.scene.morphTo3D(1.1);
            } else {
                viewer.scene.morphTo2D(1.1);
            }
        });
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
        retry.addEventListener('click', () => start());
        const handler = new Cesium.ScreenSpaceEventHandler(viewer.scene.canvas);
        const removePopupPositioner = viewer.scene.postRender.addEventListener(positionAttemptPopup);
        handler.setInputAction((event) => {
            const picked = viewer.scene.pick(event.position);
            if (picked?.id?.treasurehuntAttempt) {
                showAttemptPopup(picked.id);
                return;
            }
            selectedAttempt = null;
            element('attempt-popup').hidden = true;
            if (!playwithoutmoving || roadfinished || !available) {
                return;
            }
            const cartesian = viewer.camera.pickEllipsoid(event.position, viewer.scene.globe.ellipsoid);
            if (!cartesian) {
                return;
            }
            const cartographic = Cesium.Cartographic.fromCartesian(cartesian);
            position = [Cesium.Math.toDegrees(cartographic.longitude), Cesium.Math.toDegrees(cartographic.latitude)];
            updateMarker();
            toast(labels.selectedposition);
        }, Cesium.ScreenSpaceEventType.LEFT_CLICK);
        handler.setInputAction((event) => {
            const picked = viewer.scene.pick(event.endPosition);
            viewer.scene.canvas.style.cursor = picked?.id?.treasurehuntAttempt ? 'pointer' :
                (playwithoutmoving ? 'crosshair' : 'grab');
        }, Cesium.ScreenSpaceEventType.MOUSE_MOVE);
        window.addEventListener('pagehide', () => {
            clearTimeout(pollTimer);
            if (geowatch !== null) {
                navigator.geolocation.clearWatch(geowatch);
            }
            handler.destroy();
            removePopupPositioner();
            viewer.destroy();
        }, {once: true});
    };

    /** Create the globe and fetch the first game state. */
    const start = async() => {
        loading.hidden = false;
        retry.hidden = true;
        try {
            if (!viewer) {
                Cesium = await loadCesium();
                const baseLayer = await createBaseLayer(Cesium, null);
                viewer = new Cesium.Viewer('cessium-globe', {
                    baseLayer,
                    terrainProvider: new Cesium.EllipsoidTerrainProvider(),
                    animation: false,
                    timeline: false,
                    baseLayerPicker: false,
                    geocoder: false,
                    homeButton: false,
                    sceneModePicker: false,
                    navigationHelpButton: false,
                    fullscreenButton: false,
                    infoBox: false,
                    selectionIndicator: false,
                    requestRenderMode: true,
                });
                mapLayers.osm = baseLayer;
                viewer.scene.maximumRenderTimeChange = 60;
                if (config.custommapping?.custombackgroundurl || config.custommapping?.wmsurl) {
                    try {
                        mapLayers.custom = await createBaseLayer(Cesium, config.custommapping);
                        viewer.imageryLayers.add(mapLayers.custom);
                        const option = document.createElement('option');
                        option.value = 'custom';
                        option.textContent = labels.layercustom;
                        element('layer-select').appendChild(option);
                        element('layer-select').value = 'custom';
                        activeMapLayer = 'custom';
                    } catch (error) {
                        toast(error.message, true);
                    }
                }
                viewer.resolutionScale = Math.min(window.devicePixelRatio || 1, 1.5);
                viewer.camera.flyTo({destination: Cesium.Cartesian3.fromDegrees(0, 25, 15000000), duration: 0});
                bindInterface();
                if (!playwithoutmoving) {
                    startGps();
                }
            }
            await request({initialize: true});
        } catch (error) {
            toast(labels.webserviceerror + ': ' + error.message, true);
            retry.hidden = false;
        }
    };

    await start();
};
