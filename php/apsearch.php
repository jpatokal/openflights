<?php
session_start();
header("Content-type: text/html; charset=iso-8859-1");

include 'helper.php';

$airport = $HTTP_POST_VARS["airport"];
$iata = $HTTP_POST_VARS["iata"];
$icao = $HTTP_POST_VARS["icao"];
$city = $HTTP_POST_VARS["city"];
$country = $HTTP_POST_VARS["country"];
$code = $HTTP_POST_VARS["code"];
$myX = $HTTP_POST_VARS["x"];
$myY = $HTTP_POST_VARS["y"];
$elevation = $HTTP_POST_VARS["elevation"];
$dbname = $HTTP_POST_VARS["db"];
$iatafilter = $HTTP_POST_VARS["iatafilter"];
$offset = $HTTP_POST_VARS["offset"];
$action = $HTTP_POST_VARS["action"];
$apid = $HTTP_POST_VARS["apid"];

$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);

$uid = $_SESSION["uid"];

if($action == "RECORD") {
  if(!$uid or empty($uid)) {
    printf("0;Your session has timed out, please log in again.");
    exit;
  }

  if(! $apid || $apid == "") {
    // Adding a new airport, so first check for duplicates
    if($icao != "") {
      $sql = "SELECT * FROM airports WHERE icao='" . mysql_real_escape_string($icao) . "'";
      $result = mysql_query($sql, $db);
      if($row = mysql_fetch_array($result, MYSQL_NUM)) {
	printf("0;Sorry, an airport using the ICAO code " . $icao . " exists already.  Please double-check.");
	exit;
      }
    }
    
    $sql = sprintf("INSERT INTO airports(name,city,country,iata,icao,x,y,elevation,uid) VALUES('%s', '%s', '%s', '%s', %s, %s, %s, %s, %s)",
		   mysql_real_escape_string($airport), 
		   mysql_real_escape_string($city),
		   mysql_real_escape_string($country),
		   mysql_real_escape_string($iata),
		   $icao == "" ? "NULL" : "'" . mysql_real_escape_string($icao) . "'",
		   mysql_real_escape_string($myX),
		   mysql_real_escape_string($myY),
		   mysql_real_escape_string($elevation),
		   $uid);
  } else {
    // Editing an existing airport
    // ##TODO## un-hardcode admin write access
    $sql = sprintf("UPDATE airports SET name='%s', city='%s', country='%s', iata='%s', icao=%s, x=%s, y=%s, elevation=%s WHERE apid=%s AND (uid=%s OR %s=3)",
		   mysql_real_escape_string($airport), 
		   mysql_real_escape_string($city),
		   mysql_real_escape_string($country),
		   mysql_real_escape_string($iata),
		   $icao == "" ? "NULL" : "'" . mysql_real_escape_string($icao) . "'",
		   mysql_real_escape_string($myX),
		   mysql_real_escape_string($myY),
		   mysql_real_escape_string($elevation),
		   mysql_real_escape_string($apid),
		   $uid,
		   $uid);
  }

  mysql_query($sql, $db) or die('0;Adding new airport failed: ' . $sql);
  if(! $apid || $apid == "") {
    printf('1;' . mysql_insert_id() . ';New airport successfully added.');
  } else {
    if(mysql_affected_rows() == 1) {
      printf('1;' . $apid . ';Airport successfully edited.');
    } else {
      printf('0;Editing airport failed: ' . $sql);
    }
  }
  exit;
}

if(! $dbname) {
  $dbname = "airports";
}
$sql = "SELECT * FROM " . mysql_real_escape_string($dbname) . " WHERE ";

// Build filter
if($airport) {
  $sql .= " name LIKE '" . mysql_real_escape_string($airport) . "%' AND";
}
if($iata) {
  $sql .= " iata='" . mysql_real_escape_string($iata) . "' AND";
}
if($icao) {
  $sql .= " icao='" . mysql_real_escape_string($icao) . "' AND";
}
if($city) {
  $sql .= " city LIKE '" . mysql_real_escape_string($city) . "%' AND";
}
if($country != "ALL") {
  if($dbname == "airports_dafif") {
    if($code) {
      $sql .= " code='" . mysql_real_escape_string($code) . "' AND";
    }
  } else {
    if($country) {
      $sql .= " country='" . mysql_real_escape_string($country) . "' AND";
    }
  }
}

// Disable this filter for DAFIF (no IATA data)
if($iatafilter == "false" || $dbname == "airports_dafif") {
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
  if($dbname == "airports_dafif") {
    $country = $row["code"];
  } else {
    $country = $row["country"];
  }
 
  // ##TODO## admin flag instead of hardcoding...
  if($row["uid"] || $uid == "3" ) {
    if($row["uid"] == $uid || $uid == "3") {
      $ap_uid = "own"; // editable
    } else {
      $ap_uid = "user"; // added by another user
    }
  } else {
    $ap_uid = null; // in DB
  }
  printf ("\n%s;%s;%s;%s;%s;%s;%s;%s;%s;%s;%s", $row["iata"], $row["icao"], $row["apid"], $row["x"], $row["y"], $row["elevation"], $row["name"], $country, format_airport($row), $ap_uid, $row["city"]);
}

?>
