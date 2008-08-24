<?php
include 'helper.php';

$airport = $HTTP_POST_VARS["airport"];
$iata = $HTTP_POST_VARS["iata"];
$icao = $HTTP_POST_VARS["icao"];
$city = $HTTP_POST_VARS["city"];
$country = $HTTP_POST_VARS["country"];
$code = $HTTP_POST_VARS["code"];
$dbname = $HTTP_POST_VARS["db"];
$all = $HTTP_POST_VARS["all"];
$offset = $HTTP_POST_VARS["offset"];

$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);

if(! $dbname) {
  $dbname = "airports";
}
$sql = "SELECT * FROM " . $dbname . " WHERE ";
if($airport) {
  $sql .= " name LIKE '" . $airport . "%' AND";
}
if($iata) {
  $sql .= " iata='" . $iata . "' AND";
}
if($icao) {
  $sql .= " icao='" . $icao . "' AND";
}
if($city) {
  $sql .= " city LIKE '" . $city . "%' AND";
}
if($country != "ALL") {
  if($dbname == "airports_dafif") {
    if($code) {
      $sql .= " code='" . $code . "' AND";
    }
  } else {
    if($country) {
      $sql .= " country='" . $country . "' AND";
    }
  }
}
if($all == "true" || $dbname == "airports_dafif") {
  $sql .= " 1=1"; // dummy
 } else {
  $sql .= " iata != ''";
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
  printf ("\n%s;%s", $row["apid"], format_airport($row));
}

?>
