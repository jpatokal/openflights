<?php
session_start();
$uid = $_SESSION["uid"];
if(!$uid or empty($uid)) {
  printf("Not logged in, aborting");
  exit;
}
$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);

$sql = "SELECT DISTINCT a.apid,a.name,a.iata,a.city,a.country FROM flights AS f,airports AS a WHERE uid=" . $uid . " AND (a.apid=f.src_apid OR a.apid=f.dst_apid) ORDER BY a.iata";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  if($first) {
    $first = false;
  } else {
    printf("\t");
  }  
  printf ("%s;%s", $row["apid"], $row["iata"] . ": " . $row["name"] . ", " . $row["city"] . ", " . $row["country"]);
}
printf ("\n");

$sql = "SELECT DISTINCT a.alid,a.name,a.iata FROM flights AS f,airlines AS a WHERE uid=" . $uid . " AND a.alid=f.alid ORDER BY a.iata";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  if($first) {
    $first = false;
  } else {
    printf("\t");
  }  
  printf ("%s;%s", $row["alid"], $row["iata"] . ": " . $row["name"]);
}
printf ("\n");

$sql = "SELECT DISTINCT p.plid, p.name FROM flights AS f,planes AS p WHERE uid=" . $uid . " AND p.plid=f.plid";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  if($first) {
    $first = false;
  } else {
    printf("\t");
  }  
  printf ("%s;%s", $row["plid"], $row["name"]);
}


