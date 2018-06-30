<?php

include("../php/db_pdo.php");
include("../php/greatcircle.php");

$stderr = fopen("php://stderr", "w");

function getlocationcoords($lat, $lon, $width, $height)
{
   $x = (($lon + 180) * ($width / 360));
   $y = ((($lat * -1) + 90) * ($height / 180));
   return array("x"=>round($x),"y"=>round($y));
}

$sql = "SELECT DISTINCT s.x AS sx,s.y AS sy,d.x AS dx,d.y AS dy FROM routes AS r, airports AS s, airports AS d WHERE r.src_apid=s.apid AND r.dst_apid=d.apid GROUP BY s.apid,d.apid";

// First we load the background/base map. We assume it's located in same dir as the script.
// This can be any format but we are using JPG in this example
// We will also allocate the color for the marker

//$im = imagecreatefrompng("metacarta_wmsc.png");
$im = imagecreatefrompng("map-2048.png");
if(! $im) {
  die("Image not found");
}

imagealphablending($im, true);
$airportColors=array(imagecolorallocate ($im, 0,0,0), // black
		     imagecolorallocate ($im, 0x66,0x66,0x99), // cyan
		     imagecolorallocate ($im, 0x45,0xFF,0xA9)); // green

$yellow = imagecolorallocatealpha ($im, 0x99, 0xEE, 0, 95);

// Next need to find the base image size.
// We need these variables to be able scale the long/lat coordinates.

$scale_x = imagesx($im);
$scale_y = imagesy($im);

// Now we convert the long/lat coordinates into screen coordinates

$count = 0;
foreach ($dbh->query($sql) as $row) {
  $count++;
  if($count % 100 == 0) fwrite($stderr, "$count ");

  $x1 = $row["sx"];
  $y1 = $row["sy"];
  $x2 = $row["dx"];
  $y2 = $row["dy"];

  $distance = gcPointDistance(array("x" => $x1, "y" => $y1),
			      array("x" => $x2, "y" => $y2));

  if($distance > $GC_MIN) {
    // Plot great circle curve
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

// Return the map image. We are using a PNG format as it gives better final image quality than a JPG

header ("Content-type: image/png");
imagepng($im);
imagedestroy($im);

?>
