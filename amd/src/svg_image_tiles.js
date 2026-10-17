// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Load and render georeferenced SVG images as map tiles.
 * @author Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @module mod_treasurehunt/svg_image_tiles
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const MAX_LATITUDE = 85.05112878;

/**
 * Check whether an uploaded custom image is an SVG.
 *
 * @param {Object} mapping Custom map config.
 * @returns {boolean} Whether the image is an SVG.
 */
export const isSvgImage = (mapping) => {
    const mime = mapping?.custombackgroundmimetype || '';
    const url = mapping?.custombackgroundurl || '';
    return mime.toLowerCase() === 'image/svg+xml' || /\.svg(?:[?#]|$)/i.test(url);
};

/**
 * Load the source image, preserving the SVG document for per-tile rendering.
 *
 * @param {Object} mapping Custom map config.
 * @returns {Promise<HTMLImageElement>} Loaded source image.
 */
export const loadSvgImage = async(mapping) => {
    const response = await fetch(mapping.custombackgroundurl, {credentials: 'same-origin'});
    if (!response.ok) {
        throw new Error('Could not fetch the uploaded SVG map (' + response.status + ').');
    }
    const blob = await response.blob();
    if (blob.type && !blob.type.toLowerCase().includes('svg')) {
        throw new Error('The uploaded map is not an SVG image.');
    }
    const objecturl = URL.createObjectURL(blob);
    const image = new Image();
    image.src = objecturl;
    try {
        await new Promise((resolve, reject) => {
            if (image.complete && image.naturalWidth) {
                resolve();
                return;
            }
            image.onload = resolve;
            image.onerror = () => reject(new Error('The uploaded SVG map could not be decoded.'));
        });
        return image;
    } finally {
        URL.revokeObjectURL(objecturl);
    }
};

/**
 * Create a canvas renderer that transforms the uploaded image into EPSG:3857 map tiles.
 *
 * @param {HTMLImageElement} image Uploaded image.
 * @param {Object} mapping Custom map config.
 * @param {number} tileSize Tile dimensions in pixels.
 * @returns {Function} Render one x/y/z tile to a canvas.
 */
export const createTileRenderer = (image, mapping, tileSize = 256) => {
    const corners = Array.isArray(mapping.imagecorners) && mapping.imagecorners.length === 4 ?
        mapping.imagecorners : [
            [mapping.bbox[0], mapping.bbox[3]],
            [mapping.bbox[2], mapping.bbox[3]],
            [mapping.bbox[2], mapping.bbox[1]],
            [mapping.bbox[0], mapping.bbox[1]],
        ];
    const toWorld = ([longitude, latitude], zoom) => {
        const worldPixels = tileSize * Math.pow(2, zoom);
        const safeLatitude = Math.max(-MAX_LATITUDE, Math.min(MAX_LATITUDE, Number(latitude)));
        const x = (Number(longitude) + 180) / 360 * worldPixels;
        const sin = Math.sin(safeLatitude * Math.PI / 180);
        const y = (0.5 - Math.log((1 + sin) / (1 - sin)) / (4 * Math.PI)) * worldPixels;
        return [x, y];
    };
    return (x, y, zoom) => {
        const canvas = document.createElement('canvas');
        canvas.width = tileSize;
        canvas.height = tileSize;
        const context = canvas.getContext('2d');
        const [northwest, northeast, , southwest] = corners.map((corner) => toWorld(corner, zoom));
        const originX = x * tileSize;
        const originY = y * tileSize;
        context.setTransform(
            (northeast[0] - northwest[0]) / image.naturalWidth,
            (northeast[1] - northwest[1]) / image.naturalWidth,
            (southwest[0] - northwest[0]) / image.naturalHeight,
            (southwest[1] - northwest[1]) / image.naturalHeight,
            northwest[0] - originX,
            northwest[1] - originY
        );
        context.drawImage(image, 0, 0, image.naturalWidth, image.naturalHeight);
        return canvas;
    };
};

/**
 * Rasterize an SVG to a requested image resolution for Mapbox's image source.
 *
 * @param {HTMLImageElement} image Loaded SVG image.
 * @param {number} width Output width in pixels.
 * @param {number} height Output height in pixels.
 * @param {number} maxDimension Maximum canvas dimension supported by the browser.
 * @returns {string} Raster PNG data URL.
 */
export const renderSvgToDataUrl = (image, width, height, maxDimension = 8192) => {
    const scale = Math.min(maxDimension / image.naturalWidth, maxDimension / image.naturalHeight,
        Math.max(1, width / image.naturalWidth, height / image.naturalHeight));
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.ceil(image.naturalWidth * scale));
    canvas.height = Math.max(1, Math.ceil(image.naturalHeight * scale));
    const context = canvas.getContext('2d');
    context.drawImage(image, 0, 0, canvas.width, canvas.height);
    return canvas.toDataURL('image/png');
};
