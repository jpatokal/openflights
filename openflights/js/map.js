function OpenFlightsMap(layers) {

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
  
  var ol_map, selectControl;

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

  // Trigger loading of map content from URL
  function load(url) {
    of_debug("map.load()");
    new Ajax.Request(url,
		     { onSuccess: update,
		       onFailure: error });
  }

  function error(transport) {
    alert(transport.responseText);
  }

  // Update all flights, airports in map
  function update(transport){
    of_debug("map.update()");
    str = transport.responseText;
    url = URL_MAP; // ##TODO## fix

    flightLayer.destroyFeatures();
    airportLayer.destroyFeatures();
    lasturl = url; // used for refresh
    
    var master = str.split("\n");
    var stats = master[0];
    var flights = master[1];
    var airports = master[2];
    var col = stats.split(";");
    var apid = 0;
    var type = "M"; // map

    if(url == URL_MAP) {
      // User flight map
      var distance = col[1];
      if(! distance) distance = 0;
      var duration = col[2]; // minutes
      var days = Math.floor(col[2] / (60*24));
      var hours = Math.floor((col[2] / 60) % 24);
      var min = Math.floor(col[2] % 60);
      if(min < 10) min = "0" + min;

      flightTotal = col[0];
      privacy = col[3];
      
      if($('stats')) {
	stats = col[0] + " " + gt.gettext("segments") + "<br>" +
	  distance + "<br>" +
	  days + " " + gt.gettext("days") + " " + hours + ":" + min;
	$("stats_ajax").style.display = 'none';
	$("stats").innerHTML = stats;
	$('statsbox').style.visibility = "visible";

	if(! logged_in) {
	  elite = col[4];
	  editor = col[6];
	  document.forms['login'].challenge.value = col[7];
	  
	  // Does user have a PHP session open?  Log him in!
	  // Simulate login.php: "1;name;editor;elite"
	  if(col[5] != "demo") {
	    if(flightTotal == "0") {
	      op = "NEWUSER";
	    } else {
	      op = "REFRESH";
	    }
	    login("1;" + col[5] + ";" + col[6] + ";" + elite, op);
	  }
	}

	// Our PHP session has timed out, kick out the user
	if(logged_in && col[5] == "demo") {
	  logout(gt.gettext("Your session has timed out, please log in again."));
	}
      }

    } else {
      // Route map
      if($('statsbox')) $('statsbox').style.visibility = "hidden";
      
      apid = col[0];
      flightTotal = col[1];
      desc = col[2];
      if(apid.startsWith("L")) {
	type = "L";
	coreid = apid;
	title = gt.gettext("List all routes on this airline");
      } else {
	type = "R";
	coreid = "R" + apid + "," + apid;
	title = gt.gettext("List all routes from this airport");
      }
      
      var maptitle = "<img src=\"/img/close.gif\" onclick=\"JavaScript:clearFilter(true);\" width=17 height=17> " + desc;
      maptitle += " <a href='#' onclick='JavaScript:xmlhttpPost(\"" + URL_FLIGHTS + "\",\"" + coreid + "\", \"" + encodeURI(desc) + "\");'><img src='/img/icon_copy.png' width=16 height=16 title='" + title + "'></a>";

      var form = document.forms['filterform'];
      if(form) {
	filter_alid = form.Airlines.value.split(";")[0];
	if(filter_alid != 0 && ! apid.startsWith("L")) {
	  maptitle += " <small>on " + form.Airlines.value.split(";")[1] + "</small> " + getAirlineMapIcon(filter_alid);
	}
      }
      maptitle = maptitle.replace("routes", gt.gettext("routes"));
      $("maptitle").innerHTML = maptitle;
    }
    
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
	if(url == URL_ROUTES) {
	  color = COLOR_ROUTE;
	} else {
	  color = modecolors[rCol[9]];
	  if(!color) color = COLOR_NORMAL;
	}
	flightLayer.addFeatures(drawLine(parseFloat(rCol[1]), parseFloat(rCol[2]),
				       parseFloat(rCol[4]), parseFloat(rCol[5]),
				       rCol[6], rCol[7], color, stroke));
      }
    } else {
      $("maptitle").innerHTML = "No flights";
    }
    
    // Route maps draw the core airport even if there are no routes
    if(flightTotal != "0" || type == "R") {
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
      airportLayer.addFeatures(airports);
    }
    
    // Redraw selection markers if in input mode
    if(getCurrentPane() == "input") {
      if(input_srcmarker) markAirport("src_ap", true);
      if(input_dstmarker) markAirport("dst_ap", true);
    }
    
    $("ajaxstatus").style.display = 'none';
    if(initializing) {
      if(! logged_in && demo_mode) {
	$("loginform").style.display = 'inline';
      }
      $('statsbox').style.visibility = "visible";
      $('filter').style.visibility = "visible";
      if(logged_in || privacy == 'O') {
	$('filter_extra_key').style.visibility = "visible";
      } else {
	$('filter_extra_key').style.visibility = "hidden";
      }
    }
  }

  // Add a temporary source or destination marker over currently selected airport
  // Also calculates distance and duration (unless "quick" is true)
  // type: "src_ap" or "dst_ap"
  function markAirport(element, quick) {
    if(element.startsWith("src_ap")) {
      var icon = '/img/icon_plane-src.png';
    } else {
      var icon = '/img/icon_plane-dst.png';
    }
    if(getCurrentPane() == "multiinput") {
      element = markingLimit(element);
    }
    if(!element) return; // nothing to draw
    
    var data = $(element + 'id').value.split(":");
    var iata = data[0];
    var apid = data[1];
    var x = data[2];
    var y = data[3];
    
    if(apid > 0) {
      var point = new OpenLayers.Geometry.Point(x, y);
      var marker = new OpenLayers.Feature.Vector(point);
      marker.attributes = {
	name: "",
	icon: icon,
	size: 17,
	offset: -17/2,
	opacity: 1,
	code: data[0]
      };
      map.addAirports(marker, {silent: true});
    }
    if(element.startsWith("src_ap")) {
      if(input_srcmarker) {
	airportLayer.removeFeatures([input_srcmarker]);
      }
      if(apid > 0) {
	input_srcmarker = marker;
	if(element == "src_ap") {
	  input_toggle = "dst_ap"; // single input
	} else {
	  var idx = multiinput_order.indexOf(element) + 1;
	  if(idx == multiinput_order.length) idx = 0;
	  input_toggle = multiinput_order[idx];
	}
      } else {
	input_srcmarker = null;
      }
    } else {
      if(input_dstmarker) {
	airportLayer.removeFeatures([input_dstmarker]);
      }
      if(apid > 0) {
	input_dstmarker = marker;
	if(element == "dst_ap") {
	  input_toggle = "src_ap"; // single input
	} else {
	  var idx = multiinput_order.indexOf(element) + 1;
	  if(idx == multiinput_order.length) idx = 0;
	  input_toggle = multiinput_order[idx];
	}
      } else {
	input_dstmarker = null;
      }
    }
    
    // Draw line and calculate distance and duration
    if(! quick) {
      if(input_line) {
	lineLayer.removeFeatures(input_line);
	input_line = null;
      }
      if(input_dstmarker && input_srcmarker) {
	if(getCurrentPane() == "input") {
	  
	  var lon1 = getX('src_ap');
	  var lat1 = getY('src_ap');
	  var lon2 = getX('dst_ap');
	  var lat2 = getY('dst_ap');
	  var distance = gcDistance(lat1, lon1, lat2, lon2);
	  input_line = drawLine(parseFloat(lon1), parseFloat(lat1),
				parseFloat(lon2), parseFloat(lat2),
				4, distance, COLOR_HIGHLIGHT);
	} else {
	  input_line = [];
	  for(i = 1; i <= multiinput_rows; i++) {
	    var src_ap = $('src_ap' + i + 'id').value;
	    var dst_ap = $('dst_ap' + i + 'id').value;
	    if(src_ap != 0 && dst_ap != 0) {
	      var src_ap_data = src_ap.split(":");
	      var lon1 = src_ap_data[2];
	      var lat1 = src_ap_data[3];
	      var dst_ap_data = dst_ap.split(":");
	      var lon2 = dst_ap_data[2];
	      var lat2 = dst_ap_data[3];
	      var distance = gcDistance(lat1, lon1, lat2, lon2);
	      line = drawLine(parseFloat(lon1), parseFloat(lat1),
			      parseFloat(lon2), parseFloat(lat2),
			      4, distance, COLOR_HIGHLIGHT);
	      input_line = input_line.concat(line);
	    } else {
	      break; // stop drawing
	    }
	  }
	}
	map.addFlights(input_line);
	$('distance').value = distance;
	calcDuration("AIRPORT");
      } else {
	$('distance').value = "";
	$('duration').value = "";
      }
    }
  }

  // Remove input markers and flight lines 
  function unmarkAirports() {
    if(input_srcmarker) {
      airportLayer.removeFeatures([input_srcmarker]);
      input_srcmarker = null;
    }
    if(input_dstmarker) {
      airportLayer.removeFeatures([input_dstmarker]);
      input_dstmarker = null;
    }
    if(input_line) {
      flightLayer.removeFeatures(input_line);
      input_line = null;
    }
  }

  // Add a popup to map
  function addPopup(popup) {
    ol_map.addPopup(popup);
  }

  // Unselect any selected airports
  function unselectAll() {
    selectControl.unselectAll();
  }

  // Zoom to maximum visible extent
  function zoom() {
    var extent = getVisibleDataExtent(flightLayer);
    if(extent) ol_map.zoomToExtent(extent);
  }


  // Clear all flights, airports and popups
  function clear() {
    flightLayer.destroyFeatures();
    airportLayer.destroyFeatures();
    var popups = map.popups;
    for(p = 0; p < popups.length; p++) {
      popups[p].destroy();
    }
  }

  // Given apid or code, find the matching airport and either pop it up (select=false) or mark it as selected (select=true)
  // "quick" is passed to markAirport
  function selectAirport(apid, select, quick, code) {
    var found = false;
    for(var ap = 0; ap < airportLayer.features.length; ap++) {
      attrstack = new Array();
      if(airportLayer.features[ap].cluster) {
	for(var c = 0; c < airportLayer.features[ap].cluster.length; c++) {
	  attrstack.push(airportLayer.features[ap].cluster[c].attributes);
	}
      } else {
	attrstack.push(airportLayer.features[ap].attributes);
      }
      while(attrstack.length > 0) {
	attrs = attrstack.pop();
	if((apid && attrs.apid == apid) ||
	   (code && attrs.code == code)) {
	  // If "select" is true, we select the airport into the input form instead of popping it up
	  if(select && isEditMode()) {
	    var element = input_toggle;
	    $(element).value = attrs.name;
	    $(element).style.color = "#000";
	    $(element + 'id').value = attrs.apdata;
	    replicateSelection(element);
	    markAirport(element, quick);
	    markAsChanged(true);
	    closePopup(true);
	  } else {
	    if(airportLayer.features[ap].cluster) {
	      onAirportSelect(airportLayer.features[ap].cluster[attrstack.length]);
	    } else {
	      onAirportSelect(airportLayer.features[ap]);
	    }
	  }
	  found = true;
	  return found;
	}
      }
    }
    // Search failed
    if (!quick && !code) {
      if(confirm("This airport is currently filtered out. Clear filter?")) {
	clearFilter(false);
      }
    }
    return false;
  }
  
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
      of_debug("ERROR: " + name + ":" + colorIndex + " of " + airportMaxFlights + ".i<br>Please hit CTRL-F5 to force refresh, and <a href='/about.html'>report</a> this error if it does not go away.");
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
  
  // Compute extent for visible data (-180 to 180)
  // Known bug: incorrectly draws whole map if flight lines span the meridian...
  function getVisibleDataExtent(layer) {
    var bounds = layer.getDataExtent();
    if(! bounds) return null;
    if(bounds.left < -180 && bounds.left > -360 && bounds.right > 180 && bounds.right < 360) {
      // map spans the world, do nothing
    } else {
      if(bounds.left < -180) bounds.left += 360;
      if(bounds.right > 180) bounds.right -= 360;
    }
    return bounds;
  }

  function clusterRadius(feature) {
    var radius = feature.attributes.count * 5;
    if(radius > 29) radius = 29;
    return radius;
  }

  function of_debug(str) {
    if(OF_DEBUG) {
      $("maptitle").style.display = 'inline';
      $("maptitle").innerHTML = $("maptitle").innerHTML + "<br>" + str;
    }
  }

  // constructor starts

  this.load = load;
  this.update = update;
  this.addPopup = addPopup;

  ol_map = new OpenLayers.Map('map', {
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
  
  flightLayer = new OpenLayers.Layer.Vector(gt.gettext("Flights"),
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
  airportLayer = new OpenLayers.Layer.Vector("Airports",
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

  ol_map.zoomToMaxExtent();

  OpenLayers.Util.alphaHack = function() { return false; };
}
