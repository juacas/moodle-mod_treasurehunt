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
 * Coordinates and Street View popup shared by the 3D geographic editors.
 *
 * @module mod_treasurehunt/edit2_pegman_popup
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Format an angle like the OpenLayers editor's coordinate popup.
 *
 * @param {number} angle Latitude or longitude.
 * @param {boolean} latitude Whether this is latitude.
 * @returns {string} Degrees, minutes, seconds, and cardinal direction.
 */
const degreesMinutesSeconds = (angle, latitude) => {
    const totalSeconds = Math.round(Math.abs(angle) * 3600);
    const degrees = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor(totalSeconds % 3600 / 60);
    const seconds = totalSeconds % 60;
    let direction = angle < 0 ? 'W' : 'E';
    if (latitude) {
        direction = angle < 0 ? 'S' : 'N';
    }
    return degrees + '°' + minutes + '′' + seconds + '″' + direction;
};

/**
 * @param {HTMLElement} host Existing map editor element.
 * @param {Object} config Localised labels and icon URL.
 * @param {Function} project Project longitude and latitude into host pixels.
 * @returns {Object} Popup controls.
 */
export const createPopup = (host, config, project) => {
    const popup = document.createElement('div');
    popup.className = 'ol-popup edit2-pegman-popup';
    popup.hidden = true;
    const closer = document.createElement('button');
    closer.type = 'button';
    closer.className = 'ol-popup-closer';
    closer.setAttribute('aria-label', config.closelabel);
    const content = document.createElement('div');
    popup.append(closer, content);
    host.appendChild(popup);
    let coordinates = null;
    let timer = null;
    let frame = null;

    /** Position the popup while the globe or map moves. */
    const update = () => {
        if (!coordinates) {
            return;
        }
        const point = project(coordinates);
        const visible = point && Number.isFinite(point.x) && Number.isFinite(point.y) &&
            point.x >= 0 && point.y >= 0 && point.x <= host.clientWidth && point.y <= host.clientHeight;
        popup.style.visibility = visible ? 'visible' : 'hidden';
        if (visible) {
            popup.style.left = point.x + 'px';
            popup.style.top = point.y + 'px';
        }
        frame = window.requestAnimationFrame(update);
    };
    /** Close and stop tracking the position. */
    const close = () => {
        coordinates = null;
        popup.hidden = true;
        window.clearTimeout(timer);
        if (frame !== null) {
            window.cancelAnimationFrame(frame);
            frame = null;
        }
    };
    /** Close automatically after the same delay as the OpenLayers popup. */
    const scheduleClose = () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(close, 2000);
    };
    closer.addEventListener('click', close);
    popup.addEventListener('mouseenter', () => window.clearTimeout(timer));
    popup.addEventListener('mouseleave', scheduleClose);
    return {
        /** @param {Array<number>} pair Longitude and latitude. */
        show(pair) {
            coordinates = [...pair];
            content.replaceChildren();
            const link = document.createElement('a');
            link.target = 'street';
            link.rel = 'noopener noreferrer';
            link.href = 'https://maps.google.com/?cbll=' + pair[1] + ',' + pair[0] +
                '&cbp=12,20.09,,0,5&layer=c';
            const icon = document.createElement('img');
            icon.src = config.pegmanicon;
            icon.width = 16;
            icon.alt = '';
            link.append(icon, document.createTextNode(config.pegmanlabel));
            const detail = document.createElement('code');
            detail.textContent = degreesMinutesSeconds(pair[1], true) + ' ' +
                degreesMinutesSeconds(pair[0], false);
            content.append(link, document.createElement('br'), detail);
            popup.hidden = false;
            if (frame === null) {
                update();
            }
            scheduleClose();
        },
        close,
        /**
         * @param {{x: number, y: number}} pixel Click position in host pixels.
         * @param {number} radius Pegman hit radius in pixels.
         * @param {Array<number>|null} pair Current pegman position.
         * @returns {boolean} Whether the click landed on the pegman.
         */
        isNear(pixel, radius, pair) {
            if (!pair) {
                return false;
            }
            const point = project(pair);
            return Boolean(point && Math.hypot(pixel.x - point.x, pixel.y - point.y) <= radius);
        },
    };
};
