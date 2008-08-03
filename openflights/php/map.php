<?php
session_start();
$uid = $_SESSION["uid"];
if(!$uid or empty($uid)) {
  printf("Not logged in, aborting");
  exit;
}

$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);

$init = $HTTP_POST_VARS["init"];
if(! $init) {
  $init = $HTTP_GET_VARS["init"];
}
$trid = $HTTP_POST_VARS["trid"];
if(! $trid) {
  $trid = $HTTP_GET_VARS["trid"];
}
$alid = $HTTP_POST_VARS["alid"];
if(! $alid) {
  $alid = $HTTP_GET_VARS["alid"];
}

// Set up filtering clause

$filter = "";
if($trid && $trid != "0") {
  $filter = $filter . " AND trid= " . $trid;
}
if($alid && $alid != "0") {
  $filter = $filter . " AND alid= " . $alid;
}

// Load up all information needed by this user

// Statistics
// Number of flights, total distance (mi), total duration (minutes)
$sql = "SELECT COUNT(*) AS count, SUM(distance) AS distance, SUM(TIME_TO_SEC(duration))/60 AS duration FROM flights where uid=" .$uid . " " . $filter;
$result = mysql_query($sql, $db);
if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  printf("%s,%s,%s\n", $row["count"], $row["distance"], $row["duration"]);
}

// List of all unique flights
$sql = "SELECT DISTINCT s.apid,s.x,s.y,d.apid,d.x,d.y,count(fid) AS times FROM flights AS f, airports AS s, airports AS d WHERE f.src_apid=s.apid AND f.dst_apid=d.apid AND uid=" . $uid . " " . $filter . " GROUP BY fid";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_NUM)) {
  if($first) {
    $first = false;
  } else {
    printf(":");
  }  
  printf ("%s,%s,%s,%s,%s,%s", $row[0], $row[1], $row[2], $row[3], $row[4], $row[5]);
}
printf ("\n");

// List of all airports
$sql = "SELECT DISTINCT a.apid,x,y,name,iata,icao,city,country,count(name) AS visits FROM flights AS f, airports AS a WHERE (f.src_apid=a.apid OR f.dst_apid=a.apid) AND uid=" . $uid . $filter . " GROUP BY name";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  if($first) {
    $first = false;
  } else {
    printf(":");
  }  
  printf ("%s,%s,%s,%s,%s,%s,%s,%s", $row["apid"], $row["x"], $row["y"], $row["name"], $row["iata"], $row["city"], $row["country"], $row["visits"]);
}

// When running for the first time, load up possible filter settings for this user
if($init == "true") {
  print("\n");

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
  $sql = "SELECT DISTINCT a.alid, name FROM airlines as a, flights as f WHERE a.alid=f.alid AND uid=" . $uid . " ORDER BY name";
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
}
?>
