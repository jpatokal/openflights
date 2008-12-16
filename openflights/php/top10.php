<?php
session_start();
header("Content-type: text/html; charset=iso-8859-1");

include 'helper.php';
include 'filter.php';

$uid = $_SESSION["uid"];
if(!$uid or empty($uid)) {
  // If not logged in, default to demo mode
  $uid = 1;
}
// This applies only when viewing another's flights
$user = $HTTP_POST_VARS["user"];
$trid = $HTTP_POST_VARS["trid"];

$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);

// Verify that this trip and user are public
if($uid == 1 && $trid && $trid != "0") {
  // Verify that we're allowed to access this trip
  $sql = "SELECT * FROM trips WHERE trid=" . mysql_real_escape_string($trid);
  $result = mysql_query($sql, $db);
  if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
    if($row["uid"] != $uid and $row["public"] == "N") {
      die('Error;This trip is not public.');
    } else {
      $uid = $row["uid"];
    }
  }
}
if($user && $user != "0") {
  // Verify that we're allowed to view this user's flights
  $sql = "SELECT uid,public FROM users WHERE name='" . mysql_real_escape_string($user) . "'";
  $result = mysql_query($sql, $db);
  if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
    if($row["public"] == "N") {
      die('Error;This user\'s flights are not public.');
    } else {
      $uid = $row["uid"];
    }
  }
}
$filter = getFilterString($HTTP_POST_VARS);

// List top 10 routes
$sql = "SELECT DISTINCT s.iata AS siata,s.icao AS sicao,s.apid AS sapid,d.iata AS diata,d.icao AS dicao,d.apid AS dapid,count(fid) AS times FROM flights AS f, airports AS s, airports AS d WHERE f.src_apid=s.apid AND f.dst_apid=d.apid AND f.uid=" . $uid . " " . $filter . " GROUP BY s.apid,d.apid ORDER BY times DESC LIMIT 10";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  if($first) {
    $first = false;
  } else {
    printf(":");
  }
  $src = format_apcode2($row["siata"], $row["sicao"]);
  $dst = format_apcode2($row["diata"], $row["dicao"]);
  printf ("%s,%s,%s,%s,%s", $src, $row["sapid"], $dst, $row["dapid"], $row["times"]);
}
printf ("\n");

// List top 10 airports
$sql = "select a.name, a.iata, a.icao, count(fid) as count, a.apid from airports as a, flights as f where f.uid=" . $uid . " and (f.src_apid=a.apid or f.dst_apid=a.apid) " . $filter . " group by a.apid order by count desc limit 10";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  if($first) {
    $first = false;
  } else {
    printf(":");
  }
  $code = format_apcode($row);
  printf ("%s,%s,%s,%s", $row["name"], $code, $row["count"], $row["apid"]);
}
printf ("\n");

// List top 10 airlines
$sql = "select a.name, count(fid) as count, a.alid from airlines as a, flights as f where f.uid=" . $uid . " and f.alid=a.alid " . $filter . " group by f.alid order by count desc limit 10";
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
$sql = "select p.name, count(fid) as count from planes as p, flights as f where f.uid=" . $uid . " and p.plid=f.plid " . $filter . " group by f.plid order by count desc limit 10";
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
