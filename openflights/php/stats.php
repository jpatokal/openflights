<?php
include 'locale.php';
include 'db.php';
include 'helper.php';
include 'filter.php';

$uid = $_SESSION["uid"];
$public = "O"; // by default...
if(!$uid or empty($uid)) {
  // If not logged in, default to demo mode
  $uid = 1;
}

// This applies only when viewing another's flights
$user = $HTTP_POST_VARS["user"];
if(! $user) {
  $user = $HTTP_GET_VARS["user"];
}
$trid = $HTTP_POST_VARS["trid"];

// Verify that this trip and user are public
$filter = "";

if($uid == 1 && $trid && $trid != "0") {
  // Verify that we're allowed to access this trip
  $sql = "SELECT * FROM trips WHERE trid=" . mysql_real_escape_string($trid);
  $result = mysql_query($sql, $db);
  if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
    $public = $row["public"];
    if($row["uid"] != $uid and $public == "N") {
      die('Error;' . _("This trip is not public."));
    } else {
      $uid = $row["uid"];
    }
  } else {
    die('Error;' . _("Trip not found."));
  }
}
if($user && $user != "0") {
  // Verify that we're allowed to view this user's flights
  $sql = "SELECT uid,public FROM users WHERE name='" . mysql_real_escape_string($user) . "'";
  $result = mysql_query($sql, $db);
  if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
    $public = $row["public"];
    if($public == "N") {
      die('Error;' . _("This user's flights are not public."));
    } else {
      $uid = $row["uid"];
    }
  } else {
    die('Error;' . _("User not found."));
  }
}
$filter = "f.uid=" . $uid . getFilterString($HTTP_POST_VARS);
$array = array();

// Convert mi to km if units=K
$units = $_SESSION["units"];
if($units == "K") {
  $unit = _("km");
  $multiplier = "* " . $KMPERMILE;
} else {
  $unit = _("mi");
  $multiplier = "";
}

// unique airports, and countries
$sql = "SELECT COUNT(DISTINCT a.apid) AS num_airports, COUNT(DISTINCT a.country) AS num_countries FROM flights AS f,airports AS a WHERE (f.src_apid=a.apid OR f.dst_apid=a.apid) AND " . $filter;
$result = mysql_query($sql, $db);
if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  $array += $row;
}

// unique airlines, unique planes, total distance (mi), average distance (localized), average duration
$sql = "SELECT COUNT(DISTINCT alid) AS num_airlines, COUNT(DISTINCT plid) AS num_planes, SUM(distance) AS distance, ROUND(AVG(distance) $multiplier) AS avg_distance, TIME_FORMAT(SEC_TO_TIME(SUM(TIME_TO_SEC(duration))/COUNT(duration)), '%H:%i') AS avg_duration FROM flights AS f WHERE " . $filter;
$result = mysql_query($sql, $db);
if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  $array += $row;
}
$array["avg_distance"] .= " " . $unit;
$array["localedist"] = round($array["distance"] * ($units == "K" ? $KMPERMILE : 1)) . " " . $unit;
print json_encode($array) . "\n";

// longest and shortest
// 0 desc, 1 distance, 2 duration, 3 src_iata, 4 src_icao, 5 src_apid, 6 dst_iata, 7 dst_icao, 8 dst_apid
$sql = sprintf("(SELECT '%s',ROUND(f.distance %s) AS distance,DATE_FORMAT(duration, '%%H:%%i') AS duration,s.iata,s.icao,s.apid,d.iata,d.icao,d.apid FROM flights AS f,airports AS s,airports AS d WHERE f.src_apid=s.apid AND f.dst_apid=d.apid AND " . $filter . " ORDER BY distance DESC LIMIT 1) UNION " .
	       "(SELECT '%s',ROUND(f.distance %s) AS distance,DATE_FORMAT(duration, '%%H:%%i') AS duration,s.iata,s.icao,s.apid,d.iata,d.icao,d.apid FROM flights AS f,airports AS s,airports AS d WHERE f.src_apid=s.apid AND f.dst_apid=d.apid AND " . $filter . " ORDER BY distance ASC LIMIT 1)",
	       _("Longest"), $multiplier,
	       _("Shortest"), $multiplier);
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_NUM)) {
  if($first) {
    $first = false;
  } else {
    printf(";");
  }
  $src_code = format_apcode2($row[3], $row[4]);
  $dst_code = format_apcode2($row[6], $row[7]);
  printf ("%s,%s %s,%s,%s,%s,%s,%s", $row[0], $row[1], $unit, $row[2], $src_code, $row[5], $dst_code, $row[8]);
}
printf ("\n");

// North, South, West, East
// 0 desc, 1 iata, 2 icao, 3 apid, 4 x, 5 y
$sql = sprintf("(SELECT '%s',iata,icao,apid,x,y FROM airports WHERE y=(SELECT MAX(y) FROM airports AS a, flights AS f WHERE (f.src_apid=a.apid OR f.dst_apid=a.apid) AND " . $filter . ")) UNION " .
	       "(SELECT '%s',iata,icao,apid,x,y FROM airports WHERE y=(SELECT MIN(y) FROM airports AS a, flights AS f WHERE (f.src_apid=a.apid OR f.dst_apid=a.apid) AND " . $filter . ")) UNION " .
	       "(SELECT '%s',iata,icao,apid,x,y FROM airports WHERE x=(SELECT MIN(x) FROM airports AS a, flights AS f WHERE (f.src_apid=a.apid OR f.dst_apid=a.apid) AND " . $filter . ")) UNION " .
	       "(SELECT '%s',iata,icao,apid,x,y FROM airports WHERE x=(SELECT MAX(x) FROM airports AS a, flights AS f WHERE (f.src_apid=a.apid OR f.dst_apid=a.apid) AND " . $filter . "))",
	       addslashes(_("Northernmost")), addslashes(_("Southernmost")),
	       addslashes(_("Westernmost")), addslashes(_("Easternmost")));

$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_NUM)) {
  if($first) {
    $first = false;
  } else {
    printf(":");
  }
  $code = format_apcode2($row[1], $row[2]);
  printf ("%s,%s,%s,%s,%s", $row[0], $code, $row[3], $row[4], $row[5]);
}
printf ("\n");

// Censor remaining info unless in full-public mode
if($public != "O") {
  print "\n\n\n";
  exit;
 }

// Classes
$sql = "SELECT DISTINCT class,COUNT(*) FROM flights AS f WHERE " . $filter . " AND class != '' GROUP BY class ORDER BY class";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_NUM)) {
  if($first) {
    $first = false;
  } else {
    printf(":");
  }
  printf ("%s,%s", $row[0], $row[1]);
}
printf ("\n");

// Reason
$sql = "SELECT DISTINCT reason,COUNT(*) FROM flights AS f WHERE " . $filter . " AND reason != '' GROUP BY reason ORDER BY reason";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_NUM)) {
  if($first) {
    $first = false;
  } else {
    printf(":");
  }
  printf ("%s,%s", $row[0], $row[1]);
}
printf ("\n");

// Reason
$sql = "SELECT DISTINCT seat_type,COUNT(*) FROM flights AS f WHERE " . $filter . " AND seat_type != '' GROUP BY seat_type ORDER BY seat_type";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_NUM)) {
  if($first) {
    $first = false;
  } else {
    printf(":");
  }
  printf ("%s,%s", $row[0], $row[1]);
}
printf ("\n");

// Mode
$sql = "SELECT DISTINCT mode,COUNT(*) FROM flights AS f WHERE " . $filter . " GROUP BY mode ORDER BY mode";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_NUM)) {
  if($first) {
    $first = false;
  } else {
    printf(":");
  }
  printf ("%s,%s", $row[0], $row[1]);
}
printf ("\n");

?>
