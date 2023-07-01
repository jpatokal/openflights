<?php

include 'locale.php';
include 'db_pdo.php';
include 'helper.php';
include 'filter.php';

// ----- Session parameters -----
$units = $_SESSION["units"] ?? "M";
$uid = $_SESSION["uid"] ?? null;
if (!$uid || empty($uid)) {
    // If not logged in, default to demo mode
    $uid = 1;
}

// ----- Request parameters -----
// For backwards-compatibility reasons with the front-end, value "0" is special
// and processed the same way null.

// "user" applies only when viewing another users flights
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
            $mode = "ROUND($mode * KM_PER_MILE)";
            $data["countUnit"] = "kilometers";
        }
        break;

    case "F":
    default:
        $mode = "COUNT(fid)";
        $data["countUnit"] = "segments";
        break;
}

// Verify that this trip and user are public
if ($uid == 1 && $trid && $trid != "0") {
    // Verify that we're allowed to access this trip
    $sth = $dbh->prepare("SELECT * FROM trips WHERE trid=?");
    $sth->execute([$trid]);
    if ($row = $sth->fetch()) {
        if ($row["uid"] != $uid and $row["public"] == "N") {
            die(json_encode(["error" => _("This trip is not public.")]));
        } else {
            $uid = $row["uid"];
        }
    } else {
        die(json_encode(["error" => _("No such trip.")]));
    }
}
if ($user && $user != "0") {
    // Verify that we're allowed to view this user's flights
    $sth = $dbh->prepare("SELECT uid,public FROM users WHERE name=?");
    $sth->execute([$user]);
    if ($row = $sth->fetch()) {
        if ($row["public"] == "N") {
            die(json_encode(["error" => _("This user's flights are not public.")]));
        } else {
            $uid = $row["uid"];
        }
    } else {
        die(json_encode(["error" => _("No such user.")]));
    }
}
$filter = getFilterString($dbh, $_POST);

// List top $limit routes
$sql = "SELECT DISTINCT s.iata AS siata,s.icao AS sicao,s.apid AS sapid,d.iata AS diata,d.icao AS dicao,d.apid AS dapid,$mode AS times FROM flights AS f, airports AS s, airports AS d WHERE f.src_apid=s.apid AND f.dst_apid=d.apid AND f.uid=:uid $filter GROUP BY s.apid,d.apid ORDER BY times DESC LIMIT :limit";
$sth = $dbh->prepare($sql);
$sth->bindValue(':uid', $uid, PDO::PARAM_INT);
$sth->bindValue(':limit', intval($limit), PDO::PARAM_INT);
$sth->execute();
while ($row = $sth->fetch()) {
    array_push($data["routes"], [
        "src_code" => format_apcode2($row["siata"], $row["sicao"]),
        "src_apid" => (int) $row["sapid"],
        "dst_code" => format_apcode2($row["diata"], $row["dicao"]),
        "dst_apid" => (int) $row["dapid"],
        "count" => (int) $row["times"]
    ]);
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
(select src_apid AS apid, distance, fid FROM flights AS f WHERE uid = :uid $filter
UNION ALL
select dst_apid as apid, distance, fid from flights AS f WHERE uid = :uid $filter) AS f
WHERE f.apid=a.apid
GROUP BY a.apid ORDER BY count DESC limit :limit
SQL;

$sth = $dbh->prepare($sql);
$sth->bindValue(':uid', $uid, PDO::PARAM_INT);
$sth->bindValue(':limit', intval($limit), PDO::PARAM_INT);
$sth->execute();
while ($row = $sth->fetch()) {
    array_push($data["airports"], [
        "name" => $row["name"],
        "code" => format_apcode($row),
        "count" => (int) $row["count"],
        "apid" => (int) $row["apid"]
    ]);
}
// List top $limit airlines
$sql = "select a.name, $mode as count, a.alid from airlines as a, flights as f where f.uid=:uid and f.alid=a.alid $filter group by f.alid order by count desc limit :limit";
$sth = $dbh->prepare($sql);
$sth->bindValue(':uid', $uid, PDO::PARAM_INT);
$sth->bindValue(':limit', intval($limit), PDO::PARAM_INT);
$sth->execute();
while ($row = $sth->fetch()) {
    array_push($data["airlines"], [
        "name" => $row["name"],
        "count" => (int) $row["count"],
        "alid" => (int) $row["alid"]
    ]);
}

// List top $limit plane types
$sql = "select p.name, $mode as count from planes as p, flights as f where f.uid=:uid and p.plid=f.plid $filter group by f.plid order by count desc limit :limit";
$sth = $dbh->prepare($sql);
$sth->bindValue(':uid', $uid, PDO::PARAM_INT);
$sth->bindValue(':limit', intval($limit), PDO::PARAM_INT);
$sth->execute();
while ($row = $sth->fetch()) {
    array_push($data["planes"], [
        "name" => $row["name"],
        "count" => (int) $row["count"]
    ]);
}

print(json_encode($data));
