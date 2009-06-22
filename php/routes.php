<?php
include 'locale.php';
include 'db.php';
include 'helper.php';
include 'filter.php';

$apid = $HTTP_POST_VARS["apid"];
if(! $apid) {
  $apid = $HTTP_GET_VARS["apid"];
}
if(! $apid) {
  die('Error;Airport ID is mandatory');
}

// List of all flights from this airport
$sql = "SELECT DISTINCT s.apid,s.x,s.y,d.apid,d.x,d.y,count(rid),0,'N' AS future,'F' AS mode FROM routes AS r, airports AS s, airports AS d WHERE (r.src_apid=$apid OR r.dst_apid=$apid) AND r.src_apid=s.apid AND r.dst_apid=d.apid GROUP BY s.apid,d.apid";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_NUM)) {
  if($first) {
    $first = false;
  } else {
    printf("\t");
  }  
  printf ("%s;%s;%s;%s;%s;%s;%s;%s;%s;%s", $row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8], $row[9]);
}
printf ("\n");

// List of all airports with flights from this airport
$sql = "SELECT DISTINCT a.apid,x,y,name,iata,icao,city,country,timezone,dst,count(name) AS visits,'N' AS future FROM routes AS r, airports AS a WHERE (r.src_apid=$apid OR r.dst_apid=$apid) AND (r.src_apid=a.apid OR r.dst_apid=a.apid) GROUP BY name ORDER BY visits ASC";
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
