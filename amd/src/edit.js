// This file is part of Moodle - http:// moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify.
// it under the terms of the GNU General Public License as published by.
// the Free Software Foundation, either version 3 of the License, or.
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,.
// but WITHOUT ANY WARRANTY; without even the implied warranty of.
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <http:// www.gnu.org/licenses/>.
/**
 * @module mod_treasurehunt/edit
 * @package mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>,
 *            Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @author Adrian Rodriguez <huorwhisp@gmail.com>
 * @author Juan Pablo de Castro <jpdecastro@tel.uva.es>*
 * @license http:// www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 * @license http:// www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

define(
		[ 'jquery', 'jqueryui', 'mod_treasurehunt/jquery-ui-touch-punch',
				'core/notification', 'mod_treasurehunt/ol', 'core/ajax',
				'mod_treasurehunt/geocoder',
				'mod_treasurehunt/ol3-layerswitcher', 'core/str' ],
		function($, jqui, touch, notification, ol, ajax, GeocoderJS,
				olLayerSwitcher, str) {

			
			
			function initedittreasurehunt(idModule, treasurehuntid, strings, selectedroadid, lockid, custommapconfig) {

				var mapprojection = 'EPSG:3857';
				var mapprojobj = new ol.proj.Projection(mapprojection);
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
				
				var treasurehunt = {
					"roads" : {}
				}, dirtyStages = new ol.source.Vector({
					projection : mapprojection
				}), originalStages = new ol.source.Vector({
					projection : mapprojection
				}), dirty = false, abortDrawing = false, drawStarted = false, stageposition, roadid, stageid, selectedFeatures, selectedstageFeatures = {}, idNewFeature = 1, vectorSelected = new ol.layer.Vector(
						{
							source : new ol.source.Vector({
								projection : mapprojection
							})
						}), openStreetMapGeocoder = GeocoderJS
						.createGeocoder('openstreetmap');
				
				// Load the control pane, treasurehunt and road list.
				$("#controlpanel").addClass(
						'ui-widget-header ui-corner-all');
				$('<span id="edition"/>').appendTo($("#controlpanel"));
				$(
						'<input type="radio" name="controlpanel" id="addradio" value="add">')
						.appendTo($("#edition"));
				$("<label>").attr('for', "addradio").text(strings['add'])
						.appendTo($("#edition"));
				$(
						'<input type="radio" name="controlpanel" id="modifyradio" value="modify">')
						.appendTo($("#edition"));
				$("<label>").attr('for', "modifyradio").text(strings['modify'])
						.appendTo($("#edition"));
				$('<button id="savestage"/>').attr('disabled', true).text(
						strings['save']).appendTo($("#controlpanel"));
				$('<button id="removefeature"/>').attr('disabled', true)
						.text(strings['remove']).appendTo(
								$("#controlpanel"));
				if (geographictools) {
					$('<div id="searchcontainer">')
							.appendTo($("#controlpanel"));
					$(
							'<input type="search" placeholder="'
									+ strings['searchlocation']
									+ '" class="searchaddress"/>').appendTo(
							$("#searchcontainer"));
					$(
							'<span class="ui-icon  ui-icon-search searchicon"></span>')
							.prependTo($("#searchcontainer"));
					$(
							'<span class="ui-icon  ui-icon-closethick closeicon invisible"></span>')
							.appendTo($("#searchcontainer"));
				}
				$('<button id="addstage" />').text(strings['stage'])
						.prependTo($("#controlpanel"));
				$('<button id="addroad" />').text(strings['road'])
						.prependTo($("#controlpanel"));
				$("#addradio").button({
					text : false,
					icons : {
						primary : "ui-icon-plusthick"
					}
				});
				$("#modifyradio").button({
					text : false,
					icons : {
						primary : "ui-icon-pencil"
					}
				});
				$("#removefeature").button({
					text : false,
					icons : {
						primary : "ui-icon-trash"
					}
				});
				$("#savestage").button({
					text : false,
					icons : {
						primary : "ui-icon-disk"
					}
				});
				$("#addstage").button({
					icons : {
						primary : "ui-icon-circle-plus"
					}
				});
				$("#addroad").button({
					icons : {
						primary : "ui-icon-circle-plus"
					}
				});
				// Lo cargo como un buttonset.
				$("#edition").buttonset({disabled: true});
				// Hago visible el controlpanel.
				$("#controlpanel").removeClass('invisible');
				// Creo el stagelist.
				$('<ul id="stagelist"/>').prependTo($("#stagelistpanel"));
				// Lo cargo como un sortable.
				$("#stagelist")
						.sortable(
								{
									handle : ".handle",
									tolerance : "pointer",
									zIndex : 9999,
									opacity : 0.5,
									forcePlaceholderSize : true,
									cursorAt : {
										top : -7
									},
									cursor : "n-resize",
									axis : 'y',
									items : "li:not(:hidden , .blocked)",
									helper : "clone",
									start : function(event, ui) {
										var roadid = ui.item.attr('roadid'), start_pos = ui.item
												.index('li[roadid="'
														+ roadid + '"]'), scrollParent = $(
												this).data("ui-sortable").scrollParent, maxScrollTop = scrollParent[0].scrollHeight
												- scrollParent[0].clientHeight
												- ui.helper.height();
										ui.item
												.data('start_pos',
														start_pos);
										// Set max scrollTop for sortable
										// scrolling.
										$(this).data('maxScrollTop',
												maxScrollTop);
									},
									sort : function(e, ui) {
										// Check if scrolling is out of
										// boundaries.
										var scrollParent = $(this).data(
												"ui-sortable").scrollParent, maxScrollTop = $(
												this).data('maxScrollTop');
										if (scrollParent.scrollTop() > maxScrollTop) {
											scrollParent
													.scrollTop(maxScrollTop);
										}
									},
									update : function(event, ui) {
										var start_pos = ui.item
												.data('start_pos'), roadid = ui.item
												.attr('roadid'), end_pos = ui.item
												.index('li[roadid="'
														+ roadid + '"]'), $listitems = $(
												this).children(
												'li[roadid="' + roadid
														+ '"]'), $listlength = $($listitems).length, i;
										if (start_pos === end_pos) {
											return;
										}
										if (start_pos < end_pos) {
											for (i = start_pos; i <= end_pos; i++) {
												relocatestageList(
														$listitems,
														$listlength,
														i,
														dirtyStages,
														originalStages,
														treasurehunt.roads[roadid].vector);
											}
										} else {
											for (i = end_pos; i <= start_pos; i++) {
												relocatestageList(
														$listitems,
														$listlength,
														i,
														dirtyStages,
														originalStages,
														treasurehunt.roads[roadid].vector);
											}
										}
										activateSaveButton();
										dirty = true;
									}
								}).disableSelection();
				function relocatestageList($listitems, $listlength, i,
						dirtyStages, originalStages, vector) {
					var newVal, $item = $($listitems).get([ i ]), roadid = $(
							$item).attr('roadid');
					newVal = Math.abs($($item).index(
							'li[roadid="' + roadid + '"]')
							- $listlength);
					$($item).attr('stageposition', newVal);
					$($item).find('.sortable-number').text(newVal);
					// Si esta seleccionado cambiamos el valor de
					// stageposition.
					if ($($item).hasClass("ui-selected")) {
						stageposition = newVal;
					}
					relocatenostage(parseInt($($item).attr('stageid'), 10),
							newVal, parseInt($($item).attr('roadid'), 10),
							dirtyStages, originalStages, vector);
				}

				// Creo el roadlistpanel.
				$('<ul id="roadlist"/>').appendTo($("#roadlistpanel"));
				// Anado los handle custom.
				/*
				 * Set control
				 * 
				 * @type edit_L27.ol.style.Style
				 */
				window.app = {};
				var app = window.app;
				/**
				 * @constructor
				 * @extends {ol.control.Control}
				 * @param {Object=}
				 *            opt_options Control options.
				 */
				app.generateResizableControl = function(opt_options) {
					var options = opt_options || {}, button = document
							.createElement('button'), element = document
							.createElement('div');
					button.innerHTML = '<>';
					button.id = 'egrip';
					element.className = 'ol-control ol-unselectable egrip-container';
					element.appendChild(button);
					ol.control.Control.call(this, {
						element : element,
						target : options.target
					});
				};
				ol.inherits(app.generateResizableControl,
						ol.control.Control);
				// Get style, vectors, map and interactions.
				var defaultstageStyle = new ol.style.Style({
					fill : new ol.style.Fill({
						color : 'rgba(0, 0, 0, 0.1)'
					}),
					stroke : new ol.style.Stroke({
						color : '#6C0492',
						width : 2
					}),
					image : new ol.style.Circle({
						radius : 5,
						fill : new ol.style.Fill({
							color : '#ffcc33'
						}),
						stroke : new ol.style.Stroke({
							color : '#000000',
							width : 2
						})
					}),
					text : new ol.style.Text({
						textAlign : 'center',
						scale : 1.3,
						fill : new ol.style.Fill({
							color : '#fff'
						}),
						stroke : new ol.style.Stroke({
							color : '#6C0492',
							width : 3.5
						})
					})
				});
				// Estilo etapa seleccionada.
				var selectedstageStyle = new ol.style.Style({
					fill : new ol.style.Fill({
						color : 'rgba(0, 0, 0, 0.05)'
					}),
					stroke : new ol.style.Stroke({
						color : '#FAC30B',
						width : 2
					}),
					image : new ol.style.Circle({
						radius : 5,
						fill : new ol.style.Fill({
							color : '#ffcc33'
						}),
						stroke : new ol.style.Stroke({
							color : '#000000',
							width : 2
						})
					}),
					text : new ol.style.Text({
						textAlign : 'center',
						scale : 1.3,
						fill : new ol.style.Fill({
							color : '#fff'
						}),
						stroke : new ol.style.Stroke({
							color : '#ffcc33',
							width : 3.5
						})
					}),
					zIndex : 'Infinity'
				});
				var vectorDraw = new ol.layer.Vector({
					source : new ol.source.Vector({
						projection : 'EPSG:3857'
					}),
					visible : false
				});
				var basemaps = new ol.layer.Group(
						{
							'title' : strings['basemaps'],
							layers : [
									new ol.layer.Tile(
											{
												title : strings['aerialmap'],
												type : 'base',
												visible : false,
												source : new ol.source.BingMaps(
														{
															key : 'AmC3DXdnK5sXC_Yp_pOLqssFSaplBbvN68jnwKTEM3CSn2t6G5PGTbYN3wzxE5BR',
															imagerySet : 'AerialWithLabels',
															maxZoom : 19
														// Use maxZoom 19 to
														// see stretched
														// tiles instead of
														// the BingMaps.
														// "no photos at
														// this zoom level"
														// tiles.
														// maxZoom: 19.
														})
											}), 
									new ol.layer.Tile({
										title : strings['roadmap'],
										type : 'base',
										visible : true,
										source : new ol.source.OSM()
									}),
									
									]
						});
				if (custombaselayer != null) {
					if (custommapconfig.onlybase) {
						basemaps.getLayers().clear();
					}
					basemaps.getLayers().push(custombaselayer);
				}
				// Popup showing the position the user clicked.
				// Elements that make up the popup.
				var container = document.getElementById('popup');
				var content = document.getElementById('popup-content');
				var closer = document.getElementById('popup-closer');
				/**
				 * Create an overlay to anchor the popup to the map.
				 */
				var overlay = new ol.Overlay(/** @type {olx.OverlayOptions} */
				({
					element : container,
					autoPan : true,
					autoPanAnimation : {
						duration : 250
					}
				}));
				/**
				 * Add a click handler to hide the popup.
				 * 
				 * @return {boolean} Don't follow the href.
				 */
				closer.onclick = function() {
					overlay.setPosition(undefined);
					closer.blur();
					return false;
				};
				// Layer selector...
				var layerSwitcher = new ol.control.LayerSwitcher();
				// Map viewer...
				var map = new ol.Map(
						{
							layers : [ basemaps, vectorDraw ],
							overlays : [ overlay ],
							projection : mapprojobj,
							renderer : 'canvas',
							target : 'mapedit',
							view : new ol.View({
								center : [ 0, 0 ],
								zoom : 2,
								minZoom : 2
							}),
							controls : ol.control
									.defaults()
									.extend(
											[
													layerSwitcher,
													new app.generateResizableControl(
															{
																target : document
																		.getElementById("stagelistpanel")
															}) ])
						});
				function showpopup(evt) {
					var coordinate = evt.coordinate;
					var latlon = ol.proj.toLonLat(coordinate, map.getView()
							.getProjection());
					var hdms = ol.coordinate.toStringHDMS(latlon);
					var pegman = '<a target="street" href="http://maps.google.com/?cbll='
							+ latlon[1]
							+ ','
							+ latlon[0]
							+ '&cbp=12,20.09,,0,5&layer=c"><img src="pix/my_location.png" width="16" /></a>';
					content.innerHTML = '<code>' + pegman + ''
							+ hdms + '</code>';
					overlay.setPosition(coordinate);
				}

				map.on('click', function(evt) {
					if (!Draw.getActive() && !Modify.getActive() &&
						(custommapconfig === null || custommapconfig.geographic)) {
						showpopup(evt);
					}
				});
				layerSwitcher.showPanel();
				// Creo el resizable.
				$("#stagelistpanel").resizable({
					handles : {
						'e' : $('#egrip')
					},
					resize : function(event, ui) {
						ui.size.height = ui.originalSize.height;
					},
					stop : function(event, ui) {
						map.updateSize();
					},
					cancel : ''
				});
				var Modify = {
					init : function() {
						this.select = new ol.interaction.Select(
								{
									// Si una feature puede ser seleccionada
									// o no.
									filter : function(feature) {
										if (selectedstageFeatures[feature
												.getId()]) {
											return true;
										}
										return false;
									},
									style : function(feature) {
										var fill = new ol.style.Fill({
											color : 'rgba(255,255,255,0.4)'
										});
										var stroke = new ol.style.Stroke({
											color : '#3399CC',
											width : 2
										});
										var styles = [ new ol.style.Style(
												{
													image : new ol.style.Circle(
															{
																fill : fill,
																stroke : stroke,
																radius : 5
															}),
													fill : fill,
													stroke : stroke,
													text : new ol.style.Text(
															{
																text : ''
																		+ feature
																				.get('stageposition'),
																textAlign : 'center',
																scale : 1.3,
																fill : new ol.style.Fill(
																		{
																			color : '#fff'
																		}),
																stroke : new ol.style.Stroke(
																		{
																			color : '#3399CC',
																			width : 3.5
																		})
															}),
													zIndex : 'Infinity'
												}) ];
										return styles;
									}
								});
						map.addInteraction(this.select);
						this.modify = new ol.interaction.Modify({
							features : this.select.getFeatures(),
							style : new ol.style.Style({
								image : new ol.style.Circle({
									radius : 5,
									fill : new ol.style.Fill({
										color : '#3399CC'
									}),
									stroke : new ol.style.Stroke({
										color : '#000000',
										width : 2
									})
								})
							}),
							deleteCondition : function(event) {
								return ol.events.condition
										.shiftKeyOnly(event)
										&& ol.events.condition
												.singleClick(event);
							}
						});
						map.addInteraction(this.modify);
						this.setEvents();
					},
					setEvents : function() {
						// Elimino la seleccion de features cuando cambia a
						// off.
						selectedFeatures = this.select.getFeatures();
						this.select.on('change:active', function() {
							selectedFeatures.clear();
							deactivateDeleteButton();
						});
						// Activo o desactivo el boton de borrar segun tenga
						// una feature seleccionada o no.
						this.select.on('select', function() {
							if (selectedFeatures.getLength() > 0) {
								activateDeleteButton();
							} else {
								deactivateDeleteButton();
							}
						});
						// Activo el boton de guardar segun se haya
						// modificado algo o no.
						this.modify.on('modifyend', function(e) {
							activateSaveButton();
							modifyFeatureToDirtySource(e.features,
									originalStages, dirtyStages,
									treasurehunt.roads[roadid].vector);
							dirty = true;
						});
					},
					getActive : function() {
						return (this.select.getActive() && this.modify
								.getActive()) ? true : false;
					},
					setActive : function(active) {
						this.select.setActive(active);
						this.modify.setActive(active);
					}
				};
				Modify.init();
				var Draw = {
					init : function() {
						map.addInteraction(this.Polygon);
						this.Polygon.setActive(false);
						this.setEvents();
					},
					Polygon : new ol.interaction.Draw({
						source : vectorDraw.getSource(),
						type : /** @type {ol.geom.GeometryType} */
						('Polygon'),
						style : new ol.style.Style({
							fill : new ol.style.Fill({
								color : 'rgba(0, 0, 0, 0.05)'
							}),
							stroke : new ol.style.Stroke({
								color : '#FAC30B',
								width : 2
							}),
							image : new ol.style.Circle({
								radius : 5,
								fill : new ol.style.Fill({
									color : '#ffcc33'
								}),
								stroke : new ol.style.Stroke({
									color : '#000000',
									width : 2
								})
							}),
							zIndex : 'Infinity'
						})
					}),
					setEvents : function() {
						// Fijo el treasurehunt al que pertenecen y activo
						// el boton de guardar .
						// segun se haya modificado algo o no.
						this.Polygon.on('drawend', function(e) {
							drawStarted = false;
							if (abortDrawing) {
								vectorDraw.getSource().clear();
								abortDrawing = false;
							} else {
								e.feature.setProperties({
									'roadid' : roadid,
									'stageid' : stageid,
									'stageposition' : stageposition
								});
								selectedstageFeatures[idNewFeature] = true;
								e.feature.setId(idNewFeature);
								idNewFeature++;
								// Agrego la nueva feature a su
								// correspondiente vector de poligonos.
								treasurehunt.roads[roadid].vector
										.getSource().addFeature(e.feature);
								// Agrego la feature a la coleccion de
								// multipoligonos sucios.
								addNewFeatureToDirtySource(e.feature,
										originalStages, dirtyStages);
								// Limpio el vector de dibujo.
								vectorDraw.getSource().clear();
								activateSaveButton();
								dirty = true;
							}
						});
						this.Polygon.on('drawstart', function(e) {
							drawStarted = true;
						});
					},
					getActive : function() {
						return this.Polygon.getActive();
					},
					setActive : function(active) {
						if (active) {
							this.Polygon.setActive(true);
						} else {
							this.Polygon.setActive(false);
						}
						map.getTargetElement().style.cursor = active ? 'none'
								: '';
					}
				};
				$(document).keyup(function(e) {
					// Si pulso la tecla esc dejo de dibujar.
					if (e.keyCode === 27 && drawStarted) // Esc.
					{
						abortDrawing = true;
						Draw.Polygon.finishDrawing();
					}
				});
				Draw.init();
				Draw.setActive(false);
				Modify.setActive(false);
				deactivateEdition();
				// The snap interaction must be added after the Modify and
				// Draw interactions.
				// in order for its map browser event handlers to be fired
				// first. Its handlers.
				// are responsible of doing the snapping.
				var snap = new ol.interaction.Snap({
					source : vectorDraw.getSource()
				});
				map.addInteraction(snap);
				// Cargo las features.
				fetchTreasureHunt(treasurehuntid);
				function addNewFeatureToDirtySource(dirtyFeature,
						originalStages, dirtySource) {

					var stageid = dirtyFeature.get('stageid');
					var roadid = dirtyFeature.get('roadid');
					var feature = dirtySource.getFeatureById(stageid);
					if (!feature) {
						feature = originalStages.getFeatureById(stageid)
								.clone();
						feature.setId(stageid);
						dirtySource.addFeature(feature);
					}
					if (feature.get('idFeaturesPolygons') === 'empty') {
						feature.setProperties({
							'idFeaturesPolygons' : ''
									+ dirtyFeature.getId()
						});
						// Quito la advertencia.
						notEmptystage(stageid, roadid);
					} else {
						feature.setProperties({
							'idFeaturesPolygons' : feature
									.get('idFeaturesPolygons')
									+ ',' + dirtyFeature.getId()
						});
					}
					feature.getGeometry().appendPolygon(
							dirtyFeature.getGeometry());
				}

				function modifyFeatureToDirtySource(dirtyFeatures,
						originalStages, dirtySource, vector) {

					dirtyFeatures
							.forEach(function(dirtyFeature) {
								var stageid = dirtyFeature.get('stageid');
								var feature = dirtySource
										.getFeatureById(stageid);
								var idFeaturesPolygons;
								if (!feature) {
									feature = originalStages
											.getFeatureById(stageid)
											.clone();
									feature.setId(stageid);
									dirtySource.addFeature(feature);
								}
								var multipolygon = new ol.geom.MultiPolygon(
										[]);
								// Get those multipolygons of vector layer .
								idFeaturesPolygons = feature.get(
										'idFeaturesPolygons').split(",");
								for (var i = 0, j = idFeaturesPolygons.length; i < j; i++) {
									multipolygon.appendPolygon(vector
											.getSource().getFeatureById(
													idFeaturesPolygons[i])
											.getGeometry().clone());
								}
								feature.setGeometry(multipolygon);
							});
				}

				function removefeatureToDirtySource(dirtyFeatures,
						originalStages, dirtySource, vector) {

					dirtyFeatures
							.forEach(function(dirtyFeature) {

								var stageid = dirtyFeature.get('stageid');
								var roadid = dirtyFeature.get('roadid');
								var feature = dirtySource
										.getFeatureById(stageid);
								var idFeaturesPolygons;
								var remove;
								if (!feature) {
									feature = originalStages
											.getFeatureById(stageid)
											.clone();
									feature.setId(stageid);
									dirtySource.addFeature(feature);
								}
								var multipolygon = new ol.geom.MultiPolygon(
										[]);
								// Get those multipolygons of vector layer
								// which stageid isn't id of dirtyFeature.
								idFeaturesPolygons = feature.get(
										'idFeaturesPolygons').split(",");
								for (var i = 0, j = idFeaturesPolygons.length; i < j; i++) {
									if (idFeaturesPolygons[i] != dirtyFeature
											.getId()) {
										multipolygon
												.appendPolygon(vector
														.getSource()
														.getFeatureById(
																idFeaturesPolygons[i])
														.getGeometry()
														.clone());
									} else {
										remove = i;
									}
								}
								feature.setGeometry(multipolygon);
								if (multipolygon.getPolygons().length) {
									idFeaturesPolygons.splice(remove, 1);
									feature
											.setProperties({
												'idFeaturesPolygons' : idFeaturesPolygons
														.join()
											});
								} else {
									feature.setProperties({
										'idFeaturesPolygons' : 'empty'
									});
									emptystage(stageid, roadid);
								}

							});
				}

				function styleFunction(feature) {
					// Get the incomeLevel from the feature properties.
					var stageposition = feature.get('stageposition');
					if (!isNaN(stageposition)) {
						selectedstageStyle.getText().setText(
								'' + stageposition);
						defaultstageStyle.getText().setText(
								'' + stageposition);
					}
					// if there is no level or its one we don't recognize,.
					// return the default style (in an array!).
					if (selectedstageFeatures[feature.getId()]) {
						return [ selectedstageStyle ];
					}
					// check the cache and create a new style for the
					// income.
					// level if its not been created before.
					// at this point, the style for the current level is in
					// the cache.
					// so return it (as an array!).
					return [ defaultstageStyle ];
				}

				function fetchTreasureHunt(treasurehuntid) {
					var geojson = ajax.call([ {
						methodname : 'mod_treasurehunt_fetch_treasurehunt',
						args : {
							treasurehuntid : treasurehuntid
						}
					} ]);
					geojson[0]
							.done(
									function(response) {
										$('.treasurehunt-editor-loader')
												.hide();
										if (response.status.code) {
											notification.alert('Error',
													response.status.msg,
													'Continue');
										} else {
											var vector;
											var geoJSONFeatures = response.treasurehunt.stages;
											var geoJSON = new ol.format.GeoJSON();
											var features;
											var roads = response.treasurehunt.roads;
											// Moodle 2 returns an object
											// with indexed properties
											// instead an array...
											if (!Array.isArray(roads)) {
												roads = Object
														.values(roads);
											}
											// Necesito indexar cada camino
											// en el objeto global
											// treasurehunt.
											roads
													.forEach(function(road) {
														// agrego los
														// vectores a cada
														// camino.
														// cast string "0"
														// or "1" to
														// boolean.
														road.blocked = road.blocked == true;
														addroad2ListPanel(
																road.id,
																road.name,
																road.blocked);
														features = geoJSON
																.readFeatures(
																		road.stages,
																		{
																			dataProjection : 'EPSG:4326',
																			featureProjection : mapprojection
																		});
														originalStages
																.addFeatures(features);
														delete road.stages;
														vector = new ol.layer.Vector(
																{
																	source : new ol.source.Vector(
																			{
																				projection : mapprojection
																			}),
																	updateWhileAnimating : true,
																	style : styleFunction
																});
														features
																.forEach(function(
																		feature) {
																	if (feature
																			.getGeometry() === null) {
																		feature
																				.setGeometry(new ol.geom.MultiPolygon(
																						[]));
																	}
																	var polygons = feature
																			.getGeometry()
																			.getPolygons();
																	var idNewFeatures = 'empty';
																	var stageposition = feature
																			.get('stageposition');
																	var name = feature
																			.get('name');
																	var clue = feature
																			.get('clue');
																	var stageid = feature
																			.getId();
																	var blocked = road.blocked;
																	for (var i = 0; i < polygons.length; i++) {
																		var newFeature = new ol.Feature(
																				feature
																						.getProperties());
																		newFeature
																				.setProperties({
																					'stageid' : stageid
																				});
																		var polygon = polygons[i];
																		newFeature
																				.setGeometry(polygon);
																		newFeature
																				.setId(idNewFeature);
																		if (i === 0) {
																			idNewFeatures = idNewFeature;
																		} else {
																			idNewFeatures = idNewFeatures
																					+ ','
																					+ idNewFeature;
																		}
																		idNewFeature++;
																		vector
																				.getSource()
																				.addFeature(
																						newFeature);
																	}
																	feature
																			.setProperties({
																				idFeaturesPolygons : ''
																						+ idNewFeatures
																			});
																	addstage2ListPanel(
																			stageid,
																			road.id,
																			stageposition,
																			name,
																			clue,
																			blocked);
																	if (polygons.length === 0) {
																		emptystage(stageid);
																	}
																});
														road.vector = vector;
														map
																.addLayer(vector);
														treasurehunt.roads[road.id] = road;
													});

											// Ordeno la lista de etapas.
											sortList();
											// Selecciono el camino de la
											// URL si existe o sino el
											// primero.
											if (typeof treasurehunt.roads[selectedroadid] !== 'undefined') {
												roadid = selectedroadid;
												if (treasurehunt.roads[roadid].blocked) {
													deactivateAddstage();
												} else {
													activateAddstage();
												}
												selectRoad(
														roadid,
														treasurehunt.roads[roadid].vector,
														map);
											} else {
												selectfirstroad(
														treasurehunt.roads,
														map);
											}

										}
									}).fail(function(error) {
								$('.treasurehunt-editor-loader').hide();
								console.log(error);
								notification.exception(error);
							});
				}

				// Panel functions .
				function removefeatures(selectedFeatures, vector) {
					selectedFeatures.forEach(function(feature) {
						vector.getSource().removeFeature(feature);
					});
					selectedFeatures.clear();
				}
				function selectfirstroad(roads, map) {
					var noroads = 0;
					for ( var road in roads) {
						if (treasurehunt.roads.hasOwnProperty(road)) {
							noroads = 1;
							roadid = road;
							if (roads[roadid].blocked) {
								deactivateAddstage();
							} else {
								activateAddstage();
							}
							selectRoad(roadid, roads[roadid].vector, map);
							break;
						}
					}
					if (noroads === 0) {
						deactivateAddstage();
						$("#addroad").addClass("highlightbutton");
						$("#stagelistpanel").addClass("invisible");
						map.updateSize();
					}
				}

				function addstage2ListPanel(stageid, roadid, stageposition,
						name, clue, blocked) {
					if ($('#stagelist li[stageid="' + stageid + '"]').length < 1) {
						var li = $(
								'<li stageid="' + stageid + '" roadid="'
										+ roadid + '" stageposition="'
										+ stageposition + '"/>').appendTo(
								$("#stagelist"));
						li.addClass("ui-corner-all")
							.append(
									"<div class='stagename'>" + name
											+ "</div>")
							.append(
									"<div class='modifystage'>"
											+ "<span class='ui-icon ui-icon-pencil'></span>"
											+ "<span class='ui-icon ui-icon-info' data-id='#dialoginfo"
											+ stageid + "'>"
											+ "<div id='dialoginfo"
											+ stageid + "' title='"
											+ name + "'>" + clue
											+ "</div></span></div>");
						if (blocked) {
							li.addClass("blocked")
								.prepend(
										"<div class='nohandle validstage'>"
												+ "<span class='ui-icon ui-icon-locked'></span>"
												+ "<span class='sortable-number'>"
												+ stageposition
												+ "</span></div>");
						} else {
							li.prepend("<div class='handle validstage'>"
										+ "<span class='ui-icon ui-icon-arrowthick-2-n-s'></span>"
										+ "<span class='sortable-number'>"
										+ stageposition
										+ "</span></div>");
							li.children(".modifystage")
									.prepend(
											"<span class='ui-icon ui-icon-trash'></span>");
						}
						$('#dialoginfo' + stageid).dialog({
							maxHeight : 500,
							autoOpen : false
						});
					} else {
						console.log('El li con '
										+ stageid
										+ ' no ha podido crearse porque ya existia uno');
					}
				}

				function addroad2ListPanel(roadid, name, blocked) {
					// Si no existe lo agrego.
					if ($('#roadlist li[roadid="' + roadid + '"]').length < 1) {
						var li = $(
								'<li roadid="' + roadid + '" blocked="'
										+ blocked + '"/>').appendTo(
								$("#roadlist"));
						li
								.addClass("ui-corner-all")
								.append(
										"<div class='roadname'>" + name
												+ "</div>")
								.append(
										"<div class='modifyroad'><span class='ui-icon ui-icon-trash'></span>"
												+ "<span class='ui-icon ui-icon-pencil'></span></div>");
					}

				}
				function deleteRoad2ListPanel(roadid) {
					var $li = $('#roadlist li[roadid="' + roadid + '"]');
					if ($li.length > 0) {
						var $lis = $('#stagelist li[roadid="' + roadid
								+ '"]');
						// Elimino el li del roadlist.
						$li.remove();
						// Elimino todos los li del stagelist.
						$lis.remove();
					}
				}
				function deletestage2ListPanel(stageid, dirtySource,
						originalStages, vectorOfPolygons) {
					var $li = $('#stagelist li[stageid="' + stageid + '"]');
					if ($li.length > 0) {
						var roadid = $li.attr('roadid');
						var start_pos = $li.index('li[roadid="' + roadid
								+ '"]');
						// Elimino el li.
						$li.remove();
						var $stagelist = $("#stagelist li[roadid='"
								+ roadid + "']");
						// Compruebo el resto de etapas de la lista.
						check_stage_list($stagelist);
						var $listlength = $stagelist.length;
						// Recoloco el resto.
						for (var i = 0; i <= start_pos - 1; i++) {
							relocatestageList($stagelist, $listlength, i,
									dirtySource, originalStages,
									vectorOfPolygons);
						}
					}
				}
				function sortList() {
					// Ordeno la lista .
					$('#stagelist li').sort(
							function(a, b) {
								var contentA = parseInt($(a).attr(
										'stageposition'));
								var contentB = parseInt($(b).attr(
										'stageposition'));
								return (contentA < contentB) ? 1
										: (contentA > contentB) ? -1 : 0;
							}).appendTo($("#stagelist"));
				}

				function emptystage(stageid, roadid) {
					var $treasurehunt = $('#stagelist li[stageid="'
							+ stageid + '"]');
					$treasurehunt.children(".handle,.nohandle").addClass(
							'invalidstage').removeClass('validstage');
					// Compruebo si en este camino hay alguna etapa sin
					// geometria.
					if (roadid) {
						$("label[for='addradio']")
								.addClass('highlightbutton');
						var $stagelist = $("#stagelist li[roadid='"
								+ roadid + "']");
						if ($stagelist.length >= 2) {
							$("#erremptystage").removeClass("invisible");
						}
					}
				}

				function notEmptystage(stageid, roadid) {
					var $treasurehunt = $('#stagelist li[stageid="'
							+ stageid + '"]');
					$treasurehunt.children(".handle, .nohandle").addClass(
							'validstage').removeClass('invalidstage');
					if (roadid) {
						// Compruebo si en este camino hay alguna etapa sin
						// geometria.
						$("label[for='addradio']").removeClass(
								'highlightbutton');
						var $stagelist = $("#stagelist li[roadid='"
								+ roadid + "']");
						if ($stagelist.find(".invalidstage").length === 0) {
							$("#erremptystage").addClass("invisible");
						}
					}

				}

				function activateDeleteButton() {
					$('#removefeature').button("option", "disabled", false);
				}
				function deactivateDeleteButton() {
					$('#removefeature').button("option", "disabled", true);
				}
				function activateAddstage() {
					$('#addstage').button("option", "disabled", false);
				}
				function deactivateAddstage() {
					$('#addstage').button("option", "disabled", true);
				}
				function deactivateEdition() {

					var radioButtons = $("#edition").find("input:radio");
					$("#edition").find("input:radio").prop("checked", false).end()
			           .buttonset("refresh");
					$("#edition").buttonset("disable");

					Draw.setActive(false);
					Modify.setActive(false);
				}

				function activateEdition() {
					$("#edition").buttonset("enable");
				}
				function activateSaveButton() {
					$('#savestage').button("option", "disabled", false);
				}
				function deactivateSaveButton() {
					$('#savestage').button("option", "disabled", true);
				}
				function flyTo(map, point, extent) {
					var duration = 700;
					var view = map.getView();
					if (extent) {
						view.fit(extent, {
							duration : duration
						});
					} else {
						view.animate({
							zoom : 19,
							center : point,
							duration : duration
						});
					}
				}
				function check_stage_list($stagelist) {
					if ($stagelist.length > 0) {
						$("#stagelistpanel").removeClass("invisible");
						map.updateSize();
					} else {
						$("#stagelistpanel").addClass("invisible");
						map.updateSize();
					}
					if ($stagelist.length < 2) {
						$("#addstage").addClass("highlightbutton");
						$("#errvalidroad").removeClass("invisible");
						$("#erremptystage").addClass("invisible");
					} else if ($stagelist.find(".invalidstage").length > 0) {
						$("#addstage").removeClass("highlightbutton");
						$("#errvalidroad").addClass("invisible");
						$("#erremptystage").removeClass("invisible");
					} else {
						$("#addstage").removeClass("highlightbutton");
						$("#errvalidroad").addClass("invisible");
						$("#erremptystage").addClass("invisible");
					}
				}
				function selectRoad(roadid, vectorOfPolygons, map) {
					// Limpio todas las features seleccionadas,oculto todos
					// los li y solo muestro los que tengan el roadid .
					$("#stagelist li").removeClass("ui-selected").hide();
					var $stagelist = $("#stagelist li[roadid='" + roadid
							+ "']");
					$stagelist.show();
					check_stage_list($stagelist);
					// Si no esta marcado el li road lo marco.
					$("#roadlist li[roadid='" + roadid + "']").addClass(
							"ui-selected");
					// Dejo visible solo el vector con el roadid.
					map.getLayers().forEach(function(layer) {
						if (layer instanceof ol.layer.Vector) {
							layer.setVisible(false);
						}
					});
					vectorOfPolygons.setVisible(true);
					if (vectorOfPolygons.getSource().getFeatures().length > 0) {
						flyTo(map, null, vectorOfPolygons.getSource()
								.getExtent());
					}
				}

				function selectstageFeatures(vectorOfPolygons,
						vectorSelected, selected, selectedFeatures,
						dirtySource, originalStages) {
					vectorSelected.getSource().clear();
					// Deselecciono cualquier feature anterior.
					selectedFeatures.clear();
					// Reinicio el objeto.
					selectedstageFeatures = {};
					var feature = dirtySource.getFeatureById(selected);
					if (!feature) {
						feature = originalStages.getFeatureById(selected);
						if (!feature) {
							// Incremento la version para que se recargue el
							// mapa y se deseleccione la marcada
							// anteriormente.
							vectorOfPolygons.changed();
							return;
						}
					}
					if (feature.get('idFeaturesPolygons') === 'empty') {
						// Incremento la version para que se recargue el
						// mapa y se deseleccione la marcada anteriormente.
						vectorOfPolygons.changed();
						return;
					}
					// Agrego los poligonos a mi objecto que almacena los
					// poligonos seleccionados .
					// y tambien agrego al vector al que se le aplica la
					// animacion.
					var idFeaturesPolygons = feature.get(
							'idFeaturesPolygons').split(",");
					for (var i = 0, j = idFeaturesPolygons.length; i < j; i++) {
						vectorSelected.getSource().addFeature(
								vectorOfPolygons.getSource()
										.getFeatureById(
												idFeaturesPolygons[i])
										.clone());
						selectedstageFeatures[idFeaturesPolygons[i]] = true;
					}
					// Coloco el mapa en la posicion de las etapas
					// seleccionadas si la etapa contiene alguna feature y .
					// postergando el tiempo para que seleccione la nueva
					// feature.
					if (vectorSelected.getSource().getFeatures().length) {
						flyTo(map, null, vectorSelected.getSource()
								.getExtent());
					}
				}

				function relocatenostage(stageid, stageposition, roadid,
						dirtySource, originalStages, vector) {
					var feature = dirtySource.getFeatureById(stageid);
					var idFeaturesPolygons;
					if (!feature) {
						feature = originalStages.getFeatureById(stageid)
								.clone();
						feature.setId(stageid);
						dirtySource.addFeature(feature);
					}
					feature.setProperties({
						'stageposition' : stageposition
					});
					if (feature.get('idFeaturesPolygons') !== 'empty') {
						idFeaturesPolygons = feature.get(
								'idFeaturesPolygons').split(",");
						for (var i = 0, j = idFeaturesPolygons.length; i < j; i++) {
							vector.getSource().getFeatureById(
									idFeaturesPolygons[i]).setProperties({
								'stageposition' : stageposition
							});
						}
					}
				}

				function editFormstageEntry(stageid, idModule) {
					var url = 'editstage.php?cmid=' + idModule + '&id='
							+ stageid;
					window.location.href = url;
				}

				function newFormstageEntry(roadid, idModule) {
					var url = "editstage.php?cmid=" + idModule + "&roadid="
							+ roadid;
					window.location.href = url;
				}
				function editFormRoadEntry(roadid, idModule) {
					var url = 'editroad.php?cmid=' + idModule + '&id='
							+ roadid;
					window.location.href = url;
				}

				function newFormRoadEntry(idModule) {
					var url = "editroad.php?cmid=" + idModule;
					window.location.href = url;
				}

				function deleteRoad(roadid, dirtySource, originalStages,
						treasurehuntid, lockid) {
					$('.treasurehunt-editor-loader').show();
					var json = ajax.call([ {
						methodname : 'mod_treasurehunt_delete_road',
						args : {
							roadid : roadid,
							treasurehuntid : treasurehuntid,
							lockid : lockid
						}
					} ]);
					json[0]
							.done(
									function(response) {
										$('.treasurehunt-editor-loader')
												.hide();
										if (response.status.code) {
											notification.alert('Error',
													response.status.msg,
													'Continue');
										} else {
											// Elimino tanto el li del road
											// como todos los li de stages
											// asociados.
											deleteRoad2ListPanel(roadid);
											// Elimino la feature de
											// dirtySource si la tuviese, .
											// del originalStages y elimino
											// el camino del treasurehunt y
											// la capa del mapa.
											map.removeLayer(treasurehunt.roads[roadid].vector);
											delete treasurehunt.roads[roadid];
											selectfirstroad(treasurehunt.roads, map);
											deactivateEdition();
											var features = originalStages
													.getFeatures();
											for (var i = 0; i < features.length; i++) {
												if (roadid === features[i]
														.get('roadid')) {
													var dirtyFeature = dirtySource
															.getFeatureById(features[i]
																	.getId());
													if (dirtyFeature) {
														dirtySource
																.removeFeature(dirtyFeature);
													}
													originalStages
															.removeFeature(features[i]);
												}
											}
										}
									}).fail(function(error) {
								$('.treasurehunt-editor-loader').hide();
								console.log(error);
								notification.exception(error);
							});
				}

				function deletestage(stageid, dirtySource, originalStages,
						vectorOfPolygons, treasurehuntid, lockid) {
					$('.treasurehunt-editor-loader').show();
					var json = ajax.call([ {
						methodname : 'mod_treasurehunt_delete_stage',
						args : {
							stageid : stageid,
							treasurehuntid : treasurehuntid,
							lockid : lockid
						}
					} ]);
					json[0]
							.done(
									function(response) {
										$('.treasurehunt-editor-loader')
												.hide();
										if (response.status.code) {
											notification.alert('Error',
													response.status.msg,
													'Continue');
										} else {
											var idFeaturesPolygons = false;
											var polygonFeature;
											var feature = dirtySource
													.getFeatureById(stageid);
											// Elimino y recoloco.
											deletestage2ListPanel(stageid,
													dirtySource,
													originalStages,
													vectorOfPolygons);
											// Elimino la feature de
											// dirtySource si la tuviese y
											// todos los poligonos del
											// vector de poligonos.
											if (!feature) {
												feature = originalStages
														.getFeatureById(stageid);
												if (feature
														.get('idFeaturesPolygons') !== 'empty') {
													idFeaturesPolygons = feature
															.get(
																	'idFeaturesPolygons')
															.split(",");
												}
												originalStages
														.removeFeature(feature);
											} else {
												if (feature
														.get('idFeaturesPolygons') !== 'empty') {
													idFeaturesPolygons = feature
															.get(
																	'idFeaturesPolygons')
															.split(",");
												}
												dirtySource
														.removeFeature(feature);
											}
											if (idFeaturesPolygons) {
												for (var i = 0, j = idFeaturesPolygons.length; i < j; i++) {
													polygonFeature = vectorOfPolygons
															.getSource()
															.getFeatureById(
																	idFeaturesPolygons[i]);
													vectorOfPolygons
															.getSource()
															.removeFeature(
																	polygonFeature);
												}
											}

										}
									}).fail(function(error) {
								$('.treasurehunt-editor-loader').hide();
								console.log(error);
								notification.exception(error);
							});
				}

				function savestages(dirtySource, originalStages,
						treasurehuntid, callback, options, lockid) {
					$('.treasurehunt-editor-loader').show();
					var geojsonformat = new ol.format.GeoJSON();
					var dirtyfeatures = dirtySource.getFeatures();
					var features = [];
					var auxfeature;
					// Remove unnecessary feature properties .
					dirtyfeatures.forEach(function(dirtyfeature) {
						auxfeature = dirtyfeature.clone();
						auxfeature.unset("idFeaturesPolygons");
						auxfeature.unset("name");
						auxfeature.unset("clue");
						auxfeature.unset("treasurehuntid");
						auxfeature.setId(dirtyfeature.getId());
						features.push(auxfeature);
					});
					var geojsonstages = geojsonformat.writeFeaturesObject(
							features, {
								dataProjection : 'EPSG:4326',
								featureProjection : mapprojection
							});
					var json = ajax.call([ {
						methodname : 'mod_treasurehunt_update_stages',
						args : {
							stages : geojsonstages,
							treasurehuntid : treasurehuntid,
							lockid : lockid
						}
					} ]);
					json[0].done(
							function(response) {
								$('.treasurehunt-editor-loader').hide();
								if (response.status.code) {
									notification
											.alert('Error',
													response.status.msg,
													'Continue');
								} else {
									var originalFeature;
									// Paso las features "sucias" al objeto
									// con las features originales.
									dirtySource.forEachFeature(function(
											feature) {
										originalFeature = originalStages
												.getFeatureById(feature
														.getId());
										originalFeature
												.setProperties(feature
														.getProperties());
										originalFeature.setGeometry(feature
												.getGeometry());
									});
									// Limpio mi objeto que guarda las
									// features sucias.
									dirtySource.clear();
									// Desactivo el boton de guardar.
									deactivateSaveButton();
									dirty = false;
									if (typeof callback === "function"
											&& options instanceof Array) {
										callback.apply(null, options);
									}

								}
							}).fail(
							function(error) {
								$('.treasurehunt-editor-loader').hide();
								console.log(error);
								notification.alert('Error', error.message,
										'Continue');
							});
				}

				$(".searchaddress")
						.autocomplete(
								{
									minLength : 4,
									source : function(request, response) {
										var term = request.term;
										openStreetMapGeocoder
												.geocode(
														term,
														function(data) {
															if (!data[0]) {
																response();
																return;
															}
															var total = [];
															for (var i = 0, l = data.length; i < l; i++) {
																var latitude;
																var longitude;
																latitude = data[i]
																		.getLatitude();
																longitude = data[i]
																		.getLongitude();
																var result = {
																	"value" : data[i].totalName,
																	"latitude" : latitude,
																	"longitude" : longitude,
																	"boundingbox" : data[i].boundingbox
																};
																total[i] = result;
															}
															response(total);
														});
									},
									select : function(event, ui) {
										if (ui.item.boundingbox) {
											var extend = [];
											extend[0] = parseFloat(ui.item.boundingbox[2]);
											extend[1] = parseFloat(ui.item.boundingbox[0]);
											extend[2] = parseFloat(ui.item.boundingbox[3]);
											extend[3] = parseFloat(ui.item.boundingbox[1]);
											extend = ol.proj
													.transformExtent(
															extend,
															'EPSG:4326',
															mapprojection);
											flyTo(map, null, extend);
										} else {
											var point = ol.proj
													.fromLonLat([
															ui.item.longitude,
															ui.item.latitude ]);
											flyTo(map, point);
										}
									},
									autoFocus : true
								}).on("click", function() {
							$(this).autocomplete("search", $(this).value);
						});
				// Necesario para regular la anchura de los resultados de
				// autocompletado.
				$.ui.autocomplete.prototype._resizeMenu = function() {
					var ul = this.menu.element;
					ul.outerWidth(this.element.outerWidth());
				};
				$("#addstage").on(
						'click',
						function() {
							if (dirty) {
								savestages(dirtyStages, originalStages,
										treasurehuntid, newFormstageEntry,
										[ roadid, idModule ], lockid);
							} else {
								newFormstageEntry(roadid, idModule);
							}

						});
				$("#addroad").on(
						'click',
						function() {
							if (dirty) {
								savestages(dirtyStages, originalStages,
										treasurehuntid, newFormRoadEntry,
										[ idModule ], lockid);
							} else {
								newFormRoadEntry(idModule);
							}
						});
				$("#removefeature")
						.on(
								'click',
								function() {
									notification
											.confirm(
													strings['areyousure'],
													strings['removewarning'],
													strings['confirm'],
													strings['cancel'],
													function() {
														removefeatureToDirtySource(
																selectedFeatures,
																originalStages,
																dirtyStages,
																treasurehunt.roads[roadid].vector);
														removefeatures(
																selectedFeatures,
																treasurehunt.roads[roadid].vector);
														// Desactivo el
														// boton de borrar y
														// activo el de
														// guardar cambios.
														deactivateDeleteButton();
														activateSaveButton();
														dirty = true;
													});
								});
				$("#savestage").on(
						'click',
						function() {
							savestages(dirtyStages, originalStages,
									treasurehuntid, null, null, lockid);
						});
				$("#stagelist").on('click',
						'.ui-icon-info, .ui-icon-alert', function() {
							var id = $(this).data('id');
							// Open dialogue.
							$(id).dialog("open");
							// Remove focus from the buttons.
							$('.ui-dialog :button').blur();
						});
				$("#stagelist")
						.on(
								'click',
								'.ui-icon-trash',
								function() {
									var $this_li = $(this).parents('li');
									notification
											.confirm(
													strings['areyousure'],
													strings['removewarning'],
													strings['confirm'],
													strings['cancel'],
													function() {
														var stageid = parseInt($this_li
																.attr('stageid'));
														deletestage(
																stageid,
																dirtyStages,
																originalStages,
																treasurehunt.roads[roadid].vector,
																treasurehuntid,
																lockid);
													});
								});
				$("#stagelist").on(
						'click',
						'.ui-icon-pencil',
						function() {
							// Busco el stageid del li que contiene la
							// papelera seleccionada.

							var stageid = parseInt($(this).parents('li')
									.attr('stageid'));
							// Si esta sucio guardo el escenario.
							if (dirty) {
								savestages(dirtyStages, originalStages,
										treasurehuntid, editFormstageEntry,
										[ stageid, idModule ], lockid);
							} else {
								editFormstageEntry(stageid, idModule);
							}

						});
				var editstatus = 'off';
				$("input[name=controlpanel]:radio")
						.on(
								'click',
								function() {
									
									var prevval = editstatus;
									var value = $(this).prop('id');
									
									if (value==prevval){
										// Toggle.
										$("#edition").find("input:radio").buttonset().prop('checked', false).end().buttonset('refresh');
										value = 'off';
									}
									editstatus = value;
														
									if (value === 'addradio') {
										Draw.setActive(true);
										Modify.setActive(false);
									} else if (value === 'modifyradio') {
										Draw.setActive(false);
										Modify.setActive(true);
									} else {
										Draw.setActive(false);
										Modify.setActive(false);
									}
								});
				$("#stagelist")
						.on(
								'click',
								'li',
								function(e) {
									if ($(e.target)
											.is(
													'.handle ,.nohandle, .ui-icon , .sortable-number')) {
										e.preventDefault();
										return;
									}
									$(this).addClass("ui-selected")
											.siblings().removeClass(
													"ui-selected");
									// Selecciono el stageid de mi atributo
									// custom.
									stageposition = parseInt($(this).attr(
											'stageposition'));
									stageid = parseInt($(this).attr(
											'stageid'));
									// Borro la anterior seleccion de
									// features y busco las del mismo tipo.
									selectstageFeatures(
											treasurehunt.roads[roadid].vector,
											vectorSelected, stageid,
											selectedFeatures, dirtyStages,
											originalStages);
									activateEdition();
									// Si la etapa no tiene geometrÃ­a
									// resalto el boton de anadir.
									if ($(this).find(".invalidstage").length > 0) {
										$("label[for='addradio']").addClass(
												'highlightbutton');
									} else {
										$("label[for='addradio']")
												.removeClass(
														'highlightbutton');
									}
									// Paro de dibujar si cambio de etapa.
									if (drawStarted) {
										abortDrawing = true;
										Draw.Polygon.finishDrawing();
									}
								});
				$("#roadlist")
						.on(
								'click',
								'li',
								function(e) {
									if ($(e.target).is('.ui-icon')) {
										e.preventDefault();
										return;
									}
									$(this).addClass("ui-selected")
											.siblings().removeClass(
													"ui-selected");
									// Selecciono el stageid de mi atributo
									// custom.
									// Borro las etapas seleccionadas.
									selectedstageFeatures = {};
									// Paro de dibujar si cambio de camino.
									if (drawStarted) {
										abortDrawing = true;
										Draw.Polygon.finishDrawing();
									}
									roadid = $(this).attr('roadid');
									var blocked = $(this).attr('blocked');
									if (parseInt(blocked)
											|| blocked === 'true') {
										deactivateAddstage();
									} else {
										activateAddstage();
									}
									selectRoad(
											roadid,
											treasurehunt.roads[roadid].vector,
											map);
									deactivateEdition();
									// Scroll to editor.
									var scrolltop;
									if ($('header[role=banner]').css(
											"position") === "fixed") {
										scrolltop = parseInt($(
												".treasurehunt-editor")
												.offset().top)
												- parseInt($(
														'header[role=banner]')
														.outerHeight(true));
									} else {
										scrolltop = parseInt($(
												".treasurehunt-editor")
												.offset().top);
									}
									$('html, body').animate({
										scrollTop : scrolltop
									}, 500);
								});
				$("#roadlist").on(
						'click',
						'.ui-icon-pencil',
						function() {
							// Busco el roadid del li que contiene el
							// lapicero seleccionado.
							var roadid = parseInt($(this).parents('li')
									.attr('roadid'));
							// Si esta sucio guardo el escenario.
							if (dirty) {
								savestages(dirtyStages, originalStages,
										treasurehuntid, editFormRoadEntry,
										[ roadid, idModule ], lockid);
							} else {
								editFormRoadEntry(roadid, idModule);
							}

						});
				$("#roadlist").on(
						'click',
						'.ui-icon-trash',
						function() {
							var $this_li = $(this).parents('li');
							notification.confirm(strings['areyousure'],
									strings['removeroadwarning'],
									strings['confirm'], strings['cancel'],
									function() {
										var roadid = parseInt($this_li
												.attr('roadid'));
										deleteRoad(roadid, dirtyStages,
												originalStages,
												treasurehuntid, lockid);
									});
						});
				map.on('pointermove', function(evt) {
					if (evt.dragging || Draw.getActive()
							|| !Modify.getActive()) {
						return;
					}
					var pixel = map.getEventPixel(evt.originalEvent);
					var hit = map.forEachFeatureAtPixel(pixel, function(
							feature, layer) {
						if (selectedstageFeatures[feature.getId()]) {
							var selected = false;
							selectedFeatures.forEach(function(
									featureSelected) {
								if (feature === featureSelected) {
									selected = true;
								}
							});
							return selected ? false : true;
						}
						return false;
					});
					map.getTargetElement().style.cursor = hit ? 'pointer'
							: '';
				});
				// Evento para que funcione bien el boton de cerrar en
				// dispositivos tactiles.
				$(document).on(
						'touchend',
						'.ui-dialog-titlebar-close',
						function() {
							$(this).parent().siblings('.ui-dialog-content')
									.dialog("close");
						});
				// CLEARABLE INPUT.
				function tog(v) {
					return v ? 'removeClass' : 'addClass';
				}
				$('.searchaddress').on('input', function() {
					$('.closeicon')[tog(this.value)]('invisible');
				});
				$('.closeicon').on(
						'touchstart click',
						function(ev) {
							ev.preventDefault();
							$(this).addClass('invisible');
							$('.searchaddress').val('').change()
									.autocomplete("close");
						});
				// Al salirse.
				window.onbeforeunload = function(e) {
					var message = strings['savewarning'], e = e
							|| window.event;
					if (dirty) {
						// For IE and Firefox.
						if (e) {
							e.returnValue = message;
						}

						// For Safari.
						return message;
					}
				};
			}
			var init = {
				edittreasurehunt : function(idModule, treasurehuntid, selectedroadid, lockid, custommapconfig) {
					// I18n strings.
	            	var terms = ['stage', 'road', 'aerialmap', 'roadmap', 'basemaps', 'add', 'modify', 'save',
			                    'remove', 'searchlocation', 'savewarning', 'removewarning',
			                    'areyousure', 'removeroadwarning', 'confirm', 'cancel'];
	            	var stringsqueried = terms.map(function (term) {
	                     return {key: term, component: 'treasurehunt'};
	                });
	            	str.get_strings(stringsqueried).done(function (strings) {
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
	    	            			initedittreasurehunt(idModule, treasurehuntid, i18n, selectedroadid, lockid, custommapconfig);	    					        
	    					    });
	    					    img.src = custommapconfig.custombackgroundurl;
	            		} else {
	            			initedittreasurehunt(idModule, treasurehuntid, i18n, selectedroadid, lockid, custommapconfig);
	            		}
	                });
				} // End of function edittreasurehunt.
			}; // End of init var.
			return init;
		});
