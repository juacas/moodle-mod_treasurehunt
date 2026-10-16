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
 * Shared screen-space geometry calculations for the geographic editors.
 *
 * @module mod_treasurehunt/edit2_geometry
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Find a ring segment near the pointer, excluding existing vertices.
 *
 * @param {Array<Array<Array<number>>>} rings Polygon rings in longitude and latitude.
 * @param {Function} project Convert a geographic pair to canvas x and y.
 * @param {{x: number, y: number}} pixel Pointer position in canvas pixels.
 * @param {number} tolerance Maximum distance to a segment in pixels.
 * @param {number} vertexRadius Radius around an existing vertex in pixels.
 * @returns {{ringIndex: number, insertionIndex: number, coordinates: Array<number>}|null} Nearest segment.
 */
export const nearestSegment = (rings, project, pixel, tolerance = 12, vertexRadius = 10) => {
    const projected = rings.map((ring) => ring.map(project));
    const valid = (point) => point && Number.isFinite(point.x) && Number.isFinite(point.y);
    for (const ring of projected) {
        if (ring.slice(0, -1).some((point) => valid(point) &&
                Math.hypot(pixel.x - point.x, pixel.y - point.y) <= vertexRadius)) {
            return null;
        }
    }
    let nearest = null;
    let distance = tolerance;
    projected.forEach((ring, ringIndex) => {
        for (let index = 0; index < ring.length - 1; index++) {
            const start = ring[index];
            const end = ring[index + 1];
            if (!valid(start) || !valid(end)) {
                continue;
            }
            const dx = end.x - start.x;
            const dy = end.y - start.y;
            const lengthSquared = dx * dx + dy * dy;
            if (!lengthSquared) {
                continue;
            }
            const fraction = Math.max(0, Math.min(1,
                ((pixel.x - start.x) * dx + (pixel.y - start.y) * dy) / lengthSquared));
            const gap = Math.hypot(pixel.x - start.x - fraction * dx,
                pixel.y - start.y - fraction * dy);
            if (gap <= distance) {
                distance = gap;
                const from = rings[ringIndex][index];
                const to = rings[ringIndex][index + 1];
                nearest = {ringIndex, insertionIndex: index + 1,
                    coordinates: [from[0] + (to[0] - from[0]) * fraction,
                        from[1] + (to[1] - from[1]) * fraction]};
            }
        }
    });
    return nearest;
};
