<?php

session_start();
$uid = $_SESSION["uid"];
$export = $_GET["export"] ?? false;
if ($export) {
    if (!$uid || empty($uid)) {
        exit("You must be logged in to export.");
    }
    if ($export == "export" || $export == "backup") {
        header("Content-type: text/csv; charset=utf-8");
        header("Content-disposition: attachment; filename=\"openflights-$export-" . date("Y-m-d-Hi") . ".csv\"");
    }
    if ($export == "export" || $export == "gcmap") {
        $trid = $_GET["trid"];
        $alid = $_GET["alid"];
        $year = $_GET["year"];
        $apid = $_GET["id"];
    }
    $fid = false;
} else {
    // export everything unfiltered
    header("Content-type: text/html; charset=utf-8");

    $apid = $_POST["id"] ?? ($_GET["id"] ?? "");
    $trid = $_POST["trid"] ?? null;
    $alid = $_POST["alid"] ?? null;
    $year = $_POST["year"] ?? null;
    $fid = $_POST["fid"] ?? null;
}

include_once 'helper.php';
include_once 'filter.php';
include_once 'db_pdo.php';

$units = $_SESSION["units"];

// Logged in?
if (!$uid || empty($uid)) {
    $uid = $_SESSION["openuid"];
    // Viewing an "open" user's flights, or an "open" flight?
    if (
        // "open" user's flights
        (!$uid || empty($uid)) ||
        // opentrid will be previously set in map.php
        // If "open" flight, check if we're limited to a single trip
        (($_SESSION["opentrid"] ?? false) != ($trid ?? null))
    ) {
        // default to demo mode if not
        $uid = $OF_DEMO_UID;
    }
}

$params = [];
$route = false;

// Special handling of "route" apids in form R<apid>,<coreid>
// <apid> is user selection, <coreid> is the ID that the airport map is centered around
$type = substr($apid, 0, 1);
if ($type == "R" || $type == "L") {
    $route = true;
    $ids = explode(',', substr($apid, 1));
    $apid = $ids[0];
    $coreid = $ids[1];
    $params['apid'] = $apid;
    if ($type == "L") {
        if ($coreid == "") {
            $match = "r.alid = :apid"; // all routes on $alid
        } else {
            $params['coreid'] = $coreid;
            $match = "r.src_apid = :coreid AND r.alid = :apid"; // flight from $coreid on $alid only
        }
    } else {
        if ($apid == $coreid) {
            $match = "r.src_apid = :apid"; // all flights from $apid
        } else {
            $params['coreid'] = $coreid;
            $match = "r.src_apid = :coreid AND r.dst_apid = :apid"; // flight from $coreid to $apid only
        }
        // Airline filter on top of airport
        if ($alid) {
            $params['alid'] = $alid;
            $match .= " AND r.alid = :alid";
        }
    }
    $sql = <<<SQL
        SELECT s.x AS sx, s.y AS sy, s.iata AS src_iata, s.icao AS src_icao, s.apid AS src_apid, d.x AS dx, d.y AS dy,
               d.iata AS dst_iata, d.icao AS dst_icao, d.apid AS dst_apid, l.iata as code, '-' as src_date,
               '-' as src_time, '-' as distance, '-:-' AS duration, '' as seat, '' as seat_type, '' as class,
               '' as reason, r.equipment AS name, '' as registration, rid AS fid, l.alid, '' AS note, NULL as trid,
               'N' AS opp, NULL as plid, l.iata AS al_iata, l.icao AS al_icao, l.name AS al_name, 'F' AS mode,
               codeshare, stops
        FROM airports AS s,airports AS d, airlines AS l,routes AS r
        WHERE $match AND r.src_apid = s.apid AND r.dst_apid = d.apid AND r.alid = l.alid
    SQL;
} else {
    // List of all this user's flights
    $params['uid'] = $uid;
    $sql = <<<SQL
        SELECT s.iata AS src_iata, s.icao AS src_icao, s.apid AS src_apid, d.iata AS dst_iata, d.icao AS dst_icao,
               d.apid AS dst_apid, f.code, f.src_date, src_time, distance, DATE_FORMAT(duration,  '%H:%i') AS duration,
               seat, seat_type, class, reason, p.name, registration, fid, l.alid, note, trid, opp, f.plid,
               l.iata AS al_iata, l.icao AS al_icao, l.name AS al_name, f.mode AS mode
        FROM airports AS s,airports AS d, airlines AS l,flights AS f
        LEFT JOIN planes AS p ON f.plid = p.plid
        WHERE f.uid = :uid AND f.src_apid = s.apid AND f.dst_apid = d.apid AND f.alid = l.alid
    SQL;

    // ...filtered by airport (optional)
    if ($apid && $apid != 0) {
        $params['apid'] = $apid;
        $sql .= " AND (s.apid = :apid OR d.apid = :apid)";
    }
}

// Add filters, if any
if ($export != "backup" && !$route) {
    $sql .= getFilterString($dbh, $_POST);
}

if ($fid && $fid != "0") {
    $params['fid'] = $fid;
    $sql .= " AND fid = :fid";
}

// And sort order
if ($route) {
    if ($type == "R") {
        $sql .= " ORDER BY d.iata ASC";
    } else {
        $sql .= " ORDER BY s.iata,d.iata ASC";
    }
} else {
    $sql .= " ORDER BY src_date DESC, src_time DESC";
}

// Execute!
$sth = $dbh->prepare($sql);
if (!$sth->execute($params)) {
    die('Error;Query ' . print_r($_GET, true) . ' caused database error ' . $sql . ', ' . $sth->errorInfo()[0]);
}

if ($export == "export" || $export == "backup") {
    // Start with byte-order mark to try to clue Excel into realizing that this is UTF-8
    print "\xEF\xBB\xBFDate,From,To,Flight_Number,Airline,Distance,Duration,Seat,Seat_Type,Class,Reason,Plane,Registration,Trip,Note,From_OID,To_OID,Airline_OID,Plane_OID\r\n";
}

/**
 * @param $src mixed
 * @param $dst mixed
 * @param $flip bool
 * @return array
 */
function flip($src, $dst, $flip) {
    // if !$flip return [ $src, $dst ]
    // if $flip return [ $dst, $src ]
    return [
        $flip ? $dst : $src,
        $flip ? $src : $dst,
    ];
}

$rows = [];
foreach ($sth as $row) {
    $toFlip = $row["opp"] == 'Y';
    [$src_code, $dst_code] = flip(
        format_apcode2($row["src_iata"], $row["src_icao"]),
        format_apcode2($row["dst_iata"], $row["dst_icao"]),
        $toFlip
    );

    if ($export == "gcmap") {
        $rows[] = urlencode($src_code . '-' . $dst_code);
        continue;
    }

    [$src_apid, $dst_apid] = flip($row["src_apid"], $row["dst_apid"], $toFlip);

    if ($route) {
        $row["distance"] = gcPointDistance(
            ["x" => $row["sx"], "y" => $row["sy"]],
            ["x" => $row["dx"], "y" => $row["dy"]]
        );
        $row["duration"] = gcDuration($row["distance"]);
        $row["code"] = $row["al_name"] . " (" . $row["code"] . ")";
        $note = $row["stops"] == "0"
            ? "Direct"
            : $row["stops"] . " stops";

        // TODO: This clobbers the $note set above... Suspect it should append?
        if ($row["codeshare"] == "Y") {
            $note = "Codeshare";
        }
    } else {
        $note = $row["note"];
    }

    $al_code = format_alcode($row["al_iata"], $row["al_icao"], $row["mode"]);

    if ($export == "export" || $export == "backup") {
        $note = "\"$note\"";
        $src_time = $row["src_time"]
            // Pad time with space if it's know
            ? " " . $row["src_time"]
            : "";

        $rows[] = sprintf(
            "%s%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s",
            $row["src_date"],
            $src_time,
            $src_code,
            $dst_code,
            $row["code"],
            $row["al_name"],
            $row["distance"],
            $row["duration"],
            $row["seat"],
            $row["seat_type"],
            $row["class"],
            $row["reason"],
            $row["name"],
            $row["registration"],
            $row["trid"],
            $note,
            $src_apid,
            $dst_apid,
            $row["alid"],
            $row["plid"]
        );

        continue;
    }

    // Filter out any carriage returns or tabs
    $note = str_replace(["\n", "\r", "\t"], "", $note);

    // Convert mi to km if units=K *and* we're not loading a single flight
    if ($units == "K" && (!$fid || $fid == "0")) {
        $row["distance"] = round($row["distance"] * KM_PER_MILE);
    }

    $rows[] = sprintf(
        "%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s",
        $src_code,
        $src_apid,
        $dst_code,
        $dst_apid,
        $row["code"],
        $row["src_date"],
        $row["distance"],
        $row["duration"],
        $row["seat"],
        $row["seat_type"],
        $row["class"],
        $row["reason"],
        $row["fid"],
        $row["name"],
        $row["registration"],
        $row["alid"],
        $note,
        $row["trid"],
        $row["plid"],
        $al_code,
        $row["src_time"],
        $row["mode"]
    );
}

if ($export == "gcmap") {
    // Output the redirect URL.
    header("Location: http://www.gcmap.com/mapui?P=" . implode(',', $rows) . "&MS=bm");
    return;
}

if (count($rows)) {
    $separator = ($export == "export" || $export == "backup")
        ? "\r\n"
        // Not for $export == "gcmap"
        : "\n";

    echo implode($separator, $rows);
}
