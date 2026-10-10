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
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * Fullscreen control shared by the Moodle 4 and 5 map editors.
 *
 * @module mod_treasurehunt/editor_fullscreen
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Allow the editor to fill the screen while keeping Moodle dialogs visible.
 *
 * @param {Object} map OpenLayers map that needs a resize after layout changes.
 */
export default function init(map) {
    const editor = document.getElementById('treasurehunt-editor');
    const button = document.getElementById('togglefullscreen');
    if (!editor || !button) {
        return;
    }
    const icon = button.querySelector('i');
    let expanded = false;
    let requestedFullscreen = false;
    let requestSerial = 0;

    /** Refresh the OpenLayers viewport after the browser has applied the new layout. */
    const resizeMap = () => {
        window.requestAnimationFrame(() => map.updateSize());
        window.setTimeout(() => map.updateSize(), 120);
    };

    /**
     * Apply the editor layout and the accessible button state.
     *
     * @param {boolean} active Whether the editor fills the screen.
     */
    const setExpanded = (active) => {
        expanded = active;
        editor.classList.toggle('is-fullscreen', active);
        document.body.classList.toggle('treasurehunt-editor-fullscreen', active);
        const label = button.dataset[active ? 'restoreLabel' : 'maximizeLabel'];
        button.setAttribute('aria-pressed', String(active));
        button.setAttribute('aria-label', label);
        button.title = label;
        icon.classList.toggle('fa-arrows-alt', !active);
        icon.classList.toggle('fa-compress', active);
        button.blur();
        resizeMap();
    };

    button.addEventListener('click', async() => {
        if (expanded) {
            requestSerial++;
            setExpanded(false);
            if (requestedFullscreen && document.fullscreenElement === document.documentElement) {
                try {
                    await document.exitFullscreen();
                } catch (error) {
                    // The CSS layout has already been restored.
                }
            }
            requestedFullscreen = false;
            return;
        }
        const currentRequest = ++requestSerial;
        setExpanded(true);
        if (document.documentElement.requestFullscreen && !document.fullscreenElement) {
            try {
                await document.documentElement.requestFullscreen();
                if (currentRequest !== requestSerial || !expanded) {
                    await document.exitFullscreen();
                    return;
                }
                requestedFullscreen = true;
                resizeMap();
            } catch (error) {
                // CSS fullscreen remains available when the API is denied.
            }
        }
    });

    document.addEventListener('fullscreenchange', () => {
        if (requestedFullscreen && !document.fullscreenElement) {
            requestedFullscreen = false;
            setExpanded(false);
        } else if (expanded) {
            resizeMap();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && expanded && !document.fullscreenElement) {
            setExpanded(false);
        }
    });
}
