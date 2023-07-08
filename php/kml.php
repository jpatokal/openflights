<?php

session_start();

header("Content-type: application/vnd.google-earth.kml+xml");
header("Content-disposition: attachment; filename=\"openflights-" . date("Y-m-d") . ".kml\"");

include 'greatcircle.php';
include 'helper.php';
include 'filter.php';
include 'db_pdo.php';

const METERS_PER_FOOT = 0.3048;

$uid = $_SESSION["uid"];
// Logged in?
if (!$uid || empty($uid)) {
    $uid = 1;
}

$sql = "SELECT DISTINCT s.x AS sx,s.y AS sy,s.elevation AS sz,s.iata AS siata,s.icao AS sicao,d.x AS dx,d.y AS dy,d.elevation AS dz,d.iata AS diata,d.icao AS dicao,code,distance,mode FROM flights AS f, airports AS s, airports AS d WHERE f.src_apid=s.apid AND f.dst_apid=d.apid AND f.uid=:uid" . getFilterString($dbh, $_GET) . " GROUP BY f.fid,s.apid,d.apid";

$sth = $dbh->prepare($sql);
$sth->execute(compact('uid'));

readfile("../kml/header.kml");

print "<Folder>\n<name>Flights</name>\n";

// Plot flights on map
foreach ($sth as $row) {
    $x1 = $row["sx"];
    $y1 = $row["sy"];
    $z1 = $row["sz"] * METERS_PER_FOOT;
    $x2 = $row["dx"];
    $y2 = $row["dy"];
    $z2 = $row["dz"] * METERS_PER_FOOT;
    $distance = $row["distance"];

    // Skip flights where src==dest
    if ($x1 != $x2 && $y1 != $y2) {
        $src_ap = format_apcode2($row["siata"], $row["sicao"]);
        $dst_ap = format_apcode2($row["diata"], $row["dicao"]);
        $code = $row["code"];
        $mode = $row["mode"];

        print "<Placemark>\n";
        print "  <name>$src_ap-$dst_ap</name>\n  <description>$modes[$mode] $code</description>\n";
        print "  <styleUrl>#$mode</styleUrl>\n";

        $points = gcPath(
            array("x" => $x1, "y" => $y1, "z" => $z1),
            array("x" => $x2, "y" => $y2, "z" => $z2),
            $distance,
            true
        );

        if ($row["mode"] == "F") {
            $altitudeMode = "absolute";
        } else {
            $altitudeMode = "clampToGround";
        }
        print "  <MultiGeometry>\n    <LineString>\n      <altitudeMode>$altitudeMode</altitudeMode><coordinates>\n";
        foreach ($points as $loc) {
            if (!$loc) {
                // skip breaks
                continue;
            }
            print $loc["x"] . "," . $loc["y"] . "," . $loc["z"] . "\n";
        }
        print "      </coordinates>\n    </LineString>\n";
        print "  </MultiGeometry>\n</Placemark>\n";
    }
}

print "</Folder>\n";
print "<Folder><name>Airports</name>\n";

// Draw airports from largest to smallest
$airportColors = array ("black", "gray", "purple", "cyan", "cyan", "green");

$sql = "SELECT DISTINCT x,y,elevation,iata,icao,name,city,country,count(name) AS visits FROM flights AS f, airports AS a WHERE (f.src_apid=a.apid OR f.dst_apid=a.apid) AND f.uid=:uid" . getFilterString($dbh, $_GET) . " GROUP BY a.apid,name ORDER BY visits DESC";
$sth = $dbh->prepare($sql);
$sth->execute(compact('uid'));
$first = true;
foreach ($sth as $row) {
    $count = $row["visits"];
    if ($first) {
        $maxFlights = $count;
        $first = false;
    }

    $colorIndex = floor(($count / $maxFlights) * sizeof($airportColors)) + 1;
    if ($count <= 2 || $colorIndex < 0) {
        $colorIndex = 0;
    }
    // More than two flights: at least 2nd smallest
    if ($count > 2) {
        $colorIndex = max(1, $colorIndex);
    }
    // Max out at top color
    if ($colorIndex >= sizeof($airportColors)) {
        $colorIndex = sizeof($airportColors) - 1;
    }

    print "<Placemark>\n";
    print "  <name>" . format_apcode($row) . "</name>\n";
    printf(
        "  <description>\n    <![CDATA[\n<b>%s</b><br><i>Elevation</i>: %s ft<br><i>Flights</i>: %s\n]]>\n  </description>\n",
        format_airport($row),
        $row["elevation"],
        $count
    );
    print "  <Point>\n";
    printf("    <coordinates>%s,%s,%s</coordinates>\n", $row["x"], $row["y"], $row["elevation"]);
    print "  </Point>\n";
    print "  <styleUrl>#" . $airportColors[$colorIndex] . "-pushpin</styleUrl>\n";
    print "</Placemark>\n";
}
print "</Folder>\n";
readfile("../kml/footer.kml");
