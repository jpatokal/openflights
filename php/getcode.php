<?php
include 'helper.php';

$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);

$src = $HTTP_POST_VARS["src"];
if(!$src) {
  $src = $HTTP_GET_VARS["src"];
}
$dst = $HTTP_POST_VARS["dst"];
if(!$dst) {
  $dst = $HTTP_GET_VARS["dst"];
}
$airline = $HTTP_POST_VARS["airline"];
if(!$airline) {
  $airline = $HTTP_GET_VARS["airline"];
}

if($src) getAirport("SRC", $src);
if($dst) getAirport("DST", $dst);
if($airline) getAirline("AIRLINE", $airline);

function getAirport($id, $code) {
  global $db;
  $error = false;
  printf ("%s\n", $id);
  $len = strlen($code);
  $sql = "SELECT 2 as sort_col,apid,name,city,country,iata,icao,x,y FROM airports WHERE iata!='' AND iata != '" . mysql_real_escape_string($code) . "' AND city LIKE '" . mysql_real_escape_string($code) . "%' ORDER BY city,name";
  if($len == 3) {
    $sql = "SELECT 1 as sort_col,apid,name,city,country,iata,icao,x,y FROM airports WHERE iata='" . mysql_real_escape_string($code) . "' UNION (" . $sql . ") ORDER BY sort_col,city,name";
  } else if ($len == 4) {
    $sql = "SELECT 1 as sort_col,apid,name,city,country,iata,icao,x,y FROM airports WHERE icao='" . mysql_real_escape_string($code) . "' UNION (" . $sql . ") ORDER BY sort_col,city,name";
  } else if ($len < 3) {
    $error = true;
    printf ("0;Enter airport code or name\n");
  }
  if(!$error) {
    $result = mysql_query($sql, $db);
    $found = false;
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
      $code = $row["iata"];
      if($code == "") {
	$code = $row["icao"];
      }
      printf ("%s:%s:%s:%s;%s\n", $code, $row["apid"], $row["x"], $row["y"], format_airport($row));
      $found = true;
    }
    if(! $found) {
      printf ("0;No matching airport found\n");
    }
  }
}

function getAirline($id, $code) {
  printf ("%s\n", $id);
  global $db;
  $error = false;
  $len = strlen($code);

  // For short strings, filter out non-IATA airlines
  if($len <= 3) {
    $ext = "iata!='' AND";
  } else {
    $ext = "";
  }
  $sql = "SELECT 2 as sort_col,alid,name,iata,icao FROM airlines WHERE " . $ext . " name LIKE '" . mysql_real_escape_string($code) . "%' ORDER BY name";
  if($len == 2) {
    $sql = "SELECT 1 as sort_col,alid,name,iata,icao FROM airlines WHERE iata='" . mysql_real_escape_string($code) . "' UNION (" . $sql . ") ORDER BY sort_col, name";
  } else if ($len == 3) {
    $sql = "SELECT 1 as sort_col,alid,name,iata,icao FROM airlines WHERE icao='" . mysql_real_escape_string($code) . "' UNION (" . $sql . ") ORDER BY sort_col, name";
  } else if ($len < 2) {
    printf ("0;Enter airline code or name\n");
    return;
  }
  if(!$error) {
    $result = mysql_query($sql, $db);
    $found = false;
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
      $code = $row["iata"];
      if(!$code || $code == "") {
	$code = $row["icao"];
      }
      printf ("%s;%s\n", $code . ":" . $row["alid"], $row["name"] . " (" . $code . ")");
      $found = true;
    } 
    if(! $found) {
      printf ("0;No matching airline found\n");
    }
  }
}

?>
