// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @module    mod_treasurehunt/gpx
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'jqueryui', 'mod_treasurehunt/jquery-ui-touch-punch', 'core/notification', 'mod_treasurehunt/ol', 'core/ajax', 'mod_treasurehunt/geocoder', 'mod_treasurehunt/ol3-layerswitcher'],
        function ($, jqui, touch, notification, ol, ajax, GeocoderJS, olLayerSwitcher) {


            var init = {

                viewgpx: function (cmid, treasurehuntid, strings, users) {

                    var openStreetMapGeocoder = GeocoderJS.createGeocoder('openstreetmap');
                    /**Load the control pane, treasurehunt and road list ***************************************************
                     */
                    $("#controlpanel").addClass('ui-widget-header ui-corner-all');

                    $('<div id="searchcontainer">').appendTo($("#controlpanel"));
                    $('<input type="search" placeholder="' + strings['searchlocation']
                            + '" class="searchaddress"/>')
                            .appendTo($("#searchcontainer"));
                    $('<span class="ui-icon  ui-icon-search searchicon"></span>').prependTo($("#searchcontainer"));
                    $('<span class="ui-icon  ui-icon-closethick closeicon invisible"></span>').appendTo($(
                            "#searchcontainer"));


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

                    var basemaps = new ol.layer.Group({
                        'title': strings['basemaps'],
                        layers: [
                            new ol.layer.Tile({
                                title: strings['aerialmap'],
                                type: 'base',
                                visible: false,
                                source: new ol.source.BingMaps({
                                    key: 'AmC3DXdnK5sXC_Yp_pOLqssFSaplBbvN68jnwKTEM3CSn2t6G5PGTbYN3wzxE5BR',
                                    imagerySet: 'AerialWithLabels',
                                    maxZoom: 19
                                            // use maxZoom 19 to see stretched tiles instead of the BingMaps
                                            // "no photos at this zoom level" tiles
                                            // maxZoom: 19
                                })
                            }), new ol.layer.Tile({
                                title: strings['roadmap'],
                                type: 'base',
                                visible: true,
                                source: new ol.source.OSM()
                            })
                        ]
                    });

                    var tracks = new ol.layer.Group({
                        'title': strings['trackviewer'],
                        layers: []});

                    function styleFunction(feature, icon) {
                        var styles = [
                               // shadow
                            new ol.style.Style({
                                stroke: new ol.style.Stroke({
                                    color: 'white',//[0, 0, 127, 0.25],
                                    width: 8
                                }),
                                //zIndex: 1
                            }),
                            new ol.style.Style({
                                stroke: new ol.style.Stroke({
                                    color: '#ff0000',
                                    width: 4,
                                    lineDash: [10,10],
                                }),
                                fill: new ol.style.Fill({
                                    color: 'rgba(255,0,0,0.5)'
                                })
                            }),
                         
                            
                        ];
                        var geometry = feature.getGeometry();
                        var coord = geometry.getLastCoordinate();
                        styles.push(new ol.style.Style({
                            geometry: new ol.geom.Point(coord),
                            image: new ol.style.Icon({
                                src: icon,
                                anchor: [0.75, 0.5],
                                rotateWithView: false,
                            })
                        }));
                        return styles;
                    }
                    ;
                    var layerSwitcher = new ol.control.LayerSwitcher();
                    var map = new ol.Map({
                        layers: [basemaps, tracks],
                        renderer: 'canvas',
                        target: document.getElementById('mapgpx'),
                        view: new ol.View({
                            center: [0, 0],
                            zoom: 2,
                            minZoom: 2
                        }),
                        controls: ol.control.defaults().extend([layerSwitcher])
                    });

                    layerSwitcher.showPanel();
                    var max_extent=null;
                    users.forEach(function (user) {
                        var gpxsource = new ol.source.Vector({
                            url: 'gpx.php?id=' + cmid + '&userid=' + user.id,
                            format: new ol.format.GPX()
                        });
                        var a = user.pic.indexOf('<img src="') + 10;
                        var b = user.pic.indexOf('"', a);
                        var iconurl = user.pic.substring(a, b);
                        var vector = new ol.layer.Vector({
                            source: gpxsource,
                            title: user.pic + '' + user.fullname,
                            style: function (feature) {

                                var selectedstyle = styleFunction(feature, iconurl);
                                return selectedstyle;
                            }
                        });
                        gpxsource.on('change', function () {
                            var extent = gpxsource.getExtent();
                            if (max_extent==null){
                                max_extent=extent;
                            }else{
                                max_extent[0]=Math.min(max_extent[0],extent[0]);
                                max_extent[1]=Math.min(max_extent[1],extent[1]);
                                max_extent[2]=Math.max(max_extent[2],extent[2]);
                                max_extent[3]=Math.max(max_extent[3],extent[3]);
                            }
                       
                            flyTo(map, null, max_extent);
                        }, this);
                        tracks.getLayers().push(vector);
                        layerSwitcher.renderPanel();
                    });

                    var displayFeatureInfo = function (pixel) {
                        var features = [];
                        map.forEachFeatureAtPixel(pixel, function (feature) {
                            features.push(feature);
                        });
                        if (features.length > 0) {
                            var info = [];
                            var i, ii;
                            for (i = 0, ii = features.length; i < ii; ++i) {
                                info.push(features[i].get('desc'));
                            }
                            document.getElementById('info').innerHTML = info.join(', ') || '(unknown)';
                            map.getTarget().style.cursor = 'pointer';
                        } else {
                            document.getElementById('info').innerHTML = '&nbsp;';
                            map.getTarget().style.cursor = '';
                        }
                    };

                    map.on('pointermove', function (evt) {
                        if (evt.dragging) {
                            return;
                        }
                        var pixel = map.getEventPixel(evt.originalEvent);
                        displayFeatureInfo(pixel);
                    });

                    map.on('click', function (evt) {
                        displayFeatureInfo(evt.pixel);
                    });

                    function flyTo(map, point, extent) {
                        var duration = 700;
                        var view = map.getView();
                        if (extent) {
                            view.fit(extent, {
                                duration: duration
                            });
                        } else {
                            view.animate({
                                zoom: 19,
                                center: point,
                                duration: duration
                            });
                        }
                    }
                    $(".searchaddress").autocomplete({
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
                                flyTo(map, null, extend);
                            } else {
                                var point = ol.proj.fromLonLat([ui.item.longitude, ui.item.latitude]);
                                flyTo(map, point);
                            }
                        },
                        autoFocus: true
                    }).on("click", function () {
                        $(this).autocomplete("search", $(this).value);
                    });
                    // Necesario para regular la anchura de los resultados de autocompletado
                    $.ui.autocomplete.prototype._resizeMenu = function () {
                        var ul = this.menu.element;
                        ul.outerWidth(this.element.outerWidth());
                    };

                    // Evento para que funcione bien el boton de cerrar en dispositivos tactiles
                    $(document).on('touchend', '.ui-dialog-titlebar-close', function () {
                        $(this).parent().siblings('.ui-dialog-content').dialog("close");
                    });

                    // /////
                    // CLEARABLE INPUT
                    function tog(v) {
                        return v ? 'removeClass' : 'addClass';
                    }
                    $('.searchaddress').on('input', function () {
                        $('.closeicon')[tog(this.value)]('invisible');
                    });
                    $('.closeicon').on('touchstart click', function (ev) {
                        ev.preventDefault();
                        $(this).addClass('invisible');
                        $('.searchaddress').val('').change().autocomplete("close");
                    });
                    //Al salirse
                    window.onbeforeunload = function (e) {
                        var message = strings['savewarning'],
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
