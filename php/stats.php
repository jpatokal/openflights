<?php

include_once 'locale.php';
include_once 'config.php';
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
        die(json_encode(["error" => _("Trip not found.")]));
    }

    $public = $row["public"];
    if ($row["uid"] != $uid && $public == "N") {
        die(json_encode(["error" => _("This trip is not public.")]));
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
        die(json_encode(["error" => _("User not found.")]));
    }

    $public = $row["public"];
    if ($public == "N") {
        die(json_encode(["error" => _("This user's flights are not public.")]));
    }

    $uid = $row["uid"];
}
$filter = "f.uid = " . $uid . getFilterString($dbh, $_POST);

// Convert mi to km if units=K
$units = $_SESSION["units"] ?? null;
if ($units == "K") {
    $unit = "km";
    $unitMultiplier = KM_PER_MILE;
} else {
    $unit = "mi";
    $unitMultiplier = 1;
}

header("Content-type: application/json; charset=utf-8");
$response = [
    // All distance values in this message will be of this unit ("km" or "mi")
    // unless the unit is indicated in the property name.
    "distance_unit" => $unit,
    "unique" => [],
    "extreme" => [],
    "longshort" => [],
    "total" => [
        "segments" => 0
    ],
    "average" => [],
    "by_reason" => [],
    "by_seattype" => [],
    "by_mode" => [],
    "by_class" => [],
];

// unique airports, and countries
$sql = "SELECT COUNT(DISTINCT a.apid) AS num_airports, COUNT(DISTINCT a.country) AS num_countries
FROM flights AS f,airports AS a
WHERE (f.src_apid = a.apid OR f.dst_apid = a.apid) AND $filter";
$sth = $dbh->query($sql, PDO::FETCH_ASSOC);
$row = $sth->fetch();
if ($row) {
    $response["unique"]["airports"] = (int)$row["num_airports"];
    $response["unique"]["countries"] = (int)$row["num_countries"];
}

// unique airlines (excluding unknown), unique planes, total segments,
// total distance (mi), average distance (mi),
// total duration (s), average duration
$sql = <<<SQL
SELECT COUNT(DISTINCT case when alid <> -1 then alid end) AS num_airlines,
       COUNT(DISTINCT plid) AS num_planes,
       COUNT(*) as segments,
       IFNULL(SUM(distance), 0) AS distance,
       IFNULL(AVG(distance), 0) AS avg_distance,
       IFNULL(SUM(TIME_TO_SEC(duration)), 0) AS duration,
       IFNULL(TIME_FORMAT(SEC_TO_TIME(SUM(TIME_TO_SEC(duration))/COUNT(duration)), '%H:%i'), '00:00') AS avg_duration
FROM flights AS f
WHERE $filter
SQL;
$sth = $dbh->query($sql, PDO::FETCH_ASSOC);
$row = $sth->fetch();
if ($row) {
    $response["unique"] += [
        "carriers" => (int)$row["num_airlines"],
        "vehicles" => (int)$row["num_planes"]
    ];
    $response["total"] = [
        "segments" => (int)$row["segments"],
        "distance_mi" => round((int)$row["distance"]),
        "distance_km" => round((int)$row["distance"] * KM_PER_MILE),
        "distance" => round((int)$row["distance"] * $unitMultiplier),
        "duration_s" => round((int)$row["distance"] * KM_PER_MILE),
    ];
    $response["average"] = [
        "distance" => round((int)$row["avg_distance"] * $unitMultiplier),
        "duration" => $row["avg_duration"]
    ];
}

// longest and shortest
$sql = <<<SQL
(
    SELECT 'longest' AS prop, f.distance, DATE_FORMAT(duration, '%H:%i') AS duration,
           s.iata AS src_iata, s.icao AS src_icao, s.apid AS src_apid,
           d.iata AS dst_iata, d.icao AS dst_icao, d.apid AS dst_apid
    FROM flights AS f, airports AS s, airports AS d
    WHERE f.src_apid = s.apid AND f.dst_apid = d.apid AND $filter
    ORDER BY distance DESC
    LIMIT 1
)
UNION
(
    SELECT 'shortest' AS prop, f.distance, DATE_FORMAT(duration, '%H:%i') AS duration,
           s.iata AS src_iata, s.icao AS src_icao, s.apid AS src_apid,
           d.iata AS dst_iata, d.icao AS dst_icao, d.apid AS dst_apid
    FROM flights AS f, airports AS s, airports AS d
    WHERE f.src_apid = s.apid AND f.dst_apid = d.apid AND $filter
    ORDER BY distance ASC
    LIMIT 1
)
SQL;
$sth = $dbh->query($sql, PDO::FETCH_ASSOC);
foreach ($sth as $row) {
    $src_code = format_apcode2($row["src_iata"], $row["src_icao"]);
    $dst_code = format_apcode2($row["dst_iata"], $row["dst_icao"]);
    $response["longshort"][$row["prop"]] = [
        "src_code" => $src_code,
        "src_apid" => (int)$row["src_apid"],
        "dst_code" => $dst_code,
        "dst_apid" => (int)$row["dst_apid"],
        "distance" => round((int)$row["distance"] * $unitMultiplier),
        "duration" => $row["duration"]
    ];
}

$sql = <<<SQL
WITH visited_airports AS (
    SELECT a.iata, a.icao, a.apid, a.x, a.y
    FROM flights f
    JOIN airports a ON (f.src_apid = a.apid OR f.dst_apid = a.apid)
    WHERE $filter
    ORDER BY a.apid ASC
)
(SELECT 'N' dir, a.* FROM visited_airports a ORDER BY y DESC LIMIT 1)
UNION ALL
(SELECT 'S' dir, a.* FROM visited_airports a ORDER BY y ASC LIMIT 1)
UNION ALL
(SELECT 'E' dir, a.* FROM visited_airports a ORDER BY x DESC LIMIT 1)
UNION ALL
(SELECT 'W' dir, a.* FROM visited_airports a ORDER BY x ASC LIMIT 1)
SQL;

$sth = $dbh->query($sql, PDO::FETCH_ASSOC);
foreach ($sth as $row) {
    $code = format_apcode2($row["iata"], $row["icao"]);
    $response["extreme"][$row["dir"]] = [
        "code" => $code,
        "apid" => (int)$row["apid"],
        "lat" => (float)$row["x"],
        "lon" => (float)$row["y"],
    ];
}

// Censor remaining info unless in full-public mode
if ($public != "O") {
    echo json_encode($response);
    exit;
}

// Classes
$sql = <<<SQL
    SELECT DISTINCT class, COUNT(*) segments, SUM(distance) distance
    FROM flights AS f
    WHERE $filter AND class != ''
    GROUP BY class
    ORDER BY class
SQL;
$sth = $dbh->query($sql, PDO::FETCH_ASSOC);
foreach ($sth as $row) {
    $response["by_class"][] = [
        "class" => $row["class"],
        "segments" => (int)$row["segments"],
        "distance" => round((int)$row["distance"] * $unitMultiplier),
    ];
}

// Reason
$sql = <<<SQL
    SELECT DISTINCT reason, COUNT(*) segments, SUM(distance) distance
    FROM flights AS f
    WHERE $filter AND reason != ''
    GROUP BY reason
    ORDER BY reason
SQL;
$sth = $dbh->query($sql, PDO::FETCH_ASSOC);
foreach ($sth as $row) {
    $response["by_reason"][] = [
        "reason" => $row["reason"],
        "segments" => (int)$row["segments"],
        "distance" => round((int)$row["distance"] * $unitMultiplier),
    ];
}

// Seat Type
$sql = <<<SQL
    SELECT DISTINCT seat_type, COUNT(*) segments, SUM(distance) distance
    FROM flights AS f
    WHERE $filter AND seat_type != ''
    GROUP BY seat_type
    ORDER BY seat_type
SQL;
$sth = $dbh->query($sql, PDO::FETCH_ASSOC);
foreach ($sth as $row) {
    $response["by_seattype"][] = [
        "seattype" => $row["seat_type"],
        "segments" => (int)$row["segments"],
        "distance" => round((int)$row["distance"] * $unitMultiplier),
    ];
}

// Mode
$sql = <<<SQL
    SELECT DISTINCT mode, COUNT(*) segments, SUM(distance) distance
    FROM flights AS f
    WHERE $filter
    GROUP BY mode
    ORDER BY mode
SQL;
$sth = $dbh->query($sql, PDO::FETCH_ASSOC);
foreach ($sth as $row) {
    $response["by_mode"][] = [
        "mode" => $row["mode"],
        "segments" => (int)$row["segments"],
        "distance" => round((int)$row["distance"] * $unitMultiplier),
    ];
}

echo json_encode($response);
