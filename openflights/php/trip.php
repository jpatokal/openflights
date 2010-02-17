<?php
include 'locale.php';
include 'db.php';

$type = $_POST["type"];
$name = $_POST["name"];
$url = $_POST["url"];
$trid = $_POST["trid"];
$privacy = $_POST["privacy"];

if($type != "NEW" and (!$trid or $trid == 0)) {
  die ('0;Trip ID '. $trid . ' invalid');
}

$uid = $_SESSION["uid"];
if(!$uid or empty($uid)) {
  die ('0;' . _("Your session has timed out, please log in again."));
  exit;
}

switch($type) {
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

 default:
   die ('0;Unknown operation ' . $type);
}

mysql_query($sql, $db) or die ('0;Operation on trip ' . $name . ' failed: ' . $sql . ', error ' . mysql_error());
if(mysql_affected_rows() != 1) {
  die("0;No matching trip found");
}
  
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
