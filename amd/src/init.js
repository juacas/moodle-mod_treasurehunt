/* global require */

// Standard license block omitted.
/*
 * @package    block_overview
 * @copyright  2015 Someone cool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module block_overview/helloworld
 */
require.config({
    baseUrl: 'js',
    shim: {
        openlayers: {
            exports: 'OpenLayers'
        },
    },
    paths: {
        openlayers: 'openlayers/ol-debug',
        geocoderjs: 'geocoder/geocoder'
    }
});


define(['jquery', 'core/notification', 'core/str', 'openlayers', 'jqueryui', 'core/ajax', 'geocoderjs'], function ($, notification, str, ol, jqui, ajax, GeocoderJS) {


    var init = {
        init: function (idModule, idScavengerhunt) {

            //Lo primero recojo todas las cadenas que voy a necesitar con una llamada ajax
            var ajaxStrings = [{
                    key: 'insert_riddle',
                    component: 'scavengerhunt'
                }, {
                    key: 'insert_road',
                    component: 'scavengerhunt'
                }, {
                    key: 'empty_ridle',
                    component: 'scavengerhunt'
                }];
            str.get_strings(ajaxStrings).done(function (data) {
                /** Global var ***************************************************************
                 */
                var stage = {
                    "roads": {}
                };
                var dirtyStage = new ol.source.Vector({
                    projection: 'EPSG:3857'
                });
                var originalStage = new ol.source.Vector({
                    projection: 'EPSG:3857'
                });
                var dirty = false;
                var numRiddle;
                var idRoad;
                var idRiddle;
                var selectedFeatures;
                var selectedRiddleFeatures = new Object();
                var idNewFeature = 1;
                var Strings = getKeyValue(ajaxStrings, data);
                //Utilizada para guardar el valor original del nombre del camino
                var oriVal;
                var openStreetMapGeocoder = GeocoderJS.createGeocoder('openstreetmap');
                /**Initialize stage and selectedRiddleFeatures******************************************************
                 */

                function getKeyValue(key, value) {
                    var object = new Object();
                    for (var i = 0, j = key.length; i < j; i++) {
                        object[key[i].key] = value[i];
                    }
                    return object;
                }
                /**Load the control pane, riddle and road list ***************************************************
                 */
                $("#controlPanel").addClass('ui-widget-header ui-corner-all');
                $('<span id="edition"/>').appendTo($("#controlPanel"));
                $('<input type="radio" name="controlPanel" id="radio1" value="add">').appendTo($("#edition"));
                $("<label>").attr('for', "radio1").text('AÃƒÂ±adir').appendTo($("#edition"));
                $('<input type="radio" name="controlPanel" id="radio2" value="modify">').appendTo($("#edition"));
                $("<label>").attr('for', "radio2").text('Modificar').appendTo($("#edition"));
                $('<button id="saveRiddle"/>').attr('disabled', true).text('Guardar cambios').appendTo($("#controlPanel"));
                $('<button id="removeFeature"/>').attr('disabled', true).text('Eliminar').appendTo($("#controlPanel"));
                $('<div id="searchContainer">').appendTo($("#controlPanel"));
                $('<input id="searchAddress" type="search" placeholder="Enter a Location" class="clearable"/>').appendTo($("#searchContainer"));
                $('<span id="searchIcon" class="ui-icon  ui-icon-search"></span>').appendTo($("#searchContainer"));
                $('<button id="addRiddle"/>').text('Riddle').prependTo($("#controlPanel"));
                $('<button id="addRoad"/>').text('Road').prependTo($("#controlPanel"));
                $("#radio1").button({
                    text: false,
                    icons: {
                        primary: "ui-icon-plusthick"
                    }
                });
                $("#radio2").button({
                    text: false,
                    icons: {
                        primary: "ui-icon-pencil"
                    }
                });
                $("#removeFeature").button({
                    text: false,
                    icons: {
                        primary: "ui-icon-trash"
                    }
                });
                $("#saveRiddle").button({
                    text: false,
                    icons: {
                        primary: "ui-icon-disk"
                    }
                });
                $("#addRiddle").button({
                    icons: {
                        primary: "ui-icon-circle-plus"
                    }
                });
                $("#addRoad").button({
                    icons: {
                        primary: "ui-icon-circle-plus"
                    }
                });
                //Lo cargo como un buttonset
                $("#edition").buttonset();
                //Creo el riddleListPanel
                $('<ul id="riddleList"/>').appendTo($("#riddleListPanel"));

                //Lo cargo como un sortable
                $("#riddleList").sortable({
                    handle: ".handle",
                    revert: true,
                    cursor: "n-resize",
                    axis: 'y',
                    start: function (event, ui) {
                        var idRoad = ui.item.attr('idRoad');
                        var start_pos = ui.item.index('li[idRoad="' + idRoad + '"]');
                        ui.item.data('start_pos', start_pos);
                    },
                    update: function (event, ui) {
                        var start_pos = ui.item.data('start_pos');
                        var idRoad = ui.item.attr('idRoad');
                        var end_pos = ui.item.index('li[idRoad="' + idRoad + '"]');
                        var $listitems = $(this).children('li[idRoad="' + idRoad + '"]');
                        var $listlength = $($listitems).length;
                        if (start_pos === end_pos) {
                            return;
                        }
                        if (start_pos < end_pos) {
                            for (var i = start_pos; i <= end_pos; i++) {
                                relocateRiddleList($listitems, $listlength, i, dirtyStage, originalStage, stage["roads"][idRoad].vector);
                            }
                        } else {
                            for (var i = end_pos; i <= start_pos; i++) {
                                relocateRiddleList($listitems, $listlength, i, dirtyStage, originalStage, stage["roads"][idRoad].vector);
                            }
                        }
                        activateSaveButton();
                        dirty = true;
                    }
                });

                function relocateRiddleList($listitems, $listlength, i, dirtyStage, originalStage, vector) {
                    var newVal;
                    var $item = $($listitems).get([i]);
                    var idRoad = $($item).attr('idRoad');
                    newVal = Math.abs($($item).index('li[idRoad="' + idRoad + '"]') - $listlength);
                    $($item).attr('numRiddle', newVal);
                    $($item).find('.sortable-number').text(newVal);
                    //Si esta seleccionado cambiamos el valor de numRiddle
                    if ($($item).hasClass("ui-selected")) {
                        numRiddle = newVal;
                    }
                    relocateNumRiddle(parseInt($($item).attr('idRiddle')), newVal, parseInt($($item).attr('idRoad')), dirtyStage, originalStage, vector);
                }

                //Creo el roadListPanel
                $('<ul id="roadList"/>').appendTo($("#roadListPanel"));

                /** Get style, vectors, map and interactions ***************************************************************
                 */
                var defaultRiddleStyle = new ol.style.Style({
                    fill: new ol.style.Fill({
                        color: 'rgba(0, 0, 0, 0.1)'
                    }),
                    stroke: new ol.style.Stroke({
                        color: '#6C0492',
                        width: 2
                    }),
                    image: new ol.style.Circle({
                        radius: 5,
                        fill: new ol.style.Fill({
                            color: '#ffcc33'
                        }),
                        stroke: new ol.style.Stroke({
                            color: '#000000',
                            width: 2
                        })
                    }),
                    text: new ol.style.Text({
                        textAlign: 'center',
                        scale: 1.3,
                        fill: new ol.style.Fill({
                            color: '#fff'
                        }),
                        stroke: new ol.style.Stroke({
                            color: '#6C0492',
                            width: 3.5
                        })
                    })
                });
                //Estilo pista seleccionada
                var selectedRiddleStyle = new ol.style.Style({
                    fill: new ol.style.Fill({
                        color: 'rgba(0, 0, 0, 0.05)'
                    }),
                    stroke: new ol.style.Stroke({
                        color: '#FAC30B',
                        width: 2
                    }),
                    image: new ol.style.Circle({
                        radius: 5,
                        fill: new ol.style.Fill({
                            color: '#ffcc33'
                        }),
                        stroke: new ol.style.Stroke({
                            color: '#000000',
                            width: 2
                        })
                    }),
                    text: new ol.style.Text({
                        textAlign: 'center',
                        scale: 1.3,
                        fill: new ol.style.Fill({
                            color: '#fff'
                        }),
                        stroke: new ol.style.Stroke({
                            color: '#ffcc33',
                            width: 3.5
                        })
                    }),
                    zIndex: 'Infinity'
                });
                var vectorDraw = new ol.layer.Vector({
                    source: new ol.source.Vector({
                        projection: 'EPSG:3857'
                    }),
                    visible: false
                });

                var map = new ol.Map({
                    layers: [
                        new ol.layer.Tile({
                            source: new ol.source.OSM()
                        }), vectorDraw],
                    renderer: 'canvas',
                    target: 'map',
                    view: new ol.View({
                        center: new ol.proj.transform([-4.715354, 41.654618], 'EPSG:4326', 'EPSG:3857'),
                        zoom: 12,
                        minZoom: 2
                    })
                });

                var Modify = {
                    init: function () {
                        this.select = new ol.interaction.Select({
                            //Si una feature puede ser seleccionada o no
                            filter: function (feature) {
                                if (selectedRiddleFeatures[feature.getId()]) {
                                    return true;
                                }
                                return false;
                            },
                            style: function (feature) {
                                var fill = new ol.style.Fill({
                                    color: 'rgba(255,255,255,0.4)'
                                });
                                var stroke = new ol.style.Stroke({
                                    color: '#3399CC',
                                    width: 2
                                });
                                var styles = [
                                    new ol.style.Style({
                                        image: new ol.style.Circle({
                                            fill: fill,
                                            stroke: stroke,
                                            radius: 5
                                        }),
                                        fill: fill,
                                        stroke: stroke,
                                        text: new ol.style.Text({
                                            text: '' + feature.get('numRiddle'),
                                            textAlign: 'center',
                                            scale: 1.3,
                                            fill: new ol.style.Fill({
                                                color: '#fff'
                                            }),
                                            stroke: new ol.style.Stroke({
                                                color: '#3399CC',
                                                width: 3.5
                                            })
                                        }),
                                        zIndex: 'Infinity'
                                    })];
                                return styles;

                            }
                        });
                        map.addInteraction(this.select);

                        this.modify = new ol.interaction.Modify({
                            features: this.select.getFeatures(),
                            style: new ol.style.Style({
                                image: new ol.style.Circle({
                                    radius: 5,
                                    fill: new ol.style.Fill({
                                        color: '#3399CC'
                                    }),
                                    stroke: new ol.style.Stroke({
                                        color: '#000000',
                                        width: 2
                                    })
                                })
                            }),
                            deleteCondition: function (event) {
                                return ol.events.condition.shiftKeyOnly(event) && ol.events.condition.singleClick(event);
                            }
                        });
                        map.addInteraction(this.modify);
                        this.setEvents();
                    },
                    setEvents: function () {
                        //Elimino la seleccion de features cuando cambia a off
                        selectedFeatures = this.select.getFeatures();
                        this.select.on('change:active', function () {
                            if (!this.getActive()) {
                                selectedFeatures.clear();
                                deactivateDeleteButton();
                            }
                        });
                        //Activo o desactivo el boton de borrar segun tenga una feature seleccionada o no
                        this.select.on('select', function () {
                            if (selectedFeatures.getLength() > 0) {
                                activateDeleteButton();
                            } else {
                                deactivateDeleteButton();
                            }
                        });
                        //Activo el boton de guardar segun se haya modificado algo o no
                        this.modify.on('modifyend', function (e) {
                            activateSaveButton();
                            modifyFeatureToDirtySource(e.features, originalStage, dirtyStage, stage["roads"][idRoad].vector);
                            dirty = true;
                        });
                    },
                    getActive: function () {
                        return (this.select.getActive() && this.modify.getActive()) ? true : false;
                    },
                    setActive: function (active) {
                        this.select.setActive(active);
                        this.modify.setActive(active);
                    }
                };
                Modify.init();


                var Draw = {
                    init: function () {
                        map.addInteraction(this.Polygon);
                        this.Polygon.setActive(false);
                        this.setEvents();
                    },
                    Polygon: new ol.interaction.Draw({
                        source: vectorDraw.getSource(),
                        type: /** @type {ol.geom.GeometryType} */
                                ('Polygon'),
                        style: new ol.style.Style({
                            fill: new ol.style.Fill({
                                color: 'rgba(0, 0, 0, 0.05)'
                            }),
                            stroke: new ol.style.Stroke({
                                color: '#FAC30B',
                                width: 2
                            }),
                            image: new ol.style.Circle({
                                radius: 5,
                                fill: new ol.style.Fill({
                                    color: '#ffcc33'
                                }),
                                stroke: new ol.style.Stroke({
                                    color: '#000000',
                                    width: 2
                                })
                            }),
                            zIndex: 'Infinity'
                        })
                    }),
                    setEvents: function () {
                        //Fijo el riddle al que pertenecen y activo el boton de guardar 
                        //segun se haya modificado algo o no
                        this.Polygon.on('drawend', function (e) {

                            e.feature.setProperties({
                                'idRoad': idRoad,
                                'idRiddle': idRiddle,
                                'numRiddle': numRiddle
                            });
                            selectedRiddleFeatures[idNewFeature] = true;
                            e.feature.setId(idNewFeature);
                            idNewFeature++;
                            //Agrego la nueva feature a su correspondiente vector de poligonos
                            stage["roads"][idRoad].vector.getSource().addFeature(e.feature);
                            //Agrego la feature a la coleccion de multipoligonos sucios
                            addNewFeatureToDirtySource(e.feature, originalStage, dirtyStage);

                            //Limpio el vector de dibujo
                            vectorDraw.getSource().clear();
                            activateSaveButton();
                            dirty = true;
                        });
                    },
                    getActive: function () {
                        return this.activeType ? this[this.activeType].getActive() : false;
                    },
                    setActive: function (active) {
                        if (active) {
                            this.activeType && this[this.activeType].setActive(false);
                            this.Polygon.setActive(true);
                            this.activeType = 'Polygon';
                        } else {
                            this.activeType && this[this.activeType].setActive(false);
                            this.activeType = null;
                        }
                        map.getTargetElement().style.cursor = active ? 'none' : '';
                    }
                };
                $(document).keyup(function (e) {
                    //Si pulso la tecla esc dejo de dibujar
                    if (e.keyCode === 27) // esc
                    {
                        Draw.Polygon.abortDrawing_();
                    }
                });

                Draw.init();
                Draw.setActive(false);
                Modify.setActive(false);
                deactivateEdition();


                // The snap interaction must be added after the Modify and Draw interactions
                // in order for its map browser event handlers to be fired first. Its handlers
                // are responsible of doing the snapping.
                var snap = new ol.interaction.Snap({
                    source: vectorDraw.getSource()
                });
                map.addInteraction(snap);


                //Cargo las features
                fetchFeatures(idScavengerhunt);


                function addNewFeatureToDirtySource(dirtyFeature, originalSource, dirtySource) {
                    var idRiddle = dirtyFeature.get('idRiddle');
                    var feature = dirtySource.getFeatureById(idRiddle);
                    if (feature) {
                        feature.getGeometry().appendPolygon(dirtyFeature.getGeometry());
                    } else {
                        feature = originalSource.getFeatureById(idRiddle).clone();
                        feature.setId(idRiddle);
                    }
                    if (feature.get('idFeaturesPolygons') === 'empty') {
                        feature.setProperties({
                            'idFeaturesPolygons': '' + dirtyFeature.getId()
                        });
                        //Quito la advertencia
                        notEmptyRiddle(idRiddle);
                    } else {
                        feature.setProperties({
                            'idFeaturesPolygons': feature.get('idFeaturesPolygons') + ',' + dirtyFeature.getId()
                        });
                    }
                    feature.getGeometry().appendPolygon(dirtyFeature.getGeometry());
                    feature.setId(idRiddle);
                    dirtySource.addFeature(feature);
                }


                function modifyFeatureToDirtySource(dirtyFeatures, originalSource, dirtySource, vector) {

                    dirtyFeatures.forEach(function (dirtyFeature) {
                        var idRiddle = dirtyFeature.get('idRiddle');
                        var feature = dirtySource.getFeatureById(idRiddle);
                        var idFeaturesPolygons;
                        var polygons = new ol.Collection();
                        if (!feature) {
                            feature = originalSource.getFeatureById(idRiddle).clone();
                            feature.setId(idRiddle);
                            dirtySource.addFeature(feature);
                        }
                        var multipolygon = feature.getGeometry();
                        //Get those multipolygons of vector layer 
                        idFeaturesPolygons = feature.get('idFeaturesPolygons').split(",");
                        for (var i = 0, j = idFeaturesPolygons.length; i < j; i++) {
                            polygons.push(vector.getSource().getFeatureById(idFeaturesPolygons[i]).getGeometry().clone());
                        }
                        multipolygon.setPolygons(polygons.getArray());
                    });
                }

                function removeFeatureToDirtySource(dirtyFeatures, originalSource, dirtySource, vector) {

                    dirtyFeatures.forEach(function (dirtyFeature) {
                        var idRiddle = dirtyFeature.get('idRiddle');
                        var feature = dirtySource.getFeatureById(idRiddle);
                        var idFeaturesPolygons;
                        var polygons = new ol.Collection();
                        var remove;
                        if (!feature) {
                            feature = originalSource.getFeatureById(idRiddle).clone();
                            feature.setId(idRiddle);
                            dirtySource.addFeature(feature);
                        }
                        var multipolygon = feature.getGeometry();
                        //Get those multipolygons of vector layer which idRiddle isn't id of dirtyFeature
                        idFeaturesPolygons = feature.get('idFeaturesPolygons').split(",");
                        for (var i = 0, j = idFeaturesPolygons.length; i < j; i++) {
                            if (idFeaturesPolygons[i] != dirtyFeature.getId()) {
                                polygons.push(vector.getSource().getFeatureById(idFeaturesPolygons[i]).getGeometry().clone());
                            } else {
                                remove = i;
                            }
                        }
                        multipolygon.setPolygons(polygons.getArray());
                        if (multipolygon.getPolygons().length) {
                            idFeaturesPolygons.splice(remove, 1);
                            feature.setProperties({
                                'idFeaturesPolygons': idFeaturesPolygons.join()
                            });
                        } else {
                            feature.setProperties({
                                'idFeaturesPolygons': 'empty'
                            });
                            emptyRiddle(idRiddle);
                        }

                    });
                }

                function styleFunction(feature) {
                    // get the incomeLevel from the feature properties
                    var numRiddle = feature.get('numRiddle');
                    if (!isNaN(numRiddle)) {
                        selectedRiddleStyle.getText().setText('' + numRiddle);
                        defaultRiddleStyle.getText().setText('' + numRiddle);
                    }
                    // if there is no level or its one we don't recognize,
                    // return the default style (in an array!)
                    if (selectedRiddleFeatures[feature.getId()]) {
                        return [selectedRiddleStyle];
                    }
                    // check the cache and create a new style for the income
                    // level if its not been created before.
                    // at this point, the style for the current level is in the cache
                    // so return it (as an array!)
                    return [defaultRiddleStyle];
                }





                function fetchFeatures(idScavengerhunt) {
                    var geojson = ajax.call([{
                            methodname: 'mod_scavengerhunt_fetch_scavengerhunt',
                            args: {
                                idScavengerhunt: idScavengerhunt
                            }
                        }]);
                    geojson[0].done(function (response) {
                        console.log('json: ' + response.scavengerhunt.riddles + response.scavengerhunt.roads);
                        var vector;
                        var geoJSONFeatures = response.scavengerhunt.riddles;
                        var jsonRoads = response.scavengerhunt.roads;
                        var geoJSON = new ol.format.GeoJSON();
                        var roads = JSON.parse(jsonRoads);
                        if (roads.constructor !== Array) {
                            $.extend(stage["roads"], roads);
                        }
                        //agrego los vectores a cada camino
                        for (var road in stage["roads"]) {
                            if (stage["roads"].hasOwnProperty(road)) {
                                makeRoadLisPanel(stage["roads"][road].id, stage["roads"][road].name);
                                vector = new ol.layer.Vector({
                                    source: new ol.source.Vector({
                                        projection: 'EPSG:3857'
                                    }),
                                    updateWhileAnimating: true,
                                    style: styleFunction
                                });
                                stage["roads"][road].vector = vector;
                                map.addLayer(vector);
                            }
                        }
                        //Add stage features to source originalStage
                        originalStage.addFeatures(geoJSON.readFeatures(geoJSONFeatures, {
                            'dataProjection': "EPSG:4326",
                            'featureProjection': "EPSG:3857"
                        }));

                        originalStage.getFeatures().forEach(function (feature) {
                            if (feature.getGeometry() === null) {
                                feature.setGeometry(new ol.geom.MultiPolygon([]));
                            }
                            var polygons = feature.getGeometry().getPolygons();
                            var idNewFeatures = 'empty';
                            var idRiddle = feature.getId();
                            var idRoad = feature.get('idRoad');
                            var numRiddle = feature.get('numRiddle');
                            var name = feature.get('name');
                            var description = feature.get('description');
                            for (var i = 0; i < polygons.length; i++) {
                                var newFeature = new ol.Feature(feature.getProperties());
                                newFeature.setProperties({
                                    'idRiddle': idRiddle
                                });
                                var polygon = polygons[i];
                                newFeature.setGeometry(polygon);
                                newFeature.setId(idNewFeature);
                                if (i === 0) {
                                    idNewFeatures = idNewFeature;
                                } else {
                                    idNewFeatures = idNewFeatures + ',' + idNewFeature;
                                }
                                idNewFeature++;
                                stage["roads"][idRoad].vector.getSource().addFeature(newFeature);
                            }
                            feature.setProperties({
                                idFeaturesPolygons: '' + idNewFeatures
                            });
                            makeRiddleListPanel(idRiddle, idRoad, numRiddle, name, description);
                            if (polygons.length === 0) {
                                emptyRiddle(idRiddle);
                            }
                        });
                        //Ordeno la lista de pistas
                        sortList();
                        //Selecciono el primer camino 
                        for (var road in stage["roads"]) {
                            if (stage["roads"].hasOwnProperty(road)) {
                                idRoad = road;
                                selectRoad(road, stage["roads"][road].vector, map);
                                break;
                            }
                        }

                    }).fail(function (error) {
                        console.log(error);
                        notification.alert('Error', error.message, 'Continue');
                    });
                }


                /** Panel functions ***************************************************************
                 */
                function removeFeatures(selectedFeatures, vector) {
                    selectedFeatures.forEach(function (feature) {
                        vector.getSource().removeFeature(feature);
                    });
                    selectedFeatures.clear();

                }

                function makeRiddleListPanel(idRiddle, idRoad, numRiddle, name, description) {
                    if ($('#riddleList li[idRiddle="' + idRiddle + '"]').length < 1) {
                        $('<li idRiddle="' + idRiddle + '" idRoad="' + idRoad + '" numRiddle="' + numRiddle + '"/>').appendTo($("#riddleList")).addClass("ui-corner-all").prepend("<div class='handle'><span class='ui-icon ui-icon-arrowthick-2-n-s'></span><span class='sortable-number'>" + numRiddle + "</span></div>").append("<div class='nameRiddle'>" + name + "</div>").append("<div class='modifyRiddle'><span class='ui-icon ui-icon-trash'></span><span class='ui-icon ui-icon-pencil'></span><span class='ui-icon ui-icon-info' title='<h1>Description:</h1>" + description + "'></span></div>");
                    } else {
                        console.log('El li con ' + idRiddle + ' no ha podido crearse porque ya existia uno');
                    }
                }

                function makeRoadLisPanel(idRoad, name) {
                    //Si no existe lo agrego
                    if ($('#roadList li[idRoad="' + idRoad + '"]').length < 1) {
                        $('<li idRoad="' + idRoad + '"/>').appendTo($("#roadList")).addClass("ui-corner-all").append("<div class='nameRoad'>" + name + "</div>").append("<div class='modifyRoad'><span class='ui-icon ui-icon-trash'></span><span class='ui-icon ui-icon-pencil'></span></div>");
                    }
                }

                function sortList() {
                    //Ordeno la lista 
                    $('#riddleList li').sort(function (a, b) {
                        var contentA = parseInt($(a).attr('numRiddle'));
                        var contentB = parseInt($(b).attr('numRiddle'));
                        return (contentA < contentB) ? 1 : (contentA > contentB) ? -1 : 0;
                    }).appendTo($("#riddleList"));
                }

                function emptyRiddle(idRiddle) {
                    $('#riddleList li[idRiddle="' + idRiddle + '"]').children(".modifyRiddle").append("<span class='ui-icon ui-icon-alert'  title='" + Strings['empty_ridle'] + "'></span>");
                }

                function notEmptyRiddle(idRiddle) {
                    $('#riddleList li[idRiddle="' + idRiddle + '"]').find(".ui-icon-alert").remove();
                }

                /** TOOLTIPS **/
                $("#riddleList").tooltip({
                    track: true,
                    items: '.ui-icon-alert, .ui-icon-info',
                    position: {
                        my: "left+15 center",
                        at: "right center"
                    },
                    content: function () {
                        return $(this).prop('title');
                    }
                });
                $('.ol-zoom-in, .ol-zoom-out,.ol-rotate-reset, .ol-attribution').tooltip({
                    position: {
                        my: "left+15 center",
                        at: "right center"
                    }
                });
                function activateDeleteButton() {
                    $('#removeFeature').button("option", "disabled", false);

                }
                function deactivateDeleteButton() {
                    $('#removeFeature').button("option", "disabled", true);

                }

                function deactivateEdition() {
                    var radioButton = $("#edition").find("input:radio");
                    radioButton.attr('checked', false).button("refresh");
                    radioButton.button("option", "disabled", true);
                    Draw.setActive(false);
                    Modify.setActive(false);
                }

                function activateEdition() {
                    $("#edition").find("input:radio").button("option", "disabled", false);
                }
                function activateSaveButton() {
                    $('#saveRiddle').button("option", "disabled", false);
                }
                function deactivateSaveButton() {
                    $('#saveRiddle').button("option", "disabled", true);
                }


                function flyTo(map, extent) {
                    var duration = 500;
                    var view = map.getView();
                    var size = map.getSize();
                    var pan = ol.animation.pan({
                        duration: duration,
                        source: /** @type {ol.Coordinate} */
                                (view.getCenter()),
                    });
                    var zoom = ol.animation.zoom({
                        duration: duration,
                        resolution: view.getResolution(),
                    });

                    map.beforeRender(pan, zoom);
                    view.fit(extent, size);
                }
                function flyToPoint(map, point) {
                    var duration = 500;
                    var view = map.getView();
                    var pan = ol.animation.pan({
                        duration: duration,
                        source: /** @type {ol.Coordinate} */
                                (view.getCenter()),
                    });
                    var zoom = ol.animation.zoom({
                        duration: duration,
                        resolution: view.getResolution(),
                    });

                    map.beforeRender(pan, zoom);
                    view.setCenter(point);
                }

                function selectRoad(idRoad, vectorOfPolygons, map) {

                    //Limpio todas las features seleccionadas,oculto todos los li y solo muestro los que tengan el idRoad 
                    //selectRiddleFeatures(vectorOfPolygons, null, selectedFeatures);
                    $("#riddleList li").removeClass("ui-selected").hide();
                    $("#riddleList li[idRoad='" + idRoad + "']").show();
                    //Si no esta marcado el li road lo marco
                    $("#roadList li[idRoad='" + idRoad + "']").addClass("ui-selected");
                    //Dejo visible solo el vector con el idRoad
                    map.getLayers().forEach(function (layer) {
                        if (layer instanceof ol.layer.Vector) {
                            layer.setVisible(false);
                        }
                    });
                    vectorOfPolygons.setVisible(true);
                    if (vectorOfPolygons.getSource().getFeatures().length > 0) {
                        flyTo(map, vectorOfPolygons.getSource().getExtent());
                    }
                }

                //Revisar funcion por si se puede mejorar, tipo coger los ids de originalStage o dirtyStage y marcarlos como selected
                function selectRiddleFeatures(vectorOfPolygons, selected, selectedFeatures, dirtySource, originalSource) {
                    var vectorSelected = new ol.layer.Vector({
                        source: new ol.source.Vector({
                            projection: 'EPSG:3857'
                        })
                    });
                    //Deselecciono cualquier feature anterior
                    selectedFeatures.clear();
                    //Reinicio el objeto
                    selectedRiddleFeatures = new Object();
                    var feature = dirtySource.getFeatureById(selected);
                    if (!feature) {
                        feature = originalSource.getFeatureById(selected);
                        if (!feature) {
                            //Incremento la version para que se recargue el mapa y se deseleccione la marcada anteriormente
                            vectorOfPolygons.changed();
                            return;
                        }
                    }
                    if (feature.get('idFeaturesPolygons') === 'empty') {
                        //Incremento la version para que se recargue el mapa y se deseleccione la marcada anteriormente
                        vectorOfPolygons.changed();
                        return;
                    }
                    //Agrego a mi objecto que almacena los poligonos seleccionados y tambien agrego al vector al que se le aplica la animacion
                    var idFeaturesPolygons = feature.get('idFeaturesPolygons').split(",");
                    for (var i = 0, j = idFeaturesPolygons.length; i < j; i++) {
                        vectorSelected.getSource().addFeature(vectorOfPolygons.getSource().getFeatureById(idFeaturesPolygons[i]).clone());
                        selectedRiddleFeatures[idFeaturesPolygons[i]] = true;
                    }
                    //Coloco el mapa en la posicion de las pistas seleccionadas si la pista contiene alguna feature y 
                    //postergando el tiempo para que seleccione la nueva feature.
                    if (vectorSelected.getSource().getFeatures().length) {
                        flyTo(map, vectorSelected.getSource().getExtent());
                    }
                }


                function relocateNumRiddle(idRiddle, numRiddle, idRoad, dirtySource, originalSource, vector) {
                    var feature = dirtySource.getFeatureById(idRiddle);
                    var idFeaturesPolygons;
                    if (!feature) {
                        feature = originalSource.getFeatureById(idRiddle).clone();
                        feature.setId(idRiddle);
                        dirtySource.addFeature(feature);
                    }
                    feature.setProperties({
                        'numRiddle': numRiddle
                    });
                    if (feature.get('idFeaturesPolygons') !== 'empty') {
                        idFeaturesPolygons = feature.get('idFeaturesPolygons').split(",");
                        for (var i = 0, j = idFeaturesPolygons.length; i < j; i++) {
                            vector.getSource().getFeatureById(idFeaturesPolygons[i]).setProperties({
                                'numRiddle': numRiddle
                            });

                        }
                    }
                }


                function editFormEntry(idRiddle, idModule) {
                    var url = 'edit.php?cmid=' + idModule + '&id=' + idRiddle;
                    window.location.href = url;
                }

                function newFormEntry(idRoad, idModule) {
                    var url = "edit.php?cmid=" + idModule + "&road_id=" + idRoad;
                    window.location.href = url;
                }

                function addRoad(idScavengerhunt) {
                    var json = ajax.call([{
                            methodname: 'mod_scavengerhunt_add_road',
                            args: {
                                nameRoad: '',
                                idScavengerhunt: idScavengerhunt
                            }
                        }]);
                    json[0].done(function (response) {
                        console.log(response);
                        var idRoad = response.road.id;
                        var nameRoad = response.road.name;
                        var vector = new ol.layer.Vector({
                            source: new ol.source.Vector({
                                projection: 'EPSG:3857'
                            }),
                            updateWhileAnimating: true,
                            style: styleFunction
                        });
                        var road = {};
                        road[idRoad] = {'id': idRoad, 'name': nameRoad, 'vector': vector};
                        $.extend(stage["roads"], road);
                        map.addLayer(vector);
                        makeRoadLisPanel(idRoad, nameRoad);
                    }).fail(function (error) {
                        console.log(error);
                        notification.alert('Error', error.message, 'Continue');
                    });
                }
                function updateRoad(idRoad, nameRoad, $road, idScavengerhunt) {
                    var json = ajax.call([{
                            methodname: 'mod_scavengerhunt_update_road',
                            args: {
                                nameRoad: nameRoad,
                                idRoad: idRoad,
                                idScavengerhunt: idScavengerhunt
                            }
                        }]);
                    json[0].done(function (response) {
                        console.log(response);
                        $road.text(nameRoad);
                    }).fail(function (error) {
                        console.log(error);
                        notification.alert('Error', error.message, 'Continue');
                    });
                }
                
                function deleteRoad(idRoad, dirtySource, originalSource, idScavengerhunt) {
                    var json = ajax.call([{
                            methodname: 'mod_scavengerhunt_delete_road',
                            args: {
                                idRoad: idRoad,
                                idScavengerhunt: idScavengerhunt
                            }
                        }]);
                    json[0].done(function (response) {
                        console.log(response);
                        var lis = $('#riddleList li[idRoad="' + idRoad + '"]');
                        var li = $('#roadList li[idRoad="' + idRoad + '"]');
                        //Elimino el li del roadList
                        li.remove();
                        //Elimino todos los li del riddleList
                        lis.remove();
                        //Elimino la feature de dirtySource si la tuviese, del originalSource y elimino el camino del stage y la capa del mapa
                        map.removeLayer(stage["roads"][idRoad].vector);
                        delete stage["roads"][idRoad];
                        var features = originalSource.getFeatures();
                        for (var i = 0; i < features.length; i++) {
                            if (idRoad === features[i].get('idRoad'))
                            {
                                var dirtyFeature = dirtySource.getFeatureById(features[i].getId());
                                if (dirtyFeature) {
                                    dirtySource.removeFeature(dirtyFeature);
                                }
                                originalSource.removeFeature(features[i]);
                            }
                        }
                        deactivateEdition();
                        /*//Guardo el escenario
                         saveRiddles(dirtySource, originalSource);
                         $("#saveRiddle").button("option", "disabled", true);
                         dirty = false;*/
                    }).fail(function (error) {
                        console.log(error);
                        notification.alert('Error', error.message, 'Continue');
                    });
                }

                function deleteRiddle(idRiddle, dirtySource, originalSource, vectorOfPolygons, idScavengerhunt) {
                    var json = ajax.call([{
                            methodname: 'mod_scavengerhunt_delete_riddle',
                            args: {
                                idRiddle: idRiddle,
                                idScavengerhunt: idScavengerhunt
                            }
                        }]);
                    json[0].done(function (response) {
                        console.log(response);
                        var idFeaturesPolygons = false;
                        var polygonFeature;
                        var feature = dirtySource.getFeatureById(idRiddle);
                        var li = $('#riddleList li[idRiddle="' + idRiddle + '"]');
                        var idRoad = li.attr('idRoad');
                        var start_pos = li.index('li[idRoad="' + idRoad + '"]');
                        //Elimino el li
                        li.remove();
                        //Recoloco el resto
                        var $listitems = $("#riddleList").children('li[idRoad="' + idRoad + '"]');
                        var $listlength = $($listitems).length;
                        for (var i = 0; i <= start_pos - 1; i++) {
                            relocateRiddleList($listitems, $listlength, i, dirtySource, originalSource, vectorOfPolygons);
                        }
                        //Elimino la feature de dirtySource si la tuviese y todos los poligonos del vector de poligonos
                        if (!feature) {
                            feature = originalSource.getFeatureById(idRiddle);
                            if (feature.get('idFeaturesPolygons') !== 'empty') {
                                idFeaturesPolygons = feature.get('idFeaturesPolygons').split(",");
                            }
                            originalSource.removeFeature(feature);
                        } else {
                            if (feature.get('idFeaturesPolygons') !== 'empty') {
                                idFeaturesPolygons = feature.get('idFeaturesPolygons').split(",");
                            }
                            dirtySource.removeFeature(feature);
                        }
                        if (idFeaturesPolygons) {
                            for (var i = 0, j = idFeaturesPolygons.length; i < j; i++) {
                                polygonFeature = vectorOfPolygons.getSource().getFeatureById(idFeaturesPolygons[i]);
                                vectorOfPolygons.getSource().removeFeature(polygonFeature);
                            }
                        }

                        /*//Guardo el escenario
                         saveRiddles(dirtySource, originalSource);
                         $("#saveRiddle").button("option", "disabled", true);
                         dirty = false;*/

                    }).fail(function (error) {
                        console.log(error);
                        notification.alert('Error', error.message, 'Continue');
                    });
                }

                function saveRiddles(dirtySource, originalSource, idScavengerhunt) {
                    var geoJSONFormat = new ol.format.GeoJSON();
                    var geoJSON = geoJSONFormat.writeFeatures(dirtySource.getFeatures(), {
                        'dataProjection': "EPSG:4326",
                        'featureProjection': "EPSG:3857"
                    });
                    var json = ajax.call([{
                            methodname: 'mod_scavengerhunt_update_riddles',
                            args: {
                                riddles: geoJSON,
                                idScavengerhunt: idScavengerhunt
                            }
                        }]);
                    json[0].done(function (response) {
                        console.log(response);
                        var originalFeature;
                        //Paso las features "sucias" al objeto con las features originales
                        dirtySource.forEachFeature(function (feature) {
                            originalFeature = originalSource.getFeatureById(feature.getId());
                            originalFeature.setProperties(feature.getProperties());
                            originalFeature.setGeometry(feature.getGeometry());
                        });
                        //Limpio mi objeto que guarda las features sucias
                        dirtySource.clear();
                        //Desactivo el boton de guardar
                        deactivateSaveButton();
                        dirty = false;
                    }).fail(function (error) {
                        console.log(error);
                        notification.alert('Error', error.message, 'Continue');
                    });
                }

                $("#searchAddress").autocomplete({
                    minLength: 4,
                    source: function (request, response) {
                        var term = request.term;
                        openStreetMapGeocoder.geocode(term, function (data) {
                            if (!data[0]) {
                                response();
                                return;
                            }
                            var total = new Array();
                            for (var i = 0, l = data.length; i < l; i++) {
                                var latitude;
                                var longitude;
                                latitude = data[i].getLatitude();
                                longitude = data[i].getLongitude();
                                var result = {"value": data[i].totalName, "latitude": latitude, "longitude": longitude, "boundingbox": data[i].boundingbox};
                                total[i] = result;
                            }
                            response(total);
                        });
                    },
                    select: function (event, ui) {
                        if (ui.item.boundingbox) {
                            var extend = new Array();
                            extend[0] = parseFloat(ui.item.boundingbox[2]);
                            extend[1] = parseFloat(ui.item.boundingbox[0]);
                            extend[2] = parseFloat(ui.item.boundingbox[3]);
                            extend[3] = parseFloat(ui.item.boundingbox[1]);
                            extend = ol.proj.transformExtent(extend, 'EPSG:4326', 'EPSG:3857');
                            flyTo(map, extend);
                        } else {
                            var point = ol.proj.fromLonLat([ui.item.longitude, ui.item.latitude]);
                            flyToPoint(map, point);
                        }
                    },
                    autoFocus: true,
                    position: {my: "left top", at: "left bottom", collision: "fit"}
                }).on("click", function () {
                    $(this).autocomplete("search", $(this).value);
                });
                ;
                $("#addRiddle").on('click', function () {
                    var numRiddle = $('#riddleList li[idRoad="' + idRoad + '"]').length;
                    //Si estÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ sucio guardo el escenario
                    if (dirty) {
                        saveRiddles(dirtyStage, originalStage, idScavengerhunt);
                    }
                    newFormEntry(idRoad, idModule);

                });
                $("#addRoad").on('click', function () {
                    addRoad(idScavengerhunt);
                });

                $("#removeFeature").on('click', function () {
                    notification.confirm('Estas seguro?', 'Si la eliminas ya no podras recuperarla', 'Confirmar', 'Cancelar', function () {
                        removeFeatureToDirtySource(selectedFeatures, originalStage, dirtyStage, stage["roads"][idRoad].vector);
                        removeFeatures(selectedFeatures, stage["roads"][idRoad].vector);
                    });
                    //Desactivo el boton de borrar y activo el de guardar cambios
                    deactivateDeleteButton();
                    activateSaveButton();
                    dirty = true;
                });
                $("#saveRiddle").on('click', function () {
                    saveRiddles(dirtyStage, originalStage, idScavengerhunt);
                });
                $("#riddleList").on('click', '.ui-icon-trash', function () {
                    var $this_li = $(this).parents('li');
                    notification.confirm('Estas seguro?', 'Si la eliminas ya no podras recuperarla', 'Confirmar', 'Cancelar', function () {
                        var idRiddle = parseInt($this_li.attr('idRiddle'));
                        deleteRiddle(idRiddle, dirtyStage, originalStage, stage["roads"][idRoad].vector, idScavengerhunt);

                    });
                });
                $("#riddleList").on('click', '.ui-icon-pencil', function () {
                    //Busco el idRiddle del li que contiene la papelera seleccionada
                    var idRiddle = parseInt($(this).parents('li').attr('idRiddle'));
                    //Si estÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ sucio guardo el escenario
                    if (dirty) {
                        saveRiddles(dirtyStage, originalStage, idScavengerhunt);
                    }
                    editFormEntry(idRiddle, idModule);

                });
                $("input[name=controlPanel]:radio").on('change', function () {
                    var selected = $("input[type='radio'][name='controlPanel']:checked");
                    var value = selected.val();
                    if (value === 'add') {
                        Draw.setActive(true);
                        Modify.setActive(false);
                    } else if (value === 'modify') {
                        Draw.setActive(false);
                        Modify.setActive(true);
                    } else {
                        Draw.setActive(false);
                        Modify.setActive(false);
                    }
                });
                $("#riddleList").on('click', 'li', function (e) {
                    if ($(e.target).is('.handle , .ui-icon , .sortable-number')) {
                        e.preventDefault();
                        return;
                    }
                    $(this).addClass("ui-selected").siblings().removeClass("ui-selected");
                    //Selecciono el idRiddle de mi atributo custom
                    numRiddle = parseInt($(this).attr('numriddle'));
                    idRiddle = parseInt($(this).attr('idriddle'));
                    //Borro la anterior seleccion de features y busco las del mismo tipo
                    selectRiddleFeatures(stage["roads"][idRoad].vector, idRiddle, selectedFeatures, dirtyStage, originalStage);
                    activateEdition();
                    //Paro de dibujar si cambio de pista
                    Draw.Polygon.abortDrawing_();
                });


                $("#roadList").on('click', 'li', function (e) {
                    if ($(e.target).is('.handle , .ui-icon')) {
                        e.preventDefault();
                        return;
                    }
                    $(this).addClass("ui-selected").siblings().removeClass("ui-selected");
                    //Selecciono el idRiddle de mi atributo custom
                    //Borro las pistas seleccionadas
                    selectedRiddleFeatures = new Object();
                    //Paro de dibujar si cambio de camino
                    Draw.Polygon.abortDrawing_();
                    idRoad = $(this).attr('idRoad');
                    selectRoad(idRoad, stage["roads"][idRoad].vector, map);
                    deactivateEdition();
                });
                $("#roadList").on('click', '.ui-icon-pencil', function () {
                    var $div = $(this).parents("li").children(".nameRoad");
                    var width = $div.width();
                    oriVal = $div.text();
                    $div.text("");
                    $("<input type='text' id='modRoadName'>").val(oriVal).width(width).appendTo($div).focus();
                });
                $("#roadList").on('focusout', 'li .nameRoad > input', function () {
                    var $this = $(this);
                    var idRoad = $this.parents('li').attr('idRoad');
                    var nameRoad = $this.val();
                    if (nameRoad !== oriVal && nameRoad !== '') {
                        updateRoad(idRoad, nameRoad, $this.parent(), idScavengerhunt);
                    }
                    $this.parent().text(oriVal);
                    $this.remove();
                });
                $("#roadList").on('click', '.ui-icon-trash', function () {
                    var $this_li = $(this).parents('li');
                    notification.confirm('Estas seguro?', 'Si la eliminas se eliminaran todas las pitas asociadas y ya no podras recuperarlas', 'Confirmar', 'Cancelar', function () {
                        var idRoad = parseInt($this_li.attr('idRoad'));
                        deleteRoad(idRoad, dirtyStage, originalStage, idScavengerhunt);
                    });
                });

                map.on('pointermove', function (evt) {
                    if (evt.dragging || Draw.getActive() || !Modify.getActive()) {
                        return;
                    }
                    var pixel = map.getEventPixel(evt.originalEvent);
                    var hit = map.forEachFeatureAtPixel(pixel, function (feature, layer) {
                        if (selectedRiddleFeatures[feature.getId()]) {
                            var selected = false;
                            selectedFeatures.forEach(function (featureSelected) {
                                if (feature === featureSelected) {
                                    selected = true;
                                }
                            });
                            return selected ? false : true;
                        }
                        return false;
                    });
                    map.getTargetElement().style.cursor = hit ? 'pointer' : '';
                });
                // /////
                // CLEARABLE INPUT
                function tog(v) {
                    return v ? 'addClass' : 'removeClass';
                }
                $(document).on('input', '.clearable', function () {
                    $(this)[tog(this.value)]('x');
                }).on('mousemove', '.x', function (e) {
                    $(this)[tog(this.offsetWidth - 18 < e.clientX - this.getBoundingClientRect().left)]('onX');
                }).on('touchstart click', '.onX', function (ev) {
                    ev.preventDefault();
                    $(this).removeClass('x onX').val('').change().autocomplete("close");
                });


            }).fail(function (e) {
                console.log(e);
            });




        } // End of function init
    }; // End of init var
    return init;

});