<?php

include_once 'helper.php';
include_once 'db_pdo.php';

/**
 * Trim anything after a hyphen, period or left paren
 * @param $query
 * @return string
 */
function trim_query($query) {
    $chunks = preg_split("/[-.(]/", $query);
    return trim($chunks[0]);
}

const SEARCH_TYPES = array("airport", "airline", "plane", "multisearch");

if (!in_array($_POST['searchType'] ?? null, SEARCH_TYPES)
    || empty($_POST['searchText'] ?? "")) {
    http_response_code(400);
    exit;
}

$searchType = $_POST['searchType'];
$searchText = trim_query($_POST['searchText']);

if (empty($searchText)) {
    exit;
}

// If multi, then search airports and airlines
$multi = $searchType == 'multisearch';

// If quick, then return only one row, with no UL tags
$quick = ($_POST['quick'] ?? false) == 'true';
$limit = $quick ? 1 : 6;

$results = [];


// Skip this block for two-letter strings in limited multi-mode
// (generic search box covering airlines & airports),
// since they're assumed to be airlines.
if ($searchType == 'airport' || ($multi && strlen($searchText) != 2)) {
    // Autocompletion for airports
    // 3 chars: match on IATA or name (major airports only)
    // 4 chars: match on ICAO or name (major airports only)
    // >4 chars: match on name or city

    $ext = "";
    $sort_order = "iata IS NULL,icao IS NULL,city,name";
    if (strlen($searchText) <= 3) {
        $ext = "iata != '' AND iata != :code AND";
    } elseif ($multi) {
        // Exclude private airports from multisearch
        $ext = "icao != '' AND";
    }
    $sql = "SELECT 2 as sort_col,apid,name,city,country,iata,icao,x,y,timezone,dst FROM airports WHERE $ext (city LIKE :name";

    switch (strlen($searchText)) {
        case 3: // IATA
            $sql = "SELECT 1 as sort_col,apid,name,city,country,iata,icao,x,y,timezone,dst FROM airports WHERE iata=:code UNION ($sql)) ORDER BY sort_col,$sort_order LIMIT $limit";
            break;

        case 4: // ICAO
            $sql = "SELECT 1 as sort_col,apid,name,city,country,iata,icao,x,y,timezone,dst FROM airports WHERE icao=:code UNION ($sql)) ORDER BY sort_col,$sort_order LIMIT $limit";
            break;

        default:
            if (strlen($searchText) > 4) {
                $sql .= " OR name LIKE :name) ORDER BY $sort_order LIMIT $limit";
            } else {
                $sql .= ") ORDER BY $sort_order LIMIT $limit";
            }
            break;
    }

    $sth = $dbh->prepare($sql);

    // This is intentionally one-sided (foo%s) to avoid excessive substring matches.
    $sth->execute(['name' => "$searchText%", 'code' => $searchText]);
    foreach ($sth as $row) {
        $results[] = ['value' => format_apdata($row), 'label' => format_airport($row)];

        if ($quick) {
            // match found, don't search anything else
            $searchType = null;
            $multi = null;
            break;
        }
    }
}
if ($searchType == 'airline' || $multi) {
    // Autocompletion for airlines
    // 2 chars: match on IATA or name (major airlines only)
    // 3 chars: match on ICAO or name (major airlines only)
    // >3 chars: match on name (any airline)

    $mode = $_POST["mode"] ?? "F";
    if (strlen($searchText) <= 3 && $mode == 'F') {
        $ext = "iata != '' AND icao != :code AND";
    } else {
        $ext = ""; // anything goes!
    }
    if ($multi) {
        $ext = "iata!='' AND active='Y' AND"; // quick search only for active, IATA-coded airlines
    }
    $sql = "SELECT 2 as sort_col,alid,name,iata,icao,mode FROM airlines WHERE mode=:mode AND $ext (name LIKE :name OR alias LIKE :name)";

    // IATA/ICAO only apply to flights
    if ($mode == 'F') {
        switch (strlen($searchText)) {
            case 2: // IATA
                $sql = "SELECT 1 as sort_col,alid,name,iata,icao,mode FROM airlines WHERE iata=:code AND active='Y' UNION ($sql) ORDER BY sort_col, name LIMIT $limit";
                break;

            case 3: // ICAO
                if (!$multi) {
                    $sql = "SELECT 1 as sort_col,alid,name,iata,icao,mode FROM airlines WHERE icao=:code UNION ($sql) ORDER BY sort_col, name LIMIT $limit";
                    break;
                } // else fallthru

            default: // sort non-IATA airlines last
                $sql .= " ORDER BY LENGTH(iata) DESC, name LIMIT $limit";
                break;
        }
    } else {
        $sql .= " ORDER BY name LIMIT $limit";
    }
    $sth = $dbh->prepare($sql);
    !$sth->execute(['mode' => $mode, 'code' => $searchText, 'name' => "$searchText%"]);
    foreach ($sth as $row) {
        $results[] = ['label' => format_airline($row), 'value' => (int)$row['alid']];
    }
}

if ($searchType == 'plane') {
    // Autocompletion for plane types
    // First match against major types with IATA codes,
    // then pad to max 6 by matching against frequency of use.
    $name = "%$searchText%";
    $filter = "(name LIKE :name OR iata LIKE :name) ";
    $sql = <<<SQL
(SELECT name,plid FROM planes WHERE $filter AND iata IS NOT NULL ORDER BY name LIMIT 6)
UNION
(SELECT name,plid FROM planes WHERE $filter AND iata IS NULL ORDER BY frequency DESC LIMIT 6)
LIMIT 6;
SQL;
    $sth = $dbh->prepare($sql);
    $sth->execute(compact('name'));

    $MAX_LEN = 35;
    foreach ($sth as $data) {
        $name = $data['name'];
        if (strlen($name) > $MAX_LEN) {
            $name = substr($name, 0, $MAX_LEN - 13) . "..." . substr($name, -10, 10);
        }
        $results[] = ['label' => $name, 'value' => (int)$data['plid']];
    }
}

header("Content-type: application/json; charset=utf-8");
echo json_encode($results);
