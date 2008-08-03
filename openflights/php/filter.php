<?php
session_start();
$uid = $_SESSION["uid"];
if(!$uid or empty($uid)) {
  printf("Not logged in, aborting");
  exit;
}

$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);

// Load up possible filter settings for this user

// List of all trips
$sql = "SELECT * FROM trips WHERE uid=" . $uid . " ORDER BY name";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  if($first) {
    $first = false;
  } else {
    printf("\t");
  }  
  printf ("%s;%s;%s", $row["trid"], $row["name"], $row["url"]);
}
printf ("\n");

// List of all airlines
$sql = "SELECT DISTINCT a.alid, name FROM airlines as a, flights as f WHERE uid=" . $uid . " AND a.alid=f.alid ORDER BY name";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  if($first) {
    $first = false;
  } else {
    printf("\t");
  }  
  printf ("%s;%s", $row["alid"], $row["name"]);
}
?>
