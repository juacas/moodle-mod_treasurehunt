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
 * Select a clip from the shared Pegman GLB, including its search sequences.
 *
 * @module mod_treasurehunt/pegman_animation_state
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @param {Object} state Current game and navigation state.
 * @returns {string} Name of the glTF animation clip.
 */
export const selectClip = (state) => {
    if (state.confused) {
        return 'Desorientado';
    }
    if (state.finished || state.inside) {
        return 'Descansando';
    }
    if (state.heading) {
        return state.moving ? 'buscando_brújula' : 'Apuntando_Flecha';
    }
    if (state.distanceHint) {
        return 'buscando';
    }
    if (state.visibleTarget) {
        return state.moving ? 'Caminando_Apuntando' : 'Apuntando';
    }
    return state.moving ? 'Caminando' : 'Descansando';
};

/**
 * A new failed location attempt is represented by a new attempt ID and an unsolved geometry.
 *
 * @param {number} previousAttemptId Last attempt ID before the request.
 * @param {Object} response Progress service response.
 * @param {Object} action Submitted action.
 * @returns {boolean} Whether the latest submitted location or QR was incorrect.
 */
export const failedLocationAttempt = (previousAttemptId, response, action) => {
    if ((!action.location && !action.qrtext) || Number(response.attemptid) === Number(previousAttemptId)) {
        return false;
    }
    const latest = (response.attempts?.features || []).find((feature) =>
        Number(feature.id) === Number(response.attemptid));
    return latest?.properties?.geometrysolved === false || latest?.properties?.geometrysolved === 0;
};
