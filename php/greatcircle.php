<?php

$PI = 3.1415926;
$EARTH_RADIUS = 3958.75;    // in miles
$EARTH_CIRCUMFERENCE = 24900; // in miles
$MOON_DISTANCE = 238857;    // in miles
$MARS_DISTANCE = 34649589;    // in miles
$DEG2RAD =  0.01745329252;  // factor to convert degrees to radians (PI/180)
$RAD2DEG = 57.29577951308;

$GC_STEP = 100; // draw segment every GC_STEP mi
$GC_MIN = 1000; // trigger GC paths once distance is greater than this

$ASCENT_STEP = 1; // compute ascent path every ASCENT_STEP miles
$ASCENT_SPEED = 100; // meters per ASCENT_STEP (from sealevel)
$CRUISE_ALTITUDE = 10000; // cruising altitude in meters
$MAGIC_NUMBER = 19; // number of steps needed for the landing curve (integral of y(n)=y(n-1)+n?)

//Compute great circle distance from "from" to "to"
function gcPointDistance($from, $to) {
  $lon1 = $from["x"];
  $lat1 = $from["y"];
  $lon2 = $to["x"];
  $lat2 = $to["y"];

  // Eliminate one trivial case...
  if($lon1 == $lon2 && $lat1 == $lat2) {
    return 0;
  }

  $pi = 3.1415926;
  $rad = doubleval($pi/180.0);
  $lon1 = doubleval($lon1)*$rad; $lat1 = doubleval($lat1)*$rad;
  $lon2 = doubleval($lon2)*$rad; $lat2 = doubleval($lat2)*$rad;

  $theta = $lon2 - $lon1;
  $dist = acos(sin($lat1) * sin($lat2) + cos($lat1) * cos($lat2) * cos($theta));
  if ($dist < 0) { $dist += $pi; }
  return floor($dist * 6371.2 * 0.621);
}

// Compute great circle bearing from point "from" towards point "to"
function gcBearingTo($from, $to) {
  global $PI, $DEG2RAD, $RAD2DEG;

  $x1 = $from["x"] * $DEG2RAD;
  $y1 = $from["y"] * $DEG2RAD;
  $x2 = $to["x"] * $DEG2RAD;
  $y2 = $to["y"] * $DEG2RAD;

  $a = cos($y2) * sin($x2 - $x1);
  $b = cos($y1) * sin($y2) - sin($y1) * cos($y2) * cos($x2 - $x1);
  if(($a == 0) && ($b == 0)) {
    $bearing = 0;
    return $bearing;
  }
  if($b == 0) {
    if($a < 0)
      $bearing = 270;
    else
      $bearing = 90;
    return $bearing;
  }

  if( $b < 0)
    $adjust = $PI;
  else {
    if($a < 0)
      $adjust = 2 * $PI;
    else
      $adjust = 0;
  }
  $bearing = (atan($a/$b) + $adjust) * $RAD2DEG;
  return $bearing;
}

/**
 * Compute great circle waypoint "distance" miles away from "from" in direction "bearing"
 */
function gcWaypoint($from, $distance, $bearing) {
  global $DEG2RAD, $RAD2DEG, $EARTH_RADIUS;

  // Math.* trig functions require angles to be in radians
  $x = $from["x"] * $DEG2RAD;
  $y = $from["y"] * $DEG2RAD;
  $radBearing = $bearing * $DEG2RAD;

  // Convert arc distance to radians
  $d = $distance / $EARTH_RADIUS;

  // Modified based on http://williams.best.vwh.net/avform.htm
  $lat = asin( sin($y) * cos($d) + cos($y) * sin($d) * cos($radBearing));
  $lon = atan2( sin($radBearing) * sin($d) * cos($y), cos($d) - sin($y) * sin($lat));
  $x = ($x + $lon) * $RAD2DEG;
  $y = $lat * $RAD2DEG;
  return array("x" => $x, "y" => $y);
}

function straightPath($startPoint, $endPoint) {
  // Do we cross the dateline?  If yes, then flip endPoint across it
  if(abs($startPoint["x"]-$endPoint["x"]) > 180) {
    if($startPoint["x"] < $endPoint["x"]) {
      $endPoint["x"] -= 360;
    } else {
      $endPoint["x"] += 360;
    }
  }
  return array($startPoint, $endPoint);
}

// If $threed = true, plot path in three dimensions (x,y,z), else only two (x,y)
function gcPath($startPoint, $endPoint, $distance, $threed) {
  global $GC_STEP, $ASCENT_STEP, $ASCENT_SPEED, $MAGIC_NUMBER, $CRUISE_ALTITUDE;

  $pointList = array();
  $pointList[] = $startPoint;
  $wayPoint = $startPoint;
  $distance = gcPointDistance($startPoint, $endPoint);
  $elevation = 0;

  if($threed) {
    $step = $ASCENT_STEP;
    $elevation = $startPoint["z"];
    $delta = 1; // Ascending

    // Calculate distance at which to start descent
    $ascentspeed = ($CRUISE_ALTITUDE - $startPoint["z"]) / $ASCENT_SPEED;
    $descentspeed = ($CRUISE_ALTITUDE - $endPoint["z"]) / $ASCENT_SPEED;
    $descentpoint = $distance - ($MAGIC_NUMBER * $ASCENT_STEP);
    if($descentpoint < $distance / 2) {
      $descentpoint = $distance / 2;
    }
  } else {
    $step = $GC_STEP;
  }
  $d = 0;

  // And... action!
  while($d + 1 < $distance) {
    //printf("%s of %s: from %s,%s now at %s,%s step %s bearing %s target %s,%s\n", $d, $distance, $startPoint["x"], $startPoint["y"], $step, $bearing, $wayPoint["x"], $wayPoint["y"], $endPoint["x"], $endPoint["y"]);

    // Cruising, but increase step resolution near the poles
    if($threed && $delta != 0) {
      $step = $ASCENT_STEP;
    } else {
      if(abs($wayPoint["y"]) > 60) {
        $step = $GC_STEP / 2;
      } else {
        $step = $GC_STEP;
      }
    }

    if($threed) {
      // Ascending
      if($delta > 0) {
        if($elevation < ($CRUISE_ALTITUDE - $startPoint["z"]) / 2 + $startPoint["z"]) {
          $delta += $ascentspeed;
        } else {
          $delta -= $ascentspeed;
        }
        if($elevation >= $CRUISE_ALTITUDE || $delta < 0) {
          $elevation = $CRUISE_ALTITUDE;
          $delta = 0;
        }
      }

      // Descending
      if($d + $step >= $descentpoint) {
        if($d >= $descentpoint) {
          if($elevation > ($CRUISE_ALTITUDE - $endPoint["z"]) / 2 + $endPoint["z"]) {
            $delta -= $descentspeed;
          } else {
            $delta += $descentspeed;
          }
          $step = $ASCENT_STEP;
        } else {
          // Prepare for descent!
          $step = $descentpoint - $d;
        }
        if($elevation < $endPoint["z"]) {
          $delta = 0;
          $elevation = $endPoint["z"];
        }
      }
      $elevation += $delta;
    }

    $bearing = gcBearingTo($wayPoint, $endPoint); // degrees, clockwise from 0 deg at north
    $wayPoint = gcWaypoint($wayPoint, $step, $bearing);
    if($threed) {
      $wayPoint["z"] = $elevation;
    }

    if($wayPoint["x"] > -180 && $wayPoint["x"] < 180) {
      $pointList[] = $wayPoint;
    } else {
      // Flip paths crossing the edge of the map
      if($wayPoint["x"] < -180 && $bearing > 180) {
        $pointList[] = array("x" => -180, "y" => $wayPoint["y"], "z" => $elevation);
        $wayPoint["x"] += 360;
        $pointList[] = null; // break mark
        $pointList[] = array("x" => 180, "y" => $wayPoint["y"], "z" => $elevation);
      }
      if($wayPoint["x"] > 180 && $bearing < 180) {
        $pointList[] = array("x" => 180, "y" => $wayPoint["y"], "z" => $elevation);
        $wayPoint["x"] -= 360;
        $pointList[] = null; // break mark
        $pointList[] = array("x" => -180, "y" => $wayPoint["y"], "z" => $elevation);
      }
    }

    $d = gcPointDistance($startPoint, $wayPoint);
  }
  $pointList[] = $endPoint;
  return $pointList;
}
