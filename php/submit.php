<?php
header("Content-type: text/html; charset=iso-8859-1");

session_start();
$uid = $_SESSION["uid"];
if(!$uid or empty($uid)) {
  printf("Not logged in, aborting");
  exit;
}

include 'helper.php';

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
$alid = trim($HTTP_POST_VARS["alid"]); // IE adds some whitespace crud to this!?
$trid = $HTTP_POST_VARS["trid"];
$fid = $HTTP_POST_VARS["fid"];
$note = $HTTP_POST_VARS["note"];
$param = $HTTP_POST_VARS["param"];

$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);
$json = array();

// Validate user-entered information
if($param == "ADD" || $param == "EDIT") {
  $plane = $HTTP_POST_VARS["plane"];

  // New planes can be created on the fly
  if($plane != "") {
    $sql = "SELECT plid FROM planes WHERE name='" . mysql_real_escape_string($plane) . "' limit 1";
    $result = mysql_query($sql, $db);
    if ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
      // Match found, take its plid
      $plid = $row["plid"];
    } else {
      // No match, create new entry
      $sql = "INSERT INTO planes(name, public) VALUES('" . mysql_real_escape_string($plane) . "', 'N')";
      mysql_query($sql, $db) or die('0;Adding new plane failed');
      $plid = mysql_insert_id();
    }
  } else {
    $plid = "NULL";
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
   // Check uid to prevent an evil logged-in hacker from deleting somebody else's flight
   $sql = sprintf("DELETE FROM flights WHERE uid=%s AND fid=%s", $uid, mysql_real_escape_string($fid));
   break;

 default:
   die('0;Unknown operation ' . $param);
 }

mysql_query($sql, $db) or die('0;Database error when executing query ' . $sql);

switch($param) {
 case "DELETE":
   $code = 100;
   $msg = "Flight deleted.";
   break;

 case "ADD":
   $code = 1;
   $msg = "Flight added.";
   break;

 case "EDIT":
   $code = 2;
   $msg = "Flight edited.";
   break;
}

print $code . ";" . $msg;
?>
