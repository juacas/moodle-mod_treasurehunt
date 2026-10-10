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
 * Temporary OpenData search layers in the treasure hunt map editor.
 *
 * @module mod_treasurehunt/opendata_editor
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {add as addToast} from 'core/toast';
import $ from 'jquery';

/**
 * Connect the search dialog to the OpenLayers editor.
 *
 * @param {Object} map Editor map.
 * @param {Object} ol OpenLayers namespace.
 * @param {Object} layerSwitcher Editor layer switcher.
 * @param {Object} modal Version-compatible Bootstrap modal instance.
 * @param {Object} editor Stage creation and editor lock callbacks.
 */
export default function init(map, ol, layerSwitcher, modal, editor) {
    const dialog = document.getElementById('opendatamodal');
    const openButton = document.getElementById('opendataopen');
    if (!dialog || !openButton) {
        return;
    }
    const status = document.getElementById('opendatastatus');
    status.style.whiteSpace = 'pre-line';
    const searchButton = document.getElementById('opendatasearch');
    const termInput = document.getElementById('opendataterm');
    const typeInputs = Array.from(dialog.querySelectorAll('input[name="opendatatype"]'));
    const sourceInputs = Array.from(dialog.querySelectorAll('input[name="opendatasource"]'));
    const loadMoreButton = document.getElementById('opendataloadmore');
    const addButton = document.getElementById('opendataadd');
    const replaceButton = document.getElementById('opendatareplace');
    const previewList = document.getElementById('opendatapreview');
    const sourceProgress = document.getElementById('opendatasourceprogress');
    const fieldLabels = JSON.parse(dialog.dataset.fieldLabels || '{}');
    const storageKey = 'treasurehuntOpenData:' + window.location.pathname + ':' +
        dialog.dataset.treasurehuntid + ':' + dialog.dataset.userid;
    const vectorSource = new ol.source.Vector();
    let openDataLayer = null;
    let jobs = [];
    let searchParameters = null;
    const seenItems = new Set();
    let pendingItems = [];
    let savedItems = [];
    let requestNumber = 0;
    const activeControllers = new Set();

    /** Stop outstanding requests before replacing or committing the pending results. */
    const cancelRequests = () => {
        requestNumber++;
        activeControllers.forEach((controller) => controller.abort());
        activeControllers.clear();
    };

    /**
     * Show search progress or a result without inserting service HTML.
     *
     * @param {string} message Message to show.
     * @param {string} kind Bootstrap alert type.
     */
    const showStatus = (message, kind) => {
        status.textContent = message;
        status.className = message ? 'alert alert-' + kind + ' small mb-0' : '';
    };

    /** Invalidate an in-flight search and discard results that were not saved. */
    const clearStatus = () => {
        cancelRequests();
        jobs = [];
        pendingItems = [];
        seenItems.clear();
        previewList.replaceChildren();
        sourceProgress.replaceChildren();
        loadMoreButton.hidden = true;
        addButton.disabled = true;
        replaceButton.disabled = true;
        searchButton.disabled = false;
        showStatus('', 'info');
    };

    /** Show the pending titles in the modal without changing the map layer. */
    const renderPreview = () => {
        previewList.replaceChildren();
        pendingItems.slice(0, 100).forEach((item) => {
            const row = document.createElement('li');
            row.className = 'list-group-item py-1';
            row.textContent = item.properties?.name || item.properties?.id || '';
            previewList.appendChild(row);
        });
    };

    /** Show the state and result count of each selected source during a batch. */
    const renderProgress = () => {
        sourceProgress.replaceChildren();
        sourceInputs.filter((input) => jobs.some((job) => job.source === input.value)).forEach((input) => {
            const sourceJobs = jobs.filter((job) => job.source === input.value);
            const active = sourceJobs.some((job) => job.state === 'active');
            const queued = sourceJobs.some((job) => job.state === 'queued');
            const failed = sourceJobs.some((job) => job.state === 'failed');
            const completed = sourceJobs.filter((job) => job.state === 'done' || job.state === 'failed').length;
            const count = sourceJobs.reduce((total, job) => total + (job.count || 0), 0);
            const row = document.createElement('div');
            row.className = 'd-flex flex-wrap align-items-center justify-content-between border rounded px-2 py-1 mb-1 small';
            const name = document.createElement('strong');
            name.textContent = input.closest('label').textContent.trim();
            const detail = document.createElement('span');
            detail.className = failed ? 'text-warning' : 'text-muted';
            if (active) {
                const spinner = document.createElement('span');
                spinner.className = 'spinner-border spinner-border-sm';
                spinner.style.marginRight = '0.25rem';
                spinner.setAttribute('aria-hidden', 'true');
                detail.appendChild(spinner);
            }
            const state = active ? dialog.dataset.sourceSearching : queued ? dialog.dataset.sourceWaiting :
                failed ? dialog.dataset.sourceFailed : dialog.dataset.sourceDone;
            detail.appendChild(document.createTextNode(state + ' · ' + completed + '/' + sourceJobs.length +
                ' · ' + count + ' ' + dialog.dataset.resultsLabel));
            row.append(name, detail);
            sourceProgress.appendChild(row);
        });
    };

    /**
     * Get the current map window in WGS84 degrees, clipped to world limits.
     *
     * @returns {Array<number>} West, south, east, north.
     */
    const visibleBounds = () => {
        const extent = map.getView().calculateExtent(map.getSize());
        const degrees = ol.proj.transformExtent(extent, map.getView().getProjection(), 'EPSG:4326');
        return [Math.max(-180, degrees[0]), Math.max(-90, degrees[1]),
            Math.min(180, degrees[2]), Math.min(90, degrees[3])];
    };

    // OpenLayers draws its own fixed-size circles. SVG icons without intrinsic dimensions can fill the map.
    const symbols = {
        all: ['#0b7285', '\uf14e'],
        historic: ['#8a5a44', '\uf19c'], archaeology: ['#8a5a44', '\uf19c'],
        monument: ['#8a5a44', '\uf19c'], period: ['#8a5a44', '\uf19c'], ww1: ['#8a5a44', '\uf19c'],
        industrial: ['#596579', '\uf013'], art: ['#a44782', '\uf1fc'], fashion: ['#a44782', '\uf1fc'],
        map: ['#2b7a63', '\uf041'], migration: ['#2b7a63', '\uf041'],
        music: ['#7355a4', '\uf001'], nature: ['#3c8a4d', '\uf06c'],
        manuscript: ['#8a6843', '\uf0f6'], newspaper: ['#8a6843', '\uf0f6'],
        photography: ['#426c9b', '\uf030'], audiovisual: ['#576bb0', '\uf04b'],
        sport: ['#b16638', '\uf1e3'],
    };
    const fontSample = document.querySelector('.fa');
    const iconFont = fontSample ? window.getComputedStyle(fontSample) : null;
    const font = (iconFont?.fontWeight || '900') + ' 15px ' + (iconFont?.fontFamily || 'FontAwesome');
    const styleCache = new Map();
    /**
     * Get a consistent point icon for an OpenData theme.
     *
     * @param {Object} feature OpenLayers feature.
     * @returns {Object} OpenLayers style.
     */
    const symbolStyle = (feature) => {
        const theme = feature.get('theme') || 'all';
        if (!styleCache.has(theme)) {
            const [color, icon] = symbols[theme] || symbols.all;
            styleCache.set(theme, new ol.style.Style({
                image: new ol.style.Circle({radius: 14, fill: new ol.style.Fill({color}),
                    stroke: new ol.style.Stroke({color: '#fff', width: 2})}),
                text: new ol.style.Text({text: icon, font, textAlign: 'center', textBaseline: 'middle',
                    fill: new ol.style.Fill({color: '#fff'})}),
            }));
        }
        return styleCache.get(theme);
    };

    /** Ensure saved OpenData items share one independently switchable map layer. */
    const ensureLayer = () => {
        if (!openDataLayer) {
            openDataLayer = new ol.layer.Vector({title: dialog.dataset.layerLabel, source: vectorSource,
                style: symbolStyle});
            openDataLayer.set('treasurehuntOpenData', true);
            map.addLayer(openDataLayer);
            layerSwitcher.renderPanel();
        }
        openDataLayer.setVisible(true);
    };

    /**
     * Load one page per pending job and append unique points to the layer.
     *
     * @param {number} currentRequest Search generation.
     * @returns {Promise<void>} Completion of this batch.
     */
    const loadPage = async(currentRequest) => {
        searchButton.disabled = true;
        loadMoreButton.disabled = true;
        showStatus(dialog.dataset.loadingMoreLabel, 'info');
        const pending = jobs.filter((job) => job.cursor !== null);
        pending.forEach((job) => {
            job.state = 'queued';
        });
        renderProgress();
        const errors = [];
        let offset = 0;
        /** Fetch jobs through a small worker pool. */
        const worker = async() => {
            while (offset < pending.length) {
                if (currentRequest !== requestNumber) {
                    return;
                }
                const job = pending[offset++];
                job.state = 'active';
                renderProgress();
                const parameters = new URLSearchParams(searchParameters);
                parameters.set('source', job.source);
                parameters.set('cursor', job.cursor);
                job.types.forEach((type) => parameters.append('types[]', type));
                const controller = new AbortController();
                activeControllers.add(controller);
                try {
                    const response = await fetch(dialog.dataset.searchUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                        body: parameters.toString(),
                        signal: controller.signal,
                    });
                    const data = await response.json().catch(() => null);
                    if (currentRequest !== requestNumber) {
                        return;
                    }
                    if (!response.ok || data?.error || !Array.isArray(data?.geojson?.features)) {
                        const error = new Error(data?.error || dialog.dataset.serviceError);
                        error.httpStatus = response.status;
                        error.code = data?.code;
                        error.diagnostic = data?.diagnostic;
                        throw error;
                    }
                    const fresh = data.geojson.features.filter((item) => {
                        const key = job.source + ':' + item.properties?.id;
                        if (!item.properties?.id || seenItems.has(key)) {
                            return false;
                        }
                        seenItems.add(key);
                        return true;
                    });
                    pendingItems.push(...fresh);
                    renderPreview();
                    addButton.disabled = pendingItems.length === 0;
                    replaceButton.disabled = pendingItems.length === 0;
                    showStatus(dialog.dataset.resultsLabel + ': ' + pendingItems.length + '\n' +
                        dialog.dataset.searchingLabel, 'info');
                    job.cursor = data.nextcursor || null;
                    job.count = (job.count || 0) + fresh.length;
                    job.state = 'done';
                } catch (error) {
                    if (currentRequest === requestNumber) {
                        window.console.error('Treasurehunt OpenData search failed', {
                            source: job.source,
                            types: job.types,
                            httpStatus: error.httpStatus ?? null,
                            code: error.code ?? null,
                            diagnostic: error.diagnostic ?? null,
                            error,
                        });
                        errors.push(error.message || dialog.dataset.serviceError);
                        job.state = 'failed';
                    }
                } finally {
                    activeControllers.delete(controller);
                    if (currentRequest === requestNumber) {
                        renderProgress();
                    }
                }
            }
        };
        await Promise.all(Array.from({length: Math.min(3, pending.length)}, () => worker()));
        if (currentRequest !== requestNumber) {
            return;
        }
        loadMoreButton.hidden = !jobs.some((job) => job.cursor !== null);
        loadMoreButton.disabled = false;
        searchButton.disabled = false;
        addButton.disabled = pendingItems.length === 0;
        replaceButton.disabled = pendingItems.length === 0;
        const count = pendingItems.length;
        const summary = count ? dialog.dataset.resultsLabel + ': ' + count :
            errors.length ? '' : dialog.dataset.emptyLabel;
        const message = [summary, ...new Set(errors)].filter(Boolean).join('\n');
        showStatus(message, errors.length ? 'warning' : count ? 'success' : 'info');
    };

    /** Start a fresh search while leaving saved map items untouched. */
    const search = async() => {
        clearStatus();
        const currentRequest = requestNumber;
        jobs = [];
        for (const source of sourceInputs.filter((input) => input.checked)) {
            const selected = typeInputs.filter((input) => input.dataset.source === source.value && input.checked)
                .map((input) => input.value);
            for (const type of selected) {
                jobs.push({source: source.value, types: [type], cursor: '', state: 'queued', count: 0});
            }
        }
        if (!jobs.length) {
            showStatus(dialog.dataset.selectTypeError, 'warning');
            return;
        }
        const bounds = visibleBounds();
        if (bounds.some((value) => !Number.isFinite(value)) || bounds[0] >= bounds[2] || bounds[1] >= bounds[3]) {
            showStatus(dialog.dataset.areaError, 'warning');
            return;
        }
        searchParameters = new URLSearchParams({
            id: dialog.dataset.cmid,
            sesskey: dialog.dataset.sesskey,
            term: termInput.value.trim(),
            west: String(bounds[0]),
            south: String(bounds[1]),
            east: String(bounds[2]),
            north: String(bounds[3]),
        });
        loadMoreButton.hidden = true;
        renderProgress();
        showStatus(dialog.dataset.searchingLabel, 'info');
        await loadPage(currentRequest);
    };

    /**
     * Commit pending results to the one OpenData layer.
     *
     * @param {boolean} replace Whether to remove existing map items first.
     */
    const save = (replace) => {
        if (pendingItems.length === 0 || (replace ? replaceButton : addButton).disabled) {
            return;
        }
        cancelRequests();
        const existing = new Set(savedItems.map((item) => item.properties?.source + ':' + item.properties?.id));
        const items = replace ? pendingItems : savedItems.concat(pendingItems.filter((item) =>
            !existing.has(item.properties?.source + ':' + item.properties?.id)));
        const features = new ol.format.GeoJSON().readFeatures({type: 'FeatureCollection', features: items}, {
            dataProjection: 'EPSG:4326', featureProjection: map.getView().getProjection(),
        });
        vectorSource.clear();
        vectorSource.addFeatures(features);
        savedItems = items;
        try {
            localStorage.setItem(storageKey, JSON.stringify(savedItems));
        } catch (error) {
            addToast(dialog.dataset.storageError, {type: 'warning'});
        }
        closePopup();
        ensureLayer();
        addToast(replace ? dialog.dataset.replacedLabel : dialog.dataset.addedLabel, {type: 'success'});
        modal.hide();
    };

    // A map overlay is separate from the stage source and never enters the save payload.
    const popup = document.createElement('div');
    popup.className = 'treasurehunt-opendata-card card shadow-lg border-0';
    popup.style.display = 'none';
    map.getTargetElement().appendChild(popup);
    const overlay = new ol.Overlay({element: popup, positioning: 'bottom-center', offset: [0, -12],
        stopEvent: true, autoPan: true, autoPanMargin: 16});
    map.addOverlay(overlay);
    const tooltip = document.createElement('div');
    tooltip.className = 'treasurehunt-opendata-tooltip';
    tooltip.style.display = 'none';
    map.getTargetElement().appendChild(tooltip);
    const detailCache = new Map();
    const detailRequests = new Map();
    let activeFeature = null;

    /** Close the OpenData card and ignore any detail request still in flight. */
    const closePopup = () => {
        activeFeature = null;
        overlay.setPosition(undefined);
        popup.style.display = 'none';
    };
    const closeButton = document.createElement('a');
    closeButton.href = '#';
    closeButton.className = 'ol-popup-closer treasurehunt-opendata-closer';
    closeButton.setAttribute('aria-label', dialog.dataset.closeLabel);
    closeButton.addEventListener('click', (event) => {
        event.preventDefault();
        closePopup();
    });

    /**
     * Create an external link without interpreting text from Wikidata as HTML.
     *
     * @param {string} url Destination URL.
     * @param {string} label Localized label.
     * @returns {HTMLElement} Link button.
     */
    const externalLink = (url, label) => {
        const link = document.createElement('a');
        link.className = 'btn btn-sm btn-outline-primary';
        if (typeof url !== 'string' || !/^https?:\/\//i.test(url)) {
            link.hidden = true;
            return link;
        }
        link.href = url;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.textContent = label;
        return link;
    };

    /**
     * Add the selected search theme to any categories reported by the provider.
     *
     * @param {Object} feature Saved OpenData feature.
     * @param {Object|null} details Additional provider metadata.
     * @returns {Array<Object>} Category tags.
     */
    const itemCategories = (feature, details) => {
        const categories = [...(details?.categories || [])];
        const theme = feature.get('theme');
        const themeInput = typeInputs.find((input) => input.dataset.source === feature.get('source') &&
            input.value === theme);
        if (theme && theme !== 'all' && themeInput) {
            categories.push({label: themeInput.closest('label').textContent.trim()});
        }
        return categories;
    };

    /**
     * Create a stage at the item location, then open its normal editing form.
     *
     * @param {Object} feature Saved OpenData feature.
     * @param {HTMLButtonElement} button Card action button.
     */
    const createStage = (feature, button) => {
        const context = editor.stageContext();
        if (!context.canCreate || !context.roadid) {
            addToast(dialog.dataset.cannotCreateStage, {type: 'warning'});
            return;
        }
        button.disabled = true;
        button.textContent = dialog.dataset.creatingStageLabel;
        /** Restore the button after a failed request or save. */
        const restoreButton = () => {
            button.disabled = false;
            button.textContent = dialog.dataset.createStageLabel;
        };
        editor.beforeCreate(async() => {
            try {
                const source = feature.get('source');
                const itemId = feature.get('id');
                let details = detailCache.get(source + ':' + itemId);
                if (!details && !feature.get('detailsready')) {
                    try {
                        details = await loadDetails(itemId, source);
                    } catch (error) {
                        // Search result metadata can still initialize the teacher's stage.
                    }
                }
                const metadata = {
                    description: details?.description || feature.get('description') || '',
                    image: details?.image || feature.get('image') || '',
                    url: feature.get('url') || '',
                    aliases: details?.aliases || feature.get('aliases') || [],
                    websites: details?.websites || feature.get('websites') || [],
                    types: details?.types || [],
                    subjects: details?.subjects || [],
                    categories: itemCategories(feature, details),
                    fields: details?.fields || {},
                };
                const point = ol.proj.transform(feature.getGeometry().getCoordinates(),
                    map.getView().getProjection(), 'EPSG:4326');
                const parameters = new URLSearchParams({
                    action: 'createstage',
                    id: dialog.dataset.cmid,
                    sesskey: dialog.dataset.sesskey,
                    source,
                    roadid: String(context.roadid),
                    lockid: String(editor.stageContext().lockid),
                    name: feature.get('name'),
                    metadata: JSON.stringify(metadata),
                    longitude: String(point[0]),
                    latitude: String(point[1]),
                });
                const response = await fetch(dialog.dataset.searchUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                    body: parameters.toString(),
                });
                const data = await response.json();
                if (!response.ok || data.error || !data.editurl) {
                    throw new Error(data.error || dialog.dataset.serviceError);
                }
                window.location.assign(data.editurl);
            } catch (error) {
                restoreButton();
                addToast(error.message || dialog.dataset.serviceError, {type: 'warning'});
            }
        }, restoreButton);
    };

    /**
     * Paint the best information currently available for one selected item.
     *
     * @param {Object} feature OpenLayers feature.
     * @param {Object|null} details Optional loaded Wikidata details.
     * @param {string} state loading, error or complete.
     */
    const renderCard = (feature, details, state) => {
        popup.replaceChildren(closeButton);
        const name = feature.get('name') || feature.get('id');
        const image = details?.image || feature.get('image');
        if (image) {
            const picture = document.createElement('img');
            picture.className = 'card-img-top treasurehunt-opendata-image';
            picture.src = image;
            picture.alt = name;
            picture.loading = 'lazy';
            picture.addEventListener('error', () => picture.remove());
            popup.appendChild(picture);
        }
        const body = document.createElement('div');
        body.className = 'card-body p-3';
        const title = document.createElement('h5');
        title.className = 'card-title mb-1';
        title.textContent = name;
        body.appendChild(title);
        const id = document.createElement('span');
        id.className = 'treasurehunt-opendata-id small';
        id.textContent = feature.get('id');
        body.appendChild(id);
        const description = details?.description || feature.get('description');
        if (description) {
            const summary = document.createElement('p');
            summary.className = 'card-text mt-2 mb-2';
            summary.textContent = description;
            body.appendChild(summary);
        }
        const aliases = details?.aliases || feature.get('aliases');
        if (aliases?.length) {
            const aliasHeading = document.createElement('div');
            aliasHeading.className = 'small text-muted text-uppercase mt-2 mb-1';
            aliasHeading.textContent = dialog.dataset.aliasesLabel;
            body.appendChild(aliasHeading);
            const aliasList = document.createElement('div');
            aliasList.className = 'treasurehunt-opendata-aliases';
            aliases.forEach((alias) => {
                const chip = document.createElement('span');
                chip.className = 'treasurehunt-opendata-alias';
                chip.textContent = alias;
                aliasList.appendChild(chip);
            });
            body.appendChild(aliasList);
        }
        [['types', dialog.dataset.itemTypesLabel], ['subjects', dialog.dataset.subjectsLabel],
            ['categories', dialog.dataset.categoriesLabel]].forEach(([field, label]) => {
            const values = field === 'categories' ? itemCategories(feature, details) : details?.[field];
            if (!values?.length) {
                return;
            }
            const heading = document.createElement('div');
            heading.className = 'small text-muted text-uppercase mt-2 mb-1';
            heading.textContent = label;
            body.appendChild(heading);
            const tags = document.createElement('div');
            tags.className = 'treasurehunt-opendata-tags';
            values.forEach((tag) => {
                const linked = typeof tag.url === 'string' &&
                    /^https:\/\/(www\.europeana\.eu|www\.wikidata\.org)\//.test(tag.url);
                const element = document.createElement(linked ? 'a' : 'span');
                element.className = 'treasurehunt-opendata-tag';
                element.textContent = tag.label;
                if (linked) {
                    element.href = tag.url;
                    element.target = '_blank';
                    element.rel = 'noopener noreferrer';
                }
                tags.appendChild(element);
            });
            body.appendChild(tags);
        });
        Object.entries(details?.fields || {}).forEach(([field, values]) => {
            if (!fieldLabels[field] || !Array.isArray(values) || !values.length) {
                return;
            }
            const line = document.createElement('p');
            line.className = 'small mb-1';
            const label = document.createElement('strong');
            label.textContent = fieldLabels[field] + ': ';
            line.append(label, document.createTextNode(values.join(', ')));
            body.appendChild(line);
        });
        if (state === 'loading' || state === 'error') {
            const note = document.createElement('div');
            note.className = 'small text-muted mt-2';
            if (state === 'loading') {
                const spinner = document.createElement('span');
                spinner.className = 'spinner-border spinner-border-sm mr-1 me-1';
                spinner.setAttribute('aria-hidden', 'true');
                note.appendChild(spinner);
            }
            note.appendChild(document.createTextNode(state === 'loading' ?
                dialog.dataset.detailsLoading : dialog.dataset.detailsUnavailable));
            body.appendChild(note);
        }
        popup.appendChild(body);
        const footer = document.createElement('div');
        footer.className = 'card-footer treasurehunt-opendata-links p-2';
        footer.appendChild(externalLink(feature.get('url'), feature.get('source') === 'europeana' ? 'Europeana' : 'Wikidata'));
        (details?.websites || feature.get('websites') || []).forEach((website) => footer.appendChild(externalLink(website.url,
            website.type === 'official' ? dialog.dataset.officialLabel : dialog.dataset.aboutLabel)));
        popup.appendChild(footer);
        const action = document.createElement('div');
        action.className = 'card-footer p-2';
        const createButton = document.createElement('button');
        createButton.type = 'button';
        createButton.className = 'btn btn-primary btn-sm w-100';
        createButton.textContent = dialog.dataset.createStageLabel;
        createButton.addEventListener('click', () => createStage(feature, createButton));
        action.appendChild(createButton);
        popup.appendChild(action);
    };

    /**
     * Fetch one card when opened; cache it for later clicks on the same resource.
     *
     * @param {string} itemId Provider item identifier.
     * @param {string} source Provider ID.
     * @returns {Promise<Object>} Card details.
     */
    const loadDetails = (itemId, source) => {
        const key = source + ':' + itemId;
        if (detailCache.has(key)) {
            return Promise.resolve(detailCache.get(key));
        }
        if (!detailRequests.has(key)) {
            const parameters = new URLSearchParams({
                action: 'details',
                id: dialog.dataset.cmid,
                sesskey: dialog.dataset.sesskey,
                itemid: itemId,
                source,
            });
            const request = fetch(dialog.dataset.searchUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: parameters.toString(),
            }).then(async(response) => {
                const data = await response.json();
                if (!response.ok || data.error) {
                    throw new Error(data.error || dialog.dataset.serviceError);
                }
                detailCache.set(key, data);
                return data;
            }).finally(() => detailRequests.delete(key));
            detailRequests.set(key, request);
        }
        return detailRequests.get(key);
    };

    map.on('singleclick', (event) => {
        const feature = map.forEachFeatureAtPixel(event.pixel, (candidate) => candidate,
            {hitTolerance: 6, layerFilter: (layer) => layer.get('treasurehuntOpenData')});
        if (!feature) {
            return;
        }
        activeFeature = feature;
        tooltip.style.display = 'none';
        const key = feature.get('source') + ':' + feature.get('id');
        const cached = feature.get('source') !== 'europeana' && feature.get('detailsready') ? {
            image: feature.get('image'), websites: feature.get('websites'), aliases: feature.get('aliases'),
        } : detailCache.get(key);
        renderCard(feature, cached, cached ? 'complete' : 'loading');
        popup.style.display = 'block';
        overlay.setPosition(event.coordinate);
        if (!cached) {
            loadDetails(feature.get('id'), feature.get('source')).then((details) => {
                if (activeFeature === feature) {
                    renderCard(feature, details, 'complete');
                }
            }).catch(() => {
                if (activeFeature === feature) {
                    renderCard(feature, null, 'error');
                }
            });
        }
    });

    /** Show the title only while the pointer is over a saved OpenData point. */
    map.on('pointermove', (event) => {
        if (event.dragging) {
            tooltip.style.display = 'none';
            return;
        }
        const feature = map.forEachFeatureAtPixel(event.pixel, (candidate) => candidate,
            {hitTolerance: 6, layerFilter: (layer) => layer.get('treasurehuntOpenData')});
        tooltip.style.display = feature ? 'block' : 'none';
        if (feature) {
            tooltip.textContent = feature.get('name') || feature.get('id');
            tooltip.style.left = event.pixel[0] + 'px';
            tooltip.style.top = (event.pixel[1] - 20) + 'px';
        }
    });
    map.getTargetElement().addEventListener('pointerleave', () => {
        tooltip.style.display = 'none';
    });

    // Restore the confirmed layer for this activity and editor across browser sessions.
    try {
        let serialized = localStorage.getItem(storageKey);
        let previousKey = null;
        if (!serialized) {
            // Move results saved by the earlier tab-only implementation into persistent storage.
            previousKey = 'treasurehuntOpenData:' + dialog.dataset.cmid;
            serialized = sessionStorage.getItem(previousKey);
        }
        const stored = JSON.parse(serialized || 'null');
        if (Array.isArray(stored) && stored.length) {
            if (previousKey) {
                localStorage.setItem(storageKey, serialized);
                sessionStorage.removeItem(previousKey);
            }
            savedItems = stored;
            const features = new ol.format.GeoJSON().readFeatures({type: 'FeatureCollection', features: stored}, {
                dataProjection: 'EPSG:4326', featureProjection: map.getView().getProjection(),
            });
            vectorSource.addFeatures(features);
            ensureLayer();
        }
    } catch (error) {
        // Ignore unavailable or stale browser storage; the editor can run without it.
    }

    searchButton.addEventListener('click', search);
    addButton.addEventListener('click', () => save(false));
    replaceButton.addEventListener('click', () => save(true));
    loadMoreButton.addEventListener('click', () => loadPage(requestNumber));
    termInput.addEventListener('input', clearStatus);
    typeInputs.forEach((input) => input.addEventListener('change', () => {
        if (input.checked && input.value === 'all') {
            typeInputs.filter((other) => other !== input && other.dataset.source === input.dataset.source).forEach((other) => {
                other.checked = false;
            });
        } else if (input.checked) {
            typeInputs.find((other) => other.value === 'all' &&
                other.dataset.source === input.dataset.source).checked = false;
        }
        clearStatus();
    }));
    sourceInputs.forEach((input) => input.addEventListener('change', clearStatus));
    openButton.addEventListener('click', () => modal.show());
    dialog.addEventListener('hidden.bs.modal', clearStatus);
    $(dialog).on('hidden.bs.modal', clearStatus);
}
