<?php
include 'locale.php';
include 'db_pdo.php';

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
   $sth = $dbh->prepare("INSERT INTO trips(name,url,public,uid) VALUES(?,?,?,?)");
   $success = $sth->execute([$name, $url, $privacy, $uid]);
   break;

 case "EDIT":
   // Edit existing trip
   $sth = $dbh->prepare("UPDATE trips SET name=?, url=?, public=? WHERE uid=? AND trid=?");
   $success = $sth->execute([$name, $url, $privacy, $uid, $trid]);
   break;

   // Assign its flights to null and delete trip
 case "DELETE":
   $sth = $dbh->prepare("UPDATE flights SET trid=NULL WHERE trid=? AND uid=?");
   $sth->execute([$trid, $uid]) or die ('0;Operation on trip ' . $name . ' failed.');

   $sth = $dbh->prepare("DELETE FROM trips WHERE trid=? AND uid=?");
   $success = $sth->execute([$trid, $uid]);
   break;

 default:
   die ('0;Unknown operation ' . $type);
}

$success or die ('0;Operation on trip ' . $name . ' failed.');
if($sth->rowCount() != 1) {
  die("0;No matching trip found");
}
  
switch($type) {
 case "NEW":
   $trid = $dbh->lastInsertId();
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
