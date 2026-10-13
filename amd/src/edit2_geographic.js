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
 * Road and stage editor with native Cesium or Mapbox geometry tools.
 *
 * @module mod_treasurehunt/edit2_geographic
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import ajax from 'core/ajax';
import notification from 'core/notification';
import {add as addToast} from 'core/toast';
import {get_strings as getStrings} from 'core/str';
import initFullscreen from 'mod_treasurehunt/editor_fullscreen';
import {createCesium, createMapbox} from 'mod_treasurehunt/edit2_map';
import {setup as setupLocationSearch} from 'mod_treasurehunt/location_search';

/** Initialize the editor from the map element's data attributes. */
export const init = async() => {
    const mapedit = document.getElementById('mapedit');
    const config = JSON.parse(mapedit.dataset.editorConfig);
    const terms = ['stage', 'road', 'modify', 'remove', 'preview', 'areyousure', 'confirm', 'cancel',
        'removeroadwarning', 'removewarning', 'editorinvalidstage', 'errvalidroad', 'erremptystage',
        'editorstatussaved', 'editorstatusunsaved', 'editorroaddeleted', 'editorstagedeleted',
        'editorcopysuccess', 'editorcopycount', 'editorcopytarget', 'editorcopyexistingcount',
        'editorcopyappenddesc', 'editorcopyreplacedesc', 'editorcopysource', 'editorcopyfrom',
        'editorcopyempty', 'savewarning', 'searchlocation', 'editorclueshort', 'editorownclueshort',
        'edit2drawmapbox', 'edit2drawcesium', 'edit2dragvertex', 'edit2maplayers', 'edit2finishpolygon',
        'editorqrshort', 'editoroutofsequenceshort', 'editorpreviousshort', 'editorblockedshort',
        'editorwithoutgpsshort', 'editorenabled', 'editordisabled', 'editornone'];
    const translated = await getStrings(terms.map((key) => ({key, component: 'treasurehunt'})));
    const strings = Object.fromEntries(terms.map((key, index) => [key, translated[index]]));
    const element = (id) => document.getElementById(id);
    const canvas = document.createElement('div');
    canvas.id = 'edit2mapcanvas';
    mapedit.appendChild(canvas);
    const guidance = document.createElement('div');
    guidance.className = 'edit2-map-guidance';
    guidance.hidden = true;
    mapedit.appendChild(guidance);
    const finishButton = document.createElement('button');
    finishButton.type = 'button';
    finishButton.className = 'btn btn-primary btn-sm edit2-finish-polygon';
    finishButton.textContent = strings.edit2finishpolygon;
    finishButton.hidden = true;
    finishButton.disabled = true;
    mapedit.appendChild(finishButton);
    const stageList = document.createElement('ul');
    stageList.id = 'stagelist';
    stageList.className = 'list-group list-group-flush';
    stageList.setAttribute('role', 'listbox');
    stageList.setAttribute('aria-label', strings.stage);
    element('stagelistpanel').prepend(stageList);
    let roads = [];
    let road = null;
    let stage = null;
    let selectedPolygon = 0;
    let mode = 'navigate';
    let dirty = new Set();
    let saving = null;
    let map;
    let lockid = config.lockid;
    const storageKey = 'treasurehuntSelectedRoad:' + config.treasurehuntid;
    /** @param {string} url Local form URL. @returns {string} URL preserving this editor. */
    const editorFormUrl = (url) => url + (url.includes('?') ? '&' : '?') + 'editor=edit2&playertype=' +
        encodeURIComponent(config.playertype);
    document.addEventListener('treasurehunt:lockrenewed', (event) => {
        if (Number(event.detail.treasurehuntid) === config.treasurehuntid) {
            lockid = event.detail.lockid;
        }
    });

    /**
     * @param {string} method Service name.
     * @param {Object} args Service arguments.
     * @returns {Promise<Object>} Response.
     */
    const call = async(method, args) => ajax.call([{methodname: 'mod_treasurehunt_' + method, args}])[0];
    /** @param {Error} problem AJAX or map error. */
    const error = (problem) => {
        window.console.error('Treasure Hunt geographic editor', problem);
        notification.exception(problem);
    };
    /** @param {string} text Message. */
    const success = (text) => addToast(text, {type: 'success'});
    /** @param {string} text Message. */
    const warn = (text) => addToast(text, {type: 'warning'});
    /** @param {HTMLElement} dialog Modal element. */
    const showModal = (dialog) => {
        const previouslyFocused = document.activeElement;
        dialog.hidden = false;
        dialog.style.display = 'block';
        dialog.classList.add('show');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-hidden', 'false');
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show edit2-modal-backdrop';
        document.body.appendChild(backdrop);
        const close = () => {
            dialog.style.display = 'none';
            dialog.classList.remove('show');
            dialog.removeAttribute('aria-modal');
            dialog.setAttribute('aria-hidden', 'true');
            backdrop.remove();
            dialog.removeEventListener('keydown', onKeydown);
            previouslyFocused?.focus();
        };
        const onKeydown = (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                close();
            }
        };
        dialog.addEventListener('keydown', onKeydown);
        dialog.querySelector('button')?.focus();
        backdrop.addEventListener('click', close, {once: true});
        dialog.querySelectorAll('[data-bs-dismiss="modal"], [data-dismiss="modal"]').forEach((button) => {
            button.onclick = close;
        });
        dialog.edit2Close = close;
    };
    /** @param {Object} selected Stage record. @returns {boolean} Whether it has an editable polygon. */
    const hasGeometry = (selected) => Boolean(selected?.geometry?.coordinates?.some((rings) => rings[0]?.length));
    /** @param {Object} candidate Road. @returns {string} Validation message, if any. */
    const roadIssue = (candidate) => {
        const valid = candidate.stages.filter(hasGeometry).length;
        if (valid < 2) {
            return strings.errvalidroad;
        }
        return valid < candidate.stages.length ? strings.erremptystage : '';
    };
    /** @param {Object} selected Stage record. */
    const markDirty = (selected) => {
        if (!dirty.has(selected.id)) {
            warn(strings.editorstatusunsaved);
        }
        dirty.add(selected.id);
        element('savestage').disabled = false;
        updateStageValidation();
    };
    /** @param {string} next New map editing mode. */
    const setMode = (next) => {
        mode = next;
        map.setMode(mode);
        ['navmode', 'drawmode', 'editmode'].forEach((id) => {
            element(id).classList.toggle('selectedbutton', id === (next === 'navigate' ? 'navmode' : next + 'mode'));
            element(id).setAttribute('aria-pressed', String(id ===
                (next === 'navigate' ? 'navmode' : next + 'mode')));
        });
        guidance.hidden = next === 'navigate';
        finishButton.hidden = next !== 'draw';
        finishButton.disabled = true;
        if (next === 'draw') {
            guidance.textContent = strings[config.playertype === 'mapbox' ?
                'edit2drawmapbox' : 'edit2drawcesium'];
        } else {
            guidance.textContent = strings.edit2dragvertex;
        }
    };
    /**
     * @param {Object} selected Stage record.
     * @param {number} polygonindex Selected polygon index.
     * @param {boolean} fromList Whether the stage was selected from the list.
     */
    const chooseStage = (selected, polygonindex = 0, fromList = false) => {
        stage = selected;
        selectedPolygon = polygonindex;
        stageList.querySelectorAll('li').forEach((item) => {
            const active = Number(item.dataset.stageid) === Number(stage?.id);
            item.classList.toggle('ui-selected', active);
            item.setAttribute('aria-selected', String(active));
        });
        map.setSelection(stage?.id || null, selectedPolygon);
        if (fromList && stage && hasGeometry(stage)) {
            map.focusStage(stage);
        }
        element('drawmode').disabled = !stage || Boolean(road?.blocked);
        element('editmode').disabled = !stage || !hasGeometry(stage) || Boolean(road?.blocked);
        element('removefeature').disabled = !stage || !hasGeometry(stage) || Boolean(road?.blocked);
        setMode('navigate');
    };
    /** Mark empty stages and roads with the existing editor classes. */
    const updateStageValidation = () => {
        if (!road) {
            return;
        }
        stageList.querySelectorAll('li').forEach((item) => {
            const current = road.stages.find((candidate) => Number(candidate.id) === Number(item.dataset.stageid));
            const empty = !hasGeometry(current);
            item.classList.toggle('invalidstage', empty);
            item.title = empty ? strings.editorinvalidstage : '';
        });
        const tab = element('roadtab' + road.id);
        const issue = roadIssue(road);
        tab.classList.toggle('invalidroad', Boolean(issue));
        tab.title = issue;
        tab.querySelector('.treasurehunt-play-road').disabled = Boolean(issue);
    };
    /** @param {Object} item Stage. */
    const stageRow = (item) => {
        const row = document.createElement('li');
        row.className = 'list-group-item';
        row.dataset.stageid = item.id;
        row.setAttribute('stageid', String(item.id));
        row.setAttribute('stageposition', String(item.position));
        row.setAttribute('roadid', String(road.id));
        row.setAttribute('role', 'option');
        row.tabIndex = 0;
        const handle = document.createElement('div');
        handle.className = road.blocked ? 'nohandle validstage' : 'handle validstage';
        handle.innerHTML = '<i class="fa fa-' + (road.blocked ? 'lock' : 'arrows-v') +
            '" aria-hidden="true"></i>' +
            '<span class="sortable-number"></span>';
        handle.querySelector('span').textContent = item.position;
        row.appendChild(handle);
        const name = document.createElement('div');
        name.className = 'stagename';
        name.innerHTML = item.name;
        row.appendChild(name);
        const actions = document.createElement('div');
        actions.className = 'modifystage';
        const button = (icon, label, action, extraClass = '') => {
            const control = document.createElement('button');
            control.type = 'button';
            control.className = 'treasurehunt-icon-button ' + extraClass;
            control.setAttribute('aria-label', label);
            control.innerHTML = '<i class="fa fa-' + icon + '" aria-hidden="true"></i>';
            control.addEventListener('click', (event) => {
                event.stopPropagation();
                action();
            });
            actions.appendChild(control);
        };
        if (!road.blocked) {
            button('trash', strings.remove + ' ' + name.textContent, () => removeStage(item),
                'treasurehunt-delete-item');
        }
        button('pencil', strings.modify + ' ' + name.textContent,
            () => navigate(editorFormUrl('editstage.php?cmid=' + config.cmid + '&id=' + item.id)),
            'treasurehunt-edit-item');
        button('info-circle', strings.stage + ' ' + name.textContent, () => {
            element('stagecluemodaltitle').textContent = name.textContent;
            const body = element('stagecluemodalbody');
            body.replaceChildren();
            const appendClue = (label, html) => {
                const title = document.createElement('strong');
                title.textContent = label;
                const content = document.createElement('div');
                content.innerHTML = html || '';
                body.append(title, content);
            };
            appendClue(strings.editorclueshort, item.clue);
            appendClue(strings.editorownclueshort, item.ownclue);
            const statuses = document.createElement('div');
            statuses.className = 'treasurehunt-stage-statuses mt-3';
            const addStatus = (icons, label, enabled, detail) => {
                const status = document.createElement('div');
                status.className = 'treasurehunt-stage-status ' + (enabled ? 'is-active' : 'is-inactive');
                status.title = label + ': ' + detail;
                const symbols = document.createElement('div');
                symbols.className = 'treasurehunt-stage-status-icons';
                symbols.setAttribute('aria-hidden', 'true');
                icons.forEach((icon) => {
                    const symbol = document.createElement('i');
                    symbol.className = 'fa fa-' + icon;
                    symbols.appendChild(symbol);
                });
                const caption = document.createElement('span');
                caption.className = 'treasurehunt-stage-status-label';
                caption.textContent = label;
                const description = document.createElement('span');
                description.className = 'treasurehunt-stage-status-detail';
                description.textContent = detail;
                status.append(symbols, caption, description);
                statuses.appendChild(status);
            };
            const state = (enabled) => enabled ? strings.editorenabled : strings.editordisabled;
            addStatus(['qrcode'], strings.editorqrshort, item.hasqr, state(item.hasqr));
            addStatus(['random'], strings.editoroutofsequenceshort, item.discoveroutofsequence,
                state(item.discoveroutofsequence));
            addStatus(['window-maximize', 'arrow-right', 'lock'], strings.editorpreviousshort,
                Boolean(item.activitytoendname || item.hasquestion), item.activitytoendname || strings.editornone);
            addStatus(['lock', 'arrow-right', 'window-maximize'], strings.editorblockedshort,
                Boolean(item.inverserestrictions.length), item.inverserestrictions.join(' · ') || strings.editornone);
            addStatus(['mouse-pointer', 'hand-pointer-o'], strings.editorwithoutgpsshort,
                item.playstagewithoutmoving, state(item.playstagewithoutmoving));
            body.appendChild(statuses);
            showModal(element('stagecluemodal'));
        }, 'treasurehunt-info-item');
        row.appendChild(actions);
        row.addEventListener('click', () => chooseStage(item, 0, true));
        row.addEventListener('keydown', (event) => {
            if (event.target === row && (event.key === 'Enter' || event.key === ' ')) {
                event.preventDefault(); chooseStage(item, 0, true);
            }
        });
        if (!road.blocked) {
            row.draggable = true;
            row.addEventListener('dragstart', (event) => event.dataTransfer.setData('text/plain', String(item.id)));
            row.addEventListener('dragover', (event) => event.preventDefault());
            row.addEventListener('drop', (event) => {
                event.preventDefault();
                const source = road.stages.find((candidate) =>
                    Number(candidate.id) === Number(event.dataTransfer.getData('text/plain')));
                if (!source || source === item) {
                    return;
                }
                road.stages.splice(road.stages.indexOf(source), 1);
                road.stages.splice(road.stages.indexOf(item), 0, source);
                road.stages.forEach((candidate, index) => {
                    const position = road.stages.length - index;
                    if (candidate.position !== position) {
                        candidate.position = position;
                        markDirty(candidate);
                    }
                });
                renderStages();
            });
        }
        return row;
    };
    /** Update the left panel without changing its enclosing layout. */
    const renderStages = () => {
        stageList.replaceChildren(...road.stages.map(stageRow));
        element('stagelistpanel').classList.toggle('invisible', road.stages.length === 0);
        element('addstage').classList.toggle('highlightbutton', road.stages.length === 0);
        updateStageValidation();
        if (stage) {
            chooseStage(road.stages.find((candidate) => candidate.id === stage.id) || null);
        }
    };
    /** @param {Object} selected Road. */
    const chooseRoad = (selected) => {
        road = selected;
        stage = null;
        setMode('navigate');
        try {
            sessionStorage.setItem(storageKey, String(road.id));
        } catch (error) { /* Storage is optional. */ }
        element('roadlist').querySelectorAll('li').forEach((tab) => {
            const active = Number(tab.dataset.roadid) === Number(road.id);
            tab.classList.toggle('active', active);
            tab.classList.toggle('ui-selected', active);
            tab.setAttribute('aria-selected', String(active));
        });
        element('editorworkspace').setAttribute('aria-labelledby', 'roadtab' + road.id);
        element('addstage').disabled = Boolean(road.blocked);
        element('copystages').disabled = Boolean(road.blocked);
        renderStages();
        chooseStage(null);
        map.setRoad(road.stages);
        map.fit(road.stages);
    };
    /** Render road tabs and select the requested or remembered one. */
    const renderRoads = () => {
        const list = element('roadlist');
        list.replaceChildren();
        roads.forEach((item) => {
            const tab = document.createElement('li');
            tab.id = 'roadtab' + item.id;
            tab.dataset.roadid = item.id;
            tab.setAttribute('roadid', String(item.id));
            tab.setAttribute('blocked', String(item.blocked));
            tab.className = 'nav-item nav-link';
            const issue = roadIssue(item);
            tab.classList.toggle('invalidroad', Boolean(issue));
            tab.title = issue;
            tab.setAttribute('role', 'tab');
            tab.setAttribute('aria-controls', 'editorworkspace');
            tab.tabIndex = -1;
            const title = document.createElement('div');
            title.className = 'roadname';
            title.textContent = item.name;
            tab.appendChild(title);
            const actions = document.createElement('div');
            actions.className = 'modifyroad';
            const button = (icon, label, css, action) => {
                const control = document.createElement('button');
                control.type = 'button';
                control.className = css;
                control.setAttribute('aria-label', label);
                control.innerHTML = '<i class="fa fa-' + icon + '" aria-hidden="true"></i>';
                control.addEventListener('click', (event) => {
                    event.stopPropagation(); action();
                });
                actions.appendChild(control);
                return control;
            };
            const playButton = button('play', strings.preview + ' ' + item.name,
                'btn btn-sm btn-outline-success treasurehunt-play-road', () =>
                navigate('play.php?id=' + config.cmid + '&previewroadid=' + item.id +
                    '&playertype=' + encodeURIComponent(config.playertype)));
            playButton.append(document.createTextNode(' ' + strings.preview));
            playButton.disabled = Boolean(issue);
            button('trash', strings.remove, 'treasurehunt-icon-button treasurehunt-delete-item', () => removeRoad(item));
            button('pencil', strings.modify, 'treasurehunt-icon-button treasurehunt-edit-item', () =>
                navigate(editorFormUrl('editroad.php?cmid=' + config.cmid + '&id=' + item.id)));
            tab.appendChild(actions);
            tab.addEventListener('click', () => chooseRoad(item));
            tab.addEventListener('keydown', (event) => {
                if (event.target === tab && (event.key === 'Enter' || event.key === ' ')) {
                    event.preventDefault(); chooseRoad(item);
                }
            });
            list.appendChild(tab);
        });
        element('copystages').hidden = roads.length < 2;
        let wanted = config.selectedroadid;
        if (!wanted) {
            try {
                wanted = Number(sessionStorage.getItem(storageKey));
            } catch (error) { /* Optional. */ }
        }
        chooseRoad(roads.find((item) => Number(item.id) === Number(wanted)) || roads[0]);
    };
    /** Save modified stage geometry or position using the editor's existing service. */
    const save = async() => {
        if (saving) {
            return saving;
        }
        const stages = roads.flatMap((item) => item.stages).filter((item) => dirty.has(item.id));
        if (!stages.length) {
            return true;
        }
        if (stages.some((item) => !hasGeometry(item))) {
            warn(strings.editorinvalidstage);
            return false;
        }
        element('savestage').disabled = true;
        saving = call('update_stages', {treasurehuntid: config.treasurehuntid, lockid,
            stages: {type: 'FeatureCollection', features: stages.map((item) => ({
                type: 'Feature', id: Number(item.id), geometry: item.geometry,
                properties: {roadid: Number(item.roadid), stageposition: Number(item.position)},
            }))}}).then((response) => {
            if (response.status.code) {
                throw new Error(response.status.msg);
            }
            dirty.clear();
            success(strings.editorstatussaved);
            return true;
        }).catch((problem) => {
            element('savestage').disabled = false;
            error(problem);
            return false;
        }).finally(() => {
            saving = null;
        });
        return saving;
    };
    /** @param {string} url Next page. */
    const navigate = async(url) => {
        if (dirty.size && !await save()) {
            return;
        }
        window.location.assign(url);
    };
    /** @param {Object} item Stage to remove. */
    const removeStage = (item) => notification.confirm(strings.areyousure, strings.removewarning,
        strings.confirm, strings.cancel, async() => {
            try {
                const response = await call('delete_stage', {stageid: item.id,
                    treasurehuntid: config.treasurehuntid, lockid});
                if (response.status.code) {
                    throw new Error(response.status.msg);
                }
                road.stages = road.stages.filter((candidate) => candidate !== item);
                dirty.delete(item.id);
                road.stages.forEach((candidate, index) => {
                    candidate.position = road.stages.length - index;
                });
                stage = null;
                map.setRoad(road.stages);
                renderStages();
                success(strings.editorstagedeleted);
            } catch (problem) {
                error(problem);
            }
        });
    /** @param {Object} item Road to remove. */
    const removeRoad = (item) => notification.confirm(strings.areyousure, strings.removeroadwarning,
        strings.confirm, strings.cancel, async() => {
            try {
                const response = await call('delete_road', {roadid: item.id,
                    treasurehuntid: config.treasurehuntid, lockid});
                if (response.status.code) {
                    throw new Error(response.status.msg);
                }
                item.stages.forEach((candidate) => dirty.delete(candidate.id));
                roads = roads.filter((candidate) => candidate !== item);
                success(strings.editorroaddeleted);
                if (roads.length) {
                    renderRoads();
                } else {
                    window.location.assign(editorFormUrl('editroad.php?cmid=' + config.cmid));
                }
            } catch (problem) {
                error(problem);
            }
        });

    map = await (config.playertype === 'mapbox' ? createMapbox : createCesium)(config, {
        error,
        draftchange: (count) => {
            finishButton.disabled = count < 3;
        },
        position: (pair) => {
            mapedit.dataset.pegmanPosition = pair.join(',');
        },
        select: (id, polygonindex) => {
            const current = road.stages.find((item) => Number(item.id) === id);
            if (current) {
                chooseStage(current, polygonindex);
            }
        },
        polygon: (ring) => {
            if (!stage || road.blocked) {
                return;
            }
            stage.geometry.coordinates.push([ring]);
            markDirty(stage);
            map.setRoad(road.stages);
            map.setSelection(stage.id);
            element('editmode').disabled = false;
            element('removefeature').disabled = false;
            setMode('navigate');
        },
        geometry: (item) => markDirty(item),
    });
    initFullscreen({updateSize: () => map.resize()});
    finishButton.addEventListener('click', () => map.finish());
    const aside = element('editoraside');
    element('toggleleftpanel').addEventListener('click', (event) => {
        const workspace = element('editorworkspace');
        if (!workspace.classList.contains('is-collapsed')) {
            aside.style.height = aside.offsetHeight + 'px';
        } else {
            aside.style.height = '';
        }
        const collapsed = workspace.classList.toggle('is-collapsed');
        event.currentTarget.setAttribute('aria-expanded', String(!collapsed));
        const label = event.currentTarget.dataset[collapsed ? 'expandLabel' : 'collapseLabel'];
        event.currentTarget.setAttribute('aria-label', label);
        event.currentTarget.title = label;
        event.currentTarget.querySelector('i').classList.toggle('fa-angle-double-left', !collapsed);
        event.currentTarget.querySelector('i').classList.toggle('fa-angle-double-right', collapsed);
        window.setTimeout(() => map.resize(), 100);
    });
    element('navmode').disabled = false;
    element('navmode').addEventListener('click', () => setMode('navigate'));
    element('drawmode').addEventListener('click', () => setMode('draw'));
    element('editmode').addEventListener('click', () => setMode('edit'));
    element('removefeature').addEventListener('click', () => {
        if (!stage || !hasGeometry(stage)) {
            return;
        }
        if (stage.geometry.coordinates.length === 1) {
            warn(strings.editorinvalidstage);
            return;
        }
        notification.confirm(strings.areyousure, strings.removewarning, strings.confirm, strings.cancel, () => {
            stage.geometry.coordinates.splice(selectedPolygon, 1);
            selectedPolygon = 0;
            markDirty(stage);
            map.setRoad(road.stages);
            chooseStage(stage);
        });
    });
    element('savestage').addEventListener('click', save);
    element('addroad').addEventListener('click', () => navigate(editorFormUrl('editroad.php?cmid=' + config.cmid)));
    element('addstage').addEventListener('click', () => navigate(editorFormUrl('editstage.php?cmid=' + config.cmid +
        '&roadid=' + road.id)));
    element('copystages').addEventListener('click', () => {
        const options = element('copystagesroads');
        options.replaceChildren();
        roads.filter((item) => item !== road).forEach((item) => {
            const label = document.createElement('label');
            label.className = 'list-group-item list-group-item-action p-3 border rounded mb-2';
            const radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'copystagessource';
            radio.value = item.id;
            radio.className = 'form-check-input mr-2 me-2';
            label.append(radio, document.createTextNode(strings.editorcopyfrom + ' ' + item.name));
            options.appendChild(label);
        });
        element('copystagessummary').textContent = strings.editorcopysource;
        element('copystagessave').disabled = true;
        showModal(element('copystagesmodal'));
    });
    element('copystagesroads').addEventListener('change', () => {
        const sourceid = Number(element('copystagesroads').querySelector('input:checked')?.value);
        const source = roads.find((item) => Number(item.id) === sourceid);
        if (!source) {
            return;
        }
        element('copystagessummary').textContent = strings.editorcopycount + ' ' + source.stages.length +
            ' · ' + strings.editorcopytarget + ' ' + road.name + ' · ' +
            strings.editorcopyexistingcount + ' ' + road.stages.length;
        element('copystagessave').disabled = !source.stages.length;
    });
    element('copystagessave').addEventListener('click', async() => {
        const sourceid = Number(element('copystagesroads').querySelector('input:checked')?.value);
        if (!sourceid || !await save()) {
            return;
        }
        try {
            const response = await call('copy_stages', {treasurehuntid: config.treasurehuntid,
                sourceroadid: sourceid, targetroadid: Number(road.id),
                replaceexisting: element('copystagesreplace').checked, lockid});
            if (response.status?.code) {
                throw new Error(response.status.msg);
            }
            element('copystagesmodal').edit2Close();
            success(strings.editorcopysuccess);
            window.location.reload();
        } catch (problem) {
            error(problem);
        }
    });
    if (config.playertype === 'mapbox') {
        const form = document.createElement('form');
        form.className = 'edit2-search';
        const input = document.createElement('input');
        input.type = 'search';
        input.className = 'form-control form-control-sm';
        input.setAttribute('aria-label', strings.searchlocation);
        const results = document.createElement('div');
        results.className = 'cessium-search-results';
        results.hidden = true;
        form.append(input, results);
        mapedit.appendChild(form);
        setupLocationSearch(form, input, results, false, (longitude, latitude) => map.flyTo([longitude, latitude]), {
            searchplaceholder: strings.searchlocation, searching: config.searching,
            searchempty: config.searchempty, searcherror: config.searcherror,
            searchattribution: config.searchattribution,
        });
    }
    window.addEventListener('beforeunload', (event) => {
        if (dirty.size) {
            event.preventDefault(); event.returnValue = strings.savewarning;
        }
    });
    try {
        const response = await call('fetch_treasurehunt', {treasurehuntid: config.treasurehuntid});
        if (response.status.code) {
            throw new Error(response.status.msg);
        }
        roads = Object.values(response.treasurehunt.roads).map((item) => ({
            id: Number(item.id), name: item.name, blocked: Boolean(Number(item.blocked)),
            stages: (item.stages.features || []).map((feature) => ({
                id: Number(feature.id), roadid: Number(item.id),
                position: Number(feature.properties.stageposition),
                name: feature.properties.name, clue: feature.properties.clue,
                ownclue: feature.properties.ownclue,
                hasqr: Boolean(feature.properties.hasqr),
                discoveroutofsequence: Boolean(feature.properties.discoveroutofsequence),
                playstagewithoutmoving: Boolean(feature.properties.playstagewithoutmoving),
                activitytoendname: feature.properties.activitytoendname,
                hasquestion: Boolean(feature.properties.hasquestion),
                inverserestrictions: feature.properties.inverserestrictions || [],
                geometry: feature.geometry?.type === 'MultiPolygon' ? feature.geometry :
                    {type: 'MultiPolygon', coordinates: feature.geometry?.type === 'Polygon' ?
                        [feature.geometry.coordinates] : []},
            })).sort((a, b) => b.position - a.position),
        }));
        renderRoads();
        document.querySelector('.treasurehunt-editor-loader')?.classList.add('d-none');
    } catch (problem) {
        document.querySelector('.treasurehunt-editor-loader')?.classList.add('d-none');
        error(problem);
    }
};
