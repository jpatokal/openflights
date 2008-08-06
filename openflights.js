/**
 * openflights.js -- for openflights.org
 * by Jani Patokallio <jpatokal@iki.fi>
 */

var map, drawControls, selectControl, selectedFeature, lineLayer, currentPopup;
var trid = 0, alid = 0, apid = 0;
var input = false, initializing = true;
var input_srcmarker, input_srcpoint, input_dstmarker, input_dstpoint, input_toggle;

var URL_AIRPORT = "/php/airport.php";
var URL_FILTER = "/php/filter.php";
var URL_GETCODE = "/php/getcode.php";
var URL_INPUT = "/php/input.php";
var URL_MAP = "/php/map.php";
var URL_PREINPUT = "/php/preinput.php";
var URL_STATS = "/php/stats.php";

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
                                           {transitionEffect: 'resize'},
                                           {wrapDateLine: true}
      );

    var jpl_wms = new OpenLayers.Layer.WMS( "Geographical (NASA)",
                    "http://t1.hypercube.telascience.org/cgi-bin/landsat7", 
                    {layers: "landsat7"},
		    {transitionEffect: 'resize'},
                    {wrapDateLine: true}
      );
    jpl_wms.setVisibility(false);

    lineLayer = new OpenLayers.Layer.PointTrack("My Flights",
                {dataFrom: OpenLayers.Layer.PointTrack.dataFrom.SOURCE_NODE,
                 styleMap: new OpenLayers.StyleMap({
                    strokeColor: "#ee9900",
                    strokeOpacity: 1,
                    strokeWidth: 2,
                    hoverStrokeColor: "red",
                    hoverStrokeOpacity: 1,
                    hoverStrokeWidth: 2
                  })
                });


    airportLayer = new OpenLayers.Layer.Markers("My Airports");

    map.addLayers([ol_wms, jpl_wms, lineLayer, airportLayer]);

    selectControl = new OpenLayers.Control.SelectFeature(lineLayer,
      {onSelect: onFeatureSelect, onUnselect: onFeatureUnselect});
    drawControls = {
      select: selectControl
    };
    map.addControl(drawControls.select);

    //map.setCenter(new OpenLayers.LonLat(0, 0), 0);
    map.zoomToMaxExtent();

    xmlhttpPost(URL_MAP, 0);
 }    

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

function drawLine(lineLayer, x1, y1, x2, y2, flight) {
  if(x2 < x1) {
    var tmpx = x1;
    var tmpy = y1;
    x1 = x2;
    y1 = y2;
    x2 = tmpx;
    y2 = tmpy;
  }
  var sourceNode = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(x1, y1), {flight: flight} );
  var targetNode = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(x2, y2), {flight: flight} );
  
  if(Math.abs(x1-x2) < 180) {
    //var westNode1 = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(x1-360, y1), {flight: flight});
    //var westNode2 = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(x2-360, y2));
    var eastNode1 = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(x1+360, y1), {flight: flight});
    var eastNode2 = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(x2+360, y2));
    lineLayer.addNodes([sourceNode, targetNode]);
    //lineLayer.addNodes([westNode1, westNode2]);
    //lineLayer.addNodes([eastNode1, eastNode2]);
    
  } else {
    var dy = y2 - y1;
    var dx1 = 180 - Math.abs(x1);
    var dx2 = 180 - Math.abs(x2);
    var y = parseFloat(y1) + (dx1 / (dx1 + dx2)) * dy;
    
    //var westNode1 = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(-540, y), {flight: flight});
    //var westNode2 = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(x1-360, y1));
    //lineLayer.addNodes([westNode1, westNode2]);
    
    //var westNode3 = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(x2-360, y2), {flight: flight});
    var bNode1 = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(-180, y), {flight: flight});
    //lineLayer.addNodes([westNode3, bNode1, sourceNode]);
    lineLayer.addNodes([bNode1, sourceNode]);
    
    //var eastNode3 = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(x1+360, y1));
    var bNode2 = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(180, y), {flight: flight});
    //lineLayer.addNodes([targetNode, bNode2, eastNode3]);
    lineLayer.addNodes([targetNode, bNode2]);
    
    //var eastNode1 = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(540, y), {flight: flight});
    //var eastNode2 = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(x2+360, y2));
    //lineLayer.addNodes([eastNode1, eastNode2]);
  }
}

function drawAirport(airportLayer, apid, x, y, name, code, city, country, count) {
  var desc = name + " (<B>" + code + "</B>)<br><small>" + city + ", " + country + "</small><br>Flights: " + count;
  desc += " <input type=\"button\" value=\"View\" align=\"middle\" onclick='JavaScript:xmlhttpPost(\"" + URL_AIRPORT + "\"," + apid + ", \"" + desc + "\")'>";
  desc = "<img src=\"img/close.gif\" onclick=\"JavaScript:closePopup();\" width=17 height=17> " + desc;


  var iconfile, size;
  if(count > 4) {
    iconfile = 'img/icon_plane-19x19.png';
    size = new OpenLayers.Size(19,19);
  } else if(count <= 2) {
    iconfile = 'img/icon_plane-15x15.png';
    size = new OpenLayers.Size(15,15);
  } else {
    iconfile = 'img/icon_plane-17x17.png';
    size = new OpenLayers.Size(17,17);
  }
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
      closeResult();
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
      if(strURL == URL_AIRPORT) {
	updateAirport(self.xmlHttpReq.responseText, param);
      }
      if(strURL == URL_GETCODE) {
	updateCodes(self.xmlHttpReq.responseText);
      }
      if(strURL == URL_MAP) {
	updateMap(self.xmlHttpReq.responseText);
        if(initializing) {
	  updateFilter(self.xmlHttpReq.responseText);
	  initializing = false;
        }
      }
      if(strURL == URL_PREINPUT) {
	inputFlight(self.xmlHttpReq.responseText);
      }
      if(strURL == URL_STATS) {
	updateStats(self.xmlHttpReq.responseText);
      }
      document.getElementById("ajaxstatus").style.visibility = 'hidden';
    }
  }
  
  if(strURL == URL_AIRPORT) {
    // Don't reload the current airport
    if(id == apid) {
      return;
    }
    apid = id;
  }
  if(strURL == URL_GETCODE) {
    var form = document.forms['inputform'];
    var src = form.src_ap_code.value;
    var dst = form.dst_ap_code.value;
    var flightNumber = form.number.value;
    var airlineCode = form.airline_code.value;
    if(param == "SRC" && src) {
      document.getElementById("src_ap_ajax").style.visibility = 'visible';
      query = 'src=' + escape(src);
    }
    if(param == "DST" && dst) {
      document.getElementById("dst_ap_ajax").style.visibility = 'visible';
      query = 'dst=' + escape(dst);
    }
    if(param == "NUMBER" && flightNumber.length >= 2) {
      airlineCode = flightNumber.substring(0, 2);
      param = "AIRLINE";
    }
    if(param == "AIRLINE" && airlineCode) {
      document.getElementById("airline_ajax").style.visibility = 'visible';
      query = 'airline=' + escape(airlineCode);
    }
  } else {
    document.getElementById("ajaxstatus").style.visibility = 'visible';
    var query = getquerystring(id);
  }
  self.xmlHttpReq.send(query);
}

function getquerystring(id) {
  var form = document.forms['filterform'];
  trid = form.Trips.value;
  alid = form.Airlines.value;
  qstr = 'id=' + escape(id) + '&' +
    'trid=' + escape(trid) + '&' +
    'alid=' + escape(alid) + '&' +
    'init=' + escape(initializing);
  return qstr;
}

// Set up filter options from database result
function updateFilter(str) {
  var master = str.split("\n");
  var trips = master[3];
  var airlines = master[4];

  var tripselect = "Trips " + createSelect("Trips", "All flights", trid, trips.split("\t"), 20);
  document.getElementById("filter_tripselect").innerHTML = tripselect;

  var airlineselect = "Airlines " + createSelect("Airlines", "All airlines", alid, airlines.split("\t"), 20);
  document.getElementById("filter_airlineselect").innerHTML = airlineselect;

  /*  if(trid == 0) {
    document.getElementById("maptitle").innerHTML = "";
    } */
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
 */ 
function createSelect(name, allopts, id, rows, maxlen, hook) {
  if(hook) {
    var select = "<select name=\"" + name + "\" onChange='JavaScript:" + hook + "(\"" + name + "\")'" + ">";
  } else {
    var select = "<select name=\"" + name + "\"><option value=\"0\">" + allopts + "</option>";
  }
  select += "<option value=\"0\">" + allopts + "</option>";

  var selected = "";
  for (r in rows) {
    var col = rows[r].split(";");
    var name = col[1];
    var url = col[2];
    if(col[0] == id) {
      selected = " SELECTED";
      //      document.getElementById("maptitle").innerHTML = name + " <small>(<a href=\"" + url + "\">link</a>)<small>";
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
  stats = "<b>Statistics</b><br>" +
    "Flights: " + col[0] + "<br>" +
    "Distance: " + col[1] + " mi<br>" +
    "Duration: " + days + " days " + hours + " hrs " + min + "min" +
    "<input type=\"button\" value=\"More...\" align=\"middle\" onclick='JavaScript:xmlhttpPost(\"" + URL_STATS + "\")'>";
  document.getElementById("stats").innerHTML = stats;
    
  var rows = flights.split(":");
  for (r in rows) {
    var col = rows[r].split(",");
    // x1, y1, x2, y2, count
    drawLine(lineLayer, parseFloat(col[1]), parseFloat(col[2]), parseFloat(col[4]), parseFloat(col[5]), col[0]);
  }
  
  var rows = airports.split(":");
  for (r in rows) {
    var col = rows[r].split(",");
    // apid, x, y, name, code, city, country, count
    drawAirport(airportLayer, col[0], col[1], col[2], col[3], col[4], col[5], col[6], col[7]);
  }
}

function updateAirport(str, desc) {
  openResult();
  table = "<img src=\"img/close.gif\" onclick=\"JavaScript:closeResult();\" width=17 height=17> ";
  if(str == "") {
    table += "<i>No flights originating at this airport found.</i>";
  } else {
    table += desc.replace(/\<br\>/g, " &mdash; ");
    table += "<table class=\"sortable\" id=\"apttable\" cellpadding=\"0\" cellspacing=\"0\">";
    table += "<tr><th>From</th><th>To</th><th>Flight</th><th>Date</th><th>Distance</th><th>Duration</th><th>Seat</th><th>Seat type</th><th>Class</th><th>Reason</th></tr>";
    var rows = str.split("\t");
    for (r in rows) {
      var col = rows[r].split(",");
      // src_iata, src_apid, dst_iata, dst_apid, flight code, date
      table += "<tr><td><a href=\"#stats\" onclick=\"JavaScript:selectAirport(" + col[1] + ");\">" + col[0] + "</a></td>" +
	"<td><a href=\"#stats\" onclick=\"JavaScript:selectAirport(" + col[3] + ");\">" + col[2] + "</a></td>" +
	"<td>" + col[4] + "</td><td>" + col[5] + "</td><td>" + col[6] + "</td><td>" + col[7] +
	"</td><td>" + col[8] + "</td><td>" + col[9] + "</td><td>" + col[10] + "</td><td>" + col[11] + "</td></tr>";
    }
    table += "</table>";
  }
  document.getElementById("result").innerHTML = table;
  // Refresh sortables code
  sortables_init();
}

function updateStats(str) {
  openResult();
  if(str == "") {
    table = "<i>Statistics calculation failed!</i>";
  } else {
    var master = str.split("\n");
    var airports = master[0];
    var airlines = master[1];
    var planes = master[2];
    bigtable = "<table><td>"
      
      table = "<table style=\"border-spacing: 10px 0px\">";
    table += "<tr><th colspan=3>Top 10 Airports</th></tr>"
      var rows = airports.split(":");
    for (r in rows) {
      var col = rows[r].split(",");
      // name, iata, count, apid
      desc = col[0] + " (" + col[1] + ")";
      table += "<tr><td><a href=\"#stats\" onclick=\"JavaScript:selectAirport(" + col[3] + ");\">" + desc + "</a></td><td>" + col[2] + "</td>";
    }
    table += "</table>";
    bigtable += table + "</td><td>";
    
    table = "<table style=\"border-spacing: 10px 0px\">";
    table += "<tr><th colspan=3>Top 10 Airlines</th></tr>"
      var rows = airlines.split(":");
    for (r in rows) {
      var col = rows[r].split(",");
      // name, count, apid
      table += "<tr><td><a href=\"#stats\" onclick=\"JavaScript:selectAirline(" + col[2] + ");\">" + col[0] + "</a></td><td>" + col[1] + "</td>";
    }
    table += "</table>";
    bigtable += table + "</td><td>";
    
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
    document.getElementById("src_ap_ajax").style.visibility = 'hidden';
  }
  if(type == "DST") {
    select = document.forms['inputform'].dst_ap;
    document.getElementById("dst_ap_ajax").style.visibility = 'hidden';
  }
  if(type == "AIRLINE") {
    select = document.forms['inputform'].airline;
    document.getElementById("airline_ajax").style.visibility = 'hidden';
  }
  if(select) {
    select.options.length = lines.length - 2; // redimension select
    select.selectedIndex = 0;

    for(l in lines) {
      if(l == 0 || l == lines.length - 1) continue; // already processed
      var col = lines[l].split(";");
      select[l-1].value = col[0]; // id
      select[l-1].text = col[1]; // name
    }

    // Rebuilding select doesn't count as onChange, so we trigger manually
    if(type == "SRC") selectNewAirport("src_ap");
    if(type == "DST") selectNewAirport("dst_ap");
  }
}

function inputFlight(str) {
  openInput();

  var today = new Date();
  var month = today.getMonth() + 1;
  var day = today.getDate();
  var year = today.getFullYear();
  document.forms['inputform'].src_time.value = day + "." + month + "." + year;

  var master = str.split("\n");
  var airports = master[0];
  var airlines = master[1];
  var planes = master[2];

  var airportselect = createSelect("src_ap", "-", 0, airports.split("\t"), 0, "selectNewAirport");
  document.getElementById("input_src_ap_select").innerHTML = airportselect;

  airportselect = createSelect("dst_ap", "-", 0, airports.split("\t"), 0, "selectNewAirport");
  document.getElementById("input_dst_ap_select").innerHTML = airportselect;

  var airlineselect = createSelect("airline", "-", 0, airlines.split("\t"));
  document.getElementById("input_airline_select").innerHTML = airlineselect;

  var planeselect = createSelect("plane", "-", 0, planes.split("\t"));
  document.getElementById("input_plane_select").innerHTML = planeselect;
}

// When user has entered airline code, try to match it to airline
function flightNumberToAirline(str) {
  if(str == "NUMBER") {
    var flightNumber = document.forms['inputform'].number.value;
  } else {
    var flightNumber = document.forms['inputform'].airline_code.value;
  }
  if(flightNumber.length >= 2) {
    var found = false;
    var airlineCode = flightNumber.substring(0, 2);
    var al_select = document.forms['inputform'].airline;
    for(index = 0; index < al_select.length; index++) {
      if(al_select[index].value.substring(0, 2) == airlineCode) {
	found = true;
	al_select.selectedIndex = index;
	break;
      }
    }

    // Couldn't find it entered yet, so pull code from database
    if(!found) {
      xmlhttpPost(URL_GETCODE, 0, str);
    }
  }
}

// When user has entered airport code, try to match it to airport
function codeToAirport(type) {
  var found = false;
  var apid;
  if(type == "SRC") {
    var ap_select = document.forms['inputform'].src_ap;
    var airportCode = document.forms['inputform'].src_ap_code.value;
  } else {
    var ap_select = document.forms['inputform'].dst_ap;
    var airportCode = document.forms['inputform'].dst_ap_code.value;
  }
  for(index = 0; index < ap_select.length; index++) {
    if(ap_select[index].value.substring(0, 3) == airportCode) {
      found = true;
      ap_select.selectedIndex = index;
      apid = ap_select[index].value.split(':')[1];
      break;
    }
  }
  if(found) {
    // Altering value doesn't count as onChange, so we trigger manually
    if(type == "SRC") selectNewAirport("src_ap");
    if(type == "DST") selectNewAirport("dst_ap");
  } else {
    xmlhttpPost(URL_GETCODE, 0, type);
  }
}

// Add a temporary source or destination marker over currently selected airport
// type: "src_ap" or "dst_ap"
function selectNewAirport(type) {
  var size = new OpenLayers.Size(17, 17);
  var offset = new OpenLayers.Pixel(-(size.w/2), -(size.h/2));
  if(type == "src_ap") {
    var select = document.forms['inputform'].src_ap;
    var icon = new OpenLayers.Icon('img/icon_plane-src.png',size,offset);
  } else {
    var select = document.forms['inputform'].dst_ap;
    var icon = new OpenLayers.Icon('img/icon_plane-dst.png',size,offset);
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
      //input_srcpoint = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(x,y), {foo: "bar"}, {strokeColor: "red"});
    }
    if(apid > 0) {
      document.forms['inputform'].src_ap_code.value = iata;
      input_srcmarker = marker;
      input_toggle = "DST";
    } else {
      document.forms['inputform'].src_ap_code.value = "";
      input_srcmarker = 0;
    }
  } else {
    if(input_dstmarker) {
      airportLayer.removeMarker(input_dstmarker);
      //input_dstpoint.geometry.move(x,y);
    } else {
      //input_dstpoint = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(x,y));
      //lineLayer.addNodes([input_srcpoint, input_dstpoint]);
    }
    if(apid > 0) {
      document.forms['inputform'].dst_ap_code.value = iata;
      input_dstmarker = marker;
      input_toggle = "SRC";
    } else {
      document.forms['inputform'].dst_ap_code.value = "";
      input_dstmarker = "";
    }
  }
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
      clearFilter();
    }
  }
}

// Given alid, find it in filter and refresh view
function selectAirline(new_alid) {
  var al_select = document.forms['filterform'].Airlines;
  for(index = 0; index < al_select.length; index++) {
    if(al_select[index].value == new_alid) {
      al_select.selectedIndex = index;
    }
  }
  refresh();
}

// Functions for swapping between lower panes
function openResult() {
  document.getElementById("help").style.display = 'none';
  document.getElementById("input").style.display = 'none';
  document.getElementById("result").style.display = 'inline';
  input = false;
}

function openInput() {
  document.getElementById("help").style.display = 'none';
  document.getElementById("input").style.display = 'inline';
  document.getElementById("result").style.display = 'none';
  input = true;
  input_toggle = "SRC";
}

function closeResult() {
  document.getElementById("result").style.display = 'none';
  document.getElementById("help").style.display = 'inline';
}

function showHelp() {
  closeResult();
  input = false;
}

function closePopup() {
  // close any previous popups
  if(currentPopup && currentPopup != this.popup) {
    currentPopup.hide();
    currentPopup = null;
  }
}

function clearFilter() {
  var tr_select = document.forms['filterform'].Trips;
  tr_select.selectedIndex = 0;
  selectAirline(0);
}

// Refresh user's display after change in filter
// (loads new flight data and stats, but does not update filter options)
function refresh() {
  closePopup();
  xmlhttpPost(URL_MAP, 0, false);
}
