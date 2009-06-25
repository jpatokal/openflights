<?php
include 'locale.php';
include 'db.php';
include 'helper.php';
include 'greatcircle.php';
include 'filter.php';

$apid = $HTTP_POST_VARS["apid"];
if(! $apid) {
  $apid = $HTTP_GET_VARS["apid"];
}
$alid = $HTTP_POST_VARS["alid"];
if(! $alid) {
  $alid = $HTTP_GET_VARS["alid"];
}
if(! $apid && ! $alid) {
  $param = $HTTP_POST_VARS["param"];
  if($param) {
    $sql = "SELECT apid FROM airports WHERE iata='" . mysql_real_escape_string($param) . "'";
    $result = mysql_query($sql, $db);
    if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
      $apid = $row["apid"];
    } else {
      die('Error;Airport code ' . $param . ' not found');
    }
  } else {
    die('Error;Airport or airline ID is mandatory');
  }
}

if($apid) {
  $apid = mysql_real_escape_string($apid);
  $condition = "r.src_apid=$apid";
}
if($alid) {
  $alid = mysql_real_escape_string($alid);
  $condition = "r.alid=$alid";
}

// Title for this airport route data plus count of routes
// (count = 0 when airport exists but has no routes)
$sql = "SELECT COUNT(src_apid) AS count, apid, x, y, name, iata, icao, city, country, timezone, dst FROM airports AS a LEFT OUTER JOIN routes AS r ON r.src_apid=a.apid WHERE a.apid=$apid GROUP BY src_apid";
$result = mysql_query($sql, $db);
if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  printf ("%s;%s;%s (<b>%s</b>)<br><small>%s, %s</small><br>%s routes\n", $apid, $row["count"], $row["name"], format_apcode($row), $row["city"], $row["country"], $row["count"]);
} else {
  die('Error;No airport with ID $apid found');
}
if($row["count"] == 0) {
  // No routes, print this airport and abort
  printf("\n%s;%s;%s;%s;0;%s;N\n", format_apdata($row), $row["name"], $row["city"], $row["country"], format_airport($row));
  exit;
}

// List of all flights FROM this airport
$sql = "SELECT DISTINCT s.apid,s.x,s.y,d.apid,d.x,d.y,count(rid),0,'N' AS future,'F' AS mode FROM routes AS r, airports AS s, airports AS d WHERE $condition AND r.src_apid=s.apid AND r.dst_apid=d.apid GROUP BY s.apid,d.apid";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_NUM)) {
  $row[7] = gcPointDistance(array("x" => $row[1], "y" => $row[2]),
			    array("x" => $row[4], "y" => $row[5]));
  if($first) {
    $first = false;
  } else {
    printf("\t");
  }  
  printf ("%s;%s;%s;%s;%s;%s;%s;%s;%s;%s", $row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8], $row[9]);
}
printf ("\n");

// List of all airports with flights FROM this airport
$sql = "SELECT DISTINCT a.apid,x,y,name,iata,icao,city,country,timezone,dst,count(name) AS visits,'N' AS future FROM routes AS r, airports AS a WHERE $condition AND (r.src_apid=a.apid OR r.dst_apid=a.apid) GROUP BY name ORDER BY visits ASC";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  if($first) {
    $first = false;
  } else {
    printf("\t");
  }
  printf ("%s;%s;%s;%s;%s;%s;%s", format_apdata($row), $row["name"], $row["city"], $row["country"], $row["visits"], format_airport($row), $row["future"]);
}
?>
