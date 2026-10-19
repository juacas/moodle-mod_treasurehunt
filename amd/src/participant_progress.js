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
 * Live participant progress strip shared by all treasurehunt players.
 *
 * @module mod_treasurehunt/participant_progress
 * @author Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @copyright 2026 Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import ajax from 'core/ajax';
import {get_strings as getStrings} from 'core/str';

const POLL_INTERVAL = 15000;
const stateKeys = ['participantstate_failed', 'participantstate_passed', 'participantstate_pending'];

/**
 * Create and update the progress strip when enabled by the activity setting.
 *
 * @param {Object} config Player settings.
 */
export const init = async(config) => {
    const containers = config.containerIds ? config.containerIds.map((id) => document.getElementById(id)).filter(Boolean) :
        [config.containerId ? document.getElementById(config.containerId) :
            document.getElementById('treasurehunt-participant-progress')].filter(Boolean);
    if (!containers.length || !config?.enabled) {
        return;
    }
    const [title, ...states] = await getStrings([
        {key: 'participantprogress', component: 'treasurehunt'},
        ...stateKeys.map((key) => ({key, component: 'treasurehunt'})),
    ]);
    const tracks = containers.map((container) => {
        container.setAttribute('aria-label', title);
        container.hidden = false;
        const track = document.createElement('div');
        track.className = 'th-participant-progress-track';
        track.setAttribute('role', 'list');
        container.replaceChildren(track);
        return {container, track};
    });
    const labels = {
        failed: states[0],
        passed: states[1],
        pending: states[2],
    };
    let polling = false;
    let stopped = false;

    /**
     * Create one participant marker.
     * @param {Object} participant Participant data from the service.
     * @param {HTMLElement} track Track containing this marker.
     * @returns {HTMLButtonElement}
     */
    const createMarker = (participant, track) => {
        const marker = document.createElement('button');
        marker.type = 'button';
        marker.className = 'th-participant-marker';
        marker.dataset.participantId = participant.id;
        marker.setAttribute('role', 'listitem');
        const avatar = document.createElement('span');
        avatar.className = 'th-participant-avatar';
        const initials = document.createElement('span');
        initials.className = 'th-participant-initials';
        initials.textContent = participant.initials;
        avatar.appendChild(initials);
        const tooltip = document.createElement('span');
        tooltip.className = 'th-participant-tooltip';
        tooltip.setAttribute('role', 'tooltip');
        marker.append(avatar, tooltip);
        marker.addEventListener('click', (event) => {
            event.stopPropagation();
            const open = marker.getAttribute('aria-expanded') === 'true';
            track.querySelectorAll('.th-participant-marker[aria-expanded="true"]').forEach((item) => {
                item.setAttribute('aria-expanded', 'false');
            });
            marker.setAttribute('aria-expanded', String(!open));
        });
        return marker;
    };

    /** @param {Object} data Participant snapshot. */
    const render = (data) => {
        tracks.forEach(({container, track}) => {
            const existing = new Map(Array.from(track.children).map((marker) => [marker.dataset.participantId, marker]));
            const roadid = Number(container.dataset.thRoadid || config.roadid || 0);
            const participants = (data.participants || []).filter((participant) => !roadid || participant.roadid === roadid);
            const width = Math.max(track.clientWidth, window.innerWidth, 1);
            const occupied = [];
            participants.forEach((participant) => {
                let marker = existing.get(participant.id);
                if (!marker) {
                    marker = createMarker(participant, track);
                    track.appendChild(marker);
                }
                existing.delete(participant.id);
                const avatar = marker.querySelector('.th-participant-avatar');
                let image = avatar.querySelector('img');
                if (participant.pictureurl) {
                    if (!image) {
                        image = document.createElement('img');
                        image.alt = '';
                        image.loading = 'lazy';
                        avatar.prepend(image);
                    }
                    if (image.src !== participant.pictureurl) {
                        image.src = participant.pictureurl;
                    }
                    image.hidden = false;
                } else if (image) {
                    image.hidden = true;
                }
                marker.classList.toggle('is-current', Boolean(participant.iscurrent));
                marker.classList.toggle('is-team', Boolean(participant.teamcolor));
                marker.dataset.state = participant.state;
                marker.style.setProperty('--th-team-color', participant.teamcolor || 'transparent');
                const tooltip = marker.querySelector('.th-participant-tooltip');
                tooltip.textContent = participant.name + ' · ' + (labels[participant.state] || labels.pending);
                marker.setAttribute('aria-label', tooltip.textContent);
                marker.title = participant.name;
                const total = Math.max(1, Number(participant.totalstages || 1));
                const position = Math.max(1, Math.min(total, Number(participant.position || 1)));
                const progress = total <= 1 ? 50 : 5 + ((position - 1) / (total - 1)) * 90;
                marker.style.left = progress + '%';
                const x = progress / 100 * width;
                let lane = occupied.findIndex((laneitems) => laneitems.every((otherx) => Math.abs(x - otherx) > 34));
                if (lane < 0) {
                    lane = occupied.length % 3;
                    occupied[lane] ||= [];
                }
                occupied[lane].push(x);
                marker.style.setProperty('--th-marker-lane', String(lane));
                marker.style.setProperty('--th-marker-top', ['50%', '22%', '78%'][lane]);
            });
            existing.forEach((marker) => marker.remove());
        });
    };

    const fetch = async() => {
        if (polling || stopped) {
            return;
        }
        polling = true;
        try {
            const response = await Promise.resolve(ajax.call([{
                methodname: 'mod_treasurehunt_participant_progress',
                args: {
                    treasurehuntid: Number(config.treasurehuntid),
                    previewroadid: Number(config.previewroadid || 0),
                    roadid: config.containerIds ? 0 : Number(config.roadid || config.previewroadid || 0),
                },
            }])[0]);
            if (!response.enabled) {
                containers.forEach((container) => {
                    container.hidden = true;
                });
                stopped = true;
                return;
            }
            containers.forEach((container) => container.closest('.cesium-player')?.classList.add('has-participant-progress'));
            render(response);
        } catch (error) {
            // The progress strip is auxiliary; its service must never interrupt the game player.
        } finally {
            polling = false;
        }
    };

    await fetch();
    const timer = window.setInterval(fetch, POLL_INTERVAL);
    document.addEventListener('click', (event) => {
        if (tracks.some(({track}) => track.contains(event.target))) {
            return;
        }
        tracks.forEach(({track}) => {
            track.querySelectorAll('.th-participant-marker[aria-expanded="true"]').forEach((marker) => {
                marker.setAttribute('aria-expanded', 'false');
            });
        });
    });
    window.addEventListener('pagehide', () => {
        stopped = true;
        window.clearInterval(timer);
    }, {once: true});
};

/**
 * Initialize every progress strip embedded in an activity summary page.
 */
export const init_page = () => {
    const instances = new Map();
    document.querySelectorAll('.th-participant-progress[data-th-treasurehuntid]').forEach((container) => {
        const treasurehuntid = Number(container.dataset.thTreasurehuntid);
        instances.set(treasurehuntid, [...(instances.get(treasurehuntid) || []), container.id]);
    });
    instances.forEach((containerIds, treasurehuntid) => {
        init({
            enabled: true,
            containerIds,
            treasurehuntid,
        });
    });
};
