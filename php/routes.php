<?php

include_once 'locale.php';
include_once 'db_pdo.php';
include_once 'helper.php';
include_once 'filter.php';

/**
 * Emit the route-map JSON object shared with map.php.
 */
function respond_routes($stats, $flights, $airports, $airlines, $airlines_all) {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode([
        "stats" => $stats,
        "flights" => $flights,
        "airports" => $airports,
        "trips" => [],
        "airlines" => $airlines,
        "airlines_all" => $airlines_all,
        "years" => [],
        "modes" => [],
        "classes" => [],
    ]);
}

$apid = $_POST["apid"] ?? $_GET["apid"] ?? null;
$alid = $_POST["alid"] ?? $_GET["alid"] ?? null;

if (!$apid) {
    $param = $_POST["param"];
    if (!$param) {
        die(_('Error') . ';' . _('Airport or airline ID is mandatory'));
    }

    switch (strlen($param)) {
        case 2:
            $sql = "SELECT CONCAT('L', alid) AS apid FROM airlines WHERE iata = ?";
            break;
        case 3:
            $sql = "SELECT apid FROM airports WHERE iata = ?";
            break;
        case 4:
            $sql = "SELECT apid FROM airports WHERE icao = ?";
            break;
        default:
            die(_('Error') . ';' .
                sprintf(
                    _("Query '%s' not understood. For airlines, please enter a 2-letter IATA code. For airports, please enter a 3-letter IATA or 4-letter ICAO code."),
                    htmlspecialchars($param)
                ));
    }
    $sth = $dbh->prepare($sql);
    $sth->execute([$param]);
    $row = $sth->fetch();
    if (!$row) {
        die(_('Error') . ';' . sprintf(_("No match found for query '%s'"), htmlspecialchars($param)));
    }

    $apid = $row["apid"];
}

// $apid contains either mapped airport ID (no prefix) or the mapped airline ID (L + alid)
// $alid, if given, is an additional filter to airports only

if (substr($apid, 0, 1) == "L") {
    $type = "L";
    $apid = substr($apid, 1);
    $condParams = [$apid];
    $condition = "r.alid = ?";
    if (!$alid) {
        $condition .= " AND r.codeshare = ''"; // exclude codeshares by default
    }
    $codeshare = "codeshare";
} else {
    $type = "A";
    $condParams = [$apid];
    $condition = "r.src_apid = ?";
    if ($alid) {
        $condParams[] = $alid;
        $condition .= " AND r.alid = ?";
        $codeshare = "codeshare";
    } else {
        $codeshare = "'N'"; // never show dotted lines for airport route maps
    }
}

$stats = [];
$flights = [];
$airports = [];
$airlines = [];
$airlines_all = true;

// Title for this airport route data plus count of routes
// (count = 0 when airport exists but has no routes)
if ($type == "A") {
    if ($alid) {
        $params = [$alid, $apid];
        $filter = " AND r.alid = ?";
    } else {
        $params = [$apid];
        $filter = "";
    }

    $sql = "
    SELECT COUNT(src_apid) AS count, apid, x, y, name, iata, icao, city, country, timezone, dst
    FROM airports AS a
    LEFT OUTER JOIN routes AS r ON r.src_apid = a.apid $filter
    WHERE a.apid = ?
    GROUP BY src_apid, apid
 ";
    $sth = $dbh->prepare($sql);
    $sth->execute($params);
    $row = $sth->fetch();
    if (!$row) {
        die(_('Error') . ';' . sprintf(_("Error;No airport with ID '%s' found"), htmlspecialchars($apid)));
    }

    $stats = [
        "apid" => (string) $apid,
        "count" => (int) $row["count"],
        "description" => sprintf(
            "%s (<b>%s</b>)<br><small>%s, %s<br>%s routes</small>",
            $row["name"],
            format_apcode($row),
            $row["city"],
            $row["country"],
            $row["count"]
        ),
    ];
    if ($row["count"] == 0) {
        // No routes, print this airport and abort
        $airports[] = format_airport_data($row) + [
            "name" => $row["name"],
            "city" => $row["city"],
            "country" => $row["country"],
            "count" => 0,
            "formatted" => format_airport($row),
            "future" => "N",
        ];
        respond_routes($stats, $flights, $airports, $airlines, $airlines_all);
        exit;
    }
} else {
    if ($alid) {
        $filter = "";
    } else {
        $filter = " AND r.codeshare = ''"; // by default, don't display codeshares
    }

    // Airline route map
    $sql = "SELECT COUNT(r.alid) AS count, country, name, iata, icao 
            FROM airlines AS l
            LEFT OUTER JOIN routes AS r ON r.alid = l.alid $filter
            WHERE l.alid = ? GROUP BY r.alid";
    $sth = $dbh->prepare($sql);
    $sth->execute([$apid]);
    $row = $sth->fetch();
    if (!$row) {
        die(_('Error') . ';' . sprintf(_("Error;No airline with ID '%s' found"), htmlspecialchars($apid)));
    }

    $stats = [
        "apid" => "L" . $apid,
        "count" => (int) $row["count"],
        "description" => sprintf(
            "%s (<b>%s</b>)<br><small>%s</small><br>%s routes",
            $row["name"],
            $row["iata"],
            $row["country"],
            $row["count"]
        ),
    ];
    if ($row["count"] == 0) {
        // No routes, abort
        respond_routes($stats, $flights, $airports, $airlines, $airlines_all);
        exit;
    }
    $alname = $row["iata"];
}

// List of all flights FROM this airport
$sql = "
  SELECT DISTINCT s.apid, s.x, s.y, d.apid, d.x, d.y, count(rid), 0, $codeshare AS future, 'F' AS mode
  FROM routes AS r, airports AS s, airports AS d
  WHERE $condition AND r.src_apid = s.apid AND r.dst_apid = d.apid
  GROUP BY s.apid, d.apid, codeshare
";
$sth = $dbh->prepare($sql);
if (!$sth->execute($condParams)) {
    die(_('Error') . ';' . _('Database error.'));
}
foreach ($sth->fetchAll(PDO::FETCH_NUM) as $row) {
    $row[7] = gcPointDistance(
        ["x" => $row[1], "y" => $row[2]],
        ["x" => $row[4], "y" => $row[5]]
    );
    $flights[] = [
        "src_apid" => (int) $row[0],
        "src_lon" => (float) $row[1],
        "src_lat" => (float) $row[2],
        "dst_apid" => (int) $row[3],
        "dst_lon" => (float) $row[4],
        "dst_lat" => (float) $row[5],
        "count" => (int) $row[6],
        "distance" => (int) $row[7],
        "future" => (string) $row[8],
        "mode" => (string) $row[9],
    ];
}

// List of all airports with flights FROM this airport
if ($type == "A") {
    $apcond = "(r.src_apid = a.apid OR r.dst_apid = a.apid)"; // include source airport
} else {
    $apcond = "r.src_apid = a.apid"; // prevent double-counting
}

// MIN(codeshare) returns '' as long as at least one route is not 'Y'!
$sql = "
  SELECT DISTINCT a.apid, x, y, name, iata, icao, city, country, timezone, dst, count(name) AS visits, MIN(codeshare) AS future
  FROM routes AS r, airports AS a
  WHERE $condition AND $apcond
  GROUP BY a.apid, name
  ORDER BY visits ASC
";
$sth = $dbh->prepare($sql);
if (!$sth->execute($condParams)) {
    die(_('Error') . ';' . _('Database error.'));
}
foreach ($sth as $row) {
    $airports[] = format_airport_data($row) + [
        "name" => $row["name"],
        "city" => $row["city"],
        "country" => $row["country"],
        "count" => (int) $row["visits"],
        "formatted" => format_airport($row),
        "future" => $row["future"],
    ];
}

// List of all airlines in this route map
if ($type == "L") {
    // Special handling here: no "all" option, alid = 0 means exclude codeshares, alid != 0 means codeshares also
    $airlines[] = ["alid" => "0", "name" => $alname . _("-operated")];
    $airlines[] = [
        "alid" => htmlspecialchars($apid) . "C",
        "name" => $alname . _(" and codeshares"),
    ];
    $airlines_all = false;
} else {
    // Note: Existing airline filter is purposely ignored here
    $sql = "SELECT DISTINCT a.alid, iata, icao, name FROM airlines as a, routes as r
                 WHERE r.src_apid = ? AND a.alid = r.alid
                 ORDER BY a.alid, name;";
    $sth = $dbh->prepare($sql);
    if (!$sth->execute([$apid])) {
        die(_('Error') . ';' . _('Database error.'));
    }
    foreach ($sth as $row) {
        $airlines[] = ["alid" => (int) $row["alid"], "name" => $row["name"]];
    }
}

respond_routes($stats, $flights, $airports, $airlines, $airlines_all);
