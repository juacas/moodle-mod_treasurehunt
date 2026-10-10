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
    const saveButton = document.getElementById('opendatasave');
    const previewList = document.getElementById('opendatapreview');
    const storageKey = 'treasurehuntOpenData:' + dialog.dataset.cmid;
    const vectorSource = new ol.source.Vector();
    let openDataLayer = null;
    let jobs = [];
    let searchParameters = null;
    const seenItems = new Set();
    let pendingItems = [];
    let loadedPages = 0;
    let requestNumber = 0;

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
        requestNumber++;
        jobs = [];
        pendingItems = [];
        loadedPages = 0;
        seenItems.clear();
        previewList.replaceChildren();
        loadMoreButton.hidden = true;
        saveButton.disabled = true;
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
        saveButton.disabled = true;
        showStatus(dialog.dataset.loadingMoreLabel, 'info');
        const pending = jobs.filter((job) => job.cursor !== null);
        const errors = [];
        let offset = 0;
        /** Fetch jobs through a small worker pool. */
        const worker = async() => {
            while (offset < pending.length) {
                if (currentRequest !== requestNumber) {
                    return;
                }
                const job = pending[offset++];
                const parameters = new URLSearchParams(searchParameters);
                parameters.set('source', job.source);
                parameters.set('cursor', job.cursor);
                job.types.forEach((type) => parameters.append('types[]', type));
                try {
                    const response = await fetch(dialog.dataset.searchUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                        body: parameters.toString(),
                    });
                    const data = await response.json();
                    if (currentRequest !== requestNumber) {
                        return;
                    }
                    if (!response.ok || data.error || !Array.isArray(data.geojson?.features)) {
                        throw new Error(data.error || dialog.dataset.serviceError);
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
                    job.cursor = data.nextcursor || null;
                    loadedPages++;
                } catch (error) {
                    if (currentRequest === requestNumber) {
                        errors.push(error.message || dialog.dataset.serviceError);
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
        saveButton.disabled = loadedPages === 0;
        const count = pendingItems.length;
        const message = (count ? dialog.dataset.resultsLabel + ': ' + count : dialog.dataset.emptyLabel) +
            (errors.length ? '\n' + [...new Set(errors)].join('\n') : '');
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
                jobs.push({source: source.value, types: [type], cursor: ''});
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
        if (bounds[2] - bounds[0] > 5 || bounds[3] - bounds[1] > 5) {
            showStatus(dialog.dataset.zoomError, 'warning');
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
        showStatus(dialog.dataset.searchingLabel, 'info');
        await loadPage(currentRequest);
    };

    /** Commit the complete pending search to the one OpenData map layer. */
    const save = () => {
        if (saveButton.disabled || loadedPages === 0) {
            return;
        }
        const features = new ol.format.GeoJSON().readFeatures({type: 'FeatureCollection', features: pendingItems}, {
            dataProjection: 'EPSG:4326', featureProjection: map.getView().getProjection(),
        });
        vectorSource.clear();
        vectorSource.addFeatures(features);
        try {
            sessionStorage.setItem(storageKey, JSON.stringify(pendingItems));
        } catch (error) {
            // The current layer remains available if browser storage is full or disabled.
        }
        activeFeature = null;
        overlay.setPosition(undefined);
        popup.style.display = 'none';
        ensureLayer();
        addToast(dialog.dataset.addedLabel, {type: 'success'});
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
     * Create a stage at the item location, then open its normal editing form.
     *
     * @param {Object} feature Saved OpenData feature.
     * @param {HTMLButtonElement} button Card action button.
     */
    const createStage = (feature, button) => {
        const context = editor.stageContext();
        if (!context.canCreate || !context.roadid) {
            return;
        }
        button.disabled = true;
        editor.beforeCreate(async() => {
            try {
                const point = ol.proj.transform(feature.getGeometry().getCoordinates(),
                    map.getView().getProjection(), 'EPSG:4326');
                const parameters = new URLSearchParams({
                    action: 'createstage',
                    id: dialog.dataset.cmid,
                    sesskey: dialog.dataset.sesskey,
                    source: feature.get('source'),
                    roadid: String(context.roadid),
                    lockid: String(editor.stageContext().lockid),
                    name: feature.get('name'),
                    description: feature.get('description') || '',
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
                button.disabled = false;
                addToast(error.message || dialog.dataset.serviceError, {type: 'warning'});
            }
        }, () => {
            button.disabled = false;
        });
    };

    /**
     * Paint the best information currently available for one selected item.
     *
     * @param {Object} feature OpenLayers feature.
     * @param {Object|null} details Optional loaded Wikidata details.
     * @param {string} state loading, error or complete.
     */
    const renderCard = (feature, details, state) => {
        popup.replaceChildren();
        const name = feature.get('name') || feature.get('id');
        if (details?.image) {
            const picture = document.createElement('img');
            picture.className = 'card-img-top treasurehunt-opendata-image';
            picture.src = details.image;
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
        if (details?.aliases?.length) {
            const aliasHeading = document.createElement('div');
            aliasHeading.className = 'small text-muted text-uppercase mt-2 mb-1';
            aliasHeading.textContent = dialog.dataset.aliasesLabel;
            body.appendChild(aliasHeading);
            const aliases = document.createElement('div');
            aliases.className = 'treasurehunt-opendata-aliases';
            details.aliases.forEach((alias) => {
                const chip = document.createElement('span');
                chip.className = 'treasurehunt-opendata-alias';
                chip.textContent = alias;
                aliases.appendChild(chip);
            });
            body.appendChild(aliases);
        }
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
        details?.websites?.forEach((website) => footer.appendChild(externalLink(website.url,
            website.type === 'official' ? dialog.dataset.officialLabel : dialog.dataset.aboutLabel)));
        popup.appendChild(footer);
        const action = document.createElement('div');
        action.className = 'card-footer p-2';
        const createButton = document.createElement('button');
        createButton.type = 'button';
        createButton.className = 'btn btn-primary btn-sm w-100';
        createButton.textContent = dialog.dataset.createStageLabel;
        createButton.disabled = !editor.stageContext().canCreate;
        createButton.addEventListener('click', () => createStage(feature, createButton));
        action.appendChild(createButton);
        popup.appendChild(action);
    };

    /**
     * Fetch one card when opened; cache it for later clicks on the same resource.
     *
     * @param {string} itemId Wikidata item identifier.
     * @returns {Promise<Object>} Card details.
     */
    const loadDetails = (itemId) => {
        if (detailCache.has(itemId)) {
            return Promise.resolve(detailCache.get(itemId));
        }
        if (!detailRequests.has(itemId)) {
            const parameters = new URLSearchParams({
                action: 'details',
                id: dialog.dataset.cmid,
                sesskey: dialog.dataset.sesskey,
                itemid: itemId,
                source: 'wikidata',
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
                detailCache.set(itemId, data);
                return data;
            }).finally(() => detailRequests.delete(itemId));
            detailRequests.set(itemId, request);
        }
        return detailRequests.get(itemId);
    };

    map.on('singleclick', (event) => {
        const feature = map.forEachFeatureAtPixel(event.pixel, (candidate) => candidate,
            {hitTolerance: 6, layerFilter: (layer) => layer.get('treasurehuntOpenData')});
        activeFeature = feature || null;
        tooltip.style.display = 'none';
        if (!feature) {
            overlay.setPosition(undefined);
            popup.style.display = 'none';
            return;
        }
        const cached = feature.get('detailsready') ? {
            image: feature.get('image'), websites: feature.get('websites'), aliases: feature.get('aliases'),
        } : detailCache.get(feature.get('id'));
        renderCard(feature, cached, cached ? 'complete' : 'loading');
        popup.style.display = 'block';
        overlay.setPosition(event.coordinate);
        if (!cached) {
            loadDetails(feature.get('id')).then((details) => {
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

    // Preserve the confirmed layer while navigating to and from a stage form in this tab.
    try {
        const stored = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
        if (Array.isArray(stored) && stored.length && stored.length <= 5000) {
            const features = new ol.format.GeoJSON().readFeatures({type: 'FeatureCollection', features: stored}, {
                dataProjection: 'EPSG:4326', featureProjection: map.getView().getProjection(),
            });
            vectorSource.addFeatures(features);
            ensureLayer();
        }
    } catch (error) {
        // Ignore unavailable or stale tab storage; the editor can run without it.
    }

    searchButton.addEventListener('click', search);
    saveButton.addEventListener('click', save);
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
