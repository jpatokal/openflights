<?php
require_once("../php/locale.php");
require_once("../php/db.php");

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

$uid = $_SESSION["uid"];
if($action == "RECORD") {
  if(!$uid or empty($uid)) {
    printf("0;" . _("Your session has timed out, please log in again."));
    exit;
  }

  // Check for duplicates
  $sql = "SELECT * FROM airlines WHERE mode='" . mysql_real_escape_string($mode) . "' AND (name LIKE '" . mysql_real_escape_string($name) . "' OR alias LIKE '" . mysql_real_escape_string($name) . "')";
  // Editing an existing entry, so make sure we're not overwriting something else
  if($alid && $alid != "") {
    $sql .= " AND alid != " . mysql_real_escape_string($alid);
  }
  $result = mysql_query($sql, $db) or die('0;Duplicate check failed ' . $sql);
  if($row = mysql_fetch_array($result, MYSQL_NUM)) {
    printf("0;" ."A " . $modeOperators[$mode] . " using the name or alias " . $name . " exists already.");
    exit;
  }

  if($alias != "") {
    $sql = "SELECT * FROM airlines WHERE mode='" . mysql_real_escape_string($mode) . "' AND (name LIKE '" . mysql_real_escape_string($alias) . "' OR alias LIKE '" . mysql_real_escape_string($alias) . "')";
    // Editing an existing entry, so make sure we're not overwriting something else
    if($alid && $alid != "") {
      $sql .= " AND alid != " . mysql_real_escape_string($alid);
    }
    $result = mysql_query($sql, $db) or die('0;Duplicate check failed ' . $sql);
    if($row = mysql_fetch_array($result, MYSQL_NUM)) {
      printf("0;"."A " . $modeOperators[$mode] . " using the name or alias " . $alias . " exists already.");
      exit;
    }
  }

  // IATA duplicates allowed only for non-active airlines
  if($iata != "") {
    $sql = "SELECT * FROM airlines WHERE iata='" . mysql_real_escape_string($iata) . "' AND active='Y'";
    // Editing an existing entry, so make sure we're not overwriting something else
    if($alid && $alid != "") {
      $sql .= " AND alid != " . mysql_real_escape_string($alid);
    }
    $result = mysql_query($sql, $db) or die('0;Duplicate check failed ' . $sql);
    if($row = mysql_fetch_array($result, MYSQL_NUM)) {
      printf("0;An airline using the IATA code " . $iata . " exists already.");
      exit;
    }
  }

  // ICAO duplicates are not
  if($icao != "") {
    $sql = "SELECT * FROM airlines WHERE icao='" . mysql_real_escape_string($icao) . "'";
    // Editing an existing entry, so make sure we're not overwriting something else
    if($alid && $alid != "") {
      $sql .= " AND alid != " . mysql_real_escape_string($alid);
    }
    $result = mysql_query($sql, $db) or die('0;Duplicate check failed ' . $sql);
    if($row = mysql_fetch_array($result, MYSQL_NUM)) {
      printf("0;An airline using the ICAO code " . $icao . " exists already.");
      exit;
    }
  }

  if(! $alid || $alid == "") {    
    // Adding new airline
    $sql = sprintf("INSERT INTO airlines(name,alias,country,iata,icao,callsign,mode,active,uid) VALUES('%s', '%s', '%s', '%s', %s, '%s', '%s', '%s', %s)",
		   mysql_real_escape_string($name), 
		   mysql_real_escape_string($alias),
		   mysql_real_escape_string($country),
		   mysql_real_escape_string($iata),
		   $icao == "" ? "NULL" : "'" . mysql_real_escape_string($icao) . "'",
		   mysql_real_escape_string($callsign),
		   mysql_real_escape_string($mode),
		   $active,
		   $uid);
  } else {
    // Editing an existing airline
    $sql = sprintf("UPDATE airlines SET name='%s', alias='%s', country='%s', iata='%s', icao=%s, callsign='%s', mode='%s', active='%s' WHERE alid=%s AND (uid=%s OR %s=%s)",
		   mysql_real_escape_string($name), 
		   mysql_real_escape_string($alias),
		   mysql_real_escape_string($country),
		   mysql_real_escape_string($iata),
		   $icao == "" ? "NULL" : "'" . mysql_real_escape_string($icao) . "'",
		   mysql_real_escape_string($callsign),
		   mysql_real_escape_string($mode),
		   mysql_real_escape_string($active),
		   $alid,
		   $uid,
		   $uid,
		   $OF_ADMIN_UID);
  }
  mysql_query($sql, $db) or die('0;Adding new ' . $modeOperators[$mode] . ' failed' . $sql);
  if(! $alid || $alid == "") {
    printf('1;' . mysql_insert_id() . ';New ' . $modeOperators[$mode] . ' successfully added.');
  } else {
    if(mysql_affected_rows() == 1) {
      printf('1;' . $apid . ';' . _("Airline successfully edited."));
    } else {
      printf('0;' . _("Editing airline failed:") . ' ' . $sql);
    }
  }
  exit;
}

$sql = "SELECT * FROM airlines WHERE ";

// Build filter
if($name) {
  $sql .= " (name LIKE '" . mysql_real_escape_string($name) . "%' OR alias LIKE '" . mysql_real_escape_string($name) . "%') AND";
}
if($alias) {
  $sql .= " (name LIKE '" . mysql_real_escape_string($alias) . "%' OR alias LIKE '" . mysql_real_escape_string($alias) . "%') AND";
}
if($callsign) {
  $sql .= " callsign LIKE '" . mysql_real_escape_string($callsign) . "%' AND";
}

if($iata) {
  $sql .= " iata='" . mysql_real_escape_string($iata) . "' AND";
}
if($icao) {
  $sql .= " icao='" . mysql_real_escape_string($icao) . "' AND";
}
if($country != "ALL") {
  if($country) {
    $sql .= " country='" . mysql_real_escape_string($country) . "' AND";
  }
}
if($mode) {
  $sql .= " mode='" . mysql_real_escape_string($mode) . "' AND";
}
if($active != "") {
  $sql .= " active='" . mysql_real_escape_string($active) . "' AND";
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

$result = mysql_query($sql . " LIMIT 10 OFFSET " . $offset, $db) or die ('0;Operation ' . $param . ' failed: ' . $sql);
$result2 = mysql_query(str_replace("*", "COUNT(*)", $sql), $db);
if($row = mysql_fetch_array($result2, MYSQL_NUM)) {
  $max = $row[0];
}
printf("%s;%s;%s", $offset, $max, $sql);

while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
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

?>
