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
 * Accessible Bootstrap tooltips for the activity summary cards.
 *
 * @module mod_treasurehunt/view_tooltips_core
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Attach hover, focus and click controls to the summary cards.
 *
 * @param {Function} Tooltip Bootstrap tooltip constructor for this Moodle version.
 */
export const initTooltips = (Tooltip) => {
    const cards = document.querySelectorAll('.treasurehunt-activity-summary [data-treasurehunt-tooltip]');
    if (!cards.length) {
        return;
    }

    let active = null;
    const instances = new Map();

    /** Hide the currently visible tooltip. */
    const hide = () => {
        if (active) {
            instances.get(active).hide();
            active = null;
        }
    };

    /**
     * Show a card's tooltip and close any other open tooltip.
     *
     * @param {HTMLElement} card Card to describe.
     */
    const show = (card) => {
        if (active !== card) {
            hide();
            active = card;
            instances.get(card).show();
        }
    };

    cards.forEach((card) => {
        instances.set(card, new Tooltip(card, {
            trigger: 'manual',
            container: 'body',
            placement: 'top',
            title: () => card.getAttribute('data-treasurehunt-tooltip'),
        }));
        card.addEventListener('pointerenter', (event) => {
            if (event.pointerType === 'mouse' || event.pointerType === 'pen') {
                show(card);
            }
        });
        card.addEventListener('pointerleave', (event) => {
            if ((event.pointerType === 'mouse' || event.pointerType === 'pen') && active === card) {
                hide();
            }
        });
        card.addEventListener('focus', () => show(card));
        card.addEventListener('blur', () => {
            if (active === card) {
                hide();
            }
        });
        card.addEventListener('click', (event) => {
            if (event.isTrusted) {
                show(card);
            }
        });
    });

    document.addEventListener('pointerdown', (event) => {
        if (active && !active.contains(event.target)) {
            hide();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            hide();
        }
    });
};
