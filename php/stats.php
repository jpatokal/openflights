<?php

include 'locale.php';
include 'db_pdo.php';
include 'helper.php';
include 'filter.php';

$uid = $_SESSION["uid"];
$public = "O"; // by default...
if (!$uid || empty($uid)) {
    // If not logged in, default to demo mode
    $uid = 1;
}

// This applies only when viewing another users flights
$user = $_POST["user"];
if (!$user) {
    $user = $_GET["user"];
}
$trid = $_POST["trid"];

// Verify that this trip and user are public
$filter = "";

if ($uid == 1 && $trid && $trid != "0") {
    // Verify that we're allowed to access this trip
    $sql = "SELECT * FROM trips WHERE trid=?";
    $sth = $dbh->prepare($sql);
    $sth->execute([$trid]);
    if ($row = $sth->fetch()) {
        $public = $row["public"];
        if ($row["uid"] != $uid && $public == "N") {
            die('Error;' . _("This trip is not public."));
        } else {
            $uid = $row["uid"];
        }
    } else {
        die('Error;' . _("Trip not found."));
    }
}
if ($user && $user != "0") {
    // Verify that we're allowed to view this user's flights
    $sql = "SELECT uid,public FROM users WHERE name=?";
    $sth = $dbh->prepare($sql);
    $sth->execute([$user]);
    if ($row = $sth->fetch()) {
        $public = $row["public"];
        if ($public == "N") {
            die('Error;' . _("This user's flights are not public."));
        } else {
            $uid = $row["uid"];
        }
    } else {
        die('Error;' . _("User not found."));
    }
}
$filter = "f.uid=" . $uid . getFilterString($dbh, $_POST);
$array = array();

// Convert mi to km if units=K
$units = $_SESSION["units"];
if ($units == "K") {
    $unit = _("km");
    $multiplier = "* " . KM_PER_MILE;
} else {
    $unit = _("mi");
    $multiplier = "";
}

// unique airports, and countries
$sql = "SELECT COUNT(DISTINCT a.apid) AS num_airports, COUNT(DISTINCT a.country) AS num_countries FROM flights AS f,airports AS a WHERE (f.src_apid=a.apid OR f.dst_apid=a.apid) AND " . $filter;
$sth = $dbh->query($sql);
if ($row = $sth->fetch()) {
    $array += $row;
}

// unique airlines, unique planes, total distance (mi), average distance (localized), average duration
$sql = "SELECT COUNT(DISTINCT alid) AS num_airlines, COUNT(DISTINCT plid) AS num_planes, SUM(distance) AS distance, ROUND(AVG(distance) $multiplier) AS avg_distance, TIME_FORMAT(SEC_TO_TIME(SUM(TIME_TO_SEC(duration))/COUNT(duration)), '%H:%i') AS avg_duration FROM flights AS f WHERE " . $filter;
$sth = $dbh->query($sql);
if ($row = $sth->fetch()) {
    $array += $row;
}
$array["avg_distance"] .= " " . $unit;
$array["localedist"] = round($array["distance"] * ($units == "K" ? KM_PER_MILE : 1)) . " " . $unit;
print json_encode($array) . "\n";

// longest and shortest
// 0 desc, 1 distance, 2 duration, 3 src_iata, 4 src_icao, 5 src_apid, 6 dst_iata, 7 dst_icao, 8 dst_apid
$sql = sprintf(
    "(SELECT '%s',ROUND(f.distance %s) AS distance,DATE_FORMAT(duration, '%%H:%%i') AS duration,s.iata,s.icao,s.apid,d.iata,d.icao,d.apid FROM flights AS f,airports AS s,airports AS d WHERE f.src_apid=s.apid AND f.dst_apid=d.apid AND " . $filter . " ORDER BY distance DESC LIMIT 1) UNION " .
    "(SELECT '%s',ROUND(f.distance %s) AS distance,DATE_FORMAT(duration, '%%H:%%i') AS duration,s.iata,s.icao,s.apid,d.iata,d.icao,d.apid FROM flights AS f,airports AS s,airports AS d WHERE f.src_apid=s.apid AND f.dst_apid=d.apid AND " . $filter . " ORDER BY distance ASC LIMIT 1)",
    _("Longest"),
    $multiplier,
    _("Shortest"),
    $multiplier
);
$sth = $dbh->query($sql);
$first = true;
foreach ($sth as $row) {
    if ($first) {
        $first = false;
    } else {
        printf(";");
    }
    $src_code = format_apcode2($row[3], $row[4]);
    $dst_code = format_apcode2($row[6], $row[7]);
    printf("%s,%s %s,%s,%s,%s,%s,%s", $row[0], $row[1], $unit, $row[2], $src_code, $row[5], $dst_code, $row[8]);
}
printf("\n");

// North, South, West, East
// 0 desc, 1 iata, 2 icao, 3 apid, 4 x, 5 y
$sql = sprintf(
    "(SELECT '%s',iata,icao,apid,x,y FROM airports WHERE y=(SELECT MAX(y) FROM airports AS a, flights AS f WHERE (f.src_apid=a.apid OR f.dst_apid=a.apid) AND " . $filter . ") ORDER BY iata LIMIT 1) UNION " .
    "(SELECT '%s',iata,icao,apid,x,y FROM airports WHERE y=(SELECT MIN(y) FROM airports AS a, flights AS f WHERE (f.src_apid=a.apid OR f.dst_apid=a.apid) AND " . $filter . ") ORDER BY iata LIMIT 1) UNION " .
    "(SELECT '%s',iata,icao,apid,x,y FROM airports WHERE x=(SELECT MIN(x) FROM airports AS a, flights AS f WHERE (f.src_apid=a.apid OR f.dst_apid=a.apid) AND " . $filter . ") ORDER BY iata LIMIT 1) UNION " .
    "(SELECT '%s',iata,icao,apid,x,y FROM airports WHERE x=(SELECT MAX(x) FROM airports AS a, flights AS f WHERE (f.src_apid=a.apid OR f.dst_apid=a.apid) AND " . $filter . ") ORDER BY iata LIMIT 1)",
    addslashes(_("Northernmost")),
    addslashes(_("Southernmost")),
    addslashes(_("Westernmost")),
    addslashes(_("Easternmost"))
);

$sth = $dbh->query($sql);
$first = true;
foreach ($sth as $row) {
    if ($first) {
        $first = false;
    } else {
        printf(":");
    }
    $code = format_apcode2($row[1], $row[2]);
    printf("%s,%s,%s,%s,%s", $row[0], $code, $row[3], $row[4], $row[5]);
}
printf("\n");

// Censor remaining info unless in full-public mode
if ($public != "O") {
    print "\n\n\n";
    exit;
}

// Classes (by number of flights and distance)
$sql = "SELECT DISTINCT class,COUNT(*) num_flights,SUM(distance) distance FROM flights AS f WHERE " . $filter . " AND class != '' GROUP BY class ORDER BY class";
$sth = $dbh->query($sql);
$class_by_flight_str = '';
$class_by_distance_str = '';
foreach ($sth as $row) {
    if (!empty($class_by_flight_str)) {
        $class_by_flight_str .= ':';
        $class_by_distance_str .= ':';
    }
    $class_by_flight_str .= "$row[0],$row[1]";
    $class_by_distance_str .= "$row[0],$row[2]";
}
printf("$class_by_flight_str\n");

// Reason
$sql = "SELECT DISTINCT reason,COUNT(*) FROM flights AS f WHERE " . $filter . " AND reason != '' GROUP BY reason ORDER BY reason";
$sth = $dbh->query($sql);
$first = true;
foreach ($sth as $row) {
    if ($first) {
        $first = false;
    } else {
        printf(":");
    }
    printf("%s,%s", $row[0], $row[1]);
}
printf("\n");

// Seat Type
$sql = "SELECT DISTINCT seat_type,COUNT(*) FROM flights AS f WHERE " . $filter . " AND seat_type != '' GROUP BY seat_type ORDER BY seat_type";
$sth = $dbh->query($sql);
$first = true;
foreach ($sth as $row) {
    if ($first) {
        $first = false;
    } else {
        printf(":");
    }
    printf("%s,%s", $row[0], $row[1]);
}
printf("\n");

// Mode
$sql = "SELECT DISTINCT mode,COUNT(*) FROM flights AS f WHERE " . $filter . " GROUP BY mode ORDER BY mode";
$sth = $dbh->query($sql);
$first = true;
foreach ($sth as $row) {
    if ($first) {
        $first = false;
    } else {
        printf(":");
    }
    printf("%s,%s", $row[0], $row[1]);
}
printf("\n");

// Class (by distance); added at the end. This is to not break other potential API users.
printf("$class_by_distance_str\n");
