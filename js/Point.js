// http://trac.openlayers.org/wiki/GreatCircleAlgorithms
function Point (x, y) {
    this.x = parseFloat(x);
    this.y = parseFloat(y);
}

Point.prototype = new OpenLayers.Geometry.Point(0,0);

Point.prototype.isValid = function() {
    if((this.x != null) && (this.y != null) && (this.x != NaN) && (this.y != NaN))
        return( true );
    else
        return( false );
}

/**
 *    Geo Constants
 */
Point.EARTH_RADIUS = 3958.75;    // in miles
Point.DEG2RAD =  0.01745329252;  // factor to convert degrees to radians (PI/180)
Point.RAD2DEG = 57.29577951308;

/**
 *    Method: geoDistanceTo
 *
 *    Parameters:
 *    point - {<Point>}
 *
 *    Returns:
 *    Great Circle distance (in miles) to Point. 
 *    Coordinates must be in decimal degrees.
 *    
 *    Reference:
 *    Williams, Ed, 2000, "Aviation Formulary V1.43" web page
 *    http://williams.best.vwh.net/avform.htm
 */
Point.prototype.geoDistanceTo = function( point ) {
var x = new Array(2);
var y = new Array(2);

    if( this.isValid() && point.isValid()) {
        x[0] = this.x * Point.DEG2RAD;    y[0] = this.y * Point.DEG2RAD;
        x[1] = point.x * Point.DEG2RAD;    y[1] = point.y * Point.DEG2RAD;
        
        var a = Math.pow( Math.sin(( y[1]-y[0] ) / 2.0 ), 2);
        var b = Math.pow( Math.sin(( x[1]-x[0] ) / 2.0 ), 2);
        var c = Math.pow(( a + Math.cos( y[1] ) * Math.cos( y[0] ) * b ), 0.5);
    
        return ( 2 * Math.asin( c ) * Point.EARTH_RADIUS );
    } else
        return null;
}


Point.prototype.geoBearingTo = function( point ) {
  var x = new Array(2);
  var y = new Array(2);
  var bearing;
  var adjust;
  
  if( this.isValid() && point.isValid()) {
    x[0] = this.x * Point.DEG2RAD;    y[0] = this.y * Point.DEG2RAD;
    x[1] = point.x * Point.DEG2RAD;    y[1] = point.y * Point.DEG2RAD;
    
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
    bearing = (Math.atan(a/b) + adjust) * Point.RAD2DEG;
    return bearing;
  } else
    return null;
}


/**
 *
 */
Point.prototype.geoWaypoint = function( distance, bearing ) {
  var wp = new Point( 0, 0 );

  // Math.* trig functions require angles to be in radians
  var x = this.x * Point.DEG2RAD;
  var y = this.y * Point.DEG2RAD;
  var radBearing = bearing * Point.DEG2RAD;
  
  // Convert arc distance to radians
  var d = distance / Point.EARTH_RADIUS;
  
  // Modified based on http://williams.best.vwh.net/avform.htm
  var lat = Math.asin( Math.sin(y) * Math.cos(d) + Math.cos(y) * Math.sin(d) * Math.cos(radBearing));  
  var lon = Math.atan2( Math.sin(radBearing) * Math.sin(d) * Math.cos(y), Math.cos(d) - Math.sin(y) * Math.sin(lat));
  wp.x = (x + lon) * Point.RAD2DEG;
  wp.y = lat * Point.RAD2DEG;
  return wp;
}
  
// Compute great circle distance between two points (spherical law of cosines)
// http://www.movable-type.co.uk/scripts/latlong.html
// © 2002-2008 Chris Veness
function greatCircle(lat1, lon1, lat2, lon2) {
  var rad = Math.PI / 180;
  var R = 6371; // km
  lat1 = lat1 * rad;
  lon1 = lon1 * rad;
  lat2 = lat2 * rad;
  lon2 = lon2 * rad;
  var d = Math.acos(Math.sin(lat1)*Math.sin(lat2) + 
                  Math.cos(lat1)*Math.cos(lat2) *
                  Math.cos(lon2-lon1));
  if (d < 0) d += Math.PI;
  return Math.floor(d * R * 0.621);

}

const GC_STEP = 1000; // draw segment every GC_STEP mi

function straightPath(startPoint, endPoint, distance) {
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
    var bearing = wayPoint.geoBearingTo(endPoint);
    var wayPoint = wayPoint.geoWaypoint(GC_STEP, bearing);
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
