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
var privacy = "Y";

// Current list of flights
var fidList, fidPtr = 0, fid = 0;
// Query and description of current list
var lastQuery, lastDesc;

// Original (pre-input) select tags for airports, airlines
var origSrcSelect, origDstSelect, origAirlineSelect;
// Temporary variables for current flight being edited
var alid = 0, plane;
var input = false, logged_in = false, initializing = true;
var input_srcmarker, input_dstmarker, input_toggle;
var majorEdit = false;

var URL_FLIGHTS = "/php/flights.php";
var URL_GETCODE = "/php/getcode.php";
var URL_LOGIN = "/php/login.php";
var URL_LOGOUT = "/php/logout.php";
var URL_MAP = "/php/map.php";
var URL_PREINPUT = "/php/preinput.php";
var URL_STATS = "/php/stats.php";
var URL_SUBMIT = "/php/submit.php";
var URL_TOP10 = "/php/top10.php";

var CODE_FAIL = 0;
var CODE_ADDOK = 1;
var CODE_EDITOK = 2;
var CODE_ADDOKPLANE = 11;
var CODE_EDITOKPLANE = 12;
var CODE_DELETEOK = 100;

var INPUT_MAXLEN = 50;

var airportMaxFlights = 0;
var airportIcons = [ [ '/img/icon_plane-13x13.png', 13 ],
                     [ '/img/icon_plane-15x15.png', 15 ],
		     [ '/img/icon_plane-17x17.png', 17 ],
		     [ '/img/icon_plane-19x19b.png', 19 ],
		     [ '/img/icon_plane-19x19b.png', 19 ],
		     [ '/img/icon_plane-19x19.png', 19 ] ];

var classes = {"Y":"Economy", "P":"Prem.Eco", "C":"Business", "F":"First", "": ""};
var seattypes = {"W":"Window", "A":"Aisle", "M":"Middle", "": ""};
var reasons = {"B":"Business", "L":"Leisure", "C":"Crew", "": ""};
var classes_short = {"Y":"Eco", "P":"PrE", "C":"Biz", "F":"1st", "": ""};
var seattypes_short = {"W":"Win", "A":"Ais", "M":"Mid", "": ""};
var reasons_short = {"B":"Wrk", "L":"Fun", "C":"Crw", "": ""};

window.onload = function init(){

  var bounds = new OpenLayers.Bounds(-180, -90, 180, 90);
  map = new OpenLayers.Map('map', {
    maxExtent: bounds,
			       maxResolution: "auto",
			       maxZoomLevel: 8,
			       controls: [
					  new OpenLayers.Control.PanZoom(),
					  new OpenLayers.Control.NavToolbar(),
					  new OpenLayers.Control.LayerSwitcher({'ascending':false}),
					  new OpenLayers.Control.ScaleLine(),
					  //new OpenLayers.Control.MouseToolbar(),
					  //new OpenLayers.Control.Permalink('permalink'),
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

  //map.setCenter(new OpenLayers.LonLat(0, 0), 0);
  map.zoomToMaxExtent();

  // Extract any arguments from URL
  filter_trid = parseArgument("trip");
  filter_user = parseArgument("user");

  // Are viewing another user's flights or trip?
  if(filter_user != "0" || filter_trid != 0) {
    document.getElementById("loginform").style.display = 'none';
    document.getElementById("loginstatus").style.display = 'inline';
    document.getElementById("news").style.display = 'none';
    if(filter_trid != 0) {
      document.getElementById("filter_tripselect").style.display = 'none';
    }
  }

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

function drawAirport(airportLayer, apid, x, y, name, code, city, country, count) {
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
    alert("Color error: " + name + ":" + colorIndex);
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
  feature.apid = apid;
  feature.iata = code;
  
  // Run when the user clicks on an airport marker
  // this == the feature, *not* the marker
  var markerClick = function (evt) {
    closePopup();
    
    // If input mode is active, we select the airport instead of popping it up
    if(input) {
      if(input_toggle == "SRC") {
	var ap_select = document.forms['inputform'].src_ap;
	document.forms['inputform'].src_ap_code.value = this.iata;
      } else {
	var ap_select = document.forms['inputform'].dst_ap;
	document.forms['inputform'].dst_ap_code.value = this.iata;
      }
      codeToAirport(input_toggle);
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
	if(param == "EDIT" || param == "COPY") {
	  editFlight(self.xmlHttpReq.responseText, param);
	} else {
	  if(param == "RELOAD") param = lastDesc;
	  // param contains previously escaped semi-random HTML title
	  listFlights(self.xmlHttpReq.responseText, unescape(param));
	}
      }
      if(strURL == URL_GETCODE) {
	updateCodes(self.xmlHttpReq.responseText);
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
	  }
	  updateTitle();
	} 
      }
      if(strURL == URL_PREINPUT) {
	preInputFlight(self.xmlHttpReq.responseText, param);
      }
      if(strURL == URL_STATS) {
	showStats(self.xmlHttpReq.responseText);
      }
      if(strURL == URL_TOP10) {
	showTop10(self.xmlHttpReq.responseText);
      }
      if(strURL == URL_SUBMIT) {
	var code = self.xmlHttpReq.responseText.split(";")[0];
	var text = self.xmlHttpReq.responseText.split(";")[1];
	document.getElementById("input_status").innerHTML = '<B>' + text + '</B>';

	// A change that affected the map was made, so redraw
	if(majorEdit || CODE_DELETEOK) {
	  refresh(false);
	}
	majorEdit = false;

	// We've added a new plane, so rebuild selects
	if(code == CODE_ADDOKPLANE || code == CODE_EDITOKPLANE) {
	  setTimeout('xmlhttpPost(URL_PREINPUT)', 1000);
	}

	if(code % 10 == CODE_EDITOK || code % 10 == CODE_ADDOK) {
	  setInputAllowed(type, false);
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
	      
	      // Edit next if you can -- but with delay, since deleting a flight causes refresh
	      if(fidPtr < fidList.length) {
		setTimeout('editPointer(0)', 1000);
	      } else {
		// Move back
		setTimeout('editPointer(-1)', 1000);
	      }
	    }
	  } else {
	    setTimeout('xmlhttpPost(URL_FLIGHTS, 0, "RELOAD")', 1000);
	  }
	}

	// If id == true and operation succeeded, then clear input
	if(id && code != CODE_FAIL) {
	  setTimeout('clearInput()', 1000);
	}
      }
      document.getElementById("ajaxstatus").style.display = 'none';
    }
  }
  // End result processing

  // Start query string generation
  switch(strURL) {
  case URL_GETCODE:
    var form = document.forms['inputform'];
    var src = form.src_ap_code.value;
    var dst = form.dst_ap_code.value;
    var flightNumber = form.number.value;
    var airlineCode = form.airline_code.value;
    if(param == "SRC" && src) {
      query = 'src=' + escape(src);
    }
    if(param == "DST" && dst) {
      query = 'dst=' + escape(dst);
    }
    if(param == "AIRLINE" && airlineCode) {
      query = 'airline=' + escape(airlineCode);
    }
    setInputAllowed(param, false);
    break;

  case URL_SUBMIT:
    var inputform = document.forms['inputform'];

    // Deleting needs only the fid, and can be run without the inputform
    if(param != "DELETE") {
      var src_date = inputform.src_date.value;
      var re_date = /^[0-9]{2,4}[-/.]?[0-9]{1,2}[-/.]?[0-9]{1,2}$/
      if(! re_date.test(src_date)) {
	alert("Please enter a full date in year/month/date order. Valid formats include YYYY-MM-DD, YYYY/MM/DD, YYYY.MM.DD, YYYYMMDD and YYMMDD.");
	document.forms['inputform'].src_date.focus();
	return;
      }

      var src_apid = inputform.src_ap[inputform.src_ap.selectedIndex].value.split(":")[1];
      if(! src_apid || src_apid == 0) {
	alert("Please select a source airport.");
	document.forms['inputform'].src_ap.focus();
	return;
      }
      var dst_apid = inputform.dst_ap[inputform.dst_ap.selectedIndex].value.split(":")[1];
      if(! dst_apid || dst_apid == 0) {
	alert("Please select a destination airport.");
	document.forms['inputform'].dst_ap.focus();
	return;
      }
      var alid = inputform.airline[inputform.airline.selectedIndex].value.split(":")[0];
      if(! alid || alid == 0) {
	alert("Please select an airline.");
	document.forms['inputform'].airline.focus();
	return;
      }
      var type = inputform.seat_type.value;
      if(type == "-") type = "";
      var myClass = radioValue(inputform.myClass);
      var reason = radioValue(inputform.reason);
      var plid = inputform.plane[inputform.plane.selectedIndex].value;
      if(plid == 0) plid = "NULL";
      var trid = inputform.trips[inputform.trips.selectedIndex].value;
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
      'plid=' + escape(plid) + '&' +
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

  case URL_LOGOUT:
    // no parameters needed
    break;

  case URL_PREINPUT:
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
      filter_trid = form.Trips.value;
    }
    filter_alid = form.Airlines.value;
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
function updateFilter(str) {
  var master = str.split("\n");
  var trips = master[3];
  var airlines = master[4];
  var years = master[5];

  var tripselect = createSelect("Trips", "All trips", filter_trid, trips.split("\t"), 20);
  document.getElementById("filter_tripselect").innerHTML = tripselect;
  var airlineselect = createSelect("Airlines", "All airlines", filter_alid, airlines.split("\t"), 20);
  document.getElementById("filter_airlineselect").innerHTML = airlineselect;
  var yearselect = createSelect("Years", "All", filter_year, years.split("\t"), 20);
  document.getElementById("filter_yearselect").innerHTML = yearselect;

}


// Refresh current map title
function updateTitle(str) {
  var form = document.forms['filterform'];
  var text = "";
  var airline = form.Airlines[form.Airlines.selectedIndex].text;
  var trip = form.Trips[form.Trips.selectedIndex].text;
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
  var select = "<select name=\"" + selectName + "\"";
  if(hook) {
    select += " onChange='JavaScript:" + hook + "(\"" + selectName + "\")'";
  }
  if(tabIndex) {
    select += " tabindex=\"" + tabIndex + "\"";
  }
  select += "><option value=\"0\">" + allopts + "</option>";

  // No data?  Return an empty element
  if(! rows || rows == "") {
    return select + "</select>";
  }

  var selected = "";
  for (r in rows) {
    var col = rows[r].split(";");
    var name = col[1];
    var url = col[2];
    if(col[0].split(':')[0] == id) {
      selected = " SELECTED";
      // Special case: un-truncated trip name and URL
      if(selectName == "Trips") {
	tripname = name;
	tripurl = url;
      }
    } else {
      selected = "";
    }
    if (maxlen && maxlen > 0 && name.length > maxlen) {
      name = name.substring(0,maxlen - 3) + "...";
    }
    select += "<option value=\"" + col[0] + "\"" + selected + ">" + name + "</option>";
  }
  select += "</select>";
  return select;
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
  
  var col = stats.split(",");
  var duration = col[2]; // minutes
  var days = Math.floor(col[2] / (60*24));
  var hours = Math.floor((col[2] / 60) % 24);
  var min = Math.floor(col[2] % 60);
  stats = col[0] + " flights<br>" +
    col[1] + " mi flown<br>" +
    days + " days " + hours + ":" + min;
  document.getElementById("stats").innerHTML = stats;
  privacy = col[3];

  // New user with no flights?  Then don't even try to draw
  if(col[0] == "0") {
    return;
  }

  var rows = flights.split(":");
  for (r in rows) {
    var col = rows[r].split(",");
    // apid1 0, x1 1, y1 2, apid2 3, x2 4, y2 5, count 6, distance 7
    drawLine(parseFloat(col[1]), parseFloat(col[2]), parseFloat(col[4]), parseFloat(col[5]), col[6], col[7]);
  }
  
  var rows = airports.split(":");

  // Airports are ordered from least busy to busiest, so we calibrate the color scale based on the last result
  airportMaxFlights = rows[rows.length - 1].split(",")[7];
  for (r in rows) {
    var col = rows[r].split(",");
    // apid, x, y, name, code, city, country, count
    drawAirport(airportLayer, col[0], col[1], col[2], col[3], col[4], col[5], col[6], col[7]);
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
    table += "<i>No flights found at this airport.</i>";
  } else {
    if(desc) {
      table += desc.replace(/\<br\>/g, " &mdash; ");
    }
    table += "<table class=\"sortable\" id=\"apttable\" cellpadding=\"0\" cellspacing=\"0\">";
    table += "<tr><th>From</th><th>To</th><th>Flight</th><th>Date</th><th class=\"sorttable_numeric\">Miles</th><th>Time</th><th>Plane</th><th>Seat</th><th>Class</th><th>Reason</th><th>Trip</th><th>Note</th>";
    if(logged_in) {
      table += "<th class=\"unsortable\">Action</th>";
    }
    table += "</tr>";
    var rows = str.split("\t");
    for (r in rows) {
      // src_iata 0, src_apid 1, dst_iata 2, dst_apid 3, flight code 4, date 5, distance 6, duration 7, seat 8, seat_type 9, class 10, reason 11, fid 12, plane 13, registration 14, alid 15, note 16, trid 17
      var col = rows[r].split(",");
      var trip = col[17];
      var seat = col[8] + " " + seattypes[col[9]];
      var plane = col[13];
      var fid = col[12];
      if(col[14] != "") {
	plane += " (" + col[14] + ")";
      }
      if(logged_in && trip != "") {
	trip = "<a href=\"#\" onclick=\"JavaScript:editTrip(" + trip + ");\">" + trip + "</a>";
      }
      table += "<tr><td><a href=\"#\" onclick=\"JavaScript:selectAirport(" + col[1] + ");\">" + col[0] + "</a></td>" +
	"<td><a href=\"#\" onclick=\"JavaScript:selectAirport(" + col[3] + ");\">" + col[2] + "</a></td>" +
	"<td>" + col[4] + "</td><td>" + col[5] + "</td><td>" + col[6] + "</td><td>" + col[7] +
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
    for (r in rows) {
      var col = rows[r].split(",");
      // desc 0, distance 1, duration 2, s.iata 3, s.apid 4, d.iata 5, d.apid 6
      table += "<tr><td>" + col[0] + "</td><td><a href=\"#\" onclick=\"JavaScript:selectAirport(" + col[4] + ");\">" + col[3] + "</a>&harr;<a href=\"#\" onclick=\"JavaScript:selectAirport(" + col[6] + ");\">" + col[5] + "</a>, " + col[1] + " mi, " + col[2] + "</td></tr>";
    }
    table += "<tr><td>&nbsp;</td></tr>";
    table += "<tr><td>&nbsp;</td></tr>";
    table += "<tr><th colspan=2>Airport records</th></tr>";
    var rows = extremes.split(":");
    for (r in rows) {
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
    for (r in rows) {
      var col = rows[r].split(",");
      classPie.add(classes_short[col[0]], parseInt(col[1]));
    }
    table += "</td><td>";

    table += "<div id='reasonPie' style='position:relative;height:80px;width:150px;'></div>";
    var rows = reasonData.split(":");
    for (r in rows) {
      var col = rows[r].split(",");
      reasonPie.add(reasons_short[col[0]], parseInt(col[1]));
    }
    table += "</td></tr>";
    table += "<tr><th>Seats</th></tr><tr><td>";
    table += "<div id='seatPie' style='position:relative;height:80px;width:150px;'></div>";
    var rows = seatData.split(":");
    for (r in rows) {
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
    for (r in rows) {
      var col = rows[r].split(",");
      // s.name, s.apid, d.name, d.apid, count
      table += "<tr><td><a href=\"#\" onclick=\"JavaScript:selectAirport(" + col[1] + ");\">" + col[0] + "</a>&harr;" +
	"<a href=\"#\" onclick=\"JavaScript:selectAirport(" + col[3] + ");\">" + col[2] + "</a></td><td>"
	+ col[4] + "</td></tr>";
    }
    table += "</table>";
    bigtable += table + "</td><td style=\"vertical-align: top\">";

    table = "<table style=\"border-spacing: 10px 0px\">";
    table += "<tr><th colspan=3>Top 10 Airports</th></tr>";
    var rows = airports.split(":");
    for (r in rows) {
      var col = rows[r].split(",");
      // name, iata, count, apid
      desc = col[0] + " (" + col[1] + ")";
      table += "<tr><td><a href=\"#\" onclick=\"JavaScript:selectAirport(" + col[3] + ");\">" + desc + "</a></td><td>" + col[2] + "</td>";
    }
    table += "</table>";
    bigtable += table + "</td><td style=\"vertical-align: top\">";
    
    table = "<table style=\"border-spacing: 10px 0px\">";
    table += "<tr><th colspan=3>Top 10 Airlines</th></tr>";
    var rows = airlines.split(":");
    for (r in rows) {
      var col = rows[r].split(",");
      // name, count, apid
      table += "<tr><td><a href=\"#\" onclick=\"JavaScript:selectAirline(" + col[2] + ");refresh(false);\">" + col[0] + "</a></td><td>" + col[1] + "</td>";
    }
    table += "</table>";
    bigtable += table + "</td><td style=\"vertical-align: top\">";
    
    table = "<table style=\"border-spacing: 10px 0px\">";
    table += "<tr><th colspan=3>Top 10 Planes</th></tr>"
      var rows = planes.split(":");
    for (r in rows) {
      var col = rows[r].split(",");
      // name, count
      table += "<tr><td>" + col[0] + "</td><td>" + col[1] + "</td>";
    }
    table += "</table>";
    bigtable += table + "</td></table>";
    
  }
  document.getElementById("result").innerHTML = bigtable;
}

function updateCodes(str) {
  var lines = str.split("\n");
  var select;
  var type = lines[0];
  if(type == "SRC") {
    select = document.forms['inputform'].src_ap;
  }
  if(type == "DST") {
    select = document.forms['inputform'].dst_ap;
  }
  if(type == "AIRLINE") {
    select = document.forms['inputform'].airline;
  }
  if(select) {
    select.options.length = lines.length - 2; // redimension select
    select.selectedIndex = 0;

    for(l in lines) {
      if(l == 0 || l == lines.length - 1) continue; // already processed
      var col = lines[l].split(";");
      var name = col[1];
      if (name.length > INPUT_MAXLEN) {
	name = name.substring(0,INPUT_MAXLEN - 3) + "...";
      }
      select[l-1].value = col[0]; // id
      select[l-1].text = name;
    }

    // Rebuilding select doesn't count as onChange, so we trigger manually
    if(type == "SRC") selectNewAirport("src_ap");
    if(type == "DST") selectNewAirport("dst_ap");

    // Stop AJAX twirly
    setInputAllowed(type, true);
  }
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
    // Restore any changes to selects
    document.getElementById("input_src_ap_select").innerHTML = origSrcSelect;
    document.getElementById("input_dst_ap_select").innerHTML = origDstSelect;
    document.getElementById("input_airline_select").innerHTML = origAirlineSelect;

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

  // src_iata 0, src_apid 1, dst_iata 2, dst_apid 3, flight code 4, date 5, distance 6, duration 7, seat 8, seat_type 9, class 10, reason 11, fid 12, plane 13, registration 14, alid 15, note 16
  var col = str.split(",");
  var form = document.forms['inputform'];
  form.src_ap_code.value = col[0];
  form.src_apid.value = col[1];
  form.dst_ap_code.value = col[2];
  form.dst_apid.value = col[3];
  form.number.value = col[4];
  form.src_date.value = col[5];
  form.distance.value = col[6];
  form.duration.value = col[7];
  form.seat.value = col[8];

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
  plane = col[13]; 
  form.registration.value = col[14];
  alid = col[15];
  form.note.value = col[16];
  trid = col[17];

  // Don't allow saving until something is changed
  setInputAllowed(null, false);

  // If edit pane is *not* yet open, load and populate select boxes
  // param can be "EDIT" or "COPY"
  if(getCurrentPane() == "input") {
    flightSelectBoxes();
  } else {
    xmlhttpPost(URL_PREINPUT, 0, param);
  }
    
}

// Populate select boxes in input flight and do other preparation for entering/editing flight
function preInputFlight(str, param) {
  openInput(param);

  if(document.forms['inputform'].src_date.value == "") {
    var today = new Date();
    var month = today.getMonth() + 1;
    var day = today.getDate();
    var year = today.getFullYear();
    document.forms['inputform'].src_date.value = year + "-" + month + "-" + day;
  }
  document.forms['inputform'].src_date.focus();

  var master = str.split("\n");
  var airports = master[0];
  var airlines = master[1];
  var planes = master[2];
  var trips = master[3];

  origSrcSelect = createSelect("src_ap", "Choose airport", 0, airports.split("\t"), INPUT_MAXLEN, "selectNewAirport");
  document.getElementById("input_src_ap_select").innerHTML = origSrcSelect;

  origDstSelect = createSelect("dst_ap", "Choose airport", 0, airports.split("\t"), INPUT_MAXLEN, "selectNewAirport");
  document.getElementById("input_dst_ap_select").innerHTML = origDstSelect;

  origAirlineSelect = createSelect("airline", "Choose airline", filter_alid, airlines.split("\t"), INPUT_MAXLEN, "markAsChanged");
  document.getElementById("input_airline_select").innerHTML = origAirlineSelect;

  var tripselect = createSelect("trips", "-", filter_trid, trips.split("\t"), INPUT_MAXLEN, "markAsChanged", 8);
  document.getElementById("input_trip_select").innerHTML = tripselect;

  var planeselect = createSelect("plane", "-", 0, planes.split("\t"), INPUT_MAXLEN, "markAsChanged", 9);
  document.getElementById("input_plane_select").innerHTML = planeselect;

  // An existing entry will already have plane, airline, trip selected
  if(param == "EDIT" || param == "COPY") {
    flightSelectBoxes();
  } else {
    // Load up any values already entered into the form
    if(document.forms['inputform'].src_ap_code.value != "") codeToAirport("SRC");
    if(document.forms['inputform'].dst_ap_code.value != "") codeToAirport("DST");

    if(document.forms['inputform'].airline_code.value != "") {
      flightNumberToAirline("AIRLINE");
    } else if(document.forms['inputform'].number.value != "") {
      flightNumberToAirline("NUMBER");
    }
  }
}

// Select correct items in input form select boxes
function flightSelectBoxes() {
  codeToAirport("SRC", document.forms['inputform'].src_apid.value, true);
  codeToAirport("DST", document.forms['inputform'].dst_apid.value, true);
  
  var select = inputform.plane;
  select.selectedIndex = 0;
  for(index = 0; index < select.length; index++) {
    if(select[index].text == plane) {
      select.selectedIndex = index;
      break;
    }
  }
  select = inputform.airline;
  for(index = 0; index < select.length; index++) {
    if(select[index].value.split(":")[0] == alid) {
      select.selectedIndex = index;
      break;
    }
  }
  select = inputform.trips;
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
    setInputAllowed(null, true);
  } 
}

// Disable and re-enable submission while a) AJAX requests are pending, b) no changes have been made
// element=SRC,DST,AIRLINE or null
// state={true,false} for enabled,disabled
function setInputAllowed(element, state) {
  var ajax = null;
  switch(element) {
  case "SRC":
    ajax = "src_ap_ajax";
    break;
  case "DST":
    ajax = "dst_ap_ajax";
    break;
  case "AIRLINE":
    ajax = "airline_ajax";
    break;
  }
  if(state) {
    style = 'hidden';
    document.getElementById("b_add").disabled = false;
    document.getElementById("b_addclear").disabled = false;
    document.getElementById("b_save").disabled = false;
  } else {
    style = 'visible';
    document.getElementById("b_add").disabled = true;
    document.getElementById("b_addclear").disabled = true;
    document.getElementById("b_save").disabled = true;
  }
  if(ajax) {
    document.getElementById(ajax).style.visibility = style;
  }
}

// If clear=true, then input form is cleared after successful entry
function submitFlight(clear) {
  xmlhttpPost(URL_SUBMIT, clear, "ADD");
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
    var ap_select = document.forms['inputform'].src_ap;
  } else {
    var ap_select = document.forms['inputform'].dst_ap;
  }
  ap_select[0].text = name;
  ap_select[0].value = data;
  ap_select.selectedIndex = 0;

  if(input_toggle == "SRC") selectNewAirport("src_ap");
  if(input_toggle == "DST") selectNewAirport("dst_ap");
}

// Handle the "add new airlines" buttons
function popNewAirline() {
  window.open('/html/alsearch.html', 'Airline', 'width=500,height=580,scrollbars=yes');
}

function addNewAirline(data, name) {
  var al_select = document.forms['inputform'].airline;
  al_select[0].text = name;
  al_select[0].value = data;
  al_select.selectedIndex = 0;
  markAsChanged();
}

//
// Handle the "add new plane" button in input form
//
function inputNewPlane() {
  document.getElementById("input_status").innerHTML = "";
  document.getElementById("input_plane_add").style.display = 'none';
  document.getElementById("input_plane_unadd").style.display = 'inline';
  document.forms['inputform'].newPlane.focus();
}

function enterNewPlane() {
  var newplane = document.forms['inputform'].newPlane.value;
  cancelNewPlane(false); // don't clear status
  var inputform = document.forms['inputform'];
  inputform.plane.selectedIndex = 0;
  inputform.plane[0].value = "NEW:" + newplane;
  inputform.plane[0].text = newplane;
}

// If really=true, then print "Cancelled"
function cancelNewPlane(really) {
  document.forms['inputform'].newPlane.value = "";
  document.getElementById("input_plane_add").style.display = 'inline';
  document.getElementById("input_plane_unadd").style.display = 'none';
  if(really) {
    document.getElementById("input_status").innerHTML = "<B>Adding plane cancelled.</B>";
  }
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

  // Trip deleted?  Switch back to "all trips" view
  if(code == CODE_DELETEOK) {
    var tr_select = document.forms['filterform'].Trips;
    tr_select.selectedIndex = 0;
  }

  // This only applies when new trip is added in flight editor
  var trips = document.forms['inputform'].trips;
  if(trips) {
    switch(code) {
    case CODE_ADDOK:
      trips[0].text = name;
      trips[0].value = newTrid;
      trips.selectedIndex = 0;
      break;

    case CODE_DELETEOK:
      if(trips.selectedIndex == 0) {
	trips[0].text = "Select trip";
	trips[0].value = 0;
      } else {
	trips.remove(trips.selectedIndex);
	trips.selectedIndex = 0;
      }
      break;

    default:
      trips[trips.selectedIndex].text = name;
      break;
    }
  }
  // In all cases, refresh map
  // TODO: Would be enough to refresh the filter only...
  refresh(true);
}

// When user has entered flight number (NUMBER) or airline code (AIRLINE), try to match it to airline
function flightNumberToAirline(str) {
  document.getElementById("input_status").innerHTML = '';

  if(str == "NUMBER") {
    var flightNumber = document.forms['inputform'].number.value.toUpperCase();
    document.forms['inputform'].number.value = flightNumber;
  } else {
    var flightNumber = document.forms['inputform'].airline_code.value;
  }
  if(flightNumber.length >= 2) {
    var found = false;
    var re_iata = /^[a-zA-Z0-9][a-zA-Z0-9][ 0-9]/; // XX N...
    var re_icao = /^[a-zA-Z][a-zA-Z][a-zA-Z][ 0-9]$/;  // XXX N...
    if(flightNumber.length == 2 || re_iata.test(flightNumber.substring(0,3))) {
      var airlineCode = flightNumber.substring(0, 2);
    } else if(flightNumber.length == 3 || re_icao.test(flightNumber.substring(0,4))) {
      var airlineCode = flightNumber.substring(0, 3);
    }

    // We've found something that looks like an airline code, so overwrite it into AIRLINE field
    if(airlineCode) {
      document.forms['inputform'].airline_code.value = airlineCode;   
      var al_select = document.forms['inputform'].airline;
      for(index = 0; index < al_select.length; index++) {
	if(al_select[index].value.split(':')[1] == airlineCode) {
	  found = true;
	  al_select.selectedIndex = index;
	  document.forms['inputform'].trips.focus();
	  break;
	}
      }
    }

    // Couldn't find it entered yet, so pull code from database
    // This search is *always* an airline search (either code or full text)
    if(!found) {
      xmlhttpPost(URL_GETCODE, 0, "AIRLINE");
    } else {
      markAsChanged();
    }
  }
}

// When user has entered airport code, try to match it to airport
// Alternatively, if "id" is given, then match id to airport id (apid)
// If "quick", then do not recalculate distance/duration or flag as changed
function codeToAirport(type, id, quick) {
  document.getElementById("input_status").innerHTML = '';
  if(id) {
    idx = 1; // compare against code:apid[1]:x:y
  } else {
    idx = 0; // compare against code[0]:apid:x:y
  }
  var found = false;
  var apid;
  if(type == "SRC") {
    var ap_select = document.forms['inputform'].src_ap;
    var airportCode = document.forms['inputform'].src_ap_code.value;
  } else {
    var ap_select = document.forms['inputform'].dst_ap;
    var airportCode = document.forms['inputform'].dst_ap_code.value;
  }
  if(id) {
    airportCode = id;
  }
  for(index = 0; index < ap_select.length; index++) {
    if(ap_select[index].value.split(':')[idx] == airportCode) {
      found = true;
      ap_select.selectedIndex = index;
      apid = ap_select[index].value.split(':')[1];
      break;
    }
  }
  if(found) {
    // Altering value doesn't count as onChange, so we trigger manually
    if(type == "SRC") selectNewAirport("src_ap", quick);
    if(type == "DST") selectNewAirport("dst_ap", quick);
  } else {
    xmlhttpPost(URL_GETCODE, 0, type);
  }
}

// Add a temporary source or destination marker over currently selected airport
// Also calculates distance and duration (unless "quick" is true)
// type: "src_ap" or "dst_ap"
function selectNewAirport(type, quick) {
  document.getElementById("input_status").innerHTML = '';

  var size = new OpenLayers.Size(17, 17);
  var offset = new OpenLayers.Pixel(-(size.w/2), -(size.h/2));
  if(type == "src_ap") {
    var select = document.forms['inputform'].src_ap;
    var icon = new OpenLayers.Icon('/img/icon_plane-src.png',size,offset);
  } else {
    var select = document.forms['inputform'].dst_ap;
    var icon = new OpenLayers.Icon('/img/icon_plane-dst.png',size,offset);
  }

  var iata = select[select.selectedIndex].value.split(":")[0];
  var apid = select[select.selectedIndex].value.split(":")[1];
  var x = select[select.selectedIndex].value.split(":")[2];
  var y = select[select.selectedIndex].value.split(":")[3];

  if(apid > 0) {
    var lonlat = new OpenLayers.LonLat(x, y);
    var marker = new OpenLayers.Marker(lonlat, icon);
    airportLayer.addMarker(marker);
  }
  if(type == "src_ap") {
    if(input_srcmarker) {
      airportLayer.removeMarker(input_srcmarker);
      //input_srcpoint.geometry.move(x,y);
    } else {
      //input_srcpoint = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(x,y));
    }
    if(apid > 0) {
      document.forms['inputform'].src_ap_code.value = iata;
      input_srcmarker = marker;
      input_toggle = "DST";
    } else {
      document.forms['inputform'].src_ap_code.value = "";
      input_srcmarker = null;
    }
  } else {
    if(input_dstmarker) {
      airportLayer.removeMarker(input_dstmarker);
      //input_dstpoint.geometry.move(x,y);
    } else {
      //input_dstpoint = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(x,y));
      //highlightLayer.addNodes([input_srcpoint, input_dstpoint]);
    }
    if(apid > 0) {
      document.forms['inputform'].dst_ap_code.value = iata;
      input_dstmarker = marker;
      input_toggle = "SRC";
    } else {
      document.forms['inputform'].dst_ap_code.value = "";
      input_dstmarker = null;
    }
  }

  // Two airports defined, calculate distance and duration
  if(! quick && input_dstmarker && input_srcmarker) {
    var src_ap = document.forms['inputform'].src_ap;
    var lon1 = src_ap[src_ap.selectedIndex].value.split(":")[2];
    var lat1 = src_ap[src_ap.selectedIndex].value.split(":")[3];
    var dst_ap = document.forms['inputform'].dst_ap;
    var lon2 = dst_ap[dst_ap.selectedIndex].value.split(":")[2];
    var lat2 = dst_ap[dst_ap.selectedIndex].value.split(":")[3];
    var distance = gcDistance(lat1, lon1, lat2, lon2);

    var rawtime = Math.floor(30 + (distance / 500) * 60);
    var hours = Math.floor(rawtime/60);
    var mins = rawtime % 60;
    if(mins < 10) mins = "0" + mins;
    var duration = hours + ":" + mins;

    document.forms['inputform'].distance.value = distance;
    document.forms['inputform'].duration.value = duration;
  } 
  
  // Flag as major change
  if(! quick) {
    markAsChanged(true);
  }
}

function swapAirports() {
  var tmp = document.forms["inputform"].src_ap_code.value;
  document.forms["inputform"].src_ap_code.value = document.forms["inputform"].dst_ap_code.value;
  document.forms["inputform"].dst_ap_code.value = tmp;
  codeToAirport("SRC");
  // awful hack: wait a second for the first request to execute
  setTimeout('codeToAirport("DST")', 1000);
}

// Given apid, find the matching airport and pop it up
function selectAirport(apid) {
  var markers = airportLayer.markers;
  var found = false;
  for(m in markers) {
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
function selectAirline(new_alid) {
  var al_select = document.forms['filterform'].Airlines;
  for(index = 0; index < al_select.length; index++) {
    if(al_select[index].value == new_alid) {
      al_select.selectedIndex = index;
    }
  }
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
// Change settings
//
function openImport() {
  window.open('/html/import.html', 'Import', 'width=800,height=600,scrollbars=yes');
}

//
// Change settings
//
function settings() {
  window.open('/html/settings.html', 'ChangeSettings', 'width=500,height=400,scrollbars=yes');
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
    "<B>Welcome to OpenFlights!</b>  Click on <input type='button' value='New flight' align='middle' onclick='JavaScript:clearInput()'> to start adding flights,<br>or on <input type='button' value='Import' align='middle' onclick='JavaScript:openImport()'> to load in existing flights from sites like FlightMemory.";
  xmlhttpPost(URL_LOGIN, 0, "NEWUSER");
}

function logout(str) {
  document.getElementById("loginstatus").innerHTML = "<B>You have been logged out.</B>";
  document.getElementById("loginform").style.display = 'inline';
  document.getElementById("controlpanel").style.display = 'none';
  clearStack();
  clearFilter(true);
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
  } else {
    document.getElementById("addflighttitle").style.display = 'inline';
    document.getElementById("addflightbuttons").style.display = 'inline';
    document.getElementById("editflighttitle").style.display = 'none';
    document.getElementById("editflightbuttons").style.display = 'none';
  }
  input = true;
  input_toggle = "SRC";
  document.getElementById("input_status").innerHTML = "";

  // Don't allow saving until something is changed
  setInputAllowed(null, false);
}

// Reload flights list
function closeInput() {
  if(document.getElementById("b_add").disabled == false) {
    if(! confirm("Changes made to this flight have not been saved.  OK to discard them?")) {
      return;
    }
  }
  closePane();
  if(document.getElementById("editflighttitle").style.display == 'inline') {
    xmlhttpPost(URL_FLIGHTS, 0, "RELOAD");
  }
}

function clearInput() {
  var form = document.forms["inputform"];
  form.src_date.value = "";
  form.src_ap_code.value = "";
  form.dst_ap_code.value = "";
  form.duration.value = "-";
  form.distance.value = "-";
  form.number.value = "";
  form.airline_code.value = "";
  form.seat.value = "";
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
  xmlhttpPost(URL_PREINPUT); // rebuild selects
}

function showHelp() {
  if(currentPane == "help") return;
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
