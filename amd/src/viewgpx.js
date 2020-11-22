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
 * @module    mod_treasurehunt/viewgpx
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @author Adrian Rodriguez <huorwhisp@gmail.com>
 * @author Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'mod_treasurehunt/ol',  'mod_treasurehunt/ol3-layerswitcher', 'core/str'],
        function ($, ol, olLayerSwitcher, str) {
            console.log('Loading viewgpx.js with jquery ' + $().jquery);
			var refreshTracksInterval = 30;
			var refreshCounter = 0;
            var init = {
                addgpxlayer: function (map, cmid, treasurehuntid, strings, user, trackgroupname) {

                    var trackgroup = find_or_add_layergroup(map, trackgroupname);

                    load_gpx([user], cmid, map, trackgroup, null);
                    return trackgroup;
                },
                // @param users = "global" obtain data from users_param. The list of user structures.
                creategpxviewer: function (cmid, treasurehuntid, users, custommapconfig, refreshinterval) {
                    console.log('Creating gpxviewer.');
                    // I18n strings.
	            	var terms = ['aerialmap', 'roadmap', 'basemaps', 'searchlocation', 'trackviewer', 'pegmanlabel'];
	            	var stringsqueried = terms.map(function (term) {
	                     return {key: term, component: 'treasurehunt'};
	                });
                    refreshTracksInterval = refreshinterval;
                    if (users == 'global') {
                        // Get users from global (due to excessive size for AMD api).
                        var users = users_param;
                    }
	            	str.get_strings(stringsqueried).done(function (strings) {
                        console.log('I18N strings loaded.');
	            		var i18n = [];
	            		for (var i=0; i < terms.length; i++) {
	            			i18n[terms[i]] = strings[i];
	            		}
	            		// Detect custom image.
	            		if (typeof(custommapconfig) != 'undefined' &&
	            				custommapconfig !== null &&
	            				custommapconfig.custombackgroundurl !== null) {

	            			// Detect image size.
	    						var img = new Image();
	    					    img.addEventListener("load", function(){
	    					    	custommapconfig.imgwidth =  this.naturalWidth;
	    					    	custommapconfig.imgheight = this.naturalHeight;
	    					    	initcreategpxviewer(cmid, treasurehuntid, i18n, users, custommapconfig);
	    					    });
	    					    img.src = custommapconfig.custombackgroundurl;
	            		} else {
	            			initcreategpxviewer(cmid, treasurehuntid, i18n, users, custommapconfig);
	            		}
	                })
                },  // End of function creategpxviewer.
            createCoordsOverlay: function(selector = '#mappage', cssurl = 'css/ol-popup.css', label = '') {
                // Popup showing the position the user clicked.
                // Elements that make up the popup.
                $('<link>')
                    .appendTo('head')
                    .attr({
                        type: 'text/css',
                        rel: 'stylesheet',
                        href: cssurl
                    });
                var popupmarker = $('<div id="popupmarker" class="ol-popup"><a href="#" id="popup-closer" class="ol-popup-closer"></a><div id="popup-content"></div></div>');
                $(selector).append(popupmarker);
                var container = $('#popupmarker');
                var content = $('#popup-content');
                var closer = $('#popup-closer');
                var containernode = container.get(0);
                var timeouthandler = null;
                var createlink = this.createPegmanLink; // Function for events.
                /**
                 * Create an overlay to anchor the popup to the map.
                 */
                var overlay = new ol.Overlay(/** @type {olx.OverlayOptions} */
                    ({
                        element: containernode,
                        autoPan: true,
                        autoPanAnimation: {
                            duration: 250
                        }
                    }));
                /**
                 * Add a click handler to hide the popup.
                 *
                 * @return {boolean} Don't follow the href.
                 */
                closer.click(function () {
                    overlay.setPosition(undefined);
                    clearTimeout(timeouthandler);
                    closer.blur();
                    $('#popup-content').html('');
                    return false;
                });
                ol.events.listen(
                    overlay, ol.Object.getChangeEventType(ol.Overlay.Property.POSITION),
                    function (param) {
                        var latlon = this.getPosition();
                        clearTimeout(timeouthandler);
                        if (latlon) {
                            var pegmanlink = createlink(latlon, overlay.getMap(), label);
                            $('#popup-content').html(pegmanlink);
                            timeouthandler = setTimeout(() => { $('#popup-closer').trigger('click'); }, 2000);
                        }
                    }, overlay);
                // Disable timeout if hovering.
                container.hover( function() {
                    clearTimeout(timeouthandler);
                    }
                );
                return overlay;

            },
            createPegmanLink: function(coordinate, map, label) {
                var latlon = ol.proj.toLonLat(coordinate, map.getView().getProjection());
                var hdms = ol.coordinate.toStringHDMS(latlon);

                var pegmanlink = '<a target="street" href="http://maps.google.com/?cbll='
                    + latlon[1]
                    + ','
                    + latlon[0]
                    + '&cbp=12,20.09,,0,5&layer=c"><img src="pix/my_location.png" width="16" />' + label + '<br/></a><code>' + hdms + '</code>';
                return pegmanlink;
            }

            };
            return init;

            function initcreategpxviewer(cmid, treasurehuntid, strings, users, custommapconfig) {
                    console.log('Init gpx viewer.');
    				var mapprojection = 'EPSG:3857';
    				var custombaselayer = null;
    				var geographictools = true;
    				// Support customized base layers.
    				if (typeof(custommapconfig) != 'undefined' && custommapconfig != null) {
    					if (custommapconfig.custombackgroundurl != null) {
    						var customimageextent = ol.proj.transformExtent(custommapconfig.bbox, 'EPSG:4326', mapprojection);
    						if (!custommapconfig.geographic) {
    							// Round bbox and scales to allow vectorial SVG rendering. (Maintain ratio.)
    							var bboxwidth = customimageextent[2] - customimageextent[0];
    							var bboxheight = customimageextent[3] - customimageextent[1];
    							var centerwidth = (customimageextent[2] + customimageextent[0]) / 2;
    							var centerheight = (customimageextent[3] + customimageextent[1]) / 2;

    							var ratiorealmap = Math.round(bboxheight / custommapconfig.imgheight);
    							var adjwidth = Math.round(custommapconfig.imgwidth * ratiorealmap);
    							var adjheight = Math.round(custommapconfig.imgheight * ratiorealmap);
    							customimageextent = [centerwidth - adjwidth/2,
    								centerheight - adjheight/2,
    								centerwidth + adjwidth/2,
    								centerheight + adjheight/2];
    						}

    						custombaselayer = new ol.layer.Image({
    							  title : custommapconfig.layername,
    							  type: custommapconfig.layertype,
    						      source: new ol.source.ImageStatic({
    						        url: custommapconfig.custombackgroundurl,
    						        imageExtent: customimageextent,
    						      }),
    						      opacity: 1.0
    						    });
    					} else if (custommapconfig.wmsurl != null) {
    						var options = {
    									source: new ol.source.TileWMS({
    							            url: custommapconfig.wmsurl,
    							            params: custommapconfig.wmsparams,
    							          }),
    									type: custommapconfig.layertype,
    									title: custommapconfig.layername,
    						        };
    						if (custommapconfig.bbox[0] != null &&
    								custommapconfig.bbox[1] != null &&
    								custommapconfig.bbox[2] != null &&
    								custommapconfig.bbox[3] != null) {
    							var customwmsextent = ol.proj.transformExtent(custommapconfig.bbox, 'EPSG:4326', mapprojection);
    							options.extent = customwmsextent;
    						}
    						custombaselayer = new ol.layer.Tile(options);
    					}
    					geographictools = custommapconfig.geographic;
    				}
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
                                            // Use maxZoom 19 to see stretched tiles instead of the BingMaps
                                            // "no photos at this zoom level" tiles
                                            // maxZoom: 19.
                                })
                            }), new ol.layer.Tile({
                                title: strings['roadmap'],
                                type: 'base',
                                visible: true,
                                source: new ol.source.OSM()
                            })
                        ]
                    });
                    if (custombaselayer !== null) {
    					if (custommapconfig.onlybase) {
    						basemaps.getLayers().clear();
    					}
    					basemaps.getLayers().push(custombaselayer);
    				}
                    var tracks = new ol.layer.Group({
                        'title': strings['trackviewer'],
                        layers: []});
                    // TODO: test and include heatmap layer.
                    var heatmap = new ol.layer.Heatmap({
                        title: 'Heatmap',
                        source: new ol.source.Vector({
                            url: 'gpx.php?id=' + cmid + '&userid=24', // + users.map(function(e){return e.id;}),
                            format: new ol.format.GPX()
                        }),
                        blur: 20,
                        radius: 10
                    });

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
                    console.log('Map created in mapgpx!');
                    layerSwitcher.showPanel();
                    var selectSingleClick = new ol.interaction.Select({
                    	style: function (feature) {
                    		var layer = selectSingleClick.getLayer(feature);
                    		var iconurl = layer.iconurl;
                            var selectedstyle = trackStyleFunction(feature, iconurl, true);
                            return selectedstyle;
                        }
                    });
                    map.addInteraction(selectSingleClick);
                    load_gpx(users, cmid, map, tracks, layerSwitcher);
                    // Popup showing the position the user clicked.
                    var popup = new ol.Overlay({
                        element: document.getElementById('info')
                    });
                    map.addOverlay(popup);
                    // Duplicated function. TODO: refactor overlay definition.
                    function createPegmanLink(coordinate, map, label) {
                        var latlon = ol.proj.toLonLat(coordinate, map.getView().getProjection());
                        var hdms = ol.coordinate.toStringHDMS(latlon);

                        var pegmanlink = '<a target="street" href="http://maps.google.com/?cbll='
                            + latlon[1]
                            + ','
                            + latlon[0]
                            + '&cbp=12,20.09,,0,5&layer=c"><img src="pix/my_location.png" width="16" />' + label + '<br/></a><code>' + hdms + '</code>';
                        return pegmanlink;
                    }

                    function displayFeatureInfo(pixel, themap, coordinates, label) {
                        var features = [];
                        var msg = '';
                        themap.forEachFeatureAtPixel(pixel, function (feature) {
                            features.push(feature);
                        });
                        if (features.length > 0) {
                            var info = [];
                            var i, ii;
                            for (i = 0, ii = features.length; i < ii; ++i) {
                                info.push(features[i].get('desc'));
                            }
                            msg = info.join(', ') || '(unknown)';
                            map.getTarget().style.cursor = 'pointer';
                        } else {
                            msg = createPegmanLink(coordinates, map, label); // TODO: Show pegman.
                            map.getTarget().style.cursor = '';
                        }
                        return msg;
                    }

                    function showpopup(evt) {
                        var element = popup.getElement();
                        var coordinate = evt.coordinate;
                        $(element).popover('dispose'); // For jquery 4+ it is dispose instead of destroy!
                        popup.setPosition(coordinate);
                        $(element).popover({
                            'placement': 'top',
                            'animation': false,
                            'html': true,
                            'content': displayFeatureInfo(map.getEventPixel(evt.originalEvent), map, coordinate, strings['pegmanlabel'])
                        });
                        $(element).popover('show');
                    }
                    map.on('pointermove', function (evt) {
                        if (evt.dragging) {
                            return;
                        }
                    });
                    map.on('click', function (evt) {
                        showpopup(evt);
                    });
                    var interval = null;
                    $('#refreshtracks').change(function() {
                    	if ($(this).is(':checked')) {
                    		refreshCounter = 0;
                    		$("#timecircle").show();
                    		interval = setInterval(function() {
                    			$("#timecircle").text(refreshTracksInterval - (refreshCounter++) % refreshTracksInterval);
							}, 1000);
                    	} else {
                    		$("#timecircle").hide();
                    		clearInterval(interval);
                    	}
                    });
                } // End of function initcreategpxviewer.

            function find_or_add_layergroup(map, trackgroupname) {
                var layergroup = null;
                var layers = map.getLayers();
                layers.forEach(function (layer) {
                    if (layer.get('title') == trackgroupname) {
                        layergroup = layer;
                    }
                });

                if (!layergroup) {
                    layergroup = new ol.layer.Group({
                        'title': trackgroupname,
                        layers: []});
                    map.addLayer(layergroup);
                }
                return layergroup;
            }
            /**
             *
             * @param {ol.map} map
             * @param {Array} point center coordinates
             * @param {Array} extent bounding box coordinates
             */
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
            function load_gpx(users, cmid, map, tracksGroup, layerSwitcher) {
                var max_extent = null;
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
                            var selectedstyle = trackStyleFunction(feature, iconurl, false);
                            return selectedstyle;
                        }
                    });
                    vector.iconurl = iconurl;
                    setInterval(function() {
                    	if ($('#refreshtracks').is(':checked')) {
                    		refreshCounter = 0;
                    		gpxsource.clear();
                    		gpxsource.refresh();
                    	}
                    }, refreshTracksInterval * 1000);
                    vector.on('change:visible', function() {
                    	if (this.getVisible()) {
                            var extent = this.getSource().getExtent();
                            extent = get_valid_extent(extent);
                            if ( extent !== null) {
                                flyTo(map, null, extent);
                            }
                    	}
                    });
                    gpxsource.on('change', function () {
                    	if ($('#refreshtracks').is(':checked')) {
                    		return;
                    	}
                        var extent = gpxsource.getExtent();

                        if (max_extent === null) {
                            max_extent = extent;
                        } else {
                            max_extent[0] = Math.min(max_extent[0], extent[0]);
                            max_extent[1] = Math.min(max_extent[1], extent[1]);
                            max_extent[2] = Math.max(max_extent[2], extent[2]);
                            max_extent[3] = Math.max(max_extent[3], extent[3]);
                        }
                        flyTo(map, null, max_extent);
                    }, this);
                    tracksGroup.getLayers().push(vector);
                    if (layerSwitcher) {
                        layerSwitcher.renderPanel();
                    }
                });
            }
            /**
             *
             * @param {Array} extent boundingbox 2 coordinates
             */
            function get_valid_extent(extent) {
                if (! (extent instanceof Array) || extent.length != 4) {
                    return null;
                }
                if (extent[0] === Infinity ||
                    extent[1] === Infinity ||
                    extent[2] === Infinity ||
                    extent[3] === Infinity ) {
                    return null;
                }
                return extent;
            }
            function trackStyleFunction(feature, icon, selected) {
                var styles = [
                    // ...shadow.
                    new ol.style.Style({
                        stroke: new ol.style.Stroke({
                            color: 'rgba(255,255,255,0.8)',
                            width: 6 + 2 * selected
                        }),
                        zIndex: 1 + selected * 4
                    }),
                    new ol.style.Style({
                        stroke: new ol.style.Stroke({
                            color: '#ff0000',
                            width: 1 + selected,
                            lineDash: [10, 8],
                        }),
                        fill: new ol.style.Fill({
                            color: 'rgba(255,0,0,0.5)'
                        }),
                        zIndex: 2 + selected * 4
                    }),
                    new ol.style.Style({
                        image: new ol.style.Circle({
                          radius: 3 + 2 * selected,
                          stroke: new ol.style.Stroke({
                        	  color: 'white',
                        	  width: 1 + selected
                          }),
                          fill: new ol.style.Fill({
                            color: 'red'
                          })
                        }),
                        geometry: function(feature) {
                          // return the coordinates of the first ring of the polygon
                          var coordinates = feature.getGeometry().getCoordinates()[0];
                          return new ol.geom.MultiPoint(coordinates);
                        },
                        zIndex: 3 + selected * 4
                      })
                ];
                var geometry = feature.getGeometry();
                var coord = geometry.getLastCoordinate();
                styles.push(new ol.style.Style({
                    geometry: new ol.geom.Point(coord),
                    image: new ol.style.Icon({
                        src: icon,
                        anchor: [0.75, 0.5],
                        rotateWithView: false,
                    }),
                    zIndex: 4 + selected * 4
                }));
                return styles;
            }
        });
