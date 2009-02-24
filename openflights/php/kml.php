<?php
session_start();

header("Content-type: application/vnd.google-earth.kml+xml");
header("Content-disposition: attachment; filename=\"openflights-" . date("Y-m-d").".kml\"");

include 'greatcircle.php';
include 'helper.php';
include 'filter.php';

$METERSPERFOOT = 0.3048;

$uid = $_SESSION["uid"];
// Logged in?
if(!$uid or empty($uid)) {
  $uid = 1;
}

$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);
$sql = "SELECT DISTINCT s.x AS sx,s.y AS sy,s.elevation AS sz,d.x AS dx,d.y AS dy,d.elevation AS dz,distance FROM flights AS f, airports AS s, airports AS d WHERE f.src_apid=s.apid AND f.dst_apid=d.apid AND f.uid=" . $uid . getFilterString($HTTP_GET_VARS) . " GROUP BY s.apid,d.apid";

$result = mysql_query($sql, $db);

readfile("../kml/header.kml");

// Plot flights on map
while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  $x1 = $row["sx"];
  $y1 = $row["sy"];
  $z1 = $row["sz"] * $METERSPERFOOT;
  $x2 = $row["dx"];
  $y2 = $row["dy"];
  $z2 = $row["dz"] * $METERSPERFOOT;
  $distance = $row["distance"];

  // Skip flights where src==dest
  if($x1 != $x2 && $y1 != $y2) {
    $points = gcPath(array("x" => $x1, "y" => $y1, "z" => $z1), 
		     array("x" => $x2, "y" => $y2, "z" => $z2),
		     $distance, true);
    
    print "<LineString>\n<altitudeMode>absolute</altitudeMode><coordinates>\n";
    foreach($points as $loc) {
      if(! $loc) continue; // skip breaks
      print $loc["x"] . "," . $loc["y"] . "," . $loc["z"] . "\n";
    }
    print "  </coordinates>\n</LineString>\n";
  }
}

print "  </MultiGeometry>\n</Placemark>\n";

// Draw airports from largest to smallest
$airportColors = array ("black", "gray", "purple", "cyan", "cyan", "green");

$sql = "SELECT DISTINCT x,y,elevation,iata,icao,name,city,country,count(name) AS visits FROM flights AS f, airports AS a WHERE (f.src_apid=a.apid OR f.dst_apid=a.apid) AND f.uid=" . $uid . getFilterString($HTTP_GET_VARS) . " GROUP BY name ORDER BY visits DESC";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  $count = $row["visits"];
  if($first) {
    $maxFlights = $count;
    $first = false;
  }

  $colorIndex = floor(($count / $maxFlights) * sizeof($airportColors)) + 1;
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

  print "<Placemark>\n";
  print "  <name>" . format_apcode($row) . "</name>\n";
  printf("  <description>\n    <![CDATA[\n<b>%s</b><br><i>Elevation</i>: %s ft<br><i>Flights</i>: %s\n]]>\n  </description>\n",
	 format_airport($row), $row["elevation"], $count);
  print "  <Point>\n";
  printf("    <coordinates>%s,%s,%s</coordinates>\n", $row["x"], $row["y"], $row["elevation"]);
  print "  </Point>\n";
  print "  <styleUrl>#" . $airportColors[$colorIndex] . "-pushpin</styleUrl>\n";
  print "</Placemark>\n";
}
    
readfile("../kml/footer.kml");

?>