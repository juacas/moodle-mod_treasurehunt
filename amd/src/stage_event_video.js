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
 * Resolve the configured video for a stage success or failure.
 *
 * @module mod_treasurehunt/stage_event_video
 * @copyright 2026 Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @author Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Return the mini video URL for a stage event, or null when disabled.
 *
 * @param {string} animation Activity animation choice.
 * @param {string} stageevent Stage event identifier.
 * @returns {string|null} Video URL.
 */
export const stageVideoUrl = (animation, stageevent) => {
    if (!['chest', 'safe'].includes(animation)) {
        return null;
    }
    if (stageevent !== 'unlocked' && stageevent !== 'failedanswer' && stageevent !== 'failedlocation') {
        return null;
    }
    const result = stageevent === 'unlocked' ? 'unlocked' : 'locked';
    return animation === 'chest' ? `pix/bootstrap/chest_${result}_small.webm` : `pix/safe_${result}_small.webm`;
};
