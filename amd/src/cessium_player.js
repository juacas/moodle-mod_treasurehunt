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
import {setup as setupLocationSearch} from 'mod_treasurehunt/location_search';
import {setup as setupTouchGestureGuard} from 'mod_treasurehunt/touch_gesture_guard';
import {failedLocationAttempt, selectClip} from 'mod_treasurehunt/pegman_animation_state';
import webqr from 'mod_treasurehunt/webqr';

const SATELLITE_URL = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer';
const PEGMAN_HEIGHT = 1.7;
const PEGMAN_REFERENCE_HEIGHT_PX = 100;
const PEGMAN_ZOOM_FACTOR = 2;
const PEGMAN_SCALE_SHAKE_MS = 500;
const PEGMAN_SCALE_SHAKE_HZ = 10;
const PEGMAN_SCALE_BOUNCE_MS = 2000;
const COMPASS_ARROW_LENGTH_PX = 64;
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
 * @param {string} baseUrl URL of the plugin's bundled Cesium distribution.
 * @returns {Promise<Object>} Cesium global.
 */
const loadCesium = (baseUrl) => {
    if (window.Cesium) {
        return Promise.resolve(window.Cesium);
    }
    if (cesiumPromise) {
        return cesiumPromise;
    }
    window.CESIUM_BASE_URL = baseUrl;
    if (!document.getElementById('cessium-widgets-css')) {
        const css = document.createElement('link');
        css.id = 'cessium-widgets-css';
        css.rel = 'stylesheet';
        css.href = baseUrl + 'Widgets/widgets.css';
        document.head.appendChild(css);
    }
    cesiumPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = baseUrl + 'Cesium.js';
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
 * @returns {Promise<Object>} Cesium imagery provider.
 */
const createBaseProvider = async(Cesium, mapping) => {
    let provider;
    if (mapping && mapping.custombackgroundurl && Array.isArray(mapping.bbox)) {
        provider = await Cesium.SingleTileImageryProvider.fromUrl(mapping.custombackgroundurl, {
            rectangle: Cesium.Rectangle.fromDegrees(...mapping.bbox),
        });
    } else if (mapping && mapping.wmsurl && mapping.layerservicetype === 'tiled') {
        // OpenLayers accepts {-y} for TMS tiles; Cesium calls the same coordinate {reverseY}.
        const url = mapping.wmsurl.replace(/\{-y\}/g, '{reverseY}');
        // These custom XYZ layers use the standard EPSG:3857 tile grid. Set the scheme
        // explicitly so Cesium always has a projection when it builds globe tiles.
        provider = new Cesium.UrlTemplateImageryProvider({
            url,
            tilingScheme: new Cesium.WebMercatorTilingScheme(),
            maximumLevel: 19,
            enablePickFeatures: false,
        });
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
    return provider;
};

/**
 * Create the initial OpenStreetMap layer while the base layer picker starts.
 *
 * @param {Object} Cesium Cesium namespace.
 * @returns {Promise<Object>} Cesium imagery layer.
 */
const createBaseLayer = async(Cesium) => new Cesium.ImageryLayer(await createBaseProvider(Cesium, null));

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
    let touchGuard;
    let buildingsTileset;
    let buildingsEnabled = false;
    let worldTerrainPromise;
    let buildingsHeightRevision = 0;
    let buildingsAnchorKey = '';
    let layerPicker;
    let layerSelectionSubscription;
    /** Keep the gallery inside the visible area when the viewport changes. */
    const updateLayerPickerLayout = () => {
        const picker = element('layer-picker');
        if (picker) {
            const available = Math.max(0, Math.floor(picker.getBoundingClientRect().top - 12));
            picker.style.setProperty('--cessium-picker-available-height', available + 'px');
        }
    };
    let stageSource;
    let visibleStageBounds;
    let nextstage = null;
    let stageSignature = '';
    let attempts = [];
    let marker;
    let markerModel;
    let markerModelLoading = false;
    let markerClip = 'Descansando';
    let markerPlayingClip = null;
    let markerBearing = null;
    let markerScaleValue = 1;
    let markerVisible = false;
    let markerDisposed = false;
    let markerScaleCalculated = false;
    let markerScaleReference = null;
    let markerScaleFrame = null;
    let cameraReady = false;
    let fitInProgress = false;
    let flightCompletion = Promise.resolve();
    let gameStateLoaded = false;
    let compassArrow;
    let compassBearing = null;
    let gpsmarker;
    let selectedAttempt;
    let position = null;
    let gpsposition = null;
    let geowatch = null;
    let stage = null;
    let availableClues = [];
    let selectedClueIndex = 0;
    let clueSignature = '';
    let stageposition = 1;
    let totalstages = 0;
    let roadfinished = false;
    let available = true;
    let qrexpected = false;
    let qoaremoved = false;
    let playwithoutmoving = isEnabled(config.playwithoutmoving) || Boolean(config.previewroadid);
    let groupmode = config.groupmode;
    // The initialize response supplies the real markers for all later polls.
    let attemptid = 0;
    let roadtimestamp = 0;
    let history = [];
    let pollTimer;
    let toastTimer;
    let confusedUntil = 0;
    let confusedTimer;
    let requestQueue = Promise.resolve();

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

    /** @returns {Promise<Object>} The same elevation source used by Cesium OSM Buildings. */
    const getWorldTerrain = () => {
        if (!worldTerrainPromise) {
            worldTerrainPromise = Cesium.createWorldTerrainAsync().catch((error) => {
                worldTerrainPromise = null;
                throw error;
            });
        }
        return worldTerrainPromise;
    };

    /**
     * Match OSM building elevation to the selected ground at the current map focus.
     * World terrain uses the original heights; an ellipsoid needs a local vertical offset.
     *
     * @returns {Promise<void>} Completion of the height adjustment.
     */
    const updateBuildingsHeight = async() => {
        const revision = ++buildingsHeightRevision;
        if (!buildingsTileset || !viewer || viewer.isDestroyed()) {
            return;
        }
        if (!buildingsEnabled) {
            buildingsTileset.show = false;
            viewer.scene.requestRender();
            return;
        }
        if (!(viewer.scene.globe.terrainProvider instanceof Cesium.EllipsoidTerrainProvider)) {
            buildingsAnchorKey = '';
            buildingsTileset.modelMatrix = Cesium.Matrix4.clone(Cesium.Matrix4.IDENTITY);
            buildingsTileset.show = true;
            viewer.scene.requestRender();
            return;
        }
        const canvas = viewer.scene.canvas;
        const centre = new Cesium.Cartesian2(canvas.clientWidth / 2, canvas.clientHeight / 2);
        const ray = viewer.camera.getPickRay(centre);
        const focus = ray && viewer.scene.globe.pick(ray, viewer.scene) ||
            viewer.camera.pickEllipsoid(centre, viewer.scene.globe.ellipsoid);
        if (!focus) {
            buildingsTileset.show = false;
            viewer.scene.requestRender();
            return;
        }
        const cartographic = Cesium.Cartographic.fromCartesian(focus);
        const key = cartographic.longitude.toFixed(5) + ':' + cartographic.latitude.toFixed(5);
        if (key === buildingsAnchorKey) {
            buildingsTileset.show = true;
            viewer.scene.requestRender();
            return;
        }
        // Do not display the tiles at their old elevation while the new focus is sampled.
        buildingsTileset.show = false;
        viewer.scene.requestRender();
        try {
            const terrain = await getWorldTerrain();
            const [sample] = await Cesium.sampleTerrainMostDetailed(terrain, [cartographic]);
            if (revision !== buildingsHeightRevision || viewer.isDestroyed()) {
                return;
            }
            if (!Number.isFinite(sample.height)) {
                throw new Error(labels.layererror);
            }
            const ground = Cesium.Cartesian3.fromRadians(sample.longitude, sample.latitude, 0);
            const original = Cesium.Cartesian3.fromRadians(sample.longitude, sample.latitude, sample.height);
            const translation = Cesium.Cartesian3.subtract(ground, original, new Cesium.Cartesian3());
            buildingsTileset.modelMatrix = Cesium.Matrix4.fromTranslation(translation);
            buildingsAnchorKey = key;
            buildingsTileset.show = buildingsEnabled;
            viewer.scene.requestRender();
        } catch (error) {
            if (revision === buildingsHeightRevision && !viewer.isDestroyed()) {
                toast(labels.layererror + ': ' + error.message, true);
            }
        }
    };

    /**
     * Initialise Cesium's thumbnail picker with the activity map and flat terrain selected.
     *
     * @param {Object|null} custommapping Validated custom activity map, if available.
     */
    const initLayerPicker = (custommapping) => {
        const imagery = Cesium.createDefaultImageryProviderViewModels();
        const satelliteIndex = imagery.findIndex((item) => item.name === 'ArcGIS World Imagery');
        const officialSatellite = imagery[satelliteIndex];
        const satellite = new Cesium.ProviderViewModel({
            name: officialSatellite?.name || labels.layersatellite,
            tooltip: officialSatellite?.tooltip || labels.layersatellite,
            iconUrl: officialSatellite?.iconUrl ||
                Cesium.buildModuleUrl('Widgets/Images/ImageryProviders/ArcGisMapServiceWorldImagery.png'),
            category: officialSatellite?.category || 'Other',
            creationFunction: () => Cesium.ArcGisMapServerImageryProvider.fromUrl(SATELLITE_URL),
        });
        if (satelliteIndex >= 0) {
            imagery[satelliteIndex] = satellite;
        } else {
            imagery.push(satellite);
        }

        let selectedImagery = satellite;
        if (custommapping) {
            const custom = new Cesium.ProviderViewModel({
                name: labels.layercustom,
                tooltip: labels.layercustom,
                iconUrl: Cesium.buildModuleUrl('Widgets/Images/ImageryProviders/ArcGisMapServiceWorldImagery.png'),
                category: 'Other',
                creationFunction: () => [
                    createBaseProvider(Cesium, null),
                    createBaseProvider(Cesium, custommapping),
                ],
            });
            imagery.push(custom);
            selectedImagery = custom;
        }

        const flat = new Cesium.ProviderViewModel({
            name: labels.terrainflat,
            tooltip: labels.terrainflat,
            iconUrl: Cesium.buildModuleUrl('Widgets/Images/TerrainProviders/Ellipsoid.png'),
            category: 'Other',
            creationFunction: () => new Cesium.EllipsoidTerrainProvider(),
        });
        const relief = new Cesium.ProviderViewModel({
            name: labels.terrainrelief,
            tooltip: labels.terrainrelief,
            iconUrl: Cesium.buildModuleUrl('Widgets/Images/TerrainProviders/CesiumWorldTerrain.png'),
            category: 'Other',
            creationFunction: () => getWorldTerrain()
                .catch((error) => {
                    toast(labels.layererror + ': ' + error.message, true);
                    return new Cesium.EllipsoidTerrainProvider();
                }),
        });

        layerPicker = new Cesium.BaseLayerPicker('cessium-layer-picker', {
            globe: viewer.scene.globe,
            imageryProviderViewModels: imagery,
            selectedImageryProviderViewModel: selectedImagery,
            terrainProviderViewModels: [flat, relief],
            selectedTerrainProviderViewModel: flat,
        });
        const gallery = element('layer-picker').querySelector('.cesium-baseLayerPicker-dropDown');
        if (gallery) {
            const buildingToggle = document.createElement('label');
            buildingToggle.className = 'cessium-buildings-toggle';
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = buildingsEnabled;
            checkbox.addEventListener('change', () => {
                buildingsEnabled = checkbox.checked;
                updateBuildingsHeight();
            });
            const label = document.createElement('span');
            label.textContent = labels.buildings3d;
            buildingToggle.append(checkbox, label);
            buildingToggle.addEventListener('click', (event) => event.stopPropagation());
            gallery.prepend(buildingToggle);
        }
        updateLayerPickerLayout();
        window.addEventListener('resize', updateLayerPickerLayout);
        const selection = Cesium.knockout.getObservable(layerPicker.viewModel, 'selectedImagery');
        layerSelectionSubscription = selection.subscribe((item) => {
            const isSatellite = item === satellite;
            viewer.scene.globe.enableLighting = isSatellite;
            if (isSatellite) {
                viewer.clock.currentTime = Cesium.JulianDate.now();
            }
            viewer.scene.requestRender();
        });
        viewer.scene.globe.enableLighting = selectedImagery === satellite;
        if (selectedImagery === satellite) {
            viewer.clock.currentTime = Cesium.JulianDate.now();
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

    /** @returns {Object} Local east-north-up transform facing the navigation bearing. */
    const markerModelMatrix = () => {
        const location = Cesium.Cartesian3.fromDegrees(position[0], position[1]);
        const heading = markerBearing === null ? 0 :
            Cesium.Math.toRadians(markerBearing) - Cesium.Math.PI_OVER_TWO;
        return Cesium.Transforms.headingPitchRollToFixedFrame(location,
            new Cesium.HeadingPitchRoll(heading, 0, 0));
    };

    /** Position the model at the selected point. */
    const updateMarkerModelMatrix = () => {
        if (markerModel && position) {
            markerModel.modelMatrix = markerModelMatrix();
        }
    };

    /** Play only the clip required by the current game state. */
    const updateMarkerAnimation = () => {
        if (!markerModel?.ready || markerPlayingClip === markerClip) {
            return;
        }
        markerModel.activeAnimations.removeAll();
        markerModel.activeAnimations.add({name: markerClip, loop: Cesium.ModelAnimationLoop.REPEAT});
        markerPlayingClip = markerClip;
        viewer.scene.requestRender();
    };

    /** Load one shared glTF model and keep it clamped to the terrain. */
    const loadMarkerModel = async() => {
        if (markerModel || markerModelLoading) {
            return;
        }
        markerModelLoading = true;
        try {
            const model = await Cesium.Model.fromGltfAsync({
                url: config.pegmananimationmodel,
                scene: viewer.scene,
                heightReference: Cesium.HeightReference.CLAMP_TO_GROUND,
                modelMatrix: markerModelMatrix(),
                show: markerVisible,
                scale: markerScaleValue,
            });
            if (markerDisposed || viewer.isDestroyed()) {
                model.destroy();
                return;
            }
            markerModel = viewer.scene.primitives.add(model);
            // Camera fitting and scale calculation may finish while the GLB is loading.
            model.scale = markerScaleValue;
            model.show = markerVisible;
            model.imageBasedLighting.imageBasedLightingFactor = new Cesium.Cartesian2(2.5, 1);
            updateMarkerModelMatrix();
            if (model.ready) {
                updateMarkerAnimation();
            } else {
                model.readyEvent.addEventListener(updateMarkerAnimation);
            }
            viewer.scene.requestRender();
        } catch (error) {
            toast(labels.layererror + ': ' + error.message, true);
        } finally {
            markerModelLoading = false;
        }
    };

    /** Update the coordinate marker on the globe. */
    const updateMarker = () => {
        if (!viewer || !position) {
            return;
        }
        if (!marker) {
            marker = viewer.entities.add({
                name: labels.selectedposition,
                label: {
                    show: false,
                    text: '',
                    font: 'bold 15px sans-serif',
                    fillColor: Cesium.Color.WHITE,
                    showBackground: true,
                    backgroundColor: Cesium.Color.fromCssColorString('#071d2f').withAlpha(0.9),
                    backgroundPadding: new Cesium.Cartesian2(10, 7),
                    verticalOrigin: Cesium.VerticalOrigin.TOP,
                    pixelOffset: new Cesium.Cartesian2(0, 24),
                    heightReference: Cesium.HeightReference.CLAMP_TO_GROUND,
                    disableDepthTestDistance: 0,
                },
            });
            // The glTF animation needs a clock tick and a rendered frame on every cycle.
            // Keep the animated model responsive without forcing a 60 FPS render loop on mobile GPUs.
            viewer.scene.maximumRenderTimeChange = 0.05;
        }
        marker.position = Cesium.Cartesian3.fromDegrees(position[0], position[1]);
        markerVisible = true;
        updateMarkerScale();
        updateMarkerModelMatrix();
        loadMarkerModel();
        element('submit').disabled = roadfinished || !available || !position ||
            Boolean(stage?.question) || stage?.activitysolved === false;
        updateNavigation();
        viewer.scene.requestRender();
    };

    /**
     * Find metres per CSS pixel at the camera's ground focus.
     *
     * @returns {number|null} Ground scale, or null if the viewport is not ready.
     */
    const getMarkerMetresPerPixel = () => {
        if (!marker || !position) {
            return null;
        }
        const canvas = viewer.scene.canvas;
        const width = canvas.clientWidth;
        const height = canvas.clientHeight;
        if (!width || !height) {
            return null;
        }
        const screenCentre = new Cesium.Cartesian2(width / 2, height / 2);
        const ray = viewer.camera.getPickRay(screenCentre);
        const focus = ray && viewer.scene.globe.pick(ray, viewer.scene) ||
            viewer.camera.pickEllipsoid(screenCentre, viewer.scene.globe.ellipsoid) ||
            Cesium.Cartesian3.fromDegrees(position[0], position[1]);
        // Passing CSS dimensions gives metres per CSS pixel, regardless of devicePixelRatio.
        const metresPerPixel = viewer.camera.getPixelSize(new Cesium.BoundingSphere(focus, 0.01), width, height);
        if (!Number.isFinite(metresPerPixel) || metresPerPixel <= 0) {
            return null;
        }
        return metresPerPixel;
    };

    /** Fix the pegman's physical size from 100 CSS pixels at the camera's ground focus when positioned. */
    const updateMarkerScale = () => {
        const metresPerPixel = getMarkerMetresPerPixel();
        if (metresPerPixel === null) {
            if (markerModel) {
                markerModel.show = markerVisible;
            }
            return;
        }
        if (markerScaleFrame !== null) {
            window.cancelAnimationFrame(markerScaleFrame);
            markerScaleFrame = null;
        }
        markerScaleReference = metresPerPixel;
        markerScaleValue = PEGMAN_REFERENCE_HEIGHT_PX * metresPerPixel / PEGMAN_HEIGHT;
        markerVisible = true;
        if (markerModel) {
            markerModel.scale = markerScaleValue;
            markerModel.show = true;
        }
        markerScaleCalculated = true;
        viewer.scene.requestRender();
    };

    /**
     * Tremble at the current size, then grow towards the new physical size and settle.
     *
     * @param {number} target New model scale.
     */
    const animateMarkerScale = (target) => {
        if (markerScaleFrame !== null) {
            window.cancelAnimationFrame(markerScaleFrame);
            markerScaleFrame = null;
        }
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            markerScaleValue = target;
            if (markerModel) {
                markerModel.scale = target;
            }
            viewer.scene.requestRender();
            return;
        }
        const from = markerScaleValue > 0 ? markerScaleValue : target;
        const started = performance.now();
        const tick = (now) => {
            const elapsed = Math.min(now - started, PEGMAN_SCALE_SHAKE_MS + PEGMAN_SCALE_BOUNCE_MS);
            let scale;
            if (elapsed < PEGMAN_SCALE_SHAKE_MS) {
                const phase = elapsed / PEGMAN_SCALE_SHAKE_MS;
                const shake = 0.09 * Math.sin(Math.PI * phase) *
                    Math.sin(2 * Math.PI * PEGMAN_SCALE_SHAKE_HZ * elapsed / 1000);
                scale = from * (1 + shake);
            } else {
                const phase = (elapsed - PEGMAN_SCALE_SHAKE_MS) / PEGMAN_SCALE_BOUNCE_MS;
                const remaining = phase - 1;
                const easing = 1 + 2.5 * remaining ** 3 + 1.5 * remaining ** 2;
                scale = from + (target - from) * easing;
            }
            markerScaleValue = elapsed === PEGMAN_SCALE_SHAKE_MS + PEGMAN_SCALE_BOUNCE_MS ?
                target : Math.max(0.0001, scale);
            if (markerModel) {
                markerModel.scale = markerScaleValue;
            }
            viewer.scene.requestRender();
            markerScaleFrame = elapsed < PEGMAN_SCALE_SHAKE_MS + PEGMAN_SCALE_BOUNCE_MS ?
                window.requestAnimationFrame(tick) : null;
        };
        markerScaleFrame = window.requestAnimationFrame(tick);
    };

    /** Recalibrate only after zooming beyond twice the last calibrated view scale. */
    const updateMarkerScaleAfterZoom = () => {
        if (!markerScaleCalculated || markerScaleReference === null) {
            return;
        }
        const metresPerPixel = getMarkerMetresPerPixel();
        if (metresPerPixel === null || (metresPerPixel <= markerScaleReference * PEGMAN_ZOOM_FACTOR &&
                metresPerPixel >= markerScaleReference / PEGMAN_ZOOM_FACTOR)) {
            return;
        }
        markerScaleReference = metresPerPixel;
        animateMarkerScale(PEGMAN_REFERENCE_HEIGHT_PX * metresPerPixel / PEGMAN_HEIGHT);
    };

    /** @returns {number} Bearing from the marker towards the camera, clockwise from north. */
    const cameraFacingBearing = () => {
        const location = Cesium.Cartesian3.fromDegrees(position[0], position[1]);
        const inverse = Cesium.Matrix4.inverseTransformation(
            Cesium.Transforms.eastNorthUpToFixedFrame(location), new Cesium.Matrix4());
        const camera = Cesium.Matrix4.multiplyByPoint(inverse, viewer.camera.positionWC, new Cesium.Cartesian3());
        if (Math.hypot(camera.x, camera.y) < 1) {
            return (Cesium.Math.toDegrees(viewer.camera.heading) + 180 + 360) % 360;
        }
        return (Cesium.Math.toDegrees(Math.atan2(camera.x, camera.y)) + 360) % 360;
    };

    /** @param {number|null} bearing Direction clockwise from north, or null to face the camera. */
    const updateMarkerHeading = (bearing) => {
        markerBearing = bearing === null && position ? cameraFacingBearing() : bearing;
        updateMarkerModelMatrix();
    };

    /**
     * Place a bright green direction arrow on the terrain below the position model.
     *
     * @param {number} bearing Direction in degrees clockwise from north.
     */
    const updateCompass = (bearing) => {
        const cartographic = Cesium.Cartographic.fromDegrees(position[0], position[1]);
        const height = viewer.scene.globe.getHeight(cartographic) || 0;
        const centre = Cesium.Cartesian3.fromDegrees(position[0], position[1], height);
        const sphere = new Cesium.BoundingSphere(centre, 0.01);
        const metresPerPixel = viewer.camera.getPixelSize(sphere, viewer.scene.canvas.width, viewer.scene.canvas.height);
        if (!Number.isFinite(metresPerPixel) || metresPerPixel <= 0) {
            return;
        }
        const length = metresPerPixel * COMPASS_ARROW_LENGTH_PX;
        const enu = Cesium.Transforms.eastNorthUpToFixedFrame(centre);
        const radians = Cesium.Math.toRadians(bearing);
        const east = Math.sin(radians);
        const north = Math.cos(radians);
        const point = (forward, sideways) => {
            const tangent = Cesium.Matrix4.multiplyByPoint(enu,
                new Cesium.Cartesian3(east * forward + north * sideways,
                    north * forward - east * sideways, 0), new Cesium.Cartesian3());
            const onGlobe = Cesium.Cartographic.fromCartesian(tangent);
            return Cesium.Cartesian3.fromRadians(onGlobe.longitude, onGlobe.latitude);
        };
        const arrow = new Cesium.PolygonHierarchy([
            point(length * 1.2, 0),
            point(length * 0.62, -length * 0.32),
            point(length * 0.62, -length * 0.13),
            point(length * 0.2, -length * 0.13),
            point(length * 0.2, length * 0.13),
            point(length * 0.62, length * 0.13),
            point(length * 0.62, length * 0.32),
        ]);
        if (!compassArrow) {
            compassArrow = viewer.entities.add({
                polygon: {
                    hierarchy: arrow,
                    material: Cesium.Color.fromCssColorString('#39ff72'),
                    heightReference: Cesium.HeightReference.CLAMP_TO_GROUND,
                    classificationType: Cesium.ClassificationType.TERRAIN,
                },
            });
        }
        compassArrow.polygon.hierarchy = arrow;
        compassArrow.show = true;
        compassBearing = bearing;
        updateMarkerHeading(bearing);
    };

    /** Update optional navigation hints from the current position. */
    const updateNavigation = () => {
        const panel = element('navigation');
        const target = position && closestStagePoint(position, nextstage);
        const inZone = Boolean(position && nextstage?.features?.some((feature) =>
            inGeometry(position, feature.geometry)));
        if (!position || !target || !(
            playerconfig.showdistancehint || playerconfig.showheadinghint || playerconfig.showinzonehint)) {
            panel.hidden = true;
            if (compassArrow) {
                compassArrow.show = false;
            }
            compassBearing = null;
            updateMarkerHeading(null);
            markerClip = selectClip({moving: !playwithoutmoving, finished: roadfinished,
                visibleTarget: Boolean(position && nextstage?.features?.length && playerconfig.shownextareahint),
                inside: inZone,
                confused: Date.now() < confusedUntil});
            updateMarkerAnimation();
            if (marker) {
                marker.label.show = false;
            }
            viewer.scene.requestRender();
            return;
        }
        panel.hidden = !(playerconfig.showdistancehint || playerconfig.showinzonehint);
        const first = Cesium.Cartographic.fromDegrees(position[0], position[1]);
        const second = Cesium.Cartographic.fromDegrees(target[0], target[1]);
        const line = new Cesium.EllipsoidGeodesic(first, second);
        const distance = line.surfaceDistance;
        const inside = playerconfig.showinzonehint && inZone;
        let distanceText = '—';
        if (inside) {
            distanceText = labels.insidezone;
        } else if (playerconfig.showdistancehint) {
            distanceText = distance >= 1000 ? (distance / 1000).toFixed(1) + ' km' : Math.round(distance) + ' m';
        }
        element('distance').textContent = distanceText;
        element('position-status').textContent = playwithoutmoving ? labels.selectedposition : labels.gpsposition;
        const bearing = Cesium.Math.toDegrees(line.startHeading || 0);
        if (playerconfig.showheadinghint && !inZone && distance >= 0.5) {
            updateCompass(bearing);
        } else {
            if (compassArrow) {
                compassArrow.show = false;
            }
            compassBearing = null;
            updateMarkerHeading(null);
        }
        markerClip = selectClip({
            moving: !playwithoutmoving,
            heading: Boolean(compassBearing !== null),
            distanceHint: Boolean(playerconfig.showdistancehint && !inZone && distance >= 0.5),
            visibleTarget: Boolean(playerconfig.shownextareahint),
            inside: inZone || distance < 0.5,
            finished: roadfinished,
            confused: Date.now() < confusedUntil,
        });
        updateMarkerAnimation();
        if (marker) {
            const hint = inside ? labels.insidezone : (playerconfig.showdistancehint ? distanceText : '');
            marker.label.text = hint;
            marker.label.show = Boolean(hint);
            viewer.scene.requestRender();
        }
    };

    /** Play the disoriented reaction for one cycle after a failed location attempt. */
    const reactToFailedAttempt = () => {
        confusedUntil = Date.now() + 3600;
        clearTimeout(confusedTimer);
        updateNavigation();
        confusedTimer = window.setTimeout(() => {
            confusedUntil = 0;
            updateNavigation();
        }, 3600);
    };

    /** Fly to the visible stage, a recent attempt or the player's marker. */
    const fitMap = () => {
        fitInProgress = true;
        let resolveFlight;
        flightCompletion = new Promise((resolve) => {
            resolveFlight = resolve;
        });
        const finish = () => {
            fitInProgress = false;
            if (!cameraReady) {
                cameraReady = true;
            }
            if (marker) {
                updateMarkerScale();
            }
            resolveFlight();
        };
        if (visibleStageBounds) {
            viewer.camera.flyTo({
                destination: Cesium.Rectangle.fromDegrees(...visibleStageBounds),
                duration: 1.2,
                complete: finish,
                cancel: finish,
            });
        } else if (attempts.length) {
            viewer.flyTo(attempts[attempts.length - 1], {duration: 1.2}).then(finish, finish);
        } else if (marker || gpsmarker) {
            const target = marker && !markerVisible ? gpsmarker : (marker || gpsmarker);
            if (target) {
                viewer.flyTo(target, {duration: 1.2}).then(finish, finish);
            } else {
                viewer.camera.flyTo({
                    destination: Cesium.Cartesian3.fromDegrees(position[0], position[1], 1800),
                    duration: 1.2,
                    complete: finish,
                    cancel: finish,
                });
            }
        } else {
            viewer.camera.flyHome(1.2);
            fitInProgress = false;
            resolveFlight();
        }
        return flightCompletion;
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
                            // Ground classification follows the globe's curvature, including very large areas.
                            heightReference: Cesium.HeightReference.CLAMP_TO_GROUND,
                            classificationType: Cesium.ClassificationType.TERRAIN,
                            material: outline.withAlpha(0.26),
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
                        position: Cesium.Cartesian3.fromDegrees(centre[0], centre[1]),
                        label: {
                            text: Number(feature.properties?.stageposition) === 1 ? labels.startfromhere : labels.stage + ' ' +
                                (feature.properties?.stageposition || stageposition),
                            font: 'bold 17px sans-serif',
                            fillColor: Cesium.Color.WHITE,
                            outlineColor: Cesium.Color.fromCssColorString('#07384b'),
                            outlineWidth: 4,
                            style: Cesium.LabelStyle.FILL_AND_OUTLINE,
                            heightReference: Cesium.HeightReference.CLAMP_TO_GROUND,
                            disableDepthTestDistance: 0,
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
        root.classList.remove('has-attempt-popup');
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
                    disableDepthTestDistance: 0,
                },
                label: {
                    text: String(feature.properties?.stageposition || ''),
                    font: 'bold 14px sans-serif',
                    fillColor: Cesium.Color.WHITE,
                    style: Cesium.LabelStyle.FILL_AND_OUTLINE,
                    outlineColor: Cesium.Color.BLACK,
                    outlineWidth: 3,
                    pixelOffset: new Cesium.Cartesian2(0, -37),
                    heightReference: Cesium.HeightReference.CLAMP_TO_GROUND,
                    disableDepthTestDistance: 0,
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
        if (viewer.scene.mode === Cesium.SceneMode.SCENE3D &&
                !new Cesium.EllipsoidalOccluder(viewer.scene.globe.ellipsoid, viewer.camera.positionWC).isPointVisible(world)) {
            popup.hidden = true;
            root.classList.remove('has-attempt-popup');
            return;
        }
        const screen = Cesium.SceneTransforms.worldToWindowCoordinates(viewer.scene, world);
        if (!screen) {
            popup.hidden = true;
            root.classList.remove('has-attempt-popup');
            return;
        }
        const canvas = viewer.scene.canvas.getBoundingClientRect();
        const viewport = root.getBoundingClientRect();
        const x = screen.x + canvas.left - viewport.left;
        const y = screen.y + canvas.top - viewport.top;
        popup.hidden = x < 0 || x > viewport.width || y < 0 || y > viewport.height;
        root.classList.toggle('has-attempt-popup', !popup.hidden);
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
            title = properties.name ? labels.stageovercome : labels.discoveredlocation;
        }
        element('attempt-title').textContent = title;
        const content = element('attempt-content');
        content.replaceChildren();
        if (properties.info) {
            const info = document.createElement('div');
            info.innerHTML = properties.info;
            content.appendChild(info);
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
        selectedAttempt = entity;
        positionAttemptPopup();
    };

    /** Update the question and objective from the last resolved stage. */
    const renderClue = () => {
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
    };

    /** Frame the geometry of the selected clue when the hunt reveals target areas. */
    const focusSelectedClue = () => {
        if (!playerconfig.shownextareahint) {
            return;
        }
        const clue = availableClues[selectedClueIndex];
        const feature = nextstage?.features?.find((item) => Number(item.id) === Number(clue?.stageid));
        const bounds = feature && geometryBounds({features: [feature]});
        if (bounds) {
            viewer.camera.flyTo({destination: Cesium.Rectangle.fromDegrees(...bounds), duration: 1.2});
        }
    };

    /**
     * Select a clue without moving the map during a response refresh.
     *
     * @param {number} index Clue index.
     * @param {boolean} focusMap Whether to frame its visible geometry.
     */
    const selectClue = (index, focusMap) => {
        selectedClueIndex = index;
        const tabs = element('clue-tabs');
        tabs.querySelectorAll('[role="tab"]').forEach((button, tabIndex) => {
            button.classList.toggle('is-active', tabIndex === index);
            button.setAttribute('aria-selected', String(tabIndex === index));
            button.tabIndex = tabIndex === index ? 0 : -1;
        });
        Array.from(element('clue-content').children).forEach((pane, paneIndex) => {
            pane.classList.toggle('is-active', paneIndex === index);
            pane.setAttribute('aria-hidden', String(paneIndex !== index));
        });
        if (focusMap) {
            focusSelectedClue();
        }
    };

    /**
     * Show one tab per clue supplied by the game service.
     *
     * @param {Array<Object>} clues Current available clues.
     * @returns {boolean} Whether the available clues changed.
     */
    const renderAvailableClues = (clues) => {
        const signature = JSON.stringify(clues.length ? clues : [clues, stage?.clue || '', roadfinished]);
        if (signature === clueSignature) {
            return false;
        }
        const tabs = element('clue-tabs');
        const content = element('clue-content');
        const restoreFocus = tabs.contains(document.activeElement);
        const previouslySelected = availableClues[selectedClueIndex];
        availableClues = clues;
        const previousIndex = clues.findIndex((clue) => previouslySelected &&
            clue.stageid === previouslySelected.stageid && clue.html === previouslySelected.html);
        selectedClueIndex = Math.max(0, previousIndex);
        clueSignature = signature;
        tabs.replaceChildren();
        content.replaceChildren();
        tabs.hidden = clues.length === 0;
        element('clue-title').textContent = clues.length > 1 ? labels.nextclues : labels.nextclue;
        if (!clues.length) {
            const fallback = document.createElement('div');
            fallback.className = 'cessium-richtext';
            fallback.innerHTML = stage?.clue || '';
            if (!fallback.textContent.trim() && !fallback.querySelector('img, video, iframe')) {
                fallback.textContent = labels.nohint;
            }
            content.appendChild(fallback);
            return true;
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
            tab.addEventListener('keydown', (event) => {
                let nextIndex = index;
                if (event.key === 'ArrowRight') {
                    nextIndex = (index + 1) % clues.length;
                } else if (event.key === 'ArrowLeft') {
                    nextIndex = (index + clues.length - 1) % clues.length;
                } else if (event.key === 'Home') {
                    nextIndex = 0;
                } else if (event.key === 'End') {
                    nextIndex = clues.length - 1;
                } else {
                    return;
                }
                event.preventDefault();
                selectClue(nextIndex, true);
                tabs.children[nextIndex].focus();
            });
            tabs.appendChild(tab);
            const pane = document.createElement('div');
            pane.id = 'cessium-clue-panel-' + index;
            pane.className = 'cessium-clue-pane cessium-richtext';
            pane.setAttribute('role', 'tabpanel');
            pane.setAttribute('aria-labelledby', tab.id);
            pane.innerHTML = clue.html;
            content.appendChild(pane);
        });
        selectClue(selectedClueIndex, false);
        if (restoreFocus) {
            tabs.children[selectedClueIndex].focus({preventScroll: true});
        }
        return true;
    };

    /** Show the current progress and the possible actions. */
    const updateActions = () => {
        const completed = roadfinished ? totalstages : Number(stage?.position || 0);
        const total = Math.max(totalstages, completed);
        element('progress-label').textContent = completed + ' / ' + (total || '—');
        const percent = total ? Math.round(100 * completed / total) : 0;
        element('progress-bar').style.width = percent + '%';
        element('progress-track').setAttribute('aria-valuenow', String(percent));
        element('finished').hidden = !roadfinished;
        element('clue').disabled = roadfinished;
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
        attemptid = response.attemptid;
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
            stageposition = Math.min(Number(stage?.position || 0) + 1, totalstages || Infinity);
        }
    };

    /**
     * Report action results and asynchronous game updates.
     *
     * @param {Object} response Service result.
     * @param {Object} action Request that produced this result.
     * @param {boolean} failedAttempt Whether the submitted location was incorrect.
     */
    const showMessages = (response, action, failedAttempt) => {
        const plainText = (item) => {
            const node = document.createElement('div');
            node.innerHTML = item;
            return node.textContent.replace(/\s+/g, ' ').trim();
        };
        const information = (response.infomsg || []).map(plainText).filter(Boolean);
        const message = information.join(' · ');
        if (failedAttempt || (action.location && Number(response.status?.code || 0) !== 0)) {
            const messages = [...new Set([plainText(response.status?.msg || ''), ...information].filter(Boolean))];
            element('feedback-content').textContent = messages.join('\n\n') || labels.failedlocation;
            openDialog('feedback');
            return;
        }
        if (action.location || action.qrtext || action.selectedanswerid) {
            toast(response.status?.msg, Number(response.status?.code) !== 0);
        }
        if (message) {
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
        const previousAttemptId = attemptid;
        updateMode(response);
        const finishstage = element('finished-stage');
        finishstage.innerHTML = roadfinished ? response.endmessage || '' : '';
        finishstage.hidden = !roadfinished || !response.endmessage;
        if (roadfinished && element('clue-dialog').open) {
            element('clue-dialog').close();
        }
        if (response.attempts) {
            renderAttempts(response.attempts);
        }
        if (response.lastsuccessfulstage) {
            stage = response.lastsuccessfulstage;
            totalstages = Number(stage.totalnumber || 0);
            renderClue();
        } else if (action.initialize) {
            stage = null;
            renderClue();
        }
        const cluesChanged = renderAvailableClues(Array.isArray(response.clues) ? response.clues : []);
        await updateGeometry(response, action, previousStage);
        if (action.initialize && !fitInProgress && !cameraReady && (position || attempts.length)) {
            fitMap();
        }
        // Keep the initial message and clue closed until the destination is in view.
        await flightCompletion;
        if (Array.isArray(response.attempthistory) && (response.attempthistory.length || action.initialize)) {
            history = response.attempthistory;
        }
        updateActions();
        updateNavigation();
        const failedAttempt = failedLocationAttempt(previousAttemptId, response, action);
        showMessages(response, action, failedAttempt);
        if (failedAttempt) {
            reactToFailedAttempt();
        }
        if (!element('feedback-dialog').open && !roadfinished && ((stage && previousStage !== stage.id) ||
                (availableClues.length && (action.initialize || cluesChanged)))) {
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
                        disableDepthTestDistance: 0,
                    },
                });
            }
            gpsmarker.position = Cesium.Cartesian3.fromDegrees(gpsposition[0], gpsposition[1]);
            viewer.scene.requestRender();
            if (gameStateLoaded && !cameraReady && !fitInProgress && !playwithoutmoving) {
                fitMap();
            }
        }, (error) => toast(labels.geolocationproblem + ': ' + error.message, true), {
            enableHighAccuracy: true,
            maximumAge: 5000,
            timeout: 20000,
        });
    };

    /** Attach all player buttons and modal controls. */
    const bindInterface = () => {
        touchGuard = setupTouchGestureGuard(viewer.scene.canvas);
        setupLocationSearch(element('search'), element('search-input'), element('search-results'),
            isEnabled(playerconfig.localizationbuttondisabled), (longitude, latitude) => {
                viewer.camera.flyTo({destination: Cesium.Cartesian3.fromDegrees(longitude, latitude, 2400),
                    duration: 1.6});
            }, labels);
        element('attempt-close').addEventListener('click', () => {
            selectedAttempt = null;
            element('attempt-popup').hidden = true;
            root.classList.remove('has-attempt-popup');
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
        viewer.camera.percentageChanged = 0.05;
        const removeCompassResizer = viewer.camera.changed.addEventListener(() => {
            if (compassBearing !== null && compassArrow?.show) {
                updateCompass(compassBearing);
            }
        });
        const removeZoomResizer = viewer.camera.moveEnd.addEventListener(updateMarkerScaleAfterZoom);
        const removeBuildingsPositioner = viewer.camera.moveEnd.addEventListener(updateBuildingsHeight);
        const removeBuildingsTerrainListener = viewer.scene.globe.terrainProviderChanged.addEventListener(updateBuildingsHeight);
        handler.setInputAction((event) => {
            if (touchGuard.shouldIgnoreClick()) {
                return;
            }
            const picked = viewer.scene.pick(event.position);
            if (picked?.id?.treasurehuntAttempt) {
                showAttemptPopup(picked.id);
                return;
            }
            selectedAttempt = null;
            element('attempt-popup').hidden = true;
            root.classList.remove('has-attempt-popup');
            if (!playwithoutmoving || roadfinished || !available) {
                return;
            }
            const ray = viewer.camera.getPickRay(event.position);
            const cartesian = ray && viewer.scene.globe.pick(ray, viewer.scene) ||
                viewer.camera.pickEllipsoid(event.position, viewer.scene.globe.ellipsoid);
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
            markerDisposed = true;
            window.removeEventListener('resize', updateLayerPickerLayout);
            clearTimeout(pollTimer);
            clearTimeout(confusedTimer);
            if (geowatch !== null) {
                navigator.geolocation.clearWatch(geowatch);
            }
            handler.destroy();
            removePopupPositioner();
            removeCompassResizer();
            removeZoomResizer();
            removeBuildingsPositioner();
            removeBuildingsTerrainListener();
            buildingsHeightRevision++;
            if (markerScaleFrame !== null) {
                window.cancelAnimationFrame(markerScaleFrame);
                markerScaleFrame = null;
            }
            layerSelectionSubscription.dispose();
            layerPicker.destroy();
            viewer.destroy();
        }, {once: true});
    };

    /** Create the globe and fetch the first game state. */
    const start = async() => {
        loading.hidden = false;
        retry.hidden = true;
        try {
            if (!viewer) {
                Cesium = await loadCesium(config.cesiumbaseurl);
                const baseLayer = await createBaseLayer(Cesium);
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
                const activeViewer = viewer;
                Cesium.createOsmBuildingsAsync().then((buildings) => {
                    if (viewer === activeViewer && !viewer.isDestroyed()) {
                        buildingsTileset = buildings;
                        buildings.show = false;
                        viewer.scene.primitives.add(buildings);
                        updateBuildingsHeight();
                        viewer.scene.requestRender();
                    }
                    return buildings;
                }).catch((error) => {
                    toast(labels.layererror + ': ' + error.message, true);
                });
                let custommapping = null;
                if (config.custommapping?.custombackgroundurl || config.custommapping?.wmsurl) {
                    try {
                        await createBaseProvider(Cesium, config.custommapping);
                        custommapping = config.custommapping;
                    } catch (error) {
                        toast(labels.layererror + ': ' + error.message, true);
                    }
                }
                initLayerPicker(custommapping);
                viewer.scene.globe.depthTestAgainstTerrain = true;
                viewer.scene.maximumRenderTimeChange = 60;
                viewer.clock.shouldAnimate = true;
                viewer.resolutionScale = Math.min(window.devicePixelRatio || 1, 1.25);
                viewer.camera.flyTo({destination: Cesium.Cartesian3.fromDegrees(0, 25, 15000000), duration: 0});
                bindInterface();
                if (!playwithoutmoving) {
                    startGps();
                }
            }
            await request({initialize: true});
            gameStateLoaded = true;
            if (!cameraReady && !fitInProgress) {
                if (position || attempts.length) {
                    fitMap();
                } else if (playwithoutmoving) {
                    cameraReady = true;
                }
            }
        } catch (error) {
            toast(labels.webserviceerror + ': ' + error.message, true);
            retry.hidden = false;
        }
    };

    await start();
};
