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
 * Preview the stage-event videos beside the activity setting.
 *
 * @module mod_treasurehunt/stage_animation_preview
 * @copyright 2026 Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @author Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add the success and failure video previews to the animation selector.
 */
export const init = () => {
    const select = document.getElementById('id_stageanimation');
    if (!select) {
        return;
    }

    let sources;
    try {
        sources = JSON.parse(select.dataset.previews || '{}');
    } catch (error) {
        return;
    }

    const control = document.createElement('div');
    control.className = 'th-stage-animation-control';
    select.parentNode.insertBefore(control, select);
    control.appendChild(select);

    const previews = document.createElement('div');
    previews.className = 'th-stage-animation-previews';
    const videos = {};
    [['unlocked', select.dataset.successLabel], ['locked', select.dataset.failureLabel]].forEach(([state, label]) => {
        const card = document.createElement('figure');
        card.className = 'th-stage-animation-preview';
        const video = document.createElement('video');
        video.muted = true;
        video.loop = true;
        video.autoplay = true;
        video.playsInline = true;
        video.preload = 'metadata';
        video.setAttribute('aria-hidden', 'true');
        const caption = document.createElement('figcaption');
        caption.textContent = label || '';
        card.append(video, caption);
        previews.appendChild(card);
        videos[state] = video;
    });
    control.appendChild(previews);

    /** Update the two previews when the teacher changes the selection. */
    const update = () => {
        const selected = sources[select.value];
        previews.hidden = !selected;
        Object.entries(videos).forEach(([state, video]) => {
            const source = selected?.[state];
            if (!source) {
                video.pause();
                video.removeAttribute('src');
                video.load();
            } else if (video.getAttribute('src') !== source) {
                video.src = source;
                video.load();
                video.play().catch(() => undefined);
            }
        });
    };

    select.addEventListener('change', update);
    update();
};
