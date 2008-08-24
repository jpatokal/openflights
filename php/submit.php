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
  
  while ($newplane != "OK") {
    $sql = "SELECT * FROM planes WHERE name='" . $newplane . "' limit 1";
    $result = mysql_query($sql, $db);
    if ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
      // Found it
      $plid = $row["plid"];
      $newplane = "OK";
    } else {
      $sql = "INSERT INTO planes(name) VALUES('" . $newplane . "')";
      mysql_query($sql, $db) or die('0;Adding new plane failed');
    }
  }
 }

switch($param) {
 case "ADD":
   $sql = sprintf("INSERT INTO flights(uid, src_apid, src_time, dst_apid, duration, distance, registration, code, seat, seat_type, class, reason, note, plid, alid, trid, upd_time) VALUES (%s, %s, '%s', %s, '%s', %s, '%s', '%s', '%s', '%s', '%s', '%s', '%s', %s, %s, %s, NOW())",
		  $uid, $src_apid, $src_date, $dst_apid, $duration, $distance, $registration, $number, $seat, $seat_type, $class, $reason, $note, $plid, $alid, $trid);
   break;

 case "EDIT":
   $sql = sprintf("UPDATE flights SET src_apid=%s, src_time='%s', dst_apid=%s, duration='%s', distance=%s, registration='%s', code='%s', seat='%s', seat_type='%s', class='%s', reason='%s', note='%s', plid=%s, alid=%s, trid=%s, upd_time=NOW() WHERE fid=%s",
		  $src_apid, $src_date, $dst_apid, $duration, $distance, $registration, $number, $seat, $seat_type, $class, $reason, $note, $plid, $alid, $trid, $fid);
   break;

 case "DELETE":
   // uid is strictly speaking unnecessary, but just to be sure...
   $sql = sprintf("DELETE FROM flights WHERE uid=%s AND fid=%s", $uid, $fid);
   break;

 default:
   die('0;Unknown operation ' . $param);
 }

mysql_query($sql, $db) or die ('0;Operation ' . $param . ' failed: ' . $sql);

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
