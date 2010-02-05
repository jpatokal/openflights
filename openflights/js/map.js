/**
 * @fileoverview Base class for the OpenFlightsMap widget.  See full documentation
 * and sample code at {@link http://openflights.org/widget/ OpenFlights Widget}.
 *
 * @author Jani Patokallio jani@contentshare.sg
 * @version 0.1
 */

/**
 * Global reference to instantiated OpenFlights map object<br>
 * (there should be a nicer way of passing this, but AJAX requests seem to lose the object context?)
 */
var __ofmap;

/*
 * A little OpenLayers configuration
 */
OpenLayers.IMAGE_RELOAD_ATTEMPTS = 3;
OpenLayers.Util.onImageLoadErrorColor = "transparent";

/**
 * OpenFlightsMap constructor
 * @class Class for the OpenFlightsMap widget.
 * @constructor
 * @param map DOM element to render map in
 * @param layers Array of {@link http://dev.openlayers.org/docs/files/OpenLayers/Layer-js.html OpenLayers.Layer} base layer(s).  Flights and airports will be overlaid on top of this.
 * @return A new OpenFlightsMap object.
 */
function OpenFlightsMap(map, layers) {

  function clusterRadius(feature) {
    var radius = feature.attributes.count * 5;
    if(radius > 29) radius = 29;
    return radius;
  }

  // constructor starts here
  var ol_map = new OpenLayers.Map(map, {
    maxResolution: 0.3515625, // scales nicely on 1024x786 and nukes dateline gap
    restrictedExtent: new OpenLayers.Bounds(-9999, -90, 9999, 90), // not sure what this does
    maxExtent: new OpenLayers.Bounds(-180,-90.0,180.0,90.0),
    maxZoomLevel: 8,
    controls: [
	       new OpenLayers.Control.PanZoom(),
	       new OpenLayers.Control.Navigation({'title': gt.gettext("Toggle pan and region select mode")}),
	       new OpenLayers.Control.LayerSwitcher({'ascending':false, 'title': gt.gettext('Switch map layers')}),
	       new OpenLayers.Control.ScaleLine(),
	       new OpenLayers.Control.OverviewMap({'title': gt.gettext("Toggle overview map")})
	       ] });
  
  var flightLayer = new OpenLayers.Layer.Vector(gt.gettext("Flights"),
					    {styleMap: new OpenLayers.StyleMap({
						strokeColor: "${color}",
						strokeOpacity: 1,
						strokeWidth: "${count}",
						strokeDashstyle: "${stroke}"
					      })
					    });

  var style = new OpenLayers.Style({graphicTitle: "${name}",
				    externalGraphic: "${icon}",
				    graphicWidth: "${size}",
				    graphicHeight: "${size}",
				    graphicXOffset: "${offset}",
				    graphicYOffset: "${offset}",
				    graphicOpacity: "${opacity}",
				    pointerEvents: "visiblePainted",
				    label : "\xA0${code}",
				    fontColor: "#000000",
				    fontSize: "10px",
				    fontFamily: "Courier New, monospace",
				    labelAlign: "lt",
				    fillColor: "black"
				   }, { context: {
				     name: function(feature) {
					 if(feature.cluster) {
					   // Last airport is always the largest
					   last = feature.cluster.length - 1;
					   if(feature.cluster[last].attributes.index > 2) {
					     // One airport is dominant, copy its attributes into cluster
					     feature.attributes.apid = feature.cluster[last].attributes.apid;
					     feature.attributes.coreid = feature.cluster[last].attributes.coreid;
					     feature.attributes.code = feature.cluster[last].attributes.code + "+";
					     feature.attributes.desc = feature.cluster[last].attributes.desc;
					     feature.attributes.rdesc = feature.cluster[last].attributes.rdesc;
					     feature.attributes.icon = feature.cluster[last].attributes.icon;
					     feature.attributes.size = feature.cluster[last].attributes.size;
					     feature.attributes.offset = feature.cluster[last].attributes.offset;
					     feature.attributes.name = feature.cluster[last].attributes.name + " \u2295";
					   } else {
					     // No dominant airport, show cluster icon with aggregate info
					     name = "";
					     for(c = last; c >= 0; c--) {
					       if(c < last) name += ", ";
					       name += feature.cluster[c].attributes.code;
					     }
					     feature.attributes.icon = "/img/icon_cluster.png";
					     feature.attributes.code = "";
					     feature.attributes.size = clusterRadius(feature);
					     feature.attributes.offset = -clusterRadius(feature) / 2;
					     feature.attributes.name = name;
					   }
					 }
					 return feature.attributes.name;
				       },
				     icon: function(feature) { return feature.attributes.icon; },
				     size: function(feature) { return feature.attributes.size; },
 				     offset: function(feature) { return feature.attributes.offset; },
				     opacity: function(feature) {
					 return feature.cluster ? 1 : feature.attributes.opacity;
				       },
				     code: function(feature) { return feature.attributes.code; }
				     }});

  var renderer = OpenLayers.Util.getParameters(window.location.href).renderer;
  renderer = (renderer) ? [renderer] : OpenLayers.Layer.Vector.prototype.renderers;
  var strategy = new OpenLayers.Strategy.Cluster({distance: 15, threshold: 3});

  var airportLayer = new OpenLayers.Layer.Vector("Airports",
					     {styleMap: new OpenLayers.StyleMap
						 ({'default': style,
						   'select':{
						   fillOpacity: 1.0,
						   pointerEvents: "visiblePainted",
						   label : ""
						       }}),
						 renderers: renderer,
						 strategies: [strategy]});
  layers.push(flightLayer, airportLayer);
  ol_map.addLayers(layers);

  selectControl = new OpenLayers.Control.SelectFeature(airportLayer, {onSelect: onAirportSelect,
							              onUnselect: onAirportUnselect});
  ol_map.addControl(selectControl);
  selectControl.activate();

  OpenLayers.Util.alphaHack = function() { return false; };

  __ofmap = this;
  this.ol_map = ol_map;
  this.flightLayer = flightLayer;
  this.airportLayer = airportLayer;
}

/**
 * Map type: flight map
 * @const
 */
OpenFlightsMap.FLIGHTS = "F";

/**
 * Map type: airline route map
 * @const
 */
OpenFlightsMap.AIRLINE = "L";

/**
 * Map type: airport route map
 * @const
 */
OpenFlightsMap.AIRPORT = "R";

/**
 * URL of flight map server
 */
OpenFlightsMap.URL_MAP = "/php/map.php";

/**
 * URL of route map server
 */
OpenFlightsMap.URL_ROUTES = "/php/routes.php";


/**
 * Load a type of map content
 *
 * @param {String} type Type of map: OpenFlightsMap.FLIGHTS to load a user flight map, OpenFlightsMap.AIRLINE to load an airline route map, or OpenFlightsMap.AIRPORT to load an airport route map
 * @param {int} id Airport/airline map ID to load [AIRLINE, AIRPORT only]
 */
OpenFlightsMap.prototype.load = function(type, id) {
  var url;

  this.debug("map.load(" + type + "," + id + ")");
  switch(type) {
  case OpenFlightsMap.FLIGHTS:
    url = OpenFlightsMap.URL_MAP;
    break;
  case OpenFlightsMap.AIRLINE:
    id = "L" + id;
    //fallthru
  case OpenFlightsMap.AIRPORT:
    url = OpenFlightsMap.URL_ROUTES;
    break;
  default:
    this.error("load() failed: Unknown map type " + type);
    break;
  }
  $("ajaxstatus").style.display = 'inline';
  new Ajax.Request(url,
		   { method: 'get',
		     parameters: "apid=" + id,
		     onSuccess: function(transport) {
		       __ofmap.draw(transport, type); },
		     onFailure: function(transport) {
		       __ofmap.error("load() from " + url + " failed: " + transport.responseText); }
		   } );
}

/**
 * Draw map
 *
 * @param {string} transport Response from server
 * @param {const} type Type of map to draw (OpenFlightMap.FLIGHTS, AIRLINE, AIRPORT)
 */
OpenFlightsMap.prototype.draw = function(transport, type){

  /**
   *    Geo Constants
   */
  EARTH_RADIUS = 3958.75;    // in miles
  EARTH_CIRCUMFERENCE = 24900; // in miles
  MOON_DISTANCE = 238857;    // in miles
  MARS_DISTANCE = 34649589;    // in miles
  DEG2RAD =  0.01745329252;  // factor to convert degrees to radians (PI/180)
  RAD2DEG = 57.29577951308;
  GC_STEP = 500; // draw segment every GC_STEP mi
  GC_MIN = 1000; // trigger GC paths once distance is greater than this
  
  var COLOR_NORMAL = "#ee9900"; //orange
  var COLOR_ROUTE = "#99ee00"; //yellow
  var COLOR_TRAIN = "#ee5555"; //dull red
  var COLOR_ROAD = "#9f6500"; //brown
  var COLOR_SHIP = "#00ccff"; //cyany blue
  var COLOR_HIGHLIGHT = "#007fff"; //deeper blue

  var airportMaxFlights = 0;
  var airportIcons = [ [ '/img/icon_plane-13x13.png', 13 ],
		       [ '/img/icon_plane-15x15.png', 15 ],
		       [ '/img/icon_plane-17x17.png', 17 ],
		       [ '/img/icon_plane-19x19b.png', 19 ],
		       [ '/img/icon_plane-19x19b.png', 19 ],
		       [ '/img/icon_plane-19x19.png', 19 ] ];
  var modecolors = { "F":COLOR_NORMAL, "T":COLOR_TRAIN, "R":COLOR_ROAD, "S":COLOR_SHIP };

  // Draw a flight connecting (x1,y1)-(x2,y2)
  // Note: Values passed in *must already be parsed as floats* or very strange things happen
  function drawLine(x1, y1, x2, y2, count, distance, color, stroke) {
    if(! color) {
      color = COLOR_NORMAL;
    }
    if(! stroke) {
      stroke = "solid";
    }
    
    // 1,2 flights as single pixel
    count = Math.floor(Math.sqrt(count) + 0.5);
    
    var paths = [ gcPath(new OpenLayers.Geometry.Point(x1, y1), new OpenLayers.Geometry.Point(x2, y2)) ];
    // Path is in or extends into east (+) half, so we have to make a -360 copy
    if(x1 > 0 || x2 > 0) {
      paths.push(gcPath(new OpenLayers.Geometry.Point(x1-360, y1), new OpenLayers.Geometry.Point(x2-360, y2)));
    }
    // Path is in or extends into west (-) half, so we have to make a +360 copy
    if(x1 < 0 || x2 < 0) {
      paths.push(gcPath(new OpenLayers.Geometry.Point(x1+360, y1), new OpenLayers.Geometry.Point(x2+360, y2)));
    }
    
    var features = [];
    for(i = 0; i < paths.length; i++) {
      features.push(new OpenLayers.Feature.Vector(new OpenLayers.Geometry.LineString(paths[i]),
						  {count: count, color: color, stroke: stroke}));
    }
    return features;
  }

  //
  // Draw airport
  //
  // coreid -- apid of "core" airport at the center of a map of routes
  //
  function drawAirport(apdata, name, city, country, count, formattedName, opacity, coreid) {
    var apcols = apdata.split(":");
    var code = apcols[0];
    var apid = apcols[1];
    var x = apcols[2];
    var y = apcols[3];

    // Description
    var desc = name + " (<B>" + code + "</B>)<br><small>" + city + ", " + country + "</small><br>Flights: " + count;
    var rdesc = name + " (<B>" + code + "</B>)<br><small>" + city + ", " + country + "</small>";
    
    // Select icon based on number of flights (0...airportIcons.length-1)
    var colorIndex = Math.floor((count / airportMaxFlights) * airportIcons.length) + 1;

    // Two or less flights: smallest dot
    if(count <= 2 || colorIndex < 0) {
      colorIndex = 0;
    }
    // More than two flights: at least 2nd smallest
    if(count > 2) {
      colorIndex = Math.max(1, colorIndex);
    }
    // Max out at top color
    // Core airport of route map always uses max color
    if(colorIndex >= airportIcons.length || apid == coreid) {
      colorIndex = airportIcons.length - 1;
    }
    // This should never happen
    if(! airportIcons[colorIndex]) {
      of_error(name + ":" + colorIndex + " of " + airportMaxFlights);
      return;
    }
    
    var point = new OpenLayers.Geometry.Point(x, y);
    var feature = new OpenLayers.Feature.Vector(point);
    feature.attributes = {
      apid: apid,
      coreid: coreid,
      code: code,
      name: formattedName,
      apdata: apdata,
      desc: desc,
      rdesc: rdesc,
      opacity: opacity,
      icon: airportIcons[colorIndex][0], 
      size: airportIcons[colorIndex][1],
      index: count,
      offset: Math.floor(-airportIcons[colorIndex][1]/2)
    };

    return feature;
  }

  // http://trac.openlayers.org/wiki/GreatCircleAlgorithms
  
  // Compute great circle distance between two points (spherical law of cosines)
  // http://www.movable-type.co.uk/scripts/latlong.html
  // © 2002-2008 Chris Veness
  function gcDistance(lat1, lon1, lat2, lon2) {
    var rad = Math.PI / 180;
    lat1 = lat1 * rad;
    lon1 = lon1 * rad;
    lat2 = lat2 * rad;
    lon2 = lon2 * rad;
    var d = Math.acos(Math.sin(lat1)*Math.sin(lat2) + 
		      Math.cos(lat1)*Math.cos(lat2) *
		      Math.cos(lon2-lon1));
    if (d < 0) d += Math.PI;
    return Math.floor(d * EARTH_RADIUS);
    
  }
  
  // Compute great circle bearing from point "from" towards point "to"
  function gcBearingTo(from, to) {
    var x = new Array(2);
    var y = new Array(2);
    var bearing;
    var adjust;
    
    if( isValid(from) && isValid(to)) {
      x[0] = from.x * DEG2RAD;    y[0] = from.y * DEG2RAD;
      x[1] = to.x * DEG2RAD;    y[1] = to.y * DEG2RAD;
      
      var a = Math.cos(y[1]) * Math.sin(x[1] - x[0]);
      var b = Math.cos(y[0]) * Math.sin(y[1]) - Math.sin(y[0]) 
	* Math.cos(y[1]) * Math.cos(x[1] - x[0]);
      
      if((a == 0) && (b == 0)) {
	bearing = 0;
	return bearing;
      }
      
      if( b == 0) {
	if( a < 0)  
	  bearing = 270;
	else
	  bearing = 90;
	return bearing;
      }
      
      if( b < 0) 
	adjust = Math.PI;
      else {
	if( a < 0) 
	  adjust = 2 * Math.PI;
	else
	  adjust = 0;
      }
      bearing = (Math.atan(a/b) + adjust) * RAD2DEG;
      return bearing;
    } else
      return null;
  }
  
  
  /**
   * Compute great circle waypoint "distance" miles away from "from" in direction "bearing"
   */
  function gcWaypoint(from, distance, bearing) {
    var wp = new OpenLayers.Geometry.Point( 0, 0 );
    
    // Math.* trig functions require angles to be in radians
    var x = from.x * DEG2RAD;
    var y = from.y * DEG2RAD;
    var radBearing = bearing * DEG2RAD;
    
    // Convert arc distance to radians
    var d = distance / EARTH_RADIUS;
    
    // Modified based on http://williams.best.vwh.net/avform.htm
    var lat = Math.asin( Math.sin(y) * Math.cos(d) + Math.cos(y) * Math.sin(d) * Math.cos(radBearing));  
    var lon = Math.atan2( Math.sin(radBearing) * Math.sin(d) * Math.cos(y), Math.cos(d) - Math.sin(y) * Math.sin(lat));
    wp.x = (x + lon) * RAD2DEG;
    wp.y = lat * RAD2DEG;
    return wp;
  }
  
  /*
   * Return array of GC waypoints between two points
   * Flips across dateline if needed, and removes any invisible points
   */  
  function gcPath(startPoint, endPoint) {
    // Do we cross the dateline?  If yes, then flip endPoint across it
    if(Math.abs(startPoint.x-endPoint.x) > 180) {
      if(startPoint.x < endPoint.x) {
	endPoint.x -= 360;
      } else {
	endPoint.x += 360;
      }
    }
    
    // Compute distance between points
    var distance = gcDistance(startPoint.y, startPoint.x, endPoint.y, endPoint.x);
    if(distance < GC_MIN) {
      // Short enough that we don't need to show curvature
      return [startPoint, endPoint];
    }
    
    // And... action!
    var pointList = new Array();
    var wayPoint = startPoint;
    var d = GC_STEP;
    var step = GC_STEP;
    if(startPoint.x > -360 && startPoint.x < 360) {
      pointList.push(startPoint);
    }
    while(d < distance) {
      var bearing = gcBearingTo(wayPoint, endPoint); // degrees, clockwise from 0 deg at north
      var wayPoint = gcWaypoint(wayPoint, step, bearing);
      if(wayPoint.x > -360 && wayPoint.x < 360) {
	pointList.push(wayPoint);
      } else {
	if((wayPoint.x < -360 && bearing > 180) ||
	   (wayPoint.x > 360 && bearing < 180)) {
	  break; // line's gone off the map, so stop rendering
	}
      }
      
      // Increase step resolution near the poles
      if(Math.abs(wayPoint.y) > 60) {
	step = GC_STEP / 2;
      } else {
	step = GC_STEP;
      }
      d += step;
    }
    if(endPoint.x > -360 && endPoint.x < 360) {
      pointList.push(endPoint);
    }
    return pointList;
  }
  
  // Check if point is a point
  function isValid(point) {
    return ((point.x != null) && (point.y != null) && (point.x != NaN) && (point.y != NaN))
  }
  
  // Core starts here
  this.debug("map.draw(" + type + ")");
  str = transport.responseText;
  if(str.substring(0,5) == "Error") {
    this.error(str);
    return;
  }
  
  var master = str.split("\n");
  var stats = master[0];
  var flights = master[1];
  var airports = master[2];
  var col = stats.split(";");
  var apid = 0;

  this.clear();
  if(type == OpenFlightsMap.FLIGHTS) {
    // Flight map
    flightTotal = col[0];
    this.setStatistics(col[0], col[1], col[2]);
    desc = "Flight map";
  } else {
    // Route map
    apid = col[0];
    flightTotal = col[1];
    desc = col[2];
  }
  
  $("maptitle").innerHTML = this.getRouteMapTitle(type, apid, flightTotal, desc);
  
  // New user (or filter setting) with no flights?  Then don't even try to draw
  if(flightTotal != "0") {
    var rows = flights.split("\t");
    for (var r = 0; r < rows.length; r++) {
      // apid1 0, x1 1, y1 2, apid2 3, x2 4, y2 5, count 6, distance 7, future 8, mode 9
      var rCol = rows[r].split(";");
      if(rCol[8] == "Y") {
	stroke = "dash";
      } else {
	stroke = "solid";
      }
      if(type == OpenFlightsMap.FLIGHTS) {
	color = modecolors[rCol[9]];
	if(!color) color = COLOR_NORMAL;
      } else {
	color = COLOR_ROUTE;
      }
      this.flightLayer.addFeatures(drawLine(parseFloat(rCol[1]), parseFloat(rCol[2]),
					    parseFloat(rCol[4]), parseFloat(rCol[5]),
					    rCol[6], rCol[7], color, stroke));
    }
  } else {
    $("maptitle").innerHTML = "No flights";
  }
  
  // Route maps draw the core airport even if there are no routes
  if(flightTotal != "0" || type == OpenFlightsMap.AIRPORT) {
    var rows = airports.split("\t");
    var airports = Array();
    
    // Airports are ordered from least busy to busiest, so we calibrate the color scale based on the last result
    airportMaxFlights = rows[rows.length - 1].split(";")[4];
    for (var r = 0; r < rows.length; r++) {
      var col = rows[r].split(";");
      // 0 apdata, 1 name, 2 city, 3 country, 4 count, 5 formatted_name, 6 future
      if(col[6] == "Y") {
	opacity = 0.5;
      } else {
	opacity = 1;
      }
      airports.push(drawAirport(col[0], col[1], col[2], col[3], col[4], col[5], opacity, apid));
    }
    this.airportLayer.addFeatures(airports);
    this.zoom();
  }
  $("ajaxstatus").style.display = 'none';
}

/**
 * Clear all flights, airports and popups
 */
OpenFlightsMap.prototype.clear = function() {
  this.flightLayer.destroyFeatures();
  this.airportLayer.destroyFeatures();
  var popups = this.ol_map.popups;
  for(p = 0; p < popups.length; p++) {
    popups[p].destroy();
  }
}

/**
 * Zoom so visible flight data (-180 to 180) fills the screen<br>
 * Known bug: incorrectly draws whole map if flight lines span the meridian...
 */
OpenFlightsMap.prototype.zoom = function() {
  var bounds = this.flightLayer.getDataExtent();
  if(! bounds) return null;
  if(bounds.left < -180 && bounds.left > -360 && bounds.right > 180 && bounds.right < 360) {
    // map spans the world, do nothing
  } else {
    if(bounds.left < -180) bounds.left += 360;
    if(bounds.right > 180) bounds.right -= 360;
  }
  this.ol_map.zoomToExtent(bounds);
}

/**
 * Callback for generating title describing this route map.  Override to customize.
 *
 * @param type Type of route map (OpenFlightsMap.AIRLINE,AIRPORT)
 * @param id Airline/airport ID of this map
 * @param flightTotal Count of flights in this map
 * @param desc Default description from database
 */
OpenFlightsMap.prototype.getRouteMapTitle = function(type, id, flightTotal, desc) {
  return desc;
}

/**
 * Callback for displaying user's personal flight statistics.  Does nothing by default, override to customize.
 *
 * @param {int} flightTotal Total number of user's flights
 * @param {string} distance Distance of user's flights (preformatted as km/mi)
 * @param {int} duration Total duration of user's flights (minutes)
 */
OpenFlightsMap.prototype.setStatistics = function(flightTotal, distance, duration) {
  // do nothing
}

/**
 * Add a popup to map
 *
 * @param popup Instance of {@link http://dev.openlayers.org/docs/files/OpenLayers/Popup-js.html OpenLayers.Popup}.  Must already be attached to an OpenLayers.Feature, typically the airport provided in the onAirportSelect() callback.
 */
OpenFlightsMap.prototype.addPopup = function(popup) {
    this.ol_map.addPopup(popup);
}

/**
 * Print to debug log, if global variable OF_DEBUG is true
 *
 * @param str Log message
 */
OpenFlightsMap.prototype.debug = function(str) {
  if(OF_DEBUG) {
    $("debug").innerHTML = str + "<br>" + $("debug").innerHTML;
  }
}

/**
 * Set human-readable error message as map title
 *
 * @param str Error message
 */
OpenFlightsMap.prototype.error = function(str) {
  $("ajaxstatus").style.display = 'none';
  $("maptitle").style.display = 'inline';
  $("maptitle").innerHTML = "ERROR: " + str + "<br>Please hit CTRL-F5 to force refresh, and <a href='/about'>report</a> this error if it does not go away.";
}
