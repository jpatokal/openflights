<?php
include 'locale.php';
$uid = $_SESSION["uid"];
if(!$uid or empty($uid)) {
  printf("Not logged in, aborting");
  exit;
}

include 'helper.php';
include 'db.php';

// Hack to record X-Y and Y-X flights as same in DB
function orderAirports($src_apid, $dst_apid) {
  if($src_apid > $dst_apid) {
    return array($dst_apid, $src_apid, "Y");
  } else {
    return array($src_apid, $dst_apid, "N");
  }
}

$duration = $HTTP_POST_VARS["duration"];
$distance = $HTTP_POST_VARS["distance"];
$number = $HTTP_POST_VARS["number"];
$seat = $HTTP_POST_VARS["seat"];
$seat_type = $HTTP_POST_VARS["type"];
$class = $HTTP_POST_VARS["class"];
$reason = $HTTP_POST_VARS["reason"];
$registration = $HTTP_POST_VARS["registration"];
$trid = $HTTP_POST_VARS["trid"];
$fid = $HTTP_POST_VARS["fid"];
$mode = $HTTP_POST_VARS["mode"];
$note = stripslashes($HTTP_POST_VARS["note"]);
$param = $HTTP_POST_VARS["param"];
$multi = $HTTP_POST_VARS["multi"];

if(!$mode || $mode == "") {
  $mode = "F";
}

$src_time = $HTTP_POST_VARS["src_time"];
if(! $src_time || $src_time == "") {
  $src_time = "NULL";
} else {
  $src_time = "'" . mysql_real_escape_string($src_time) . "'";
}

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

switch($param) {
 case "ADD":
   // Can add multiple flights or just one
   if($multi) {
     for($idx = 0; $idx < $multi; $idx++) {
       $rows[$idx] = $idx + 1;
     }
   } else {
     $rows = array("");
   }
   $sql = "INSERT INTO flights(uid, src_apid, src_date, src_time, dst_apid, duration, distance, registration, code, seat, seat_type, class, reason, note, plid, alid, trid, upd_time, opp, mode) VALUES";
   foreach($rows as $idx) {
     $src_date = $HTTP_POST_VARS["src_date" . $idx];
     $src_apid = $HTTP_POST_VARS["src_apid" . $idx];
     $dst_apid = $HTTP_POST_VARS["dst_apid" . $idx];
     $alid = trim($HTTP_POST_VARS["alid" . $idx]);
     if($alid == 0) $alid = -1; // this should not be necessary, but just in case...
     if(! $_POST["duration"]) {
       list($distance, $duration) = gcDistance($db, $src_apid, $dst_apid);
     }
     list($src_apid, $dst_apid, $opp) = orderAirports($src_apid, $dst_apid, $opp);

     if($idx != "" && $idx != "1") {
       $sql .= ",";
     }
     $sql = $sql . sprintf("(%s, %s, '%s', %s, %s, '%s', %s, '%s', '%s', '%s', '%s', '%s', '%s', '%s', %s, %s, %s, NOW(), '%s', '%s')",
			   $uid, mysql_real_escape_string($src_apid), mysql_real_escape_string($src_date), $src_time, mysql_real_escape_string($dst_apid), mysql_real_escape_string($duration), mysql_real_escape_string($distance), mysql_real_escape_string($registration), mysql_real_escape_string($number), mysql_real_escape_string($seat), mysql_real_escape_string($seat_type), mysql_real_escape_string($class), mysql_real_escape_string($reason), mysql_real_escape_string($note), mysql_real_escape_string($plid), mysql_real_escape_string($alid), mysql_real_escape_string($trid), $opp, mysql_real_escape_string($mode));
   }
   break;

 case "EDIT":
   $src_date = $HTTP_POST_VARS["src_date"];
   $src_apid = $HTTP_POST_VARS["src_apid"];
   $dst_apid = $HTTP_POST_VARS["dst_apid"];
   $alid = trim($HTTP_POST_VARS["alid"]); // IE adds some whitespace crud to this!?
   if($alid == 0) $alid = -1; // this should not be necessary, but just in case...
   list($src_apid, $dst_apid, $opp) = orderAirports($src_apid, $dst_apid, $opp);
   $sql = sprintf("UPDATE flights SET src_apid=%s, src_date='%s', src_time=%s, dst_apid=%s, duration='%s', distance=%s, registration='%s', code='%s', seat='%s', seat_type='%s', class='%s', reason='%s', note='%s', plid=%s, alid=%s, trid=%s, opp='%s', mode='%s' WHERE fid=%s",
		  mysql_real_escape_string($src_apid), mysql_real_escape_string($src_date), $src_time, mysql_real_escape_string($dst_apid), mysql_real_escape_string($duration), mysql_real_escape_string($distance), mysql_real_escape_string($registration), mysql_real_escape_string($number), mysql_real_escape_string($seat), mysql_real_escape_string($seat_type), mysql_real_escape_string($class), mysql_real_escape_string($reason), mysql_real_escape_string($note), mysql_real_escape_string($plid), mysql_real_escape_string($alid), mysql_real_escape_string($trid), $opp, mysql_real_escape_string($mode), mysql_real_escape_string($fid));
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
   $msg = $modes[$mode] . " deleted.";
   break;

 case "ADD":
   $code = 1;
   $count = mysql_affected_rows();
   if($count == 1) {
     $msg = _("Added.");
   } else {
     $msg = sprintf(_("%s flights added."), $count);
   }
   break;

 case "EDIT":
   $code = 2;
   $msg = _("Edited.");
   break;
}

print $code . ";" . $msg;
?>
