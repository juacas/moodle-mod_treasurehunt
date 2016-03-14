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
        }
    },
    paths: {
        openlayers: 'openlayers/ol-debug',
        geocoderjs: 'geocoder/geocoder',
        jquerymobile: 'jquery-mobile/jquerymobile'
    }
});
define(['jquery', 'core/notification', 'core/str', 'core/url', 'openlayers', 'jqueryui', 'core/ajax', 'geocoderjs', 'core/templates', 'jquerymobile'], function ($, notification, str, url, ol, jqui, ajax, GeocoderJS, templates, $m) {

    var init = {
        playScavengerhunt: function (idModule, idScavengerhunt, playwithoutmove) {

            var pergaminoUrl = url.imageUrl('images/pergamino', 'scavengerhunt');
            var falloUrl = url.imageUrl('images/fallo', 'scavengerhunt');
            var markerUrl = url.imageUrl('flag-marker', 'scavengerhunt');
            var openStreetMapGeocoder = GeocoderJS.createGeocoder('openstreetmap');




            var text = new ol.style.Text({
                textAlign: 'center',
                scale: 1.3,
                fill: new ol.style.Fill({
                    color: '#fff'
                }),
                stroke: new ol.style.Stroke({
                    color: '#000',
                    width: 3.5
                })
            });
            var selectText = new ol.style.Text({
                textAlign: 'center',
                scale: 1.4,
                fill: new ol.style.Fill({
                    color: '#fff'
                }),
                stroke: new ol.style.Stroke({
                    color: '#3399CC',
                    width: 3.5
                })
            });
            var defaultRiddleStyle = new ol.style.Style({
                image: new ol.style.Icon({
                    opacity: 1,
                    scale: 0.2,
                    src: pergaminoUrl
                }),
                text: text,
                zIndex: 'Infinity'
            });
            var failRiddleStyle = new ol.style.Style({
                image: new ol.style.Icon({
                    anchor: [0.5, 0.5],
                    opacity: 1,
                    scale: 0.1,
                    src: falloUrl
                }),
                text: text,
                zIndex: 'Infinity'
            });
            var defaultSelectRiddleStyle = new ol.style.Style({
                image: new ol.style.Icon({
                    opacity: 1,
                    scale: 0.29,
                    src: pergaminoUrl
                }),
                text: selectText,
                zIndex: 'Infinity'
            });
            var failSelectRiddleStyle = new ol.style.Style({
                image: new ol.style.Icon({
                    opacity: 1,
                    scale: 0.14,
                    src: falloUrl
                }),
                text: selectText,
                zIndex: 'Infinity'
            });
            var geoJSONFormat = new ol.format.GeoJSON();
            var view = new ol.View({
                center: [0, 0],
                zoom: 2,
                minZoom: 2,
            });
            var source = new ol.source.Vector({
                projection: 'EPSG:3857',
                loader: function (extent, resolution, projection) {
                    var geojson = ajax.call([{
                            methodname: 'mod_scavengerhunt_user_progress',
                            args: {
                                idScavengerhunt: idScavengerhunt,
                            }
                        }]);
                    geojson[0].done(function (response) {
                        console.log('json: ' + response.riddles);
                        if (response.status.code) {
                            toast(response.status.msg);
                        } else {
                            source.addFeatures(geoJSONFormat.readFeatures(response.riddles, {
                                'dataProjection': "EPSG:4326",
                                'featureProjection': "EPSG:3857"
                            }));
                        }
                    }).fail(function (error) {
                        console.log(error);
                        error_dialog(error.message);
                    });
                },
                strategy: ol.loadingstrategy.bbox
            });
            var vector = new ol.layer.Vector({
                source: source,
                style: styleFunction,
                /*updateWhileAnimating: true,
                 updateWhileInteracting: true*/
            });
            var select = new ol.interaction.Select({
                layers: [vector],
                style: selectStyleFunction
            });
            var geolocation = new ol.Geolocation({
                projection: view.getProjection(),
                trackingOptions: {
                    enableHighAccuracy: true,
                    maximumAge: 0,
                    timeout: 7000
                },
                tracking: false
            });
            var accuracyFeature = new ol.Feature();
            accuracyFeature.setStyle(new ol.style.Style({
                fill: new ol.style.Fill({
                    color: [255, 255, 255, 0.3]
                }),
                stroke: new ol.style.Stroke({
                    color: [0, 0, 0, 0.5],
                    width: 1
                }),
                zIndex: -1
            }));
            var positionFeature = new ol.Feature();
            positionFeature.setStyle(new ol.style.Style({
                image: new ol.style.Circle({
                    radius: 6,
                    fill: new ol.style.Fill({
                        color: [0, 0, 0, 1]
                    }),
                    stroke: new ol.style.Stroke({
                        color: [255, 255, 255, 1],
                        width: 2
                    })
                })
            }));
            var userPosition = new ol.layer.Vector({
                source: new ol.source.Vector({
                    features: [accuracyFeature, positionFeature]
                })
            });
            var markerFeature = new ol.Feature();
            markerFeature.setGeometry(null);
            markerFeature.setStyle(new ol.style.Style({
                image: new ol.style.Icon({
                    anchor: [0.5, 1],
                    opacity: 1,
                    scale: 0.3,
                    src: markerUrl
                })
            }));
            var markerVector = new ol.layer.Vector({
                source: new ol.source.Vector({
                    features: [markerFeature]
                })
            });
            $("#container").show();
            //Nuevo zoom personalizado
            var zoom = new ol.control.Zoom({target: "navigation", className: "custom-zoom"});
            var map = new ol.Map({
                layers: [
                    new ol.layer.Tile({
                        source: new ol.source.OSM()
                    }), vector, userPosition, markerVector
                ],
                controls: [zoom], //ol.control.defaults({rotate: false, attribution: false}),
                target: 'map',
                view: view
                        /*loadTilesWhileAnimating: true,
                         loadTilesWhileInteracting: true*/
            });
            map.addInteraction(select);

            //Si quiero que se actualicen con cada cambio de resolucion
            map.getView().on('change:resolution', function (evt) {
                source.clear();
            });

            function styleFunction(feature, resolution) {
                // get the incomeLevel from the feature properties
                var numRiddle = feature.get('numRiddle');
                if (!feature.get('success')) {
                    failRiddleStyle.getImage().setScale((view.getZoom() / 220));
                    failRiddleStyle.getText().setText('' + numRiddle);
                    return [failRiddleStyle];
                }
                defaultRiddleStyle.getImage().setScale((view.getZoom() / 110));
                defaultRiddleStyle.getText().setText('' + numRiddle);
                return [defaultRiddleStyle];
            }
            function selectStyleFunction(feature, resolution) {
                var numRiddle = feature.get('numRiddle');
                if (!feature.get('success')) {
                    failSelectRiddleStyle.getText().setText('' + numRiddle);
                    return [failSelectRiddleStyle];
                }
                defaultSelectRiddleStyle.getText().setText('' + numRiddle);
                return [defaultSelectRiddleStyle];
            }
            /*-------------------------------Funcionalidades-----------------------------------*/
            function autolocate(center, validate) {
                center = center || false;
                validate = validate || false;
                if (playwithoutmove && validate) {
                    if (markerFeature.getGeometry() !== null) {
                        validateLocation();
                    } else {
                        toast('Marca primero en el mapa el punto deseado');
                    }
                } else {
                    $.mobile.loading("show");
                    geolocation.setProperties({center: center, validateLocation: validate});
                    geolocation.setTracking(true);
                }
            }
            function setTextRiddle(feature) {
                debugger;
                var date = new Date(feature.get('date') * 1000);
                if (feature.get('success'))
                {
                    $("#nameRiddle").html(feature.get('name'));
                    $("#descriptionRiddle").html(feature.get('description'));
                    $("#timeLabel").html(date.toLocaleString());
                    //$("#infoRiddlePanel").trigger("updatelayout");
                    $("#infoRiddle").show();
                    $("#infoFailedLocation").hide();
                } else {
                    $("#nameFailedRiddle").html(feature.get('name'));
                    $("#timeLabelFailed").html(date.toLocaleString());
                    //$("#infoRiddlePanel").trigger("updatelayout");
                    $("#infoFailedLocation").show();
                    $("#infoRiddle").hide();
                }

                $("#infoRiddlePanel").panel("open");
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
                view.setResolution(2.388657133911758);
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

            function validateLocation() {
                var coordinates;
                if (playwithoutmove) {
                    coordinates = markerFeature.getGeometry();
                } else {
                    coordinates = positionFeature.getGeometry();
                }
                var position = geoJSONFormat.writeGeometry(coordinates, {
                    dataProjection: 'EPSG:4326',
                    featureProjection: 'EPSG:3857'
                });
                $.mobile.loading("show");
                var geojson = ajax.call([{
                        methodname: 'mod_scavengerhunt_validate_location',
                        args: {
                            idScavengerhunt: idScavengerhunt,
                            location: position
                        }
                    }]);
                geojson[0].done(function (response) {
                    source.clear();
                    $.mobile.loading("hide");
                    toast(response.status.msg);
                }).fail(function (error) {
                    $.mobile.loading("hide");
                    console.log(error);
                    toast(error.message);
                });
            }
            $("#autocomplete").on("filterablebeforefilter", function (e, data) {
                        var $ul = $(this),
                                    value = $(data.input).val(),
                                    html = "";
                        $ul.html(html);
                        if (value && value.length > 2) {
                    $.mobile.loading("show", {
                        text: "Buscando",
                        textVisible: true});
                    openStreetMapGeocoder.geocode(value, function (response) {
                        $.each(response, function (i, place) {
                            $('<li>')
                                    .hide().append($("<a href='#'>").text(place.totalName)
                                    ).appendTo($ul).click(function () {
                                $.mobile.pageContainer.pagecontainer("change", "#mappage");
                                var extend = new Array();
                                extend[0] = parseFloat(place.boundingbox[2]);
                                extend[1] = parseFloat(place.boundingbox[0]);
                                extend[2] = parseFloat(place.boundingbox[3]);
                                extend[3] = parseFloat(place.boundingbox[1]);
                                extend = ol.proj.transformExtent(extend, 'EPSG:4326', 'EPSG:3857');
                                flyTo(map, extend);
                            }).show();                                              
                        });
                                        $ul.listview("refresh");
                                        $ul.trigger("updatelayout");
                        $.mobile.loading("hide");
                            });
                }
                });
            /*-------------------------------Eventos-----------------------------------*/
            geolocation.on('change:position', function () {
                debugger;
                var coordinates = this.getPosition();
                if (this.get("center")) {
                    flyToPoint(map, coordinates);
                }
                positionFeature.setGeometry(coordinates ?
                        new ol.geom.Point(coordinates) : null);
                if (this.get("validateLocation")) {
                    validateLocation();
                }
            });
            geolocation.on('change:accuracyGeometry', function () {
                accuracyFeature.setGeometry(this.getAccuracyGeometry());
                this.setTracking(false);
                $.mobile.loading("hide");
            });
            geolocation.on('error', function (error) {
                debugger;
                $.mobile.loading("hide");
                toast(error.message);
            });
            select.on("select", function (features) {
                if (features.selected.length === 1) {
                    setTextRiddle(features.selected[0]);
                } else {
                    var coordinates = features.mapBrowserEvent.coordinate;
                    markerFeature.setGeometry(coordinates ?
                            new ol.geom.Point(coordinates) : null);
                }
            });
            //Bottons events
            $('#autolocate').on('click', function () {
                autolocate(true);
            });
            $('#infoRiddlePanel').panel({beforeclose: function () {
                    select.getFeatures().clear();
                }
            });
            $('#sendLocation').on('click', function () {
                autolocate(false, true);
            });


            /*-------------------------------Help functions -------------*/
            function toast(msg) {
                $("<div class='ui-loader ui-overlay-shadow  ui-corner-all' style='background-color:black;'><p>" + msg + "</p></div>")
                        .css({display: "block",
                            opacity: 0.90,
                            position: "fixed",
                            padding: "7px",
                            "text-align": "center",
                            width: "270px",
                            left: ($(window).width() - 284) / 2,
                            top: $(window).height() / 2})
                        .appendTo($.mobile.pageContainer).delay(1500)
                        .fadeOut(400, function () {
                            $(this).remove();
                        });
            }
            function error_dialog(msg) {
                var $popUp = $("<div/>").popup({
                    dismissible: false,
                    theme: "b",
                    overlyaTheme: "e",
                    transition: "pop"
                }).on("popupafterclose", function () {
                    window.location.replace("view.php?id=" + idModule);
                    //remove the popup when closing
                    $(this).remove();
                }).css({
                    'width': '270px',
                    'height': '200px',
                    'padding': '5px'
                });
                //create a title for the popup
                $("<h2/>", {
                    text: "Error"
                }).appendTo($popUp);
                //create a message for the popup
                $("<p/>", {
                    text: msg
                }).appendTo($popUp);
                $("<a>", {
                    text: "Continue"
                }).buttonMarkup({
                    inline: false,
                    mini: true,
                    icon: "forward"
                }).on("click", function () {
                    $popUp.popup("close");
                }).appendTo($popUp);
                $popUp.popup('open').trigger("create");
            }
        } // End of function playScavengerhunt
    };
    return init;
});