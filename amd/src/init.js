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
    }
});


define(['jquery', 'core/notification', 'core/str', 'openlayers', 'jqueryui', 'core/ajax'], function($, notification, str, ol, jqui, ajax) {


    var init = {
        init: function(idModule, idStage) {

            //Cargo el panel de control y la lista de pistas
            //Creo el control Panel
            $('<span id="edition"/>').appendTo($("#controlPanel"));
            $('<input type="radio" name="controlPanel" id="radio1" value="anadir" checked>').appendTo($("#edition"));
            $("<label>").attr('for', "radio1").text('AÃ±adir').appendTo($("#edition"));
            $('<input type="radio" name="controlPanel" id="radio2" value="modificar">').appendTo($("#edition"));
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
            //Inserto el nueva pista al inicio seleccionado por defecto
            $('<li idRiddle="-1"/>').addClass("ui-corner-all ui-static-li ui-selected").text('Insertar nueva pista').prepend("<div class='handle'><span class='ui-icon ui-icon-circle-plus'></span></div>").prependTo($("#riddleList"));
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

            /** Variables
             */
            var stage = new Object();
            var dirty = false;
            var idRoad;
            var idRiddle = -1;
            var selectedFeatures;


            //Estilo con el que se dibujarÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡n los Polygon
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
                    projection: 'EPSG:3857',
                }),
                visible: false,
                style: styleFunction
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
                    //Elimino la selecciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n de features cuando cambia a off
                    selectedFeatures = this.select.getFeatures();
                    this.select.on('change:active', function() {
                        if (!this.getActive()) {
                            selectedFeatures.clear();
                        }
                    });
                    //Activo o desactivo el boton de borrar segÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Âºn tenga una feature seleccionada o no
                    this.select.on('select', function() {
                        setActivateRemoveBotton(selectedFeatures);
                    });
                    //Activo el boton de guardar segÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Âºn se haya modificado algo o no
                    this.modify.on('modifyend', function(e) {
                        $('#saveRiddle').button("option", "disabled", false);
                        e.features.setProperties({
                            'dirty': true
                        });
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
                    style: selectedRiddleStyle,
                }),
                setEvents: function() {
                    //Fijo el riddle al que pertenecen y activo el boton de guardar 
                    //segÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Âºn se haya modificado algo o no
                    this.Polygon.on('drawend', function(e) {
                        e.feature.setProperties({
                            'idRiddle': idRiddle,
                            'selected': true,
                            'dirty': true
                        });
                        //Añado la nueva feature a su correspondiente vector
                        stage[idRoad].vector.getSource().addFeature(e.feature);
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
            //Si pulso la tecla esc dejo de dibujar
            $(document).keyup(function(e) {
                if (e.keyCode === 27) // esc
                {
                    var selected = $("input[type='radio'][name='controlPanel']:checked");
                    var value = selected.val();
                    if (value === 'anadir') {
                        Draw.init();
                        Draw.setActive(true);
                    }
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

            function makeRiddleListPanel(idRiddle, idRoad, numRiddle, name) {
                //Si no existe lo aÃ±ado escondido para luego seleccionar el camino y mostrar solo ese
                if ($('#riddleList li [idRiddle="' + idRiddle + '"]').length < 1) {
                    $('<li idRiddle="' + idRiddle + '" idRoad="' + idRoad + '" numRiddle="' + numRiddle + '"/>').text(name).appendTo($("#riddleList")).addClass("ui-corner-all").prepend("<div class='handle'><span class='ui-icon ui-icon-carat-2-n-s'></span></div>").append("<div class='modifyRiddle'><span class='ui-icon ui-icon-trash'></span><span class='ui-icon ui-icon-pencil'></span></div>");
                }

            }

            function makeRoadLisPanel(idRoad, name) {
                //Si no existe lo añado
                if ($('#roadList li [idRoad="' + idRoad + '"]').length < 1) {
                    $('<li idRoad="' + idRoad + '"/>').text(name).appendTo($("#roadList")).addClass("ui-corner-all");
                }
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
                    var geoJSON = new ol.format.GeoJSON();
                    var FeaturesCollection = geoJSON.readFeatures(response[0], {
                        'dataProjection': "EPSG:4326",
                        'featureProjection': "EPSG:3857"
                    });
                      var vector;
                    stage = JSON.parse(response[1]);
                    for (var i = 1, n = Object.keys(stage).length; i <= n; i++) {
                        makeRoadLisPanel(stage[i].id, stage[i].name);
                    }
                    FeaturesCollection.forEach(function(feature) {
                        var polygons = feature.getGeometry().getPolygons();
                        var idRiddle = feature.getId();
                        var idRoad = feature.get('idRoad');
                        var numRiddle = feature.get('numRiddle');
                        var name = feature.get('name');
                        if (!stage[idRoad].hasOwnProperty("vector")) {
                            vector = new ol.layer.Vector({
                                source: new ol.source.Vector({
                                    projection: 'EPSG:3857',
                                }),
                                style: styleFunction
                            });
                            stage[idRoad].vector = vector;
                            map.addLayer(stage[idRoad].vector);
                        }
                        for (var i = 0; i < polygons.length; i++) {
                            var newFeature = new ol.Feature(feature.getProperties());
                            newFeature.setProperties({
                                'idRiddle': idRiddle
                            });
                            var polygon = polygons[i];
                            newFeature.setGeometry(polygon);
                            vector.getSource().addFeature(newFeature);
                        }
                        makeRiddleListPanel(idRiddle, idRoad, numRiddle, name);

                    });
                    selectRoad(stage[1].id, vector);
                    idRoad = stage[1].id;

                }).fail(function(ex) {
                    console.log(ex);
                });
            }

            function removeFeatures(selectedFeatures) {
                selectedFeatures.forEach(function(feature) {
                    stage[idRoad].vector.getSource().removeFeature(feature);
                });
                selectedFeatures.clear();
            }
            //Activo o desactivo el boton de borrar segÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Âºn tenga una feature seleccionada o no
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

            function selectRoad(idRoad, vectorOfPolygons) {
                //Oculto todos y solo muestro los que tengan el idRoad
                $('#riddleList li').not("[idRiddle='-1']").hide();
                $("#riddleList li[idRoad='" + idRoad + "']").show();
            }

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
                //Coloco el mapa en la posiciÃ³n de las pistas seleccionadas si la pista contiene alguna feature y 
                //postergando el tiempo para que seleccione la nueva feature.
                if (vectorSelected.getSource().getFeatures().length) {
                    setTimeout(function() {
                        flyTo(map, vectorSelected);
                    }, 100);
                }

                //map.getView().fit(vectorSelected.getSource().getExtent(), map.getSize());
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
                    //AÃƒÆ’Ã‚Â±ado el polÃƒÆ’Ã‚Â­gono clonado y cambiado de projecciÃƒÆ’Ã‚Â³n
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

            function callbackremoveFeatures() {
                removeFeatures(selectedFeatures);
            }

            function editFormEntry(idRiddle) {
                var url = 'save_riddle.php?cmid=' + idModule + '&id=' + idRiddle;
                window.location.href = url;
            }

            $("#removeFeature").click(function() {
                notification.confirm('ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¿EstÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡s seguro?', 'Si la eliminas ya no podrÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡s recuperarla', 'Confirmar', 'Cancelar', callbackremoveFeatures);
                //Desactivo el botÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n de borrar y activo el de guardar cambios
                $('#removeFeature').button("option", "disabled", true);
                $('#saveRiddle').button("option", "disabled", false);
                dirty = true;
            });
            $("#saveRiddle").click(function() {

                var result = $("#select_result").empty();
                //Genero los multiPolygon, los recoloco y recojo solo los sucios
                var polygonCollection = onlyDirtyMultiPolygon(relocateNumRiddle(generateMultiPolygon(vector)));
                var geoJSON = new ol.format.GeoJSON();
                var mygeoJSON = geoJSON.writeFeatures(polygonCollection.getArray(), {
                    'dataProjection': "EPSG:4326",
                    'featureProjection': "EPSG:3857"
                });
                result.append(mygeoJSON);
                //Compruebo si existen nuevas pistas
                var newRiddle = false;
                if (polygonCollection.get(-1)) {
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
                notification.confirm('Ãƒâ€šÃ‚Â¿EstÃƒÆ’Ã‚Â¡s seguro?', 'Si la eliminas ya no podrÃƒÆ’Ã‚Â¡s recuperarla', 'Confirmar', 'Cancelar', callbackremoveFeatures);
            });
            $("#riddleList .ui-icon-pencil").click(function() {
                //Busco el idRiddle del li que contiene la papelera seleccionada
                var idRiddle = $(this).parents('li').attr('idRiddle');
                debugger;
                if (dirty) {
                    notification.confirm('Ã‚Â¿Desea continuar?', 'Hay cambios sin guardar, si continua se perderÃƒÂ¡n', 'Confirmar', 'Cancelar', function(idRiddle) {
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
                if (value === 'anadir') {
                    Draw.setActive(true);
                    Modify.setActive(false);
                } else if (value === 'modificar') {
                    Draw.setActive(false);
                    Modify.setActive(true);
                }
            });
            $("#riddleList").on("selectablestop", function(event, ui) {
                //Selecciono el idRiddle de mi atributo custom
                var result = $("#select_result").empty();
                idRiddle = parseInt($(".ui-selected", this).attr('idRiddle'));
                result.append(" #" + idRiddle);
                //Borro la anterior selecciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n de features y busco las del mismo tipo
                selectRiddleFeatures(stage[idRoad].vector, idRiddle, selectedFeatures);
            });
            $("#riddleList").on("sortstop", function(event, ui) {
                //Compruebo la posiciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n de cada elemento en la lista 
                //relocateNumRiddle();
                dirty = true;
            });

            $("#roadList").on("selectablestop", function(event, ui) {
                //Selecciono el idRiddle de mi atributo custom
                var result = $("#select_result").empty();
                idRoad = $(".ui-selected", this).attr('idRoad');
                result.append(" #" + idRoad);
                debugger;
                selectRoad(idRoad, stage[idRoad].vector);
            });




        } // End of function init
    }; // End of init var
    return init;

});