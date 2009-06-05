/**
 * openflights.js -- for openflights.org
 * by Jani Patokallio <jani at contentshare dot sg>
 */

// Core map features
var map, drawControls, selectControl, selectedFeature, lineLayer, currentPopup;
var paneStack = [ "ad" ];

// User settings (defaults)
var privacy = "Y", flightTotal = 0, prefs_editor = "B", elite = "";

// Filter selections and currently chosen airport 
var filter_user = 0, filter_trid = 0, filter_alid = 0, filter_year = 0, apid = 0;
var tripname, tripurl;

// Current list of flights
var fidList, fidPtr = 0, fid = 0;
// Query and description of current list
var lastQuery, lastDesc;

// Temporary variables for current flight being edited
var alid = 0, plane;
var logged_in = false, demo_mode = true, initializing = true;
var input_srcmarker, input_dstmarker, input_line, input_toggle, input_al_toggle;
var changed = false, majorEdit = false;
// If true, the value has been set by autocomplete
var autocompleted = {"src_ap": false, "dst_ap": false, "airline": false,
		     "src_ap1": false, "dst_ap1": false, "airline1": false, 
		     "src_ap2": false, "dst_ap2": false, "airline2": false, 
		     "src_ap3": false, "dst_ap3": false, "airline3": false, 
		     "src_ap4": false, "dst_ap4": false, "airline4": false };

// Some helpers for multiinput handling
var multiinput_order = [ "src_ap1", "dst_ap1", "dst_ap2", "dst_ap3", "dst_ap4" ];
var multiinput_ids = [ "src_ap1id", "src_ap2id", "src_ap3id", "src_ap4id",
		       "dst_ap1id", "dst_ap2id", "dst_ap3id", "dst_ap4id",
		       "airline1id", "airline2id", "airline3id", "airline4id" ];
var multiinput_rows = 1;

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
var SELECT_MAXLEN = 25;

var COLOR_NORMAL = "#ee9900"; //orange
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

// Redefined with localized strings under init
var classes, seattypes, reasons, classes_short, reasons_short, modenames, modesegments, modeoperators, topmodes;
var modecolors = { "F":COLOR_NORMAL, "T":COLOR_TRAIN, "R":COLOR_ROAD, "S":COLOR_SHIP };
var modeicons = { "F":'/img/icon_airline.png', "T": '/img/icon_train.png',
		  "R": '/img/icon_car.png', "S": '/img/icon_ship.png' };
var modespeeds = { "F":500, "T":100, "R":60, "S":40 };
var toplimits = { "10":"Top 10", "20":"Top 20", "50":"Top 50" };

// Validate 24-hr time ([0]0:00-23:59)
var re_time = /(^0?[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/;
// Validate YYYY*MM*DD date; contains groups, leading zeroes not required for month, date)
var re_date = /^((19|20)\d\d)[- /.]([1-9]|0[1-9]|1[012])[- /.]([1-9]|0[1-9]|[12][0-9]|3[01])$/;
// Validate numeric value
var re_numeric = /^[0-9]*$/;

// avoid pink tiles
OpenLayers.IMAGE_RELOAD_ATTEMPTS = 3;
OpenLayers.Util.onImageLoadErrorColor = "transparent";

window.onload = function init(){
  $("helplink").style.display = 'inline';
  gt = new Gettext({ 'domain' : 'messages' });

  classes = {"Y": gt.gettext("Economy"), "P": gt.gettext("Prem.Eco"), "C":gt.gettext("Business"), "F":gt.gettext("First"), "": ""};
  seattypes = {"W":gt.gettext("Window"), "A":gt.gettext("Aisle"), "M":gt.gettext("Middle"), "": ""};
  reasons = {"B":gt.gettext("Work"), "L":gt.gettext("Leisure"), "C":gt.gettext("Crew"), "O": gt.gettext("Other"), "": ""};
  classes_short = {"Y":gt.gettext("Econ"), "P":gt.gettext("P.Eco"), "C":gt.gettext("Biz"), "F":gt.gettext("1st"), "": ""};
  reasons_short = {"B":gt.gettext("Work"), "L":gt.gettext("Leis."), "C":gt.gettext("Crew"), "O":gt.gettext("Other"), "": ""};
  modenames = { "F":gt.gettext("Flight"), "T":gt.gettext("Train"), "R":gt.gettext("Road"), "S":gt.gettext("Ship") };
  modesegments = { "F":gt.gettext("flight"), "T":gt.gettext("train"), "R":gt.gettext("road trip"), "S":gt.gettext("ship") };
  modeoperators = { "F":gt.gettext("airline"), "T":gt.gettext("railway"), "R":gt.gettext("road transport"), "S":gt.gettext("shipping") };
  topmodes = { "F":gt.gettext("Segments"), "D":gt.gettext("Mileage") };

  var bounds = new OpenLayers.Bounds(-180, -90, 180, 90);
  map = new OpenLayers.Map('map', {
      //eventListeners: { "zoomend": zoomEvent },
    maxExtent: bounds,
			       maxResolution: "auto",
			       maxZoomLevel: 8,
			       controls: [
					  new OpenLayers.Control.PanZoom(),
					  new OpenLayers.Control.NavToolbar({'title': gt.gettext("Toggle pan and region select mode")}),
					  new OpenLayers.Control.LayerSwitcher({'ascending':false, 'title': gt.gettext('Switch map layers')}),
					  new OpenLayers.Control.ScaleLine(),
					  new OpenLayers.Control.OverviewMap({'title': gt.gettext("Toggle overview map")})
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

  lineLayer = new OpenLayers.Layer.Vector(gt.gettext("My Flights"),
					{styleMap: new OpenLayers.StyleMap({
					    strokeColor: "${color}",
						strokeOpacity: 1,
						strokeWidth: "${count}",
						strokeDashstyle: "${stroke}"
						})
					    });
  
  
  airportLayer = new OpenLayers.Layer.Markers(gt.gettext("My Airports"));
  
  map.addLayers([ol_wms, jpl_wms, lineLayer, airportLayer]);
  
  // Extract any arguments from URL
  filter_trid = parseArgument("trip");
  filter_user = parseArgument("user");

  // Are we viewing another user's flights or trip?
  if(filter_user != "0" || filter_trid != 0) {
    demo_mode = false;
    $("loginstatus").style.display = 'inline';
    if(filter_trid != 0) {
      $("filter_tripselect").style.display = 'none';
    }
  } else {
    $("news").style.display = 'inline';

    // Nope, set up hinting and autocompletes for editor
    initHintTextboxes();

    new Ajax.Autocompleter("src_ap", "src_apAC", "php/autocomplete.php",
    			   {afterUpdateElement : getSelectedApid});
    new Ajax.Autocompleter("dst_ap", "dst_apAC", "php/autocomplete.php",
    			   {afterUpdateElement : getSelectedApid});
    new Ajax.Autocompleter("airline", "airlineAC", "php/autocomplete.php",
    			   {afterUpdateElement : getSelectedAlid});
    new Ajax.Autocompleter("plane", "planeAC", "php/autocomplete.php",
    			   {afterUpdateElement : getSelectedPlid});

    new Ajax.Autocompleter("src_ap1", "src_apAC1", "php/autocomplete.php",
    			   {afterUpdateElement : getSelectedApid});
    new Ajax.Autocompleter("dst_ap1", "dst_apAC1", "php/autocomplete.php",
    			   {afterUpdateElement : getSelectedApid});
    new Ajax.Autocompleter("airline1", "airlineAC1", "php/autocomplete.php",
    			   {afterUpdateElement : getSelectedAlid});
    new Ajax.Autocompleter("src_ap2", "src_apAC2", "php/autocomplete.php",
    			   {afterUpdateElement : getSelectedApid});
    new Ajax.Autocompleter("dst_ap2", "dst_apAC2", "php/autocomplete.php",
    			   {afterUpdateElement : getSelectedApid});
    new Ajax.Autocompleter("airline2", "airlineAC2", "php/autocomplete.php",
    			   {afterUpdateElement : getSelectedAlid});
    new Ajax.Autocompleter("src_ap3", "src_apAC3", "php/autocomplete.php",
    			   {afterUpdateElement : getSelectedApid});
    new Ajax.Autocompleter("dst_ap3", "dst_apAC3", "php/autocomplete.php",
    			   {afterUpdateElement : getSelectedApid});
    new Ajax.Autocompleter("airline3", "airlineAC3", "php/autocomplete.php",
    			   {afterUpdateElement : getSelectedAlid});
    new Ajax.Autocompleter("src_ap4", "src_apAC4", "php/autocomplete.php",
    			   {afterUpdateElement : getSelectedApid});
    new Ajax.Autocompleter("dst_ap4", "dst_apAC4", "php/autocomplete.php",
    			   {afterUpdateElement : getSelectedApid});
    new Ajax.Autocompleter("airline4", "airlineAC4", "php/autocomplete.php",
    			   {afterUpdateElement : getSelectedAlid});

    // No idea why this is needed, but FF3 disables random buttons without it...
    for(i=0;i<document.forms['inputform'].elements.length;i++){
      document.forms['inputform'].elements[i].disabled=false;
    }
    for(i=0;i<document.forms['multiinputform'].elements.length;i++){
      document.forms['multiinputform'].elements[i].disabled=false;
    }
    $('b_less').disabled = true;

    map.zoomToMaxExtent();
  }

  OpenLayers.Util.alphaHack = function() { return false; };

  xmlhttpPost(URL_MAP, 0, true);
}

// Extract arguments from URL (/trip/xxx or /user/xxx)
// Returns null if not found
function parseArgument(name)
{
  // http://foobar.com/name/xxx#blah *or* xxx?blah=blah
  // 0      1          2    3   4         3   4
  var urlbits = window.location.href.split(/[\/#?]+/);
  if(urlbits[2] == name) {
    return unescape(urlbits[3]);
  } else {
    return 0;
  }
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
  var features = [ new OpenLayers.Feature.Vector(new OpenLayers.Geometry.LineString(cList),
						 {count: count, color: color, stroke: stroke}) ];
  if(wList) {
    features.push(new OpenLayers.Feature.Vector(new OpenLayers.Geometry.LineString(wList),
						{count: count, color: color, stroke: stroke}));
  }
  if(eList) {
    features.push(new OpenLayers.Feature.Vector(new OpenLayers.Geometry.LineString(eList),
						{count: count, color: color, stroke: stroke}));
  }
  return features;
}

//
// Draw airport (or just update marker if it exists already)
// Returns true if a new marker was created, or false if it existed already
//
function drawAirport(airportLayer, apdata, name, city, country, count, formattedName, opacity) {
  var apcols = apdata.split(":");
  var code = apcols[0];
  var apid = apcols[1];
  var x = apcols[2];
  var y = apcols[3];

  // Description
  var desc = name + " (<B>" + code + "</B>)<br><small>" + city + ", " + country + "</small><br>" + gt.gettext("Flights:") + " " + count;

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
    $("news").style.display = 'inline';
    $("news").innerHTML = "ERROR: " + name + ":" + colorIndex + " of " + airportMaxFlights + ".i<br>Please hit CTRL-F5 to force refresh, and <a href='/about.html'>report</a> this error if it does not go away.";
    colorIndex = 0;
  }

  // Check if the airport has a marker already
  var markers = airportLayer.markers;
  for(m = 0; m < markers.length; m++) {
    if(markers[m].apid == apid) {
      markers[m].desc = desc; // update description for popup
      markers[m].state = true; // flag so that it will be made visible
      if(colorIndex != markers[m].colorIndex) {
	// Change icon
	var size = new OpenLayers.Size(airportIcons[colorIndex][1], airportIcons[colorIndex][1]);
	markers[m].icon.offset = new OpenLayers.Pixel(-(size.w/2), -(size.h/2));
	markers[m].icon.setSize(size);
 	markers[m].setUrl(airportIcons[colorIndex][0]);
      }
      markers[m].setOpacity(opacity);
      return false;
    }
  }

  // Nope, we need to add it
  var iconfile = airportIcons[colorIndex][0];
  var size = new OpenLayers.Size(airportIcons[colorIndex][1], airportIcons[colorIndex][1]);
  var offset = new OpenLayers.Pixel(-(size.w/2), -(size.h/2));
  var icon = new OpenLayers.Icon(iconfile,size,offset);
  
  var feature = new OpenLayers.Feature(airportLayer, new OpenLayers.LonLat(x, y));
  feature.closeBox = false;
  feature.data.icon = icon;
  feature.data.overflow = "auto";
  var marker = feature.createMarker();
  marker.setOpacity(opacity);

  // These are used to do airport lookups
  marker.apid = apid;
  marker.code = code;
  marker.count = count;
  marker.name = formattedName;
  marker.apdata = apdata;
  marker.state = true; // visible

  // Stored for popup
  marker.desc = desc;

  // Run when the user clicks on an airport marker
  // this == the feature, *not* the marker
  var markerClick = function (evt) {
    this.popupClass = OpenLayers.Class(OpenLayers.Popup.FramedCloud, {'autoSize': true, 'minSize': new OpenLayers.Size(200,110) });

    // Detailed flights accessible only if...
    // 1. user is logged in, or
    // 2. system is in "demo mode", or
    // 3. privacy is set to (O)pen
    desc = this.marker.desc;
    if( logged_in || demo_mode || privacy == "O") {
	// Add toolbar to popup
	desc = "<span style='position: absolute; right: 5; bottom: 3;'>" +
	  "<a href='#' onclick='JavaScript:selectAirport(" + apid + ", true);'><img src='/img/icon_plane-src.png' width=17 height=17 title='" + gt.gettext("Select this airport") + "' id='popup" + apid + "' style='visibility: hidden'></a> " +
	  "<a href='#' onclick='JavaScript:xmlhttpPost(\"" + URL_FLIGHTS + "\"," + apid + ", \"" + encodeURIComponent(desc) + "\");'><img src='/img/icon_copy.png' width=16 height=16 title='" + gt.gettext("List flights") + "'></a>" +
	  "</span>" + desc;
      }
    desc = "<img src=\"/img/close.gif\" onclick=\"JavaScript:closePopup();\" width=17 height=17> " + desc;
    closePopup();

    if (this.popup == null) {
      this.data.popupContentHTML = desc;
      this.popup = this.createPopup(this.closeBox);
      map.addPopup(this.popup);
      this.popup.show();
    } else {
      this.popup.setContentHTML(desc);
      this.popup.toggle();
    }
    if(this.popup.visible()) {
      currentPopup = this.popup;
    } else {
      closePane();
    }
    // Show or hide toolbar when applicable
    if($('popup' + apid)) {
      if(getCurrentPane() == "input" || getCurrentPane() == "multiinput") {
	$('popup' + apid).style.visibility = "visible";
      } else {
	$('popup' + apid).style.visibility = "hidden";
      }
    }
    OpenLayers.Event.stop(evt);
  };
  marker.events.register("mousedown", feature, markerClick);

  //marker.display(zoomFilter(count));
  airportLayer.addMarker(marker);

  return true;
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
	$("ajaxstatus").style.display = 'none';
      }
      if(strURL == URL_GETCODE) {
	var cols = self.xmlHttpReq.responseText.split(";");
	switch(param) {
	case 'airline':
	case 'airline1':
	case 'airline2':
	case 'airline3':
	case 'airline4':
	  var alid = cols[0];
	  if(alid != "" && alid != 0) {
	    $(param + 'id').value = cols[0];
	    $(param).value = cols[1];
	    $(param).style.color = '#000000';
	    replicateSelection(param);
	    markAsChanged(true); // new airline, force refresh on save
	  } else {
	    $(param).style.color = '#FF0000';
	    $(param + 'id').value = 0;
	  }
	  break;

	case 'src_ap':
	case 'dst_ap':
	case 'src_ap1':
	case 'dst_ap1':
	case 'src_ap2':
	case 'dst_ap2':
	case 'src_ap3':
	case 'dst_ap3':
	case 'src_ap4':
	case 'dst_ap4':
	  var apdata = cols[0];
	  var apid = apdata.split(":")[1];
	  if(apid && apid != 0) {
	    $(param + 'id').value = apdata;
	    $(param).value = cols[1];
	    $(param).style.color = '#000000';
	    replicateSelection(param);
	    markAirport(param); // new airport, force refresh on save
	    markAsChanged(true);
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
	  $("result").innerHTML = "<h4>" + str.split(';')[1] + "</h4><br><h6><a href='/'>Home</a></h6>";
	  $("ajaxstatus").style.display = 'none';
	  openPane("result");
	} else {
	  // Zoom map to fit when first loading another's flights/trip
	  updateMap(str);
	  if(! logged_in && ! demo_mode && initializing) {
	    closePane();
	    map.zoomToExtent(airportLayer.getDataExtent());
	  }
	  if(param) {
	    updateFilter(str);
	  }
	  updateTitle();

	  // Map now completely drawn for the first time
	  if(initializing) {
	    initializing = false;
	  }
	} 
      }
      if(strURL == URL_STATS) {
	showStats(self.xmlHttpReq.responseText);
	$("ajaxstatus").style.display = 'none';
      }
      if(strURL == URL_TOP10) {
	showTop10(self.xmlHttpReq.responseText);
	$("ajaxstatus").style.display = 'none';
      }
      if(strURL == URL_SUBMIT) {
	var result = self.xmlHttpReq.responseText.split(";");
	code = result[0];
	text = result[1];
	if(getCurrentPane() == "multiinput") {
	  $("multiinput_status").innerHTML = '<B>' + text + '</B>';
	} else {
	  $("input_status").innerHTML = '<B>' + text + '</B>';
	}
	setCommitAllowed(false);

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
	  majorEdit = true; // trigger map refresh
	}

	if(code == CODE_EDITOK || code == CODE_ADDOK) {
	  // If adding new flights (not editing), swap last destination to be new source and focus on date
	  if(getCurrentPane() == "input") {
	    if($("addflighttitle").style.display == 'inline') {
	      swapAirports(false);
	      document.forms['inputform'].seat.value = "";
	      document.forms['inputform'].seat_type.selectedIndex = 0;
	      document.forms['inputform'].src_date.focus();
	    } 
	  } else {
	    clearInput(); // Always clear multiview
	  }
	}

	// A change that affected the map was made, so redraw
	if(majorEdit) {
	  if(code == CODE_DELETEOK) {
	    setTimeout('refresh(true)', 1000); // wait for earlier ops to complete...
	  } else {
	    refresh(true); // ...else do it right now
	  }
	} else {
	  $("ajaxstatus").style.display = 'none';
	}
	majorEdit = false;
      } // end URL_SUBMIT
    }
  }
  // End result processing

  // Start query string generation
  switch(strURL) {

  case URL_SUBMIT:
    var inputform = document.forms['inputform'];

    // Deleting needs only the fid, and can be run without the inputform
    if(param != "DELETE") {
      if(getCurrentPane() == "multiinput") {
	query = 'multi=' + multiinput_rows + '&';
	var indexes = [];
	for(i = 1; i <= multiinput_rows; i++) {
	  indexes.push(i);
	}
      } else {
	var indexes = [ "" ];
      }
      for(i = 0; i < indexes.length; i++) {
	var src_date = $('src_date' + indexes[i]).value;
        if(! re_date.test(src_date)) {
	  alert(gt.gettext("Please enter a full date in year/month/date order, eg. 2008/10/30 for 30 October 2008. Valid formats include YYYY-MM-DD, YYYY/MM/DD, YYYY.MM.DD and YYYY MM DD."));
	  $('src_date' + indexes[i]).focus();
	  return;
	}
	var src_apid = $('src_ap' + indexes[i] + 'id').value.split(':')[1];
	if(! src_apid || src_apid == "0") {
	  alert(gt.gettext("Please enter a valid source airport."));
	  $('src_ap' + indexes[i]).focus();
	  return;
	}
	var dst_apid = $('dst_ap' + indexes[i] + 'id').value.split(':')[1];
	if(! dst_apid || dst_apid == "0") {
	  alert(gt.gettext("Please enter a valid destination airport."));
	  $('dst_ap' + indexes[i]).focus();
	  return;
	}
	var alid = $('airline' + indexes[i] + 'id').value;
	var airline = $('airline' + indexes[i]).value.trim();
	if(! alid || alid == 0) {
	  if(airline == "" || airline == $('airline').hintText) {
	    alid = "-1"; // UNKNOWN
	  } else {
	    mode = getMode();
	    if(confirm(Gettext.strargs(gt.gettext("'%1' not found in %2 database.  Do you want to add it as a new %2 company?"), [airline, modeoperators[mode], modeoperators[mode]]))) {
	      popNewAirline('airline' + indexes[i], airline, mode);
	    } else {
	      $('airline' + indexes[i]).focus();
	    }
	    return;
	  }
	}
	query +='alid' + indexes[i] + '=' + encodeURIComponent(alid) + '&' +
	  'src_date' + indexes[i] + '=' + encodeURIComponent(src_date) + '&' +
	  'src_apid' + indexes[i] + '=' + encodeURIComponent(src_apid) + '&' +
	  'dst_apid' + indexes[i] + '=' + encodeURIComponent(dst_apid) + '&';
      }
      if(getCurrentPane() == "input") {
	src_time = $('src_time').value;
	if(src_time != "" && src_time != "HH:MM") {
	  if(! re_time.test(src_time)) {
	    alert(gt.gettext("Please enter times in 24-hour format with a colon between hour and minute, eg. 21:37 for 9:37 PM."));
	    $('src_time').focus();
	    return;
	  }
	  query += 'src_time=' + encodeURIComponent(src_time) + '&';
	}

	var type = inputform.seat_type.value;
	if(type == "-") type = "";
	var myClass = radioValue(inputform.myClass);
	var reason = radioValue(inputform.reason);
	var plane = inputform.plane.value;
        // ##TODO## fix
	if(plane == "Enter plane model") {
	  plane = "";
	}
	var trid = 0;
	if(inputform.trips) {
	  trid = inputform.trips[inputform.trips.selectedIndex].value.split(";")[0];
	}
	if(trid == 0) trid = "NULL";
	var registration = inputform.registration.value;
	var note = inputform.note.value;
	var duration = $('duration').value;
	if(! re_time.test(duration)) {
	  alert(gt.gettext("Please enter flight duration as hours and minutes with a colon between hour and minute, eg. 5:37 for 5 hours, 37 minutes.  You can blank the duration to have it recalculated automatically."));
	  $('duration').focus();
	  return;
	}
	var distance = $('distance').value;
	if(! re_numeric.test(distance)) {
	  alert(gt.gettext("Please enter flight distance as miles, with no fractional parts.  You can blank the distance to have it re-calculated automatically."));
	  $('distance').focus();
	  return;
	}
	var number = inputform.number.value;
	var seat = inputform.seat.value;
	var mode = inputform.mode.value;
      } else {
	var number = "";
	var seat = "";
	var type = "";
	var myClass = "";
	var reason = "";
	var plane = "";
	var trid = "NULL";
	var registration = "";
	var note = "";
	var duration = "";
	var distance = "";
	var mode = "F";
      }
    }
    query += 'duration=' + encodeURIComponent(duration) + '&' +
      'distance=' + encodeURIComponent(distance) + '&' +
      'number=' + encodeURIComponent(number) + '&' +
      'seat=' + encodeURIComponent(seat) + '&' +
      'type=' + encodeURIComponent(type) + '&' +
      'class=' + encodeURIComponent(myClass) + '&' +
      'reason=' + encodeURIComponent(reason) + '&' +
      'registration=' + encodeURIComponent(registration) + '&' +
      'note=' + encodeURIComponent(note) + '&' +
      'plane=' + encodeURIComponent(plane) + '&' +
      'trid=' + encodeURIComponent(trid) + '&' +
      'mode=' + encodeURIComponent(mode) + '&' +
      'fid=' + encodeURIComponent(fid) + '&' +
      'param=' + encodeURIComponent(param);
    if(getCurrentPane() == "multiinput") {
      $("multiinput_status").innerHTML = '<B>Saving...</B>';
    } else {
      $("input_status").innerHTML = '<B>Saving...</B>';
    }
    break;

  case URL_LOGIN:
    $("ajaxstatus").style.display = 'inline';
    var name = document.forms['login'].name.value;
    var pw = document.forms['login'].pw.value;
    var challenge = document.forms['login'].challenge.value;
    hash = hex_md5(challenge + hex_md5(pw + name.toLowerCase()));
    legacy_hash = hex_md5(challenge + hex_md5(pw + name));
    query = 'name=' + encodeURIComponent(name) + '&pw=' + encodeURIComponent(hash) + '&lpw=' + encodeURIComponent(legacy_hash);
    break;

  case URL_GETCODE:
    query = encodeURIComponent(param) + '=' + encodeURIComponent(id) + '&quick=true&mode=' + getMode();
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
    // ...else fallthru and generate new query

  // URL_MAP, URL_FLIGHTS, URL_STATS, URL_TOP10
  default:
    $("ajaxstatus").style.display = 'inline';
    var form = document.forms['filterform'];    
    if(! initializing && form.Trips) {
      filter_trid = form.Trips.value.split(";")[0];
    }
    filter_alid = form.Airlines.value.split(";")[0];
    filter_year = form.Years.value;
    query = 'user=' + encodeURIComponent(filter_user) + '&' +
      'trid=' + encodeURIComponent(filter_trid) + '&' +
      'alid=' + encodeURIComponent(filter_alid) + '&' +
      'year=' + encodeURIComponent(filter_year) + '&' +
      'param=' + encodeURIComponent(param);
    guestpw = $('guestpw');
    if(guestpw) {
      query += "&guestpw=" + hex_md5(guestpw.value + filter_user.toLowerCase());
    }
    filter_extra_key = form.Extra.value;
    if(filter_extra_key != "" && $('filter_extra_value')) {
      filter_extra_value = $('filter_extra_value').value;
      query += '&xkey=' + filter_extra_key +
	'&xvalue=' + filter_extra_value;
    }
    if(strURL == URL_FLIGHTS) {
      if(param == "EDIT" || param == "COPY") {
	query += '&fid=' + encodeURIComponent(id);
      } else {
	// This is a flight list query, so store its details
	query += '&id=' + encodeURIComponent(id);
	lastQuery = query;
	lastDesc = param;
      }
    }
    if(strURL == URL_TOP10) {
      if(param) {
	query += '&' + param;
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

  if(!trips || trips == "") {
    $("filter_tripselect").innerHTML = "&nbsp;" + gt.gettext("No trips");
    $("input_trip_select").innerHTML = "&nbsp;" + gt.gettext("No trips. Add one? ");
  } else {
    var tripSelect = createSelect("Trips", "All trips", filter_trid, trips.split("\t"), SELECT_MAXLEN, "refresh(true)");
    $("filter_tripselect").innerHTML = tripSelect;
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
    $("input_trip_select").innerHTML =
      cloneSelect(document.forms['filterform'].Trips, "trips", "markAsChanged", selected);
    document.forms['inputform'].trips[0].text = "Select trip";
  }

  var airlineSelect = createSelect("Airlines", "All carriers", filter_alid, airlines.split("\t"), SELECT_MAXLEN, "refresh(true)");
  $("filter_airlineselect").innerHTML = airlineSelect;
  var yearSelect = createSelect("Years", "All", filter_year, years.split("\t"), 20, "refresh(true)");
  $("filter_yearselect").innerHTML = yearSelect;

}


// Refresh current map title
function updateTitle(str) {
  var form = document.forms['filterform'];
  var text = "";
  var airline = form.Airlines[form.Airlines.selectedIndex].value.split(";")[1];
  var year = form.Years[form.Years.selectedIndex].text;
  var trip = "";
  if(form.Trips) {
    trip = form.Trips[form.Trips.selectedIndex].value.split(";")[1];
  }

  // Logged in users
  if(logged_in) {
    switch(filter_trid) {
    case 0:
    case "0":
      // do nothing
      break;
    case "null":
      text = gt.gettext("Unassigned flights");
      break;
    default:
      text = tripname + " <a href=\"" + tripurl + "\">\u2197</a>";
      break;
    }
    if(airline != "All carriers") {
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
    if(demo_mode) {
      if(airline != "All carriers") {
	text = Gettext.strargs(gt.gettext("Recent flights on %1"), [airline]);
      } else {
	text = gt.gettext("Recently added flights");
      }

    } else {
      // Viewing another's profile
      if(trip != "All trips" && trip != "") {
	text = tripname + " <a href=\"" + tripurl + "\">\u2197</a>";
      } else {
	if(airline != "All carriers") {
	  if(year != "All") {
	    text = Gettext.strargs(gt.gettext("%1's flights on %2 in %3"), [filter_user, airline, year]);
	  } else {
	    text = Gettext.strargs(gt.gettext("%1's flights on %2"), [filter_user, airline]);
	  }
	} else {
	  if(year != "All") {
	    text = Gettext.strargs(gt.gettext("%1's flights in %2"), [filter_user, year]);
	  } else {
	    text = Gettext.strargs(gt.gettext("%1's flights"), [filter_user]);
	  }
	}
      }
      $("loginstatus").innerHTML = getEliteIcon(elite) + "<b>" + text +
	"</b> <h6><a href='/'>" + gt.gettext("Home") + "</a></h6>";
    }
  }

  // And finally tack on the extra filter, if any
  filter_extra_key = form.Extra.value;
  if(filter_extra_key != "" && $('filter_extra_value')) {
    if(text == "") {
      text = "<img src=\"/img/close.gif\" onclick=\"JavaScript:clearFilter(true);\" width=17 height=17> ";
    } else {
      text = ", ";
    }
    text += form.Extra[form.Extra.selectedIndex].text + ' ' + $('filter_extra_value').value;
  }

  $("maptitle").innerHTML = text;
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
  var select = "<select class='filter' id='" + selectName + "' name='" + selectName + "'";
  if(hook) {
    select += " onChange='JavaScript:" + hook + "'";
  }
  if(tabIndex) {
    select += " tabindex=\"" + tabIndex + "\"";
  }
  if(selectName == "Years") {
    select += "><option value='0'>" + allopts + "</option>";
  } else {
    select += "><option value='0;" + allopts + "'>" + allopts + "</option>";
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
  if(logged_in && selectName == "Trips") {
    select += "<option value='null' " + (filter_trid == "null" ? " SELECTED" : "") + ">" + gt.gettext("Not in a trip") + "</option>";
  }
  select += "</select>";
  return select;
}

// If current value is given, don't add "All" option
function createSelectFromArray(selectName, opts, hook, current) {
  var select = "<select style='width: 100px' id='" + selectName + "' name='" + selectName +
    "' onChange='JavaScript:" + hook + "'>";
  if(! current) select += "<option value=''>All</option>";
  for (r in opts) {
    if(r.length > 2) continue; // filter out Prototype.js cruft
    select += "<option value='" + r + "' " + (r == current ? "SELECTED" : "") + ">" + opts[r] + "</option>";
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
    if(id != "null") { // Skip "Not in trip" special option
      newSelect += "<option value=\"" + id + "\" " + selectedText + ">" + text + "</option>";
    }
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
  return "";
}

// Clear all markers, popups and flights
function clearMap() {
  lineLayer.destroyFeatures();
  markers = airportLayer.markers.slice(); // clone
  airportLayer.clearMarkers();
  for(m = 0; m < markers.length; m++) {
    markers[m].destroy();
  }
  var popups = map.popups;
  for(p = 0; p < popups.length; p++) {
    popups[p].destroy();
  }
}

// Reinsert all flights, airports from database result
function updateMap(str){
  lineLayer.destroyFeatures();

  // Default all markers to be turned off
  var markers = airportLayer.markers;
  for(m = 0; m < markers.length; m++) {
    markers[m].state = false;
  }

  var master = str.split("\n");
  var stats = master[0];
  var flights = master[1];
  var airports = master[2];
  
  var col = stats.split(";");
  var miles = col[1];
  if(! miles) miles = 0;
  var duration = col[2]; // minutes
  var days = Math.floor(col[2] / (60*24));
  var hours = Math.floor((col[2] / 60) % 24);
  var min = Math.floor(col[2] % 60);
  if(min < 10) min = "0" + min;

  stats = col[0] + " " + gt.gettext("segments") + "<br>" +
    miles + " " + gt.gettext("miles") + "<br>" +
    days + " " + gt.gettext("days") + " " + hours + ":" + min;
  $("stats_ajax").style.display = 'none';
  $("stats").innerHTML = stats;
  flightTotal = col[0];
  privacy = col[3];
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

  // New user (or filter setting) with no flights?  Then don't even try to draw
  if(flightTotal != "0") {
    var rows = flights.split("\t");
    for (r = 0; r < rows.length; r++) {
      // apid1 0, x1 1, y1 2, apid2 3, x2 4, y2 5, count 6, distance 7, future 8, mode 9
      var rCol = rows[r].split(";");
      if(rCol[8] == "Y") {
	stroke = "dash";
      } else {
	stroke = "solid";
      }
      color = modecolors[rCol[9]];
      if(!color) color = COLOR_NORMAL;
      lineLayer.addFeatures(drawLine(parseFloat(rCol[1]), parseFloat(rCol[2]),
				     parseFloat(rCol[4]), parseFloat(rCol[5]),
				     rCol[6], rCol[7], color, stroke));
    }
    
    var rows = airports.split("\t");
    
    // Airports are ordered from least busy to busiest, so we calibrate the color scale based on the last result
    airportMaxFlights = rows[rows.length - 1].split(";")[4];
    for (r = 0; r < rows.length; r++) {
      var col = rows[r].split(";");
      // 0 apdata, 1 name, 2 city, 3 country, 4 count, 5 formatted_name, 6 future
      if(col[6] == "Y") {
	opacity = 0.5;
      } else {
	opacity = 1;
      }
      drawAirport(airportLayer, col[0], col[1], col[2], col[3], col[4], col[5], opacity);
    }
    //zoomEvent(); // filter in/out airports based on zoom level
  }

  // Now we turn marker display on or off
  for(m = 0; m < markers.length; m++) {
    markers[m].display(markers[m].state);
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

function startListFlights() {
  var form = document.forms['filterform'];
  if(form.Trips) {
    var tripName = form.Trips[document.forms['filterform'].Trips.selectedIndex].text;
  }
  var airlineName = document.forms['filterform'].Airlines[document.forms['filterform'].Airlines.selectedIndex].text;
  var yearName = document.forms['filterform'].Years[document.forms['filterform'].Years.selectedIndex].text;
  // ##TODO##
  var title = "Flights" + (tripName ? " for " + tripName : "") + " on " + airlineName + " in year " + yearName;
  xmlhttpPost(URL_FLIGHTS, 0, encodeURIComponent(title));
}

function listFlights(str, desc) {
  openPane("result");
  fidList = new Array();
  var today = new Date().getTime();

  // IE string concat is painfully slow, so we use an array and join it instead
  var table = [];
  table.push("<img src=\"/img/close.gif\" onclick=\"JavaScript:closePane();\" width=17 height=17> ");
  if(str == "") {
    table.push("<i>" + gt.gettext("No flights found at this airport.") + "</i></span></div>");
  } else {
    if(desc) {
      table.push(desc.replace(/\<br\>/g, " &mdash; "));
      table.unshift("<span style='float: right'>" + gt.gettext("Export") + " <input type='button' value='CSV' title='" + gt.gettext("Comma-Separated Value, for Excel and data processing") + "' align='middle' onclick='JavaScript:exportFlights(\"export\")'><input type='button' value='KML' title='" + gt.gettext("Keyhole Markup Language, for Google Earth and visualization") + "' align='middle' onclick='JavaScript:exportFlights(\"KML\")'></span>"); // place at front of array
    }
    table.push("<table width=100% class=\"sortable\" id=\"apttable\" cellpadding=\"0\" cellspacing=\"0\">");
    table.push("<tr><th class=\"unsortable\"></th><th>" + gt.gettext("From") + "</th><th>" + gt.gettext("To") + "</th><th>" + gt.gettext("Nr.") + "</th><th>" + gt.gettext("Date") + "</th><th class=\"sorttable_numeric\">" + gt.gettext("Miles") + "</th><th>" + gt.gettext("Time") + "</th><th>" + gt.gettext("Vehicle") + "</th><th>" + gt.gettext("Seat") + "</th><th>" + gt.gettext("Class") + "</th><th>" + gt.gettext("Reason") + "</th><th>" + gt.gettext("Trip") + "</th><th>" + gt.gettext("Note") + "</th>");
    if(logged_in) {
      table.push("<th class=\"unsortable\">" + gt.gettext("Action") + "</th>");
    }
    table.push("</tr>");

    var rows = str.split("\n");
    for (r = 0; r < rows.length; r++) {
      // src_iata 0, src_apid 1, dst_iata 2, dst_apid 3, flight code 4, date 5, distance 6, duration 7, seat 8, seat_type 9, class 10, reason 11, fid 12, plane 13, registration 14, alid 15, note 16, trid 17, plid 18, airline_code 19, src_time 20, mode 21
      var col = rows[r].split("\t");
      var trip = col[17];
      var seat = col[8];
      var plane = col[13];
      var fid = col[12];
      var code = col[4];
      var date = col[5];
      var modeicon = modeicons[col[21]];
      var modename = modenames[col[21]];

      // Date.parse() doesn't work on YYYY/MM/DD, so we chop it up and use Date.UTC instead (sigh)
      if(Date.UTC(date.substring(0,4), date.substring(5,7) - 1, date.substring(8,10)) > today) {
	date = "<I>" + date + "</I>";
      }

      // Prepend airline code to numeric/missing flight number
      if(/^[0-9]*$/.test(code)) {
	code = col[19] + code;
      }
      if(col[14] != "") {
	plane += " (" + col[14] + ")";
      }
      if(logged_in && trip != "") {
	trip = "<a href=\"#\" onclick=\"JavaScript:editTrip(" + trip + ");\">" + trip + "</a>";
      }
      table.push("<tr><td><img src='" + modeicon + "' title='" + modename + "' width=17 height=17></td>" +
		 "<td><a href=\"#\" onclick=\"JavaScript:selectAirport(" + col[1] + ");\">" + col[0] + "</a></td>" +
		      "<td><a href=\"#\" onclick=\"JavaScript:selectAirport(" + col[3] + ");\">" + col[2] + "</a></td>" +
		      "<td>" + code + "</td><td>" + date + "</td><td>" + col[6] + "</td><td>" + col[7] +
		      "</td><td>" + plane + "</td><td>" + seat +
		      "</td><td>" + classes[col[10]] + "</td><td>" + reasons[col[11]] +
		      "</td><td>" + trip + "</td>" +
		      "</td><td>" + col[16].substring(0,15) + "</td>");
	
      if(logged_in) {
	table.push("<td>");
	table.push("<a href='#' onclick='JavaScript:preEditFlight(" + fid + "," + r + ");'><img src='/img/icon_edit.png' width=16 height=16 title='" + gt.gettext("Edit this flight") + "'></a>");
	table.push("<a href='#' onclick='JavaScript:preCopyFlight(" + fid + ");'><img src='/img/icon_copy.png' width=16 height=16 title='" + gt.gettext("Copy to new flight") + "'></a>");
	table.push("<a href='#' onclick='JavaScript:deleteFlight(" + fid + ");'><img src='/img/icon_delete.png' width=16 height=16 title='" + gt.gettext("Delete this flight") + "'></a>");
	table.push("</td>");
      }
      table.push("</tr>");
      fidList.push(fid);
    }
    table.push("</table>");
  }
  $("result").innerHTML = table.join("");
  // Refresh sortables code
  sortables_init();
}

// Dump flights to CSV
// type: "backup" to export everything, "export" to export only current filter selection
function exportFlights(type) {
  if(type == "KML") {
    location.href="http://" + location.host + "/php/kml.php?" + lastQuery;
  } else {
    location.href="http://" + location.host + "/php/flights.php?" + lastQuery + "&export=" + type;
  }
}

// The "Analyze" button (detailed stats)
function showStats(str) {
  if(str.substring(0,5) == "Error") {
    $("result").innerHTML = str.split(';')[1];
    openPane("result");
    return;
  }

  openPane("result");
  if(str == "") {
    bigtable = "<i>Statistics calculation failed!</i>";
  } else {
    var master = str.split("\n");
    var uniques = jsonParse(master[0]);
    var longshort = master[1];
    var extremes = master[2];
    var classData = master[3];
    var reasonData = master[4];
    var seatData = master[5];
    var modeData = master[6];

    bigtable = "<table><td style=\"vertical-align: top\"><img src=\"/img/close.gif\" onclick=\"JavaScript:closePane();\" width=17 height=17></td><td style=\"vertical-align: top\">";

    table = "<table style=\"border-spacing: 10px 0px\">";
    table += "<tr><th colspan=2>" + gt.gettext("Unique") + "</th></tr>";
    table += "<tr><td>" + gt.gettext("Airports") + "</td><td>" + uniques["num_airports"] + "</td></tr>";
    table += "<tr><td>" + gt.gettext("Carriers") + "</td><td>" + uniques["num_airlines"] + "</td></tr>";
    table += "<tr><td>" + gt.gettext("Countries") + "</td><td>" + uniques["num_countries"] + "</td></tr>";
    table += "<tr><td>" + gt.gettext("Vehicles") + "</td><td>" + uniques["num_planes"] + "</td></tr>";
    table += "<tr><td>&nbsp;</td></tr>";

    var distance = uniques["distance"];
    table += "<tr><th colspan=2>" + gt.gettext("Distance") + "</th></tr>";
    table += "<tr><td>" + gt.gettext("Miles flown") + "</td><td>" + distance + "</td></tr>";
    table += "<tr><td>" + gt.gettext("Around the world") + "</td><td>" + (distance / EARTH_CIRCUMFERENCE).toFixed(2) + "x</td></tr>";
    table += "<tr><td>" + gt.gettext("To the Moon") + "</td><td>" + (distance / MOON_DISTANCE).toFixed(3) + "x</td></tr>";
    table += "<tr><td>" + gt.gettext("To Mars") + "</td><td>" + (distance / MARS_DISTANCE).toFixed(4) + "x</td></tr>";
    table += "</table>";
    bigtable += table + "</td><td style=\"vertical-align: top\">";

    table = "<table style=\"border-spacing: 10px 0px\">";
    table += "<tr><th colspan=2>" + gt.gettext("Journey records") + "</th></tr>";
    var rows = longshort.split(";");
    for (r = 0; r < rows.length; r++) {
      var col = rows[r].split(",");
      // desc 0, distance 1, duration 2, s.iata 3, s.apid 4, d.iata 5, d.apid 6
      table += "<tr><td>" + col[0] + "</td><td><a href=\"#\" onclick=\"JavaScript:selectAirport(" + col[4] + ");\">" + col[3] + "</a>&harr;<a href=\"#\" onclick=\"JavaScript:selectAirport(" + col[6] + ");\">" + col[5] + "</a>, " + col[1] + " mi, " + col[2] + "</td></tr>";
    }
    table += "<tr><td>" + gt.gettext("Average") + "</td><td>" + uniques["avg_distance"] + " mi, " + uniques["avg_duration"] + "</td></tr>";
    table += "<tr><td>&nbsp;</td></tr>";
    table += "<tr><td>&nbsp;</td></tr>";
    table += "<tr><th colspan=2>" + gt.gettext("Airport records") + "</th></tr>";
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
    table += "<tr><th>" + gt.gettext("Class") + "</th><th>" + gt.gettext("Reason") + "</th></tr>";
    table += "<tr><td>";
    table += googleChart(classData, classes_short);
    table += "</td><td>";
    table += googleChart(reasonData, reasons_short);
    table += "</td></tr>";

    table += "<tr><th>" + gt.gettext("Seats") + "</th><th>" + gt.gettext("Mode") + "</th></tr><tr><td>";
    table += googleChart(seatData, seattypes);
    table += "</td><td>";
    table += googleChart(modeData, modenames);
    table += "</td></tr></table>";
    bigtable += table + "</td></tr></table>";
  }

  $("result").innerHTML = bigtable;
}

// Generate a pie chart image via Google Charts API
function googleChart(data, labeldata) {
  var rows = data.split(":");
  var cols = "", labels = "";
  for (r = 0; r < rows.length; r++) {
    var col = rows[r].split(",");
    cols += (r == 0 ? "" : ",") + col[1];
    labels += (r == 0 ? "" : "|") + labeldata[col[0]];
  }
  return "<img width=180 height=80 src='http://chart.apis.google.com/chart?cht=p&chd=t:" + cols + "&chs=180x80&chl=" + labels + "&chco=2A416A,39588E,688BC3,B2C3DF'>";
}

function showTop10(str) {
  if(str.substring(0,5) == "Error") {
    $("result").innerHTML = str.split(';')[1];
    openPane("result");
    return;
  }

  // Take note of existing settings, if any
  var form = document.forms['top10form'];
  if(form) {
    mode = form.mode[form.mode.selectedIndex].value;
    limit = form.limit[form.limit.selectedIndex].value;
  } else {
    mode = "F";
    limit = "10";
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
    bigtable = "<table style='width: 100%; border-collapse: collapse'><td style='vertical-align: top; padding-right: 10px'><img src='/img/close.gif' onclick='JavaScript:closePane();' width=17 height=17><form id='top10form'>";
    table = "<br>" + gt.gettext("Show...") + "<br>";
    table += createSelectFromArray('limit', toplimits, "updateTop10()", limit) + "<br>";
    table += gt.gettext("Sort by...") + "<br>";
    table += createSelectFromArray('mode', topmodes, "updateTop10()", mode) + "<br>";
    bigtable += table + "</form></td>";

    bigtable += "<td style='vertical-align: top; background-color: #ddd; padding: 0px 10px'>";
    table = "<table><tr><th colspan=3'>" + gt.gettext("Routes") + "</th></tr>";
    var rows = routes.split(":");
    for (r = 0; r < rows.length; r++) {
      var col = rows[r].split(",");
      // s.name, s.apid, d.name, d.apid, count
      table += "<tr><td><a href='#' onclick='JavaScript:selectAirport(" + col[1] + ");'>" + col[0] + "</a>&harr;" +
	"<a href='#' onclick='JavaScript:selectAirport(" + col[3] + ");'>" + col[2] + "</a></td>" + 
	"<td style='text-align: right; padding-left: 10px'>" + col[4] + "</td></tr>";
    }
    table += "</table>";

    bigtable += table + "</td><td style='vertical-align: top; padding: 0px 10px'>";
    table = "<table><tr><th colspan=3'>" + gt.gettext("Airports") + "</th></tr>";
    var rows = airports.split(":");
    for (r = 0; r < rows.length; r++) {
      var col = rows[r].split(",");
      // name, iata, count, apid
      desc = col[0].substring(0,20) + " (" + col[1] + ")";
      table += "<tr><td><a href='#' onclick='JavaScript:selectAirport(" + col[3] + ");'>" + desc + "</a></td><td style='text-align: right; padding-left: 10px'>" + col[2] + "</td>";
    }
    table += "</table>";

    bigtable += table + "</td><td style='vertical-align: top; background-color: #ddd; padding: 0px 10px'>";    
    table = "<table><tr><th colspan=3'>" + gt.gettext("Airlines") + "</th></tr>";
    var rows = airlines.split(":");
    for (r = 0; r < rows.length; r++) {
      var col = rows[r].split(",");
      // name, count, apid
      table += "<tr><td><a href='#' onclick='JavaScript:selectAirline(" + col[2] + ");refresh(false);'>" + col[0] + "</a></td><td style='text-align: right; padding-left: 10px'>" + col[1] + "</td>";
    }
    table += "</table>";

    bigtable += table + "</td><td style='vertical-align: top; padding-left: 10px;'>";
    table = "<table><tr><th colspan=3>" + gt.gettext("Planes") + "</th></tr>";
    var rows = planes.split(":");
    for (r = 0; r < rows.length; r++) {
      var col = rows[r].split(",");
      // name, count
      table += "<tr><td>" + col[0] + "</td><td style='text-align: right; padding-left: 10px'>" + col[1] + "</td>";
    }
    table += "</table>";
    bigtable += table + "</td>";
  }

  var form = document.forms['top10form'];
  if(form) {
    mode = form.mode[form.mode.selectedIndex].value;
    limit = form.limit[form.limit.selectedIndex].value;
  }
  $("result").innerHTML = bigtable;
}

function updateTop10() {
  var form = document.forms['top10form'];
  if(form) {
    mode = form.mode[form.mode.selectedIndex].value;
    limit = form.limit[form.limit.selectedIndex].value;
  } else {
    mode = "F";
    limit = "10";
  }
  xmlhttpPost(URL_TOP10, 0, "mode=" + mode + "&limit=" + limit);
}

// Move "pointer" in flight list up or down one when user clicks prev, next
function editPointer(offset) {
  var newPtr = fidPtr + offset;
  if(newPtr >= 0 && newPtr < fidList.length) {
    if(hasChanged()) {
      if(! confirm(gt.gettext("Changes made to this flight have not been saved.  OK to discard them?"))) {
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
  $("b_prev").disabled = (fidPtr <= 0 ? true : false);
  $("b_next").disabled = (fidPtr >= fidList.length - 1 ? true : false);
  $("editflighttitle").innerHTML = gt.gettext("Loading...");
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
    openDetailedInput(param);
  }

  // src_iata 0, src_apid 1, dst_iata 2, dst_apid 3, flight code 4, date 5, distance 6, duration 7, seat 8, seat_type 9, class 10, reason 11, fid 12, plane 13, registration 14, alid 15, note 16, trid 17, plid 18, alcode 19, src_time 20, mode 21
  var col = str.split("\t");
  var form = document.forms['inputform'];
  form.number.value = col[4];
  form.src_date.value = col[5];
  if(col[20] != "") {
    $('src_time').style.color = '#000';
    form.src_time.value = col[20].substring(0,5); // HH:MM, drop seconds
    // dst_time calculated automatically
  } else {
    clearTimes();
  }
  form.seat.value = col[8];

  // Don't recalc distance/duration yet
  selectAirport(col[1], true, true); // sets src_ap, src_apid
  selectAirport(col[3], true, true); // sets dst_ap, dst_apid
  selectAirline(col[15], true); // sets airline, airlineid

  selectInSelect(inputform.seat_type, col[9]);
  selectInSelect(inputform.trips, col[17]);
  selectInSelect(inputform.mode, col[21]);
  changeMode(col[21]);

  $("editflighttitle").innerHTML = Gettext.strargs(gt.gettext("Edit segment %1 of %2"), [fidPtr+1, fidList.length]);

  var myClass = inputform.myClass;
  for(index = 0; index < myClass.length; index++) {
    if(myClass[index].value == col[10]) {
      myClass[index].checked = true;
    } else {
      myClass[index].checked = false;
    }
  }
  var reason = inputform.reason;
  for(index = 0; index < reason.length; index++) {
    if(reason[index].value == col[11]) {
      reason[index].checked = true;
    } else {
      reason[index].checked = false;
    }
  }

  // Read these after selectAirport mucks up the dist/duration
  $('distance').value = col[6];
  $('distance').style.color = '#000';
  $('duration').value = col[7];
  $('duration').style.color = '#000';
  calcDuration('DEPARTURE'); // figure out arrival time according to previous dist/dur
  fid = col[12]; //stored until flight is saved or deleted

  $('plane').value = col[13]; 
  $('plane_id').value = col[18];

  form.registration.value = col[14];
  alid = col[15];
  if(col[16] != "") {
    $('note').style.color = '#000';
    form.note.value = col[16];
  } else {
    $('note').value = $('note').hintText;
  }
  form.seat.value = col[8];

  $('src_ap').style.color = '#000000';
  $('dst_ap').style.color = '#000000';
  $('airline').style.color = '#000000';
  $('plane').style.color = '#000000';

  // Don't allow saving until something is changed
  setCommitAllowed(false);
  majorEdit = false;
}

// Select correct input editor
function newFlight() {
  switch(prefs_editor) {
  case "D":
    openDetailedInput("ADD");
    break;

  case "B":
    openBasicInput("ADD");
    break;
  }
}

// User has edited a flight's contents
// if major is true, force a redraw later
function markAsChanged(major) {
  if(major) {
    majorEdit = true;
  }
  if(! changed) {
    changed = true;
    setCommitAllowed(true);
    $("input_status").innerHTML = '';
    $("multiinput_status").innerHTML = '';
  } 
}

// Has the user made any changes?
// If yes, the add button will be enabled (in both ADD and EDIT modes)
function hasChanged() {
  return changed;
}

// Disable and re-enable submission while a) AJAX requests are pending, b) no changes have been made
// state={true,false} for enabled,disabled
function setCommitAllowed(state) {
  state = !state; // enabled=true -> disabled=false
  if(state) changed = false; // if no commit allowed, then no changes have been made

  if(getCurrentPane() == "input") {
    $("b_add").disabled = state;
    $("b_save").disabled = state;
  } else {
    $("b_multi_add").disabled = state;
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
  if(confirm(gt.gettext("Are you sure you want to delete this flight?"))) {
    xmlhttpPost(URL_SUBMIT, false, "DELETE");
  } else {
    $("input_status").innerHTML = "<B>" + gt.gettext("Deleting flight cancelled.") + "</B>";
  }
}

// Handle change of transportation mode
// If 'mode' is supplied, force it
function changeMode(mode) {
  if(!mode) {
    mode=document.forms['inputform'].mode.value;
  }
  $('icon_airline').src = modeicons[mode];
  $('icon_airline').title = Gettext.strargs(gt.gettext("Search for %1"), [modeoperators[mode]]);
  calcDuration('AIRPORT'); // recompute duration estimate
  markAsChanged(true);
}

// Handle the "add new airports" buttons
function popNewAirport(type) {
  url = '/html/apsearch';
  if(type) {
    input_toggle = type;
    apid = $(type + 'id').value;
  }
  if(apid != 0) {
    url += "?apid=" + apid;
  }
  window.open(url, 'Airport', 'width=550,height=580,scrollbars=yes');
}

// Read in newly added airport (from Airport Search)
// (new for this map, that is, not necessarily DB)
function addNewAirport(data, name) {
  var element = input_toggle;
  $(element).value = name;
  $(element).style.color = '#000000';
  $(element + 'id').value = data;
  replicateSelection(element);
  markAirport(element);
  markAsChanged(true);
}

// Handle the "add new airlines" buttons
function popNewAirline(type, name, mode) {
  if(type) {
    input_al_toggle = type;
  }
  url = '/html/alsearch';
  if(name) {
    url += "?name=" + encodeURIComponent(name) + "&mode=" + mode;
  }
  window.open(url, 'Airline', 'width=500,height=580,scrollbars=yes');
}

// Read in newly added airline
function addNewAirline(alid, name, mode) {
  markAsChanged();
  changeMode(mode);
  selectInSelect(inputform.mode, mode);

  // Check if airline was listed already
  if(selectAirline(alid, true)) {
    return;
  }

  // Nope, we need to add it to filter options
  var al_select = document.forms['filterform'].Airlines;
  var elOptNew = document.createElement('option');
  if (name.length > SELECT_MAXLEN) {
    // Three dots in a proportional font is about two chars...
    elOptNew.text = name.substring(0,SELECT_MAXLEN - 2) + "...";
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
  $(input_al_toggle).value = name;
  $(input_al_toggle + 'id').value = alid;
  $(input_al_toggle).style.color = "#000";
}

//
// Inject apid into hidden src/dst_apid field after new airport is selected, and draw on map
//
function getSelectedApid(text, li) {
  var element = text.id;
  $(element).style.color = '#000000';
  $(element + 'id').value=li.id;
  replicateSelection(element);
  markAirport(element);
  markAsChanged(true); // new airport, force refresh
  autocompleted[element] = true;
}

//
// Inject alid into hidden alid field after new plane type is selected
//
function getSelectedAlid(text, li) {
  var element = text.id;
  $(element).style.color = '#000000';
  $(element + 'id').value=li.id;
  markAsChanged(true); // new airline, force refresh
  autocompleted[element] = true;
}

function getSelectedPlid(text, li) {
  $('plane_id').value=li.id;
}

//
// Handle the "add new/edit trip" buttons in input
// thisTrip can be "ADD" (new), "EDIT" (edit selected), or a numeric trip id (edit this)
//
function editTrip(thisTrip) {
  var url = '/html/trip';
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
  if(!trips) {
    $("input_trip_select").innerHTML = "<select style='width: 100px' name='trips'></select>";
    trips = document.forms['inputform'].trips;
  }
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

// When user has manually entered an airport code, try to match it
// "type" contains the element name
function airportCodeToAirport(type) {
  // Ignore autocomplete results
  if(autocompleted[type]) {
    autocompleted[type] = false;
    return;
  }
  input_toggle = type;
  markAsChanged(true);

  // Is it blank?
  if($(type).value == "") {
    $(type + 'id').value = 0;
    autocompleted[type] = false;
    return;
  }

  // Try to match against existing airports
  // TODO: Also match against marker.name
  var code = $(type).value.toUpperCase();
  var markers = airportLayer.markers;
  var found = false;
  for(m = 0; m < markers.length; m++) {
    if(markers[m].code == code) {
      selectAirport(markers[m].apid, true);
      found = true;
      break;
    }
  }

  // If not found, dig up from DB
  if (!found) {
    xmlhttpPost(URL_GETCODE, code, type);
  }
}

// User has entered invalid input: clear apid, turn field red (unless empty) and remove marker
function invalidateAirport(type) {
  if($(type).value != "" && $(type).value != $(type).hintText) {
    $(type).style.color = '#FF0000';
  }
  $(type + 'id').value = 0;
  markAirport(type);
}

// When user has entered flight number, try to match it to airline
// type: element invoked
function flightNumberToAirline(type) {
  markAsChanged();
  if(type == "NUMBER") {
    // This is a flight number
    var flightNumber = document.forms['inputform'].number.value.toUpperCase();
    document.forms['inputform'].number.value = flightNumber;

    // Ignore all-numeric flight numbers 
    if(re_numeric.test(flightNumber)) {
      return;
    }

    // Does flight number start with IATA or ICAO code?
    if(flightNumber.length >= 2) {
      var found = false;
      var re_iata = /^([a-zA-Z0-9][a-zA-Z0-9]$|[a-zA-Z0-9][a-zA-Z0-9][ 0-9])/; // XX or XX[ ]N...
      var re_icao = /^([a-zA-Z][a-zA-Z][a-zA-Z]$|[a-zA-Z][a-zA-Z][a-zA-Z][ 0-9])/;  // XXX or XXX[ ]N...
      if(re_iata.test(flightNumber.substring(0,3))) {
	var airlineCode = flightNumber.substring(0, 2);
      } else if(re_icao.test(flightNumber.substring(0,4))) {
	var airlineCode = flightNumber.substring(0, 3);
      } else {
	// User has entered something weird, ignore it
	return;
      }
      type = "airline";
    }
  } else {
    // This is a manually entered airline name
    // Ignore autocompleted results
    if(autocompleted[type]) {
      autocompleted[type] = false;
      return;
    }

    // Is it blank?
    if($(type).value == "") {
      $(type + 'id').value = 0;
      return;
    }
    airlineCode = $(type).value;
  }

  // We've found something that looks like an airline code, so overwrite it into AIRLINE field
  if(airlineCode) {
    xmlhttpPost(URL_GETCODE, airlineCode, type);
  }
}

// Calculate duration of flight given user-entered arrival and departure time
// param 'AIRPORT': airport changed by user, recompute duration and time at destination
// param 'ARRIVAL': arrival time changed by user, recompute duration
// param 'DEPARTURE': date or time at source (departure) changed, recompute time at destination
// param 'DURATION': duration changed by user, recompute time at destination
// param 'DISTANCE': recalculate distance *only* if blanked
function calcDuration(param) {
  var days = 0, duration = 0;

  // Need both airports first
  if($('src_apid').value == 0 || $('dst_apid').value == 0) return;
  dst_time = $('dst_time').value.trim();
  if(dst_time == "" || dst_time == "HH:MM") {
    dst_time = 0;
  }

  switch(param) {
  case 'DISTANCE':
    // Reestimate *only* if blanked
    var distance = $('distance').value.trim();
    if(distance == "") {
      var lon1 = getX('src_ap');
      var lat1 = getY('src_ap');
      var lon2 = getX('dst_ap');
      var lat2 = getY('dst_ap');
      $('distance').value = gcDistance(lat1, lon1, lat2, lon2);
      $('distance').style.color = "#000";
    } else {
      if(! re_numeric.test(distance)) {
	$('distance').focus();
	$('distance').style.color = "#F00";
      } else {
	$('distance').style.color = "#000";
      }
    }
    markAsChanged();
    return; // always terminate here

  case 'ARRIVAL':
    // User has changed arrival time: recompute duration
    // User has blanked arrival time: recompute arrival time (using existing duration)
    if(dst_time != 0) {
      duration = 0;
      break;
    }
    // else fallthru

  case 'DURATION':
  case 'DEPARTURE':
    // Recompute arrival time based on user-changed/previously calculated duration
    // (if no duration is available, estimate it)
    var duration = $('duration').value.trim();
    if(duration != "") {
      if(! re_time.test(duration)) {
	$('duration').focus();
	$('duration').style.color = "#F00";
	return;
      }
      $('duration').style.color = "#000";
      duration = parseFloat(duration.split(":")[0]) + parseFloat(duration.split(":")[1] / 60);
      dst_time = 0;
      break;
    }
    // else no duration known, estimate and reset
    param = 'AIRPORT';
    // fallthru

  case 'AIRPORT':
    // User has changed airport, estimate duration based on distance then (30 min plus 1 hr/500 mi) and
    // compute time at destination
    var distance = $('distance').value;
    if(! re_numeric.test(distance)) {
      $('distance').focus();
      $('distance').style.color = "#F00";
      return;
    }
    $('distance').style.color = "#000";
    duration = ($('distance').value / modespeeds[getMode()]) + 0.5;
    dst_time = 0;
    break;

  default:
    alert("Error: Unknown parameter '" + param + "' at calcDuration()");
    return;
  }

  // Do we have a starting time?
  src_time = $('src_time').value;
  if(src_time != "" && src_time != "HH:MM") {
    // We do!  Does it make sense?
    if(! re_time.test(src_time)) {
      $('src_time').focus();
      $('src_time').style.color = "#F00";
      return;
    }
    src_time = parseFloat(src_time.split(":")[0]) + parseFloat(src_time.split(":")[1] / 60);
    
    // Do we have an arrival time?
    if(dst_time != 0) {
      // Yes, validate it
      if(! re_time.test(dst_time)) {
	$('dst_time').focus();
	$('dst_time').style.color = "#F00";
	return;
      }
      // Case 3: Need to determine duration
      dst_time = parseFloat(dst_time.split(":")[0]) + parseFloat(dst_time.split(":")[1] / 60);
      days = $('dst_days').value;
      if(days != "") {
	dst_time += parseInt($('dst_days').value) * 24;
      }
      duration = 0;
    }

    // Get timezones
    src_tz = getTZ('src_ap');
    dst_tz = getTZ('dst_ap');
    src_dst = getDST('src_ap');
    dst_dst = getDST('dst_ap');

    // Verify if DST is active
    // 2008-01-26[0],2008[1],20[2],01[3],26[4]
    src_date = re_date.exec($('src_date').value);
    if(! src_date) {
      // Nonsensical date
      return;
    }
    src_year = src_date[1];
    src_month = src_date[3];
    src_day = src_date[4];
    src_date = new Date();
    src_date = src_date.setFullYear(src_year, src_month - 1, src_day);
    if(checkDST(src_dst, src_date, src_year)) {
      src_tz += 1;
      src_dst = "Y";
    }
    if(checkDST(dst_dst, src_date, src_year)) {
      dst_tz += 1;
      dst_dst = "Y";
    }
    $('icon_clock').title =
      Gettext.strargs(gt.gettext("Departure UTC %1%2%3, Arrival UTC %4%5%6, Time difference %7 hours"),
		      [src_tz > 0 ? "+" : "", src_tz, src_dst == "Y" ? " (" + gt.gettext("DST") + ")" : "",
		       dst_tz > 0 ? "+" : "", dst_tz, dst_dst == "Y" ? " (" + gt.gettext("DST") + ")" : "",
		       dst_tz-src_tz]);
 
    // Case 2: Calculate arrival time from starting time and duration
    if(dst_time == 0) {
      dst_time = src_time + duration + (dst_tz-src_tz);
      days = Math.floor(dst_time / 24);
      dst_time = dst_time % 24;
      while(dst_time < 0) dst_time += 24;
      hours = Math.floor(dst_time);
      mins = Math.round((Math.abs(dst_time) % 1.0) * 60);
      if(mins < 10) {
	mins = "0" + mins + "";
      }
      $('dst_time').value = hours + ":" + mins;
      $('dst_time').style.color = '#000';
      if(days == 0) {
	$('dst_days').value = "";
	$('dst_days').style.display = "none";
      } else {
	if(days > 0) {
	  $('dst_days').value = Gettext.strargs(gt.gettext("+%1 day"), [days]);
	} else {
	  $('dst_days').value = Gettext.strargs(gt.gettext("-%1 day"), [days]);
	}
	$('dst_days').style.display = "inline";
      }
    }
    
    // Case 3: Calculate duration from arrival time and starting time
    if(duration == 0) {    
      duration = (dst_time-src_time) - (dst_tz-src_tz);
      if(duration < 0) duration += 24;
    }
  } else {
    // Case 1: Do nothing, just use estimated duration
    $('icon_clock').title = gt.gettext("Flight departure and arrival times");
  }

  if(param == "AIRPORT" || param == "ARRIVAL") {
    // Convert duration back to clock time (sexagesimal)
    hours = Math.floor(duration);
    mins = Math.round((Math.abs(duration) % 1.0) * 60);
    if(mins == 60) {
      mins = 0;
      hours++;
    }
    if(mins < 10) {
      mins = "0" + mins + "";
    }
    $('duration').value = hours + ":" + mins;
    $('duration').style.color = "#000";
  }
  markAsChanged();
}

// Add a temporary source or destination marker over currently selected airport
// Also calculates distance and duration (unless "quick" is true)
// type: "src_ap" or "dst_ap"
function markAirport(element, quick) {
  var size = new OpenLayers.Size(17, 17);
  var offset = new OpenLayers.Pixel(-(size.w/2), -(size.h/2));
  if(element.startsWith("src_ap")) {
    var icon = new OpenLayers.Icon('/img/icon_plane-src.png',size,offset);
  } else {
    var icon = new OpenLayers.Icon('/img/icon_plane-dst.png',size,offset);
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
    var lonlat = new OpenLayers.LonLat(x, y);
    var marker = new OpenLayers.Marker(lonlat, icon);
    airportLayer.addMarker(marker);
  }
  if(element.startsWith("src_ap")) {
    if(input_srcmarker) {
      airportLayer.removeMarker(input_srcmarker);
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
      airportLayer.removeMarker(input_dstmarker);
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
      lineLayer.addFeatures(input_line);
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
    airportLayer.removeMarker(input_srcmarker);
    input_srcmarker = null;
  }
  if(input_dstmarker) {
    airportLayer.removeMarker(input_dstmarker);
    input_dstmarker = null;
  }
  if(input_line) {
    lineLayer.removeFeatures(input_line);
    input_line = null;
  }
}

// Find the highest valid (defined && non-zero apid) airport in multiinput
function markingLimit(element) {
  if(element.startsWith("src_ap")) {
    return "src_ap1";
  }
  for(i = 1; i <= multiinput_rows; i++) {
    var ap = $("dst_ap" + i + 'id').value;
    if(!ap || ap == "" || ap == 0) {
      i--;  // this airport is no longer valid, so we use prev row as limit
      break;
    }
  }
  if(i == 0) return null; // no valid airports
  if(i > multiinput_rows) i = multiinput_rows; // otherwise it goes one over if all rows are valid
  return "dst_ap" + i;
}

// Swap airports around
// If "true" (manual), swap both
// If "false" (automatic), swap only top to bottom and restore top to original
function swapAirports(manual) {
  if(manual) {
    srcName = $('src_ap').value;
    srcData = $('src_apid').value;
  }
  // Clear out times (makes no sense to swap them)
  clearTimes();

  // Clone SRC from DST
  $('src_ap').value = $('dst_ap').value;
  $('src_apid').value = $('dst_apid').value;

  if(manual) {
    // Clone DST from SRC
    $('dst_ap').value = srcName;
    $('dst_apid').value = srcData;
    if(srcName != "") {
      $('dst_ap').style.color = "#000";
    }
  } else {
    // Clear out DST
    $('dst_ap').value = "";
    $('dst_apid').value = "";
  }

  // Redraw markers and airline codes
  markAirport("src_ap", true);
  markAirport("dst_ap", true);
  if(manual) {
    markAsChanged();
  }
}

// Given apid, find the matching airport and either pop it up (false) or mark it as selected (true)
// "quick" is passed to markAirport
function selectAirport(apid, select, quick) {
  var markers = airportLayer.markers;
  var found = false;
  for(m = 0; m < markers.length; m++) {
    if(markers[m].apid == apid) {

      // If "select" is true, we select the airport into the input form instead of popping it up
      if(select && (getCurrentPane() == "input" || getCurrentPane() == "multiinput")) {
	var marker = markers[m];
	var element = input_toggle;
	$(element).value = marker.name;
	$(element).style.color = "#000";
	$(element + 'id').value = marker.apdata;
	replicateSelection(element);
	markAirport(element, quick);
	markAsChanged(true);
	closePopup();
      } else {
	markers[m].events.triggerEvent("mousedown");
      }
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

// Change number of rows displayed in multiinput
function changeRows(type) {
  switch(type) {
  case gt.gettext("More"):
    if(multiinput_rows >= 3) {
      $('b_more').disabled = true;
    }
    if(multiinput_rows == 1) {
      $('b_less').disabled = false;
    }
    source = "dst_ap" + multiinput_rows;
    multiinput_rows++;
    var row = "row" + multiinput_rows;
    $(row).style.display = ""; // resolves to "table-row" in FF and "block" in IE...
    replicateSelection(source);
    break;

  case gt.gettext("Less"):
    if(multiinput_rows == 4) {
      $('b_more').disabled = false;
    }
    if(multiinput_rows == 2) {
      $('b_less').disabled = true;
    }
    $("row" + multiinput_rows).style.display = "none";
    multiinput_rows--;
    break;
  }

  markAirport('dst_ap' + multiinput_rows); // redraw flight path
}

// In multiinput mode, copy entered airport/airline/date into next row (when empty)
// Special argument "More" adds a row, "Less" removes one
function replicateSelection(source) {
  if(getCurrentPane() != "multiinput") return;

  switch(source) {
  case "dst_ap1":
  case "dst_ap2":
  case "dst_ap3":
  case "airline1":
  case "airline2":
  case "airline3":
    // Check if the row we're trying to replicate to is active; if no, abort
    idx = parseInt(source.charAt(source.length-1)) + 1;
    row = "row" + idx;
    if($(row).style.display == "none") return;

    target = "src_ap" + idx;
    date_target = "src_date" + idx;
    date_source = "src_date" + (idx - 1);
    al_target = "airline" + idx;
    al_source = "airline" + (idx - 1);
    break;

  default:
    return; // do nothing
  }
  if($(source + 'id').value != 0 && $(target + 'id').value == 0) {
    $(target).value = $(source).value;
    $(target).style.color = "#000";
    $(target + 'id').value = $(source + 'id').value;
  }
  if($(al_source + 'id').value != 0  && $(al_target + 'id').value == 0) {
    $(al_target).value = $(al_source).value;
    $(al_target).style.color = "#000";
    $(al_target + 'id').value = $(al_source + 'id').value;
  }
  if($(date_target).value == "") {
    $(date_target).value = $(date_source).value;
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
	$(input_al_toggle).value = al_select[index].value.split(";")[1];
	$(input_al_toggle).style.color = "#000";
	$(input_al_toggle + 'id').value = new_alid;
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
// Import flights
//
function openImport() {
  window.open('/html/import', 'Import', 'width=800,height=600,scrollbars=yes');
}

//
// Change settings
//
function settings() {
  location.href = '/html/settings';
}

//
// Let users log in by pressing ENTER (from onKeyPress) or TAB (from onChange)
// 
function submitEnter(e) {
  var keycode;
  if(e == "CHANGE") {
    if(logged_in == "pending") return true;
  } else {
    if (window.event) keycode = window.event.keyCode;
    else if (e) keycode = e.which;
    else return true;
  }

  if (keycode == 13 || e == "CHANGE") {
    logged_in = "pending";
    xmlhttpPost("/php/login.php");
  }
  return true;
}

//
// Login and logout
// param --
// null: "Normal" login from front page
// REFRESH: User has session open and is coming back
// NEWUSER: User using OF for the first time (or has zero flights)
//
function login(str, param) {
  var cols = str.split(";");
  var status = cols[0];
  var name = cols[1];
  $("loginstatus").style.display = 'inline';

  // Login successful
  if(status == "1") {
    prefs_editor = cols[2].trim();
    elite = cols[3];
    if(elite) elite = elite.trim();
    logged_in = true;
    $("loginform").style.display = 'none';
    $("controlpanel").style.display = 'inline';
    switch(param) {
    case "REFRESH":
      $("loginstatus").innerHTML = getEliteIcon(elite) + Gettext.strargs(gt.gettext("Logged in as <B>%1</B>"), [name]);
      break;

    case "NEWUSER":
      $("loginstatus").innerHTML = getEliteIcon(elite) + Gettext.strargs(gt.gettext("Welcome, <B>%1</B> !"), [name]);
      break;

    default:
      $("stats").innerHTML = "<i>" + gt.gettext("Loading") + "</i>";
      $("stats_ajax").style.display = "inline";
      $("loginstatus").innerHTML = getEliteIcon(elite) + Gettext.strargs(gt.gettext("Hi, <B>%1</B> !"), [name]);
      break;
    }
    
    switch(elite) {
    case "X":
      $("news").style.display = 'inline';
      $("news").innerHTML = getEliteIcon("X") +
	"<img src='/img/close.gif' height=17 width=17 onClick='JavaScript:closeNews()'> " + 
	gt.gettext("<b>Welcome back!</b>  We're delighted to see that you like OpenFlights.<br>Please <a href='/donate.html' target='_blank'>donate and help keep the site running</a>!");
      break;

    case "G":
    case "P":
      // Remove ad pane and manually force help to show
      $(getCurrentPane()).style.display = 'none';
      $("help").style.display = 'inline';
      paneStack[0] = "help";
      break;
    }

    if(param == "NEWUSER") {
      $("news").innerHTML =
	"<img src='/img/close.gif' height=17 width=17 onClick='JavaScript:closeNews()'> " + 
	Gettext.strargs(gt.gettext("<B>Welcome to OpenFlights!</b>  Click on %1 to start adding flights, or on %2 to load in existing flights from sites like FlightMemory."),
			[ "<input type='button' value='" + gt.gettext("New flight") + "' align='middle' onclick='JavaScript:newFlight()'>",
			  "<input type='button' value='" + gt.gettext("Import") + "' align='middle' onclick='JavaScript:openImport()'>" ]);
      $("news").style.display = 'inline';
    } else {
      closeNews();
    }

    // in a NEWUSER or REFRESH, we've already drawn the map, so no need to redraw
    if(!param) {
      clearStack();
      clearMap();
      clearFilter(true);
    }
  } else {
    logged_in = false;
    $("loginstatus").innerHTML = "<B>" + name + "</B>";
    $("ajaxstatus").style.display = 'none';
  }
}

function logout(str) {
  logged_in = false;
  $("loginstatus").innerHTML = "<B>" + gt.gettext("You have been logged out.") + "</B>";
  $("stats").innerHTML = "<i>" + gt.gettext("Loading") + "</i>";
  $("stats_ajax").style.display = 'inline';
  $("loginform").style.display = 'inline';
  $("controlpanel").style.display = 'none';
  $(getCurrentPane()).style.display = 'none';
  $("ad").style.display = 'inline';
  paneStack[0] = "ad";
  clearStack();
  clearMap();
  clearFilter(true);
  closeNews();
  document.forms['login'].name.value = "";
  document.forms['login'].pw.value = "";
  document.forms['login'].name.focus();
}

// Get current transport mode
function getMode() {
  if(getCurrentPane() == "input") {
    mode = document.forms['inputform'].mode.value;
  } else {
    mode = "F";
  }
  return mode;
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
    $(currentPane).style.display = 'none';
  }
  $(newPane).style.display = 'inline';
  paneStack.push(newPane);
}

// Check if a named pane is already open, return index if yes
function findPane(pane) {
  for(i = 0; i < paneStack.length; i++) {
    if(paneStack[i] == pane) {
      return i;
    }
  }
  return null;
}

// Close current pane
// If the current pane is the last one, do nothing
function closePane() {
  if(paneStack.length == 1) return;

  var currentPane = paneStack.pop();
  var lastPane = getCurrentPane();
  if(currentPane == "input" || currentPane == "multiinput") {
    unmarkAirports();
    $("newairport").style.display = 'none';
  }
  if(currentPane == "result") {
    apid = 0;
  }
  $(currentPane).style.display = 'none';
  $(lastPane).style.display = 'inline';

  // If ad pane is now displayed, refresh it
  if(paneStack.length == 1 && paneStack[0] == "ad") refreshAd();
}

// Clear all panes until the base pane (ad)
function clearStack() {
  while(paneStack.length > 1) {
    closePane();
  }
}

function openDetailedInput(param) {
  // Does the user already have an input pane open?
  p = findPane("input");
  if(!p) p = findPane("multiinput");
  if(p) {
    // Have they changed it, and do they want to throw away the changes?
    if(hasChanged()) {
      switch(param) {
      case "ADD":
      case "COPY":
	msg = gt.gettext("You are already editing a flight. OK to discard your changes and add a new flight instead?");
	break;

	break;
      case "EDIT":
	msg = gt.gettext("You are already editing a flight. OK to discard your changes and edit this flight instead?");
	break;
      }
      if(! confirm(msg)) {
	return;
      }
    }
    $(paneStack[p]).style.display = "none";
    paneStack.splice(p, 1); // remove the previous "input" pane from stack
  }
  openPane("input");

  switch(param) {
  case "ADD":
    clearInput();
    // fall thru

  case "COPY":
    $("addflighttitle").style.display = 'inline';
    $("addflightbuttons").style.display = 'inline';
    $("editflighttitle").style.display = 'none';
    $("editflightbuttons").style.display = 'none';
    break;

  case "EDIT":
    $("addflighttitle").style.display = 'none';
    $("addflightbuttons").style.display = 'none';
    $("editflighttitle").style.display = 'inline';
    $("editflightbuttons").style.display = 'inline';
  }
  input_toggle = "src_ap";
  input_al_toggle = "airline";
  $("newairport").style.display = 'inline';
  $("input_status").innerHTML = "";
}

// Open basic (multiflight) editor
function openBasicInput(param) {
  // Does the user already have an input pane open?
  p = findPane("input");
  if(!p) p = findPane("multiinput");
  if(p) {
    // Have they changed it, and do they want to throw away the changes?
    if(hasChanged()) {
      if(! confirm(gt.gettext("You are already editing a flight. OK to discard your changes and add a new flight instead?"))) {
	return;
      }
    }
    $(paneStack[p]).style.display = "none";
    paneStack.splice(p, 1); // remove the previous "input" pane from stack
  }

  openPane("multiinput");
  $("multiinput_status").innerHTML = "";
  clearInput();
  input_toggle = multiinput_order[0];
  input_al_toggle = "airline1";
}

function closeInput() {
  if(hasChanged()) {
    if(! confirm(gt.gettext("Changes made to this flight have not been saved.  OK to discard them?"))) {
      return;
    }
  }
  closePane();

  // Reload flights list if we were editing flights, or
  // user had a result pane open when he opened new flight editor

  if(getCurrentPane() == "result" &&
     ($("editflighttitle").style.display == 'inline' ||
      $("addflighttitle").style.display == 'inline')) {
    xmlhttpPost(URL_FLIGHTS, 0, "RELOAD");
  }
}

// Clear out (restore to defaults) the time indicators in the editor
function clearTimes() {
  $('src_time').value = $('src_time').hintText;
  $('dst_time').value = $('dst_time').hintText;
  $('dst_days').value = "";
  $('src_time').style.color = '#888';
  $('dst_time').style.color = '#888';
  $('dst_days').style.display = "none";
}

// Clear out (restore to defaults) the input box
function clearInput() {
  var today = new Date();
  resetHintTextboxes();
  if(getCurrentPane() == "input") {
    var form = document.forms["inputform"];
    form.src_date.value = today.getFullYear()+ "-" + (today.getMonth() + 1) + "-" + today.getDate();
    form.src_date.focus();
    form.src_apid.value = 0;
    form.dst_apid.value = 0;
    form.dst_days.value = "";
    clearTimes();
    $('duration').value = "";
    $('duration').style.color = "#000";
    $('distance').value = "";
    $('distance').style.color = "#000";
    form.number.value = "";
    form.airlineid.value = 0;
    form.seat.value = "";
    form.seat_type.selectedIndex = 0;
    form.mode.selectedIndex = 0;
    changeMode("F");
    form.plane_id.value = "";
    form.registration.value = "";
    form.note.value = "";
    if(form.trips) form.trips.selectedIndex = 0;
  } else {
    var form = document.forms["multiinputform"];
    for(i = 0; i < multiinput_ids.length; i++) {
      $(multiinput_ids[i]).value = 0;
    }
    form.src_date1.value = today.getFullYear()+ "-" + (today.getMonth() + 1) + "-" + today.getDate();
    form.src_ap1.focus();
    form.src_ap1.style.color = '#000000';
  }
  unmarkAirports();
  for(a = 0; a < autocompleted.length; a++) {
    autocompleted[a] = false;
  }
  setCommitAllowed(false);
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
  $("news").style.display = 'none';
}

// user has selected a new field in the extra filter
function setExtraFilter() {
  var key = document.forms['filterform'].Extra.value;
  var span = "";
  switch(key) {
  case "": // none (More...)
    $('filter_extra_span').innerHTML = "";
    refresh(true);
    return;

  case "class":
    span = createSelectFromArray('filter_extra_value', classes, "refresh(true)");
    break;

  case "distgt":
  case "distlt":
    span = "<input type='text' style='width: 50px' id='filter_extra_value' class='date' onChange='JavaScript:refresh(true)'> mi";
    break;

  case "mode":
    span = createSelectFromArray('filter_extra_value', modenames, "refresh(true)");
    break;

  case "reason":
    span = createSelectFromArray('filter_extra_value', reasons, "refresh(true)");
    break;
    
  case "reg":
  case "note":
    span = "<input type='text' style='width: 100px' id='filter_extra_value' class='date' onChange='JavaScript:refresh(true)'>";
    break;
  }
  $('filter_extra_span').innerHTML = span;
}

// refresh_all: false = only flights, true = reload everything
function clearFilter(refresh_all) {
  var form = document.forms['filterform'];

  // Do not allow trip filter to be cleared if it's set in URL
  if(form.Trips && parseArgument("trip") == 0) {
    form.Trips.selectedIndex = 0;
  }
  form.Years.selectedIndex = 0;
  form.Extra.selectedIndex = 0;
  $('filter_extra_span').innerHTML = "";
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
  var d=$('ad');
  if(d){
    var s=d.getElementsByTagName('iframe');
    if(s && s.length){
      var src = (s[0].src.split(/&xtime=/))[0];
      s[0].src = src + '&xtime='+new Date().getTime();
    }
  }
  return true;
}
