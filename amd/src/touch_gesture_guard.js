// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Prevent completed touch gestures from also being handled as map clicks.
 *
 * @module mod_treasurehunt/touch_gesture_guard
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Track touch pointers and identify pinch or pan gestures that must not place a marker.
 *
 * @param {HTMLElement} target Map canvas.
 * @returns {Object} Gesture guard methods.
 */
export const setup = (target) => {
    const pointers = new Map();
    let ignoreclickuntil = 0;
    let pinchstarted = false;

    const suppressFor = (duration = 800) => {
        ignoreclickuntil = Math.max(ignoreclickuntil, Date.now() + duration);
    };

    const down = (event) => {
        if (event.pointerType !== 'touch') {
            return;
        }
        pointers.set(event.pointerId, {x: event.clientX, y: event.clientY, moved: false});
        if (pointers.size > 1) {
            pinchstarted = true;
            suppressFor(1200);
        }
    };

    const move = (event) => {
        const pointer = pointers.get(event.pointerId);
        if (!pointer) {
            return;
        }
        if (Math.hypot(event.clientX - pointer.x, event.clientY - pointer.y) > 12) {
            pointer.moved = true;
            if (pointers.size > 1 || pinchstarted) {
                suppressFor(1200);
            } else {
                suppressFor(650);
            }
        }
    };

    const up = (event) => {
        const pointer = pointers.get(event.pointerId);
        if (pointer?.moved) {
            suppressFor(pinchstarted ? 1200 : 650);
        }
        pointers.delete(event.pointerId);
        if (!pointers.size) {
            pinchstarted = false;
        }
    };

    if (window.PointerEvent) {
        target.addEventListener('pointerdown', down, true);
        target.addEventListener('pointermove', move, true);
        target.addEventListener('pointerup', up, true);
        target.addEventListener('pointercancel', up, true);
    } else {
        target.addEventListener('touchstart', (event) => {
            if (event.touches.length > 1) {
                pinchstarted = true;
                suppressFor(1200);
            }
        }, {capture: true, passive: true});
        target.addEventListener('touchmove', (event) => {
            if (event.touches.length > 1 || pinchstarted) {
                suppressFor(1200);
            }
        }, {capture: true, passive: true});
        target.addEventListener('touchend', (event) => {
            if (pinchstarted) {
                suppressFor(1200);
            }
            if (!event.touches.length) {
                pinchstarted = false;
            }
        }, {capture: true, passive: true});
    }

    return {
        /** @returns {boolean} Whether the current click should be ignored. */
        shouldIgnoreClick: () => Date.now() < ignoreclickuntil,
        suppressFor,
    };
};
