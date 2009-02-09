<?php

$PI = 3.1415926;
$EARTH_RADIUS = 3958.75;    // in miles
$EARTH_CIRCUMFERENCE = 24900; // in miles
$MOON_DISTANCE = 238857;    // in miles
$MARS_DISTANCE = 34649589;    // in miles
$DEG2RAD =  0.01745329252;  // factor to convert degrees to radians (PI/180)
$RAD2DEG = 57.29577951308;
$GC_STEP = 500; // draw segment every GC_STEP mi
$GC_MIN = 1000; // trigger GC paths once distance is greater than this

$stderr = fopen("php://stderr", "w");

// Compute great circle bearing from point "from" towards point "to"
function gcBearingTo($from, $to) {
  global $PI, $DEG2RAD, $RAD2DEG, $stderr;

  $x1 = $from["x"] * $DEG2RAD;
  $y1 = $from["y"] * $DEG2RAD;
  $x2 = $to["x"] * $DEG2RAD;
  $y2 = $to["y"] * $DEG2RAD;

  fwrite($stderr, "GCBEARING From $x1,y1 to $x2,$y2\n");

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

function gcPath($startPoint, $endPoint, $distance) {
  global $GC_STEP, $stderr;

  // And... action!
  $pointList = array();
  $pointList[] = $startPoint;
  $wayPoint = $startPoint;
  $d = $GC_STEP;
  $step = $GC_STEP;
  while($d < $distance) {
    $bearing = gcBearingTo($wayPoint, $endPoint); // degrees, clockwise from 0 deg at north
    $wayPoint = gcWaypoint($wayPoint, $step, $bearing);
    fwrite($stderr, "GCPATH Bearing " . $bearing . ", Waypoint (" . $wayPoint["x"] . "," . $wayPoint["y"] . ")\n");
    if($wayPoint["x"] > -180 && $wayPoint["x"] < 180) {
      $pointList[] = $wayPoint;
    } else {
      // Flip paths crossing the edge of the map
      if($wayPoint["x"] < -180 && $bearing > 180) {
	$pointList[] = array("x" => -180, "y" => $wayPoint["y"]);
	$wayPoint["x"] += 360;
	fwrite($stderr, "GCPATH Break West " . $wayPoint["x"] . "\n");
	$pointList[] = null; // break mark
	$pointList[] = array("x" => 180, "y" => $wayPoint["y"]);
      }
      if($wayPoint["x"] > 180 && $bearing < 180) {
	$pointList[] = array("x" => 180, "y" => $wayPoint["y"]);
	$wayPoint["x"] -= 360;
	fwrite($stderr, "GCPATH Break East " . $wayPoint["x"] . "\n");
	$pointList[] = null; // break mark
	$pointList[] = array("x" => -180, "y" => $wayPoint["y"]);
      }
    }

    // Increase step resolution near the poles
    if(abs($wayPoint["y"]) > 60) {
      $step = $GC_STEP / 2;
    } else {
      $step = $GC_STEP;
    }
    $d += $step;
  }
  $pointList[] = $endPoint;
  return $pointList;
}

function getlocationcoords($lat, $lon, $width, $height)
{
   $x = (($lon + 180) * ($width / 360));
   $y = ((($lat * -1) + 90) * ($height / 180));
   return array("x"=>round($x),"y"=>round($y));
}

// These are the coordinates the location we wish to plot.
// These are being passed in the URL, but we will set them to a default if nothing is passed.

// List of all this user's flights
$uid = $_GET["uid"];
$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);
$sql = "SELECT DISTINCT s.x AS sx,s.y AS sy,d.x AS dx,d.y AS dy, distance FROM flights AS f, airports AS s, airports AS d WHERE f.src_apid=s.apid AND f.dst_apid=d.apid AND f.uid=" . $uid . " GROUP BY s.apid,d.apid";

// First we load the background/base map. We assume it's located in same dir as the script.
// This can be any format but we are using JPG in this example
// We will also allocate the color for the marker

$im = imagecreatefrompng("../img/fb-thumbnail.png");
$airportColors=array(imagecolorallocate ($im, 0,0,0), // black
		     imagecolorallocate ($im, 0x66,0x66,0x99), // cyan
		     imagecolorallocate ($im, 0x45,0xFF,0xA9)); // green
$yellow = imagecolorallocate ($im, 0xEE, 0x99, 0);
$scale_x = imagesx($im);
$scale_y = imagesy($im);

$result = mysql_query($sql, $db);
if(mysql_num_rows($result) == 0) {
  // No flights, return a blank map
  header ("Content-type: image/png");
  imagepng($im);
  imagedestroy($im);
}

// Plot flights on map
while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  $x1 = $row["sx"];
  $y1 = $row["sy"];
  $x2 = $row["dx"];
  $y2 = $row["dy"];
  $distance = $row["distance"];
  if($distance > $GC_MIN) {
    // Plot great circle curve
    $points = gcPath(array("x" => $x1, "y" => $y1), 
		     array("x" => $x2, "y" => $y2),
		     $distance);
    fwrite($stderr, "GC POINTS " . print_r($points, true));
  } else {
    // Draw straight lines
    $points = straightPath(array("x" => $x1, "y" => $y1),
			   array("x" => $x2, "y" => $y2));
    fwrite($stderr, "STR POINTS " . print_r($points, true));
  }
  $oldpt = null;
  foreach($points as $loc) {
    if($loc == null) {
      fwrite($stderr, "BREAK\n");
      $oldpt = null;
      continue;
    }
    $newpt = getlocationcoords($loc["y"], $loc["x"], $scale_x, $scale_y);
    if($oldpt) {
      fwrite($stderr, "LINE (" . $oldpt["x"] . "," . $oldpt["y"] . ")-(" . $newpt["x"] . "," . $newpt["y"] . ")\n");
      imageline($im, $oldpt["x"], $oldpt["y"], $newpt["x"], $newpt["y"], $yellow);
    }
    $oldpt = $newpt;
  }
}

$sql = "SELECT DISTINCT x,y,count(name) AS visits FROM flights AS f, airports AS a WHERE (f.src_apid=a.apid OR f.dst_apid=a.apid) AND f.uid=" . $uid . " GROUP BY name ORDER BY visits ASC";
$result = mysql_query($sql, $db);

// Figure out max flight count
mysql_data_seek($result, mysql_num_rows($result) - 1);
$row = mysql_fetch_array($result, MYSQL_ASSOC);
$maxFlights = $row["visits"];
mysql_data_seek($result, 0);

// Then draw airports from smallest to largest
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  $count = $row["visits"];
  $colorIndex = floor(($count / $maxFlights) * sizeof($airportColors));
  if($count <= 2 || $colorIndex < 0) {
    $colorIndex = 0;
  }
  // More than two flights: at least 2nd smallest
  if($count > 2) {
    $colorIndex = max(1, $colorIndex);
  }
  // Max out at top color
  if($colorIndex >= sizeof($airportColors)) {
    $colorIndex = sizeof($airportColors) - 1;
  }
  $radius = ($colorIndex + 1) * 2;
  fwrite($stderr, "VISITS $count IDX $colorIndex\n");
  $pt = getlocationcoords($row["y"], $row["x"], $scale_x, $scale_y);
  imagefilledellipse($im, $pt["x"], $pt["y"], $radius, $radius, $airportColors[$colorIndex]);
}

header ("Content-type: image/png");
imagepng($im);
imagedestroy($im);

?>