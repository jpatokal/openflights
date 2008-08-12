<?php
session_start();
$uid = $_SESSION["uid"];
if(!$uid or empty($uid)) {
  printf("Not logged in, aborting");
  exit;
}

$src_date = $HTTP_POST_VARS["src_date"];
$duration = $HTTP_POST_VARS["duration"];
$distance = $HTTP_POST_VARS["distance"];
$src_apid = $HTTP_POST_VARS["src_apid"];
$dst_apid = $HTTP_POST_VARS["dst_apid"];
$number = $HTTP_POST_VARS["number"];
$seat = $HTTP_POST_VARS["seat"];
$seat_type = $HTTP_POST_VARS["type"];
$class = $HTTP_POST_VARS["class"];
$reason = $HTTP_POST_VARS["reason"];
$registration = $HTTP_POST_VARS["registration"];
$plid = $HTTP_POST_VARS["plid"];
$alid = $HTTP_POST_VARS["alid"];
$trid = $HTTP_POST_VARS["trid"];

$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);

$sql = sprintf("INSERT INTO flights(uid, src_apid, src_time, dst_apid, duration, distance, registration, code, seat, seat_type, class, reason, plid, alid, trid) VALUES (%s, %s, '%s', %s, '%s', %s, '%s', '%s', '%s', '%s', '%s', '%s', %s, %s, %s)",
	       $uid, $src_apid, $src_date, $dst_apid, $duration, $distance, $registration, $number, $seat, $seat_type, $class, $reason, $plid, $alid, $trid);

mysql_query($sql, $db) or die ('0;Adding plane to DB failed: ' . $sql);
printf("1;Flight added.");
?>
