/**
 * openflights.js -- for openflights.org
 * by Jani Patokallio <jpatokal@iki.fi>
 */

var map, drawControls, selectControl, selectedFeature, lineLayer, currentPopup;
var trid = 0, alid = 0, apid = 0, initializing = true;
var helptext;

var URL_AIRPORT = "/php/airport.php";
var URL_FILTER = "/php/filter.php";
var URL_FLIGHT = "/php/airport.php";
var URL_MAP = "/php/map.php";
var URL_STATS = "/php/stats.php";

function init(){
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
        new OpenLayers.Control.OverviewMap(),
      ] });

    var ol_wms = new OpenLayers.Layer.WMS( "Political (Metacarta)",
                                           "http://labs.metacarta.com/wms/vmap0?",
		                           {layers: 'basic'},
                                           //{transitionEffect: 'resize'},
                                           {wrapDateLine: true}
      );

    var jpl_wms = new OpenLayers.Layer.WMS( "Geographical (NASA)",
                    "http://t1.hypercube.telascience.org/cgi-bin/landsat7", 
                    {layers: "landsat7"},
		    //{transitionEffect: 'resize'},
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
                    hoverStrokeWidth: 2,
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
    var westNode1 = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(x1-360, y1), {flight: flight});
    var westNode2 = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(x2-360, y2));
    var eastNode1 = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(x1+360, y1), {flight: flight});
    var eastNode2 = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(x2+360, y2));
    lineLayer.addNodes([sourceNode, targetNode]);
    lineLayer.addNodes([westNode1, westNode2]);
    lineLayer.addNodes([eastNode1, eastNode2]);
    
  } else {
    var dy = y2 - y1;
    var dx1 = 180 - Math.abs(x1);
    var dx2 = 180 - Math.abs(x2);
    var y = parseFloat(y1) + (dx1 / (dx1 + dx2)) * dy;
    
    var westNode1 = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(-540, y), {flight: flight});
    var westNode2 = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(x1-360, y1));
    lineLayer.addNodes([westNode1, westNode2]);
    
    var westNode3 = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(x2-360, y2), {flight: flight});
    var bNode1 = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(-180, y), {flight: flight});
    lineLayer.addNodes([westNode3, bNode1, sourceNode]);
    
    var eastNode3 = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(x1+360, y1));
    var bNode2 = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(180, y), {flight: flight});
    lineLayer.addNodes([targetNode, bNode2, eastNode3]);
    
    var eastNode1 = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(540, y), {flight: flight});
    var eastNode2 = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(x2+360, y2));
    //lineLayer.addNodes([eastNode1, eastNode2]);
  }
}

function drawAirport(airportLayer, apid, x, y, name, code, city, country, count) {
  var desc = name + " (<B>" + code + "</B>)<br><small>" + city + ", " + country + "</small><br>Flights: " + count;
  desc += " <input type=\"button\" value=\"View\" align=\"middle\" onclick='JavaScript:xmlhttpPost(\"" + URL_AIRPORT + "\"," + apid + ", \"" + desc + "\")'>";
  desc = "<img src=\"img/close.gif\" onclick=\"JavaScript:closePopup();\" width=17 height=17> " + desc;


  var iconfile, size;
  if(count > 4) {
    iconfile = 'img/icon_plane-25x25.png';
    size = new OpenLayers.Size(25,25);
  } else if(count <= 2) {
    iconfile = 'img/icon_plane-15x15.png';
    size = new OpenLayers.Size(15,15);
  } else {
    iconfile = 'img/icon_plane-20x20.png';
    size = new OpenLayers.Size(20,20);
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

  var markerClick = function (evt) {
    closePopup();

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
      if(strURL == URL_MAP) {
	updateMap(self.xmlHttpReq.responseText);
        if(initializing) {
	  updateFilter(self.xmlHttpReq.responseText);
	  initializing = false;
        }
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
  document.getElementById("ajaxstatus").style.visibility = 'visible';
  self.xmlHttpReq.send(getquerystring(id));
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

  var tripselect = createSelect("Trips", "All flights", trid, trips.split("\t"));
  document.getElementById("filter_tripselect").innerHTML = tripselect;

  var airlineselect = createSelect("Airlines", "All airlines", alid, airlines.split("\t"));
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
 * rows: rows of array
 */ 
function createSelect(name, allopts, id, rows) {
  var select = name + " <select name=\"" + name + "\"><option value=\"0\">" + allopts + "</option>";
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
    if (name.length > 20) {
      name = name.substring(0,17) + "...";
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

// Given apid, find the matching airport marker and pop it up
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

function openResult() {
  document.getElementById("help").style.display = 'none';
  document.getElementById("input").style.display = 'none';
  document.getElementById("result").style.display = 'inline';
}

function closeResult() {
  document.getElementById("result").style.display = 'none';
  document.getElementById("help").style.display = 'inline';
}

function showHelp() {
  closeResult();
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
