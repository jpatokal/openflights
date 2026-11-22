<?php

/**
 * Helper functions for filter handling
 */

/**
 * Build a flight filter string for SQL SELECT
 *
 * @param $dbh PDO
 * @param $vars array
 * @return string
 */
function getFilterString($dbh, $vars) {
    $filter = "";
    $trid = $vars["trid"] ?? null;
    $alid = $vars["alid"] ?? null;
    $year = $vars["year"] ?? null;
    $fmode = $vars["fmode"] ?? null;
    $class = $vars["class"] ?? null;
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
    if ($fmode && $fmode != "0") {
        $filter .= " AND f.mode = " . $dbh->quote($fmode);
    }
    if ($class && $class != "0") {
        $filter .= " AND f.class = " . $dbh->quote($class);
    }
    if ($xvalue && $xvalue != "") {
        $xkey = $vars["xkey"] ?? null;
        switch ($xkey) {
            case null:
            case "":
                break;

            case "class":
            case "mode":
            case "reason":
                $filter .= " AND f.{$xkey} = " . $dbh->quote($xvalue);
                break;

            case "distgt":
                $filter .= " AND f.distance > " . $dbh->quote($xvalue, PDO::PARAM_INT);
                break;

            case "distlt":
                $filter .= " AND f.distance < " . $dbh->quote($xvalue, PDO::PARAM_INT);
                break;

            case "note":
            case "reg":
                $filter .= " AND f.{$xkey} LIKE " . $dbh->quote("%$xvalue%");
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
 * @param $year string Selected year, or null/"0" for all years
 * @param $alid string Selected airline, or null/"0" for all airlines
 * @param $fmode string Selected mode, or null/"0" for all modes
 * @param $class string Selected class, or null/"0" for all classes
 */
function loadFilter($dbh, $uid, $trid, $logged_in, $year = null, $alid = null, $fmode = null, $class = null) {
    // Base filter: limit to a single trip. The list queries below alias
    // flights as `f`, so keep every condition table-qualified.
    $filter = "";
    $params = [$uid];
    if ($trid && $trid != "0") {
        if ($trid == "null") {
            $filter = "AND f.trid IS NULL";
        } else {
            $filter = "AND f.trid = ?";
            $params[] = $trid;
        }
    }

    $add = function (&$f, &$p, $condition, $value, $int = false) {
        if ($value !== null && $value !== "" && $value !== "0") {
            $f .= " AND " . $condition;
            $p[] = $int ? (int) $value : $value;
        }
    };

    $addYear = function (&$f, &$p) use ($year) {
        if ($year && $year != "0") {
            $f .= " AND YEAR(f.src_date) = ?";
            $p[] = (int) $year;
        }
    };

    $mode_labels = ["F" => "Flight", "T" => "Train", "R" => "Road", "S" => "Ship"];
    $class_labels = ["Y" => "Economy", "P" => "Prem. Eco", "C" => "Business", "F" => "First"];

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

    // Airlines: constrained by year, mode, and class (not by the airline itself).
    $airlineFilter = $filter;
    $airlineParams = $params;
    $addYear($airlineFilter, $airlineParams);
    $add($airlineFilter, $airlineParams, "f.mode = ?", $fmode);
    $add($airlineFilter, $airlineParams, "f.class = ?", $class);
    $sth = $dbh->prepare(<<<SQL
        SELECT DISTINCT a.alid, iata, icao, name
        FROM airlines as a, flights as f
        WHERE f.uid = ? $airlineFilter AND a.alid = f.alid
        ORDER BY name
SQL
    );
    $sth->execute($airlineParams);
    $rows = [];
    foreach ($sth as $row) {
        $rows[] = sprintf("%s;%s", $row["alid"], $row["name"]);
    }
    echo implode("\t", $rows) . "\n";

    // Years: constrained by airline, mode, and class (not by the year itself).
    $yearFilter = $filter;
    $yearParams = $params;
    $add($yearFilter, $yearParams, "f.alid = ?", $alid, true);
    $add($yearFilter, $yearParams, "f.mode = ?", $fmode);
    $add($yearFilter, $yearParams, "f.class = ?", $class);
    $sth = $dbh->prepare(<<<SQL
        SELECT DISTINCT YEAR(f.src_date) AS year
        FROM flights AS f WHERE f.uid = ? $yearFilter AND YEAR(f.src_date) != '0'
        ORDER BY year DESC
SQL
    );
    $sth->execute($yearParams);
    $rows = [];
    foreach ($sth as $row) {
        $rows[] = sprintf("%s;%s", $row["year"], $row["year"]);
    }
    echo implode("\t", $rows) . "\n";

    // Modes: constrained by year, airline, and class.
    $modeFilter = $filter;
    $modeParams = $params;
    $addYear($modeFilter, $modeParams);
    $add($modeFilter, $modeParams, "f.alid = ?", $alid, true);
    $add($modeFilter, $modeParams, "f.class = ?", $class);
    $sth = $dbh->prepare(<<<SQL
        SELECT DISTINCT f.mode
        FROM flights AS f WHERE f.uid = ? $modeFilter AND f.mode != ''
        ORDER BY f.mode
SQL
    );
    $sth->execute($modeParams);
    $rows = [];
    foreach ($sth as $row) {
        $m = $row["mode"];
        $rows[] = sprintf("%s;%s", $m, $mode_labels[$m] ?? $m);
    }
    echo implode("\t", $rows) . "\n";

    // Classes: constrained by year, airline, and mode.
    $classFilter = $filter;
    $classParams = $params;
    $addYear($classFilter, $classParams);
    $add($classFilter, $classParams, "f.alid = ?", $alid, true);
    $add($classFilter, $classParams, "f.mode = ?", $fmode);
    $sth = $dbh->prepare(<<<SQL
        SELECT DISTINCT f.class
        FROM flights AS f WHERE f.uid = ? $classFilter AND f.class != ''
        ORDER BY f.class
SQL
    );
    $sth->execute($classParams);
    $rows = [];
    foreach ($sth as $row) {
        $c = $row["class"];
        $rows[] = sprintf("%s;%s", $c, $class_labels[$c] ?? $c);
    }
    echo implode("\t", $rows);
}
