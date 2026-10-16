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
 * Address suggestions for the map editor, without jQuery UI.
 *
 * @module mod_treasurehunt/addressautocomplete
 * @copyright Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @param {HTMLInputElement} input Address input.
 * @param {Function} search Function returning a jqXHR for the query.
 * @param {Function} select Callback receiving the selected address.
 * @returns {Object} Controls for clearing the input and suggestions.
 */
export default function initAddressAutocomplete(input, search, select) {
    if (!input) {
        return {clear: () => {}};
    }
    const menu = document.createElement('div');
    menu.id = input.id + '-suggestions';
    menu.className = 'list-group treasurehunt-address-suggestions';
    menu.setAttribute('role', 'listbox');
    menu.hidden = true;
    document.body.appendChild(menu);

    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', menu.id);
    input.setAttribute('aria-expanded', 'false');

    const icon = input.parentElement.querySelector('.searchicon');
    let request = null;
    let timer = null;
    let requestId = 0;
    let results = [];
    let activeIndex = -1;
    let resultTerm = '';

    const setLoading = (loading) => {
        input.setAttribute('aria-busy', String(loading));
        if (icon) {
            icon.classList.toggle('fa-search', !loading);
            icon.classList.toggle('fa-spinner', loading);
            icon.classList.toggle('fa-spin', loading);
        }
    };

    const close = () => {
        menu.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        activeIndex = -1;
    };

    const cancel = () => {
        requestId++;
        window.clearTimeout(timer);
        timer = null;
        if (request) {
            request.abort();
            request = null;
        }
        setLoading(false);
    };

    const dismiss = () => {
        cancel();
        close();
    };

    const position = () => {
        if (menu.hidden) {
            return;
        }
        const rect = input.getBoundingClientRect();
        const width = Math.min(Math.max(rect.width, 320), window.innerWidth - 16);
        menu.style.width = width + 'px';
        menu.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8)) + 'px';
        const height = menu.offsetHeight;
        const below = window.innerHeight - rect.bottom;
        const above = rect.top;
        menu.style.top = below < Math.min(height, 180) && above > below
            ? Math.max(8, rect.top - height - 4) + 'px'
            : Math.min(window.innerHeight - height - 8, rect.bottom + 4) + 'px';
    };

    const setActive = (index) => {
        activeIndex = index;
        Array.from(menu.children).forEach((option, optionIndex) => {
            const selected = optionIndex === activeIndex;
            option.classList.toggle('active', selected);
            option.setAttribute('aria-selected', String(selected));
        });
        const option = menu.children[activeIndex];
        if (option) {
            input.setAttribute('aria-activedescendant', option.id);
            if (option.offsetTop < menu.scrollTop) {
                menu.scrollTop = option.offsetTop;
            } else if (option.offsetTop + option.offsetHeight > menu.scrollTop + menu.clientHeight) {
                menu.scrollTop = option.offsetTop + option.offsetHeight - menu.clientHeight;
            }
        }
    };

    const choose = (index) => {
        const result = results[index];
        if (!result) {
            return;
        }
        dismiss();
        input.value = result.display_name;
        select(result);
    };

    const render = (items, term) => {
        results = Array.isArray(items) ? items.filter(item => item && item.display_name) : [];
        resultTerm = term;
        menu.replaceChildren();
        if (!results.length) {
            close();
            return;
        }
        const fragment = document.createDocumentFragment();
        results.forEach((result, index) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'list-group-item list-group-item-action';
            option.id = menu.id + '-' + index;
            option.setAttribute('role', 'option');
            option.textContent = result.display_name;
            option.addEventListener('pointerdown', event => event.preventDefault());
            option.addEventListener('mouseenter', () => setActive(index));
            option.addEventListener('click', () => choose(index));
            fragment.appendChild(option);
        });
        menu.appendChild(fragment);
        menu.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        setActive(0);
        position();
    };

    const requestSuggestions = (immediate = false) => {
        const term = input.value.trim();
        dismiss();
        if (term.length < 4) {
            results = [];
            resultTerm = '';
            return;
        }
        timer = window.setTimeout(() => {
            const currentRequest = ++requestId;
            setLoading(true);
            request = search(term);
            request.done(data => {
                if (currentRequest === requestId && input.value.trim() === term) {
                    render(data, term);
                }
            }).fail(() => {
                if (currentRequest === requestId) {
                    close();
                }
            }).always(() => {
                if (currentRequest === requestId) {
                    request = null;
                    setLoading(false);
                }
            });
        }, immediate ? 0 : 500);
    };

    input.addEventListener('input', () => requestSuggestions());
    input.addEventListener('click', () => {
        if (input.value.trim().length >= 4) {
            if (resultTerm === input.value.trim() && results.length) {
                menu.hidden = false;
                input.setAttribute('aria-expanded', 'true');
                setActive(0);
                position();
            } else {
                requestSuggestions(true);
            }
        }
    });
    input.addEventListener('keydown', event => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            if (menu.hidden && results.length && resultTerm === input.value.trim()) {
                menu.hidden = false;
                input.setAttribute('aria-expanded', 'true');
                position();
            }
            if (!menu.hidden) {
                event.preventDefault();
                const direction = event.key === 'ArrowDown' ? 1 : -1;
                setActive((activeIndex + direction + results.length) % results.length);
            }
        } else if (event.key === 'Enter' && !menu.hidden) {
            event.preventDefault();
            choose(activeIndex < 0 ? 0 : activeIndex);
        } else if (event.key === 'Escape' || event.key === 'Tab') {
            dismiss();
        }
    });
    input.addEventListener('blur', () => window.setTimeout(() => {
        if (!menu.contains(document.activeElement)) {
            dismiss();
        }
    }, 100));
    document.addEventListener('pointerdown', event => {
        if (event.target !== input && !menu.contains(event.target)) {
            dismiss();
        }
    });
    window.addEventListener('resize', position);
    window.addEventListener('scroll', position, true);

    return {
        clear: () => {
            dismiss();
            results = [];
            resultTerm = '';
            input.value = '';
            input.dispatchEvent(new Event('input', {bubbles: true}));
        },
    };
}
