<?php

include 'locale.php';
include 'db_pdo.php';
include 'helper.php';
include 'filter.php';

// If not logged in, default to demo mode
$uid = $_SESSION["uid"] ?? 1;
$units = $_SESSION["units"] ?? null;

// This applies only when viewing another users flights
$user = $_POST["user"] ?? null;
$trid = $_POST["trid"] ?? null;

$mode = $_POST["mode"] ?? null;
switch ($mode) {
    case "D":
        $mode = "SUM(distance)";
        if ($units == "K") {
            $mode = "ROUND($mode * KM_PER_MILE)";
        }
        break;

    case "F":
    default:
        $mode = "COUNT(fid)";
        break;
}

$limit = $_POST["limit"] ?? "10";
if ($limit == "-1") {
    $limit = "9999";
}

// Verify that this trip and user are public
if ($uid == 1 && $trid && $trid != "0") {
    // Verify that we're allowed to access this trip
    $sth = $dbh->prepare("SELECT * FROM trips WHERE trid=?");
    $sth->execute([$trid]);
    if ($row = $sth->fetch()) {
        if ($row["uid"] != $uid and $row["public"] == "N") {
            die('Error;' . _("This trip is not public."));
        } else {
            $uid = $row["uid"];
        }
    }
}
if ($user && $user != "0") {
    // Verify that we're allowed to view this user's flights
    $sth = $dbh->prepare("SELECT uid,public FROM users WHERE name=?");
    $sth->execute([$user]);
    if ($row = $sth->fetch()) {
        if ($row["public"] == "N") {
            die('Error;' . _("This user's flights are not public."));
        } else {
            $uid = $row["uid"];
        }
    }
}
$filter = getFilterString($dbh, $_POST);

// List top 10 routes
$sql = "SELECT DISTINCT s.iata AS siata,s.icao AS sicao,s.apid AS sapid,d.iata AS diata,d.icao AS dicao,d.apid AS dapid,$mode AS times FROM flights AS f, airports AS s, airports AS d WHERE f.src_apid=s.apid AND f.dst_apid=d.apid AND f.uid=:uid $filter GROUP BY s.apid,d.apid ORDER BY times DESC LIMIT :limit";
$sth = $dbh->prepare($sql);
$sth->bindValue(':uid', $uid, PDO::PARAM_INT);
$sth->bindValue(':limit', intval($limit), PDO::PARAM_INT);
$sth->execute();
$first = true;
while ($row = $sth->fetch()) {
    if ($first) {
        $first = false;
    } else {
        printf(":");
    }
    $src = format_apcode2($row["siata"], $row["sicao"]);
    $dst = format_apcode2($row["diata"], $row["dicao"]);
    printf("%s,%s,%s,%s,%s", $src, $row["sapid"], $dst, $row["dapid"], $row["times"]);
}
printf("\n");

// List top 10 airports

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
$first = true;
while ($row = $sth->fetch()) {
    if ($first) {
        $first = false;
    } else {
        printf(":");
    }
    $code = format_apcode($row);
    printf("%s,%s,%s,%s", $row["name"], $code, $row["count"], $row["apid"]);
}
printf("\n");

// List top 10 airlines
$sql = "select a.name, $mode as count, a.alid from airlines as a, flights as f where f.uid=:uid and f.alid=a.alid $filter group by f.alid order by count desc limit :limit";
$sth = $dbh->prepare($sql);
$sth->bindValue(':uid', $uid, PDO::PARAM_INT);
$sth->bindValue(':limit', intval($limit), PDO::PARAM_INT);
$sth->execute();
$first = true;
while ($row = $sth->fetch()) {
    if ($first) {
        $first = false;
    } else {
        printf(":");
    }
    printf("%s,%s,%s", $row["name"], $row["count"], $row["alid"]);
}
printf("\n");

// List top 10 plane types
$sql = "select p.name, $mode as count from planes as p, flights as f where f.uid=:uid and p.plid=f.plid $filter group by f.plid order by count desc limit :limit";
$sth = $dbh->prepare($sql);
$sth->bindValue(':uid', $uid, PDO::PARAM_INT);
$sth->bindValue(':limit', intval($limit), PDO::PARAM_INT);
$sth->execute();
$first = true;
while ($row = $sth->fetch()) {
    if ($first) {
        $first = false;
    } else {
        printf(":");
    }
    printf("%s,%s", $row["name"], $row["count"]);
}
