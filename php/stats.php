<?php

include_once 'locale.php';
include_once 'db_pdo.php';
include_once 'helper.php';
include_once 'filter.php';

$public = "O"; // by default...

// If not logged in, default to demo mode
$uid = $_SESSION["uid"] ?? $OF_DEMO_UID;

// This applies only when viewing another user's flights
$user = $_POST["user"] ?? ($_GET["user"] ?? null);
$trid = $_POST["trid"] ?? null;

// Verify that this trip and user are public
if ($uid == 1 && $trid && $trid != "0") {
    // Verify that we're allowed to access this trip
    $sql = "SELECT * FROM trips WHERE trid = ?";
    $sth = $dbh->prepare($sql);
    $sth->execute([$trid]);
    $row = $sth->fetch();
    if (!$row) {
        die('Error;' . _("Trip not found."));
    }

    $public = $row["public"];
    if ($row["uid"] != $uid && $public == "N") {
        die('Error;' . _("This trip is not public."));
    }

    $uid = $row["uid"];
}
if ($user && $user != "0") {
    // Verify that we're allowed to view this user's flights
    $sql = "SELECT uid, public FROM users WHERE name = ?";
    $sth = $dbh->prepare($sql);
    $sth->execute([$user]);
    $row = $sth->fetch();
    if (!$row) {
        die('Error;' . _("User not found."));
    }

    $public = $row["public"];
    if ($public == "N") {
        die('Error;' . _("This user's flights are not public."));
    }

    $uid = $row["uid"];
}
$filter = "f.uid = " . $uid . getFilterString($dbh, $_POST);
$array = [];

// Convert mi to km if units=K
$units = $_SESSION["units"] ?? null;
if ($units == "K") {
    $unit = _("km");
    $multiplier = "* " . KM_PER_MILE;
} else {
    $unit = _("mi");
    $multiplier = "";
}

// unique airports, and countries
$sql = "SELECT COUNT(DISTINCT a.apid) AS num_airports, COUNT(DISTINCT a.country) AS num_countries
FROM flights AS f,airports AS a
WHERE (f.src_apid = a.apid OR f.dst_apid = a.apid) AND $filter";
$sth = $dbh->query($sql);
$row = $sth->fetch();
if ($row) {
    $array += $row;
}

// unique airlines (excluding unknown), unique planes, total distance (mi), average distance (localized), average duration
$sql = "SELECT COUNT(DISTINCT case when alid <> -1 then alid end) AS num_airlines, COUNT(DISTINCT plid) AS num_planes,
IFNULL(SUM(distance), 0) AS distance, IFNULL(ROUND(AVG(distance) $multiplier),0) AS avg_distance,
IFNULL(TIME_FORMAT(SEC_TO_TIME(SUM(TIME_TO_SEC(duration))/COUNT(duration)), '%H:%i'), '00:00') AS avg_duration
FROM flights AS f
WHERE $filter";
$sth = $dbh->query($sql);
$row = $sth->fetch();
if ($row) {
    $array += $row;
}
$array["avg_distance"] .= " " . $unit;
$array["localedist"] = round($array["distance"] * ($units == "K" ? KM_PER_MILE : 1)) . " " . $unit;
print json_encode($array) . "\n";

// longest and shortest
// 0 desc, 1 distance, 2 duration, 3 src_iata, 4 src_icao, 5 src_apid, 6 dst_iata, 7 dst_icao, 8 dst_apid
$sql = sprintf(
<<<SQL
(
    SELECT '%s', ROUND(f.distance %s) AS distance, DATE_FORMAT(duration, '%%H:%%i') AS duration, s.iata, s.icao, s.apid, d.iata, d.icao, d.apid
    FROM flights AS f, airports AS s, airports AS d
    WHERE f.src_apid = s.apid AND f.dst_apid = d.apid AND $filter
    ORDER BY distance DESC
    LIMIT 1
)
UNION 
(
    SELECT '%s', ROUND(f.distance %s) AS distance, DATE_FORMAT(duration, '%%H:%%i') AS duration, s.iata, s.icao, s.apid, d.iata, d.icao, d.apid
    FROM flights AS f, airports AS s, airports AS d
    WHERE f.src_apid = s.apid AND f.dst_apid = d.apid AND $filter
    ORDER BY distance ASC
    LIMIT 1
)
SQL,
    _("Longest"),
    $multiplier,
    _("Shortest"),
    $multiplier
);
$sth = $dbh->query($sql);
$rows = [];
foreach ($sth as $row) {
    $src_code = format_apcode2($row[3], $row[4]);
    $dst_code = format_apcode2($row[6], $row[7]);
    $rows[] = sprintf(
        "%s,%s %s,%s,%s,%s,%s,%s",
        $row[0],
        $row[1],
        $unit,
        $row[2],
        $src_code,
        $row[5],
        $dst_code,
        $row[8]
    );
}
echo implode(";", $rows) . "\n";

// North, South, West, East
// 0 desc, 1 iata, 2 icao, 3 apid, 4 x, 5 y
$sql = sprintf(
<<<SQL
(
    SELECT '%s', iata, icao, apid, x, y
    FROM airports WHERE y = (
        SELECT MAX(y)
        FROM airports AS a, flights AS f
        WHERE (f.src_apid = a.apid OR f.dst_apid = a.apid) AND $filter
    )
    ORDER BY iata
    LIMIT 1
)
UNION
(
    SELECT '%s',iata,icao,apid,x,y
    FROM airports
    WHERE y = (
        SELECT MIN(y)
        FROM airports AS a, flights AS f
        WHERE (f.src_apid = a.apid OR f.dst_apid = a.apid) AND $filter
    )
    ORDER BY iata
    LIMIT 1
)
UNION
(
    SELECT '%s',iata,icao,apid,x,y
    FROM airports
    WHERE x = (
        SELECT MIN(x)
        FROM airports AS a, flights AS f
        WHERE (f.src_apid = a.apid OR f.dst_apid = a.apid) AND $filter
    )
    ORDER BY iata
    LIMIT 1
)
UNION
(
    SELECT '%s',iata,icao,apid,x,y
    FROM airports WHERE x=(
        SELECT MAX(x) FROM airports AS a, flights AS f WHERE (f.src_apid = a.apid OR f.dst_apid = a.apid) AND $filter
    )
    ORDER BY iata
    LIMIT 1
)
SQL,
    addslashes(_("Northernmost")),
    addslashes(_("Southernmost")),
    addslashes(_("Westernmost")),
    addslashes(_("Easternmost"))
);

$sth = $dbh->query($sql);
$rows = [];
foreach ($sth as $row) {
    $code = format_apcode2($row[1], $row[2]);
    $rows[] = sprintf("%s,%s,%s,%s,%s", $row[0], $code, $row[3], $row[4], $row[5]);
}
echo implode(":", $rows) . "\n";

// Censor remaining info unless in full-public mode
if ($public != "O") {
    print "\n\n\n";
    exit;
}

// Classes (by number of flights and distance)
$sql = "SELECT DISTINCT class, COUNT(*) num_flights, SUM(distance) distance
    FROM flights AS f
    WHERE $filter AND class != ''
    GROUP BY class
    ORDER BY class
";
$sth = $dbh->query($sql);
$classByFlight = [];
$classByDistance = [];
foreach ($sth as $row) {
    $classByFlight[] = "$row[0],$row[1]";
    $classByDistance[] = "$row[0],$row[2]";
}
echo implode(":", $classByFlight) . "\n";

// Reason
$sql = "SELECT DISTINCT reason, COUNT(*)
    FROM flights AS f
    WHERE $filter AND reason != ''
    GROUP BY reason
    ORDER BY reason
";
$sth = $dbh->query($sql);
$rows = [];
foreach ($sth as $row) {
    $rows[] = sprintf("%s,%s", $row[0], $row[1]);
}
echo implode(":", $rows) . "\n";

// Seat Type
$sql = "SELECT DISTINCT seat_type, COUNT(*)
    FROM flights AS f
    WHERE $filter AND seat_type != ''
    GROUP BY seat_type
    ORDER BY seat_type
";
$sth = $dbh->query($sql);
$rows = [];
foreach ($sth as $row) {
    $rows[] = sprintf("%s,%s", $row[0], $row[1]);
}
echo implode(":", $rows) . "\n";

// Mode
$sql = "SELECT DISTINCT mode, COUNT(*)
    FROM flights AS f
    WHERE $filter
    GROUP BY mode
    ORDER BY mode
";
$sth = $dbh->query($sql);
$rows = [];
foreach ($sth as $row) {
    $rows[] = sprintf("%s,%s", $row[0], $row[1]);
}
echo implode(":", $rows) . "\n";

// Class (by distance); added at the end. This is to not break other potential API users.
echo implode(":", $classByDistance) . "\n";
