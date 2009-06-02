<?php
include 'locale.php';
include 'db.php';

$type = $HTTP_POST_VARS["type"];
$name = $HTTP_POST_VARS["name"];
$url = $HTTP_POST_VARS["url"];
$trid = $HTTP_POST_VARS["trid"];
$privacy = $HTTP_POST_VARS["privacy"];

$uid = $_SESSION["uid"];
if(!$uid or empty($uid)) {
  printf("0;Your session has timed out, please log in again.");
  exit;
}

switch($type) {
 case "LOAD":
   // Load data for existing trip
   $sql = "SELECT * FROM trips WHERE trid=" . mysql_real_escape_string($trid) . " AND uid=" . mysql_real_escape_string($uid);
   $result = mysql_query($sql, $db);
   if ($row = mysql_fetch_array($result)) {
     printf("1;%s;%s;%s;%s", $row["trid"], $row["name"], $row["url"], $row["public"]);
   } else {
     printf("0;Could not load trip data.");
   }
   exit;

 case "NEW":
   // Create new trip
   $sql = sprintf("INSERT INTO trips(name,url,public,uid) VALUES('%s','%s','%s', %s)",
		  mysql_real_escape_string($name),
		  mysql_real_escape_string($url),
		  mysql_real_escape_string($privacy),
		  $uid);
   break;

 case "EDIT":
   // Edit existing trip
   $sql = sprintf("UPDATE trips SET name='%s', url='%s', public='%s' WHERE uid=%s AND trid=%s",
		  mysql_real_escape_string($name),
		  mysql_real_escape_string($url),
		  mysql_real_escape_string($privacy),
		  $uid,
		  mysql_real_escape_string($trid));
   break;

   // Assign its flights to null and delete trip
 case "DELETE":
   $sql = sprintf("UPDATE flights SET trid=NULL WHERE trid=%s AND uid=%s",
		  mysql_real_escape_string($trid),
		  mysql_real_escape_string($uid));
   mysql_query($sql, $db) or die ('0;Operation on trip ' . $name . ' failed: ' . $sql . ', error ' . mysql_error());

   $sql = sprintf("DELETE FROM trips WHERE trid=%s AND uid=%s",
		  mysql_real_escape_string($trid),
		  mysql_real_escape_string($uid));
   break;
}

mysql_query($sql, $db) or die ('0;Operation on trip ' . $name . ' failed: ' . $sql . ', error ' . mysql_error());

switch($type) {
 case "NEW":
   $trid = mysql_insert_id();
   printf("1;%s;" . _("Trip successfully created"), $trid);
   break;

 case "DELETE":
   printf("100;%s;" . _("Trip successfully deleted"), $trid);
   break;

 default:
   printf("2;%s;" . _("Trip successfully edited."), $trid);
   break;
}
?>
