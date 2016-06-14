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
    waitSeconds: 15,
    shim: {
        'openlayers': {
            exports: 'OpenLayers'
        },
        'jquery.mobile-config': ['jquery'],
        'jquery.mobile': ['jquery', 'jquery.mobile-config']
    },
    paths: {
        openlayers: 'openlayers/ol',
        geocoderjs: 'geocoder/geocoder',
        'jquery.mobile-config': 'jquery-mobile/jquery.mobile-config',
        'jquery.mobile': 'jquery-mobile/jquerymobile'
    }
});
define(['jquery', 'core/notification', 'core/str', 'core/url', 'openlayers', 'core/ajax', 'geocoderjs', 'core/templates', 'jquery.mobile'], function ($, notification, str, url, ol, ajax, GeocoderJS, templates) {

    var init = {
        playtreasurehunt: function (strings, cmid, treasurehuntid, playwithoutmove, groupmode, lastattempttimestamp, lastroadtimestamp, gameupdatetime) {
            var pergaminoUrl = url.imageUrl('images/pergamino', 'treasurehunt'),
                    falloUrl = url.imageUrl('images/fallo', 'treasurehunt'),
                    markerUrl = url.imageUrl('flag-marker', 'treasurehunt'),
                    openStreetMapGeocoder = GeocoderJS.createGeocoder('openstreetmap'),
                    lastsuccessfulriddle = {},
                    interval,
                    imgloaded = 0, totalimg = 0,
                    infomsgs = [],
                    attemptshistory = [],
                    changesinattemptshistory = false,
                    changesinlastsuccessfulriddle = false,
                    changesinquestionriddle = false,
                    fitmap = false,
                    roadfinished = false,
                    available = true;
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
            var attemptslayer = new ol.layer.Vector({
                source: source,
                style: style_function
                        /*updateWhileAnimating: true,
                         updateWhileInteracting: true*/
            });
            var aeriallayer = new ol.layer.Tile({
                visible: false,
                source: new ol.source.BingMaps({
                    key: 'AmC3DXdnK5sXC_Yp_pOLqssFSaplBbvN68jnwKTEM3CSn2t6G5PGTbYN3wzxE5BR',
                    imagerySet: 'AerialWithLabels',
                    maxZoom: 19
                            // use maxZoom 19 to see stretched tiles instead of the BingMaps
                            // "no photos at this zoom level" tiles
                            // maxZoom: 19
                })});
            aeriallayer.set("name", strings["aerialview"]);
            var roadlayer = new ol.layer.Tile({
                source: new ol.source.OSM()
            });
            roadlayer.set("name", strings["roadview"]);
            var layergroup = new ol.layer.Group({layers: [aeriallayer, roadlayer]});
            var view = new ol.View({
                center: [0, 0],
                zoom: 2,
                minZoom: 2
            });
            var select = new ol.interaction.Select({
                layers: [attemptslayer],
                style: select_style_function,
                filter: function (feature, layer) {
                    if (feature.get('noriddle') === 0) {
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
            layers = [layergroup, attemptslayer, userPosition, markerVector];
            //Nuevo zoom personalizado
            var zoom = new ol.control.Zoom({target: "navigation", className: "custom-zoom"});
            var map = new ol.Map({
                layers: layers,
                controls: [zoom], //ol.control.defaults({rotate: false, attribution: false}),
                target: 'mapplay',
                view: view
                        /*loadTilesWhileAnimating: true,
                         loadTilesWhileInteracting: true*/
            });
            map.addInteraction(select);
            // Inicializo
            //Si quiero que se actualicen cada 20 seg
            renew_source(false, true);
            interval = setInterval(function () {
                renew_source(false, false);
            }, gameupdatetime);
            // Inicializo la pagina de capas
            add_layergroup_to_list(layergroup);


            /*-------------------------------Functions-----------------------------------*/
            function style_function(feature, resolution) {
                // get the incomeLevel from the feature properties
                var noriddle = feature.get('noriddle');
                if (noriddle === 0) {
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
                if (!feature.get('geometrysolved')) {
                    failRiddleStyle.getImage().setScale((view.getZoom() / 220));
                    failRiddleStyle.getText().setText('' + noriddle);
                    return [failRiddleStyle];
                }
                defaultRiddleStyle.getImage().setScale((view.getZoom() / 110));
                defaultRiddleStyle.getText().setText('' + noriddle);
                return [defaultRiddleStyle];
            }
            function select_style_function(feature, resolution) {
                var noriddle = feature.get('noriddle');
                if (!feature.get('geometrysolved')) {
                    failSelectRiddleStyle.getText().setText('' + noriddle);
                    return [failSelectRiddleStyle];
                }
                defaultSelectRiddleStyle.getText().setText('' + noriddle);
                return [defaultSelectRiddleStyle];
            }
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
            function renew_source(location, initialize, selectedanswerid) {
                var position = 0;
                if (!selectedanswerid) {
                    selectedanswerid = 0;
                } else {
                    $.mobile.loading("show");
                }
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
                        methodname: 'mod_treasurehunt_user_progress',
                        args: {
                            treasurehuntid: treasurehuntid,
                            attempttimestamp: lastattempttimestamp,
                            roadtimestamp: lastroadtimestamp,
                            playwithoutmove: playwithoutmove,
                            groupmode: groupmode,
                            initialize: initialize,
                            location: position,
                            selectedanswerid: selectedanswerid
                        }
                    }]);
                geojson[0].done(function (response) {
                    console.log(response);
                    var body = '';

                    roadfinished = response.roadfinished;
                    available = response.available;
                    if (roadfinished || !available) {
                        clearInterval(interval);
                    }
                    // Si he enviado una localización o una respuesta imprimo si es correcta o no.
                    if (location || selectedanswerid) {
                        $.mobile.loading("hide");
                        if (response.status !== null) {
                            toast(response.status.msg);
                        }
                    }
                    // Si cambia el modo de juego (móvil o estático)
                    if (playwithoutmove != response.playwithoutmove) {
                        playwithoutmove = response.playwithoutmove;
                    }
                    // Si cambia el modo grupo
                    if (groupmode != response.groupmode) {
                        groupmode = response.groupmode;
                    }
                    if (lastattempttimestamp !== response.attempttimestamp || lastroadtimestamp !== response.roadtimestamp || initialize) {
                        lastattempttimestamp = response.attempttimestamp;
                        lastroadtimestamp = response.roadtimestamp;
                        attemptshistory = response.historicalattempts;
                        changesinattemptshistory = true;
                        // Compruebo si es distinto de null, lo que indica que se ha actualizado.
                        if (response.riddles !== null) {
                            source.clear();
                            source.addFeatures(geoJSONFormat.readFeatures(response.riddles, {
                                'dataProjection': "EPSG:4326",
                                'featureProjection': "EPSG:3857"
                            }));
                        }
                        // Compruebo si existe, lo que indica que se ha actualizado.
                        if (response.lastsuccessfulriddle) {
                            lastsuccessfulriddle = response.lastsuccessfulriddle;
                            changesinlastsuccessfulriddle = true;
                            // Si la pista no esta solucionada aviso de que hay cambios.
                            if (lastsuccessfulriddle.question !== '') {
                                changesinquestionriddle = true;
                            }
                        }
                        // Compruebo si es un multipolygon o se esta inicializando y lo centro.
                        if (source.getFeatures()[0].getGeometry() instanceof ol.geom.MultiPolygon || initialize) {
                            fitmap = true;
                        }
                        // Compruebo la pagina en la que nos encontramos.
                        var pageId = $.mobile.pageContainer.pagecontainer('getActivePage').prop("id");
                        if (pageId === 'mappage') {
                            set_lastsuccessfulriddle();
                            fit_map_to_source();
                        } else if (pageId === 'historypage') {
                            set_attempts_history();
                        } else if (pageId === 'questionpage') {
                            if (lastsuccessfulriddle.question === '') {
                                $.mobile.pageContainer.pagecontainer("change", "#mappage");
                            } else {
                                set_question();
                                $.mobile.resetActivePageHeight();
                            }
                        }
                    }
                    if (response.infomsg.length > 0) {
                        infomsgs.forEach(function (msg) {
                            body += '<p>' + msg + '</p>';
                        });
                        response.infomsg.forEach(function (msg) {
                            infomsgs.push(msg);
                            body += '<p>' + msg + '</p>';
                        });
                        create_popup('displayupdates', strings["updates"], body);
                    }
                }).fail(function (error) {
                    console.log(error);
                    create_popup('displayerror', strings['error'], error.message);
                    clearInterval(interval);
                });
            }
            function fit_map_to_source() {
                if (fitmap) {
                    var features = source.getFeatures();
                    if (features.length === 1 && features[0].getGeometry() instanceof ol.geom.Point) {
                        setTimeout(function () {
                            fly_to_point(map, features[0].getGeometry().getCoordinates());
                        }, 500);
                    } else {
                        setTimeout(function () {
                            fly_to_extent(map, source.getExtent());
                        }, 500);
                    }

                    fitmap = false;
                }
            }
            function set_lastsuccessfulriddle() {
                if (changesinlastsuccessfulriddle) {
                    $("#lastsuccessfulriddlename").text(lastsuccessfulriddle.name);
                    $("#lastsuccesfulriddlepos").text(lastsuccessfulriddle.number +
                            " / " + lastsuccessfulriddle.totalnumber);
                    $("#lastsuccessfulriddledescription").html(lastsuccessfulriddle.description);
                    if (lastsuccessfulriddle.question !== '') {
                        $("#lastsuccessfulriddledescription").append("<a href='#questionpage' " +
                                "data-transition='none' class='ui-btn ui-shadow ui-corner-all " +
                                "ui-btn-icon-left ui-btn-inline ui-mini ui-icon-comment'>" + strings['question'] + "</a>");
                    }
                    $("#collapsibleset").collapsibleset("refresh");
                    $("#infopanel").panel("open");
                    $("#lastsuccessfulriddle").collapsible("expand");
                    changesinlastsuccessfulriddle = false;
                }
            }
            function set_question() {
                if (changesinquestionriddle) {
                    $('#questionform').html("<legend>" + lastsuccessfulriddle.question + "</legend>");
                    var counter = 1;
                    $.each(lastsuccessfulriddle.answers, function (key, answer) {
                        var id = 'answer' + counter;
                        $('#questionform').append('<input type="radio" name="answers" id="' + id + '"value="' + answer.id + '">' +
                                '<label for="' + id + '">' + answer.answertext + '</label>');
                        counter++;
                    });
                    $('#questionform').enhanceWithin().controlgroup("refresh");
                    changesinquestionriddle = false;
                }

            }
            function set_attempts_history() {
                // Compruebo si ha habido cambios desde la ultima vez
                if (changesinattemptshistory) {
                    var $historylist = $("#historylist");
                    // Lo reinicio
                    $historylist.html('');
                    changesinattemptshistory = false;
                    if (attemptshistory.length === 0) {
                        $("<li>" + strings["noattempts"] + "</li>").appendTo($historylist);
                    } else {
                        // Anado cada intento
                        attemptshistory.forEach(function (attempt) {
                            $("<li><span class='ui-btn-icon-left " + (attempt.penalty ? 'ui-icon-delete failedattempt1' : 'ui-icon-check successfulattempt1') + "' style='position:relative'></span>" + attempt.string + "</li>").appendTo($historylist);

                        });
                    }
                    $historylist.listview("refresh");
                    $historylist.trigger("updatelayout");
                }
            }

            function add_layergroup_to_list(layergroup) {
                layergroup.getLayers().forEach(function (layer) {
                    var item = $('<li>', {
                        "data-icon": "check",
                        "class": layer.getVisible() ? "checked" : "unchecked"
                    })
                            .append($('<a />', {
                                text: layer.get("name"),
                                href: "#mappage"
                            })
                                    .click(function () {
                                        layergroup.getLayers().forEach(function (l) {
                                            if (l === layer) {
                                                l.setVisible(true);
                                            } else {
                                                l.setVisible(false);
                                            }
                                        });
                                    })
                                    );
                    layer.on('change:visible', function () {
                        $(item).toggleClass('checked unchecked');
                    });
                    item.insertAfter('#baseLayer');
                });

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
                $.mobile.loading("hide");
                toast(error.message);
            });
            select.on("select", function (features) {
                if (features.selected.length === 1) {
                    if (lastsuccessfulriddle.id === features.selected[0].getId()) {
                        $("#infopanel").panel("open");
                        $("#lastsuccessfulriddle").collapsible("expand");
                    } else {
                        var title, riddlename = features.selected[0].get('name'),
                                riddledescription = features.selected[0].get('description'),
                                info = features.selected[0].get('info'), body = '';
                        if (features.selected[0].get('success'))
                        {
                            body = get_block_text(strings["riddlename"], riddlename);
                            title = strings["discoveredriddle"];
                            body += get_block_text(strings["riddledescription"], riddledescription);
                        } else {
                            title = strings["failedlocation"];
                        }
                        if (info) {
                            body += '<p>' + info + '</p>';
                        }
                        create_popup('inforiddle', title, body);
                    }
                } else {
                    if (playwithoutmove) {
                        var coordinates = features.mapBrowserEvent.coordinate;
                        markerFeature.setGeometry(coordinates ?
                                new ol.geom.Point(coordinates) : null);
                    }
                }
            });
            $("#autocomplete").on("filterablebeforefilter", function (e, data) {
                var $ul = $(this),
                        value = $(data.input).val(),
                        html = "";
                $ul.html(html);
                if (value && value.length > 2) {
                    $.mobile.loading("show", {
                        text: strings['searching'],
                        textVisible: true,
                        theme: "b"});
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
                $.mobile.resetActivePageHeight();
                var pageId = $.mobile.pageContainer.pagecontainer('getActivePage').prop("id");
                if (pageId === 'mappage') {
                    if (event.type === "resize") {
                        setTimeout(function () {
                            map.updateSize();
                        }, 200);
                    } else {
                        map.updateSize();
                        set_lastsuccessfulriddle();
                        fit_map_to_source();
                    }
                } else if (pageId === 'historypage') {
                    if (event.type === 'pagecontainershow') {
                        set_attempts_history();
                    }
                } else if (pageId === 'questionpage') {
                    if (event.type === 'pagecontainershow') {
                        if (lastsuccessfulriddle.question === '') {
                            $.mobile.pageContainer.pagecontainer("change", "#mappage");
                            toast('no existe la pregunta');
                        } else {
                            set_question();
                        }
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
            $('#sendAnswer').on('click', function (event) {
                //selecciono la respuesta
                var selected = $("#questionform input[type='radio']:checked");
                if (!available) {
                    event.preventDefault();
                    toast(strings['timeexceeded']);
                } else {
                    if (selected.length === 0) {
                        event.preventDefault();
                        toast(strings['noasnwerselected']);
                    } else {
                        renew_source(false, false, selected.val());
                    }
                }

            });
            $('#validateLocation').on('click', function (event) {
                if (roadfinished) {
                    event.preventDefault();
                    toast(strings['huntcompleted']);
                    return;
                }
                if (!available) {
                    event.preventDefault();
                    toast(strings['timeexceeded']);
                    return;
                }
                if (lastsuccessfulriddle.question !== '') {
                    event.preventDefault();
                    toast(strings['answerwarning']);
                    $("#infopanel").panel("open");
                    return;
                }
                if (lastsuccessfulriddle.completion === 0) {
                    event.preventDefault();
                    toast(strings['activitytoendwarning']);
                    $("#infopanel").panel("open");
                    return;
                }
            });
            /*-------------------------------Initialize page -------------*/
            if ($.mobile.autoInitializePage === false) {
                $("#container").show();
                $("#loader").remove();
                $.mobile.initializePage();
                var viewport = document.querySelector("meta[name=viewport]");
                viewport.setAttribute('content', 'width=device-width, initial-scale=1.0, ' +
                        'maximum-scale=1.0, user-scalable=0,target-densitydpi=medium-dpi');
            }

            /*-------------------------------Help functions -------------*/
            function toast(msg) {
                if ($(".toast").length > 0) {
                    setTimeout(function () {
                        toast(msg);
                    }, 2500);
                } else {
                    $("<div class='ui-loader ui-overlay-shadow  ui-corner-all toast'>" +
                            "<p>" + msg + "</p></div>")
                            .css({left: ($(window).width() - 284) / 2,
                                top: $(window).height() / 2})
                            .appendTo($.mobile.pageContainer).delay(2000)
                            .fadeOut(400, function () {
                                $(this).remove();
                            });
                }
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
                    $.mobile.loading("show");
                    totalimg.one('load', function () {
                        imgloaded++;
                        if (totalimg.length === imgloaded) {
                            open_popup(popup);
                            imgloaded = 0;
                            $.mobile.loading("hide");
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
        } // End of function playtreasurehunt
    };
    return init;
});