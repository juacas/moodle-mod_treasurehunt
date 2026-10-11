// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Shared place search UI for the geographic players.
 *
 * @module mod_treasurehunt/location_search
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Attach a submit-driven OpenStreetMap Nominatim search to a player.
 *
 * @param {HTMLFormElement} form Search form.
 * @param {HTMLInputElement} input Search field.
 * @param {HTMLElement} results Result list.
 * @param {boolean} disabled Whether the activity hides the location search control.
 * @param {Function} flyTo Callback receiving longitude and latitude.
 * @param {Object} labels Localized labels.
 */
export const setup = (form, input, results, disabled, flyTo, labels) => {
    if (disabled) {
        form.hidden = true;
        return;
    }
    form.hidden = false;
    input.placeholder = labels.searchplaceholder;
    let lastSearch = 0;

    form.addEventListener('submit', async(event) => {
        event.preventDefault();
        if (Date.now() - lastSearch < 1000) {
            return;
        }
        lastSearch = Date.now();
        const query = input.value.trim();
        if (!query) {
            return;
        }
        results.replaceChildren();
        const status = document.createElement('div');
        status.className = 'cessium-search-status';
        status.textContent = labels.searching;
        results.appendChild(status);
        results.hidden = false;
        input.setAttribute('aria-expanded', 'true');

        try {
            const params = new URLSearchParams({format: 'jsonv2', limit: '5', q: query});
            const response = await fetch('https://nominatim.openstreetmap.org/search?' + params.toString(), {
                headers: {Accept: 'application/json'},
            });
            if (!response.ok) {
                throw new Error('Place search failed: ' + response.status);
            }
            const places = await response.json();
            results.replaceChildren();
            if (!places.length) {
                status.textContent = labels.searchempty;
                results.appendChild(status);
                return;
            }
            places.forEach((place) => {
                const result = document.createElement('button');
                result.type = 'button';
                result.className = 'cessium-search-result';
                result.setAttribute('role', 'option');
                result.textContent = place.display_name;
                result.addEventListener('click', () => {
                    flyTo(Number(place.lon), Number(place.lat));
                    results.hidden = true;
                    input.setAttribute('aria-expanded', 'false');
                });
                results.appendChild(result);
            });
            const attribution = document.createElement('a');
            attribution.className = 'cessium-search-attribution';
            attribution.href = 'https://www.openstreetmap.org/copyright';
            attribution.target = '_blank';
            attribution.rel = 'noopener noreferrer';
            attribution.textContent = labels.searchattribution;
            results.appendChild(attribution);
        } catch (error) {
            status.textContent = labels.searcherror;
            results.replaceChildren(status);
        }
    });
    input.addEventListener('focus', () => {
        if (results.childElementCount) {
            results.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }
    });
    document.addEventListener('click', (event) => {
        if (!form.contains(event.target)) {
            results.hidden = true;
            input.setAttribute('aria-expanded', 'false');
        }
    });
};
