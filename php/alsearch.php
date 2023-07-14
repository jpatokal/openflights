<?php

require_once "../php/locale.php";
require_once "../php/db_pdo.php";

include_once 'helper.php';

$name = $_POST["name"];
$alias = $_POST["alias"];
$mode = $_POST["mode"];
if (!$mode || $mode == 'F') {
    $iata = $_POST["iata"];
    $icao = $_POST["icao"];
    $callsign = $_POST["callsign"];
    $mode = "F";
} else {
    $iata = "";
    $icao = "";
    $callsign = "";
}
$country = $_POST["country"];
$offset = $_POST["offset"];
$active = $_POST["active"];
$iatafilter = $_POST["iatafilter"];
$action = $_POST["action"];
$alid = $_POST["alid"];

$uid = $_SESSION["uid"] ?? null;
if ($action == "RECORD") {
    if (!$uid || empty($uid)) {
        printf("0;" . _("Your session has timed out, please log in again."));
        exit;
    }

    // Check for duplicates
    $sql = "SELECT * FROM airlines WHERE mode = ? AND (name LIKE ? OR alias LIKE ?)";
    $params = [$mode, $name, $name];

    // Editing an existing entry, so make sure we're not overwriting something else
    if ($alid && $alid != "") {
        $sql .= " AND alid != ?";
        $params[] = $alid;
    }

    $sth = $dbh->prepare($sql);
    if (!$sth->execute($params)) {
        die('0;Duplicate check failed.');
    }
    $row = $sth->fetch();
    if ($row) {
        printf("0;" . "A " . MODES_OPERATOR[$mode] . " using the name or alias " . $name . " exists already.");
        exit;
    }

    if ($alias != "") {
        $sql = "SELECT * FROM airlines WHERE mode = ? AND (name LIKE ? OR alias LIKE ?)";
        $params = [$mode, $name, $alias];

        // Editing an existing entry, so make sure we're not overwriting something else
        if ($alid && $alid != "") {
            $sql .= " AND alid != ?";
            $params[] = $alid;
        }

        $sth = $dbh->prepare($sql);
        if (!$sth->execute($params)) {
            die('0;Duplicate check failed.');
        }
        $row = $sth->fetch();
        if ($row) {
            printf("0;" . "A " . MODES_OPERATOR[$mode] . " using the name or alias " . $alias . " exists already.");
            exit;
        }
    }

    // IATA duplicates allowed only for non-active airlines
    if ($iata != "") {
        $sql = "SELECT * FROM airlines WHERE iata = ? AND active = 'Y'";
        $params = [$iata];

        // Editing an existing entry, so make sure we're not overwriting something else
        if ($alid && $alid != "") {
            $sql .= " AND alid != ?";
            $params[] = $alid;
        }

        $sth = $dbh->prepare($sql);
        if (!$sth->execute($params)) {
            die('0;Duplicate check failed.');
        }
        $row = $sth->fetch();
        if ($row) {
            printf("0;An airline using the IATA code " . $iata . " exists already.");
            exit;
        }
    }

    // ICAO duplicates are not
    if ($icao != "") {
        $sql = "SELECT * FROM airlines WHERE icao = ?";
        $params = [$icao];

        // Editing an existing entry, so make sure we're not overwriting something else
        if ($alid && $alid != "") {
            $sql .= " AND alid != ?";
            $params[] = $alid;
        }

        $sth = $dbh->prepare($sql);
        if (!$sth->execute($params)) {
            die('0;Duplicate check failed.');
        }
        $row = $sth->fetch();
        if ($row) {
            printf("0;An airline using the ICAO code " . $icao . " exists already.");
            exit;
        }
    }

    if (!$alid || $alid == "") {
        // Adding new airline
        $sql = <<<SQL
            INSERT INTO airlines(name, alias, country, iata, icao, callsign, mode, active, uid)
            VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)
SQL;
        $params = [
            $name,
            $alias,
            $country,
            $iata == "" ? null : $iata,
            $icao == "" ? null : $icao,
            $callsign,
            $mode,
            $active,
            $uid
        ];
    } else {
        // Editing an existing airline
        $sql = <<<SQL
            UPDATE airlines
            SET name = ?, alias = ?, country = ?, iata = ?, icao = ?, callsign = ?, mode = ?, active = ?
            WHERE alid = ? AND (uid = ? OR ? IN (?))
SQL;
        $params = [
            $name,
            $alias,
            $country,
            $iata == "" ? null : $iata,
            $icao == "" ? null : $icao,
            $callsign,
            $mode,
            $active,
            $alid,
            $uid,
            $uid,
            implode(', ', (array)$OF_ADMIN_UID)
        ];
    }

    $sth = $dbh->prepare($sql);
    if (!$sth->execute($params)) {
        die('0;Adding new ' . MODES_OPERATOR[$mode] . ' failed.');
    }
    if (!$alid || $alid == "") {
        printf('1;' . $dbh->lastInsertId() . ';New ' . MODES_OPERATOR[$mode] . ' successfully added.');
    } elseif ($sth->rowCount() === 1) {
        printf('1;' . $alid . ';' . _("Airline successfully edited."));
    } else {
        printf('0;' . _("Editing airline failed:") . ' ' . $sql);
    }
    exit;
}

$filters = [];
$filterParams = [];

// Build filter
if ($name) {
    $filters[] = "(name LIKE ? OR alias LIKE ?)";
    $filterParams[] = $name . '%';
    $filterParams[] = $name . '%';
}
if ($alias) {
    $filters[] = "(name LIKE ? OR alias LIKE ?)";
    $filterParams[] = $alias . '%';
    $filterParams[] = $alias . '%';
}
if ($callsign) {
    $filters[] = "callsign LIKE ?";
    $filterParams[] = $callsign . '%';
}

if ($iata) {
    $filters[] = "iata = ?";
    $filterParams[] = $iata;
}
if ($icao) {
    $filters[] = "icao = ?";
    $filterParams[] = $icao;
}
if ($country != "ALL" && $country) {
    $filters[] = "country = ?";
    $filterParams[] = $country;
}
if ($mode) {
    $filters[] = "mode = ?";
    $filterParams[] = $mode;
}
if ($active != "") {
    $filters[] = "active = ?";
    $filterParams[] = $active;
}

if ($mode == "F" && $iatafilter != "false") {
    $filters[] = "iata NOT IN ('', 'N/A')";
}
if (!$offset) {
    $offset = 0;
}

$sql = "SELECT * FROM airlines WHERE "  . implode(" AND ", $filters) . " ORDER BY name";

$sth = $dbh->prepare($sql . " LIMIT 10 OFFSET " . $offset);
if (!$sth->execute($filterParams)) {
    die("0;Operation $action failed.");
}
$sth2 = $dbh->prepare(str_replace("*", "COUNT(*)", $sql));
$sth2->execute($filterParams);
$row = $sth2->fetch();
if ($row) {
    $max = $row[0];
}
printf("%s;%s", $offset, $max);

$isAdmin = in_array($uid, (array)$OF_ADMIN_UID);

foreach ($sth->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if ($row["uid"] || $isAdmin) {
        if ($row["uid"] == $uid || $isAdmin) {
            $row["al_uid"] = "own"; // editable
        } else {
            $row["al_uid"] = "user"; // added by another user
        }
    } else {
        $row["al_uid"] = null; // in DB
    }
    unset($row["uid"]);
    $row["al_name"] = format_airline($row);
    print "\n" . json_encode($row);
}
