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
 * Fullscreen behaviour shared by the three game players.
 *
 * @module mod_treasurehunt/player_fullscreen
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @author Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Initialise native fullscreen on the first player interaction. */
export const init = () => {
    const player = document.getElementById('cesium-player') || document.querySelector('.play-container');
    const button = player?.querySelector('.treasurehunt-player-fullscreen-toggle');
    if (!player || !button) {
        return;
    }
    if (!document.fullscreenEnabled || !document.documentElement.requestFullscreen) {
        button.hidden = true;
        return;
    }

    /** Notify each map library that the available viewport has changed. */
    const resizeMap = () => {
        window.requestAnimationFrame(() => window.dispatchEvent(new Event('resize')));
        window.setTimeout(() => window.dispatchEvent(new Event('resize')), 120);
    };

    /** Keep the button in sync when Escape or the browser exits fullscreen. */
    const syncButton = () => {
        const active = document.fullscreenElement === document.documentElement;
        const label = button.dataset[active ? 'exitLabel' : 'enterLabel'];
        button.setAttribute('aria-pressed', String(active));
        button.setAttribute('aria-label', label);
        button.title = label;
        button.querySelector('i')?.classList.toggle('fa-expand', !active);
        button.querySelector('i')?.classList.toggle('fa-compress', active);
        resizeMap();
    };

    /** Request fullscreen while the browser still has the user's activation. */
    const enter = () => {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(() => {
                // The CSS layout still fills the viewport if fullscreen is denied.
            });
        }
    };

    /**
     * Use the first game interaction to enter native fullscreen.
     * @param {MouseEvent} event Player click event.
     */
    const enterOnInteraction = (event) => {
        const target = event.target instanceof Element ? event.target : null;
        if (target?.closest('a[href], .treasurehunt-player-fullscreen-toggle')) {
            return;
        }
        player.removeEventListener('click', enterOnInteraction, true);
        enter();
    };

    player.addEventListener('click', enterOnInteraction, true);
    button.addEventListener('click', () => {
        player.removeEventListener('click', enterOnInteraction, true);
        if (document.fullscreenElement === document.documentElement) {
            document.exitFullscreen().catch(() => {
                // The browser keeps control of native fullscreen state.
            });
        } else {
            enter();
        }
    });
    document.addEventListener('fullscreenchange', syncButton);
    syncButton();
};
