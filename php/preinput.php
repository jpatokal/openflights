<?php
session_start();
header("Content-type: text/html; charset=iso-8859-1");
$uid = $_SESSION["uid"];
if(!$uid or empty($uid)) {
  printf("Not logged in, aborting");
  exit;
}

include 'helper.php';

$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);

//
// List all of this user's "X" so we can populate the select boxes
//

// List of this user's airports
$sql = "SELECT DISTINCT a.apid,a.name,a.iata,a.icao,a.city,a.country,a.x,a.y,a.iata = '' AS isnull FROM flights AS f,airports AS a WHERE f.uid=" . $uid . " AND (a.apid=f.src_apid OR a.apid=f.dst_apid) ORDER BY isnull,a.iata";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  if($first) {
    $first = false;
  } else {
    printf("\t");
  }
  $code = format_apcode($row);
  printf ("%s:%s:%s:%s;%s", $code, $row["apid"], $row["x"], $row["y"], format_airport($row));
}
printf ("\n");

// List of this user's airlines
// NB: private flights (alid 1) handled separately
$sql = "SELECT DISTINCT a.alid,a.name,a.iata FROM flights AS f,airlines AS a WHERE f.uid=" . $uid . " AND a.alid=f.alid AND a.alid != 1 ORDER BY a.iata";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  if($first) {
    $first = false;
  } else {
    printf("\t");
  }  
  printf ("%s;%s", $row["alid"] . ":" . $row["iata"], format_airline($row));
}
if(! $first) printf("\t");
printf ("1:-;Private flight\n");

// List of this user's planes
$sql = "SELECT DISTINCT p.plid, p.name FROM flights AS f,planes AS p WHERE f.uid=" . $uid . " AND p.plid=f.plid ORDER BY NAME";
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
printf ("\n");

// List of this user's trips
$sql = "SELECT DISTINCT t.trid, t.name FROM trips AS t WHERE uid=" . $uid . " ORDER BY NAME";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  if($first) {
    $first = false;
  } else {
    printf("\t");
  }  
  printf ("%s;%s", $row["trid"], $row["name"]);
}
