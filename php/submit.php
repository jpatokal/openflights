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
$fid = $HTTP_POST_VARS["fid"];
$note = $HTTP_POST_VARS["note"];
$param = $HTTP_POST_VARS["param"];

$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);

// If $plid is of form "NEW:xxx", we add a new plane type called xxx and read its auto-assigned plid
if(strstr($plid, "NEW:")) {
  $newplane = substr($plid, 4);
  
  $sql = "SELECT * FROM planes WHERE name='" . mysql_real_escape_string($newplane) . "' limit 1";
  $result = mysql_query($sql, $db);
  if ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
    // Found it
    $plid = $row["plid"];
  } else {
    $sql = "INSERT INTO planes(name) VALUES('" . mysql_real_escape_string($newplane) . "')";
    mysql_query($sql, $db) or die('0;Adding new plane failed');
    $plid = mysql_insert_id();
  }
 }

// Hack to record X-Y and Y-X flights as same in DB
if($src_apid > $dst_apid) {
  $tmp = $src_apid;
  $src_apid = $dst_apid;
  $dst_apid = $tmp;
  $opp = "Y";
} else {
  $opp = "N";
}

switch($param) {
 case "ADD":
   $sql = sprintf("INSERT INTO flights(uid, src_apid, src_time, dst_apid, duration, distance, registration, code, seat, seat_type, class, reason, note, plid, alid, trid, upd_time, opp) VALUES (%s, %s, '%s', %s, '%s', %s, '%s', '%s', '%s', '%s', '%s', '%s', '%s', %s, %s, %s, NOW(), '%s')",
		  $uid, mysql_real_escape_string($src_apid), mysql_real_escape_string($src_date), mysql_real_escape_string($dst_apid), mysql_real_escape_string($duration), mysql_real_escape_string($distance), mysql_real_escape_string($registration), mysql_real_escape_string($number), mysql_real_escape_string($seat), mysql_real_escape_string($seat_type), mysql_real_escape_string($class), mysql_real_escape_string($reason), mysql_real_escape_string($note), mysql_real_escape_string($plid), mysql_real_escape_string($alid), mysql_real_escape_string($trid), $opp);
   break;

 case "EDIT":
   $sql = sprintf("UPDATE flights SET src_apid=%s, src_time='%s', dst_apid=%s, duration='%s', distance=%s, registration='%s', code='%s', seat='%s', seat_type='%s', class='%s', reason='%s', note='%s', plid=%s, alid=%s, trid=%s, upd_time=NOW(), opp='%s' WHERE fid=%s",
		  mysql_real_escape_string($src_apid), mysql_real_escape_string($src_date), mysql_real_escape_string($dst_apid), mysql_real_escape_string($duration), mysql_real_escape_string($distance), mysql_real_escape_string($registration), mysql_real_escape_string($number), mysql_real_escape_string($seat), mysql_real_escape_string($seat_type), mysql_real_escape_string($class), mysql_real_escape_string($reason), mysql_real_escape_string($note), mysql_real_escape_string($plid), mysql_real_escape_string($alid), mysql_real_escape_string($trid), $opp, mysql_real_escape_string($fid));
   break;

 case "DELETE":
   // uid is strictly speaking unnecessary, but just to be sure...
   $sql = sprintf("DELETE FROM flights WHERE uid=%s AND fid=%s", $uid, mysql_real_escape_string($fid));
   break;

 default:
   die('0;Unknown operation ' . $param);
 }

mysql_query($sql, $db) or die ('0;Operation ' . $param . ' failed: ' . $sql . ', error ' . mysql_error());

switch($param) {
 case "DELETE":
   printf("100;Flight deleted.");
   break;

 case "ADD":
   if($newplane == "OK") {
     printf("11;Flight and plane added.");
   } else {
     printf("1;Flight added.");
   }
   break;

 case "EDIT":
   if($newplane == "OK") {
     printf("12;Flight and plane edited.");
   } else {
     printf("2;Flight edited.");
   }
   break;
}

?>
