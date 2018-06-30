<?php
include 'locale.php';
include 'db_pdo.php';
include 'helper.php';
include 'greatcircle.php';
include 'filter.php';

$apid = $_POST["apid"];
if(! $apid) {
  $apid = $_GET["apid"];
}
$alid = $_POST["alid"];
if(! $alid) {
  $alid = $_GET["alid"];
}

if(! $apid) {
  $param = $_POST["param"];
  if($param) {
    switch(strlen($param)) {
    case 2:
      $sql = "SELECT CONCAT('L', alid) AS apid FROM airlines WHERE iata=?";
      break;
    case 3:
      $sql = "SELECT apid FROM airports WHERE iata=?";
      break;
    case 4:
      $sql = "SELECT apid FROM airports WHERE icao=?";
      break;
    default:
      die('Error;Query ' . $param . ' not understood.  For airlines, please enter a 2-letter IATA code.  For airports, please enter a 3-letter IATA or 4-letter ICAO code.');
    }
    $sth = $dbh->prepare($sql);
    $sth->execute([$param]);
    if($row = $sth->fetch()) {
      $apid = $row["apid"];
    } else {
      die('Error;No match found for query ' . $param);
    }
  } else {
    die('Error;Airport or airline ID is mandatory');
  }
}

// $apid contains either mapped airport ID (no prefix) or the mapped airline ID (L + alid)
// $alid, if given, is an additional filter to airports only

if(substr($apid, 0, 1) == "L") {
  $type = "L";
  $apid = substr($apid, 1);
  $condParams = [$apid];
  if($alid) {
    $condition = "r.alid=?";
  } else {
    $condition  = "r.alid=? AND r.codeshare=''"; // exclude codeshares by default
  }
  $codeshare = "codeshare";
} else {
  $type = "A";
  $condParams = [$apid];
  $condition = "r.src_apid=?";
  if($alid) {
    $condParams[] = $alid;
    $condition .= " AND r.alid=?";
    $codeshare = "codeshare";
  } else {
    $codeshare = "'N'"; // never show dotted lines for airport route maps
  }
}

$map = "";

// Title for this airport route data plus count of routes
// (count = 0 when airport exists but has no routes)
if($type == "A") {
  if($alid) {
    $params = [$alid, $apid];
    $filter = " AND r.alid=?";
  } else {
    $params = [$apid];
    $filter = "";
  }

  $sql = "
    SELECT COUNT(src_apid) AS count, apid, x, y, name, iata, icao, city, country, timezone, dst
    FROM airports AS a
    LEFT OUTER JOIN routes AS r ON r.src_apid = a.apid $filter
    WHERE a.apid = ?
    GROUP BY src_apid, apid
  ";
  $sth = $dbh->prepare($sql);
  $sth->execute($params);
  if($row = $sth->fetch()) {
    printf ("%s;%s;%s (<b>%s</b>)<br><small>%s, %s<br>%s routes</small>\n", $apid, $row["count"], $row["name"], format_apcode($row), $row["city"], $row["country"], $row["count"]);
  } else {
    die('Error;No airport with ID ' . $apid . ' found');
  }
  if($row["count"] == 0) {
    // No routes, print this airport and abort
    printf("\n%s;%s;%s;%s;0;%s;N\n", format_apdata($row), $row["name"], $row["city"], $row["country"], format_airport($row));
    exit;
  }
} else {
  if($alid) {
    $filter = "";
  } else {
    $filter = " AND r.codeshare=''"; // by default, don't display codeshares
  }

  // Airline route map
  $sql = "SELECT COUNT(r.alid) AS count, country, name, iata, icao FROM airlines AS l LEFT OUTER JOIN routes AS r ON r.alid=l.alid $filter WHERE l.alid=? GROUP BY r.alid";
  $sth = $dbh->prepare($sql);
  $sth->execute([$apid]);
  if($row = $sth->fetch()) {
    printf ("%s;%s;%s (<b>%s</b>)<br><small>%s</small><br>%s routes\n", "L" . $apid, $row["count"], $row["name"], $row["iata"], $row["country"], $row["count"]);
  } else {
    die('Error;No airline with ID ' . $apid . ' found');
  }
  if($row["count"] == 0) {
    // No routes, abort
    printf("\n\n\n\n\n\n");
    exit;
  }
  $alname = $row["iata"];
}

// List of all flights FROM this airport
$sql = "
  SELECT DISTINCT s.apid, s.x, s.y, d.apid, d.x, d.y, count(rid), 0, $codeshare AS future, 'F' AS mode
  FROM routes AS r, airports AS s, airports AS d
  WHERE $condition AND r.src_apid = s.apid AND r.dst_apid = d.apid
  GROUP BY s.apid, d.apid, codeshare
";
$sth = $dbh->prepare($sql);
$sth->execute($condParams) or die ('Error;Database error.');
$first = true;
while ($row = $sth->fetch(PDO::FETCH_NUM)) {
  $row[7] = gcPointDistance(array("x" => $row[1], "y" => $row[2]),
			    array("x" => $row[4], "y" => $row[5]));
  if($first) {
    $first = false;
  } else {
    $map .= "\t";
  }  
  $map .= sprintf ("%s;%s;%s;%s;%s;%s;%s;%s;%s;%s", $row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8], $row[9]);
}
$map .= "\n";

// List of all airports with flights FROM this airport
if($type == "A") {
  $apcond = "(r.src_apid=a.apid OR r.dst_apid=a.apid)"; // include source airport
} else {
  $apcond = "r.src_apid=a.apid"; // prevent double-counting
}

// MIN(codeshare) returns '' as long as at least one route is not 'Y'!
$sql = "
  SELECT DISTINCT a.apid, x, y, name, iata, icao, city, country, timezone, dst, count(name) AS visits, MIN(codeshare) AS future
  FROM routes AS r, airports AS a
  WHERE $condition AND $apcond
  GROUP BY a.apid, name
  ORDER BY visits ASC
";
$sth = $dbh->prepare($sql);
$sth->execute($condParams) or die ('Error;Database error.');
$first = true;
while ($row = $sth->fetch()) {
  if($first) {
    $first = false;
  } else {
    $map .= "\t";
  }
  $map .= sprintf ("%s;%s;%s;%s;%s;%s;%s", format_apdata($row), $row["name"], $row["city"], $row["country"], $row["visits"], format_airport($row), $row["future"]);
}

// Trips always null
$map .= "\n\n";

// List of all airlines in this route map
if($type == "L") {
  // Special handling here: no "all" option, alid = 0 means exclude codeshares, alid != 0 means codeshares also
  $map .= sprintf("NOALL\t%s;%s\t", 0, $alname . _("-operated"));
  $map .= sprintf("%s;%s", $apid . "C", $alname . _(" and codeshares"));
 } else {
  // Note: Existing airline filter is purposely ignored here
  $sql = "SELECT DISTINCT a.alid, iata, icao, name FROM airlines as a, routes as r WHERE r.src_apid=? AND a.alid=r.alid ORDER BY a.alid, name;";
  $sth = $dbh->prepare($sql);
  $sth->execute([$apid]) or die ('Error;Database error.');
  $first = true;
  while ($row = $sth->fetch()) {
    if($first) {
      $first = false;
    } else {
      $map .= "\t";
    }  
    $map .= sprintf("%s;%s", $row["alid"], $row["name"]);
  }
}

// And years also null
print $map . "\n\n";

?>
