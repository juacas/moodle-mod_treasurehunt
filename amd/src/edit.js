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
        'jquerytouch': {
            deps: ['jqueryui'],
            exports: '$'
        }
    },
    paths: {
        openlayers: 'openlayers/ol',
        geocoderjs: 'geocoder/geocoder',
        'jquerytouch': 'jquery-ui-touch-punch/jquery-ui-touch-punch.min'
    }
});
define(['jquerytouch', 'core/notification', 'core/str', 'openlayers', 'jqueryui', 'core/ajax', 'geocoderjs', 'core/templates'],
        function ($, notification, str, ol, jqui, ajax, GeocoderJS, templates) {


            var init = {
                edittreasurehunt: function (idModule, treasurehuntid, strings, selectedroadid, lockid) {
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
                    var noriddle;
                    var roadid;
                    var riddleid;
                    var selectedFeatures;
                    var selectedRiddleFeatures = {};
                    var idNewFeature = 1;
                    var vectorSelected = new ol.layer.Vector({
                        source: new ol.source.Vector({
                            projection: 'EPSG:3857'
                        })
                    });
                    var openStreetMapGeocoder = GeocoderJS.createGeocoder('openstreetmap');
                    /**Initialize stage and selectedRiddleFeatures******************************************************
                     */

                    /**Load the control pane, riddle and road list ***************************************************
                     */
                    $("#controlpanel").addClass('ui-widget-header ui-corner-all');
                    $('<span id="edition"/>').appendTo($("#controlpanel"));
                    $('<input type="radio" name="controlpanel" id="radio1" value="add">').appendTo($("#edition"));
                    $("<label>").attr('for', "radio1").text('Anadir').appendTo($("#edition"));
                    $('<input type="radio" name="controlpanel" id="radio2" value="modify">').appendTo($("#edition"));
                    $("<label>").attr('for', "radio2").text('Modificar').appendTo($("#edition"));
                    $('<button id="saveriddle"/>').attr('disabled', true).text('Guardar cambios').appendTo($("#controlpanel"));
                    $('<button id="removefeature"/>').attr('disabled', true).text('Eliminar').appendTo($("#controlpanel"));
                    $('<div id="searchcontainer">').appendTo($("#controlpanel"));
                    $('<input id="searchaddress" type="search" placeholder="Enter a Location" class="clearable"/>')
                            .appendTo($("#searchcontainer"));
                    $('<span id="searchicon" class="ui-icon  ui-icon-search"></span>').appendTo($("#searchcontainer"));
                    $('<button id="addriddle"/>').text('Riddle').prependTo($("#controlpanel"));
                    $('<button id="addroad"/>').text('Road').prependTo($("#controlpanel"));
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
                    $("#removefeature").button({
                        text: false,
                        icons: {
                            primary: "ui-icon-trash"
                        }
                    });
                    $("#saveriddle").button({
                        text: false,
                        icons: {
                            primary: "ui-icon-disk"
                        }
                    });
                    $("#addriddle").button({
                        icons: {
                            primary: "ui-icon-circle-plus"
                        }
                    });
                    $("#addroad").button({
                        icons: {
                            primary: "ui-icon-circle-plus"
                        }
                    });
                    //Lo cargo como un buttonset
                    $("#edition").buttonset();
                    //Creo el riddlelistpanel
                    $('<ul id="riddlelist"/>').prependTo($("#riddlelistpanel"));
                    //Lo cargo como un sortable
                    $("#riddlelist").sortable({
                        handle: ".handle",
                        tolerance: "pointer",
                        zIndex: 9999,
                        opacity: 0.5,
                        forcePlaceholderSize: true,
                        cursorAt: {top: -7},
                        cursor: "n-resize",
                        axis: 'y',
                        items: "li:not(:hidden , .blocked)",
                        helper: "clone",
                        start: function (event, ui) {
                            var roadid = ui.item.attr('roadid');
                            var start_pos = ui.item.index('li[roadid="' + roadid + '"]');
                            ui.item.data('start_pos', start_pos);
                            //set max scrollTop for sortable scrolling
                            var scrollParent = $(this).data("ui-sortable").scrollParent;
                            var maxScrollTop = scrollParent[0].scrollHeight - scrollParent[0].clientHeight - ui.helper.height();
                            $(this).data('maxScrollTop', maxScrollTop);
                        },
                        sort: function (e, ui) {
                            //check if scrolling is out of boundaries
                            var scrollParent = $(this).data("ui-sortable").scrollParent,
                                    maxScrollTop = $(this).data('maxScrollTop');
                            if (scrollParent.scrollTop() > maxScrollTop) {
                                scrollParent.scrollTop(maxScrollTop);
                            }
                        },
                        update: function (event, ui) {
                            var start_pos = ui.item.data('start_pos');
                            var roadid = ui.item.attr('roadid');
                            var end_pos = ui.item.index('li[roadid="' + roadid + '"]');
                            var $listitems = $(this).children('li[roadid="' + roadid + '"]');
                            var $listlength = $($listitems).length;
                            var i;
                            if (start_pos === end_pos) {
                                return;
                            }
                            if (start_pos < end_pos) {
                                for (i = start_pos; i <= end_pos; i++) {
                                    relocateRiddleList($listitems, $listlength, i,
                                            dirtyStage, originalStage, stage.roads[roadid].vector);
                                }
                            } else {
                                for (i = end_pos; i <= start_pos; i++) {
                                    relocateRiddleList($listitems, $listlength, i,
                                            dirtyStage, originalStage, stage.roads[roadid].vector);
                                }
                            }
                            activateSaveButton();
                            dirty = true;
                        }
                    }).disableSelection();
                    function relocateRiddleList($listitems, $listlength, i, dirtyStage, originalStage, vector) {
                        var newVal;
                        var $item = $($listitems).get([i]);
                        var roadid = $($item).attr('roadid');
                        newVal = Math.abs($($item).index('li[roadid="' + roadid + '"]') - $listlength);
                        $($item).attr('noriddle', newVal);
                        $($item).find('.sortable-number').text(newVal);
                        //Si esta seleccionado cambiamos el valor de noriddle
                        if ($($item).hasClass("ui-selected")) {
                            noriddle = newVal;
                        }
                        relocatenoriddle(parseInt($($item).attr('riddleid')),
                                newVal, parseInt($($item).attr('roadid')), dirtyStage, originalStage, vector);
                    }

                    //Creo el roadlistpanel
                    $('<ul id="roadlist"/>').appendTo($("#roadlistpanel"));
                    //Anado los handle custom
                    /*Set control
                     * 
                     * @type edit_L27.ol.style.Style
                     */
                    window.app = {};
                    var app = window.app;
                    /**
                     * @constructor
                     * @extends {ol.control.Control}
                     * @param {Object=} opt_options Control options.
                     */
                    app.generateResizableControl = function (opt_options) {
                        var options = opt_options || {};
                        var button = document.createElement('button');
                        button.innerHTML = '<>';
                        button.id = 'egrip';
                        var element = document.createElement('div');
                        element.className = 'ol-control ol-unselectable egrip-container';
                        element.appendChild(button);
                        ol.control.Control.call(this, {
                            element: element,
                            target: options.target
                        });
                    };
                    ol.inherits(app.generateResizableControl, ol.control.Control);
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
                        target: 'mapedit',
                        view: new ol.View({
                            center: [0, 0],
                            zoom: 2,
                            minZoom: 2
                        }),
                        controls: ol.control.defaults().extend([
                            new app.generateResizableControl({target: document.getElementById("riddlelistpanel")})
                        ])
                    });
                    //Creo el resizable
                    $("#riddlelistpanel").resizable({
                        handles: {'e': $('#egrip')},
                        resize: function (event, ui) {
                            ui.size.height = ui.originalSize.height;
                        },
                        stop: function (event, ui) {
                            map.updateSize();
                        },
                        cancel: ''
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
                                                text: '' + feature.get('noriddle'),
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
                                selectedFeatures.clear();
                                deactivateDeleteButton();
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
                                modifyFeatureToDirtySource(e.features, originalStage, dirtyStage, stage.roads[roadid].vector);
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
                                    'roadid': roadid,
                                    'riddleid': riddleid,
                                    'noriddle': noriddle
                                });
                                selectedRiddleFeatures[idNewFeature] = true;
                                e.feature.setId(idNewFeature);
                                idNewFeature++;
                                //Agrego la nueva feature a su correspondiente vector de poligonos
                                stage.roads[roadid].vector.getSource().addFeature(e.feature);
                                //Agrego la feature a la coleccion de multipoligonos sucios
                                addNewFeatureToDirtySource(e.feature, originalStage, dirtyStage);
                                //Limpio el vector de dibujo
                                vectorDraw.getSource().clear();
                                activateSaveButton();
                                dirty = true;
                            });
                        },
                        getActive: function () {
                            return this.Polygon.getActive();
                        },
                        setActive: function (active) {
                            if (active) {
                                this.Polygon.setActive(true);
                            } else {
                                this.Polygon.setActive(false);
                            }
                            map.getTargetElement().style.cursor = active ? 'none' : '';
                        }
                    };
                    $(document).keyup(function (e) {
                        //Si pulso la tecla esc dejo de dibujar
                        if (e.keyCode === 27) // esc
                        {
                            Draw.Polygon.abortDrawing();
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
                    fetchFeatures(treasurehuntid);
                    function addNewFeatureToDirtySource(dirtyFeature, originalSource, dirtySource) {

                        var riddleid = dirtyFeature.get('riddleid');
                        var roadid = dirtyFeature.get('roadid');
                        var feature = dirtySource.getFeatureById(riddleid);
                        if (!feature) {
                            feature = originalSource.getFeatureById(riddleid).clone();
                            feature.setId(riddleid);
                            dirtySource.addFeature(feature);
                        }
                        if (feature.get('idFeaturesPolygons') === 'empty') {
                            feature.setProperties({
                                'idFeaturesPolygons': '' + dirtyFeature.getId()
                            });
                            //Quito la advertencia
                            notEmptyRiddle(riddleid, roadid);
                        } else {
                            feature.setProperties({
                                'idFeaturesPolygons': feature.get('idFeaturesPolygons') + ',' + dirtyFeature.getId()
                            });
                        }
                        feature.getGeometry().appendPolygon(dirtyFeature.getGeometry());
                    }


                    function modifyFeatureToDirtySource(dirtyFeatures, originalSource, dirtySource, vector) {

                        dirtyFeatures.forEach(function (dirtyFeature) {
                            var riddleid = dirtyFeature.get('riddleid');
                            var feature = dirtySource.getFeatureById(riddleid);
                            var idFeaturesPolygons;
                            if (!feature) {
                                feature = originalSource.getFeatureById(riddleid).clone();
                                feature.setId(riddleid);
                                dirtySource.addFeature(feature);
                            }
                            var multipolygon = new ol.geom.MultiPolygon([]);
                            //Get those multipolygons of vector layer 
                            idFeaturesPolygons = feature.get('idFeaturesPolygons').split(",");
                            for (var i = 0, j = idFeaturesPolygons.length; i < j; i++) {
                                multipolygon.appendPolygon(vector.getSource().getFeatureById(idFeaturesPolygons[i]).getGeometry().clone());
                            }
                            feature.setGeometry(multipolygon);
                        });
                    }

                    function removefeatureToDirtySource(dirtyFeatures, originalSource, dirtySource, vector) {

                        dirtyFeatures.forEach(function (dirtyFeature) {

                            var riddleid = dirtyFeature.get('riddleid');
                            var roadid = dirtyFeature.get('roadid');
                            var feature = dirtySource.getFeatureById(riddleid);
                            var idFeaturesPolygons;
                            var remove;
                            if (!feature) {
                                feature = originalSource.getFeatureById(riddleid).clone();
                                feature.setId(riddleid);
                                dirtySource.addFeature(feature);
                            }
                            var multipolygon = new ol.geom.MultiPolygon([]);
                            //Get those multipolygons of vector layer which riddleid isn't id of dirtyFeature
                            idFeaturesPolygons = feature.get('idFeaturesPolygons').split(",");
                            for (var i = 0, j = idFeaturesPolygons.length; i < j; i++) {
                                if (idFeaturesPolygons[i] != dirtyFeature.getId()) {
                                    multipolygon.appendPolygon(vector.getSource().getFeatureById(idFeaturesPolygons[i]).getGeometry().clone());
                                } else {
                                    remove = i;
                                }
                            }
                            feature.setGeometry(multipolygon);
                            if (multipolygon.getPolygons().length) {
                                idFeaturesPolygons.splice(remove, 1);
                                feature.setProperties({
                                    'idFeaturesPolygons': idFeaturesPolygons.join()
                                });
                            } else {
                                feature.setProperties({
                                    'idFeaturesPolygons': 'empty'
                                });
                                emptyRiddle(riddleid, roadid);

                            }

                        });
                    }

                    function styleFunction(feature) {
                        // get the incomeLevel from the feature properties
                        var noriddle = feature.get('noriddle');
                        if (!isNaN(noriddle)) {
                            selectedRiddleStyle.getText().setText('' + noriddle);
                            defaultRiddleStyle.getText().setText('' + noriddle);
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





                    function fetchFeatures(treasurehuntid) {
                        var geojson = ajax.call([{
                                methodname: 'mod_treasurehunt_fetch_treasurehunt',
                                args: {
                                    treasurehuntid: treasurehuntid
                                }
                            }]);
                        geojson[0].done(function (response) {
                            console.log('json: ' + response.treasurehunt.riddles + response.treasurehunt.roads);
                            if (response.status.code) {
                                notification.alert('Error', response.status.msg, 'Continue');
                            } else {
                                var vector;
                                var geoJSONFeatures = response.treasurehunt.riddles;
                                var jsonRoads = response.treasurehunt.roads;
                                var geoJSON = new ol.format.GeoJSON();
                                var roads = JSON.parse(jsonRoads);
                                if (roads.constructor !== Array) {
                                    $.extend(stage.roads, roads);
                                }
                                //agrego los vectores a cada camino
                                for (var road in stage.roads) {
                                    if (stage.roads.hasOwnProperty(road)) {
                                        addroad2ListPanel(stage.roads[road].id, stage.roads[road].name,
                                                stage.roads[road].blocked);
                                        vector = new ol.layer.Vector({
                                            source: new ol.source.Vector({
                                                projection: 'EPSG:3857'
                                            }),
                                            updateWhileAnimating: true,
                                            style: styleFunction
                                        });
                                        stage.roads[road].vector = vector;
                                        map.addLayer(vector);
                                    }
                                }
                                //Add stage features to source originalStage
                                originalStage.addFeatures(geoJSON.readFeatures(geoJSONFeatures, {
                                    dataProjection: 'EPSG:4326',
                                    featureProjection: 'EPSG:3857'
                                }));
                                originalStage.getFeatures().forEach(function (feature) {
                                    if (feature.getGeometry() === null) {
                                        feature.setGeometry(new ol.geom.MultiPolygon([]));
                                    }
                                    var polygons = feature.getGeometry().getPolygons();
                                    var idNewFeatures = 'empty';
                                    var riddleid = feature.getId();
                                    var roadid = feature.get('roadid');
                                    var noriddle = feature.get('noriddle');
                                    var name = feature.get('name');
                                    var description = feature.get('description');
                                    var blocked = stage.roads[roadid].blocked;
                                    for (var i = 0; i < polygons.length; i++) {
                                        var newFeature = new ol.Feature(feature.getProperties());
                                        newFeature.setProperties({
                                            'riddleid': riddleid
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
                                        stage.roads[roadid].vector.getSource().addFeature(newFeature);
                                    }
                                    feature.setProperties({
                                        idFeaturesPolygons: '' + idNewFeatures
                                    });
                                    addriddle2ListPanel(riddleid, roadid, noriddle, name, description, blocked);
                                    if (polygons.length === 0) {
                                        emptyRiddle(riddleid);
                                    }
                                });
                                // Ordeno la lista de pistas
                                sortList();
                                // Selecciono el camino seleccionado si existe o sino el primero.
                                if (typeof stage.roads[selectedroadid] !== 'undefined') {
                                    roadid = selectedroadid;
                                    if (stage.roads[roadid].blocked) {
                                        deactivateAddRiddle();
                                    } else {
                                        activateAddRiddle();
                                    }
                                    selectRoad(roadid, stage.roads[roadid].vector, map);
                                } else {
                                    for (var road in stage.roads) {
                                        if (stage.roads.hasOwnProperty(road)) {
                                            roadid = road;
                                            if (stage.roads[roadid].blocked) {
                                                deactivateAddRiddle();
                                            } else {
                                                activateAddRiddle();
                                            }
                                            selectRoad(roadid, stage.roads[roadid].vector, map);
                                            break;
                                        }
                                    }
                                }

                            }
                        }).fail(function (error) {
                            console.log(error);
                            notification.exception(error);
                        });
                    }


                    /** Panel functions ***************************************************************
                     */
                    function removefeatures(selectedFeatures, vector) {
                        selectedFeatures.forEach(function (feature) {
                            vector.getSource().removeFeature(feature);
                        });
                        selectedFeatures.clear();
                    }

                    function addriddle2ListPanel(riddleid, roadid, noriddle, name, description, blocked) {
                        if ($('#riddlelist li[riddleid="' + riddleid + '"]').length < 1) {
                            var li = $('<li riddleid="' + riddleid + '" roadid="' + roadid + '" noriddle="' + noriddle + '"/>')
                                    .appendTo($("#riddlelist"));
                            li.addClass("ui-corner-all")
                                    .append("<div class='riddlename'>" + name + "</div>")
                                    .append("<div class='modifyriddle'>" +
                                            "<span class='ui-icon ui-icon-pencil'></span>" +
                                            "<span class='ui-icon ui-icon-info' data-id='#dialoginfo" + riddleid + "'>" +
                                            "<div id='dialoginfo" + riddleid + "' title='" + name + "'>"
                                            + description + "</div></span></div>");
                            if (blocked) {
                                li.addClass("blocked")
                                        .prepend("<div class='nohandle validriddle'>" +
                                                "<span class='ui-icon ui-icon-locked'></span>" +
                                                "<span class='sortable-number'>" + noriddle + "</span></div>");
                            } else {
                                li.prepend("<div class='handle validriddle'>" +
                                        "<span class='ui-icon ui-icon-arrowthick-2-n-s'></span>" +
                                        "<span class='sortable-number'>" + noriddle + "</span></div>");
                                li.children(".modifyriddle").prepend("<span class='ui-icon ui-icon-trash'></span>");
                            }
                            $('#dialoginfo' + riddleid).dialog({
                                maxHeight: 500,
                                autoOpen: false
                            });
                        } else {
                            console.log('El li con ' + riddleid + ' no ha podido crearse porque ya existia uno');
                        }
                    }

                    function addroad2ListPanel(roadid, name, blocked) {
                        //Si no existe lo agrego
                        if ($('#roadlist li[roadid="' + roadid + '"]').length < 1) {
                            var li = $('<li roadid="' + roadid + '" blocked="' + blocked + '"/>').appendTo($("#roadlist"));
                            li.addClass("ui-corner-all").append("<div class='roadname'>" + name + "</div>")
                                    .append("<div class='modifyroad'><span class='ui-icon ui-icon-trash'></span>" +
                                            "<span class='ui-icon ui-icon-pencil'></span></div>");
                        }

                    }
                    function deleteRoad2ListPanel(roadid) {
                        var $li = $('#roadlist li[roadid="' + roadid + '"]');
                        if ($li.length > 0) {
                            var $lis = $('#riddlelist li[roadid="' + roadid + '"]');
                            //Elimino el li del roadlist
                            $li.remove();
                            //Elimino todos los li del riddlelist
                            $lis.remove();
                        }
                    }
                    function deleteRiddle2ListPanel(riddleid, dirtySource, originalSource, vectorOfPolygons) {
                        var $li = $('#riddlelist li[riddleid="' + riddleid + '"]');
                        if ($li.length > 0) {
                            var roadid = $li.attr('roadid');
                            var start_pos = $li.index('li[roadid="' + roadid + '"]');
                            //Elimino el li
                            $li.remove();
                            var $riddlelist = $("#riddlelist li[roadid='" + roadid + "']");
                            // Compruebo el resto de pistas de la lista.
                            check_riddle_list($riddlelist);
                            var $listlength = $riddlelist.length;
                            //Recoloco el resto
                            for (var i = 0; i <= start_pos - 1; i++) {
                                relocateRiddleList($riddlelist, $listlength, i, dirtySource, originalSource, vectorOfPolygons);
                            }
                        }
                    }
                    function sortList() {
                        //Ordeno la lista 
                        $('#riddlelist li').sort(function (a, b) {
                            var contentA = parseInt($(a).attr('noriddle'));
                            var contentB = parseInt($(b).attr('noriddle'));
                            return (contentA < contentB) ? 1 : (contentA > contentB) ? -1 : 0;
                        }).appendTo($("#riddlelist"));
                    }

                    function emptyRiddle(riddleid, roadid) {
                        var $riddle = $('#riddlelist li[riddleid="' + riddleid + '"]');
                        $riddle.children(".handle,.nohandle").addClass('invalidriddle').removeClass('validriddle');
                        // Compruebo si en este camino hay alguna pista sin geometria.
                        if (roadid) {
                            $("label[for='radio1']").addClass('highlightbutton');
                            var $riddlelist = $("#riddlelist li[roadid='" + roadid + "']");
                            if ($riddlelist.length >= 2) {
                                $("#erremptyriddle").removeClass("invisible");
                            }
                        }
                    }

                    function notEmptyRiddle(riddleid, roadid) {
                        var $riddle = $('#riddlelist li[riddleid="' + riddleid + '"]');
                        $riddle.children(".handle, .nohandle").addClass('validriddle').removeClass('invalidriddle');
                        if (roadid) {
                            // Compruebo si en este camino hay alguna pista sin geometria.
                            $("label[for='radio1']").removeClass('highlightbutton');
                            var $riddlelist = $("#riddlelist li[roadid='" + roadid + "']");
                            if ($riddlelist.find(".invalidriddle").length === 0) {
                                $("#erremptyriddle").addClass("invisible");
                            }
                        }

                    }

                    /** TOOLTIPS 
                     $("#riddlelist").tooltip({
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
                     });**/
                    function activateDeleteButton() {
                        $('#removefeature').button("option", "disabled", false);
                    }
                    function deactivateDeleteButton() {
                        $('#removefeature').button("option", "disabled", true);
                    }
                    function activateAddRiddle() {
                        $('#addriddle').button("option", "disabled", false);
                    }
                    function deactivateAddRiddle() {
                        $('#addriddle').button("option", "disabled", true);
                    }
                    function deactivateEdition() {
                        var radioButton = $("#edition").find("input:radio");
                        radioButton.attr('checked', false).button("refresh");
                        radioButton.button("option", "disabled", true);
                        $("label[for='radio1']").removeClass('highlightbutton');
                        Draw.setActive(false);
                        Modify.setActive(false);
                    }

                    function activateEdition() {
                        $("#edition").find("input:radio").button("option", "disabled", false);
                    }
                    function activateSaveButton() {
                        $('#saveriddle').button("option", "disabled", false);
                    }
                    function deactivateSaveButton() {
                        $('#saveriddle').button("option", "disabled", true);
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
                    function check_riddle_list($riddlelist) {
                        if ($riddlelist.length > 0) {
                            $("#riddlelistpanel").removeClass("invisible");
                            map.updateSize();
                        } else {
                            $("#riddlelistpanel").addClass("invisible");
                            map.updateSize();
                        }
                        if ($riddlelist.length < 2) {
                            $("#addriddle").addClass("highlightbutton");
                            $("#errvalidroad").removeClass("invisible");
                            $("#erremptyriddle").addClass("invisible");
                        } else if ($riddlelist.find(".invalidriddle").length > 0) {
                            $("#addriddle").removeClass("highlightbutton");
                            $("#errvalidroad").addClass("invisible");
                            $("#erremptyriddle").removeClass("invisible");
                        } else {
                            $("#addriddle").removeClass("highlightbutton");
                            $("#errvalidroad").addClass("invisible");
                            $("#erremptyriddle").addClass("invisible");
                        }
                    }
                    function selectRoad(roadid, vectorOfPolygons, map) {
                        //Limpio todas las features seleccionadas,oculto todos los li y solo muestro los que tengan el roadid 
                        $("#riddlelist li").removeClass("ui-selected").hide();
                        var $riddlelist = $("#riddlelist li[roadid='" + roadid + "']");
                        $riddlelist.show();
                        check_riddle_list($riddlelist);
                        //Si no esta marcado el li road lo marco
                        $("#roadlist li[roadid='" + roadid + "']").addClass("ui-selected");
                        //Dejo visible solo el vector con el roadid
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


                    function selectRiddleFeatures(vectorOfPolygons, vectorSelected, selected,
                            selectedFeatures, dirtySource, originalSource) {
                        vectorSelected.getSource().clear();
                        //Deselecciono cualquier feature anterior
                        selectedFeatures.clear();
                        //Reinicio el objeto
                        selectedRiddleFeatures = {};
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
                        // Agrego los polÃƒÂ­gonos a mi objecto que almacena los poligonos seleccionados 
                        // y tambien agrego al vector al que se le aplica la animacion.
                        var idFeaturesPolygons = feature.get('idFeaturesPolygons').split(",");
                        for (var i = 0, j = idFeaturesPolygons.length; i < j; i++) {
                            vectorSelected.getSource()
                                    .addFeature(vectorOfPolygons.getSource().getFeatureById(idFeaturesPolygons[i]).clone());
                            selectedRiddleFeatures[idFeaturesPolygons[i]] = true;
                        }
                        //Coloco el mapa en la posicion de las pistas seleccionadas si la pista contiene alguna feature y 
                        //postergando el tiempo para que seleccione la nueva feature.
                        if (vectorSelected.getSource().getFeatures().length) {
                            flyTo(map, vectorSelected.getSource().getExtent());
                        }
                    }


                    function relocatenoriddle(riddleid, noriddle, roadid, dirtySource, originalSource, vector) {
                        var feature = dirtySource.getFeatureById(riddleid);
                        var idFeaturesPolygons;
                        if (!feature) {
                            feature = originalSource.getFeatureById(riddleid).clone();
                            feature.setId(riddleid);
                            dirtySource.addFeature(feature);
                        }
                        feature.setProperties({
                            'noriddle': noriddle
                        });
                        if (feature.get('idFeaturesPolygons') !== 'empty') {
                            idFeaturesPolygons = feature.get('idFeaturesPolygons').split(",");
                            for (var i = 0, j = idFeaturesPolygons.length; i < j; i++) {
                                vector.getSource().getFeatureById(idFeaturesPolygons[i]).setProperties({
                                    'noriddle': noriddle
                                });
                            }
                        }
                    }


                    function editFormRiddleEntry(riddleid, idModule) {
                        var url = 'editriddle.php?cmid=' + idModule + '&id=' + riddleid;
                        window.location.href = url;
                    }

                    function newFormRiddleEntry(roadid, idModule) {
                        var url = "editriddle.php?cmid=" + idModule + "&roadid=" + roadid;
                        window.location.href = url;
                    }
                    function editFormRoadEntry(roadid, idModule) {
                        var url = 'editroad.php?cmid=' + idModule + '&id=' + roadid;
                        window.location.href = url;
                    }

                    function newFormRoadEntry(idModule) {
                        var url = "editroad.php?cmid=" + idModule;
                        window.location.href = url;
                    }


                    function deleteRoad(roadid, dirtySource, originalSource, treasurehuntid, lockid) {
                        var json = ajax.call([{
                                methodname: 'mod_treasurehunt_delete_road',
                                args: {
                                    roadid: roadid,
                                    treasurehuntid: treasurehuntid,
                                    lockid: lockid
                                }
                            }]);
                        json[0].done(function (response) {
                            console.log(response);
                            if (response.status.code) {
                                notification.alert('Error', response.status.msg, 'Continue');
                            } else {
                                //Elimino tanto el li del road como todos los li de riddles asociados
                                deleteRoad2ListPanel(roadid);
                                // Elimino la feature de dirtySource si la tuviese, 
                                // del originalSource y elimino el camino del stage y la capa del mapa
                                map.removeLayer(stage.roads[roadid].vector);
                                delete stage.roads[roadid];
                                var features = originalSource.getFeatures();
                                for (var i = 0; i < features.length; i++) {
                                    if (roadid === features[i].get('roadid'))
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
                                 saveriddles(dirtySource, originalSource);
                                 $("#saveriddle").button("option", "disabled", true);
                                 dirty = false;*/
                            }
                        }).fail(function (error) {
                            console.log(error);
                            notification.exception(error);
                        });
                    }

                    function deleteRiddle(riddleid, dirtySource, originalSource, vectorOfPolygons, treasurehuntid, lockid) {
                        var json = ajax.call([{
                                methodname: 'mod_treasurehunt_delete_riddle',
                                args: {
                                    riddleid: riddleid,
                                    treasurehuntid: treasurehuntid,
                                    lockid: lockid
                                }
                            }]);
                        json[0].done(function (response) {
                            console.log(response);
                            if (response.status.code) {
                                notification.alert('Error', response.status.msg, 'Continue');
                            } else {
                                var idFeaturesPolygons = false;
                                var polygonFeature;
                                var feature = dirtySource.getFeatureById(riddleid);
                                //Elimino y recoloco 
                                deleteRiddle2ListPanel(riddleid, dirtySource, originalSource, vectorOfPolygons);
                                //Elimino la feature de dirtySource si la tuviese y todos los poligonos del vector de poligonos
                                if (!feature) {
                                    feature = originalSource.getFeatureById(riddleid);
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

                            }
                        }).fail(function (error) {
                            console.log(error);
                            notification.exception(error);
                        });
                    }

                    function saveriddles(dirtySource, originalSource, treasurehuntid, callback, options, lockid) {

                        var geoJSONFormat = new ol.format.GeoJSON();
                        var features = dirtySource.getFeatures();
                        var geoJSON = geoJSONFormat.writeFeatures(features, {
                            dataProjection: 'EPSG:4326',
                            featureProjection: 'EPSG:3857'
                        });
                        var json = ajax.call([{
                                methodname: 'mod_treasurehunt_update_riddles',
                                args: {
                                    riddles: geoJSON,
                                    treasurehuntid: treasurehuntid,
                                    lockid: lockid
                                }
                            }]);
                        json[0].done(function (response) {

                            console.log(response);
                            if (response.status.code) {
                                notification.alert('Error', response.status.msg, 'Continue');
                            } else {
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
                                if (typeof callback === "function" && options instanceof Array) {
                                    callback.apply(null, options);
                                }

                            }
                        }).fail(function (error) {
                            console.log(error);
                            notification.alert('Error', error.message, 'Continue');
                        });
                    }

                    $("#searchaddress").autocomplete({
                        minLength: 4,
                        source: function (request, response) {
                            var term = request.term;
                            openStreetMapGeocoder.geocode(term, function (data) {
                                if (!data[0]) {
                                    response();
                                    return;
                                }
                                var total = [];
                                for (var i = 0, l = data.length; i < l; i++) {
                                    var latitude;
                                    var longitude;
                                    latitude = data[i].getLatitude();
                                    longitude = data[i].getLongitude();
                                    var result = {"value": data[i].totalName,
                                        "latitude": latitude,
                                        "longitude": longitude,
                                        "boundingbox": data[i].boundingbox};
                                    total[i] = result;
                                }
                                response(total);
                            });
                        },
                        select: function (event, ui) {
                            if (ui.item.boundingbox) {
                                var extend = [];
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
                    $("#addriddle").on('click', function () {
                        if (dirty) {
                            saveriddles(dirtyStage, originalStage, treasurehuntid, newFormRiddleEntry, [roadid, idModule], lockid);
                        } else {
                            newFormRiddleEntry(roadid, idModule);
                        }

                    });
                    $("#addroad").on('click', function () {
                        if (dirty) {
                            saveriddles(dirtyStage, originalStage, treasurehuntid, newFormRoadEntry, [idModule], lockid);
                        } else {
                            newFormRoadEntry(idModule);
                        }
                    });
                    $("#removefeature").on('click', function () {

                        notification.confirm('Estas seguro?',
                                'Si la eliminas ya no podras recuperarla',
                                'Confirmar',
                                'Cancelar', function () {

                                    removefeatureToDirtySource(selectedFeatures, originalStage,
                                            dirtyStage, stage.roads[roadid].vector);
                                    removefeatures(selectedFeatures, stage.roads[roadid].vector);
                                    //Desactivo el boton de borrar y activo el de guardar cambios
                                    deactivateDeleteButton();
                                    activateSaveButton();
                                    dirty = true;
                                });
                    });
                    $("#saveriddle").on('click', function () {
                        saveriddles(dirtyStage, originalStage, treasurehuntid, null, null, lockid);
                    });
                    $("#riddlelist").on('click', '.ui-icon-info, .ui-icon-alert', function () {
                        var id = $(this).data('id');
                        // Open dialogue.
                        $(id).dialog("open");
                        // Remove focus from the buttons.
                        $('.ui-dialog :button').blur();
                    });
                    $("#riddlelist").on('click', '.ui-icon-trash', function () {
                        var $this_li = $(this).parents('li');
                        notification.confirm('Estas seguro?',
                                'Si la eliminas ya no podras recuperarla',
                                'Confirmar',
                                'Cancelar', function () {
                                    var riddleid = parseInt($this_li.attr('riddleid'));
                                    deleteRiddle(riddleid, dirtyStage, originalStage,
                                            stage.roads[roadid].vector, treasurehuntid, lockid);
                                });
                    });
                    $("#riddlelist").on('click', '.ui-icon-pencil', function () {
                        //Busco el riddleid del li que contiene la papelera seleccionada

                        var riddleid = parseInt($(this).parents('li').attr('riddleid'));
                        //Si esta sucio guardo el escenario
                        if (dirty) {
                            saveriddles(dirtyStage, originalStage, treasurehuntid,
                                    editFormRiddleEntry, [riddleid, idModule], lockid);
                        } else {
                            editFormRiddleEntry(riddleid, idModule);
                        }

                    });
                    $("input[name=controlpanel]:radio").on('click', function () {
                        if ($(this).attr('previousValue') === 'true') {
                            $(this).attr('checked', false).button("refresh");
                        } else {
                            $("input[name=controlpanel]:radio").attr('previousValue', false);
                        }
                        $(this).attr('previousValue', $(this).is(':checked'));
                        var selected = $("input[type='radio'][name='controlpanel']:checked");
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
                    $("#riddlelist").on('click', 'li', function (e) {
                        if ($(e.target).is('.handle ,.nohandle, .ui-icon , .sortable-number')) {
                            e.preventDefault();
                            return;
                        }
                        $(this).addClass("ui-selected").siblings().removeClass("ui-selected");
                        //Selecciono el riddleid de mi atributo custom
                        noriddle = parseInt($(this).attr('noriddle'));
                        riddleid = parseInt($(this).attr('riddleid'));
                        //Borro la anterior seleccion de features y busco las del mismo tipo
                        selectRiddleFeatures(stage.roads[roadid].vector, vectorSelected,
                                riddleid, selectedFeatures, dirtyStage, originalStage);
                        activateEdition();
                        // Si la pista no tiene geometría resalto el boton de anadir.
                        if ($(this).find(".invalidriddle").length > 0) {
                            $("label[for='radio1']").addClass('highlightbutton');
                        } else {
                            $("label[for='radio1']").removeClass('highlightbutton');
                        }
                        //Paro de dibujar si cambio de pista
                        Draw.Polygon.abortDrawing();
                    });
                    $("#roadlist").on('click', 'li', function (e) {
                        if ($(e.target).is('.ui-icon')) {
                            e.preventDefault();
                            return;
                        }
                        $(this).addClass("ui-selected").siblings().removeClass("ui-selected");
                        //Selecciono el riddleid de mi atributo custom
                        //Borro las pistas seleccionadas
                        selectedRiddleFeatures = {};
                        //Paro de dibujar si cambio de camino
                        Draw.Polygon.abortDrawing();
                        roadid = $(this).attr('roadid');
                        if (parseInt($(this).attr('blocked'))) {
                            deactivateAddRiddle();
                        } else {
                            activateAddRiddle();
                        }
                        selectRoad(roadid, stage.roads[roadid].vector, map);
                        deactivateEdition();
                        // Scroll to editor.
                        var scrolltop;
                        if ($('header[role=banner]').css("position") === "fixed") {
                            scrolltop = parseInt($(".treasurehunt_editor").offset().top) -
                                    parseInt($('header[role=banner]').outerHeight(true));
                        } else {
                            scrolltop = parseInt($(".treasurehunt_editor").offset().top);
                        }
                        $('html, body').animate({
                            scrollTop: scrolltop
                        }, 500);
                    });
                    $("#roadlist").on('click', '.ui-icon-pencil', function () {
                        //Busco el roadid del li que contiene el lapicero seleccionado

                        var roadid = parseInt($(this).parents('li').attr('roadid'));
                        //Si esta sucio guardo el escenario
                        if (dirty) {
                            saveriddles(dirtyStage, originalStage, treasurehuntid, editFormRoadEntry, [roadid, idModule], lockid);
                        } else {
                            editFormRoadEntry(roadid, idModule);
                        }

                    });
                    $("#roadlist").on('click', '.ui-icon-trash', function () {
                        var $this_li = $(this).parents('li');
                        notification.confirm('Estas seguro?',
                                'Si la eliminas se eliminaran todas las pitas asociadas y ya no podras recuperarlas',
                                'Confirmar',
                                'Cancelar', function () {
                                    var roadid = parseInt($this_li.attr('roadid'));
                                    deleteRoad(roadid, dirtyStage, originalStage, treasurehuntid, lockid);
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
                    // Evento para que funcione bien el boton de cerrar en dispositivos tactiles
                    $(document).on('touchend', '.ui-dialog-titlebar-close', function () {
                        $(this).parent().siblings('.ui-dialog-content').dialog("close");
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
                    //Al salirse
                    window.onbeforeunload = function (e) {
                        var message = "No ha guardado los cambios realizados.",
                                e = e || window.event;
                        if (dirty) {
                            // For IE and Firefox
                            if (e) {
                                e.returnValue = message;
                            }

                            // For Safari
                            return message;
                        }
                    };
                } // End of function init
            }; // End of init var
            return init;
        });