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
        'openlayers': {
            exports: 'OpenLayers'
        },
        'jquery.mobile-config': ['jquery'],
        'jquery.mobile': ['jquery', 'jquery.mobile-config']
    },
    paths: {
        openlayers: 'openlayers/ol-debug',
        geocoderjs: 'geocoder/geocoder',
        'jquery.mobile-config': 'jquery-mobile/jquery.mobile-config',
        'jquery.mobile': 'jquery-mobile/jquerymobile',
    }
});
define(['jquery', 'core/notification', 'core/str', 'core/url', 'openlayers', 'jqueryui', 'core/ajax', 'geocoderjs', 'core/templates', 'jquery.mobile-config', 'jquery.mobile'], function ($, notification, str, url, ol, jqui, ajax, GeocoderJS, templates) {

    var init = {
        playScavengerhunt: function (strings, cmid, idScavengerhunt, playwithoutmove, lastattempttimestamp, lastroadtimestamp) {
            var pergaminoUrl = url.imageUrl('images/pergamino', 'scavengerhunt');
            var falloUrl = url.imageUrl('images/fallo', 'scavengerhunt');
            var markerUrl = url.imageUrl('flag-marker', 'scavengerhunt');
            var openStreetMapGeocoder = GeocoderJS.createGeocoder('openstreetmap');
            var attempttimestamp = lastattempttimestamp;
            var roadtimestamp = lastroadtimestamp;
            var initialize = true;
            var lastsuccess = {};
            var interval;
            var imgloaded = 0, totalimg = 0;
            var infomsgs = [];
            /*-------------------------------Styles-----------------------------------*/
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
            var positionFeatureStyle = new ol.style.Style({
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
            });
            var accuracyFeatureStyle = new ol.style.Style({
                fill: new ol.style.Fill({
                    color: [255, 255, 255, 0.3]
                }),
                stroke: new ol.style.Stroke({
                    color: [0, 0, 0, 0.5],
                    width: 1
                }),
                zIndex: -1
            });
            var markerFeatureStyle = new ol.style.Style({
                image: new ol.style.Icon({
                    anchor: [0.5, 1],
                    opacity: 1,
                    scale: 0.3,
                    src: markerUrl
                })
            });
            /*-------------------------------Layers-----------------------------------*/
            var layers = [];
            var geoJSONFormat = new ol.format.GeoJSON();
            var source = new ol.source.Vector({
                projection: 'EPSG:3857'
            });
            var vector = new ol.layer.Vector({
                source: source,
                style: style_function
                        /*updateWhileAnimating: true,
                         updateWhileInteracting: true*/
            });
            var view = new ol.View({
                center: [0, 0],
                zoom: 2,
                minZoom: 2
            });
            var select = new ol.interaction.Select({
                layers: [vector],
                style: selectStyleFunction,
                filter: function (feature, layer) {
                    if (feature.get('numRiddle') === 0) {
                        return false;
                    }
                    return true;
                }
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
            accuracyFeature.setStyle(accuracyFeatureStyle);
            var positionFeature = new ol.Feature();
            positionFeature.setStyle(positionFeatureStyle);
            var userPosition = new ol.layer.Vector({
                source: new ol.source.Vector({
                    features: [accuracyFeature, positionFeature]
                })
            });
            var markerFeature = new ol.Feature();
            markerFeature.setGeometry(null);
            markerFeature.setStyle(markerFeatureStyle);
            var markerVector = new ol.layer.Vector({
                source: new ol.source.Vector({
                    features: [markerFeature]
                })
            });
            //Nuevo zoom personalizado
            var zoom = new ol.control.Zoom({target: "navigation", className: "custom-zoom"});
            var map = new ol.Map({
                layers: [new ol.layer.Tile({
                        visible: false,
                        source: new ol.source.BingMaps({
                            key: 'AmC3DXdnK5sXC_Yp_pOLqssFSaplBbvN68jnwKTEM3CSn2t6G5PGTbYN3wzxE5BR',
                            imagerySet: 'AerialWithLabels',
                            maxZoom: 19
                                    // use maxZoom 19 to see stretched tiles instead of the BingMaps
                                    // "no photos at this zoom level" tiles
                                    // maxZoom: 19
                        })}),
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
            function style_function(feature, resolution) {
                // get the incomeLevel from the feature properties
                var numRiddle = feature.get('numRiddle');
                if (numRiddle === 0) {
                    var fill = new ol.style.Fill({
                        color: 'rgba(255,255,255,0.4)'
                    });
                    var stroke = new ol.style.Stroke({
                        color: '#3399CC',
                        width: 1.25
                    });
                    var styles = new ol.style.Style({
                        image: new ol.style.Circle({
                            fill: fill,
                            stroke: stroke,
                            radius: 5
                        }),
                        fill: fill,
                        stroke: stroke,
                        text: new ol.style.Text({
                            text: strings["startfromhere"],
                            textAlign: 'center'
                        })
                    });
                    return [styles];
                }
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
            /*-------------------------------Functions-----------------------------------*/
            function autolocate(center, validate) {
                center = center || false;
                validate = validate || false;
                if (playwithoutmove && validate) {
                    if (markerFeature.getGeometry() !== null) {
                        renew_source(true, false);
                    } else {
                        toast(strings["nomarks"]);
                    }
                } else {
                    $.mobile.loading("show");
                    geolocation.setProperties({center: center, validate_location: validate});
                    geolocation.setTracking(true);
                }
            }

            function fly_to_point(map, point) {
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
            function fly_to_extent(map, extent) {
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
            function renew_source(location, initialize) {
                var position;
                if (location) {
                    var coordinates;
                    if (playwithoutmove) {
                        coordinates = markerFeature.getGeometry();
                    } else {
                        coordinates = positionFeature.getGeometry();
                    }
                    position = geoJSONFormat.writeGeometry(coordinates, {
                        dataProjection: 'EPSG:4326',
                        featureProjection: 'EPSG:3857'
                    });
                    $.mobile.loading("show");
                }
                var geojson = ajax.call([{
                        methodname: 'mod_scavengerhunt_user_progress',
                        args: {
                            idScavengerhunt: idScavengerhunt,
                            attempttimestamp: attempttimestamp,
                            roadtimestamp: roadtimestamp,
                            initialize: initialize,
                            location: position
                        }
                    }]);
                geojson[0].done(function (response) {
                    console.log('json: ' + response.riddles);
                    if (attempttimestamp !== response.attempttimestamp || roadtimestamp !== response.roadtimestamp || initialize) {
                        attempttimestamp = response.attempttimestamp;
                        roadtimestamp = response.roadtimestamp;
                        source.clear();
                        source.addFeatures(geoJSONFormat.readFeatures(response.riddles, {
                            'dataProjection': "EPSG:4326",
                            'featureProjection': "EPSG:3857"
                        }));
                        debugger;
                        // Compruebo si ambos objetos son iguales
                        if (JSON.stringify(lastsuccess) !== JSON.stringify(response.lastsuccess)) {
                            lastsuccess = response.lastsuccess;
                            $("#lastsuccessname").text(lastsuccess.name);
                            $("#lastsuccessdescription").html(lastsuccess.description);
                            $("#collapsibleset").collapsibleset("refresh");
                            $("#infopanel").panel("open");
                            $("#lastsuccess").collapsible("expand");
                        }
                        if (response.infomsg.length > 0) {
                            var body = '';
                            infomsgs.forEach(function (msg) {
                                body += '<p>' + msg + '</p>';
                            });
                            response.infomsg.forEach(function (msg) {
                                infomsgs.push(msg);
                                body += '<p>' + msg + '</p>';
                            });
                            // Because chaining popups not allowed in jquery mobile
                            create_popup('displayupdates', strings["updates"], body);
                        }
                        // Compruebo si es un multipolygon o acaba de empezar y lo centro
                        if (source.getFeatures()[0].getGeometry() instanceof ol.geom.MultiPolygon || initialize) {
                            fly_to_extent(map, source.getExtent());
                        }
                        if (location) {
                            $.mobile.loading("hide");
                            toast(response.status.msg);
                        }

                        //add_msg_to_info_panel(source);
                    }
                }).fail(function (error) {
                    console.log(error);
                    create_popup('displayerror', 'Error', error.message);
                    clearInterval(interval);
                });
            }
            function initLayerList() {
                $('#layerspage').page();
                $('<li>', {
                    "data-role": "list-divider",
                    text: "Vista del Mapa"
                })
                        .appendTo('#layerslist');
                var baseLayers = map.getLayersBy("isBaseLayer", true);
                $.each(baseLayers, function () {
                    add_layer_to_list(this);
                });
                $('<li>', {
                    "data-role": "list-divider",
                    text: "Capas"
                })
                        .appendTo('#layerslist');
                var overlayLayers = map.getLayersBy("isBaseLayer", false);
                $.each(overlayLayers, function () {
                    switch (this.name) {
                        case 'vector':
                        case 'Tesoro:Editable':
                        case 'Markers':
                            break;
                        default:
                            if (this.name.indexOf('OpenLayers_Control') == -1) {
                                add_layer_to_list(this);
                            }
                    }
                });
                $('#layerslist').listview('refresh');
                map.events.register("addlayer", this, function (e) {
                    switch (e.layer.name) {
                        case 'OpenLayers.Handler.Polygon':
                        case 'Pistas nuevo escenario':
                            break;
                        default:
                            if (e.layer.name.indexOf('OpenLayers_Control') == -1) {
                                add_layer_to_list(e.layer);
                            }
                    }
                    $("#layerslist").listview("refresh");
                });
            }

            function add_layer_to_list(layer) {
                var item = $('<li>', {
                    "data-icon": "check",
                    "class": layer.getVisible() ? "checked" : "unchecked"
                })
                        .append($('<a />', {
                            text: 'layer.name'
                        })
                                .click(function () {
                                    if (layer instanceof ol.layer.Tile) {
                                        map.getLayers().forEach(function (l) {
                                            if (l instanceof ol.layer.Tile) {
                                                if (l === layer) {
                                                    l.setVisible(true);
                                                } else {
                                                    l.setVisible(false);
                                                }
                                            }
                                        });
                                    } else {
                                        layer.setVisible(!layer.getVisible());
                                    }
                                })
                                );
                layer.on('change:visible', function () {
                    $(item).toggleClass('checked unchecked');
                });
                if (layer instanceof ol.layer.Tile) {
                    item.insertAfter('#baseLayer');
                } else {
                    item.insertAfter('#overlayLayer');
                }
            }


            /*-------------------------------Events-----------------------------------*/
            geolocation.on('change:position', function () {
                var coordinates = this.getPosition();
                if (this.get("center")) {
                    fly_to_point(map, coordinates);
                }
                positionFeature.setGeometry(coordinates ?
                        new ol.geom.Point(coordinates) : null);
                if (this.get("validate_location")) {
                    renew_source(true, false);
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
                    var title, riddlename = features.selected[0].get('name'),
                            riddledescription = features.selected[0].get('description'),
                            info = features.selected[0].get('info'), body;
                    body = get_block_text(strings["riddlename"], riddlename);
                    if (features.selected[0].get('success'))
                    {
                        title = strings["discoveredriddle"];
                        body += get_block_text(strings["riddledescription"], riddledescription);
                    } else {
                        title = strings["failedlocation"];
                    }
                    body += '<p>' + info + '</p>';
                    create_popup('inforiddle', title, body);
                } else {
                    var coordinates = features.mapBrowserEvent.coordinate;
                    markerFeature.setGeometry(coordinates ?
                            new ol.geom.Point(coordinates) : null);
                }
            });
            map.getLayers().forEach(function (layer, i) {
                add_layer_to_list(layer);
            });
            $("#autocomplete").on("filterablebeforefilter", function (e, data) {
                var $ul = $(this),
                        value = $(data.input).val(),
                        html = "";
                $ul.html(html);
                if (value && value.length > 2) {
                    $.mobile.loading("show", {
                        text: strings['searching'],
                        textVisible: true});
                    openStreetMapGeocoder.geocode(value, function (response) {
                        if (response[0] === false) {
                            $ul.html("<li data-filtertext='" + value + "'>" + strings["noresults"] + "</li>");
                        } else {
                            $.each(response, function (i, place) {
                                $("<li data-filtertext='" + value + "'>")
                                        .hide().append($("<a href='#'>").text(place.totalName)
                                        .append($("<p>").text(place.type))
                                        ).appendTo($ul).click(function () {
                                    var extent = [];
                                    extent[0] = parseFloat(place.boundingbox[2]);
                                    extent[1] = parseFloat(place.boundingbox[0]);
                                    extent[2] = parseFloat(place.boundingbox[3]);
                                    extent[3] = parseFloat(place.boundingbox[1]);
                                    extent = ol.proj.transformExtent(extent, 'EPSG:4326', 'EPSG:3857');
                                    fly_to_extent(map, extent);
                                    $('#searchpanel').panel("close");
                                }).show();
                            });
                        }
                        $ul.listview("refresh");
                        $ul.trigger("updatelayout");
                        $.mobile.loading("hide");
                    });
                }
            });
            // Scroll to collapsible expanded
            $("#infopanel").on("collapsibleexpand", "[data-role='collapsible']", function (event, ui) {
                var innerinfopanel = $("#infopanel .ui-panel-inner");
                innerinfopanel.animate({
                    scrollTop: parseInt($(this).offset().top - innerinfopanel.offset().top + innerinfopanel.scrollTop())
                }, 500);
            });
            // Set a max-height to make large images shrink to fit the screen.
            $(document).on("popupbeforeposition", function () {
                var maxHeight = $(window).height() - 200 + "px";
                $('.ui-popup [data-role="content"]').css("max-height", maxHeight);
            });
            // Remove the popup after it has been closed to manage DOM size
            $(document).on("popupafterclose", ".ui-popup:not(#popupDialog)", function () {
                $(this).remove();
                select.getFeatures().clear();
            });
            $(document).on("click", "#acceptupdates", function () {
                infomsgs = [];
            });
            // Redraw map
            $(window).on("pagecontainershow resize", function (event, ui) {
                var pageId = $.mobile.pageContainer.pagecontainer('getActivePage').prop("id");
                if (pageId === 'mappage') {
                    if (map instanceof ol.Map) {
                        setTimeout(function () {
                            map.updateSize();
                        }, 200);
                        if (initialize) {
                            initialize = false;
                            //Si quiero que se actualicen cada 20 seg
                            renew_source(false, true);
                            interval = setInterval(function () {
                                renew_source(false, false);
                            }, 20000);
                        }
                    } else {
                        // initialize map
                        debugger;
                    }
                }

            });
            //Buttons events
            $('#autolocate').on('click', function () {
                autolocate(true);
            });
            $('#infopanel').panel({beforeclose: function () {
                    select.getFeatures().clear();
                }
            });
            $('#sendLocation').on('click', function () {
                autolocate(false, true);
            });
            /*-------------------------------Initialize page -------------*/
            if ($.mobile.autoInitializePage === false) {
                $("#container").show();
                $.mobile.initializePage();
            }

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
            function create_popup(type, title, body) {
                var header = $('<div data-role="header"><h2>' + title + '</h2></div>'),
                        content = $('<div data-role="content" class="ui-content ui-overlay-b">' + body + '</div>'),
                        popup = $('<div data-role="popup" id="' + type + '"' +
                                'data-theme="b" data-transition="slidedown"></div>');
                if (type === 'inforiddle') {
                    $('<a href="#" data-rel="back" class="ui-btn ui-corner-all' +
                            'ui-btn-b ui-icon-delete ui-btn-icon-notext ui-btn-right"></a>').appendTo(header);
                }
                if (type === 'displayupdates') {
                    $('<p class="center-wrapper"><a id="acceptupdates" href="#" data-rel="back"'
                            + 'class="ui-btn center-button ui-mini ui-btn-inline">'
                            + strings["continue"] + '</a></p>')
                            .appendTo(content);
                    var attributes = {'data-dismissible': false, 'data-overlay-theme': "b"};
                    $(popup).attr(attributes);
                }
                if (type === 'displayerror') {
                    $('<p class="center-wrapper"><a href="view.php?id=' + cmid +
                            '" class="ui-btn  center-button ui-mini ui-icon-forward ui-btn-inline ui-btn-icon-left"'
                            + 'data-ajax="false">' + strings["continue"] + '</a></p>')
                            .appendTo(content);
                    var attributes = {'data-dismissible': false, 'data-overlay-theme': "b"};
                    $(popup).attr(attributes);
                }
                // Create the popup.
                $(header)
                        .appendTo($(popup)
                                .appendTo($.mobile.activePage)
                                .popup())
                        .toolbar()
                        .after(content);
                // Need it for calculate popup's dimesions when popup contents an image.
                totalimg = $(content).find('img');
                if (totalimg.length > 0) {
                    totalimg.one('load', function () {
                        imgloaded++;
                        if (totalimg.length === imgloaded) {
                            open_popup(popup);
                            imgloaded = 0;
                        }
                    });
                } else {
                    open_popup(popup);
                }


            }
            function open_popup(popup) {
                // Because chaining of popups not allowed in jquery mobile.
                if ($(".ui-popup-active").length > 0) {
                    $(".ui-popup").popup("close");
                    setTimeout(function () {
                        $(popup).popup("open", {positionTo: "window"});
                    }, 1000);
                } else {
                    $(popup).popup("open", {positionTo: "window"});
                }

            }
            function get_block_text(title, body) {
                return '<div class="ui-bar ui-bar-a">' + title +
                        '</div><div class="ui-body ui-body-a">' + body +
                        '</div>';
            }
        } // End of function playScavengerhunt
    };
    return init;
});