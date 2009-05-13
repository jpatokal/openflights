<?php
require_once '../php/greatcircle.php';

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
if(! $uid) {
  print "User ID unknown";
  return;
}

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
    // Plot great circle curve (2D)
    $points = gcPath(array("x" => $x1, "y" => $y1), 
		     array("x" => $x2, "y" => $y2),
		     $distance, false);
  } else {
    // Draw straight lines
    $points = straightPath(array("x" => $x1, "y" => $y1),
			   array("x" => $x2, "y" => $y2));
  }
  $oldpt = null;
  foreach($points as $loc) {
    if($loc == null) {
      $oldpt = null;
      continue;
    }
    $newpt = getlocationcoords($loc["y"], $loc["x"], $scale_x, $scale_y);
    if($oldpt) {
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
  $pt = getlocationcoords($row["y"], $row["x"], $scale_x, $scale_y);
  imagefilledellipse($im, $pt["x"], $pt["y"], $radius, $radius, $airportColors[$colorIndex]);
}

header ("Content-type: image/png");
imagepng($im);
imagedestroy($im);

?>