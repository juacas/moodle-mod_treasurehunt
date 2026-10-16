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
 * Match the players' 100-pixel pegman calibration and zoom transition.
 *
 * @module mod_treasurehunt/edit2_pegman_scale
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const HEIGHT_PIXELS = 100;
const HEIGHT_METRES = 1.7;
const ZOOM_FACTOR = 2;
const SHAKE_MS = 500;
const SHAKE_HZ = 10;
const BOUNCE_MS = 2000;

/**
 * @param {Function} measure Return metres per CSS pixel at the current map focus.
 * @param {Function} apply Set the physical model scale and request rendering.
 * @returns {Object} Position and zoom controls.
 */
export const createPegmanScale = (measure, apply) => {
    let reference = null;
    let value = null;
    let frame = null;
    /** Stop the previous transition when the marker is placed again. */
    const stop = () => {
        if (frame !== null) {
            window.cancelAnimationFrame(frame);
            frame = null;
        }
    };
    /** @param {number} next Physical scale. */
    const set = (next) => {
        value = next;
        apply(value);
    };
    /** @returns {number|null} A usable ground scale. */
    const measured = () => {
        const metresPerPixel = measure();
        return Number.isFinite(metresPerPixel) && metresPerPixel > 0 ? metresPerPixel : null;
    };
    return {
        /** Recalibrate immediately when the pegman is placed. */
        positioned() {
            const metresPerPixel = measured();
            if (metresPerPixel === null) {
                return;
            }
            stop();
            reference = metresPerPixel;
            set(HEIGHT_PIXELS * metresPerPixel / HEIGHT_METRES);
        },
        /** Animate only after zooming beyond twice the last calibrated view scale. */
        zoomEnd() {
            if (reference === null || value === null) {
                return;
            }
            const metresPerPixel = measured();
            if (metresPerPixel === null || (metresPerPixel <= reference * ZOOM_FACTOR &&
                    metresPerPixel >= reference / ZOOM_FACTOR)) {
                return;
            }
            reference = metresPerPixel;
            const target = HEIGHT_PIXELS * metresPerPixel / HEIGHT_METRES;
            stop();
            if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                set(target);
                return;
            }
            const from = value;
            const started = performance.now();
            const tick = (now) => {
                const elapsed = Math.min(now - started, SHAKE_MS + BOUNCE_MS);
                if (elapsed < SHAKE_MS) {
                    const phase = elapsed / SHAKE_MS;
                    const shake = 0.09 * Math.sin(Math.PI * phase) *
                        Math.sin(2 * Math.PI * SHAKE_HZ * elapsed / 1000);
                    set(from * (1 + shake));
                } else {
                    const phase = (elapsed - SHAKE_MS) / BOUNCE_MS;
                    const remaining = phase - 1;
                    const easing = 1 + 2.5 * remaining ** 3 + 1.5 * remaining ** 2;
                    set(elapsed === SHAKE_MS + BOUNCE_MS ? target :
                        Math.max(0.0001, from + (target - from) * easing));
                }
                frame = elapsed < SHAKE_MS + BOUNCE_MS ? window.requestAnimationFrame(tick) : null;
            };
            frame = window.requestAnimationFrame(tick);
        },
        /** Apply the latest scale after the player rebuilds its model. */
        reapply() {
            if (value !== null) {
                apply(value);
            }
        },
        /** @returns {number|null} The latest physical model scale. */
        current() {
            return value;
        },
    };
};
