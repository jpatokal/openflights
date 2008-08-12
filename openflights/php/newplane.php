<?php
session_start();
$uid = $_SESSION["uid"];
if(!$uid or empty($uid)) {
  printf("Not logged in, aborting");
  exit;
}

$newplane = $HTTP_POST_VARS["param"];

$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);

while ($newplane != "") {
  $sql = "SELECT * FROM planes WHERE name='" . $newplane . "' limit 1";
  $result = mysql_query($sql, $db);
  if ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
    // Found it
    printf("%s;%s", $row["plid"], "Plane added");
    $newplane = "";
  } else {
    $sql = "INSERT INTO planes(name) VALUES('" . $newplane . "')";
    mysql_query($sql, $db) or die('0;Adding new plane failed');
  }
}
?>
