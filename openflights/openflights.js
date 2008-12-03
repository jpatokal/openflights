/**
 * openflights.js -- for openflights.org
 * by Jani Patokallio <jpatokal@iki.fi>
 */

// Core map features
var map, drawControls, selectControl, selectedFeature, lineLayer, currentPopup;
var paneStack = [ "ad" ];

// Filter selections and currently chosen airport 
var filter_user = 0, filter_trid = 0, filter_alid = 0, filter_year = 0, apid = 0;
var tripname, tripurl;
var privacy = "Y", flightTotal = 0;

// Current list of flights
var fidList, fidPtr = 0, fid = 0;
// Query and description of current list
var lastQuery, lastDesc;

// Temporary variables for current flight being edited
var alid = 0, plane;
var input = false, logged_in = false, initializing = true;
var input_srcmarker, input_dstmarker, input_toggle;
var majorEdit = false;
// If true, the value has been set by autocomplete
var srcApidAC = false, dstApidAC = false, airlineAC = false;
var URL_FLIGHTS = "/php/flights.php";
var URL_GETCODE = "/php/autocomplete.php";
var URL_LOGIN = "/php/login.php";
var URL_LOGOUT = "/php/logout.php";
var URL_MAP = "/php/map.php";
var URL_STATS = "/php/stats.php";
var URL_SUBMIT = "/php/submit.php";
var URL_TOP10 = "/php/top10.php";

var CODE_FAIL = 0;
var CODE_ADDOK = 1;
var CODE_EDITOK = 2;
var CODE_DELETEOK = 100;

var INPUT_MAXLEN = 50;
var AIRLINE_MAXLEN = 21;

var airportMaxFlights = 0;
var airportIcons = [ [ '/img/icon_plane-13x13.png', 13 ],
                     [ '/img/icon_plane-15x15.png', 15 ],
		     [ '/img/icon_plane-17x17.png', 17 ],
		     [ '/img/icon_plane-19x19b.png', 19 ],
		     [ '/img/icon_plane-19x19b.png', 19 ],
		     [ '/img/icon_plane-19x19.png', 19 ] ];

var classes = {"Y":"Economy", "P":"Prem.Eco", "C":"Business", "F":"First", "": ""};
var seattypes = {"W":"Window", "A":"Aisle", "M":"Middle", "": ""};
var reasons = {"B":"Business", "L":"Leisure", "C":"Crew", "O": "Other", "": ""};
var classes_short = {"Y":"Eco", "P":"PrE", "C":"Biz", "F":"1st", "": ""};
var seattypes_short = {"W":"Win", "A":"Ais", "M":"Mid", "": ""};
var reasons_short = {"B":"Wrk", "L":"Fun", "C":"Crw", "O":"Oth", "": ""};

window.onload = function init(){

  var bounds = new OpenLayers.Bounds(-180, -90, 180, 90);
  map = new OpenLayers.Map('map', {
      //eventListeners: { "zoomend": zoomEvent },
    maxExtent: bounds,
			       maxResolution: "auto",
			       maxZoomLevel: 8,
			       controls: [
					  new OpenLayers.Control.PanZoom(),
					  new OpenLayers.Control.NavToolbar(),
					  new OpenLayers.Control.LayerSwitcher({'ascending':false}),
					  new OpenLayers.Control.ScaleLine(),
					  new OpenLayers.Control.OverviewMap()
					  ] });
  
  var ol_wms = new OpenLayers.Layer.WMS( "Political (Metacarta)",
					 "http://labs.metacarta.com/wms/vmap0?",
					 {layers: 'basic'},
					 {transitionEffect: 'resize', wrapDateLine: true}
					 );
  
  var jpl_wms = new OpenLayers.Layer.WMS( "Geographical (NASA)",
					  "http://t1.hypercube.telascience.org/cgi-bin/landsat7", 
					  {layers: "landsat7"},
					  {transitionEffect: 'resize', wrapDateLine: true}
					  );
  jpl_wms.setVisibility(false);

  lineLayer = new OpenLayers.Layer.Vector("My Flights",
					{styleMap: new OpenLayers.StyleMap({
					    strokeColor: "#ee9900",
						strokeOpacity: 1,
						strokeWidth: "${count}"
						})
					    });
  
  
  airportLayer = new OpenLayers.Layer.Markers("My Airports");
  
  map.addLayers([ol_wms, jpl_wms, lineLayer, airportLayer]);
  
  /* flight selection -- currently disabled
  selectControl = new OpenLayers.Control.SelectFeature(lineLayer,
						       {onSelect: onFeatureSelect, onUnselect: onFeatureUnselect});
  drawControls = {
    select: selectControl
  };
  map.addControl(drawControls.select); */

  // Extract any arguments from URL
  filter_trid = parseArgument("trip");
  filter_user = parseArgument("user");

  // Are we viewing another user's flights or trip?
  if(filter_user != "0" || filter_trid != 0) {
    document.getElementById("loginstatus").style.display = 'inline';
    if(filter_trid != 0) {
      document.getElementById("filter_tripselect").style.display = 'none';
    }
  } else {
    document.getElementById("loginform").style.display = 'inline';
    document.getElementById("news").style.display = 'inline';

    // Nope, set up hinting and autocompletes for editor
    initHintTextboxes();

    new Ajax.Autocompleter("src_ap", "src_apAC", "php/autocomplete.php",
    			   {afterUpdateElement : getSelectedSrcApid});
    new Ajax.Autocompleter("dst_ap", "dst_apAC", "php/autocomplete.php",
    			   {afterUpdateElement : getSelectedDstApid});
    new Ajax.Autocompleter("airline", "airlineAC", "php/autocomplete.php",
    			   {afterUpdateElement : getSelectedAlid});
    new Ajax.Autocompleter("plane", "planeAC", "php/autocomplete.php",
    			   {afterUpdateElement : getSelectedPlid});

    map.zoomToMaxExtent();
  }

  OpenLayers.Util.alphaHack = function() { return false; };

  xmlhttpPost(URL_MAP, 0, true);
 }    

// Extract arguments from URL (/trip/xxx or /user/xxx)
// Returns null if not found
function parseArgument(name)
{
  // http://foobar.com/name/xxx
  // 0    1 2          3    4
  var urlbits = window.location.href.split('/');
  if(urlbits[3] == name) {
    return unescape(urlbits[4]);
  } else {
    return 0;
  }
}

/* currently not used
function onFeatureSelect(feature) {
  selectedFeature = feature;
  popup = new OpenLayers.Popup.FramedCloud("chicken", 
					   feature.geometry.getBounds().getCenterLonLat(),
					   null,
					   "<div style='font-size:.8em'>" + feature.attributes.flight +"</div>",
					   null, true, onPopupClose);
  feature.popup = popup;
  map.addPopup(popup);
}
function onFeatureUnselect(feature) {
  map.removePopup(feature.popup);
  feature.popup.destroy();
  feature.popup = null;
}
function onPopupClose(evt) {
  selectControl.unselect(selectedFeature);
}
  */

function drawLine(x1, y1, x2, y2, count, distance) {
  if(x2 < x1) {
    var tmpx = x1;
    var tmpy = y1;
    x1 = x2;
    y1 = y2;
    x2 = tmpx;
    y2 = tmpy;
  }
  // 1,2 flights as single pixel
  count = Math.floor(Math.sqrt(count) + 0.5);

  var cList = null, wList = null, eList = null;
  if(distance > GC_MIN) {
    // Plot great circle curve
    cList = gcPath(new OpenLayers.Geometry.Point(x1, y1), new OpenLayers.Geometry.Point(x2, y2), distance);

    // Path is in or extends into east (+) half, so we have to make a -360 copy
    if(x1 > 0 || x2 > 0) {
      wList = gcPath(new OpenLayers.Geometry.Point(x1-360, y1), new OpenLayers.Geometry.Point(x2-360, y2), distance);
    }
    // Path is in or extends into west (-) half, so we have to make a +360 copy
    if(x1 < 0 || x2 < 0) {
      eList = gcPath(new OpenLayers.Geometry.Point(x1+360, y1), new OpenLayers.Geometry.Point(x2+360, y2), distance);
    }
  } else {
    // Draw straight lines
    cList = straightPath(new OpenLayers.Geometry.Point(x1, y1), new OpenLayers.Geometry.Point(x2, y2));

    // Path is in or extends into east (+) half, so we have to make a -360 copy
    if(x1 > 0 || x2 > 0) {
      wList = straightPath(new OpenLayers.Geometry.Point(x1-360, y1), new OpenLayers.Geometry.Point(x2-360, y2));
    }
    // Path is in or extends into west (-) half, so we have to make a +360 copy
    if(x1 < 0 || x2 < 0) {
      eList = straightPath(new OpenLayers.Geometry.Point(x1+360, y1), new OpenLayers.Geometry.Point(x2+360, y2));
    }
  }
  var features = [ new OpenLayers.Feature.Vector(new OpenLayers.Geometry.LineString(cList), {count: count}) ];
  if(wList) {
    features.push(new OpenLayers.Feature.Vector(new OpenLayers.Geometry.LineString(wList), {count: count}));
  }
  if(eList) {
    features.push(new OpenLayers.Feature.Vector(new OpenLayers.Geometry.LineString(eList), {count: count}));
  }
  lineLayer.addFeatures(features);
}

function drawAirport(airportLayer, apid, x, y, name, code, city, country, count, formattedName) {
  var desc = name + " (<B>" + code + "</B>)<br><small>" + city + ", " + country + "</small><br>Flights: " + count;
  // Detailed flights accessible only if...
  // 1. user is logged in, or
  // 2. system is in "demo mode", or
  // 3. privacy is set to (O)pen
  if( logged_in ||
      (filter_user == 0 && filter_trid == 0) ||
      privacy == "O")
    {
      desc += " <input type=\"button\" value=\"View\" align=\"middle\" onclick='JavaScript:xmlhttpPost(\"" + URL_FLIGHTS + "\"," + apid + ", \"" + escape(desc) + "\")'>";
  }
  desc = "<img src=\"/img/close.gif\" onclick=\"JavaScript:closePopup();\" width=17 height=17> " + desc;

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
  if(colorIndex >= airportIcons.length) {
    colorIndex = airportIcons.length - 1;
  }
  // This should never happen
  if(! airportIcons[colorIndex]) {
    alert("ERROR: " + name + ":" + colorIndex);
    colorIndex = 0;
  }
  var iconfile = airportIcons[colorIndex][0];
  var size = new OpenLayers.Size(airportIcons[colorIndex][1], airportIcons[colorIndex][1]);
  var offset = new OpenLayers.Pixel(-(size.w/2), -(size.h/2));
  var icon = new OpenLayers.Icon(iconfile,size,offset);
  
  var feature = new OpenLayers.Feature(airportLayer, new OpenLayers.LonLat(x, y));
  feature.closeBox = false;
  feature.popupClass = OpenLayers.Class(OpenLayers.Popup.FramedCloud, {'autoSize': true, 'minSize': new OpenLayers.Size(200,110) });
  feature.data.icon = icon;
  feature.data.popupContentHTML = desc;
  feature.data.overflow = "auto";
  var marker = feature.createMarker();
  marker.apid = apid;
  marker.code = code;
  marker.count = count;
  feature.apid = apid;
  feature.code = code;
  feature.name = formattedName;

  // Run when the user clicks on an airport marker
  // this == the feature, *not* the marker
  var markerClick = function (evt) {
    closePopup();
    
    // If input mode is active, we select the airport instead of popping it up
    if(input) {
      data = this.code + ":" + this.apid + ":" + this.lonlat.lon + ":" + this.lonlat.lat;
      if(input_toggle == "SRC") {
	$('src_ap').value = this.name;
	$('src_ap').style.color = "#000";
	$('src_apid').value = data;
	selectNewAirport('src_ap');
      } else {
	$('dst_ap').value = this.name;
	$('src_ap').style.color = "#000";
	$('dst_apid').value = data;
	selectNewAirport('dst_ap');
      }
      return;
    }
    
    if (this.popup == null) {
      this.popup = this.createPopup(this.closeBox);
      map.addPopup(this.popup);
      this.popup.show();
    } else {
      this.popup.toggle();
    }
    if(this.popup.visible()) {
      currentPopup = this.popup;
    } else {
      closePane();
    }
    OpenLayers.Event.stop(evt);
  };
  marker.events.register("mousedown", feature, markerClick);
  //marker.display(zoomFilter(count));
  airportLayer.addMarker(marker);
}

function toggleControl(element) {
  for(key in drawControls) {
    var control = drawControls[key];
    if(element.value == key && element.checked) {
      control.activate();
    } else {
      control.deactivate();
      onPopupClose();
    }
  }
}

function xmlhttpPost(strURL, id, param) {
  var xmlHttpReq = false;
  var self = this;
  var query = "";
  // Mozilla/Safari
  if (window.XMLHttpRequest) {
    self.xmlHttpReq = new XMLHttpRequest();
  }
  // IE
  else if (window.ActiveXObject) {
    self.xmlHttpReq = new ActiveXObject("Microsoft.XMLHTTP");
  }
  self.xmlHttpReq.open('POST', strURL, true);
  self.xmlHttpReq.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  self.xmlHttpReq.onreadystatechange = function() {
    if (self.xmlHttpReq.readyState == 4) {

      // Process results of query

      // First make sure session is still up
      // (note: sessionfree PHPs do not return this string)
      if(self.xmlHttpReq.responseText.substring(0, 13) == "Not logged in") {
	logout(self.xmlHttpReq.responseText);
	return;
      }

      if(strURL == URL_FLIGHTS) {
	switch(param) {
	  case "COPY":
	  case "EDIT":
	    editFlight(self.xmlHttpReq.responseText, param);
	    break;
	  
	  case "RELOAD":
	    param = lastDesc;
	    // param contains previously escaped semi-random HTML title
	    // fallthru

	  default:
  	    listFlights(self.xmlHttpReq.responseText, unescape(param));
	    break;
	}
      }
      if(strURL == URL_GETCODE) {
	var cols = self.xmlHttpReq.responseText.split(";");
	switch(param) {
	case 'airline':
	  var alid = cols[0];
	  if(alid != 0) {
	    $('airline_id').value = cols[0];
	    $('airline').value = cols[1];
	    $('airline').style.color = '#000000';
	  } else {
	    $('airline').style.color = '#FF0000';
	    $('airline_id').value = 0;
	  }
	  break;

	case 'src_ap':
	case 'dst_ap':
	  var apdata = cols[0];
	  var apid = apdata.split(":")[1];
	  if(apid && apid != 0) {
	    $(param + 'id').value = apdata;
	    $(param).value = cols[1];
	    $(param).style.color = '#000000';
	    selectNewAirport(param);
	  } else {
	    invalidateAirport(param);
	  }
	  break;
	}
      }
      if(strURL == URL_LOGIN) {
	login(self.xmlHttpReq.responseText, param);
      }
      if(strURL == URL_LOGOUT) {
	logout(self.xmlHttpReq.responseText);
      }
      if(strURL == URL_MAP) {
	var str = self.xmlHttpReq.responseText;
	if(str.substring(0,5) == "Error") {
	  document.getElementById("result").innerHTML = "<h4>" + str.split(';')[1] + "</h4><br><h6><a href='/'>Home</a></h6>";
	  document.getElementById("statsbox").style.visibility = "hidden";
	  document.getElementById("filter").style.visibility = "hidden";
	  openPane("result");
	} else {
	  if(! logged_in) {
	    closePane();
	  }
	  updateMap(str);
	  if(param) {
	    updateFilter(str);
	    // Zoom map to fit when loading another's flights/trip
	    if(! logged_in && (filter_user != 0 || filter_trid != 0)) {
	      var form = document.forms['filterform'];    
	      if(form.Airlines.selectedIndex == 0 &&
		form.Years.selectedIndex == 0 &&
		 (filter_trid != 0 || form.Trips.selectedIndex == 0)) {
		map.zoomToExtent(airportLayer.getDataExtent());
	      }
	    }
	  }
	  updateTitle();
	} 
      }
      if(strURL == URL_STATS) {
	showStats(self.xmlHttpReq.responseText);
      }
      if(strURL == URL_TOP10) {
	showTop10(self.xmlHttpReq.responseText);
      }
      if(strURL == URL_SUBMIT) {
	var result = self.xmlHttpReq.responseText.split(";");
	code = result[0];
	text = result[1];
	document.getElementById("input_status").innerHTML = '<B>' + text + '</B>';
	setInputAllowed(false);

	// Something went wrong, so we just abort
	if(code == CODE_FAIL) {
	  return;
	}

	// If flight was successfully deleted...
	if(code == CODE_DELETEOK) {

	  //... and we're in input mode, move to another flight
	  if(getCurrentPane() == "input") {
	    // Last flight deleted
	    if(fidList.length == 1) {
	      clearStack();
	    } else {
	      // Remove current flight
	      fidList.splice(fidPtr, 1);
	      
	      // Edit next if you can
	      if(fidPtr < fidList.length) {
		editPointer(0);
	      } else {
		// Move back
		editPointer(-1);
	      }
	    }
	  } else {
	    // Not in edit mode, so reload currently displayed list of flights
	    xmlhttpPost(URL_FLIGHTS, 0, "RELOAD");
	  }
	}

	if(code == CODE_EDITOK || code == CODE_ADDOK) {
	  // If adding new flights (not editing), swap last destination to be new source and focus on date
	  if(document.getElementById("addflighttitle").style.display == 'inline') {
	    swapAirports(false);
	    document.forms['inputform'].src_date.focus();
	  }
	}

	// A change that affected the map was made, so redraw (after any ops above complete)
	if(majorEdit || code == CODE_DELETEOK) {
	  setTimeout('refresh(false)', 1000);
	}
	majorEdit = false;
      }
      document.getElementById("ajaxstatus").style.display = 'none';
    }
  }
  // End result processing

  // Start query string generation
  switch(strURL) {

  case URL_SUBMIT:
    var inputform = document.forms['inputform'];

    // Deleting needs only the fid, and can be run without the inputform
    if(param != "DELETE") {
      var src_date = inputform.src_date.value;
      // leading zeroes not required for month, date
      var re_date = /^(19|20)\d\d[- /.]([1-9]|0[1-9]|1[012])[- /.]([1-9]|0[1-9]|[12][0-9]|3[01])$/
      if(! re_date.test(src_date)) {
	alert("Please enter a full date in year/month/date order, eg. 2008/10/30 for 30 October 2008. Valid formats include YYYY-MM-DD, YYYY/MM/DD, YYYY.MM.DD and YYYY MM DD.");
	document.forms['inputform'].src_date.focus();
	return;
      }

      var src_apid = $('src_apid').value.split(':')[1];
      if(! src_apid || src_apid == "0") {
	alert("Please enter a valid source airport.");
	document.forms['inputform'].src_ap.focus();
	return;
      }
      var dst_apid = $('dst_apid').value.split(':')[1];
      if(! dst_apid || dst_apid == "0") {
	alert("Please enter a valid destination airport.");
	document.forms['inputform'].dst_ap.focus();
	return;
      }
      var alid = $('airline_id').value;
      var airline = $('airline').value.trim();
      if(! alid || airline == "" || airline == $('airline').hintText) {
	alid = "-1"; // UNKNOWN
      }
      var type = inputform.seat_type.value;
      if(type == "-") type = "";
      var myClass = radioValue(inputform.myClass);
      var reason = radioValue(inputform.reason);
      var plane = inputform.plane.value;
      if(plane == "Enter plane model") {
	plane = "";
      }
      var trid = inputform.trips[inputform.trips.selectedIndex].value.split(";")[0];
      if(trid == 0) trid = "NULL";
      var registration = inputform.registration.value;
      var note = inputform.note.value;
    }

    query = 'src_date=' + escape(inputform.src_date.value) + '&' +
      'duration=' + escape(inputform.duration.value) + '&' +
      'distance=' + escape(inputform.distance.value) + '&' +
      'src_apid=' + escape(src_apid) + '&' +
      'dst_apid=' + escape(dst_apid) + '&' +
      'number=' + escape(inputform.number.value) + '&' +
      'seat=' + escape(inputform.seat.value) + '&' +
      'type=' + escape(type) + '&' +
      'class=' + escape(myClass) + '&' +
      'reason=' + escape(reason) + '&' +
      'registration=' + escape(registration) + '&' +
      'note=' + escape(note) + '&' +
      'plane=' + escape(plane) + '&' +
      'alid=' + escape(alid) + '&' +
      'trid=' + escape(trid) + '&' +
      'fid=' + escape(fid) + '&' +
      'param=' + escape(param);
    break;

  case URL_LOGIN:
    document.getElementById("ajaxstatus").style.display = 'inline';
    var name = document.forms['login'].name.value;
    var pw = document.forms['login'].pw.value;
    query = 'name=' + escape(name) + '&' + 'pw=' + escape(pw);
    break;

  case URL_GETCODE:
    query = escape(param) + '=' + escape(id) + '&quick=true';
    break;
    
  case URL_LOGOUT:
    // no parameters needed
    break;

  case URL_FLIGHTS:
    if(param != "EDIT" && param != "COPY") {
      if(id) {
	apid = id;
      } else {
	id = 0;
      }
    }
    if(param == "RELOAD") {
      query = lastQuery;
      break;
    }
    // ...else generate new query

  // URL_MAP, URL_FLIGHTS, URL_STATS
  default:
    document.getElementById("ajaxstatus").style.display = 'inline';
    var form = document.forms['filterform'];    
    if(initializing) {
      initializing = false;
    } else {
      filter_trid = form.Trips.value.split(";")[0];
    }
    filter_alid = form.Airlines.value.split(";")[0];
    filter_year = form.Years.value;
    query = 'user=' + escape(filter_user) + '&' +
      'trid=' + escape(filter_trid) + '&' +
      'alid=' + escape(filter_alid) + '&' +
      'year=' + escape(filter_year) + '&' +
      'param=' + escape(param);
    if(strURL == URL_FLIGHTS) {
      if(param == "EDIT" || param == "COPY") {
	query += '&fid=' + escape(id);
      } else {
	// This is a flight list query, so store its details
	query += '&id=' + escape(id);
	lastQuery = query;
	lastDesc = param;
      }
    }
  }
  self.xmlHttpReq.send(query);
}

function getquerystring(id, param) {
}

// Set up filter options from database result
// (also copies list of trips into editor)
function updateFilter(str) {
  var master = str.split("\n");
  var trips = master[3];
  var airlines = master[4];
  var years = master[5];

  var tripSelect = createSelect("Trips", "All trips", filter_trid, trips.split("\t"), 20, "refresh(true)");
  document.getElementById("filter_tripselect").innerHTML = tripSelect;
  var editTripSelect = document.forms['inputform'].trips;
  if(editTripSelect) {

    // New trip added, so now we need to figure out the newest (highest) trid to find it
    if(editTripSelect.reselect) {
      var newestId = 0;
      var filterTripSelect = document.forms['filterform'].Trips;
      for(i = 0; i < filterTripSelect.length; i++) {
	id = filterTripSelect[i].value.split(";")[0];
	if(parseInt(id) > newestId) {
	  newestId = id;
	  selected = i;
	}
      }
    } else {
      selected = editTripSelect.selectedIndex;
    }
  } else {
    selected = null;
  }
  document.getElementById("input_trip_select").innerHTML =
    cloneSelect(document.forms['filterform'].Trips, "trips", "markAsChanged", selected);
  document.forms['inputform'].trips[0].text = "Select trip";

  var airlineSelect = createSelect("Airlines", "All airlines", filter_alid, airlines.split("\t"), AIRLINE_MAXLEN, "refresh(true)");
  document.getElementById("filter_airlineselect").innerHTML = airlineSelect;
  var yearSelect = createSelect("Years", "All", filter_year, years.split("\t"), 20, "refresh(true)");
  document.getElementById("filter_yearselect").innerHTML = yearSelect;

}


// Refresh current map title
function updateTitle(str) {
  var form = document.forms['filterform'];
  var text = "";
  var airline = form.Airlines[form.Airlines.selectedIndex].value.split(";")[1];
  var trip = form.Trips[form.Trips.selectedIndex].value.split(";")[1];
  var year = form.Years[form.Years.selectedIndex].text;

  // Logged in users
  if(logged_in) {
    if(trip != "All trips") {
      text = tripname + " <a href=\"" + tripurl + "\">\u2197</a>";
    }
    if(airline != "All airlines") {
      if(text != "") text += ", ";
      text += airline;
    }
    if(year != "All") {
      if(text != "") text += " in ";
      text += year;
    }
    // For non-null titles, add X for easy filter removal
    if(text != "") {
      text = "<img src=\"/img/close.gif\" onclick=\"JavaScript:clearFilter(true);\" width=17 height=17> " + text;
    }

  } else {
    // Demo mode
    if(filter_user == 0 && filter_trid == 0) {
      if(airline != "All airlines") {
	text = "Recent flights on " + airline;
      } else {
	text = "Recently added flights";
      }

    } else {
      // Viewing another's profile
      if(trip != "All trips") {
	text = tripname + " <a href=\"" + tripurl + "\">\u2197</a>";
      } else {
	text = filter_user + "'s flights";
	if(airline != "All airlines") {
	  text += " on " + airline;
	}
	if(year != "All") {
	  text += " in " + year;
	}
      }
      document.getElementById("loginstatus").innerHTML = "<b>" + text +
	"</b> <h6><a href='/'>Home</a></h6>";
    }
  }
  document.getElementById("maptitle").innerHTML = text;
}

/*
 * Create a <SELECT> box from row of (id;name)
 *
 * name: document name (id) of select element
 * allopts: "No filtering" option
 * id: id to match col 1 against
 * rows: Array of strings
 * length: maximum length (omit or set to <= 0 to allow any length)
 * hook: Function to call on value change, with name as argument
 * tabIndex: tabindex
 */ 
function createSelect(selectName, allopts, id, rows, maxlen, hook, tabIndex) {
  var select = "<select class=\"filter\" name=\"" + selectName + "\"";
  if(hook) {
    select += " onChange='JavaScript:" + hook + "'";
  }
  if(tabIndex) {
    select += " tabindex=\"" + tabIndex + "\"";
  }
  if(selectName == "Years") {
    select += "><option value=\"0\">" + allopts + "</option>";
  } else {
    select += "><option value=\"0;" + allopts + "\">" + allopts + "</option>";
  }
  // No data?  Return an empty element
  if(! rows || rows == "") {
    return select + "</select>";
  }

  var selected = "";
  for (r = 0; r < rows.length; r++) {
    var col = rows[r].split(";");
    var rid = col[0];
    var name = col[1];
    var url = col[2];

    if(rid == id) {
      selected = " SELECTED";
      // Special case: un-truncated trip name and URL
      if(selectName == "Trips") {
	tripname = name;
	tripurl = url;
      }
    } else {
      selected = "";
    }
    // ID;Full name
    if(selectName != "Years") {
      rid = rid + ";" + name;
    }
    // Truncate display name
    if (maxlen && maxlen > 0 && name.length > maxlen) {
      // Three dots in a proportional font is about two chars...
      name = name.substring(0,maxlen - 2) + "...";
    }
    select += "<option value=\"" + rid + "\"" + selected + ">" + name + "</option>";
  }
  select += "</select>";
  return select;
}

// Create a copy of 'select', renamed (incl. hook) as 'name'
// Note: *not* class="filter", so width is not limited
function cloneSelect(oldSelect, name, hook, selected) {
  var newSelect = "<select name=\"" + name + "\"";
  if(hook) {
    newSelect += " onChange='JavaScript:" + hook + "(\"" + name + "\")'>";
  }
  for(index = 0; index < oldSelect.length; index++) {
    id = oldSelect[index].value.split(";")[0];
    text = oldSelect[index].value.split(";")[1];
    if(index == selected) {
      selectedText = " SELECTED";
    } else {
      selectedText = "";
    }
    newSelect += "<option value=\"" + id + "\" " + selectedText + ">" + text + "</option>";
  }
  newSelect += "</select>";
  return newSelect;
}


// Return value of currently selected radio button in this group
function radioValue(radio) {
  for (r=0; r < radio.length; r++){
    if (radio[r].checked) {
      return radio[r].value;
    }
  }
}

// Reinsert all flights, airports from database result
function updateMap(str){
  lineLayer.destroyFeatures();
  airportLayer.clearMarkers();

  var master = str.split("\n");
  var stats = master[0];
  var flights = master[1];
  var airports = master[2];
  
  var col = stats.split(";");
  var duration = col[2]; // minutes
  var days = Math.floor(col[2] / (60*24));
  var hours = Math.floor((col[2] / 60) % 24);
  var min = Math.floor(col[2] % 60);
  if(min < 10) min = "0" + min;

  stats = col[0] + " flights<br>" +
    col[1] + " mi flown<br>" +
    days + " days " + hours + ":" + min;
  document.getElementById("stats").innerHTML = stats;
  flightTotal = col[0];
  privacy = col[3];

  // New user with no flights?  Then don't even try to draw
  if(flightTotal == "0") {
    return;
  }

  var rows = flights.split(":");
  for (r = 0; r < rows.length; r++) {
    var rCol = rows[r].split(";");
    // apid1 0, x1 1, y1 2, apid2 3, x2 4, y2 5, count 6, distance 7
    drawLine(parseFloat(rCol[1]), parseFloat(rCol[2]), parseFloat(rCol[4]), parseFloat(rCol[5]), rCol[6], rCol[7]);
  }
  
  var rows = airports.split(":");

  // Airports are ordered from least busy to busiest, so we calibrate the color scale based on the last result
  airportMaxFlights = rows[rows.length - 1].split(";")[7];
  for (r = 0; r < rows.length; r++) {
    var col = rows[r].split(";");
    // apid, x, y, name, code, city, country, count, formatted_name
    drawAirport(airportLayer, col[0], col[1], col[2], col[3], col[4], col[5], col[6], col[7], col[8]);
  }
  //zoomEvent(); // filter in/out airports based on zoom level

  // Redraw selection markers if in input mode
  if(input) {
    if(input_srcmarker) selectNewAirport("src_ap", true);
    if(input_dstmarker) selectNewAirport("dst_ap", true);
  }    
}

function startListFlights() {
  var tripName = document.forms['filterform'].Trips[document.forms['filterform'].Trips.selectedIndex].text;  
  var airlineName = document.forms['filterform'].Airlines[document.forms['filterform'].Airlines.selectedIndex].text;
  var yearName = document.forms['filterform'].Years[document.forms['filterform'].Years.selectedIndex].text;
  xmlhttpPost(URL_FLIGHTS, 0, escape("Flights for " + tripName + " on " + airlineName + " in year " + yearName));
}

function listFlights(str, desc) {
  openPane("result");
  fidList = new Array();
  table = "<img src=\"/img/close.gif\" onclick=\"JavaScript:closePane();\" width=17 height=17> ";
  if(str == "") {
    table += "<i>No flights found at this airport.</i></span></div>";
  } else {
    if(desc) {
      table += desc.replace(/\<br\>/g, " &mdash; ");
      table = "<span style='float: right'><input type='button' value='Export' align='middle' onclick='JavaScript:exportFlights(\"export\")'></span>" + table;
    }
    table += "<table width=100% class=\"sortable\" id=\"apttable\" cellpadding=\"0\" cellspacing=\"0\">";
    table += "<tr><th>From</th><th>To</th><th>Flight</th><th>Date</th><th class=\"sorttable_numeric\">Miles</th><th>Time</th><th>Plane</th><th>Seat</th><th>Class</th><th>Reason</th><th>Trip</th><th>Note</th>";
    if(logged_in) {
      table += "<th class=\"unsortable\">Action</th>";
    }
    table += "</tr>";
    var rows = str.split("\n");
    for (r = 0; r < rows.length; r++) {
      // src_iata 0, src_apid 1, dst_iata 2, dst_apid 3, flight code 4, date 5, distance 6, duration 7, seat 8, seat_type 9, class 10, reason 11, fid 12, plane 13, registration 14, alid 15, note 16, trid 17, plid 18, airline_code 19
      var col = rows[r].split("\t");
      var trip = col[17];
      var seat = col[8] + " " + seattypes[col[9]];
      var plane = col[13];
      var fid = col[12];
      var code = col[4];
      // If no flight number, then use airline code
      if(code == "") {
	code = col[19];
      }
      if(col[14] != "") {
	plane += " (" + col[14] + ")";
      }
      if(logged_in && trip != "") {
	trip = "<a href=\"#\" onclick=\"JavaScript:editTrip(" + trip + ");\">" + trip + "</a>";
      }
      table += "<tr><td><a href=\"#\" onclick=\"JavaScript:selectAirport(" + col[1] + ");\">" + col[0] + "</a></td>" +
	"<td><a href=\"#\" onclick=\"JavaScript:selectAirport(" + col[3] + ");\">" + col[2] + "</a></td>" +
	"<td>" + code + "</td><td>" + col[5] + "</td><td>" + col[6] + "</td><td>" + col[7] +
	"</td><td>" + plane + "</td><td>" + seat +
	"</td><td>" + classes[col[10]] + "</td><td>" + reasons[col[11]] +
	"</td><td>" + trip + "</td>" +
	"</td><td>" + col[16].substring(0,15) + "</td>";
	
      if(logged_in) {
	table += "<td>" +
	  "<a href=\"#\" onclick=\"JavaScript:preEditFlight(" + fid + "," + r + ");\"><img src=\"/img/icon_edit.png\" width=16 height=16 title=\"Edit this flight\"></a>" +
	  "<a href=\"#\" onclick=\"JavaScript:preCopyFlight(" + fid + ");\"><img src=\"/img/icon_copy.png\" width=16 height=16 title=\"Copy to new flight\"></a>" +
	  "<a href=\"#\" onclick=\"JavaScript:deleteFlight(" + fid + ");\"><img src=\"/img/icon_delete.png\" width=16 height=16 title=\"Delete this flight\"></a>" +
	  "</td>";
      }
      table += "</tr>";
      fidList.push(fid);
    }
    table += "</table>";
  }
  document.getElementById("result").innerHTML = table;
  // Refresh sortables code
  sortables_init();
}

// Dump flights to CSV
// type: "backup" to export everything, "export" to export only current filter selection
function exportFlights(type) {
  location.href="http://" + location.host + "/php/flights.php?" + lastQuery + "&export=" + type;
}

function showStats(str) {
  if(str.substring(0,5) == "Error") {
    document.getElementById("result").innerHTML = str.split(';')[1];
    openPane("result");
    return;
  }

  var classPie = new pie(), reasonPie = new pie(), seatPie = new pie();
  openPane("result");
  if(str == "") {
    bigtable = "<i>Statistics calculation failed!</i>";
  } else {
    var master = str.split("\n");
    var uniques = master[0];
    var longshort = master[1];
    var extremes = master[2];
    var classData = master[3];
    var reasonData = master[4];
    var seatData = master[5];

    bigtable = "<table><td style=\"vertical-align: top\"><img src=\"/img/close.gif\" onclick=\"JavaScript:closePane();\" width=17 height=17></td><td style=\"vertical-align: top\">";

    table = "<table style=\"border-spacing: 10px 0px\">";
    table += "<tr><th colspan=2>Unique</th></tr>";
    var col = uniques.split(",");
    // num_airports, num_airlines, num_planes, distance
    distance = col[3];
    table += "<tr><td>Airports</td><td>" + col[0] + "</td></tr>";
    table += "<tr><td>Airlines</td><td>" + col[1] + "</td></tr>";
    table += "<tr><td>Plane types</td><td>" + col[2] + "</td></tr>";
    table += "<tr><td>&nbsp;</td></tr>";
    table += "<tr><th colspan=2>Distance</th></tr>";
    table += "<tr><td>Miles flown</td><td>" + distance + "</td></tr>";
    table += "<tr><td>Around the world</td><td>" + (distance / EARTH_CIRCUMFERENCE).toFixed(2) + "x</td></tr>";
    table += "<tr><td>To the Moon</td><td>" + (distance / MOON_DISTANCE).toFixed(3) + "x</td></tr>";
    table += "<tr><td>To Mars</td><td>" + (distance / MARS_DISTANCE).toFixed(4) + "x</td></tr>";
    table += "</table>";
    bigtable += table + "</td><td style=\"vertical-align: top\">";

    table = "<table style=\"border-spacing: 10px 0px\">";
    table += "<tr><th colspan=2>Flight records</th></tr>";
    var rows = longshort.split(";");
    for (r = 0; r < rows.length; r++) {
      var col = rows[r].split(",");
      // desc 0, distance 1, duration 2, s.iata 3, s.apid 4, d.iata 5, d.apid 6
      table += "<tr><td>" + col[0] + "</td><td><a href=\"#\" onclick=\"JavaScript:selectAirport(" + col[4] + ");\">" + col[3] + "</a>&harr;<a href=\"#\" onclick=\"JavaScript:selectAirport(" + col[6] + ");\">" + col[5] + "</a>, " + col[1] + " mi, " + col[2] + "</td></tr>";
    }
    table += "<tr><td>&nbsp;</td></tr>";
    table += "<tr><td>&nbsp;</td></tr>";
    table += "<tr><th colspan=2>Airport records</th></tr>";
    var rows = extremes.split(":");
    for (r = 0; r < rows.length; r++) {
      var col = rows[r].split(",");
      // 0 desc, 1 code, 2 apid, 3 x, 4 y
      lat = parseFloat(col[4]).toFixed(2);
      lon = parseFloat(col[3]).toFixed(2);
      if(lat < 0) {
	lat = -lat + "&deg;S";
      } else {
	lat += "&deg;N";
      }
      if(lon < 0) {
	lon = -lon + "&deg;W";
      } else {
	lon += "&deg;E";
      }
      table += "<tr><td>" + col[0] + "</td><td><a href=\"#\" onclick=\"JavaScript:selectAirport(" + col[2] + ");\">" + col[1] + "</a> (" + lat + " " + lon + ")</td></tr>";
    }
    table += "</table>";
    bigtable += table + "</td><td style=\"vertical-align: top\">";
  
    table = "<table style=\"border-spacing: 10px 0px\">";
    table += "<tr><th>Class</th><th>Reason</th></tr>";
    table += "<tr><td>";
    table += "<div id='classPie' style='position:relative;height:80px;width:150px;'></div>";
    var rows = classData.split(":");
    for (r = 0; r < rows.length; r++) {
      var col = rows[r].split(",");
      classPie.add(classes_short[col[0]], parseInt(col[1]));
    }
    table += "</td><td>";

    table += "<div id='reasonPie' style='position:relative;height:80px;width:150px;'></div>";
    var rows = reasonData.split(":");
    for (r = 0; r < rows.length; r++) {
      var col = rows[r].split(",");
      reasonPie.add(reasons_short[col[0]], parseInt(col[1]));
    }
    table += "</td></tr>";
    table += "<tr><th>Seats</th></tr><tr><td>";
    table += "<div id='seatPie' style='position:relative;height:80px;width:150px;'></div>";
    var rows = seatData.split(":");
    for (r = 0; r < rows.length; r++) {
      var col = rows[r].split(",");
      seatPie.add(seattypes_short[col[0]], parseInt(col[1]));
    }
    table += "</td></tr></table>";
    bigtable += table + "</td></tr></table>";
  }

  document.getElementById("result").innerHTML = bigtable;
  classPie.render("classPie", "Class");
  reasonPie.render("reasonPie", "Reason");
  seatPie.render("seatPie", "Seat");
}

function showTop10(str) {
  if(str.substring(0,5) == "Error") {
    document.getElementById("result").innerHTML = str.split(';')[1];
    openPane("result");
    return;
  }

  openPane("result");
  if(str == "") {
    bigtable = "<i>Statistics calculation failed!</i>";
  } else {
    var master = str.split("\n");
    var routes = master[0];
    var airports = master[1];
    var airlines = master[2];
    var planes = master[3];
    bigtable = "<table><td style=\"vertical-align: top\"><img src=\"/img/close.gif\" onclick=\"JavaScript:closePane();\" width=17 height=17></td><td style=\"vertical-align: top\">";

    table = "<table style=\"border-spacing: 10px 0px\">";
    table += "<tr><th colspan=3>Top 10 Routes</th></tr>";
    var rows = routes.split(":");
    for (r = 0; r < rows.length; r++) {
      var col = rows[r].split(",");
      // s.name, s.apid, d.name, d.apid, count
      table += "<tr><td><a href=\"#\" onclick=\"JavaScript:selectAirport(" + col[1] + ");\">" + col[0] + "</a>&harr;" +
	"<a href=\"#\" onclick=\"JavaScript:selectAirport(" + col[3] + ");\">" + col[2] + "</a></td>" + 
	"<td style='text-align: right'>" + col[4] + "</td></tr>";
    }
    table += "</table>";
    bigtable += table + "</td><td style=\"vertical-align: top\">";

    table = "<table style=\"border-spacing: 10px 0px\">";
    table += "<tr><th colspan=3>Top 10 Airports</th></tr>";
    var rows = airports.split(":");
    for (r = 0; r < rows.length; r++) {
      var col = rows[r].split(",");
      // name, iata, count, apid
      desc = col[0] + " (" + col[1] + ")";
      table += "<tr><td><a href=\"#\" onclick=\"JavaScript:selectAirport(" + col[3] + ");\">" + desc + "</a></td><td style='text-align: right'>" + col[2] + "</td>";
    }
    table += "</table>";
    bigtable += table + "</td><td style=\"vertical-align: top\">";
    
    table = "<table style=\"border-spacing: 10px 0px\">";
    table += "<tr><th colspan=3>Top 10 Airlines</th></tr>";
    var rows = airlines.split(":");
    for (r = 0; r < rows.length; r++) {
      var col = rows[r].split(",");
      // name, count, apid
      table += "<tr><td><a href=\"#\" onclick=\"JavaScript:selectAirline(" + col[2] + ");refresh(false);\">" + col[0] + "</a></td><td style='text-align: right'>" + col[1] + "</td>";
    }
    table += "</table>";
    bigtable += table + "</td><td style=\"vertical-align: top\">";
    
    table = "<table style=\"border-spacing: 10px 0px\">";
    table += "<tr><th colspan=3>Top 10 Planes</th></tr>";
    var rows = planes.split(":");
    for (r = 0; r < rows.length; r++) {
      var col = rows[r].split(",");
      // name, count
      table += "<tr><td>" + col[0] + "</td><td style='text-align: right'>" + col[1] + "</td>";
    }
    table += "</table>";
    bigtable += table + "</td></table>";
    
  }
  document.getElementById("result").innerHTML = bigtable;
}

// Move "pointer" in flight list up or down one when user clicks prev, next
function editPointer(offset) {
  var newPtr = fidPtr + offset;
  if(newPtr >= 0 && newPtr < fidList.length) {
    if(document.getElementById("b_add").disabled == false) {
      if(! confirm("Changes made to this flight have not been saved.  OK to discard them?")) {
	return;
      }
    }
    // Load new flight
    preEditFlight(fidList[newPtr], newPtr);
  }
}

// Load up parameters of a given flight
function preEditFlight(fid, idx) {
  fidPtr = idx;
  document.getElementById("b_prev").disabled = (fidPtr <= 0 ? true : false);
  document.getElementById("b_next").disabled = (fidPtr >= fidList.length - 1 ? true : false);
  document.getElementById("editflighttitle").innerHTML = "Edit flight " + (fidPtr+1) + " of " + fidList.length;
  xmlhttpPost(URL_FLIGHTS, fid, "EDIT");
}

function preCopyFlight(fid) {
  xmlhttpPost(URL_FLIGHTS, fid, "COPY");
}

// Load existing flight data into input form
function editFlight(str, param) {
  // Oops, no matches!?
  if(str == "") {
    closeInput();
    return;
  }

  if(getCurrentPane() != "input") {
    // EDIT -> edit, COPY -> add
    openInput(param);
  }

  // src_iata 0, src_apid 1, dst_iata 2, dst_apid 3, flight code 4, date 5, distance 6, duration 7, seat 8, seat_type 9, class 10, reason 11, fid 12, plane 13, registration 14, alid 15, note 16, trid 17, plid 18
  var col = str.split("\t");
  var form = document.forms['inputform'];
  form.number.value = col[4];
  form.src_date.value = col[5];
  form.distance.value = col[6];
  form.duration.value = col[7];
  form.seat.value = col[8];

  selectAirport(col[1]); // sets src_ap, src_apid
  selectAirport(col[3]); // sets dst_ap, dst_apid
  selectAirline(col[15], true); // sets airline, airline_id

  var seat_type = inputform.seat_type;
  seat_type.selectedIndex = 0; // default to unselected
  for(index = 0; index < seat_type.length; index++) {
    if(seat_type[index].value == col[9]) {
      seat_type.selectedIndex = index;
    }
  }
  var myClass = inputform.myClass;
  for(index = 0; index < myClass.length; index++) {
    if(myClass[index].value == col[10]) {
      myClass[index].checked = true;
      break;
    }
  }
  var reason = inputform.reason;
  for(index = 0; index < reason.length; index++) {
    if(reason[index].value == col[11]) {
      reason[index].checked = true;
      break;
    }
  }
  fid = col[12]; //stored until flight is saved or deleted

  $('plane').value = col[13]; 
  $('plane_id').value = col[18];

  form.registration.value = col[14];
  alid = col[15];
  form.note.value = col[16];
  trid = col[17];

  $('src_ap').style.color = '#000000';
  $('dst_ap').style.color = '#000000';
  $('airline').style.color = '#000000';
  $('plane').style.color = '#000000';

  // Don't allow saving until something is changed
  setInputAllowed(false);
  flightSelectBoxes();
}

// Populate select boxes in input flight and do other preparation for entering/editing flight
function preInputFlight(param) {
  openInput(param);

  if(document.forms['inputform'].src_date.value == "") {
    var today = new Date();
    var month = today.getMonth() + 1;
    var day = today.getDate();
    var year = today.getFullYear();
    document.forms['inputform'].src_date.value = year + "-" + month + "-" + day;
  }
  document.forms['inputform'].src_date.focus();

  // An existing entry will already have plane, airline, trip selected
  if(param == "EDIT" || param == "COPY") {
    flightSelectBoxes();
  }
}

// Select correct items in input form select boxes
function flightSelectBoxes() {
  var select = inputform.trips;
  select.selectedIndex = 0;
  for(index = 0; index < select.length; index++) {
    if(select[index].value == trid) {
      select.selectedIndex = index;
      break;
    }
  }
}

// User has edited a flight's contents
// if major is true, force a redraw later
function markAsChanged(major) {
  if(major) {
    majorEdit = true;
  }
  if(document.getElementById("b_add").disabled == true) {
    setInputAllowed(true);
    document.getElementById("input_status").innerHTML = '';
  } 
}

// Disable and re-enable submission while a) AJAX requests are pending, b) no changes have been made
// state={true,false} for enabled,disabled
function setInputAllowed(state) {
  if(state) {
    style = 'hidden';
    document.getElementById("b_add").disabled = false;
    document.getElementById("b_save").disabled = false;
  } else {
    style = 'visible';
    document.getElementById("b_add").disabled = true;
    document.getElementById("b_save").disabled = true;
  }
}

// If clear=true, then input form is cleared after successful entry
function submitFlight() {
  xmlhttpPost(URL_SUBMIT, null, "ADD");
}

function saveFlight() {
  xmlhttpPost(URL_SUBMIT, false, "EDIT");
}

// Delete current flight (fid)
function deleteFlight(id) {
  if(id) {
    fid = id;
  }
  if(confirm("Are you sure you want to delete this flight?")) {
    xmlhttpPost(URL_SUBMIT, false, "DELETE");
  } else {
    document.getElementById("input_status").innerHTML = "<B>Deleting flight cancelled.</B>";
  }
}

// Handle the "add new airports" buttons
function popNewAirport(type) {
  if(type) {
    input_toggle = type;
  }
  window.open('/html/apsearch.html', 'Airport', 'width=500,height=580,scrollbars=yes');
}

function addNewAirport(data, name) {
  if(input_toggle == "SRC") {
    $('src_ap').value = name;
    $('src_apid').value = data;
    selectNewAirport('src_ap');
  } else {
    $('dst_ap').value = name;
    $('dst_apid').value = data;
    selectNewAirport('dst_ap');
  }
}

// Handle the "add new airlines" buttons
function popNewAirline() {
  window.open('/html/alsearch.html', 'Airline', 'width=500,height=580,scrollbars=yes');
}

function addNewAirline(alid, name) {
  markAsChanged();

  // Check if airline was listed already
  if(selectAirline(alid, true)) {
    return;
  }

  // Nope, we need to add it to filter options
  var al_select = document.forms['filterform'].Airlines;
  var elOptNew = document.createElement('option');
  if (name.length > AIRLINE_MAXLEN) {
    // Three dots in a proportional font is about two chars...
    elOptNew.text = name.substring(0,maxlen - 2) + "...";
  } else {
    elOptNew.text = name;
  }
  elOptNew.value = alid + ";" + name;

  try {
    al_select.add(elOptNew, null); // standards compliant; doesn't work in IE
  }
  catch(ex) {
    al_select.add(elOptNew); // IE only
  }

  // And finally the input form
  $('airline').value = name;
  $('airline_id').value = alid;
}

//
// Inject apid into hidden src/dst_apid field after new airport is selected, and draw on map
//
function getSelectedSrcApid(text, li) {
  $('src_ap').style.color = '#000000';
  $('src_apid').value=li.id;
  selectNewAirport('src_ap');
  srcApidAC = true;
}
function getSelectedDstApid(text, li) {
  $('dst_ap').style.color = '#000000';
  $('dst_apid').value=li.id;
  selectNewAirport('dst_ap');
  dstApidAC = true;
}

//
// Inject plid into hidden plane_id field after new plane type is selected
//
function getSelectedAlid(text, li) {
  $('airline_id').value=li.id;
  $('airline').style.color = '#000000';
  airlineAC = true;
}

function getSelectedPlid(text, li) {
  $('plane_id').value=li.id;
}

//
// Handle the "add new/edit trip" buttons in input
// thisTrip can be "ADD" (new), "EDIT" (edit selected), or a numeric trip id (edit this)
//
function editTrip(thisTrip) {
  var url = '/html/trip.html';
  var trid = 0;
  if(thisTrip == "ADD") {
    // do nothing, we'll create a new trip
  } else {
    if(thisTrip == "EDIT") {
      var inputform = document.forms['inputform'];
      trid = inputform.trips[inputform.trips.selectedIndex].value;
    } else {
      trid = thisTrip;
    }
  }
  if(trid != 0) {
    url += "?trid=" + trid;
  }
  window.open(url, 'TripEditor', 'width=500,height=280,scrollbars=yes');
}

// User has added, edited or deleted trip, so punch it in
function newTrip(code, newTrid, name, url) {
  code = parseInt(code);

  // Trip deleted?  Switch filter back to "all trips" view
  if(code == CODE_DELETEOK) {
    var tr_select = document.forms['filterform'].Trips;
    tr_select.selectedIndex = 0;
  }

  // This only applies when new trip is added in flight editor
  var trips = document.forms['inputform'].trips;
  if(trips) {
    switch(code) {
    case CODE_ADDOK:
      trips.reselect = true; // means recalculate on refresh
      break;

    case CODE_DELETEOK:
      trips.selectedIndex = 0;
      break;

    default: // EDIT
      trips[trips.selectedIndex].text = name;
      break;
    }
  }
  // In all cases, refresh map
  // TODO: Would be enough to refresh the filter only...
  refresh(true);
  markAsChanged();
}

// When user has manually entered an airport code, try to match it to DB
function airportCodeToAirport(type) {

  // Ignore autocomplete results
  if(type == "src_ap") {
    if(srcApidAC == true) {
      srcApidAC = false;
      return;
    }
    input_toggle = "SRC";
  }
  if(type == "dst_ap") {
    if(dstApidAC == true) {
      dstApidAC = false;
      return;
    }
    input_toggle = "DST";    
  }

  // Try to match against existing airports
  var code = $(type).value.toUpperCase();
  var markers = airportLayer.markers;
  var found = false;
  for(m = 0; m < markers.length; m++) {
    if(markers[m].code == code) {
      markers[m].events.triggerEvent("mousedown");
      found = true;
      break;
    }
  }

  // If not found, dig up from DB
  if (!found) {
    xmlhttpPost(URL_GETCODE, code, type);
  }
  markAsChanged(true);
}

// User has entered invalid input: clear apid, turn field red (unless empty) and remove marker
function invalidateAirport(type) {
  if($(type).value != "") {
    $(type).style.color = '#FF0000';
  }
  $(type + 'id').value = 0;
  selectNewAirport(type);
}

// When user has entered flight number, try to match it to airline
function flightNumberToAirline(type) {
  if(type == "NUMBER") {
    var flightNumber = document.forms['inputform'].number.value.toUpperCase();
    document.forms['inputform'].number.value = flightNumber;
    if(flightNumber.length >= 2) {
      var found = false;
      var re_iata = /^[a-zA-Z0-9][a-zA-Z0-9][ 0-9]/; // XX N...
      var re_icao = /^[a-zA-Z][a-zA-Z][a-zA-Z][ 0-9]$/;  // XXX N...
      if(flightNumber.length == 2 || re_iata.test(flightNumber.substring(0,3))) {
	var airlineCode = flightNumber.substring(0, 2);
      } else if(flightNumber.length == 3 || re_icao.test(flightNumber.substring(0,4))) {
	var airlineCode = flightNumber.substring(0, 3);
      }
    }
  } else {
    // Ignore autocompleted results
    if(airlineAC == true) {
      airlineAC = false;
      return;
    }
    airlineCode = $('airline').value;
  }

  // We've found something that looks like an airline code, so overwrite it into AIRLINE field
  if(airlineCode) {
    xmlhttpPost(URL_GETCODE, airlineCode, "airline");
  }
  markAsChanged();
}

// Add a temporary source or destination marker over currently selected airport
// Also calculates distance and duration (unless "quick" is true)
// type: "src_ap" or "dst_ap"
function selectNewAirport(type, quick) {
  var size = new OpenLayers.Size(17, 17);
  var offset = new OpenLayers.Pixel(-(size.w/2), -(size.h/2));
  if(type == "src_ap") {
    var data = $('src_apid').value.split(":");
    var icon = new OpenLayers.Icon('/img/icon_plane-src.png',size,offset);
  } else {
    var data = $('dst_apid').value.split(":");
    var icon = new OpenLayers.Icon('/img/icon_plane-dst.png',size,offset);
  }

  var iata = data[0];
  var apid = data[1];
  var x = data[2];
  var y = data[3];

  if(apid > 0) {
    var lonlat = new OpenLayers.LonLat(x, y);
    var marker = new OpenLayers.Marker(lonlat, icon);
    airportLayer.addMarker(marker);
  }
  if(type == "src_ap") {
    if(input_srcmarker) {
      airportLayer.removeMarker(input_srcmarker);
    }
    if(apid > 0) {
      input_srcmarker = marker;
      input_toggle = "DST";
    } else {
      input_srcmarker = null;
    }
  } else {
    if(input_dstmarker) {
      airportLayer.removeMarker(input_dstmarker);
    }
    if(apid > 0) {
      input_dstmarker = marker;
      input_toggle = "SRC";
    } else {
      input_dstmarker = null;
    }
  }

  // Two airports defined, calculate distance and duration
  if(! quick) {
    if(input_dstmarker && input_srcmarker) {
      var src_ap_data = $('src_apid').value.split(":");
      var lon1 = src_ap_data[2];
      var lat1 = src_ap_data[3];
      var dst_ap_data = $('dst_apid').value.split(":");
      var lon2 = dst_ap_data[2];
      var lat2 = dst_ap_data[3];
      distance = gcDistance(lat1, lon1, lat2, lon2);
      
      var rawtime = Math.floor(30 + (distance / 500) * 60);
      var hours = Math.floor(rawtime/60);
      var mins = rawtime % 60;
      if(mins < 10) mins = "0" + mins;
      duration = hours + ":" + mins;
    } else {
      distance = "-";
      duration = "-";
    }
    document.forms['inputform'].distance.value = distance;
    document.forms['inputform'].duration.value = duration;

    // Flag change of airport as major change
    markAsChanged(true);
  }
}

// Swap airports around
// If "true" (manual), swap both
// If "false" (automatic), swap only top to bottom and restore top to original
function swapAirports(manual) {
  if(manual) {
    srcName = $('src_ap').value;
    srcData = $('src_apid').value;
  }

  // Clone SRC from DST
  $('src_ap').value = $('dst_ap').value;
  $('src_apid').value = $('dst_apid').value;

  if(manual) {
    // Clone DST from SRC
    $('dst_ap').value = srcName;
    $('dst_apid').value = srcData;
  } else {
    // Clear out DST
    $('dst_ap').value = "";
    $('dst_apid').value = "";
  }

  // Redraw markers and airline codes
  selectNewAirport("src_ap", true);
  selectNewAirport("dst_ap", true);
  if(manual) {
    markAsChanged();
  }
}

// Given apid, find the matching airport and pop it up
function selectAirport(apid) {
  var markers = airportLayer.markers;
  var found = false;
  for(m = 0; m < markers.length; m++) {
    if(markers[m].apid == apid) {
      markers[m].events.triggerEvent("mousedown");
      found = true;
      break;
    }
  }
  if (!found) {
    if(confirm("This airport is currently filtered out. Clear filter?")) {
      clearFilter(false);
    }
  }
}

// Given alid, find it in filter
// if edit is true, set it in editor, else set in map (filter)
// return true if found, false if not
function selectAirline(new_alid, edit) {
  var al_select = document.forms['filterform'].Airlines;
  for(index = 0; index < al_select.length; index++) {
    if(al_select[index].value.split(";")[0] == new_alid) {
      if(edit) {
	$('airline_id').value = new_alid;
	$('airline').value = al_select[index].value.split(";")[1];
	$('airline').style.color = "#000";
      } else {
	al_select.selectedIndex = index;
      }
      return true;
    }
  }
  return false;
}

//
// Context help
//
function help(context) {
  window.open('/help/' + context + '.html', 'Help', 'width=500,height=400,scrollbars=yes');
}

//
// Register new account
//
function signUp() {
  var regWindow = window.open('/html/signup.html', 'CreateNewAccount', 'width=500,height=500,scrollbars=yes');
  if (!regWindow) {
    alert("Oops, your browser seems to be blocking the sign up window?  Please disable your popup blocker for this site and try again.");
  }
}

//
// Import flights
//
function openImport() {
  window.open('/html/import.html', 'Import', 'width=800,height=600,scrollbars=yes');
}

//
// Change settings
//
function settings() {
  window.open('/html/settings.html', 'ChangeSettings', 'width=500,height=450,scrollbars=yes');
}

//
// Login and logout
//
function login(str, param) {
  var status = str.split(";")[0];
  var name = str.split(";")[1];
  document.getElementById("loginstatus").style.display = 'inline';
  // Login successful
  if(status == "1") {
    logged_in = true;
    document.getElementById("loginstatus").innerHTML = "Welcome, <B>" + name + "</B>!";
    document.getElementById("loginform").style.display = 'none';
    document.getElementById("controlpanel").style.display = 'inline';
    if(param != "NEWUSER") {
      closeNews();
    }
    clearStack();
    clearFilter(true);

  } else {
    logged_in = false;
    document.getElementById("loginstatus").innerHTML = "<B>" + name + "</B>";
  }
}

// Called by signup.js when a new user has successfully registered
function newUserLogin(name, pw) {
  document.forms['login'].name.value = name;
  document.forms['login'].pw.value = pw;
  document.getElementById("news").innerHTML =
    "<img src='/img/close.gif' height=17 width=17 onClick='JavaScript:closeNews()'> " + 
    "<B>Welcome to OpenFlights!</b>  Click on <input type='button' value='New flight' align='middle' onclick='JavaScript:preInputFlight(\"ADD\")'> to start adding flights,<br>or on <input type='button' value='Import' align='middle' onclick='JavaScript:openImport()'> to load in existing flights from sites like FlightMemory.";
  xmlhttpPost(URL_LOGIN, 0, "NEWUSER");
}

function logout(str) {
  document.getElementById("loginstatus").innerHTML = "<B>You have been logged out.</B>";
  document.getElementById("loginform").style.display = 'inline';
  document.getElementById("controlpanel").style.display = 'none';
  clearStack();
  clearFilter(true);
  document.forms['login'].name.focus();
}

// Functions for swapping between lower panes
// Possible panes: 'ad', 'result', 'input', 'help'

function getCurrentPane() {
  return paneStack[paneStack.length-1];
}

// Open a new pane
// If the pane is open already, do nothing
function openPane(newPane) {
  if(paneStack.length > 0) {
    var currentPane = getCurrentPane();
    if(currentPane == newPane) return;
    document.getElementById(currentPane).style.display = 'none';
  }
  document.getElementById(newPane).style.display = 'inline';
  paneStack.push(newPane);
}

// Close current pane
// If the current pane is the last one, do nothing
function closePane() {
  if(paneStack.length == 1) return;

  var currentPane = paneStack.pop();
  var lastPane = getCurrentPane();
  if(currentPane == "input") {
    input = false;
    if(input_srcmarker) airportLayer.removeMarker(input_srcmarker);
    if(input_dstmarker) airportLayer.removeMarker(input_dstmarker);
    document.getElementById("newairport").style.display = 'none';
  }
  if(currentPane == "result") {
    apid = 0;
  }
  document.getElementById(currentPane).style.display = 'none';
  document.getElementById(lastPane).style.display = 'inline';

  // If ad pane is now displayed, refresh it
  if(paneStack.length == 1) refreshAd();
}

// Clear all panes until the base pane (ad)
function clearStack() {
  while(paneStack.length > 1) {
    closePane();
  }
}

function openInput(param) {
  openPane("input");
  document.getElementById("newairport").style.display = 'inline';
  if(param == "EDIT") {
    document.getElementById("addflighttitle").style.display = 'none';
    document.getElementById("addflightbuttons").style.display = 'none';
    document.getElementById("editflighttitle").style.display = 'inline';
    document.getElementById("editflightbuttons").style.display = 'inline';
  } else { // ADD, COPY
    document.getElementById("addflighttitle").style.display = 'inline';
    document.getElementById("addflightbuttons").style.display = 'inline';
    document.getElementById("editflighttitle").style.display = 'none';
    document.getElementById("editflightbuttons").style.display = 'none';
  }
  input = true;
  input_toggle = "SRC";
  document.getElementById("input_status").innerHTML = "";

  // Don't allow saving until something is changed
  setInputAllowed(false);
}

function closeInput() {
  if(document.getElementById("b_add").disabled == false) {
    if(! confirm("Changes made to this flight have not been saved.  OK to discard them?")) {
      return;
    }
  }
  closePane();

  // Reload flights list if we were editing flights, or
  // user had a result pane open when he opened new flight editor

  if(getCurrentPane() == "result" &&
     (document.getElementById("editflighttitle").style.display == 'inline' ||
      document.getElementById("addflighttitle").style.display == 'inline')) {
    xmlhttpPost(URL_FLIGHTS, 0, "RELOAD");
  }
}

// Clear out (restore to defaults) the input box
function clearInput() {
  var form = document.forms["inputform"];
  var today = new Date();
  form.src_date.value = today.getFullYear()+ "-" + (today.getMonth() + 1) + "-" + today.getDate();
  form.src_date.focus();
  form.src_ap.value = "";
  form.src_apid.value = 0;
  form.dst_ap.value = "";
  form.dst_apid.value = 0;
  form.duration.value = "-";
  form.distance.value = "-";
  form.number.value = "";
  form.airline.value = "";
  form.airline_id.value = 0;
  form.seat.value = "";
  form.plane.value = "";
  form.plane_id.value = "";
  form.registration.value = "";
  form.note.value = "";
  if(input_srcmarker) {
    airportLayer.removeMarker(input_srcmarker);
    input_srcmarker = null;
  }
  if(input_dstmarker) {
    airportLayer.removeMarker(input_dstmarker);
    input_dstmarker = null;
  }
  $('src_ap').style.color = '#000000';
  $('dst_ap').style.color = '#000000';
  $('airline').style.color = '#000000';
  $('plane').style.color = '#000000';

  srcApidAC = false;
  dstApidAC = false;
  airlineAC = false;
  resetHintTextboxes();
}

function showHelp() {
  if(getCurrentPane() == "help") return;
  openPane("help");
}

function closePopup() {
  // close any previous popups
  if(currentPopup && currentPopup != this.popup) {
    currentPopup.hide();
    currentPopup = null;
  }
}

function closeNews() {
  document.getElementById("news").style.display = 'none';
}

// refresh_all: false = only flights, true = reload everything
function clearFilter(refresh_all) {

  // Do not allow trip filter to be cleared if it's set in URL
  if(parseArgument("trip") == 0) {
    var tr_select = document.forms['filterform'].Trips;
    tr_select.selectedIndex = 0;
  }
  var year_select = document.forms['filterform'].Years;
  year_select.selectedIndex = 0;
  selectAirline(0);
  refresh(refresh_all);
}

// Refresh user's display after change in filter
// init = true: reloads all user data
// init = false: loads flight data and stats only
function refresh(init) {
  closePopup();
  apid = 0;
  xmlhttpPost(URL_MAP, 0, init);
}

// Filter out airports based on current map zoom level
// Current rule: on level 0 only, filter out airports with <2 flights if user has over 200 flights
function zoomFilter(count) {
  if(count <= 2 && flightTotal > 200 && map.getZoom() == 0) {
    return false;
  } else {
    return true;
  }
}

// Zoom level has been changed, toggle markers on/off
function zoomEvent(event) {
  var markers = airportLayer.markers;
  for(m = 0; m < markers.length; m++) {
    markers[m].display(zoomFilter(markers[m].count));
  }
}

/* Refresh the Google Ad iframe
 * TODO: How to make the second ad refresh?
 */
function refreshAd() {
  var d=document.getElementById('ad');
  if(d){
    var s=d.getElementsByTagName('iframe');
    if(s && s.length){
      var src = (s[0].src.split(/&xtime=/))[0];
      s[0].src = src + '&xtime='+new Date().getTime();
    }
  }
  return true;
}
