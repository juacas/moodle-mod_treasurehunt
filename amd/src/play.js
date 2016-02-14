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


define(['jquery', 'core/notification', 'core/str', 'openlayers', 'jqueryui', 'core/ajax', 'geocoderjs', 'core/templates', 'jquerymobile'], function ($, notification, str, ol, jqui, ajax, GeocoderJS, templates, $m) {


    var init = {
        playScavengerhunt: function (idModule, idScavengerhunt) {
            
            var view = new ol.View({
                center: [0, 0],
                zoom: 2
            });

            var map = new ol.Map({
                layers: [
                    new ol.layer.Tile({
                        source: new ol.source.OSM()
                    })
                ],
                controls: ol.control.defaults({rotate: false, attribution: false, zoom: false}),
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
            $m.loading("show", {
                text: "Cargando",
                textVisible: true,
                theme: 'b'});
            // This will call the function to load and render our template. 
            var promise = templates.render('mod_scavengerhunt/play', '');

            // The promise object returned by this function means "I've considered your request and will finish it later - I PROMISE!"

            // How we deal with promise objects is by adding callbacks.
            promise.done(function (source, javascript) {
                // Here eventually I have my compiled template, and any javascript that it generated.
                
                // I can execute the javascript (probably after adding the html to the DOM) like this:
                templates.runTemplateJS(javascript);
                $m.loading("hide");
            });

            // Sometimes things fail
            promise.fail(function (ex) {
                // Deal with this exception (I recommend core/notify exception function for this).
            });

        } // End of function playScavengerhunt
    }; // End of init var
    return init;
});