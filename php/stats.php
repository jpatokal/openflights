<?php
session_start();
$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);
$id = $HTTP_POST_VARS["id"];
if(!$id) {
  // For easier debugging
  $id = $HTTP_GET_VARS["id"];
}

// List top 10 airports
$sql = "select a.name, a.iata, count(fid) as count, a.apid from airports as a, flights as f where uid=1 and (f.src_apid=a.apid or f.dst_apid=a.apid) group by a.apid order by count desc limit 10";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  if($first) {
    $first = false;
  } else {
    printf(":");
  }  
  printf ("%s,%s,%s,%s", $row["name"], $row["iata"], $row["count"], $row["apid"]);
}
printf ("\n");

// List top 10 airlines
$sql = "select a.name, count(fid) as count, a.alid from airlines as a, flights as f where uid=1 and f.alid=a.alid group by f.alid order by count desc limit 10";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  if($first) {
    $first = false;
  } else {
    printf(":");
  }  
  printf ("%s,%s,%s", $row["name"], $row["count"], $row["alid"]);
}
printf ("\n");

// List top 10 plane types
$sql = "select p.name, count(fid) as count from planes as p, flights as f where uid=1 and p.plid=f.plid group by f.plid order by count desc limit 10";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  if($first) {
    $first = false;
  } else {
    printf(":");
  }  
  printf ("%s,%s", $row["name"], $row["count"]);
}

?>
