<?php
session_start();
$uid = $_SESSION["uid"];
if(!$uid or empty($uid)) {
  // If not logged in, default to demo mode
  $uid = 1;
}

$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);

$apid = $HTTP_POST_VARS["id"];
if(!$apid) {
  // For easier debugging
  $apid = $HTTP_GET_VARS["id"];
}

$trid = $HTTP_POST_VARS["trid"];
$alid = $HTTP_POST_VARS["alid"];
$fid = $HTTP_POST_VARS["fid"];

// List of all this user's flights
$sql = "SELECT s.iata AS src_iata,s.icao AS src_icao,s.apid AS src_apid,d.iata AS dst_iata,d.icao AS dst_icao,d.apid AS dst_apid,f.code,DATE(f.src_time) as src_date,distance,DATE_FORMAT(duration, '%H:%i') AS duration,seat,seat_type,class,reason,p.name,registration,fid,alid,note FROM airports AS s,airports AS d, flights AS f LEFT JOIN planes AS p ON f.plid=p.plid WHERE f.uid=" . $uid . " AND f.src_apid=s.apid AND f.dst_apid=d.apid";

// ...filtered by airport (optional)
if($apid && $apid != 0) {
  $sql = $sql . " AND (s.apid=" . mysql_real_escape_string($apid) . " OR d.apid=" . mysql_real_escape_string($apid) . ")";
}

// Add filters, if any
if($trid && $trid != "0") {
  $sql = $sql . " AND trid= " . mysql_real_escape_string($trid);
}
if($alid && $alid != "0") {
  $sql = $sql . " AND alid= " . mysql_real_escape_string($alid);
}
if($fid && $fid != "0") {
  $sql = $sql . " AND fid= " . mysql_real_escape_string($fid);
}

// Execute!
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  if($first) {
    $first = false;
  } else {
    printf("\t");
  }
  $src_code = $row["src_iata"];
  if($src_code == "") {
    $src_code = $row["src_icao"];
  }
  $dst_code = $row["dst_iata"];
  if($dst_code == "") {
    $dst_code = $row["dst_icao"];
  }
  printf ("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s", $src_code, $row["src_apid"], $dst_code, $row["dst_apid"], $row["code"], $row["src_date"], $row["distance"], $row["duration"], $row["seat"], $row["seat_type"], $row["class"], $row["reason"], $row["fid"], $row["name"], $row["registration"], $row["alid"], $row["note"]);
}
?>
