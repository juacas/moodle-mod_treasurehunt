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
        openlayers: 'openlayers/ol',
        geocoderjs: 'geocoder/geocoder',
    }
});


define(['jquery', 'core/notification', 'core/str', 'openlayers', 'jqueryui', 'core/ajax'], function($, notification, str, ol, jqui, ajax) {


    var init = {
        init: function() {

            //Cargo el panel de control y la lista de pistas
            //Creo el control Panel
            $('<span id="edition"/>').appendTo($("#controlPanel"));
            $('<input type="radio" name="controlPanel" id="radio1" value="añadir" checked>').appendTo($("#edition"));
            $("<label>").attr('for', "radio1").text('Añadir').appendTo($("#edition"));
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
            //Inserto el nueva pista al inicio con la clase estática seleccionada por defecto
            $('<li idRiddle="-1"/>').addClass("ui-corner-all ui-static-li ui-selected").text('Insertar nueva pista').prepend("<div class='handle'><span class='ui-icon ui-icon-circle-plus'></span></div>").prependTo($("#riddleList"));

            /*//Esto es los vectores de los paises
            var vectorSource = new ol.source.Vector({
                url: 'data/countries.geojson',
                format: new ol.format.GeoJSON(),
                wrapX: false

            });*/

            //Estilo con el que se dibujarán los Polygon
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
                                    color: '#FF0000'
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
                    //Elimino la selección de features cuando cambia a off
                    selectedFeatures = this.select.getFeatures();
                    this.select.on('change:active', function() {
                        if (!this.getActive()) {
                            selectedFeatures.clear();
                        }
                    });
                    //Activo o desactivo el boton de borrar según tenga una feature seleccionada o no
                    this.select.on('select', function() {
                        setActivateRemoveBotton();
                    });
                    //Activo el boton de guardar según se haya modificado algo o no
                    this.modify.on('modifyend', function() {
                        $('#saveRiddle').button("option", "disabled", false);
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
                    source: vector.getSource(),
                    type: /** @type {ol.geom.GeometryType} */
                    ('Polygon'),
                    style: selectedRiddleStyle
                }),
                setEvents: function() {
                    //Fijo el riddle al que pertenecen y activo el boton de guardar 
                    //según se haya modificado algo o no
                    this.Polygon.on('drawend', function(e) {
                        e.feature.setProperties({
                            'idRiddle': idRiddle,
                            'selected': true
                        });
                        console.log(e.feature, e.feature.getProperties());
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

            function removeFeatures() {

                selectedFeatures.forEach(function(feature) {
                    vector.getSource().removeFeature(feature);
                });
                selectedFeatures.clear();
                //Desactivo el botón de borrar y activo el de guardar cambios
                $('#removeFeature').button("option", "disabled", true);
                $('#saveRiddle').button("option", "disabled", false);
                dirty = true;
            }
            //Activo o desactivo el boton de borrar según tenga una feature seleccionada o no
            function setActivateRemoveBotton() {
                if (selectedFeatures.getLength() > 0) {
                    $('#removeFeature').button("option", "disabled", false);

                } else {
                    $('#removeFeature').button("option", "disabled", true);
                }
            }

            function selectRiddleFeatures(selected) {

                vector.getSource().forEachFeature(function(feature) {
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

            $("#removeFeature").click(function() {
                notification.confirm('¿Estás seguro?', 'Si la eliminas ya no podrás recuperarla', 'Confirmar', 'Cancelar', removeFeatures);
            });
            $("#saveRiddle").click(function() {

                var result = $("#select_result").empty();
                //Uso el formato geoJSON para almacenar las features y pasarlas a la base de datos
                var geoJSON = new ol.format.GeoJSON();
                /*vector.getSource().forEachFeature(function(feature) {
                    result.append(' ['+ feature.getGeometry().getCoordinates() + ']' + geoJSON.writeFeature(feature));
                });*/
                result.append(geoJSON.writeFeatures(vector.getSource().getFeatures()));
            });
            $("#riddleList .ui-icon-trash").click(function() {
                notification.confirm('¿Estás seguro?', 'Si la eliminas ya no podrás recuperarla', 'Confirmar', 'Cancelar', removeFeatures);
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
                //Selecciono el numRiddle de mi atributo custom
                var result = $("#select_result").empty();
                idRiddle = $(".ui-selected", this).attr('idRiddle');
                result.append(" #" + idRiddle);
                //Borro la anterior selección de features y busco las del mismo tipo
                selectRiddleFeatures(idRiddle);
            });

        } // End of function init
    }; // End of init var
    return init;

});