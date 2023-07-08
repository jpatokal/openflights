<?php

include 'helper.php';
include 'db_pdo.php';

/**
 * Trim anything after a hyphen, period or left paren
 * @param $query
 * @return string
 */
function trim_query($query) {
    $chunks = preg_split("/[-.(]/", $query);
    return trim($chunks[0]);
}

// If quick, then return only one row, with no UL tags
if ($_POST['quick']) {
    $limit = 1;
} else {
    $limit = 6;
}
// If multi, then search airports and airlines
$multi = $_POST["qs"];
$results = false;

// Autocompletion for airports
// 3 chars: match on IATA or name (major airports only)
// 4 chars: match on ICAO or name (major airports only)
// >4 chars: match on name or city

$airports = array("qs", "src_ap", "dst_ap", "src_ap1", "dst_ap1", "src_ap2", "dst_ap2", "src_ap3", "dst_ap3", "src_ap4", "dst_ap4");
foreach ($airports as $ap) {
    if ($_POST[$ap]) {
        $query = trim_query($_POST[$ap]);
        // Limit the number of rows returned in multi-input, where space is at a premium
        if ($limit > 1) {
            $idx = substr($ap, -1);
            switch ($idx) {
                case "4":
                case "3":
                case "2":
                case "1":
                    $limit = 7 - $idx;
            }
        }
        break;
    }
}

// Skip this block for two-letter strings in limited multi-mode (generic search box covering airlines & airports),
// since they're assumed to be airlines
if ($query && !($multi && $limit == 1 && strlen($query) < 3)) {
    $ext = "";
    $sort_order = "iata IS NULL,icao IS NULL,city,name";
    if (strlen($query) <= 3) {
        $ext = "iata != '' AND iata != :code AND";
    } elseif ($multi) {
        // Exclude private airports from multisearch
        $ext = "icao != '' AND";
    }
    $sql = "SELECT 2 as sort_col,apid,name,city,country,iata,icao,x,y,timezone,dst FROM airports WHERE $ext (city LIKE :name";

    switch (strlen($query)) {
        case 3: // IATA
            $sql = "SELECT 1 as sort_col,apid,name,city,country,iata,icao,x,y,timezone,dst FROM airports WHERE iata=:code UNION ($sql)) ORDER BY sort_col,$sort_order LIMIT $limit";
            break;

        case 4: // ICAO
            $sql = "SELECT 1 as sort_col,apid,name,city,country,iata,icao,x,y,timezone,dst FROM airports WHERE icao=:code UNION ($sql)) ORDER BY sort_col,$sort_order LIMIT $limit";
            break;

        default:
            if (strlen($query) > 4) {
                $sql .= " OR name LIKE :name) ORDER BY $sort_order LIMIT $limit";
            } else {
                $sql .= ") ORDER BY $sort_order LIMIT $limit";
            }
            break;
    }

    if ($limit > 1) {
        print ("<ul class='autocomplete'>");
    }
    $sth = $dbh->prepare($sql);

    // This is intentionally one-sided (foo%s) to avoid excessive substring matches.
    $sth->execute(['name' => "$query%", 'code' => $query]);
    if ($sth->rowCount() > 0) {
        $results = true;
        foreach ($sth as $row) {
            if ($limit > 1) {
                printf(
                    "<li class='autocomplete' origin='%s' id='%s'>%s</li>\n",
                    $ap,
                    format_apdata($row),
                    format_airport($row)
                );
            } else {
                printf("%s;%s", format_apdata($row), format_airport($row));
                exit; // match found, do not fall thru to airlines
            }
        }
    }
}
if (!$query || $multi) {
    // Autocompletion for airlines
    // 2 chars: match on IATA or name (major airlines only)
    // 3 chars: match on ICAO or name (major airlines only)
    // >3 chars: match on name (any airline)

    $airlines = array("qs", "airline", "airline1", "airline2", "airline3", "airline4");
    foreach ($airlines as $al) {
        if ($_POST[$al]) {
            $query = trim_query($_POST[$al]);
            // Limit(/expand) the number of rows returned in multiinput, where space is at a premium
            if ($limit != 1) {
                $idx = substr($al, -1);
                switch ($idx) {
                    case "4":
                    case "3":
                    case "2":
                    case "1":
                        $limit = 7 - $idx;
                        break;
                    default:
                        $limit = 3;
                }
            }
            break;
        }
    }
    if ($query) {
        $mode = $_POST["mode"];
        if (!$mode) {
            $mode = "F";
        }
        if (strlen($query) <= 3 && $mode == 'F') {
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
            switch (strlen($query)) {
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
        if ($limit > 1 && ! $multi) {
            print ("<ul class='autocomplete'>");
        }
        $sth = $dbh->prepare($sql);
        if (!$sth->execute(['mode' => $mode, 'code' => $query, 'name' => "$query%"])) {
            die('Autocomplete failed.');
        }
        if ($sth->rowCount() > 0) {
            $results = true;
            foreach ($sth as $row) {
                if ($limit > 1) {
                    printf("<li class='autocomplete' id='%s'>%s</li>", $row["alid"], format_airline($row));
                } else {
                    printf("%s;%s", $row["alid"], format_airline($row));
                }
            }
        }
    } elseif ($_POST['plane']) {
        // Autocompletion for plane types
        // First match against major types with IATA codes, then pad to max 6 by matching against frequency of use
        $query = $_POST['plane'];
        $name = "%$query%";
        $query = "(name LIKE :name OR iata LIKE :name) ";
        $sql = "(SELECT name,plid FROM planes WHERE " . $query . " AND iata IS NOT NULL ORDER BY name LIMIT 6) UNION " .
        "(SELECT name,plid FROM planes WHERE " . $query . " AND iata IS NULL ORDER BY frequency DESC LIMIT 6) LIMIT 6";
        $sth = $dbh->prepare($sql);
        $sth->execute(compact('name'));

        print("<ul class='autocomplete2'>");
        $MAX_LEN = 35;
        foreach ($sth as $data) {
            $results = true;
            $item = stripslashes($data['name']);
            if (strlen($item) > $MAX_LEN) {
                $item = substr($item, 0, $MAX_LEN - 13) . "..." . substr($item, -10, 10);
            }
            echo "<li class='autocomplete' id='" . $data['plid'] . "'>" . $item . "</li>";
        }
    }
}

if (!$results) {
    http_response_code(204); // No Data
}

if ($limit > 1) {
    printf("</ul>");
}
