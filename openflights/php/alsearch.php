<?php
session_start();
header("Content-type: text/html; charset=iso-8859-1");

include 'helper.php';

$airline = $HTTP_POST_VARS["airline"];
$alias = $HTTP_POST_VARS["alias"];
$iata = $HTTP_POST_VARS["iata"];
$icao = $HTTP_POST_VARS["icao"];
$callsign = $HTTP_POST_VARS["callsign"];
$country = $HTTP_POST_VARS["country"];
$offset = $HTTP_POST_VARS["offset"];
$action = $HTTP_POST_VARS["action"];
$iatafilter = $HTTP_POST_VARS["iatafilter"];

$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);

if($action == "RECORD") {
  $uid = $_SESSION["uid"];
  if(!$uid or empty($uid)) {
    printf("0;Your session has timed out, please log in again.");
    exit;
  }

  // Check for duplicates
  $sql = "SELECT * FROM airlines WHERE name LIKE '" . mysql_real_escape_string($airline) . "%' OR alias LIKE '" . mysql_real_escape_string($airline) . "%';";
  $result = mysql_query($sql, $db);
  if($row = mysql_fetch_array($result, MYSQL_NUM)) {
    printf("0;An airline using the name or alias " . $airline . " exists already.");
    exit;
  }

  if($alias != "") {
    $sql = "SELECT * FROM airlines WHERE name LIKE '" . mysql_real_escape_string($alias) . "%' OR alias LIKE '" . mysql_real_escape_string($alias) . "%';";
    $result = mysql_query($sql, $db);
    if($row = mysql_fetch_array($result, MYSQL_NUM)) {
      printf("0;An airline using the name or alias " . $alias . " exists already.");
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

  $sql = sprintf("INSERT INTO airlines(name,alias,country,iata,icao,callsign,uid) VALUES('%s', '%s', '%s', '%s', %s, '%s', %s)",
		 mysql_real_escape_string($airline), 
		 mysql_real_escape_string($alias),
		 mysql_real_escape_string($country),
		 mysql_real_escape_string($iata),
		 $icao == "" ? "NULL" : "'" . mysql_real_escape_string($icao) . "'",
		 mysql_real_escape_string($callsign),
		 $uid);

  mysql_query($sql, $db) or die('0;Adding new airline failed' . $sql);
  printf('1;' . mysql_insert_id() . ';New airline successfully added.');
  exit;
}

$sql = "SELECT * FROM airlines WHERE ";

// Build filter
if($airline) {
  $sql .= " (name LIKE '" . mysql_real_escape_string($airline) . "%' OR alias LIKE '" . mysql_real_escape_string($airline) . "%') AND";
}
if($alias) {
  $sql .= " (name LIKE '" . mysql_real_escape_string($alias) . "%' OR alias LIKE '" . mysql_real_escape_string($alias) . "%') AND";
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
if($iatafilter == "false") {
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
  printf ("\n%s;%s;%s;%s;%s;%s;%s;%s;%s", $row["iata"], $row["icao"], $row["alid"], $row["name"], $row["alias"], $row["country"], $row["callsign"], format_airline($row), $row["uid"]);
}

?>
