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


    var playScavengerhunt = function (idModule, idScavengerhunt) {
        var s = url.imageUrl('images/pergamino', 'scavengerhunt');
        var defaultRiddleStyle = new ol.style.Style({
            image: new ol.style.Icon({
                opacity: 1,
                scale: 0.2,
                src: s
            }),
            text: new ol.style.Text({
                textAlign: 'center',
                scale: 1.3,
                fill: new ol.style.Fill({
                    color: '#fff'
                }),
                stroke: new ol.style.Stroke({
                    color: '#000',
                    width: 3.5
                })
            })
        });
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
                        source.addFeatures(new ol.format.GeoJSON().readFeatures(response.riddles, {
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
            style: styleFunction
                    /*updateWhileAnimating: true,
                     updateWhileInteracting: true*/
        });
        this.select = new ol.interaction.Select({
            layers: [vector],
            style: function (feature) {
                var styles = [
                    new ol.style.Style({
                        image: new ol.style.Icon({
                            opacity: 1,
                            scale: 0.29,
                            src: s
                        }),
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
        $('#mappage').show();
        var zoom = new ol.control.Zoom({target:"navigation"});
        var map = new ol.Map({
            layers: [
                new ol.layer.Tile({
                    source: new ol.source.OSM()
                }), vector
            ],
            controls: [zoom],//ol.control.defaults({rotate: false, attribution: false}),
            target: 'map',
            view: view,
            /*loadTilesWhileAnimating: true,
             loadTilesWhileInteracting: true*/
        });
        map.addInteraction(this.select);
        var geolocation = new ol.Geolocation({
            projection: view.getProjection(),
            trackingOptions: {
                enableHighAccuracy: true,
                maximumAge: 0,
                timeout: 7000
            },
            tracking: false
        });
        geolocation.on('change:position', function () {
            var coordinates = this.getPosition();
            view.setCenter(coordinates);
            view.setResolution(2.388657133911758);
            positionFeature.setGeometry(coordinates ?
                    new ol.geom.Point(coordinates) : null);
        });
        var accuracyFeature = new ol.Feature();
        geolocation.on('change:accuracyGeometry', function () {
            accuracyFeature.setGeometry(this.getAccuracyGeometry());
            this.setTracking(false);
        });

        var positionFeature = new ol.Feature();
        positionFeature.setStyle(new ol.style.Style({
            image: new ol.style.Circle({
                radius: 6,
                fill: new ol.style.Fill({
                    color: '#3399CC'
                }),
                stroke: new ol.style.Stroke({
                    color: '#fff',
                    width: 2
                })
            })
        }));
        new ol.layer.Vector({
            map: map,
            source: new ol.source.Vector({
                features: [accuracyFeature, positionFeature]
            })
        });
        //Si quiero que se actualicen con cada cambio de resolucion
        /*map.getView().on('change:center', function (evt) {
         debugger;
         source.clear();
         });*/

        function styleFunction(feature) {
            // get the incomeLevel from the feature properties
            var numRiddle = feature.get('numRiddle');
            if (!isNaN(numRiddle)) {
                defaultRiddleStyle.getText().setText('' + numRiddle);
            }
            return [defaultRiddleStyle];
        }
        /*-------------------------------Funcionalidades-----------------------------------*/
        this.autolocate = function () {
            geolocation.setTracking(true);
        };

    } // End of function playScavengerhunt

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
    return playScavengerhunt;
});