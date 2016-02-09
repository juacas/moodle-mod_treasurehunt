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


define(['jquery', 'core/notification', 'core/str', 'openlayers', 'jqueryui', 'core/ajax', 'geocoderjs', 'core/templates','jquerymobile'], function ($, notification, str, ol, jqui, ajax, GeocoderJS, templates,$m) {


    var init = {
        playScavengerhunt: function (idModule, idScavengerhunt) {
            var view = new ol.View({
                center: [0, 0],
                zoom: 2
            });

            var map = new ol.Map({
                layers: [
                    new ol.layer.Tile({
                        source: new ol.source.BingMaps({
                            key: 'AkGbxXx6tDWf1swIhPJyoAVp06H0s0gDTYslNWWHZ6RoPqMpB9ld5FY1WutX8UoF',
                            imagerySet: 'Road'
                        })
                    })
                ],
                controls: ol.control.defaults({rotate: false,attribution:false,zoom:false}),
                target: 'map',
                view: view
            });

            var geolocation = new ol.Geolocation({
                projection: view.getProjection(),
                tracking: true
            });
            geolocation.once('change:position', function () {
                view.setCenter(geolocation.getPosition());
                view.setResolution(2.388657133911758);
            });



        } // End of function init
    }; // End of init var
    return init;
});