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
 * @module    mod_treasurehunt/play
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @author Adrian Rodriguez <huorwhisp@gmail.com>
 * @author Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery',
    'core/url',
    'mod_treasurehunt/ol',
    'core/ajax',
    'mod_treasurehunt/osm-geocoder',
	'mod_treasurehunt/viewgpx',
	'mod_treasurehunt/webqr',
    // 'core/str',
    'mod_treasurehunt/jquery.truncate',
    'mod_treasurehunt/jquery.mobile-config',
	'mod_treasurehunt/jquerymobile',
    ],
	    function ($, url, ol, ajax, OSMGeocoder, viewgpx, webqr) {

			console.log('loading play.js with jquery ' + $().jquery);
	        var init = {
	            playtreasurehunt: function (cmid, treasurehuntid, playwithoutmoving, groupmode,
	                    lastattempttimestamp,
	                    lastroadtimestamp, gameupdatetime, tracking, user, custommapconfig) {

	            	// I18n strings.
	            	var terms = ["stageovercome", "failedlocation", "stage", "stagename",
									        "stageclue", "question", "noanswerselected", "timeexceeded",
									        "searching", "continue", "noattempts", "aerialview", "roadview",
									        "noresults", "startfromhere", "nomarks", "updates", "activitytoendwarning",
									        "huntcompleted", "discoveredlocation", "answerwarning", "error", "pegmanlabel"];
					console.log('loading i18n strings');
	            	var stringsqueried = terms.map(function (term) {
	                     return {key: term, component: 'treasurehunt'};
					});
					i18n = i18nplay; // Use globally passed strings. Moodle 3.8 core/str broke with jquery 2.1.4.
	            	//str.get_strings(stringsqueried).done(function (strings) {
	            		// var i18n = [];
	            		// for (var i=0; i < terms.length; i++) {
	            		// 	i18n[terms[i]] = strings[i]; // JPC: TODO: Global strings.
						// }
						// i18n = i18nplay;
	            		// Detect custom image.
	            		if (typeof(custommapconfig) != 'undefined' &&
	            				custommapconfig !== null &&
	            				custommapconfig.custombackgroundurl !== null) {
	    						console.log('Detecting custom background image dimensions.');
	            			// Detect image size.
	    						var img = new Image();
	    					    img.addEventListener("load", function(){
	    					    	custommapconfig.imgwidth =  this.naturalWidth;
	    					    	custommapconfig.imgheight = this.naturalHeight;
	    							console.log('image is ' + this.naturalWidth + 'x' + this.naturalHeight + 'pixels');
	    					    	initplaytreasurehunt(i18n, cmid, treasurehuntid, playwithoutmoving, groupmode,
	    		                	        lastattempttimestamp,
	    		                	        lastroadtimestamp, gameupdatetime, tracking, user, custommapconfig);
	    					    });
	    					    img.src = custommapconfig.custombackgroundurl;
	            		} else {
	            			initplaytreasurehunt(i18n, cmid, treasurehuntid, playwithoutmoving, groupmode,
		                	        lastattempttimestamp,
		                	        lastroadtimestamp, gameupdatetime, tracking, user, custommapconfig);	            		}

	                // });
	            } // End of function playtreasurehunt.
	        };
	    return init;
		// Initialization function.
		function initplaytreasurehunt(strings, cmid, treasurehuntid, playwithoutmoving, groupmode,
		        lastattempttimestamp, lastroadtimestamp, gameupdatetime, tracking, user, custommapconfig){

			// I18n support.
			console.log('init player Openlayers');
			$.mobile.loading("show");
			var mapprojection = 'EPSG:3857';
			var custombaselayer = null;
			var geographictools = true;
			var defaultzoom = 15;
			// Support customized base layers.
			if (typeof(custommapconfig) != 'undefined' && custommapconfig !== null) {
				if (custommapconfig.custombackgroundurl != null) {
					console.log('config custom background image');
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
						defaultzoom = 5;
					}
					custombaselayer = new ol.layer.Image({
						  title : custommapconfig.layername,
						  name : custommapconfig.layername,
						  type: custommapconfig.layertype,
					      source: new ol.source.ImageStatic({
					        url: custommapconfig.custombackgroundurl,
					        imageExtent: customimageextent,
					      }),
					      opacity: 1.0
					    });
				} else if (custommapconfig.wmsurl !== null) {
					console.log('config custom wms server: ' + custommapconfig.wmsurl);
					var options = {
								source: new ol.source.TileWMS({
						            url: custommapconfig.wmsurl,
						            params: custommapconfig.wmsparams,
						          }),
								type: custommapconfig.layertype,
								title: custommapconfig.layername,
								name: custommapconfig.layername,
					        };
					if (custommapconfig.bbox[0] !== null &&
							custommapconfig.bbox[1] !== null &&
							custommapconfig.bbox[2] !== null &&
							custommapconfig.bbox[3] !== null) {
						var customwmsextent = ol.proj.transformExtent(custommapconfig.bbox, 'EPSG:4326', mapprojection);
						options.extent = customwmsextent;
					}
					custombaselayer = new ol.layer.Tile(options);
				}

				geographictools = custommapconfig.geographic;
			}
			if (geographictools === false) {
				console.log('geographic tools disabled');
				playwithoutmoving = true;
				$('#autolocate').hide();
			}

		    var parchmenturl = url.imageUrl('success_mark', 'treasurehunt'),
		            failureurl = url.imageUrl('failure_mark', 'treasurehunt'),
		            markerurl = url.imageUrl('my_location', 'treasurehunt'),
		            lastsuccessfulstage = {},
		            interval,
		            imgloaded = 0,
		            totalimg = 0,
		            infomsgs = [],
		            attemptshistory = [],
		            changesinattemptshistory = false,
		            changesinlastsuccessfulstage = false,
		            changesinquestionstage = false,
		            fitmap = false,
		            roadfinished = false,
		            available = true,
                qoaremoved = false;
      let osmGeocoderXHR;
      let osmTimer = 0;
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
		    var defaultstageStyle = new ol.style.Style({
		        image: new ol.style.Icon({
		            anchor: [0.5, 1],
		            opacity: 1,
		            scale: 0.5,
		            src: parchmenturl
		        }),
		        text: text,
		        zIndex: 'Infinity'
		    });
		    var failstageStyle = new ol.style.Style({
		        image: new ol.style.Icon({
		            anchor: [0.5, 1],
		            opacity: 1,
		            scale: 0.5,
		            src: failureurl
		        }),
		        text: text,
		        zIndex: 'Infinity'
		    });
		    var defaultSelectstageStyle = new ol.style.Style({
		        image: new ol.style.Icon({
		            anchor: [0.5, 1],
		            opacity: 1,
		            scale: 0.75,
		            src: parchmenturl
		        }),
		        text: selectText,
		        zIndex: 'Infinity'
		    });
		    var failSelectstageStyle = new ol.style.Style({
		        image: new ol.style.Icon({
		            anchor: [0.5, 1],
		            opacity: 1,
		            scale: 0.75,
		            src: failureurl
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
		            scale: 0.35,
		            src: markerurl
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
		                    // Use maxZoom 19 to see stretched tiles instead of the BingMaps
		                    // "no photos at this zoom level" tiles
		                    // maxZoom: 19.
		        })});
		    aeriallayer.set("name", strings["aerialview"]);
		    var roadlayer = new ol.layer.Tile({
		        source: new ol.source.OSM()
		    });
		    roadlayer.set("name", strings["roadview"]);

		    var layersbase = [];
		    var layersoverlay = [];
		    if ( custommapconfig === null || custommapconfig.onlybase === false) {
		    	layersbase = [aeriallayer, roadlayer];
		    }
		    if (custombaselayer !== null) {
		    	if (custommapconfig.layertype != 'overlay') {
		    		layersbase.push(custombaselayer);
		    	} else {
		    		layersoverlay.push(custombaselayer);
		    	}
			}
		    var layergroup = new ol.layer.Group({layers: layersbase});
			// Create placement for a popup over user marker.
			var overlay = viewgpx.createCoordsOverlay(
									"#mapplay",
									"css/playerfancy/ol-popup.css",
									strings["pegmanlabel"]
								);
		    // All layers hidden except last one.
		    var toplayer = null;
		    layergroup.getLayers().forEach(function (layer) {
		    	layer.setVisible(false);
		    	toplayer = layer;
		    });
		    toplayer.setVisible(true);

		    var view = new ol.View({
		        center: [0, 0],
		        zoom: 2,
		        minZoom: 2
			});
		    var select = new ol.interaction.Select({
		        layers: [attemptslayer],
		        style: select_style_function,
		        filter: function (feature, layer) {
		            if (feature.get('stageposition') === 0) {
		                return false;
		            }
		            return true;
		        }
		    });
		    var accuracyFeature = new ol.Feature();
		    accuracyFeature.setProperties({name: 'user_accuracy'});
		    accuracyFeature.setStyle(accuracyFeatureStyle);
		    var positionFeature = new ol.Feature();
		    positionFeature.setProperties({name: 'user_position'});
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
		    layers.push(layergroup);
		    layers = layers.concat(layersoverlay);
		    layers = layers.concat([ attemptslayer, userPosition, markerVector]);
		    // New Custom zoom.
			var zoom = new ol.control.Zoom({target: "navigation", className: "custom-zoom"});
			var attribution = new ol.control.Attribution({
				collapsible: false
			});
		    var map = new ol.Map({
		        layers: layers,
				overlays: [overlay],
				controls: [zoom, attribution], //ol.control.defaults({rotate: false, attribution: false}),
		        target: 'mapplay',
		        view: view,
		        loadTilesWhileAnimating: true,
		        loadTilesWhileInteracting: true
		    });
		    map.addInteraction(select);
		    // It initializes the game.
		    renew_source(false, true);
		    // For the game is updated every gameupdatetime seconds.
		    interval = setInterval(function () {
		        renew_source(false, false);
		    }, gameupdatetime);
		    // Initialize the page layers.

		    add_layergroup_to_list(layergroup);
		    layersoverlay.forEach(function (overlay) {
		    	add_layer_to_list(overlay);
		    });
		    if (tracking && user) {
		        var tracklayergroup = viewgpx.addgpxlayer(map, cmid, treasurehuntid, strings, user, "trackgroup");
		        tracklayergroup.set("name", tracklayergroup.get("title"));

		        var tracklayer = tracklayergroup.getLayers().item(0);
		        var htmltitle = tracklayer.get("title");
		        //var plaintitle = htmltitle.substring(htmltitle.indexOf('</a>') + 4);// Had a picture and a link.
		        tracklayer.set("name", htmltitle);
		        tracklayer.setVisible(false);
		        add_layer_to_list(tracklayer);
		    }
		    /*-------------------------------Functions-----------------------------------*/
		    function style_function(feature, resolution) {
		        // Get the income level from the feature properties.
		        var stageposition = feature.get('stageposition');
		        if (stageposition === 0) {
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
		                    textAlign: 'center',
		                    fill: new ol.style.Fill({
		                        color: 'rgb(255,255,255)'
		                    }),
		                    stroke: new ol.style.Stroke({
		                        color: '#3399CC',
		                        width: 2
							}),
							overflow: true,
                            scale: 2
		                })
		            });
		            return [styles];
		        }
		        if (!feature.get('geometrysolved')) {
		// Don't change the scale with the map. This is confusing failstageStyle.getImage().setScale((view.getZoom() / 30));.
		            failstageStyle.getText().setText('' + stageposition);
		            return [failstageStyle];
		        }
		// Don't change the scale with the map. This is confusing  defaultstageStyle.getImage().setScale((view.getZoom() / 100));.
		        defaultstageStyle.getText().setText('' + stageposition);
		        return [defaultstageStyle];
		    }
		    function select_style_function(feature, resolution) {
		        var stageposition = feature.get('stageposition');
		        if (!feature.get('geometrysolved')) {
		            failSelectstageStyle.getText().setText('' + stageposition);
		            return [failSelectstageStyle];
		        }
		        defaultSelectstageStyle.getText().setText('' + stageposition);
		        return [defaultSelectstageStyle];
		    }
		    function validateposition() {
		        if (playwithoutmoving && markerFeature.getGeometry() === null) {
		            toast(strings["nomarks"]);
		        } else {
		            renew_source(true, false);
		        }
		    }
		    function autocentermap() {
		        position = geolocation.getPosition();
		        fly_to(map, position);
		    }
		    function fly_to(map, point, extent) {
		        var duration = 700;
		        var view = map.getView();
		        if (extent) {
		            view.fit(extent, {
		                duration: duration
		            });
		        } else {
		            view.animate({
		                zoom: defaultzoom,
		                center: point,
		                duration: duration
		            });
		        }
		    }

		    /**
		     * Updates the model of the game.
		     * Notifies a new location for validation or a new answer to a question.
			 *
		     * @param {boolean} location requests a location validation.
		     * @param {boolean} initialize
		     * @param {int} selectedanswerid submits an answer to a question
		     * @param {string} qrtext submits a text scanned from a QRCode
		     * @returns {undefined}
		     */
		    function renew_source(location, initialize, selectedanswerid, qrtext) {
		        // var position holds the position to be evaluated. undef if no evaluation requested
		        var position;
		        var currentposition;
		        var coordinates;
		        var answerid;

		        if (playwithoutmoving) {
		            coordinates = markerFeature.getGeometry();
		        } else {
		            coordinates = positionFeature.getGeometry();
		        }
		        if (coordinates) {
		            currentposition = geoJSONFormat.writeGeometryObject(coordinates, {
		                dataProjection: 'EPSG:4326',
		                featureProjection: 'EPSG:3857'
		            });
		        }
		        if (selectedanswerid) {
		            $.mobile.loading("show");
		            answerid = selectedanswerid;
		        }
		        if (location) {
		            position = currentposition;
		            $.mobile.loading("show");
				}

				// Get the progress of the user: road is finished,
		        var geojson = ajax.call([{
		                methodname: 'mod_treasurehunt_user_progress',
		                args: {userprogress: {
		                        treasurehuntid: treasurehuntid,
		                        attempttimestamp: lastattempttimestamp,
		                        roadtimestamp: lastroadtimestamp,
		                        playwithoutmoving: playwithoutmoving,
		                        groupmode: groupmode,
		                        initialize: initialize,
		                        location: position,
		                        currentposition: (tracking && !playwithoutmoving) ? currentposition : undefined, // only for tracking in mobility.
		                        selectedanswerid: answerid,
		                        qoaremoved: qoaremoved,
		                        qrtext: qrtext}
		                }
		            }]);
		        geojson[0].done(function (response) {
		            var body = '';
		            qoaremoved = response.qoaremoved;
					roadfinished = response.roadfinished;
					// Is the activity still available or are we after the cut-off date (if set)?
					available = response.available;
					$.mobile.loading("hide");
		            // If I have sent a location or an answer I print out whether it is correct or not.
		            if (location || selectedanswerid) {
		                if (response.status !== null && available) {
		                    console.log(response.status.msg);
		                }
					}
		            // If you change the game mode (mobile or static).
		            if (playwithoutmoving != response.playwithoutmoving) {
		                playwithoutmoving = response.playwithoutmoving;
		                if (!playwithoutmoving) {
		                    markerFeature.setGeometry(null);
		                }
		            }
		            // If change the group mode.
		            if (groupmode != response.groupmode) {
		                groupmode = response.groupmode;
		            }
		            if (lastattempttimestamp !== response.attempttimestamp || lastroadtimestamp
		                    !== response.roadtimestamp || initialize || !available) {
		                lastattempttimestamp = response.attempttimestamp;
		                lastroadtimestamp = response.roadtimestamp;
		                if (response.attempthistory.length > 0) {
		                    attemptshistory = response.attempthistory;
		                    changesinattemptshistory = true;
		                }
		                // Compruebo si es distinto de null, lo que indica que se ha actualizado.
		                if (response.attempts || response.firststagegeom) {
		                    source.clear();
		                    if (response.firststagegeom) {
		                        source.addFeatures(geoJSONFormat.readFeatures(response.firststagegeom, {
		                            'dataProjection': "EPSG:4326",
		                            'featureProjection': "EPSG:3857"
		                        }));
		                    }
		                    if (response.attempts.features.length > 0) {
		                        source.addFeatures(geoJSONFormat.readFeatures(response.attempts, {
		                            'dataProjection': "EPSG:4326",
		                            'featureProjection': "EPSG:3857"
		                        }));
		                    }
						}

						// Display the buttons corresponding to the situation:

		                // Check if it exists, which indicates that it has been updated.
		                if (response.lastsuccessfulstage) {
		                    lastsuccessfulstage = response.lastsuccessfulstage;
							changesinlastsuccessfulstage = true;
                            $("#validateqr").hide();
		                    // If the stage is not solved I will notify you that there are changes.
		                    if (lastsuccessfulstage.question !== '') {

								// There is a question => disable location validation button and
								// set the big button as Question.
								changesinquestionstage = true;
								$('#validatelocation').show().addClass('ui-state-disabled');
								set_big_button_as("question");
		                    } else if (!lastsuccessfulstage.activitysolved) {
								// Previous activity not solved => disable location validation button
								$('#validatelocation').show().addClass('ui-state-disabled');
								set_big_button_as("question");
		                    } else {
                          $("#validatelocation")
                            .show()
                            .removeClass("ui-state-disabled");
                          set_big_button_as("clue");
                          // Show QR button?
                          if (response.qrexpected) {
                            $("#validateqr").show();
                          }
                        }
		                }
		                // Check if it is the first geometry or it is being initialized and center the map.
		                if (response.firststagegeom || initialize) {
		                    fitmap = true;
		                }
		                // Check the page we're on.
						var pageId = $.mobile.pageContainer.pagecontainer('getActivePage').prop("id");
						// Update the page model wherever the page we are.
		                if (pageId === 'mappage') {
							// Nothing special.
		                } else if (pageId === 'historypage') {
							set_attempts_history();
		                } else if (pageId === 'questionpage') {
							if (lastsuccessfulstage.question === '') {
								$.mobile.pageContainer.pagecontainer("change", "#mappage");
		                    } else {
								$.mobile.resetActivePageHeight();
		                    }
		                }
						set_lastsuccessfulstage();
						fit_map_to_source();
						set_question();
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
		            if (!roadfinished) {
		                $('#roadended').hide();
		            }
		            if (roadfinished || !available) {
						$('#validatelocation').show().addClass('ui-state-disabled');
		                $('#question_button').hide();
		                $('#roadended').show();
		                markerFeature.setGeometry(null);
		                playwithoutmoving = false;
		                clearInterval(interval);
		                $("#mapplay").css('opacity', '0.8');
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
		                fly_to(map, features[0].getGeometry().getCoordinates());
		            } else {
		                fly_to(map, null, source.getExtent());
		            }

		            fitmap = false;
		        }
		    }
		    function truncate( n, useWordBoundary ){
		        if (this.length <= n) { return this; }
		        var subString = this.substr(0, n-1);
		        return (useWordBoundary
		           ? subString.substr(0, subString.lastIndexOf(' '))
		           : subString) + "&hellip";
		    };
		    function set_lastsuccessfulstage() {
		        if (changesinlastsuccessfulstage) {
		            $("#lastsuccessfulstagename").text(strings['stage'] + ':' + lastsuccessfulstage.name);
		            $("#lastsuccesfulstagepos").text(lastsuccessfulstage.position +
		                    " / " + lastsuccessfulstage.totalnumber);
		            var maxchars = 100;
		            var briefing;
		            // Clean color styles from clue.
		            lastsuccessfulstage.clue = lastsuccessfulstage.clue.replace(/color/gm, 'color-disabled');
		            $("#lastsuccessfulstageclue").html(lastsuccessfulstage.clue);
		            var clueplaintext = lastsuccessfulstage.clue.replace(/<(?:.|\n)*?>/gm, '');
		            if (clueplaintext.length > maxchars*2 ) {
		            	$("#lastsuccessfulstageclue").truncate(maxchars*2);
		            	briefing = ' <a href="#historypage" data-transition="none" class="ui-btn ui-shadow ui-corner-all ui-btn-icon-left ui-btn-inline ui-icon-bullets ui-btn-icon-notext"></a> ';
		            	$("#lastsuccessfulstageclue").append(briefing);
		            } else {
		            	briefing = lastsuccessfulstage.clue;
		            }

		            $('[id=lastsuccessfulstagename2]').text(lastsuccessfulstage.name);
		            $('[id=lastsuccesfulstagepos2').text(lastsuccessfulstage.position +
		                    " / " + lastsuccessfulstage.totalnumber);
		            $("[id=lastsuccessfulstageclue2]").html(lastsuccessfulstage.clue);

		            if (lastsuccessfulstage.question !== '') {
		                $("#lastsuccessfulstageclue").append("<a href='#questionpage' " +
		                        "data-transition='none' class='ui-btn ui-shadow ui-corner-all " +
		                        "ui-btn-icon-left ui-btn-inline ui-mini ui-icon-comment'>"
		                        + strings['question']
		                        + "</a>");
		                $("[id=lastsuccessfulstageclue2]").append("<a href='#questionpage' " +
		                        "data-transition='none' class='ui-btn ui-shadow ui-corner-all " +
		                        "ui-btn-icon-left ui-btn-inline ui-mini ui-icon-comment'>"
		                        + strings['question']
								+ "</a>");
						$('#sendAnswer').show();
		            } else {
						$('#questionform').html('');
						$('#sendAnswer').hide();
					}

			// The 1st time, it is called after the map is loaded

					//$("#collapsibleset").collapsibleset("refresh");
					//open_next_activity_panel();
					//$("#infopanel").panel("open");
		            //$("#lastsuccessfulstage").collapsible("expand");
		            changesinlastsuccessfulstage = false;
		        }
		    }
		    function set_question() {
				if (changesinquestionstage) {
		        	// Clean color tag.
		        	lastsuccessfulstage.question = lastsuccessfulstage.question.replace(/color/gm, 'color-disabled');
					var questionform = "<legend>" + lastsuccessfulstage.question + "</legend>";
		            var counter = 1;
		            $.each(lastsuccessfulstage.answers, function (key, answer) {
		                var id = 'answer' + counter;
			        	// Clean color tag.
				    answer.answertext = answer.answertext.replace(/color/gm, 'color-disabled');
				    questionform += '<div class="answer"><input type="radio" name="answers" id="' + id + '"value="'
							+ answer.id + '">' +
							'<label style="color:white" for="' + id + '">' + answer.answertext + '</label></div>';
		                counter++;
					});

					$('#questionform').html(questionform).scrollTop();
					// Enhance this with jquery mobile.
					// JPC: It doesn't work in some cases (i.e. Moodle 3.7) probably some interaction with jquery, jqueryui and jquerymobile.
					// When the radio controls are not correctly shown its better to show the native controls.
					$('#questionform').enhanceWithin();
					setTimeout(() => $('#questionform input').removeClass('ui-helper-hidden-accessible') ,1); //.controlgroup("refresh");
		            changesinquestionstage = false;
		        }
			}

		    function set_attempts_history() {
		        // I'm checking to see if there have been any changes since the last time.
		        if (changesinattemptshistory) {
		            var $historylist = $("#historylist");
		            // I reset it
		            $historylist.html('');
		            changesinattemptshistory = false;
		            if (attemptshistory.length === 0) {
		                $("<li>" + strings["noattempts"] + "</li>").appendTo($historylist);
		            } else {
		                // I add each attempt
		                attemptshistory.forEach(function (attempt) {
		                    $("<li><span class='ui-btn-icon-left "
		                            + (attempt.penalty ? 'ui-icon-delete failedattempt' : 'ui-icon-check successfulattempt')
		                            + "' style='position:relative'></span>" + attempt.string + "</li>")
		                            .appendTo(
		                                    $historylist);
		                });
		            }
		            $historylist.listview("refresh");
		            $historylist.trigger("updatelayout");
		        }
		    }
		    function add_layer_to_list(layer) {
                var name = jQuery.parseHTML(layer.get("name"));
                var item = $('<li>', {
                    "data-icon": "check",
                    "class": layer.getVisible() ? "checked" : "unchecked"
                })
                    .append($('<a />', {
                        href: "#mappage"
                    })
                        .click(function () {
                            layer.setVisible(!layer.getVisible());
                        }).append(name)
                    );
                layer.on('change:visible', function () {
                    $(item).toggleClass('checked unchecked');
                });
                item.insertAfter('#baseLayer');
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
		    /**
		     * Geolocation component
		     * @type ol.Geolocation
		     */
		    var geolocation = new ol.Geolocation(/** @type {olx.GeolocationOptions} */({
		        projection: view.getProjection(),
		        trackingOptions: {
		            enableHighAccuracy: true,
		            maximumAge: 0,
		            timeout: 10000
		        }
			}));


			/*-------------------------------Events-----------------------------------*/

		    geolocation.on('change:position', function () {
		        var coordinates = this.getPosition();
		        positionFeature.setGeometry(coordinates ? new ol.geom.Point(coordinates) : null);
		        // The map must be re-centered in the new position
		        if (this.get("center")) {
		            fly_to(map, coordinates);
		            this.setProperties({"center": false}); // Disable center request. Deprecated.
		        }
		        // the new position must be evaluated
		        if (this.get("validate_location")) {
		            renew_source(true, false);
		            this.setProperties({"validate_location": false}); // Disable validate_location request. Deprecated.
		        }
		        $.mobile.loading("hide");
		    });
		    geolocation.on('change:accuracyGeometry', function () {
		        accuracyFeature.setGeometry(this.getAccuracyGeometry());
		        $.mobile.loading("hide");
		    });
		    var trackinggeolocationwarndispatched = false;
		    geolocation.on('error', function (error) {
		        $.mobile.loading("hide");
		        geolocation.setProperties({"user_denied": true});
		        toast(error.message);
		        if (error.code == error.PERMISSION_DENIED && tracking && !playwithoutmoving && trackinggeolocationwarndispatched == false) {
		            setTimeout(function () {
		                $('#popupgeoloc').popup("open", {positionTo: "window"});
		                trackinggeolocationwarndispatched = true;
		            }, 500);
		        }
		    });
		    geolocation.setTracking(tracking); // Start position tracking.

			//
		    select.on("select", function (features) {
		        if (features.selected.length === 1) {
		            if (lastsuccessfulstage.position === features.selected[0].get('stageposition')
		                    && features.selected[0].get('geometrysolved') && !roadfinished && available) {
						//$("#infopanel").panel("open");
						open_next_activity_panel();
		                $("#lastsuccessfulstage").collapsible("expand");
		            } else {
		                var title, stagename = features.selected[0].get('name'),
		                        stageclue = features.selected[0].get('clue'),
		                        info = features.selected[0].get('info'), body = '';
		                if (features.selected[0].get('geometrysolved'))
		                {
		                    if (stagename && stageclue) {
		                        title = strings["stageovercome"];
		                        body = get_block_text(strings["stagename"], stagename);
		                        body += get_block_text(strings["stageclue"], stageclue);
		                    } else {
		                        title = strings["discoveredlocation"];
		                    }
		                } else {
		                    title = strings["failedlocation"];
		                }
		                if (info) {
		                    body += '<p>' + info + '</p>';
		                }
		                create_popup('infostage', title, body);
		            }
		        }
		    });
		    map.on('click', function (evt) {
		        var hasFeature = false;
		        map.forEachFeatureAtPixel(map.getEventPixel(evt.originalEvent), function (feature, layer) {
		            if (feature.get('stageposition') === 0 ||
		            		feature.get('name') === "user_position" ||
		            		feature.get('name') === "user_accuracy") {
		                return false;
		            }
		            hasFeature = true;
		        });
		        if (playwithoutmoving && !hasFeature) {
		            var coordinates = map.getEventCoordinate(evt.originalEvent);
		            markerFeature.setGeometry(coordinates ?
		                    new ol.geom.Point(coordinates) : null);
					// Shorcut to Google Street View.
					if (custommapconfig === null || custommapconfig.geographic) {
						overlay.setPosition(evt.coordinate);
					}
		        }
		    });
		    $("#autocomplete").on("filterablebeforefilter", function (e, data) {
		    	if (!geographictools) {
		    		return;
          }
          if (osmGeocoderXHR) {
            osmGeocoderXHR.abort();
          }
          if (osmTimer) {
            clearTimeout(osmTimer);
          }
		      let $ul = $(this);
		      let value = $(data.input).val();
		      let html = "";
		      $ul.html(html);
		      if (value && value.length > 2) {
              $.mobile.loading("show", {
                  text: strings['searching'],
                  textVisible: true,
                  theme: "b"});

            osmTimer = setTimeout(() => {
              osmGeocoderXHR = OSMGeocoder.search(value)
                .done(resp => {
                  if (resp.length === 0) {
                    $ul.html("<li data-filtertext='" + value + "'>" + strings["noresults"] + "</li>");
                  } else {
                    $.each(resp, (i, place) => {
                      $("<li data-filtertext='" + value + "'>")
                        .hide()
                        .append($("<a href='#'>").text(place.display_name)
                        ).appendTo($ul).click(function () {
                          var extent = [];
                          extent[0] = parseFloat(place.boundingbox[2]);
                          extent[1] = parseFloat(place.boundingbox[0]);
                          extent[2] = parseFloat(place.boundingbox[3]);
                          extent[3] = parseFloat(place.boundingbox[1]);
                          extent = ol.proj.transformExtent(extent, 'EPSG:4326', 'EPSG:3857');
                          fly_to(map, null, extent);
                          $('#searchpanel').panel("close");
                        }).show();
                    });
                  }
                  //loading hide
                  $ul.listview("refresh");
                  $ul.trigger("updatelayout");
                })
                .fail(() => { })
                .always(() => {
                  osmGeocoderXHR = null;
                  $.mobile.loading("hide");
                });
            }, 400);
		        }
		    });
		    // Scroll to collapsible expanded
		    $("#infopanel").on("collapsibleexpand", "[data-role='collapsible']", function (event, ui) {
		        var innerinfopanel = $("#infopanel .ui-panel-inner");
		        innerinfopanel.animate({
		            scrollTop: parseInt($(this).offset().top - innerinfopanel.offset().top
		                    + innerinfopanel.scrollTop())
		        }, 500);
		    });
		    // Set a max-height to make large images shrink to fit the screen.
		    $(document).on("popupbeforeposition", function () {
		        var maxHeight = $(window).height() - 200 + "px";
		        $('.ui-popup [data-role="content"]').css("max-height", maxHeight);
		    });
		    // Remove the popup after it has been closed to manage DOM size.
		    $(document).on("popupafterclose", ".ui-popup:not(#QRdialog):not(#popupdialog):not(#popupgeoloc)", function () {
		        $(this).remove();
		        select.getFeatures().clear();
		    });

		    $(document).on("click", "#acceptupdates", function () {
		        infomsgs = [];
		    });
		    // Redraw map.
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
		                //set_lastsuccessfulstage();
		                fit_map_to_source();
		            }
		        } else if (pageId === 'historypage') {
		            if (event.type === 'pagecontainershow') {
		                set_attempts_history();
		            }
		        } else if (pageId === 'questionpage') {
		            if (event.type === 'pagecontainershow') {
						if ( (lastsuccessfulstage.question == null || lastsuccessfulstage.question === '')
							&& lastsuccessfulstage.activitysolved !== false ) {
		                    $.mobile.pageContainer.pagecontainer("change", "#mappage");
		                } else {
		                    set_question();
						}

		            }
		        }
				// Some Themes override this style breaking the layout (i.e. Klass via :target). Patch it.
				setTimeout( ()=> {
					var style = document.querySelector('#questionpage').style;
					style.setProperty('padding-top',style.paddingTop, 'important');
					}, 200);
			});
			// -------------
		    // Button events
		    // -------------
		    if (geographictools) {
			    $('#autolocate').on('click', function () {
			        if (geolocation.get("user_denied")) {
			            $('#popupgeoloc').popup("open", {positionTo: "window"});
			        } else {
			            autocentermap(true);
			        }
			    });
			}
		    $('#infopanel').panel({beforeclose: function () {
		            select.getFeatures().clear();
		        }
		    });
		    $('#sendLocation').on('click', function () {
		        validateposition(true);
		    });
		    $('#sendAnswer').on('click', function (event) {
		        // Selecciono la respuesta.
		        var selected = $("#questionform input[type='radio']:checked");
		        if (!available) {
		            event.preventDefault();
		            toast(strings['timeexceeded']);
		        } else {
		            if (selected.length === 0) {
		                event.preventDefault();
		                toast(strings['noanswerselected']);
		            } else {
		                renew_source(false, false, selected.val());
		            }
		        }

		    });
		    $('#validatelocation').on('click', function (event) {
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
		        if (lastsuccessfulstage.question !== '') {
		            event.preventDefault();
					toast(strings['answerwarning']);
					// TODO Show the clue or Question panel
					open_next_activity_panel();
		            //$("#infopanel").panel("open");
		            return;
		        }
		        if (!lastsuccessfulstage.activitysolved) {
		            event.preventDefault();
					toast(strings['activitytoendwarning']);
					open_next_activity_panel();
		            //$("#infopanel").panel("open");
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
		                'maximum-scale=1.0, user-scalable=0');
//		        $("#infopanel .ui-panel-inner").niceScroll();

		        $("#QRdialog").popup({
		            beforeposition: function (event, ui) {
//		                $(this).css({
//		                    width: window.innerWidth * 0.8,
//		                    height: window.innerHeight * 0.8,
//		                    top: 0,
//		                    left: 0
//		                });
		            	$(this).width(window.innerWidth * 0.9);
		            	$(this).height(window.innerHeight * 0.9);

		            },
		            afteropen: function (event, ui) {
		            	var contentdiv =  $(this).find("div[data-role='content']").first();
		            	var headerdiv = $(this).find("div[data-role='header']").first();
		            	var padding = parseInt(contentdiv.css('padding-top')) + parseInt(contentdiv.css('padding-bottom'));
		            	contentdiv.css('max-height', "1000px");
		            	$('#previewVideoDiv').width($(this).width() - padding)
	                						  .height($(this).height()
	                								  - $('#previewQRbuttons').height()
	                								  - headerdiv.height()
	                								  - padding * 2 );
		            	$('#previewQRvideo').show();
						webqr.loadQR(qrReaded, qrReport);
		            },
		            afterclose: function (event, ui) {
		                webqr.unloadQR(qrReport);
		            }.bind(this)
		        });
		    }
            $("#nextcamera").on('click', function() {
                var detectedCameras = webqr.getDetectedCameras();
                if (detectedCameras !== null) {
                    var nextcam = webqr.getnextwebCam();
                    toast('Give access to:' + detectedCameras[nextcam].name);
                }
                webqr.setnextwebcam(qrReport);
		    });
		    // Scan QR.
		    function qrReaded(value) {
		        close_popup($('#QRdialog'));
		        console.log(value);
		        toast("QR code readed: " + value);
		        renew_source(false, false, null, value);
		    }
		    function qrReport(message) {
		    	if (typeof(message) == 'string') {
		    		$('#errorQR').text(message);
		    	} else {
		    		if (message.cameras[message.camera].name !== null) {
		    			$('#errorQR').text(message.cameras[message.camera].name);
		    		}
		    		// hide/show next camera button.
		    		if (message.cameras.length > 1) {
		    			$('#nextcamera').show();
		    		} else {
		    			$('#nextcamera').hide();
		    		}
		    	}
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
			/** Open the Clue or Question panel */
			function open_next_activity_panel() {
				$("#bigbtn").click();
			}
		    function create_popup(type, title, body) {
		        var header = $('<div data-role="header"><h2>' + title + '</h2></div>'),
		                content = $('<div data-role="content" class="ui-content ui-overlay-b">' + body
		                        + '</div>'),
		                popup = $('<div data-role="popup" id="' + type + '"' +
		                        'data-theme="b" data-transition="slidedown"></div>');
		        if (type === 'infostage') {
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
		                    // Clear the fallback
		                    clearTimeout(fallback);
		                    $.mobile.loading("hide");
		                }
		            });
		            // Fallback in case the browser doesn't fire a load event.
		            var fallback = setTimeout(function () {
		                open_popup(popup);
		                $.mobile.loading("hide");
		            }, 2000);
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
		    function close_popup(popup) {
		        popup.popup("close")
		    }
		    function get_block_text(title, body) {
		        return '<div class="ui-bar ui-bar-a">' + title +
		                '</div><div class="ui-body ui-body-a">' + body +
		                '</div>';
			}
			function set_big_button_as(what) {
				var btn = $("#bigbtn")[0];
				btn.href = "#" + what + "page";
				btnImg = $("img", btn)[0];
				var url = btnImg.src.replace(/btn_(\w+)$/, "btn_" + what);
				btnImg.src = url;
			}
		}
	}
);
