<?php
session_start();
$uid = $_SESSION["uid"];
if(!$uid or empty($uid)) {
  // If not logged in, default to demo mode
  $uid = 1;
}

$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);
$id = $HTTP_POST_VARS["id"];
if(!$id) {
  // For easier debugging
  $id = $HTTP_GET_VARS["id"];
}

// List of all flights originating from an airport
$sql = "SELECT s.iata AS src_iata,s.apid AS src_apid,d.iata AS dst_iata,d.apid AS dst_apid,f.code,DATE(f.src_time) as src_date,distance,DATE_FORMAT(duration, '%H:%i') AS duration,seat,seat_type,class,reason,fid FROM flights AS f,airports AS s,airports AS d WHERE f.uid=" . $uid . " AND f.src_apid=s.apid AND f.dst_apid=d.apid AND (s.apid=" . $id . " OR d.apid=" . $id . ")";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  if($first) {
    $first = false;
  } else {
    printf("\t");
  }  
  printf ("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s", $row["src_iata"], $row["src_apid"], $row["dst_iata"], $row["dst_apid"], $row["code"], $row["src_date"], $row["distance"], $row["duration"], $row["seat"], $row["seat_type"], $row["class"], $row["reason"], $row["fid"]);
}
?>
