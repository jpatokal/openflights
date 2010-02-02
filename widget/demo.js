/**
 * OpenFlights Widget Demo -- for openflights.org
 * by Jani Patokallio <jani at contentshare dot sg>
 */

var URL_MAP = "/php/map.php";
var URL_ROUTES = "/php/routes.php";

// Set to "true" to enable debugging messages
var OF_DEBUG = true;

// avoid pink tiles
OpenLayers.IMAGE_RELOAD_ATTEMPTS = 3;
OpenLayers.Util.onImageLoadErrorColor = "transparent";

var map;

window.onload = function init(){
  gt = new Gettext({ 'domain' : 'messages' });

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

  map = new OpenFlightsMap([ol_wms, jpl_wms]);
  map.load(URL_MAP);
}

function onAirportSelect(airport) {
  // Create description of this airport
  if(airport.cluster) {
    desc = "<b>Cluster</b>:<br>";
    for(var c = 0; c < airport.cluster.length; c++) {
      desc += " " + airport.cluster[c].attributes.code;
    }
  } else {
    desc = airport.attributes.desc;
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

function onAirportUnselect(airport) {
  airport.popup.hide();
}

