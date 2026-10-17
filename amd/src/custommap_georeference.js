// This file is part of Treasurehunt for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Georeference an uploaded custom map image by applying affine transforms.
 *
 * @module mod_treasurehunt/custommap_georeference
 */
define(['jquery', 'mod_treasurehunt/ol'], function($, ol) {
    const mapProjection = 'EPSG:3857';
    const geographicProjection = 'EPSG:4326';
    let map;
    let image;
    let imageLayer;
    let handleSource;
    let corners = [];
    let dragType = null;
    let dragIndex = -1;
    let dragStart = null;
    let dragCorners = null;
    let dragPanInteraction = null;

    const dialog = () => document.getElementById('custommap-georeference-dialog');
    const field = (name) => document.querySelector(`[name="${name}"]`);
    const imageUrl = () => {
        const links = document.querySelectorAll('#fitem_id_custombackground a[href]');
        for (const link of links) {
            if (/\.(svg|png|jpe?g)(?:[?#]|$)/i.test(link.href)) {
                return link.href;
            }
        }
        return field('customimageurl')?.value || null;
    };
    const projectCorners = (points) => points.map((point) => ol.proj.transform(point, geographicProjection, mapProjection));
    const unprojectCorners = (points) => points.map((point) => ol.proj.transform(point, mapProjection, geographicProjection));
    const currentBBoxCorners = () => {
        const west = parseFloat(field('custommapminlon')?.value);
        const south = parseFloat(field('custommapminlat')?.value);
        const east = parseFloat(field('custommapmaxlon')?.value);
        const north = parseFloat(field('custommapmaxlat')?.value);
        if ([west, south, east, north].every(Number.isFinite) && west < east && south < north) {
            return [[west, north], [east, north], [east, south], [west, south]];
        }
        const center = ol.proj.transform([0, 0], geographicProjection, mapProjection);
        const half = 1200;
        return unprojectCorners([
            [center[0] - half, center[1] + half], [center[0] + half, center[1] + half],
            [center[0] + half, center[1] - half], [center[0] - half, center[1] - half],
        ]);
    };
    const getCorners = () => {
        try {
            const parsed = JSON.parse(field('customimagecorners')?.value || 'null');
            if (Array.isArray(parsed) && parsed.length === 4 && parsed.every((point) =>
                Array.isArray(point) && point.length === 2 && point.every(Number.isFinite))) {
                return parsed;
            }
        } catch (error) {
            // Use the bounding-box fields for an existing map made before affine corners were stored.
        }
        return currentBBoxCorners();
    };
    const updateLayer = () => {
        if (imageLayer) {
            if (corners.length === 4) {
                imageLayer.setExtent(ol.extent.boundingExtent(projectCorners(corners)));
            }
            imageLayer.getSource().changed();
        }
        if (handleSource && corners.length === 4) {
            const projected = projectCorners(corners);
            if (handleSource.getFeatures().length === 0) {
                projected.forEach((point) => {
                    handleSource.addFeature(new ol.Feature(new ol.geom.Point(point)));
                });
                return;
            }
            handleSource.getFeatures().forEach((feature, index) => {
                feature.getGeometry().setCoordinates(projected[index]);
            });
        }
    };
    const pointInPolygon = (point, polygon) => {
        let inside = false;
        for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
            const [xi, yi] = polygon[i];
            const [xj, yj] = polygon[j];
            const intersects = ((yi > point[1]) !== (yj > point[1])) &&
                (point[0] < (xj - xi) * (point[1] - yi) / (yj - yi) + xi);
            if (intersects) {
                inside = !inside;
            }
        }
        return inside;
    };
    const cornerAtPixel = (pixel) => {
        const projected = projectCorners(corners);
        for (let i = 0; i < projected.length; i++) {
            const cornerPixel = map.getPixelFromCoordinate(projected[i]);
            if (Math.hypot(pixel[0] - cornerPixel[0], pixel[1] - cornerPixel[1]) <= 14) {
                return i;
            }
        }
        return -1;
    };
    const imageAtPixel = (pixel) => {
        const polygon = projectCorners(corners).map((point) => map.getPixelFromCoordinate(point));
        return pointInPolygon(pixel, polygon);
    };
    const initMap = () => {
        if (map) {
            return;
        }
        const source = new ol.source.ImageCanvas({
            projection: mapProjection,
            ratio: 1,
            canvasFunction: (extent, resolution, pixelRatio, size) => {
                const canvas = document.createElement('canvas');
                // ImageCanvas already passes size in device pixels.
                canvas.width = Math.max(1, Math.round(size[0]));
                canvas.height = Math.max(1, Math.round(size[1]));
                if (!image || corners.length !== 4) {
                    return canvas;
                }
                const projected = projectCorners(corners);
                const [nw, ne, , sw] = projected;
                const toCanvas = (point) => [
                    (point[0] - extent[0]) / resolution * pixelRatio,
                    (extent[3] - point[1]) / resolution * pixelRatio,
                ];
                const p0 = toCanvas(nw);
                const px = toCanvas(ne);
                const py = toCanvas(sw);
                const a = (px[0] - p0[0]) / image.naturalWidth;
                const b = (px[1] - p0[1]) / image.naturalWidth;
                const c = (py[0] - p0[0]) / image.naturalHeight;
                const d = (py[1] - p0[1]) / image.naturalHeight;
                canvas.getContext('2d').setTransform(a, b, c, d, p0[0], p0[1]);
                canvas.getContext('2d').drawImage(image, 0, 0);
                return canvas;
            },
        });
        imageLayer = new ol.layer.Image({source, opacity: 0.78});
        handleSource = new ol.source.Vector();
        const handleLayer = new ol.layer.Vector({
            source: handleSource,
            style: new ol.style.Style({
                image: new ol.style.Circle({
                    radius: 7,
                    fill: new ol.style.Fill({color: '#ffdf00'}),
                    stroke: new ol.style.Stroke({color: '#212529', width: 2}),
                }),
            }),
        });
        map = new ol.Map({
            target: 'custommap-georeference-map',
            layers: [new ol.layer.Tile({source: new ol.source.OSM()}), imageLayer, handleLayer],
            view: new ol.View({projection: mapProjection, center: [0, 0], zoom: 15}),
        });
        map.getInteractions().forEach((interaction) => {
            if (interaction instanceof ol.interaction.DragPan) {
                dragPanInteraction = interaction;
            }
        });
        map.on('pointerdown', (event) => {
            if (!image || !event.pixel || event.originalEvent?.shiftKey) {
                return;
            }
            dragIndex = cornerAtPixel(event.pixel);
            if (dragIndex >= 0) {
                dragType = 'corner';
            } else if (imageAtPixel(event.pixel)) {
                dragType = 'image';
            } else {
                return;
            }
            dragStart = event.coordinate.slice();
            dragCorners = projectCorners(corners);
            if (dragPanInteraction) {
                dragPanInteraction.setActive(false);
            }
            event.preventDefault();
        });
        map.on('pointerdrag', (event) => {
            if (!dragType || !dragStart || !dragCorners) {
                return;
            }
            const dx = event.coordinate[0] - dragStart[0];
            const dy = event.coordinate[1] - dragStart[1];
            const next = dragCorners.map((point) => point.slice());
            if (dragType === 'image') {
                next.forEach((point) => {
                    point[0] += dx;
                    point[1] += dy;
                });
            } else {
                const selected = dragIndex;
                const opposite = (selected + 2) % 4;
                next[selected] = [dragCorners[selected][0] + dx, dragCorners[selected][1] + dy];
                [((selected + 1) % 4), ((selected + 3) % 4)].forEach((index) => {
                    next[index] = [dragCorners[index][0] + dx / 2, dragCorners[index][1] + dy / 2];
                });
                next[opposite] = dragCorners[opposite].slice();
            }
            corners = unprojectCorners(next);
            updateLayer();
        });
        map.on('pointerup', () => {
            if (dragPanInteraction) {
                dragPanInteraction.setActive(true);
            }
            dragType = null;
            dragIndex = -1;
            dragStart = null;
            dragCorners = null;
        });
        map.on('pointermove', (event) => {
            if (event.dragging || !image || !event.pixel) {
                return;
            }
            let cursor = 'grab';
            if (cornerAtPixel(event.pixel) >= 0) {
                cursor = 'crosshair';
            } else if (imageAtPixel(event.pixel)) {
                cursor = 'move';
            }
            map.getTargetElement().style.cursor = cursor;
        });
    };
    const loadImage = () => new Promise((resolve, reject) => {
        const url = imageUrl();
        if (!url) {
            reject(new Error('No uploaded image is available.'));
            return;
        }
        const loaded = new Image();
        loaded.onload = () => {
            image = loaded;
            resolve();
        };
        loaded.onerror = reject;
        loaded.src = url;
    });
    const open = async() => {
        const type = field('customlayerservicetype')?.value;
        const layertype = field('customlayertype')?.value;
        if (type !== 'image' || layertype === 'nongeographic') {
            return;
        }
        try {
            await loadImage();
        } catch (error) {
            return;
        }
        corners = getCorners();
        initMap();
        updateLayer();
        imageLayer.setOpacity(1 - parseFloat(document.getElementById('custommap-georeference-opacity').value));
        const projected = projectCorners(corners);
        dialog().showModal();
        window.requestAnimationFrame(() => {
            map.updateSize();
            map.getView().fit(ol.extent.boundingExtent(projected), {padding: [32, 32, 32, 32], maxZoom: 19});
            updateLayer();
        });
    };
    const fitImageToView = () => {
        if (!map || !map.getSize()) {
            return;
        }
        const extent = map.getView().calculateExtent(map.getSize());
        corners = unprojectCorners([
            [extent[0], extent[3]], [extent[2], extent[3]],
            [extent[2], extent[1]], [extent[0], extent[1]],
        ]);
        updateLayer();
    };
    const save = () => {
        field('customimagecorners').value = JSON.stringify(corners);
        const longitudes = corners.map((point) => point[0]);
        const latitudes = corners.map((point) => point[1]);
        field('custommapminlon').value = Math.min(...longitudes).toFixed(8);
        field('custommapmaxlon').value = Math.max(...longitudes).toFixed(8);
        field('custommapminlat').value = Math.min(...latitudes).toFixed(8);
        field('custommapmaxlat').value = Math.max(...latitudes).toFixed(8);
        dialog().close();
    };
    const init = () => {
        const openButton = document.getElementById('custommap-georeference-open');
        if (!openButton) {
            return;
        }
        openButton.addEventListener('click', open);
        document.getElementById('custommap-georeference-close').addEventListener('click', () => dialog().close());
        document.getElementById('custommap-georeference-cancel').addEventListener('click', () => dialog().close());
        document.getElementById('custommap-georeference-save').addEventListener('click', save);
        const updateButton = () => {
            const unavailable = !imageUrl() || field('customlayerservicetype')?.value !== 'image' ||
                field('customlayertype')?.value === 'nongeographic';
            openButton.disabled = unavailable;
            openButton.hidden = unavailable;
        };
        document.getElementById('custommap-georeference-opacity').addEventListener('input', (event) => {
            if (imageLayer) {
                imageLayer.setOpacity(1 - Number(event.target.value));
            }
        });
        document.getElementById('custommap-georeference-fit').addEventListener('click', fitImageToView);
        const fileManager = document.getElementById('fitem_id_custombackground');
        if (fileManager) {
            new MutationObserver(updateButton).observe(fileManager, {childList: true, subtree: true});
        }
        document.querySelectorAll('[name="customlayerservicetype"], [name="customlayertype"]').forEach((select) => {
            select.addEventListener('change', updateButton);
        });
        updateButton();
    };
    return {init};
});
