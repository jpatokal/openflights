<?php
session_start();
$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);
$src_time = $HTTP_POST_VARS["src_time"];
$src_ap = $HTTP_POST_VARS["src_ap"];
$src_iata = $HTTP_POST_VARS["src_iata"];
$dst_ap = $HTTP_POST_VARS["dst_ap"];
$dst_iata = $HTTP_POST_VARS["dst_iata"];
$number = $HTTP_POST_VARS["number"];
$plid = $HTTP_POST_VARS["plane"];
$newplane = $HTTP_POST_VARS["newplane"];

$error = false;

if($number != "") {
  $code = substr($number,0,2);
} else {
  $code = $airline;
}

$sql = "SELECT * FROM airlines WHERE iata='" . $code . "' limit 1";
$result = mysql_query($sql, $db);
if (strlen($code) == 2 and $row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  $alid = $row["alid"];
  printf ("AIRLINE: %s (%s) id %s<br>", $row["name"], $code, $alid);
} else {
  print "<b>Airline with code '" . $code . "' not found</b><br>";
  $error = true;
}

if($src_iata != "") {
  $src = $src_iata;
} else {
  $src = $src_ap;
}
if(strlen($src) == 3) {
  $sql = "SELECT * FROM airports WHERE iata='" . $src . "' limit 1";
} else if (strlen($src) == 4) {
  $sql = "SELECT * FROM airports WHERE icao='" . $src . "' limit 1";
}
$result = mysql_query($sql, $db);
if (strlen($src) >= 3 and strlen($src) <= 4 and
    $row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  $lat1 = $row["y"];
  $lon1 = $row["x"];
  $src_apid = $row["apid"];
  printf ("SRC: %s id %s loc (%s, %s)<br>", $row["name"], $src_apid, $lat1, $lon1);
} else {
  printf ("<b>Source airport %s not found</b><br>", $src);
  $error = true;
}

if($dst_iata != "") {
  $dst = $dst_iata;
} else {
  $dst = $dst_ap;
}
if(strlen($dst) == 3) {
  $sql = "SELECT * FROM airports WHERE iata='" . $dst . "' limit 1";
} else if (strlen($src) == 4) {
  $sql = "SELECT * FROM airports WHERE icao='" . $dst . "' limit 1";
}
$result = mysql_query($sql, $db);
if (strlen($src) < 2 or strlen($src) > 4 or
    $row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  $lat2 = $row["y"];
  $lon2 = $row["x"];
  $dst_apid = $row["apid"];
  printf ("DST: %s id %s loc (%s, %s)<br>", $row["name"], $dst_apid, $lat2, $lon2);
} else {
  printf ("<b>Destination airport %s not found</b><br>", $dst);
  $error = true;
} 

if ($src_apid == $dst_apid) {
  printf ("<b>Source and destination airports are identical</b><br>", $dst);
  $error = true;
}

// New plane type?
while ($newplane != "") {
  $sql = "SELECT * FROM planes WHERE name='" . $newplane . "' limit 1";
  $result = mysql_query($sql, $db);
  if ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
    // Found it
    $plid = $row["plid"];
    $newplane = "";
  } else {
    $sql = "INSERT INTO planes(name) VALUES('" . $newplane . "')";
    print $sql;
    mysql_query($sql, $db) or die ('Adding plane to DB failed.');
  }
}
  
if (! $error) {
  $pi = 3.1415926;
  $rad = doubleval($pi/180.0);
  
  $lon1 = doubleval($lon1)*$rad; $lat1 = doubleval($lat1)*$rad;
  $lon2 = doubleval($lon2)*$rad; $lat2 = doubleval($lat2)*$rad;
  
  $theta = $lon2 - $lon1;
  $dist = acos(sin($lat1) * sin($lat2) + cos($lat1) * cos($lat2) * cos($theta));
  if ($dist < 0) { $dist += $pi; }
  $dist = $dist * 6371.2;
  $miles = doubleval($dist * 0.621);

  printf("MILES: %.0f<br>", $miles);

  $time = 30 + ($miles / 500) * 60;
  $duration = sprintf("%d:%02d", $time / 60, $time % 60);
  printf("DURATION: %s", $duration);

  $uid = 1;
  $seat = '';
  $seat_type = 'W';
  $class = 'Y';
  $reason = 'P';
  $trid = 'NULL';
  print "<br><br>";
  printf("INSERT INTO flights(uid, src_apid, src_time, dst_apid, duration, distance, code, seat, seat_type, class, reason, plid, alid, trid) VALUES (%s, %s, '%s', %s, '%s', %s, '%s', '%s', '%s', '%s', '%s', %s, %s, %s)",
	 $uid, $src_apid, $src_time, $dst_apid, $duration, $miles, $number, $seat, $seat_type, $class, $reason, $plid, $alid, $trid);

}

?>
