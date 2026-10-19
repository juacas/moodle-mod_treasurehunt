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
 * A bounded tile cache for the GPX heatmap.
 *
 * @module mod_treasurehunt/cached_heatmap
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @author Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const TILE_SIZE = 256;
// Bound the extra canvas cache to roughly 16 MiB, regardless of the area visited.
const MAX_TILES = 64;
const COLORS = ['#00f', '#0ff', '#0f0', '#ff0', '#f00'];

/**
 * Make a canvas with the requested dimensions.
 *
 * @param {number} width Canvas width.
 * @param {number} height Canvas height.
 * @returns {HTMLCanvasElement} The canvas.
 */
const canvasOfSize = (width, height) => {
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    return canvas;
};

/**
 * Build the same colour ramp used by the OpenLayers heatmap layer.
 *
 * @returns {Uint8ClampedArray} A 256-colour lookup table.
 */
const colourRamp = () => {
    const context = canvasOfSize(1, 256).getContext('2d');
    const gradient = context.createLinearGradient(0, 0, 0, 256);
    COLORS.forEach((colour, index) => gradient.addColorStop(index / (COLORS.length - 1), colour));
    context.fillStyle = gradient;
    context.fillRect(0, 0, 1, 256);
    return context.getImageData(0, 0, 1, 256).data;
};

/**
 * Create an image of the heatmap point kernel at one tile resolution.
 *
 * @param {number} radius Radius in map units.
 * @param {number} blur Blur in map units.
 * @param {number} resolution Map units per pixel.
 * @returns {{canvas: HTMLCanvasElement, halfSize: number}} The point kernel.
 */
const pointKernel = (radius, blur, resolution) => {
    const radiusPixels = radius / resolution;
    const blurPixels = blur / resolution;
    const size = Math.max(2, Math.ceil(2 * (radiusPixels + blurPixels + 1)));
    const canvas = canvasOfSize(size, size);
    const context = canvas.getContext('2d');
    const halfSize = size / 2;
    // OpenLayers draws each point with a blurred shadow before colourising alpha.
    const shadowOffset = halfSize + radiusPixels + 1;
    context.shadowOffsetX = shadowOffset;
    context.shadowOffsetY = shadowOffset;
    context.shadowBlur = blurPixels;
    context.shadowColor = '#000';
    context.beginPath();
    context.arc(halfSize - shadowOffset, halfSize - shadowOffset, radiusPixels, 0, Math.PI * 2);
    context.fill();
    return {canvas, halfSize};
};

/**
 * Create a heatmap layer backed by a bounded cache of map tiles.
 *
 * @param {Object} ol OpenLayers 4 API.
 * @param {string} title Name shown in the layer selector.
 * @param {number} radius Point radius in EPSG:3857 map units.
 * @param {number} blur Point blur in EPSG:3857 map units.
 * @returns {{layer: Object, setPoints: Function}} The layer and data updater.
 */
export const create = (ol, title, radius, blur) => {
    const tiles = new Map();
    const kernels = new Map();
    const ramp = colourRamp();
    let points = [];
    let revision = 0;

    const source = new ol.source.XYZ({
        projection: 'EPSG:3857',
        maxZoom: 19,
        wrapX: false,
        cacheSize: MAX_TILES,
        tileUrlFunction: (coordinate) => coordinate ? 'heatmap:' + revision + ':' + coordinate.join('/') : undefined,
        tileLoadFunction: (tile) => {
            tile.getImage().src = getTile(tile.getTileCoord()).toDataURL('image/png');
        }
    });
    const grid = source.getTileGrid();
    const layer = new ol.layer.Tile({title, source, opacity: 0.7});

    /**
     * Read a tile and promote it in the least-recently-used cache.
     * @param {string} key Tile key.
     * @returns {HTMLCanvasElement|undefined} Cached tile.
     */
    const readTile = (key) => {
        const canvas = tiles.get(key);
        if (canvas) {
            tiles.delete(key);
            tiles.set(key, canvas);
        }
        return canvas;
    };

    /**
     * Store a tile without retaining an unbounded geographic surface.
     * @param {string} key Tile key.
     * @param {HTMLCanvasElement} canvas Rendered tile.
     * @returns {HTMLCanvasElement} Rendered tile.
     */
    const storeTile = (key, canvas) => {
        tiles.set(key, canvas);
        if (tiles.size > MAX_TILES) {
            tiles.delete(tiles.keys().next().value);
        }
        return canvas;
    };

    /**
     * Reuse four already rendered, more detailed child tiles when possible.
     * @param {number[]} coordinate OpenLayers tile coordinate.
     * @returns {HTMLCanvasElement|null} Downsampled tile, or null.
     */
    const fromChildren = (coordinate) => {
        const [zoom, x, y] = coordinate;
        if (zoom >= 19) {
            return null;
        }
        // In OpenLayers 4, tile Y is negative and grows towards the north.
        const childKeys = [
            [zoom + 1, 2 * x, 2 * y + 1], [zoom + 1, 2 * x + 1, 2 * y + 1],
            [zoom + 1, 2 * x, 2 * y], [zoom + 1, 2 * x + 1, 2 * y]
        ].map((child) => child.join('/'));
        if (!childKeys.every((key) => tiles.has(key))) {
            return null;
        }
        const canvas = canvasOfSize(TILE_SIZE, TILE_SIZE);
        const context = canvas.getContext('2d');
        childKeys.forEach((key, index) => {
            context.drawImage(readTile(key), (index % 2) * 128, Math.floor(index / 2) * 128, 128, 128);
        });
        return canvas;
    };

    /**
     * Render one tile from the loaded GPX coordinates.
     * @param {number[]} coordinate OpenLayers tile coordinate.
     * @returns {HTMLCanvasElement} Heatmap tile.
     */
    const renderTile = (coordinate) => {
        const extent = grid.getTileCoordExtent(coordinate);
        const resolution = (extent[2] - extent[0]) / TILE_SIZE;
        const halo = radius + blur;
        const canvas = canvasOfSize(TILE_SIZE, TILE_SIZE);
        const context = canvas.getContext('2d');
        let kernel = kernels.get(coordinate[0]);
        if (!kernel) {
            kernel = pointKernel(radius, blur, resolution);
            kernels.set(coordinate[0], kernel);
        }
        points.forEach((point) => {
            if (point[0] < extent[0] - halo || point[0] > extent[2] + halo ||
                    point[1] < extent[1] - halo || point[1] > extent[3] + halo) {
                return;
            }
            const x = (point[0] - extent[0]) / resolution - kernel.halfSize;
            const y = (extent[3] - point[1]) / resolution - kernel.halfSize;
            context.drawImage(kernel.canvas, x, y);
        });
        const image = context.getImageData(0, 0, TILE_SIZE, TILE_SIZE);
        for (let offset = 0; offset < image.data.length; offset += 4) {
            const alpha = image.data[offset + 3];
            if (alpha) {
                image.data[offset] = ramp[alpha * 4];
                image.data[offset + 1] = ramp[alpha * 4 + 1];
                image.data[offset + 2] = ramp[alpha * 4 + 2];
            }
        }
        context.putImageData(image, 0, 0);
        return canvas;
    };

    /**
     * Return a cached tile, a downsampled tile, or a newly rendered tile.
     * @param {number[]} coordinate OpenLayers tile coordinate.
     * @returns {HTMLCanvasElement} Heatmap tile.
     */
    const getTile = (coordinate) => {
        const key = coordinate.join('/');
        return readTile(key) || storeTile(key, fromChildren(coordinate) || renderTile(coordinate));
    };

    /**
     * Invalidate tiles only when the actual GPX point sequence changes.
     * @param {number[][]} nextPoints Loaded GPX coordinates.
     */
    const setPoints = (nextPoints) => {
        if (points.length === nextPoints.length && nextPoints.every((point, index) =>
            point[0] === points[index][0] && point[1] === points[index][1])) {
            return;
        }
        points = nextPoints.map((point) => [point[0], point[1]]);
        tiles.clear();
        revision++;
        source.refresh();
    };

    return {layer, setPoints};
};
