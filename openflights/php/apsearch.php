<?php
include 'helper.php';

$airport = $HTTP_POST_VARS["airport"];
$iata = $HTTP_POST_VARS["iata"];
$icao = $HTTP_POST_VARS["icao"];
$city = $HTTP_POST_VARS["city"];
$country = $HTTP_POST_VARS["country"];
$code = $HTTP_POST_VARS["code"];
$dbname = $HTTP_POST_VARS["db"];
$iatafilter = $HTTP_POST_VARS["iatafilter"];
$offset = $HTTP_POST_VARS["offset"];

$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);

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
  printf ("\n%s:%s:%s:%s;%s", $row["iata"], $row["apid"], $row["x"], $row["y"], format_airport($row));
}

?>
