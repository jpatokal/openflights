<?php
require_once("../php/locale.php");
require_once("../php/db.php");

include 'helper.php';

$name = $HTTP_POST_VARS["name"];
$alias = $HTTP_POST_VARS["alias"];
$mode = $HTTP_POST_VARS["mode"];
if(! $mode || $mode == 'F') {
  $iata = $HTTP_POST_VARS["iata"];
  $icao = $HTTP_POST_VARS["icao"];
  $callsign = $HTTP_POST_VARS["callsign"];
  $mode = "F";
} else {
  $iata = "";
  $icao = "";
  $callsign = "";
}
$country = $HTTP_POST_VARS["country"];
$offset = $HTTP_POST_VARS["offset"];
$action = $HTTP_POST_VARS["action"];
$iatafilter = $HTTP_POST_VARS["iatafilter"];

if($action == "RECORD") {
  $uid = $_SESSION["uid"];
  if(!$uid or empty($uid)) {
    printf("0;" . _("Your session has timed out, please log in again."));
    exit;
  }

  // Check for duplicates
  $sql = "SELECT * FROM airlines WHERE mode='$mode' AND (name LIKE '" . mysql_real_escape_string($name) . "%' OR alias LIKE '" . mysql_real_escape_string($name) . "%');";
  $result = mysql_query($sql, $db);
  if($row = mysql_fetch_array($result, MYSQL_NUM)) {
    printf("0;" ."A " . $modeOperators[$mode] . " using the name or alias " . $name . " exists already.");
    exit;
  }

  if($alias != "") {
    $sql = "SELECT * FROM airlines WHERE mode='$mode' AND (name LIKE '" . mysql_real_escape_string($alias) . "%' OR alias LIKE '" . mysql_real_escape_string($alias) . "%');";
    $result = mysql_query($sql, $db);
    if($row = mysql_fetch_array($result, MYSQL_NUM)) {
      printf("0;"."A " . $modeOperators[$mode] . " using the name or alias " . $alias . " exists already.");
      exit;
    }
  }

  if($iata != "") {
    $sql = "SELECT * FROM airlines WHERE iata='" . mysql_real_escape_string($iata) . "'";
    $result = mysql_query($sql, $db);
    if($row = mysql_fetch_array($result, MYSQL_NUM)) {
      printf("0;An airline using the IATA code " . $iata . " exists already.");
      exit;
    }
  }
  if($icao != "") {
    $sql = "SELECT * FROM airlines WHERE icao='" . mysql_real_escape_string($icao) . "'";
    $result = mysql_query($sql, $db);
    if($row = mysql_fetch_array($result, MYSQL_NUM)) {
      printf("0;An airline using the ICAO code " . $icao . " exists already.");
      exit;
    }
  }

  $sql = sprintf("INSERT INTO airlines(name,alias,country,iata,icao,callsign,mode,active,uid) VALUES('%s', '%s', '%s', '%s', %s, '%s', '%s', '%s', %s)",
		 mysql_real_escape_string($name), 
		 mysql_real_escape_string($alias),
		 mysql_real_escape_string($country),
		 mysql_real_escape_string($iata),
		 $icao == "" ? "NULL" : "'" . mysql_real_escape_string($icao) . "'",
		 mysql_real_escape_string($callsign),
		 mysql_real_escape_string($mode),
		 $iata == "" ? "N" : "Y", // assume that new IATA-coded airlines are active!?
		 $uid);

  mysql_query($sql, $db) or die('0;Adding new ' . $modeOperators[$mode] . ' failed' . $sql);
  printf('1;' . mysql_insert_id() . ';New ' . $modeOperators[$mode] . ' successfully added.');
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
  // ##TODO## admin flag instead of hardcoding...
  if($row["uid"] || $uid == "3" ) {
    if($row["uid"] == $uid || $uid == "3") {
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
