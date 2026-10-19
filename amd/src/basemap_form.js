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
 * Keep the activity's base-map selector focused on the chosen player.
 *
 * @module mod_treasurehunt/basemap_form
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @author Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Hide incompatible choices while leaving their selected state intact.
 */
export const init = () => {
    const player = document.getElementById('id_playerstyle');
    const maps = document.getElementById('id_allowedbasemaps');
    if (!player || !maps) {
        return;
    }

    let byPlayer;
    try {
        byPlayer = JSON.parse(maps.dataset.playerBasemaps || '{}');
    } catch (error) {
        return;
    }

    /** Refresh visible choices after the player changes. */
    const update = () => {
        const visible = new Set(byPlayer[player.value] || []);
        let count = 0;
        Array.from(maps.options).forEach((option) => {
            const supported = visible.has(option.value);
            option.hidden = !supported;
            option.style.display = supported ? '' : 'none';
            if (supported) {
                count++;
            }
        });
        maps.size = Math.min(8, Math.max(1, count));
    };

    player.addEventListener('change', update);
    update();
};
