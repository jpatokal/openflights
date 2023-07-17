<?php

// https://developers.google.com/kml/documentation/kmlreference

session_start();

header("Content-type: application/vnd.google-earth.kml+xml");
header("Content-disposition: attachment; filename=\"openflights-" . date("Y-m-d-Hi") . ".kml\"");

include_once 'helper.php';
include_once 'filter.php';
include_once 'db_pdo.php';

const METERS_PER_FOOT = 0.3048;

const DATE_FORMAT = 'Y-m-d\TH:i:sP';

$uid = $_SESSION["uid"] ?? false;
// Logged in?
if (!$uid || empty($uid)) {
    $uid = 1;
}

$filterString = getFilterString($dbh, $_GET);
$sql = <<<SQL
    SELECT s.x AS sx, s.y AS sy, s.elevation AS sz, s.iata AS siata, s.icao AS sicao, d.x AS dx,
        d.y AS dy, d.elevation AS dz, d.iata AS diata, d.icao AS dicao, code, mode,
        opp, src_date, src_time, duration, s.tz_id AS stz, d.tz_id AS dtz
    FROM flights AS f, airports AS s, airports AS d
    WHERE f.src_apid = s.apid AND f.dst_apid = d.apid AND f.uid = :uid $filterString
    ORDER BY src_date, src_time
SQL;

$sth = $dbh->prepare($sql);
$sth->execute(compact('uid'));

/**
 * @var $interval string|null
 * @return string|false
 */
function parseIntervalString($interval) {
    if ($interval === null) {
        return false;
    }
    [$h, $m, $s] = explode(':', $interval);

    $intervalString = trimZero($h, 'H') . trimZero($m, 'M') . trimZero($s, 'S');
    return $intervalString !== ""
        ? "PT" . $intervalString
        : false;
}

/**
 * @param $value string
 * @param $suffix string
 * @return string
 */
function trimZero($value, $suffix) {
    $value = trim($value, '0');
    return $value !== ""
        ? $value . $suffix
        : "";
}

readfile("../kml/header.kml");

print "<Folder>
<name>Flights</name>
";

// Plot flights on the map
foreach ($sth as $row) {
    // Skip flights where src==dest
    if ($row["sx"] == $row["dx"] && $row["sy"] == $row["dy"]) {
        continue;
    }
    $flip = $row['opp'] === 'Y';

    [$x1, $x2] = flip($row["sx"], $row["dx"], $flip);
    [$y1, $y2] = flip($row["sy"], $row["dy"], $flip);
    [$z1, $z2] = flip($row["sz"] * METERS_PER_FOOT, $row["dz"] * METERS_PER_FOOT, $flip);
    [$src_ap, $dst_ap] = flip(
        format_apcode2($row["siata"], $row["sicao"]),
        format_apcode2($row["diata"], $row["dicao"]),
        $flip
    );
    [$srcTZ, $dstTZ] = flip($row["stz"], $row["dtz"], $flip);

    $code = $row["code"];
    $mode = $row["mode"];

    print "<Placemark>
";
    print "  <name>$src_ap-$dst_ap</name>
  <description>" . MODES[$mode] . " $code</description>
";
    print "  <styleUrl>#$mode</styleUrl>
";

    $points = gcPath(
        ["x" => $x1, "y" => $y1, "z" => $z1],
        ["x" => $x2, "y" => $y2, "z" => $z2],
        null,
        true
    );

    if ($row["mode"] == "F") {
        $altitudeMode = "absolute";
    } else {
        $altitudeMode = "clampToGround";
    }

    // Do we care about Daylight Savings? airports.dst
    $startTime = new DateTime($row["src_date"] . 'T' . $row["src_time"]);
    if ($srcTZ !== null) {
        $startTime->setTimezone(new DateTimeZone($srcTZ));
    }
    $formattedStartTime = $startTime->format(DATE_FORMAT);

    $intervalString = parseIntervalString($row["duration"]);

    if ($intervalString !== false) {
        $arrivalTime = (clone $startTime)
            ->add(new DateInterval($intervalString));

        if ($dstTZ !== null) {
            $arrivalTime->setTimezone(new DateTimeZone($dstTZ));
        }
        $formattedEndTime = $arrivalTime->format(DATE_FORMAT);
    } else {
        // Mark start time and end time the same...
        $formattedEndTime = $formattedStartTime;
    }
    print "  <TimeSpan>
    <begin>$formattedStartTime</begin>
    <end>$formattedEndTime</end>
  </TimeSpan>
";
    print "  <MultiGeometry>
    <LineString>
      <altitudeMode>$altitudeMode</altitudeMode>
      <coordinates>
  ";
    foreach ($points as $loc) {
        if (!$loc) {
            // skip breaks
            continue;
        }
        print $loc["x"] . "," . $loc["y"] . "," . $loc["z"] . "\n";
    }
    print "      </coordinates>
    </LineString>
  </MultiGeometry>
</Placemark>
";
}

print "</Folder>
";
print "<Folder>
  <name>Airports</name>
";

// Draw airports from largest to smallest
$airportColors = ["black", "gray", "purple", "cyan", "cyan", "green"];

$sql = <<<SQL
    SELECT DISTINCT x, y, elevation, iata, icao, name, city, country, count(name) AS visits
    FROM flights AS f, airports AS a
    WHERE (f.src_apid = a.apid OR f.dst_apid = a.apid) AND f.uid = :uid $filterString
    GROUP BY a.apid,name
    ORDER BY visits DESC
SQL;
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

    print "<Placemark>
";
    print "  <name>" . format_apcode($row) . "</name>
";
    printf(
        "  <description>
    <![CDATA[
<b>%s</b><br><i>Elevation</i>: %s ft<br><i>Flights</i>: %s
]]>
  </description>
",
        format_airport($row),
        $row["elevation"],
        $count
    );
    print "  <Point>
";
    printf(
        "    <coordinates>%s,%s,%s</coordinates>
",
        $row["x"],
        $row["y"],
        $row["elevation"]
    );
    print "  </Point>
";
    print "  <styleUrl>#{$airportColors[$colorIndex]}-pushpin</styleUrl>
";
    print "</Placemark>
";
}
print "</Folder>
";
readfile("../kml/footer.kml");
