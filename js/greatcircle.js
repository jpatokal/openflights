// http://trac.openlayers.org/wiki/GreatCircleAlgorithms

/**
 *    Geo Constants
 */
EARTH_RADIUS = 3958.75;    // in miles
DEG2RAD =  0.01745329252;  // factor to convert degrees to radians (PI/180)
RAD2DEG = 57.29577951308;
GC_STEP = 1000; // draw segment every GC_STEP mi

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
 * Return array of two points, flipping across dateline if needed
 */
function straightPath(startPoint, endPoint) {
  // Do we cross the dateline?  If yes, then flip endPoint across it
  if(Math.abs(startPoint.x-endPoint.x) > 180) {
    if(startPoint.x < endPoint.x) {
      endPoint.x -= 360;
    } else {
      endPoint.x += 360;
    }
  }
  return [startPoint, endPoint];
}

/*
 * Return array of GC waypoints between two points
 * Flips across dateline if needed, and removes any invisible points
 */  
function gcPath(startPoint, endPoint, distance) {
  // Do we cross the dateline?  If yes, then flip endPoint across it
  if(Math.abs(startPoint.x-endPoint.x) > 180) {
    if(startPoint.x < endPoint.x) {
      endPoint.x -= 360;
    } else {
      endPoint.x += 360;
    }
  }

  // And... action!
  var pointList = new Array();
  var wayPoint = startPoint;
  var d = GC_STEP;
  if(startPoint.x > -360 && startPoint.x < 360) {
    pointList.push(startPoint);
  }
  while(d < distance) {
    var bearing = gcBearingTo(wayPoint, endPoint);
    var wayPoint = gcWaypoint(wayPoint, GC_STEP, bearing);
    if(wayPoint.x > -360 && wayPoint.x < 360) {
      pointList.push(wayPoint);
    }
    d += GC_STEP;
  }
  if(endPoint.x > -360 && endPoint.x < 360) {
    pointList.push(endPoint);
  }
  return pointList;
}

// Check if point is a point
function isValid(point) {
    if((point.x != null) && (point.y != null) && (point.x != NaN) && (point.y != NaN))
        return( true );
    else
        return( false );
}
