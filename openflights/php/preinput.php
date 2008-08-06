<?php
include 'helper.php';

session_start();
$uid = $_SESSION["uid"];
if(!$uid or empty($uid)) {
  printf("Not logged in, aborting");
  exit;
}
$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);

$sql = "SELECT DISTINCT a.apid,a.name,a.iata,a.city,a.country,a.x,a.y FROM flights AS f,airports AS a WHERE uid=" . $uid . " AND (a.apid=f.src_apid OR a.apid=f.dst_apid) ORDER BY a.iata";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  if($first) {
    $first = false;
  } else {
    printf("\t");
  }  
  printf ("%s:%s:%s:%s;%s", $row["iata"], $row["apid"], $row["x"], $row["y"], format_airport($row));
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
  printf ("%s;%s", $row["iata"] . ":" . $row["alid"], format_airline($row));
}
printf ("\n");

$sql = "SELECT DISTINCT p.plid, p.name FROM flights AS f,planes AS p WHERE uid=" . $uid . " AND p.plid=f.plid ORDER BY NAME";
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


