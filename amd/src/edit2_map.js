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
 * Native geographic map adapters for the second Treasure Hunt editor.
 *
 * @module mod_treasurehunt/edit2_map
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {loadMapbox, mapStyle} from 'mod_treasurehunt/mapbox_player';
import {loadCesium, createBaseProvider} from 'mod_treasurehunt/cessium_player';
import {createLayer as createPegman, loadLibrary as loadPegmanLibrary} from
    'mod_treasurehunt/mapbox_animated_pegman';
import {createPegmanScale} from 'mod_treasurehunt/edit2_pegman_scale';
import {selectCameraClip, selectClip} from 'mod_treasurehunt/pegman_animation_state';
import {setup as setupTouchGestureGuard} from 'mod_treasurehunt/touch_gesture_guard';
import {nearestSegment} from 'mod_treasurehunt/edit2_geometry';
import {createPopup} from 'mod_treasurehunt/edit2_pegman_popup';

/** @param {Object} geometry Stage geometry. @returns {Array} Polygon coordinate sets. */
const polygons = (geometry) => {
    if (geometry?.type === 'MultiPolygon') {
        return geometry.coordinates;
    }
    return geometry?.type === 'Polygon' ? [geometry.coordinates] : [];
};

/** @param {Array} coordinates Nested coordinates. @returns {Array<Array<number>>} Flat coordinate pairs. */
const pairs = (coordinates) => {
    if (!Array.isArray(coordinates)) {
        return [];
    }
    return typeof coordinates[0] === 'number' ? [coordinates] : coordinates.flatMap(pairs);
};

/** @param {Array} stages Road stages. @returns {Array<number>|null} Geographic extent. */
const extent = (stages) => {
    const points = stages.flatMap((stage) => pairs(stage.geometry?.coordinates));
    return points.length ? points.reduce((box, point) => [Math.min(box[0], point[0]),
        Math.min(box[1], point[1]), Math.max(box[2], point[0]), Math.max(box[3], point[1])],
    [180, 90, -180, -90]) : null;
};

/** @param {Object} stage Stage. @returns {Object} A GeoJSON feature collection. */
const stageFeatures = (stage) => polygons(stage.geometry).map((rings, index) => ({
    type: 'Feature',
    id: stage.id + '-' + index,
    properties: {stageid: stage.id, polygonindex: index, position: stage.position},
    geometry: {type: 'Polygon', coordinates: rings},
}));

/** @param {Object} stage Stage. @returns {Array<Object>} Outlines for all polygon rings. */
const stageProfiles = (stage) => polygons(stage.geometry).flatMap((rings, polygonindex) =>
    rings.map((ring, ringindex) => ({type: 'Feature',
        properties: {stageid: stage.id, polygonindex, ringindex},
        geometry: {type: 'LineString', coordinates: ring}})));

/**
 * @param {Object} config Map configuration.
 * @param {Object} callbacks Map editor callbacks.
 * @returns {Object} Mapbox editor adapter.
 */
export const createMapbox = async(config, callbacks) => {
    const mapboxgl = await loadMapbox();
    const library = await loadPegmanLibrary(config.threepegmanurl);
    mapboxgl.accessToken = config.mapboxaccesstoken || '';
    const mapping = config.custommapping;
    let style = 'osm';
    if (mapping?.wmsurl || mapping?.custombackgroundurl) {
        style = 'custom';
    } else if (config.mapboxaccesstoken) {
        style = 'satellite';
    }
    const map = new mapboxgl.Map({container: 'edit2mapcanvas', style: mapStyle(style, mapping),
        center: [0, 25], zoom: 2, pitch: 0, maxZoom: 19, antialias: true,
        performanceMetricsCollection: false, collectResourceTiming: false});
    const host = document.getElementById('mapedit');
    const project = (pair) => {
        const point = map.project(pair);
        const canvas = map.getCanvas().getBoundingClientRect();
        const bounds = host.getBoundingClientRect();
        return {x: point.x + canvas.left - bounds.left, y: point.y + canvas.top - bounds.top};
    };
    const popup = createPopup(host, config, project);
    const hostPixel = (pixel) => {
        const canvas = map.getCanvas().getBoundingClientRect();
        const bounds = host.getBoundingClientRect();
        return {x: pixel.x + canvas.left - bounds.left, y: pixel.y + canvas.top - bounds.top};
    };
    const touchGuard = setupTouchGestureGuard(map.getCanvas());
    map.addControl(new mapboxgl.NavigationControl(), 'bottom-right');
    const picker = document.createElement('select');
    picker.className = 'form-select form-select-sm edit2-layer-picker';
    picker.setAttribute('aria-label', config.layerlabel);
    if (config.mapboxaccesstoken) {
        picker.add(new Option(config.layersatellite, 'satellite'));
        picker.add(new Option('Mapbox streets', 'streets'));
    }
    picker.add(new Option(config.layerosm, 'osm'));
    if (mapping?.wmsurl || mapping?.custombackgroundurl) {
        picker.add(new Option(mapping.layername || config.layercustom, 'custom'));
    }
    picker.value = style;
    picker.addEventListener('change', () => {
        style = picker.value;
        map.setStyle(mapStyle(style, mapping));
    });
    document.getElementById('mapedit').appendChild(picker);

    let stages = [];
    let selected = null;
    let selectedPolygon = 0;
    let mode = 'navigate';
    let drawing = [];
    let dragVertex = null;
    let pegman = null;
    let lastPosition = null;
    const searchingClip = selectClip({moving: true, distanceHint: true});
    /** Keep the editor pegman animation in step with the camera pitch. */
    const updatePegmanAnimation = () => {
        if (pegman && map.getLayer('pegman-model')) {
            pegman.setClip(selectCameraClip(searchingClip, 90 - map.getPitch()));
        }
    };
    const pegmanScale = createPegmanScale(() => lastPosition ?
        78271.51696 * Math.cos(lastPosition[1] * Math.PI / 180) / 2 ** map.getZoom() : null,
    (value) => pegman?.setScale(value));
    const finish = () => {
        if (mode !== 'draw' || drawing.length < 3) {
            return;
        }
        callbacks.polygon([...drawing, drawing[0]]);
        drawing = [];
        callbacks.draftchange(0);
        render();
    };
    const collection = (features) => ({type: 'FeatureCollection', features});
    const getSelected = () => stages.find((stage) => Number(stage.id) === Number(selected));
    /** @param {Object|null} candidate Segment location to preview, or null to hide it. */
    const showInsertion = (candidate) => {
        if (map.getSource('edit2-insertion')) {
            map.getSource('edit2-insertion').setData(collection(candidate ? [{type: 'Feature',
                geometry: {type: 'Point', coordinates: candidate.coordinates}, properties: {}}] : []));
        }
    };
    /** @param {Object} event Mapbox pointer event. @returns {Object|null} Segment under the pointer. */
    const insertionAt = (event) => {
        const stage = getSelected();
        if (mode !== 'edit' || !stage || dragVertex !== null || !map.getLayer('edit2-fill')) {
            return null;
        }
        const picked = map.queryRenderedFeatures(event.point, {layers: ['edit2-outline', 'edit2-fill']})[0];
        if (picked && Number(picked.properties.stageid) !== Number(selected)) {
            return null;
        }
        return nearestSegment(polygons(stage.geometry)[selectedPolygon] || [],
            (pair) => map.project(pair), event.point);
    };
    const render = () => {
        if (!map.getSource('edit2-stages')) {
            return;
        }
        map.getSource('edit2-stages').setData(collection(stages.flatMap(stageFeatures)));
        map.getSource('edit2-profiles').setData(collection(stages.flatMap(stageProfiles)));
        const stage = getSelected();
        const vertices = stage && mode === 'edit' ?
            (polygons(stage.geometry)[selectedPolygon] || []).flatMap((ring, ringindex) =>
                ring.slice(0, -1).map((point, index) => ({
                    type: 'Feature', geometry: {type: 'Point', coordinates: point},
                    properties: {index, ringindex},
                }))) : [];
        map.getSource('edit2-vertices').setData(collection(vertices));
        const draft = drawing.length > 1 ? [{type: 'Feature',
            geometry: {type: 'LineString', coordinates: drawing}, properties: {}}] : [];
        map.getSource('edit2-draft').setData(collection(draft));
    };
    const install = () => {
        if (style !== 'custom' && map.getSource('composite') && !map.getLayer('mapbox-3d-buildings')) {
            map.addLayer({id: 'mapbox-3d-buildings', source: 'composite', 'source-layer': 'building',
                filter: ['==', ['get', 'extrude'], 'true'], type: 'fill-extrusion', minzoom: 15,
                paint: {'fill-extrusion-color': '#aebac5', 'fill-extrusion-height': ['get', 'height'],
                    'fill-extrusion-base': ['get', 'min_height'], 'fill-extrusion-opacity': 0.72}});
        }
        map.addSource('edit2-stages', {type: 'geojson', data: collection([])});
        map.addSource('edit2-profiles', {type: 'geojson', data: collection([])});
        map.addSource('edit2-vertices', {type: 'geojson', data: collection([])});
        map.addSource('edit2-insertion', {type: 'geojson', data: collection([])});
        map.addSource('edit2-draft', {type: 'geojson', data: collection([])});
        map.addLayer({id: 'edit2-fill', type: 'fill', source: 'edit2-stages',
            paint: {'fill-color': ['case', ['==', ['get', 'stageid'], Number(selected)], '#ffd43b', '#0d6efd'],
                'fill-opacity': 0.33}});
        map.addLayer({id: 'edit2-outline', type: 'line', source: 'edit2-profiles',
            paint: {'line-color': ['case', ['==', ['get', 'stageid'], Number(selected)], '#b45309', '#063b82'],
                'line-width': 4, 'line-opacity': 1}});
        map.addLayer({id: 'edit2-draft-line', type: 'line', source: 'edit2-draft',
            paint: {'line-color': '#ff9f1c', 'line-width': 3, 'line-dasharray': [2, 1]}});
        map.addLayer({id: 'edit2-handles', type: 'circle', source: 'edit2-vertices',
            paint: {'circle-radius': 7, 'circle-color': '#fff', 'circle-stroke-width': 3,
                'circle-stroke-color': '#d63384'}});
        map.addLayer({id: 'edit2-insertion-point', type: 'circle', source: 'edit2-insertion',
            paint: {'circle-radius': 7, 'circle-color': '#ffd43b', 'circle-stroke-width': 2,
                'circle-stroke-color': '#3a2600'}});
        pegman = createPegman(mapboxgl, library, config.pegmanmodel, callbacks.error);
        map.addLayer(pegman);
        if (lastPosition) {
            pegman.setPosition(lastPosition);
            pegman.setBearing((map.getBearing() + 180) % 360);
            updatePegmanAnimation();
            pegmanScale.reapply();
        }
        render();
    };
    map.on('style.load', install);
    await new Promise((resolve) => map.once('load', resolve));
    map.on('zoomend', () => pegmanScale.zoomEnd());
    map.on('move', updatePegmanAnimation);
    map.on('click', (event) => {
        if (touchGuard.shouldIgnoreClick()) {
            return;
        }
        if (mode === 'draw') {
            drawing.push([event.lngLat.lng, event.lngLat.lat]);
            callbacks.draftchange(drawing.length);
            render();
            return;
        }
        if (mode === 'edit' && dragVertex !== null) {
            return;
        }
        if (mode === 'navigate' && popup.isNear(hostPixel(event.point), 40, lastPosition)) {
            popup.show(lastPosition);
            return;
        }
        const picked = map.queryRenderedFeatures(event.point, {layers: ['edit2-outline', 'edit2-fill']})[0];
        if (picked) {
            if (mode === 'edit' && Number(picked.properties.stageid) === Number(selected)) {
                return;
            }
            callbacks.select(Number(picked.properties.stageid), Number(picked.properties.polygonindex));
            return;
        }
        if (mode === 'navigate') {
            lastPosition = [event.lngLat.lng, event.lngLat.lat];
            pegman?.setPosition(lastPosition);
            pegman?.setBearing((map.getBearing() + 180) % 360);
            updatePegmanAnimation();
            pegmanScale.positioned();
            popup.show(lastPosition);
            callbacks.position(lastPosition);
        }
    });
    map.on('dblclick', (event) => {
        if (mode !== 'draw') {
            return;
        }
        event.preventDefault();
        // Mapbox also dispatches the second click, so drop a repeated final point.
        if (drawing.length > 1 && drawing.at(-1)[0] === drawing.at(-2)[0] &&
                drawing.at(-1)[1] === drawing.at(-2)[1]) {
            drawing.pop();
        }
        finish();
    });
    map.on('mousedown', 'edit2-handles', (event) => {
        if (mode !== 'edit' || !getSelected()) {
            return;
        }
        event.preventDefault();
        dragVertex = {index: Number(event.features[0].properties.index),
            ringindex: Number(event.features[0].properties.ringindex)};
        map.dragPan.disable();
    });
    map.on('mousedown', (event) => {
        if (touchGuard.shouldIgnoreClick() || !map.getLayer('edit2-handles') ||
                map.queryRenderedFeatures(event.point, {layers: ['edit2-handles']}).length) {
            return;
        }
        const target = insertionAt(event);
        if (!target) {
            return;
        }
        event.preventDefault();
        const stage = getSelected();
        polygons(stage.geometry)[selectedPolygon][target.ringIndex].splice(target.insertionIndex, 0,
            target.coordinates);
        dragVertex = {index: target.insertionIndex, ringindex: target.ringIndex};
        showInsertion(null);
        map.dragPan.disable();
        callbacks.geometry(stage);
        render();
    });
    map.on('mousemove', (event) => {
        if (dragVertex === null) {
            const candidate = insertionAt(event);
            showInsertion(candidate);
            map.getCanvas().style.cursor = candidate ? 'crosshair' : '';
            return;
        }
        const stage = getSelected();
        const ring = polygons(stage.geometry)[selectedPolygon][dragVertex.ringindex];
        ring[dragVertex.index] = [event.lngLat.lng, event.lngLat.lat];
        ring[ring.length - 1] = [...ring[0]];
        callbacks.geometry(stage);
        render();
    });
    map.on('mouseup', () => {
        dragVertex = null;
        map.dragPan.enable();
    });
    map.getCanvas().addEventListener('pointerleave', () => showInsertion(null));
    map.on('movestart', () => showInsertion(null));
    return {
        setRoad(items) {
            popup.close();
            showInsertion(null);
            stages = items;
            render();
        },
        setSelection(id, index = 0) {
            showInsertion(null);
            selected = id;
            selectedPolygon = index;
            if (map.getLayer('edit2-fill')) {
                map.setPaintProperty('edit2-fill', 'fill-color',
                    ['case', ['==', ['get', 'stageid'], Number(selected)], '#ffd43b', '#0d6efd']);
                map.setPaintProperty('edit2-outline', 'line-color',
                    ['case', ['==', ['get', 'stageid'], Number(selected)], '#b45309', '#063b82']);
            }
            render();
        },
        setMode(next) {
            popup.close();
            showInsertion(null);
            mode = next;
            drawing = [];
            callbacks.draftchange(0);
            map.doubleClickZoom.enable();
            if (mode === 'draw') {
                map.doubleClickZoom.disable();
            }
            render();
        },
        fit(items) {
            const bounds = extent(items);
            if (bounds) {
                map.fitBounds([[bounds[0], bounds[1]], [bounds[2], bounds[3]]], {padding: 80, maxZoom: 17});
            }
        },
        focusStage(item) {
            const bounds = extent([item]);
            if (bounds) {
                const workspace = document.getElementById('editorworkspace');
                const aside = document.getElementById('editoraside');
                const left = workspace.classList.contains('is-collapsed') ? 80 :
                    Math.min(aside.offsetWidth + 30, map.getCanvas().clientWidth * 0.4);
                map.fitBounds([[bounds[0], bounds[1]], [bounds[2], bounds[3]]],
                    {padding: {top: 90, right: 70, bottom: 70, left}, maxZoom: 18, duration: 800});
            }
        },
        flyTo(point) {
            map.flyTo({center: point, zoom: 16});
        },
        resize() {
            map.resize();
        },
        finish,
    };
};

/**
 * @param {Object} config Map configuration.
 * @param {Object} callbacks Map editor callbacks.
 * @returns {Object} Cesium editor adapter.
 */
export const createCesium = async(config, callbacks) => {
    const Cesium = await loadCesium(config.cesiumbaseurl);
    const provider = await createBaseProvider(Cesium, config.custommapping);
    const viewer = new Cesium.Viewer('edit2mapcanvas', {baseLayer: new Cesium.ImageryLayer(provider),
        terrainProvider: new Cesium.EllipsoidTerrainProvider(), baseLayerPicker: false,
        animation: false, timeline: false, geocoder: true, homeButton: true,
        navigationHelpButton: true, fullscreenButton: false, infoBox: false,
        selectionIndicator: false});
    const host = document.getElementById('mapedit');
    const projectCanvas = (pair) => {
        const location = Cesium.Cartographic.fromDegrees(pair[0], pair[1]);
        const height = viewer.scene.globe.getHeight(location) || 0;
        return Cesium.SceneTransforms.worldToWindowCoordinates(viewer.scene,
            Cesium.Cartesian3.fromRadians(location.longitude, location.latitude, height));
    };
    const project = (pair) => {
        const point = projectCanvas(pair);
        if (!point) {
            return null;
        }
        const canvas = viewer.scene.canvas.getBoundingClientRect();
        const bounds = host.getBoundingClientRect();
        return {x: point.x + canvas.left - bounds.left, y: point.y + canvas.top - bounds.top};
    };
    const popup = createPopup(host, config, project);
    const hostPixel = (pixel) => {
        const canvas = viewer.scene.canvas.getBoundingClientRect();
        const bounds = host.getBoundingClientRect();
        return {x: pixel.x + canvas.left - bounds.left, y: pixel.y + canvas.top - bounds.top};
    };
    const pickerHost = document.createElement('div');
    pickerHost.className = 'edit2-cesium-picker';
    document.getElementById('mapedit').appendChild(pickerHost);
    const imagery = Cesium.createDefaultImageryProviderViewModels();
    const hasCustomMap = Boolean(config.custommapping?.wmsurl || config.custommapping?.custombackgroundurl);
    const activityMap = new Cesium.ProviderViewModel({
        name: hasCustomMap ? config.layercustom : config.layerosm,
        tooltip: hasCustomMap ? config.layercustom : config.layerosm,
        iconUrl: Cesium.buildModuleUrl('Widgets/Images/ImageryProviders/ArcGisMapServiceWorldImagery.png'),
        category: 'Other',
        creationFunction: () => createBaseProvider(Cesium, config.custommapping),
    });
    imagery.push(activityMap);
    const flat = new Cesium.ProviderViewModel({
        name: config.terrainflat, tooltip: config.terrainflat,
        iconUrl: Cesium.buildModuleUrl('Widgets/Images/TerrainProviders/Ellipsoid.png'),
        category: 'Other',
        creationFunction: () => new Cesium.EllipsoidTerrainProvider(),
    });
    const relief = new Cesium.ProviderViewModel({
        name: config.terrainrelief, tooltip: config.terrainrelief,
        iconUrl: Cesium.buildModuleUrl('Widgets/Images/TerrainProviders/CesiumWorldTerrain.png'),
        category: 'Other',
        creationFunction: () => Cesium.createWorldTerrainAsync().catch(() =>
            new Cesium.EllipsoidTerrainProvider()),
    });
    viewer.imageryLayers.removeAll();
    new Cesium.BaseLayerPicker(pickerHost, {globe: viewer.scene.globe,
        imageryProviderViewModels: imagery, selectedImageryProviderViewModel: activityMap,
        terrainProviderViewModels: [flat, relief], selectedTerrainProviderViewModel: flat});
    viewer.scene.globe.depthTestAgainstTerrain = true;
    viewer.clock.shouldAnimate = true;
    const touchGuard = setupTouchGestureGuard(viewer.scene.canvas);
    const stages = {items: []};
    let selected = null;
    let selectedPolygon = 0;
    let mode = 'navigate';
    let drawing = [];
    let entities = [];
    let vertices = [];
    let draft = null;
    let dragVertex = null;
    let insertionPoint = null;
    let marker = null;
    let markerLoading = null;
    let markerPosition = null;
    let markerBearing = null;
    const searchingClip = selectClip({moving: true, distanceHint: true});
    let markerPlayingClip = null;
    /** Change the clip only when the camera crosses the overhead threshold. */
    const updateMarkerAnimation = () => {
        const clip = selectCameraClip(searchingClip, viewer.scene.mode === Cesium.SceneMode.SCENE3D ?
            -Cesium.Math.toDegrees(viewer.camera.pitch) : 0);
        if (!marker?.ready || markerPlayingClip === clip) {
            return;
        }
        marker.activeAnimations.removeAll();
        marker.activeAnimations.add({name: clip, loop: Cesium.ModelAnimationLoop.REPEAT});
        markerPlayingClip = clip;
        viewer.scene.requestRender();
    };
    const finish = () => {
        if (mode !== 'draw' || drawing.length < 3) {
            return;
        }
        callbacks.polygon([...drawing, drawing[0]]);
        drawing = [];
        callbacks.draftchange(0);
        render();
    };
    const getSelected = () => stages.items.find((stage) => Number(stage.id) === Number(selected));
    /** @param {Object|null} candidate Segment location to preview, or null to hide it. */
    const showInsertion = (candidate) => {
        if (!candidate) {
            if (insertionPoint) {
                insertionPoint.show = false;
                viewer.scene.requestRender();
            }
            return;
        }
        if (!insertionPoint) {
            insertionPoint = viewer.entities.add({position: Cesium.Cartesian3.fromDegrees(...candidate.coordinates),
                point: {pixelSize: 13, color: Cesium.Color.fromCssColorString('#ffd43b'),
                    outlineColor: Cesium.Color.fromCssColorString('#3a2600'), outlineWidth: 2,
                    heightReference: Cesium.HeightReference.CLAMP_TO_GROUND,
                    disableDepthTestDistance: 0}});
        }
        insertionPoint.position = Cesium.Cartesian3.fromDegrees(...candidate.coordinates);
        insertionPoint.editorStage = selected;
        insertionPoint.editorPolygon = selectedPolygon;
        insertionPoint.show = true;
        viewer.scene.requestRender();
    };
    /** @param {Object} screen Cesium window coordinates. @returns {Object|null} Segment under the pointer. */
    const insertionAt = (screen) => {
        const stage = getSelected();
        if (mode !== 'edit' || !stage || dragVertex !== null) {
            return null;
        }
        const picked = viewer.scene.pick(screen)?.id;
        if (picked?.editorStage && Number(picked.editorStage) !== Number(selected)) {
            return null;
        }
        return nearestSegment(polygons(stage.geometry)[selectedPolygon] || [], projectCanvas, screen);
    };
    const position = (screen) => {
        const ray = viewer.camera.getPickRay(screen);
        const point = ray && viewer.scene.globe.pick(ray, viewer.scene) || viewer.camera.pickEllipsoid(screen);
        if (!point) {
            return null;
        }
        const geo = Cesium.Cartographic.fromCartesian(point);
        return [Cesium.Math.toDegrees(geo.longitude), Cesium.Math.toDegrees(geo.latitude)];
    };
    const cartesian = (pair) => Cesium.Cartesian3.fromDegrees(pair[0], pair[1]);
    const clear = (list) => {
        list.forEach((entity) => viewer.entities.remove(entity)); list.length = 0;
    };
    const render = () => {
        clear(entities);
        clear(vertices);
        stages.items.forEach((stage) => polygons(stage.geometry).forEach((rings, polygonindex) => {
            if (!rings[0]?.length) {
                return;
            }
            const hierarchy = new Cesium.PolygonHierarchy(rings[0].map(cartesian),
                rings.slice(1).map((hole) => new Cesium.PolygonHierarchy(hole.map(cartesian))));
            const entity = viewer.entities.add({polygon: {hierarchy,
                material: Cesium.Color.fromCssColorString(Number(selected) === Number(stage.id) ?
                    '#ffd43b' : '#0d6efd').withAlpha(0.33),
                outline: false,
                heightReference: Cesium.HeightReference.CLAMP_TO_GROUND}});
            entity.editorStage = stage.id;
            entity.editorPolygon = polygonindex;
            entities.push(entity);
            rings.forEach((ring, ringindex) => {
                const profile = viewer.entities.add({polyline: {positions: ring.map(cartesian),
                    width: 4, clampToGround: true,
                    material: Cesium.Color.fromCssColorString(Number(selected) === Number(stage.id) ?
                        '#b45309' : '#063b82')}});
                profile.editorStage = stage.id;
                profile.editorPolygon = polygonindex;
                profile.editorRing = ringindex;
                entities.push(profile);
            });
        }));
        const stage = getSelected();
        if (mode === 'edit' && stage) {
            (polygons(stage.geometry)[selectedPolygon] || []).forEach((ring, ringindex) =>
                ring.slice(0, -1).forEach((pair, index) => {
                    const entity = viewer.entities.add({position: cartesian(pair), point: {
                        pixelSize: 14, color: Cesium.Color.WHITE,
                        outlineColor: Cesium.Color.fromCssColorString('#d63384'), outlineWidth: 3,
                        heightReference: Cesium.HeightReference.CLAMP_TO_GROUND,
                        disableDepthTestDistance: 0}});
                    entity.editorVertex = index;
                    entity.editorRing = ringindex;
                    vertices.push(entity);
                }));
        }
        if (draft) {
            viewer.entities.remove(draft); draft = null;
        }
        if (drawing.length > 1) {
            draft = viewer.entities.add({polyline: {positions: drawing.map(cartesian), width: 3,
                material: Cesium.Color.ORANGE, clampToGround: true}});
        }
        viewer.scene.requestRender();
    };
    const markerModelMatrix = () => Cesium.Transforms.headingPitchRollToFixedFrame(
        cartesian(markerPosition), new Cesium.HeadingPitchRoll(
            Cesium.Math.toRadians(markerBearing) - Cesium.Math.PI_OVER_TWO, 0, 0));
    const cameraFacingBearing = () => {
        const inverse = Cesium.Matrix4.inverseTransformation(
            Cesium.Transforms.eastNorthUpToFixedFrame(cartesian(markerPosition)), new Cesium.Matrix4());
        const camera = Cesium.Matrix4.multiplyByPoint(inverse, viewer.camera.positionWC, new Cesium.Cartesian3());
        if (Math.hypot(camera.x, camera.y) < 1) {
            return (Cesium.Math.toDegrees(viewer.camera.heading) + 180 + 360) % 360;
        }
        return (Cesium.Math.toDegrees(Math.atan2(camera.x, camera.y)) + 360) % 360;
    };
    const pegmanScale = createPegmanScale(() => {
        if (!markerPosition) {
            return null;
        }
        const canvas = viewer.scene.canvas;
        if (!canvas.clientWidth || !canvas.clientHeight) {
            return null;
        }
        const centre = new Cesium.Cartesian2(canvas.clientWidth / 2, canvas.clientHeight / 2);
        const ray = viewer.camera.getPickRay(centre);
        const focus = ray && viewer.scene.globe.pick(ray, viewer.scene) ||
            viewer.camera.pickEllipsoid(centre, viewer.scene.globe.ellipsoid) || cartesian(markerPosition);
        return viewer.camera.getPixelSize(new Cesium.BoundingSphere(focus, 0.01),
            canvas.clientWidth, canvas.clientHeight);
    }, (value) => {
        if (marker) {
            marker.scale = value;
            viewer.scene.requestRender();
        }
    });
    const setPegman = async(pair) => {
        markerPosition = pair;
        markerBearing = cameraFacingBearing();
        pegmanScale.positioned();
        if (!marker && !markerLoading) {
            markerLoading = Cesium.Model.fromGltfAsync({url: config.pegmanmodel, scene: viewer.scene,
                heightReference: Cesium.HeightReference.CLAMP_TO_GROUND,
                modelMatrix: markerModelMatrix(), scale: pegmanScale.current() || 1})
                .catch((problem) => {
                    markerLoading = null;
                    throw problem;
                });
        }
        if (!marker) {
            const loaded = await markerLoading;
            if (!marker) {
                marker = viewer.scene.primitives.add(loaded);
                marker.imageBasedLighting.imageBasedLightingFactor = new Cesium.Cartesian2(2.5, 1);
                if (marker.ready) {
                    updateMarkerAnimation();
                } else {
                    marker.readyEvent.addEventListener(updateMarkerAnimation);
                }
            }
        }
        marker.modelMatrix = markerModelMatrix();
        pegmanScale.reapply();
        viewer.scene.requestRender();
    };
    viewer.camera.moveEnd.addEventListener(() => pegmanScale.zoomEnd());
    viewer.scene.preRender.addEventListener(updateMarkerAnimation);
    const handler = new Cesium.ScreenSpaceEventHandler(viewer.scene.canvas);
    handler.setInputAction((event) => {
        if (touchGuard.shouldIgnoreClick()) {
            return;
        }
        const pair = position(event.position);
        if (!pair) {
            return;
        }
        if (mode === 'draw') {
            drawing.push(pair);
            callbacks.draftchange(drawing.length);
            render();
            return;
        }
        const picked = viewer.scene.pick(event.position)?.id;
        if (mode === 'navigate' && popup.isNear(hostPixel(event.position), 40, markerPosition)) {
            popup.show(markerPosition);
            return;
        }
        if (mode === 'edit' && Number(picked?.editorStage) === Number(selected)) {
            return;
        }
        if (picked?.editorStage) {
            callbacks.select(Number(picked.editorStage), Number(picked.editorPolygon));
        } else if (mode === 'navigate') {
            setPegman(pair).catch(callbacks.error);
            popup.show(pair);
            callbacks.position(pair);
        }
    }, Cesium.ScreenSpaceEventType.LEFT_CLICK);
    handler.setInputAction(() => {
        finish();
    }, Cesium.ScreenSpaceEventType.RIGHT_CLICK);
    handler.setInputAction((event) => {
        if (mode !== 'edit') {
            return;
        }
        const picked = viewer.scene.pick(event.position)?.id;
        if (picked?.editorVertex !== undefined) {
            dragVertex = {index: picked.editorVertex, ringindex: picked.editorRing};
            viewer.scene.screenSpaceCameraController.enableInputs = false;
            showInsertion(null);
            return;
        }
        const target = insertionAt(event.position);
        if (target) {
            const stage = getSelected();
            polygons(stage.geometry)[selectedPolygon][target.ringIndex].splice(target.insertionIndex, 0,
                target.coordinates);
            dragVertex = {index: target.insertionIndex, ringindex: target.ringIndex};
            viewer.scene.screenSpaceCameraController.enableInputs = false;
            showInsertion(null);
            callbacks.geometry(stage);
            render();
        }
    }, Cesium.ScreenSpaceEventType.LEFT_DOWN);
    handler.setInputAction((event) => {
        if (dragVertex === null) {
            showInsertion(insertionAt(event.endPosition));
            return;
        }
        const pair = position(event.endPosition);
        if (!pair) {
            return;
        }
        const stage = getSelected();
        const ring = polygons(stage.geometry)[selectedPolygon][dragVertex.ringindex];
        ring[dragVertex.index] = pair;
        ring[ring.length - 1] = [...ring[0]];
        callbacks.geometry(stage);
        render();
    }, Cesium.ScreenSpaceEventType.MOUSE_MOVE);
    handler.setInputAction(() => {
        dragVertex = null;
        viewer.scene.screenSpaceCameraController.enableInputs = true;
    }, Cesium.ScreenSpaceEventType.LEFT_UP);
    viewer.scene.canvas.addEventListener('pointerleave', () => showInsertion(null));
    viewer.camera.moveStart.addEventListener(() => showInsertion(null));
    return {
        setRoad(items) {
            popup.close();
            showInsertion(null);
            stages.items = items;
            render();
        },
        setSelection(id, index = 0) {
            showInsertion(null);
            selected = id;
            selectedPolygon = index;
            render();
        },
        setMode(next) {
            popup.close();
            showInsertion(null);
            mode = next;
            drawing = [];
            callbacks.draftchange(0);
            render();
        },
        fit(items) {
            const box = extent(items);
            if (box) {
                viewer.camera.flyTo({destination: Cesium.Rectangle.fromDegrees(...box), duration: 0.7});
            }
        },
        focusStage(item) {
            const box = extent([item]);
            if (box) {
                viewer.camera.flyTo({destination: Cesium.Rectangle.fromDegrees(...box), duration: 0.8});
            }
        },
        flyTo(pair) {
            viewer.camera.flyTo({destination: Cesium.Cartesian3.fromDegrees(pair[0], pair[1], 2000)});
        },
        resize() {
            viewer.resize();
        },
        finish,
    };
};
