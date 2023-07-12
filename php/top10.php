<?php

include_once 'locale.php';
include_once 'db_pdo.php';
include_once 'helper.php';
include_once 'filter.php';

// ----- Session parameters -----
$units = $_SESSION["units"] ?? "M";
// If not logged in, default to demo mode user id
$uid = $_SESSION["uid"] ?? $OF_DEMO_UID;

// ----- Request parameters -----
// For backwards-compatibility reasons with the front-end, value "0" is special
// and processed the same way null.

// "user" applies only when viewing another user's flights
$user = $_POST["user"] ?? null;
$trid = $_POST["trid"] ?? null;
$alid = $_POST["year"] ?? null;
$mode = $_POST["mode"] ?? "F";
$limit = $_POST["limit"] ?? "9999";
// $year is also a valid parameter (used in `getFilterString`)

$data = [
    "routes" => [],
    "airports" => [],
    "airlines" => [],
    "planes" => [],
    // "countUnit" is set below
];

switch ($mode) {
    case "D":
        $mode = "SUM(distance)";
        $data["countUnit"] = "miles";
        if ($units == "K") {
            $mode = "ROUND($mode * " . KM_PER_MILE . ")";
            $data["countUnit"] = "kilometers";
        }
        break;

    case "F":
    default:
        $mode = "COUNT(fid)";
        $data["countUnit"] = "segments";
        break;
}

$limit = $_POST["limit"] ?? "10";
if ($limit == "-1") {
    $limit = "9999";
}

// Verify that this trip and user are public
if ($uid == 1 && $trid && $trid != "0") {
    // Verify that we're allowed to access this trip
    $sth = $dbh->prepare("SELECT * FROM trips WHERE trid = ?");
    $sth->execute([$trid]);
    $row = $sth->fetch();
    if (!$row) {
        die(json_encode(["error" => _("No such trip.")]));
    }

    if ($row["uid"] != $uid && $row["public"] == "N") {
        die(json_encode(["error" => _("This trip is not public.")]));
    }

    $uid = $row["uid"];
}
if ($user && $user != "0") {
    // Verify that we're allowed to view this user's flights
    $sth = $dbh->prepare("SELECT uid, public FROM users WHERE name = ?");
    $sth->execute([$user]);
    $row = $sth->fetch();
    if (!$row) {
        die(json_encode(["error" => _("No such user.")]));
    }

    if ($row["public"] == "N") {
        die(json_encode(["error" => _("This user's flights are not public.")]));
    }

    $uid = $row["uid"];
}
$filter = getFilterString($dbh, $_POST);

// List top $limit routes
$sql = "SELECT DISTINCT s.iata AS siata, s.icao AS sicao, s.apid AS sapid, d.iata AS diata, d.icao AS dicao, d.apid AS dapid, $mode AS times
    FROM flights AS f, airports AS s, airports AS d
    WHERE f.src_apid = s.apid AND f.dst_apid = d.apid AND f.uid = :uid $filter
    GROUP BY s.apid,d.apid
    ORDER BY times DESC
    LIMIT :limit
";
$sth = $dbh->prepare($sql);
$sth->bindValue(':uid', $uid, PDO::PARAM_INT);
$sth->bindValue(':limit', intval($limit), PDO::PARAM_INT);
$sth->execute();
foreach ($sth as $row) {
    $data["routes"][] = [
        "src_code" => format_apcode2($row["siata"], $row["sicao"]),
        "src_apid" => (int)$row["sapid"],
        "dst_code" => format_apcode2($row["diata"], $row["dicao"]),
        "dst_apid" => (int)$row["dapid"],
        "count" => (int)$row["times"]
    ];
}

// List top $limit airports

//$sql = "SELECT a.name, a.iata, a.icao, $mode AS count, a.apid FROM airports AS a, " .
//  "(SELECT src_apid as apid, distance, count(*) AS fid FROM flights WHERE uid = $uid GROUP BY src_apid" .
//  "  UNION ALL " .
//  "SELECT dst_apid as apid, distance, count(*) AS fid FROM flights WHERE uid = $uid GROUP BY dst_apid) as f " .
//  "WHERE f.apid=a.apid $filter " .
//  "GROUP BY a.apid ORDER BY count DESC LIMIT $limit";
//
// ^^^ this is even faster, but $mode has to be SUM(fid), not COUNT(fid), to count flights correctly...

$sql = <<<SQL
SELECT a.name, a.iata, a.icao, $mode AS count, a.apid FROM airports AS a,
(
    SELECT src_apid AS apid, distance, fid FROM flights AS f WHERE uid = :uid $filter
    UNION ALL
    SELECT dst_apid as apid, distance, fid FROM flights AS f WHERE uid = :uid $filter
) AS f
WHERE f.apid = a.apid
GROUP BY a.apid
ORDER BY count DESC
LIMIT :limit
SQL;

$sth = $dbh->prepare($sql);
$sth->bindValue(':uid', $uid, PDO::PARAM_INT);
$sth->bindValue(':limit', intval($limit), PDO::PARAM_INT);
$sth->execute();
foreach ($sth as $row) {
    $data["airports"][] = [
        "name" => $row["name"],
        "code" => format_apcode($row),
        "count" => (int)$row["count"],
        "apid" => (int)$row["apid"]
    ];
}
// List top $limit airlines
$sql = "SELECT a.name, $mode AS count, a.alid
    FROM airlines AS a, flights AS f
    WHERE f.uid = :uid AND f.alid = a.alid $filter
    GROUP BY f.alid
    ORDER BY count DESC
    LIMIT :limit
";
$sth = $dbh->prepare($sql);
$sth->bindValue(':uid', $uid, PDO::PARAM_INT);
$sth->bindValue(':limit', intval($limit), PDO::PARAM_INT);
$sth->execute();
foreach ($sth as $row) {
    $data["airlines"][] = [
        "name" => $row["name"],
        "count" => (int)$row["count"],
        "alid" => (int)$row["alid"]
    ];
}

// List top $limit plane types
$sql = "SELECT p.name, $mode AS count
    FROM planes AS p, flights AS f
    WHERE f.uid = :uid and p.plid = f.plid $filter
    GROUP BY f.plid
    ORDER BY count DESC
    LIMIT :limit
";
$sth = $dbh->prepare($sql);
$sth->bindValue(':uid', $uid, PDO::PARAM_INT);
$sth->bindValue(':limit', intval($limit), PDO::PARAM_INT);
$sth->execute();
foreach ($sth as $row) {
    $data["planes"][] = [
        "name" => $row["name"],
        "count" => (int)$row["count"]
    ];
}

print(json_encode($data));
