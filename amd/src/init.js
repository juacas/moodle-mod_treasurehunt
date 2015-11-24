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
        geocoderjs: 'geocoder/geocoder',
        selectpart: 'openlayers/selectpart',
        turf: 'turf/turf'
    }
});


define(['jquery', 'core/notification', 'core/str', 'openlayers', 'jqueryui', 'core/ajax'], function($, notification, str, ol, jqui, ajax) {


    var init = {
        init: function(idModule, idStage) {


            //Lo primero recojo todas las cadenas que voy a necesitar con una llamada ajax
            var ajaxStrings = [{
                key: 'insert_riddle',
                component: 'scavengerhunt'
            }, {
                key: 'insert_road',
                component: 'scavengerhunt'
            }];
            str.get_strings(ajaxStrings).done(function(data) {

                /** Global var ***************************************************************
                 */
                var stage = new Object(); // new array
                var dirtyStage = new ol.source.Vector({
                    projection: 'EPSG:3857'
                });
                var originalStage = new ol.source.Vector({
                    projection: 'EPSG:3857'
                });
                var dirty = false;
                var idRoad = -1;
                var idRiddle = -1;
                var selectedFeatures;
                var idNewFeature = 1;

                /**Initialize stage******************************************************
                 */
                stage = {
                    "roads": {
                        "-1": {
                            id: -1,
                            name: data[1]
                        }
                    }
                };

                /**Load the control pane, riddle and road list ***************************************************
                 */
                $('<span id="edition"/>').appendTo($("#controlPanel"));
                $('<input type="radio" name="controlPanel" id="radio1" value="add" checked>').appendTo($("#edition"));
                $("<label>").attr('for', "radio1").text('Añadir').appendTo($("#edition"));
                $('<input type="radio" name="controlPanel" id="radio2" value="modify">').appendTo($("#edition"));
                $("<label>").attr('for', "radio2").text('Modificar').appendTo($("#edition"));
                $('<button id="removeFeature"/>').attr('disabled', true).text('Eliminar').appendTo($("#controlPanel"));
                $('<button id="saveRiddle"/>').attr('disabled', true).text('Guardar cambios').appendTo($("#controlPanel"));
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
                //Lo cargo como un buttonset
                $("#edition").buttonset();
                //Creo el riddleListPanel
                $('<span/>').text('Has seleccionado').appendTo($("#controlPanel"));
                $('<span id="select_result"/>').text(' nada').appendTo($("#controlPanel"));
                $('<ul id="riddleList"/>').appendTo($("#riddleListPanel"));
                //Inserto el li nueva pista al inicio seleccionado por defecto
                $('<li idRiddle="-1"/>').addClass("ui-corner-all ui-static-li ui-selected").text(data[0]).prepend("<div class='handle'><span class='ui-icon ui-icon-circle-plus'></span></div>").prependTo($("#riddleList"));
                //Lo cargo como un sortable y selectable
                $("#riddleList").sortable({
                    handle: ".handle",
                    revert: true,
                    cursor: "move",
                    axis: 'y',
                    items: 'li:not(.ui-static-li)',
                    //Evito que Insertar nueva pista se mueva del inicio y pueda bajar
                    start: function() {
                        $('.ui-static-li', this).each(function() {
                            var $this = $(this);
                            $this.data('pos', $this.index());
                        });
                    },
                    change: function() {
                        $sortable = $(this);
                        $statics = $('.ui-static-li', this).detach();
                        $helper = $('<li></li>').prependTo(this);
                        $statics.each(function() {
                            var $this = $(this);
                            var target = $this.data('pos');

                            $this.insertAfter($('li', $sortable).eq(target));
                        });
                        $helper.remove();
                    }
                }).selectable({
                    filter: "li",
                    cancel: ".handle,.ui-icon",
                    //Solo dejo seleccionar uno
                    selecting: function(event, ui) {
                        if ($("#riddleList .ui-selected,#riddleList .ui-selecting").length > 1) {
                            $(ui.selecting).removeClass("ui-selecting");
                        }
                    }
                });

                //Creo el roadListPanel
                $('<ul id="roadList"/>').appendTo($("#roadListPanel"));
                //Lo cargo como un selectable
                $("#roadList").selectable({
                    filter: "li",
                    //Solo dejo seleccionar uno
                    selecting: function(event, ui) {
                        if ($("#roadList .ui-selected,#roadList .ui-selecting").length > 1) {
                            $(ui.selecting).removeClass("ui-selecting");
                        }
                    }
                });



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
                    zIndex: 9999
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
                    init: function() {
                        this.select = new ol.interaction.Select();
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
                            deleteCondition: function(event) {
                                return ol.events.condition.shiftKeyOnly(event) && ol.events.condition.singleClick(event);
                            }
                        });
                        map.addInteraction(this.modify);
                        this.setEvents();
                    },
                    setEvents: function() {
                        //Elimino la seleccion de features cuando cambia a off
                        selectedFeatures = this.select.getFeatures();
                        this.select.on('change:active', function() {
                            if (!this.getActive()) {
                                selectedFeatures.clear();
                            }
                        });
                        //Activo o desactivo el boton de borrar segun tenga una feature seleccionada o no
                        this.select.on('select', function() {
                            setActivateRemoveBotton(selectedFeatures);
                        });
                        //Activo el boton de guardar segun se haya modificado algo o no
                        this.modify.on('modifyend', function(e) {
                            $('#saveRiddle').button("option", "disabled", false);
                            e.features.setProperties({
                                'dirty': true
                            });
                            debugger;
                            modifyFeatureToDirtySource(e.features, originalStage, dirtyStage, stage["roads"][idRoad].vector);
                            dirty = true;
                        });
                    },
                    setActive: function(active) {
                        this.select.setActive(active);
                        this.modify.setActive(active);
                    }
                };
                Modify.init();


                var Draw = {
                    init: function() {
                        map.addInteraction(this.Polygon);
                        this.Polygon.setActive(false);
                        this.setEvents();
                    },
                    Polygon: new ol.interaction.Draw({
                        source: vectorDraw.getSource(),
                        type: /** @type {ol.geom.GeometryType} */
                        ('Polygon'),
                        style: selectedRiddleStyle
                    }),
                    setEvents: function() {
                        //Fijo el riddle al que pertenecen y activo el boton de guardar 
                        //segun se haya modificado algo o no
                        this.Polygon.on('drawend', function(e) {

                            e.feature.setProperties({
                                'idRoad': idRoad,
                                'idRiddle': idRiddle,
                                'selected': true,
                                'dirty': true
                            });
                            e.feature.setId(idNewFeature);
                            idNewFeature++;
                            //Agrego la nueva feature a su correspondiente vector de poligonos
                            stage["roads"][idRoad].vector.getSource().addFeature(e.feature);
                            //Agrego la feature a la coleccion de multipoligonos sucios
                            addFeatureToDirtySource(e.feature, originalStage, dirtyStage, idRiddle);

                            //Limpio el vector de dibujo
                            vectorDraw.getSource().clear();
                            $('#saveRiddle').button("option", "disabled", false);
                            dirty = true;
                        });
                    },
                    getActive: function() {
                        return this.activeType ? this[this.activeType].getActive() : false;
                    },
                    setActive: function(active) {
                        if (active) {
                            this.activeType && this[this.activeType].setActive(false);
                            this.Polygon.setActive(true);
                            this.activeType = 'Polygon';
                        } else {
                            this.activeType && this[this.activeType].setActive(false);
                            this.activeType = null;
                        }
                    }
                };
                $(document).keyup(function(e) {
                    //Si pulso la tecla esc dejo de dibujar
                    if (e.keyCode === 27) // esc
                    {
                        Draw.Polygon.abortDrawing_();
                    }
                });

                Draw.init();
                Draw.setActive(true);
                Modify.setActive(false);

                // The snap interaction must be added after the Modify and Draw interactions
                // in order for its map browser event handlers to be fired first. Its handlers
                // are responsible of doing the snapping.
                var snap = new ol.interaction.Snap({
                    source: vectorDraw.getSource()
                });
                map.addInteraction(snap);


                //Cargo las features
                fetchFeatures();


                function addFeatureToDirtySource(dirtyFeature, originalSource, dirtySource, idRiddle) {
                    var feature = dirtySource.getFeatureById(idRiddle);
                    if (feature) {
                        feature.getGeometry().appendPolygon(dirtyFeature.getGeometry());
                        feature.setProperties({
                            'idFeaturesPolygons': feature.get('idFeaturesPolygons') + ',' + dirtyFeature.getId()
                        });
                    } else {
                        if (idRiddle !== -1) {
                            feature = originalSource.getFeatureById(idRiddle).clone();
                            feature.setProperties({
                                'idFeaturesPolygons': feature.get('idFeaturesPolygons') + ',' + dirtyFeature.getId()
                            });
                            feature.setId(idRiddle);
                        } else {
                            feature = new ol.Feature(new ol.geom.MultiPolygon());
                            feature.setProperties({
                                'idFeaturesPolygons': dirtyFeature.getId(),
                                'idRoad': dirtyFeature.get('idRoad'),
                                //Tendria que poner el numRiddle
                            });
                            //Si ya he instartado un -1 no debería dejar insertar más en otros caminos
                        }
                        feature.getGeometry().appendPolygon(dirtyFeature.getGeometry());
                        feature.setId(idRiddle);
                        dirtySource.addFeature(feature);
                    }
                }

                function modifyFeatureToDirtySource(dirtyFeatures, originalSource, dirtySource, vector) {

                    dirtyFeatures.forEach(function(dirtyFeature) {
                        debugger;
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

                    dirtyFeatures.forEach(function(dirtyFeature) {
                        debugger;
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
                            dirtySource.removeFeature(feature);
                        }

                    });
                }

                function styleFunction(feature) {

                    // get the incomeLevel from the feature properties
                    var selected = feature.get('selected');
                    // if there is no level or its one we don't recognize,
                    // return the default style (in an array!)
                    if (selected) {
                        return [selectedRiddleStyle];
                    }
                    // check the cache and create a new style for the income
                    // level if its not been created before.
                    // at this point, the style for the current level is in the cache
                    // so return it (as an array!)
                    return [defaultRiddleStyle];
                }





                function fetchFeatures() {
                    var geojson = ajax.call([{
                        methodname: 'mod_scavengerhunt_fetchstage',
                        args: {
                            idStage: idStage
                        }
                    }]);
                    geojson[0].done(function(response) {
                        console.log('json: ' + response);
                        var vector;
                        var geoJSON = new ol.format.GeoJSON();
                        var roads = JSON.parse(response[1]);

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
                                    style: styleFunction
                                });
                                stage["roads"][road].vector = vector;
                                map.addLayer(vector);
                            }
                        }
                        //Add stage features to source originalStage
                        originalStage.addFeatures(geoJSON.readFeatures(response[0], {
                            'dataProjection': "EPSG:4326",
                            'featureProjection': "EPSG:3857"
                        }));

                        originalStage.forEachFeature(function(feature) {
                            var polygons = feature.getGeometry().getPolygons();
                            var idNewFeatures;
                            var idRiddle = feature.getId();
                            var idRoad = feature.get('idRoad');
                            var numRiddle = feature.get('numRiddle');
                            var name = feature.get('name');
                            for (var i = 0; i < polygons.length; i++) {
                                var newFeature = new ol.Feature(feature.getProperties());
                                newFeature.setProperties({
                                    'idRiddle': idRiddle
                                });
                                var polygon = polygons[i];
                                newFeature.setGeometry(polygon);
                                newFeature.setId(idNewFeature);
                                if (i = 0) {
                                    idNewFeatures = idNewFeature;
                                } else {
                                    idNewFeatures = idNewFeatures + ',' + idNewFeature;
                                }
                                idNewFeature++;
                                vector.getSource().addFeature(newFeature);
                            }
                            feature.setProperties({
                                idFeaturesPolygons: idNewFeatures
                            });
                            makeRiddleListPanel(idRiddle, idRoad, numRiddle, name);
                        });

                        /*selectRoad(stage[1].id, vector);
                    idRoad = stage[1].id;*/

                    }).fail(function(ex) {
                        console.log(ex);
                    });
                }


                /** Panel functions ***************************************************************
                 */
                function removeFeatures(selectedFeatures, vector) {
                    selectedFeatures.forEach(function(feature) {
                        vector.getSource().removeFeature(feature);
                    });
                    selectedFeatures.clear();

                }

                function makeRiddleListPanel(idRiddle, idRoad, numRiddle, name) {
                    //Si no existe lo aÃƒÂ±ado escondido para luego seleccionar el camino y mostrar solo ese
                    if ($('#riddleList li [idRiddle="' + idRiddle + '"]').length < 1) {
                        $('<li idRiddle="' + idRiddle + '" idRoad="' + idRoad + '" numRiddle="' + numRiddle + '"/>').text(name).appendTo($("#riddleList")).addClass("ui-corner-all").prepend("<div class='handle'><span class='ui-icon ui-icon-carat-2-n-s'></span></div>").append("<div class='modifyRiddle'><span class='ui-icon ui-icon-trash'></span><span class='ui-icon ui-icon-pencil'></span></div>");
                    }

                }

                function makeRoadLisPanel(idRoad, name) {
                    //Si no existe lo aÃ±ado
                    if ($('#roadList li [idRoad="' + idRoad + '"]').length < 1) {
                        $('<li idRoad="' + idRoad + '"/>').text(name).appendTo($("#roadList")).addClass("ui-corner-all");
                    }
                }
                //Activo o desactivo el boton de borrar segÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âºn tenga una feature seleccionada o no
                function setActivateRemoveBotton(selectedFeatures) {
                    if (selectedFeatures.getLength() > 0) {
                        $('#removeFeature').button("option", "disabled", false);

                    } else {
                        $('#removeFeature').button("option", "disabled", true);
                    }
                }

                function flyTo(map, vectorSelected) {
                    var duration = 500;
                    var view = map.getView();
                    var extent = vectorSelected.getSource().getExtent();
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

                function selectRoad(idRoad, vectorOfPolygons, map, selectedFeatures) {
                    map.getLayers().forEach(function(layer) {
                        if (layer instanceof ol.layer.Vector) {
                            layer.setVisible(false);
                        }
                    });
                    vectorOfPolygons.setVisible(true);
                    if (vectorOfPolygons.getSource().getFeatures().length > 0) {
                        flyTo(map, vectorOfPolygons);
                    }
                    //Limpio todas las features seleccionadas
                    selectedFeatures.clear();
                    //Oculto todos y solo muestro los que tengan el idRoad
                    $('#riddleList li').not("[idRiddle='-1']").hide();
                    $("#riddleList li[idRoad='" + idRoad + "']").show();
                }

                //Revisar funcion por si se puede mejorar, tipo coger los ids de originalStage o dirtyStage y marcarlos como selected
                function selectRiddleFeatures(vectorOfPolygons, selected, selectedFeatures) {
                    var vectorSelected = new ol.layer.Vector({
                        source: new ol.source.Vector({
                            projection: 'EPSG:3857'
                        })
                    });
                    //Selecciono las features de la pista seleccionada
                    vectorOfPolygons.getSource().forEachFeature(function(feature) {
                        var idRiddle = feature.get('idRiddle');
                        if (idRiddle === selected) {
                            feature.setProperties({
                                'selected': true
                            });
                            vectorSelected.getSource().addFeature(feature);
                        } else {
                            feature.setProperties({
                                'selected': false
                            });
                        }
                    });
                    //Deselecciono cualquier feature anterior
                    selectedFeatures.clear();
                    //Coloco el mapa en la posicion de las pistas seleccionadas si la pista contiene alguna feature y 
                    //postergando el tiempo para que seleccione la nueva feature.
                    if (vectorSelected.getSource().getFeatures().length) {
                        setTimeout(function() {
                            flyTo(map, vectorSelected);
                        }, 100);
                    }
                }

                function generateMultiPolygon(vectorOfPolygons) {
                    //Selecciono las features de la pista seleccionada
                    var features = new ol.Collection();
                    vectorOfPolygons.getSource().forEachFeature(function(feature) {
                        var property = feature.getProperties();
                        var idRiddle = property['idRiddle'];
                        var newFeature = features.get(idRiddle);
                        var mpoly;

                        if (typeof newFeature === 'undefined') {
                            newFeature = new ol.Feature(property);
                            mpoly = new ol.geom.MultiPolygon();
                            newFeature.setId(idRiddle);
                            //Aparently ol.collection needs to add the item firstly
                            features.push(newFeature);
                            features.set(idRiddle, newFeature);
                            newFeature.setGeometry(mpoly);
                        } else {
                            mpoly = newFeature.getGeometry();
                        }
                        if (property['dirty']) {
                            newFeature.setProperties({
                                'dirty': true
                            });
                        }
                        //AÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â±ado el polÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­gono clonado y cambiado de projecciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n
                        mpoly.appendPolygon(feature.getGeometry());
                    });

                    return features;
                }

                function onlyDirtyMultiPolygon(features) {

                    features.forEach(function(element) {
                        if (!element.get('dirty')) {
                            features.remove(element);
                        }
                    });
                    return features;
                }

                function relocateNumRiddle(features) {
                    $.map($("#riddleList").find('li'), function(el) {
                        features.forEach(function(feature) {
                            var position = Math.abs($(el).index() - $("#riddleList li").length) - 1;
                            var property = feature.getProperties();
                            if (feature.getId() === parseInt($(el).attr('idRiddle'))) {
                                if (property['numRiddle'] !== position) {
                                    feature.setProperties({
                                        'numRiddle': position,
                                        'dirty': true
                                    });
                                } else {
                                    //Tiene la misma posicion que antes
                                }
                            } else {
                                //No es la feature necesaria
                            }
                        });
                    });
                    return features;
                }

                function editFormEntry(idRiddle) {
                    var url = 'save_riddle.php?cmid=' + idModule + '&id=' + idRiddle;
                    window.location.href = url;
                }

                $("#removeFeature").click(function() {
                    notification.confirm('¿Estas seguro?', 'Si la eliminas ya no podras recuperarla', 'Confirmar', 'Cancelar', function() {
                        removeFeatureToDirtySource(selectedFeatures, originalStage, dirtyStage, stage["roads"][idRoad].vector);
                        removeFeatures(selectedFeatures, stage["roads"][idRoad].vector);
                    });
                    //Desactivo el boton de borrar y activo el de guardar cambios
                    $('#removeFeature').button("option", "disabled", true);
                    $('#saveRiddle').button("option", "disabled", false);
                    dirty = true;
                });
                $("#saveRiddle").click(function() {
                    var result = $("#select_result").empty();
                    var dirtyFeatureCollection = new ol.Collection();
                    //Genero los multiPolygon, los recoloco y recojo solo los sucios
                    for (var road in stage["roads"]) {
                        if (stage["roads"].hasOwnProperty(road)) {
                            debugger;
                            dirtyFeatureCollection.extend(onlyDirtyMultiPolygon(relocateNumRiddle(generateMultiPolygon(stage["roads"][road].vector))).getArray());
                        }
                    }
                    debugger;
                    var geoJSON = new ol.format.GeoJSON();
                    var mygeoJSON = geoJSON.writeFeatures(dirtyFeatureCollection.getArray(), {
                        'dataProjection': "EPSG:4326",
                        'featureProjection': "EPSG:3857"
                    });
                    result.append(mygeoJSON);
                    //Compruebo si existen nuevas pistas
                    var newRiddle = false;
                    if (dirtyFeatureCollection.get(-1)) {
                        newRiddle = true;
                    }
                    //Si existe redirecciono para hacer el formulario
                    if (newRiddle) {
                        var url = "save_riddle.php?cmid=" + idModule;
                        $('<form id=myform method="POST"/>').attr('action', url).appendTo('#controlPanel');
                        $('<input type="hidden" name="json"/>').val(mygeoJSON).appendTo('#myform');
                        $("#myform").submit();
                    }
                    //Si no existe envio un json con un ajax para actualizar los datos.
                });
                $("#riddleList .ui-icon-trash").click(function() {
                    debugger;
                    notification.confirm('ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¿EstÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡s seguro?', 'Si la eliminas ya no podrÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡s recuperarla', 'Confirmar', 'Cancelar', function() {
                        removeFeatures(selectedFeatures, stage["roads"][idRiddle].vector);
                    });
                });
                $("#riddleList .ui-icon-pencil").click(function() {
                    //Busco el idRiddle del li que contiene la papelera seleccionada
                    var idRiddle = $(this).parents('li').attr('idRiddle');
                    debugger;
                    if (dirty) {
                        notification.confirm('Ãƒâ€šÃ‚Â¿Desea continuar?', 'Hay cambios sin guardar, si continua se perderÃƒÆ’Ã‚Â¡n', 'Confirmar', 'Cancelar', function(idRiddle) {
                            debugger;
                            editFormEntry(idRiddle);

                        });
                    } else {
                        editFormEntry(idRiddle);
                    }
                });
                $("input[name=controlPanel]:radio").change(function() {
                    var selected = $("input[type='radio'][name='controlPanel']:checked");
                    var value = selected.val();
                    if (value === 'add') {
                        Draw.setActive(true);
                        Modify.setActive(false);
                    } else if (value === 'modify') {
                        Draw.setActive(false);
                        Modify.setActive(true);
                    }
                });
                $("#riddleList").on("selectablestop", function(event, ui) {
                    //Selecciono el idRiddle de mi atributo custom
                    var result = $("#select_result").empty();
                    idRiddle = parseInt($(".ui-selected", this).attr('idRiddle'));
                    result.append(" #" + idRiddle);
                    //Borro la anterior selecciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n de features y busco las del mismo tipo
                    selectRiddleFeatures(stage["roads"][idRoad].vector, idRiddle, selectedFeatures);
                });
                $("#riddleList").on("sortstop", function(event, ui) {
                    //Compruebo la posiciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n de cada elemento en la lista 
                    //relocateNumRiddle();
                    dirty = true;
                });

                $("#roadList").on("selectablestop", function(event, ui) {
                    //Selecciono el idRiddle de mi atributo custom
                    var result = $("#select_result").empty();
                    idRoad = $(".ui-selected", this).attr('idRoad');
                    result.append(" #" + idRoad);
                    selectRoad(idRoad, stage["roads"][idRoad].vector, map, selectedFeatures);
                });

            }).fail(function(e) {
                console.log(e);
            });




        } // End of function init
    }; // End of init var
    return init;

});