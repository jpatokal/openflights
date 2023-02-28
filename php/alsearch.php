<?php
require_once("../php/locale.php");
require_once("../php/db_pdo.php");

include 'helper.php';

$name = $_POST["name"];
$alias = $_POST["alias"];
$mode = $_POST["mode"];
if(! $mode || $mode == 'F') {
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
if($action == "RECORD") {
  if(!$uid or empty($uid)) {
    printf("0;" . _("Your session has timed out, please log in again."));
    exit;
  }

  // Check for duplicates
  $sql = "SELECT * FROM airlines WHERE mode=? AND (name LIKE ? OR alias LIKE ?)";
  $params = [$mode, $name, $name];

  // Editing an existing entry, so make sure we're not overwriting something else
  if($alid && $alid != "") {
    $sql .= " AND alid != ?";
    $params[] = $alid;
  }

  $sth = $dbh->prepare($sql);
  $sth->execute($params) or die('0;Duplicate check failed.');
  if($row = $sth->fetch()) {
    printf("0;" ."A " . $modeOperators[$mode] . " using the name or alias " . $name . " exists already.");
    exit;
  }

  if($alias != "") {
    $sql = "SELECT * FROM airlines WHERE mode=? AND (name LIKE ? OR alias LIKE ?)";
    $params = [$mode, $name, $alias];

    // Editing an existing entry, so make sure we're not overwriting something else
    if($alid && $alid != "") {
      $sql .= " AND alid != ?";
      $params[] = $alid;
    }

    $sth = $dbh->prepare($sql);
    $sth->execute($params) or die('0;Duplicate check failed.');
    if($row = $sth->fetch()) {
      printf("0;"."A " . $modeOperators[$mode] . " using the name or alias " . $alias . " exists already.");
      exit;
    }
  }

  // IATA duplicates allowed only for non-active airlines
  if($iata != "") {
    $sql = "SELECT * FROM airlines WHERE iata=? AND active='Y'";
    $params = [$iata];

    // Editing an existing entry, so make sure we're not overwriting something else
    if($alid && $alid != "") {
      $sql .= " AND alid != ?";
      $params[] = $alid;
    }

    $sth = $dbh->prepare($sql);
    $sth->execute($params) or die('0;Duplicate check failed.');
    if($row = $sth->fetch()) {
      printf("0;An airline using the IATA code " . $iata . " exists already.");
      exit;
    }
  }

  // ICAO duplicates are not
  if($icao != "") {
    $sql = "SELECT * FROM airlines WHERE icao=?";
    $params = [$icao];

    // Editing an existing entry, so make sure we're not overwriting something else
    if($alid && $alid != "") {
      $sql .= " AND alid != ?";
      $params[] = $alid;
    }

    $sth = $dbh->prepare($sql);
    $sth->execute($params) or die('0;Duplicate check failed.');
    if($row = $sth->fetch()) {
      printf("0;An airline using the ICAO code " . $icao . " exists already.");
      exit;
    }
  }

  if(! $alid || $alid == "") {
    // Adding new airline
    $sql = "INSERT INTO airlines(name,alias,country,iata,icao,callsign,mode,active,uid) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $params = [
      $name, $alias, $country,
      $iata == "" ? null : $iata,
      $icao == "" ? null : $icao,
      $callsign, $mode, $active, $uid
    ];
  } else {
    // Editing an existing airline
    $sql = "UPDATE airlines SET name=?, alias=?, country=?, iata=?, icao=?, callsign=?, mode=?, active=? WHERE alid=? AND (uid=? OR ?=?)";
    $params = [
      $name, $alias, $country,
      $iata == "" ? null : $iata,
      $icao == "" ? null : $icao,
      $callsign, $mode, $active, $alid,
      $uid, $uid, $OF_ADMIN_UID
    ];
  }

  $sth = $dbh->prepare($sql);
  $sth->execute($params) or die('0;Adding new ' . $modeOperators[$mode] . ' failed.');
  if(! $alid || $alid == "") {
    printf('1;' . $dbh->lastInsertId() . ';New ' . $modeOperators[$mode] . ' successfully added.');
  } else {
    if($sth->rowCount() == 1) {
      printf('1;' . $apid . ';' . _("Airline successfully edited."));
    } else {
      printf('0;' . _("Editing airline failed:") . ' ' . $sql);
    }
  }
  exit;
}

$sql = "SELECT * FROM airlines WHERE ";
$params = [];

// Build filter
if($name) {
  $sql .= " (name LIKE ? OR alias LIKE ?) AND";
  $params[] = $name . '%';
  $params[] = $name . '%';
}
if($alias) {
  $sql .= " (name LIKE ? OR alias LIKE ?) AND";
  $params[] = $alias . '%';
  $params[] = $alias . '%';
}
if($callsign) {
  $sql .= " callsign LIKE ? AND";
  $params[] = $callsign . '%';
}

if($iata) {
  $sql .= " iata=? AND";
  $params[] = $iata;
}
if($icao) {
  $sql .= " icao=? AND";
  $params[] = $icao;
}
if($country != "ALL") {
  if($country) {
    $sql .= " country=? AND";
    $params[] = $country;
  }
}
if($mode) {
  $sql .= " mode=? AND";
  $params[] = $mode;
}
if($active != "") {
  $sql .= " active=? AND";
  $params[] = $active;
}

if($mode != "F" || $iatafilter == "false") {
  $sql .= " 1=1"; // dummy
 } else {
  $sql .= " iata != '' AND iata != 'N/A'";
}
if(! $offset) {
  $offset = 0;
}
$sql .= " ORDER BY name";

$sth = $dbh->prepare($sql . " LIMIT 10 OFFSET " . $offset);
$sth->execute($params) or die ('0;Operation ' . $param . ' failed.');
$sth2 = $dbh->prepare(str_replace("*", "COUNT(*)", $sql));
$sth2->execute($params);
if($row = $sth2->fetch()) {
  $max = $row[0];
}
printf("%s;%s", $offset, $max);

while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
  if($row["uid"] || $uid == $OF_ADMIN_UID ) {
    if($row["uid"] == $uid || $uid == $OF_ADMIN_UID) {
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
