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
        init: function() {

            //Cargo el panel de control y la lista de pistas
            //Creo el control Panel
            $('<span id="edition"/>').appendTo($("#controlPanel"));
            $('<input type="radio" name="controlPanel" id="radio1" value="aÃ±adir" checked>').appendTo($("#edition"));
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
            $('<li idRiddle="15"/>').text('Prueba2').appendTo($("#riddleList"));
            $('<li idRiddle="14"/>').text('Prueba3').appendTo($("#riddleList"));
            $('<li idRiddle="13"/>').text('Prueba4').appendTo($("#riddleList"));
            $('<li idRiddle="12"/>').text('Prueba5').appendTo($("#riddleList"));
            $('<li idRiddle="11"/>').text('Prueba6').appendTo($("#riddleList"));
            $('<li idRiddle="10"/>').text('Prueba7').appendTo($("#riddleList"));
            $('<li idRiddle="9"/>').text('Prueba8').appendTo($("#riddleList"));
            $('<li idRiddle="8"/>').text('Prueba9').appendTo($("#riddleList"));
            $('<li idRiddle="7"/>').text('Prueba10').appendTo($("#riddleList"));
            $('<li idRiddle="6"/>').text('Prueba11').appendTo($("#riddleList"));
            $('<li idRiddle="5"/>').text('Prueba11').appendTo($("#riddleList"));
            $('<li idRiddle="4"/>').text('Prueba11').appendTo($("#riddleList"));
            $('<li idRiddle="3"/>').text('Prueba11').appendTo($("#riddleList"));
            $('<li idRiddle="2"/>').text('Prueba11').appendTo($("#riddleList"));
            $('<li idRiddle="1"/>').text('Prueba11').appendTo($("#riddleList"));
            $('<li idRiddle="0"/>').text('Prueba11').appendTo($("#riddleList"));

            var dirty = false;
            var numRiddle;
            var idRiddle = -1;
            var selectedFeatures;

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
                    if ($(".ui-selected, .ui-selecting").length > 1) {
                        $(ui.selecting).removeClass("ui-selecting");
                    }
                }
            }).find("li").addClass("ui-corner-all").prepend("<div class='handle'><span class='ui-icon ui-icon-carat-2-n-s'></span></div>").append("<div class='modifyRiddle'><span class='ui-icon ui-icon-trash'></span><span class='ui-icon ui-icon-pencil'></span></div>");
            //Inserto el nueva pista al inicio con la clase estÃ¡tica seleccionada por defecto
            $('<li idRiddle="-1"/>').addClass("ui-corner-all ui-static-li ui-selected").text('Insertar nueva pista').prepend("<div class='handle'><span class='ui-icon ui-icon-circle-plus'></span></div>").prependTo($("#riddleList"));

            /*//Esto es los vectores de los paises
            var vectorSource = new ol.source.Vector({
                url: 'data/countries.geojson',
                format: new ol.format.GeoJSON(),
                wrapX: false

            });*/

            //Estilo con el que se dibujarÃ¡n los Polygon
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
                })
            });
            var vector = new ol.layer.Vector({
                source: new ol.source.Vector(),
                style: styleFunction
            });

            var map = new ol.Map({
                layers: [
                new ol.layer.Tile({
                    source: new ol.source.OSM()
                }), vector],
                renderer: 'canvas',
                target: 'map',
                view: new ol.View({
                    center: new ol.proj.transform([-4.715354, 41.654618], 'EPSG:4326', 'EPSG:3857'),
                    zoom: 12
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
                    //Elimino la selecciÃ³n de features cuando cambia a off
                    selectedFeatures = this.select.getFeatures();
                    this.select.on('change:active', function() {
                        if (!this.getActive()) {
                            selectedFeatures.clear();
                        }
                    });
                    //Activo o desactivo el boton de borrar segÃºn tenga una feature seleccionada o no
                    this.select.on('select', function() {
                        setActivateRemoveBotton(selectedFeatures);
                    });
                    //Activo el boton de guardar segÃºn se haya modificado algo o no
                    this.modify.on('modifyend', function(e) {
                        $('#saveRiddle').button("option", "disabled", false);
                        e.features.setProperties({
                            'dirty': true
                        });
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
                    source: vector.getSource(),
                    type: /** @type {ol.geom.GeometryType} */
                    ('Polygon'),
                    style: selectedRiddleStyle
                }),
                setEvents: function() {
                    //Fijo el riddle al que pertenecen y activo el boton de guardar 
                    //segÃºn se haya modificado algo o no
                    this.Polygon.on('drawend', function(e) {
                        e.feature.setProperties({
                            'idRiddle': idRiddle,
                            'selected': true,
                            'dirty': true
                        });
                        $('#saveRiddle').button("option", "disabled", false);
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
            Draw.init();
            Draw.setActive(true);
            Modify.setActive(false);

            // The snap interaction must be added after the Modify and Draw interactions
            // in order for its map browser event handlers to be fired first. Its handlers
            // are responsible of doing the snapping.
            var snap = new ol.interaction.Snap({
                source: vector.getSource()
            });
            map.addInteraction(snap);

            function removeFeatures(selectedFeatures) {
                selectedFeatures.forEach(function(feature) {
                    vector.getSource().removeFeature(feature);
                });
                selectedFeatures.clear();
            }
            //Activo o desactivo el boton de borrar segÃºn tenga una feature seleccionada o no
            function setActivateRemoveBotton(selectedFeatures) {
                if (selectedFeatures.getLength() > 0) {
                    $('#removeFeature').button("option", "disabled", false);

                } else {
                    $('#removeFeature').button("option", "disabled", true);
                }
            }

            function selectRiddleFeatures(vectorOfPolygons, selected) {
                //Selecciono las features de la pista seleccionada
                vectorOfPolygons.getSource().forEachFeature(function(feature) {
                    var property = feature.getProperties();
                    if (property['idRiddle'] == selected) {
                        feature.setProperties({
                            'selected': true
                        });
                    } else {
                        feature.setProperties({
                            'selected': false
                        });
                    }
                });
            }

            function generateMultiPolygon(vectorOfPolygons) {
                //Selecciono las features de la pista seleccionada
                var features = new ol.Collection();
                vectorOfPolygons.getSource().forEachFeature(function(feature) {
                    var property = feature.getProperties();
                    var idRiddle = property['idRiddle'];
                    var newFeature = features.get(idRiddle);
                    var mpoly;

                    if (typeof newFeature == 'undefined') {
                        newFeature = new ol.Feature(feature.getProperties());
                        mpoly = new ol.geom.MultiPolygon();
                        mpoly.setProperties({
                            'idRiddle': idRiddle
                        });
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

            function relocateNumRiddle() {

                $.map($("#riddleList").find('li'), function(el) {
                    vector.getSource().forEachFeature(function(feature) {
                        var property = feature.getProperties();
                        if (property['idRiddle'] == $(el).attr('idRiddle')) {
                            feature.setProperties({
                                'numRiddle': Math.abs($(el).index() - $("#riddleList li").length) - 1
                            });
                        }
                    });
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

            function callbackremoveFeatures() {
                removeFeatures(selectedFeatures);
            }
            $("#removeFeature").click(function() {
                notification.confirm('Â¿EstÃ¡s seguro?', 'Si la eliminas ya no podrÃ¡s recuperarla', 'Confirmar', 'Cancelar', callbackremoveFeatures);
                //Desactivo el botÃ³n de borrar y activo el de guardar cambios
                $('#removeFeature').button("option", "disabled", true);
                $('#saveRiddle').button("option", "disabled", false);
            });
            $("#saveRiddle").click(function() {
                var result = $("#select_result").empty();
                relocateNumRiddle();
                var polygonCollection = onlyDirtyMultiPolygon(generateMultiPolygon(vector));
                var geoJSON = new ol.format.GeoJSON();
                var mygeoJSON = geoJSON.writeFeatures(polygonCollection.getArray())
                result.append(mygeoJSON);
                //Compruebo si existen nuevas pistas
                var newRiddle = false;
                polygonCollection.forEach(function(feature) {
                    var property = feature.getProperties();
                    if (property['idRiddle'] == -1) {
                        newRiddle = true;
                        debugger;
                        return;
                    }
                });
                //Si existe redirecciono para hacer el formulario
                if (newRiddle) {
                    var url = "save_riddle.php?id=2";
                    $('<form id=myform method="POST"/>').attr('action', url).appendTo('#controlPanel');
                    $('<input type="hidden" name="json"/>').val(mygeoJSON).appendTo('#myform');
                    $("#myform").submit();
                }
                //Si no existe envio un json con un ajax para actualizar los datos.

            });
            $("#riddleList .ui-icon-trash").click(function() {
                notification.confirm('¿Estás seguro?', 'Si la eliminas ya no podrás recuperarla', 'Confirmar', 'Cancelar', callbackremoveFeatures);
            });
            $("input[name=controlPanel]:radio").change(function() {
                var selected = $("input[type='radio'][name='controlPanel']:checked");
                var value = selected.val();
                if (value === 'añadir') {
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
                idRiddle = $(".ui-selected", this).attr('idRiddle');
                result.append(" #" + idRiddle);
                //Borro la anterior selecciÃ³n de features y busco las del mismo tipo
                selectRiddleFeatures(vector, idRiddle);
            });
            $("#riddleList").on("sortstop", function(event, ui) {
                //Compruebo la posiciÃ³n de cada elemento en la lista 
                //relocateNumRiddle();
                dirty = true;
            });


        } // End of function init
    }; // End of init var
    return init;

});