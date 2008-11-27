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
if(! $user) {
  $user = $HTTP_GET_VARS["user"];
}

$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);

$init = $HTTP_POST_VARS["param"];
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
$year = $HTTP_POST_VARS["year"];
if(! $year) {
  $year = $HTTP_GET_VARS["year"];
}

// Set up filtering clause and verify that this trip and user are public
$filter = "";
$public = "O"; // default to full access

if($trid && $trid != "0") {
  // Verify that we're allowed to access this trip
  // NB: a "trid" filter can mean logged-in *and* filtered, or not logged in!
  $sql = "SELECT * FROM trips WHERE trid=" . mysql_real_escape_string($trid);
  $result = mysql_query($sql, $db);
  if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
    if($row["uid"] != $uid and $row["public"] == "N") {
      die('Error;This trip is not public.');
    } else {
      $uid = $row["uid"];
      $public = $row["public"];
      if($public == "O") {
	$_SESSION["openuid"] = $uid;
	$_SESSION["opentrid"] = $trid;
      }
    }
  } else {
    die('Error;No such trip.');
  }
  $filter = $filter . " AND trid= " . mysql_real_escape_string($trid);
}

if($user && $user != "0") {
  // Verify that we're allowed to view this user's flights
  // if $user is set, we are never logged in
  $sql = "SELECT uid,public FROM users WHERE name='" . mysql_real_escape_string($user) . "'";
  $result = mysql_query($sql, $db);
  if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
    if($row["public"] == "N") {
      die('Error;This user\'s flights are not public.');
    } else {
      $uid = $row["uid"];
      $public = $row["public"];
      if($public == "O") {
	$_SESSION["openuid"] = $uid;
	$_SESSION["opentrid"] = null;
      }
    }
  } else {
    die('Error;No such user.');
  }
}

if($alid && $alid != "0") {
  $filter = $filter . " AND alid=" . mysql_real_escape_string($alid);
}
if($year && $year != "0") {
  $filter = $filter . " AND YEAR(src_time)='" . mysql_real_escape_string($year) . "'";
}

// Load up all information needed by this user

// Statistics
// Number of flights, total distance (mi), total duration (minutes), public/open
$sql = "SELECT COUNT(*) AS count, SUM(distance) AS distance, SUM(TIME_TO_SEC(duration))/60 AS duration FROM flights where uid=" . $uid . " " . $filter;
$result = mysql_query($sql, $db);
if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  printf("%s;%s;%s;%s\n", $row["count"], $row["distance"], $row["duration"], $public);
}

// List of all flights (unique by airport pair)
$sql = "SELECT DISTINCT s.apid,s.x,s.y,d.apid,d.x,d.y,count(fid),distance AS times FROM flights AS f, airports AS s, airports AS d WHERE f.src_apid=s.apid AND f.dst_apid=d.apid AND f.uid=" . $uid . " " . $filter . " GROUP BY s.apid,d.apid";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_NUM)) {
  if($first) {
    $first = false;
  } else {
    printf(":");
  }  
  printf ("%s;%s;%s;%s;%s;%s;%s;%s", $row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7]);
}
printf ("\n");

// List of all airports
$sql = "SELECT DISTINCT a.apid,x,y,name,iata,icao,city,country,count(name) AS visits FROM flights AS f, airports AS a WHERE (f.src_apid=a.apid OR f.dst_apid=a.apid) AND f.uid=" . $uid . $filter . " GROUP BY name ORDER BY visits ASC";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  if($first) {
    $first = false;
  } else {
    printf(":");
  }
  $code = format_apcode($row);
  printf ("%s;%s;%s;%s;%s;%s;%s;%s;%s", $row["apid"], $row["x"], $row["y"], $row["name"], $code, $row["city"], $row["country"], $row["visits"], format_airport($row));
}

// When running for the first time, load up possible filter settings for this user
if($init == "true") {
  print("\n");
  loadFilter($db, $uid, $trid);
}
?>
