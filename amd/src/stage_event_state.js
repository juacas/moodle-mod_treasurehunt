// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Shared stage event classification for all treasurehunt players.
 *
 * @module mod_treasurehunt/stage_event_state
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const STAGE_EVENTS = new Set(['unlocked', 'failedanswer', 'failedlocation']);

/**
 * Resolve the stage event represented by a common user-progress response.
 *
 * The server's latest explicit stage event takes precedence. `stageunlocked`
 * supports responses that report an unlock without an event name; request
 * details are only used as a fallback for errors that have no stage event.
 *
 * @param {Object} response User-progress service response.
 * @param {Object} [action] Request that produced the response.
 * @param {boolean} [failedAttempt] Whether location-attempt tracking found a failure.
 * @returns {string} One of the stage event names, or an empty string.
 */
export const classifyStageEvent = (response = {}, action = {}, failedAttempt = false) => {
    if (STAGE_EVENTS.has(response.stageevent)) {
        return response.stageevent;
    }
    if (response.stageunlocked) {
        return 'unlocked';
    }
    if (failedAttempt || (action.location && Number(response.status?.code || 0) !== 0)) {
        return 'failedlocation';
    }
    if (action.selectedanswerid && Number(response.status?.code || 0) !== 0) {
        return 'failedanswer';
    }
    return '';
};
