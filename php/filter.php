<?php

//
// Helper functions for filter handling
//

// Build a flight filter string for SQL SELECT
function getFilterString($dbh, $vars) {
    $filter = "";
    $trid = $vars["trid"] ?? null;
    $alid = $vars["alid"] ?? null;
    $year = $vars["year"] ?? null;
    $xkey = $vars["xkey"] ?? null;
    $xvalue = $vars["xvalue"] ?? null;

    if ($trid && $trid != "0") {
        if ($trid == "null") {
            $filter .= " AND f.trid IS NULL";
        } else {
            $filter .= " AND f.trid = " . $dbh->quote($trid, PDO::PARAM_INT);
        }
    }
    if ($alid && $alid != "0") {
        $filter .= " AND f.alid = " . $dbh->quote($alid, PDO::PARAM_INT);
    }
    if ($year && $year != "0") {
        $filter .= " AND YEAR(f.src_date)=" . $dbh->quote($year, PDO::PARAM_INT);
    }
    if ($xvalue && $xvalue != "") {
        switch ($xkey) {
            case null:
            case "":
                break;

            case "class":
                $filter .= " AND f.class = " . $dbh->quote($xvalue);
                break;

            case "distgt":
                $filter .= " AND f.distance > " . $dbh->quote($xvalue, PDO::PARAM_INT);
                break;

            case "distlt":
                $filter .= " AND f.distance < " . $dbh->quote($xvalue, PDO::PARAM_INT);
                break;

            case "mode":
                $filter .= " AND f.mode = " . $dbh->quote($xvalue);
                break;

            case "note":
                $filter .= " AND f.note LIKE " . $dbh->quote("%$xvalue%");
                break;

            case "reason":
                $filter .= " AND f.reason = " . $dbh->quote($xvalue);
                break;

            case "reg":
                $filter .= " AND f.registration LIKE " . $dbh->quote("$xvalue%");
                break;
        }
    }
    return $filter;
}

/**
 * Load up possible filter settings for this user
 *
 * @param $dbh PDO OpenFlights DB handler
 * @param $uid string User ID
 * @param $trid string Trip ID
 * @param $logged_in string Username if signed in
 */
function loadFilter($dbh, $uid, $trid, $logged_in) {
    // Limit selections to a single trip?
    $filter = "";
    $params = [$uid];
    if ($trid && $trid != "0") {
        if ($trid == "null") {
            $filter = "AND trid IS NULL";
        } else {
            $filter = "AND trid = ?";
            $params[] = $trid;
        }
    }

    // List of all trips
    $privacy = ($logged_in == "demo")
        ? "AND public != 'N'" // filter out private trips
        : "";

    $sth = $dbh->prepare("SELECT * FROM trips WHERE uid = ? $privacy ORDER BY name");
    $sth->execute([$uid]);
    $rows = [];
    foreach ($sth as $row) {
        $rows[] = sprintf("%s;%s;%s", $row["trid"], $row["name"], $row["url"]);
    }
    echo implode("\t", $rows) . "\n";

    // List of all airlines
    $sth = $dbh->prepare(<<<SQL
        SELECT DISTINCT a.alid, iata, icao, name
        FROM airlines as a, flights as f
        WHERE f.uid = ? $filter AND a.alid = f.alid
        ORDER BY name
SQL
    );
    $sth->execute($params);
    $rows = [];
    foreach ($sth as $row) {
        $rows[] = sprintf("%s;%s", $row["alid"], $row["name"]);
    }
    echo implode("\t", $rows) . "\n";

    // List of all years
    $sth = $dbh->prepare(<<<SQL
        SELECT DISTINCT YEAR(src_date) AS year
        FROM flights WHERE uid = ? $filter AND YEAR(src_date) != '0'
        ORDER BY year DESC
SQL
    );
    $sth->execute($params);
    $rows = [];
    foreach ($sth as $row) {
        $rows[] = sprintf("%s;%s", $row["year"], $row["year"]);
    }
    echo implode("\t", $rows);
}
