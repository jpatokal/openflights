/**
 * OpenFlights Widget Demo -- for openflights.org
 * by Jani Patokallio <jani at contentshare dot sg>
 */

// Set to "true" to enable debugging messages
var OF_DEBUG = true;

// avoid pink tiles
OpenLayers.IMAGE_RELOAD_ATTEMPTS = 3;
OpenLayers.Util.onImageLoadErrorColor = "transparent";

/**
 * The OpenFlightsMap object
 */
var map;

/**
 * Create an OpenFlightsMap on initialization
 */
window.onload = function init(){
  // Set up localization
  gt = new Gettext({ 'domain' : 'messages' });

  // Set up OpenLayers base maps
  var ol_wms = new OpenLayers.Layer.WMS( "Political (Metacarta)",
					 "http://labs.metacarta.com/wms/vmap0?",
					 {layers: 'basic'},
					 {transitionEffect: 'resize', wrapDateLine: true}
					 );
  
  var jpl_wms = new OpenLayers.Layer.WMS("Geographical (NASA)", 
                ["http://t1.hypercube.telascience.org/tiles?",
                 "http://t2.hypercube.telascience.org/tiles?",
                 "http://t3.hypercube.telascience.org/tiles?",
                 "http://t4.hypercube.telascience.org/tiles?"], 
                {layers: 'landsat7'},
	        {transitionEffect: 'resize', wrapDateLine: true}
            );
  jpl_wms.setVisibility(false);

  // Create OpenFlightsMap with base layers
  map = new OpenFlightsMap([ol_wms, jpl_wms]);

  // Load a flight map
  map.load(OpenFlightsMap.FLIGHTS);
}

/**
 * Create a popup when an airport is selected
 *
 * @param airport An OpenLayers.Feature.Vector object.  May be either a single airport or a cluster,
 * check airport.cluster to check which.  Contains attribute data under airport.attributes.*
 * (single) or airport.cluster[n].attributes.* (cluster).  List of known attributes:
 * <ul>
 * <li>apid Airport ID
 * <li>code Airport IATA/ICAO code
 * <li>desc Default description
 * <li>icon Airport icon
 * <li>name Airport name
 * </ul>
 */
function onAirportSelect(airport) {
  map.debug("onAirportSelect()");

  // Create a description of this airport or airport cluster
  if(airport.cluster) {
    desc = "<b>Cluster</b>:<br>";
    for(var c = 0; c < airport.cluster.length; c++) {
      desc += " " + airport.cluster[c].attributes.code;
    }
  } else {
    desc = airport.attributes.desc + ", ID: " + airport.attributes.apid;
  }

  if(!airport.popup) {
    // Create new popup
    airport.popup = new OpenLayers.Popup.FramedCloud("airport", 
						     airport.geometry.getBounds().getCenterLonLat(),
						     new OpenLayers.Size(200,80),
						     desc, null, false);
    airport.popup.minSize = new OpenLayers.Size(200,80);
    airport.popup.overflow = "auto";
    map.addPopup(airport.popup);
    airport.popup.show();
  } else {
    // Show previously created popup
    airport.popup.setContentHTML(desc); // in case description has changed
    airport.popup.toggle();
  }
}

/**
 * Hide popup when an airport is unselected
 *
 * @param airport An OpenLayers.Feature.Vector object. 
 */
function onAirportUnselect(airport) {
  map.debug("onAirportUnselect()");
  airport.popup.hide();
}

/**
 * Check which map radio button is currently selected
 */
function getMapType() {
  var radio = document.forms['controlform'].maptype;
  for(var r = 0; r < radio.length; r++) {
    if(radio[r].checked) {
      return radio[r].value;
    }
  }
}

/**
 * User has requested a change to the map
 */
function changeMap() {
  var type = getMapType(); 
  var id = document.forms['controlform'].apid.value;

  if((type == 'L' || type == 'R') && id == "") {
    // User has requested an airline/airport route map, but didn't give us an ID!
    document.forms['controlform'].apid.focus();
  } else {
    // Load the new map contents
    map.load(type, id);
  }
}
