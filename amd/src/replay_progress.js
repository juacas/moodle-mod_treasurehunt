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
 * Calculate historic game progress for the GPX replay.
 *
 * @module mod_treasurehunt/replay_progress
 * @copyright 2026 Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @author Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Cumulative geometric distance of a GPX trace at a given timestamp.
 *
 * @param {Array<Object>} points Ordered points with cumulative distance.
 * @param {number} time Unix time in seconds.
 * @returns {number} Distance in metres.
 */
export const distanceAt = (points, time) => {
    let low = 0;
    let high = points.length;
    while (low < high) {
        const middle = Math.floor((low + high) / 2);
        if (points[middle].time <= time) {
            low = middle + 1;
        } else {
            high = middle;
        }
    }
    const index = low - 1;
    if (index < 0) {
        return 0;
    }
    if (index + 1 >= points.length || points[index + 1].time <= points[index].time) {
        return points[index].distance;
    }
    const next = points[index + 1];
    const ratio = Math.max(0, Math.min(1, (time - points[index].time) / (next.time - points[index].time)));
    return points[index].distance + ratio * (next.distance - points[index].distance);
};

/**
 * Sum each teammate's travelled distance within a stage interval.
 *
 * @param {Object} actor Participant or team with GPX members.
 * @param {number} start Interval start.
 * @param {number} end Interval end.
 * @returns {number} Distance in metres.
 */
export const distanceBetween = (actor, start, end) => actor.members.reduce((sum, entry) =>
    sum + Math.max(0, distanceAt(entry.points, end) - distanceAt(entry.points, start)), 0);

/**
 * Count completed stages and estimate travel toward the next stage's location.
 *
 * The partial stage uses GPX distance since the last newly completed stage.
 * The marker reaches the next stage position when a geometry attempt succeeds,
 * then stays there while its question or activity is pending. Failed attempts
 * do not reset the accumulated distance.
 * GPS proximity never counts as a successful attempt.
 *
 * @param {Object} actor Participant or team with attempts and GPX members.
 * @param {number} time Unix time in seconds.
 * @returns {Object} Normalized fraction, state and number of completed stages.
 */
export const progressAt = (actor, time) => {
    const completed = new Set();
    let previous = null;
    let lastAdvanceTime = actor.firstTrackTime;
    let arrivalTime = null;
    actor.attempts.forEach((attempt) => {
        if (attempt.time <= time) {
            previous = attempt;
            if (attempt.success && !completed.has(attempt.stageid)) {
                completed.add(attempt.stageid);
                lastAdvanceTime = attempt.time;
                arrivalTime = null;
            } else if (attempt.geometrysolved && !completed.has(attempt.stageid)) {
                arrivalTime = attempt.time;
            }
        }
    });
    const total = Math.max(1, actor.totalstages);
    const start = lastAdvanceTime;
    const nextArrival = actor.attempts.find((attempt) => attempt.time > time && attempt.geometrysolved &&
        !completed.has(attempt.stageid));
    const end = nextArrival ? nextArrival.time : actor.lastTrackTime;
    const whole = end > start ? distanceBetween(actor, start, end) : 0;
    const travelled = time > start ? distanceBetween(actor, start, Math.min(time, end)) : 0;
    // A known geometry arrival is the exact end of this stage interval.
    // Without one, keep the marker just short of the next stage.
    let partial = whole > 0 ? Math.min(nextArrival ? 1 : 0.95, travelled / whole) : 0;
    if (arrivalTime !== null) {
        partial = 1;
    }
    const stageindex = completed.size === 0 ? 0 : Math.min(total - 1, completed.size - 1 + partial);
    const fraction = total <= 1 ? 0.5 : stageindex / (total - 1);
    let status = 'pending';
    if (previous && completed.has(previous.stageid)) {
        status = 'passed';
    } else if (previous && (previous.penalty || !previous.geometrysolved)) {
        status = 'failed';
    }
    return {fraction, status, completed: completed.size};
};
