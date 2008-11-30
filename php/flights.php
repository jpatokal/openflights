<?php
session_start();
$export = $HTTP_GET_VARS["export"];
if($export) {
  header("Content-type: text/csv; charset=iso-8859-1");
  header("Content-disposition: attachment; filename=\"openflights-$export-" . date("Y-m-d").".csv\"");
  if($export == "export") {
    $trid = $HTTP_GET_VARS["trid"];
    $alid = $HTTP_GET_VARS["alid"];
    $year = $HTTP_GET_VARS["year"];
  }
  // else export everything unfiltered
 } else {
  header("Content-type: text/html; charset=iso-8859-1");
  $apid = $HTTP_POST_VARS["id"];
  $trid = $HTTP_POST_VARS["trid"];
  $alid = $HTTP_POST_VARS["alid"];
  $fid = $HTTP_POST_VARS["fid"];
  $user = $HTTP_POST_VARS["user"];
  $year = $HTTP_POST_VARS["year"];
 }

include 'helper.php';

$uid = $_SESSION["uid"];
// Logged in?
if(!$uid or empty($uid)) {

  // Viewing an "open" user's flights, or an "open" flight?
  // (will be previously set in map.php)
  $uid = $_SESSION["openuid"]; 
  if($uid && !empty($uid)) {
    // Yes we are, so check if we're limited to a single trip
    $openTrid = $_SESSION["opentrid"];
    if($openTrid) {
      if($openTrid == $trid) {
	// This trip's OK
      } else {
	// Naughty naughty, back to demo mode
	$uid = 1;
      }
    } else {
      // No limit, do nothing
    }
  } else {
    // Nope, default to demo mode
    $uid = 1;
  }
}

$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);

// List of all this user's flights
$sql = "SELECT s.iata AS src_iata,s.icao AS src_icao,s.apid AS src_apid,d.iata AS dst_iata,d.icao AS dst_icao,d.apid AS dst_apid,f.code,DATE(f.src_time) as src_date,distance,DATE_FORMAT(duration, '%H:%i') AS duration,seat,seat_type,class,reason,p.name,registration,fid,l.alid,note,trid,opp,f.plid,l.iata AS al_iata,l.icao AS al_icao,l.name AS al_name FROM airports AS s,airports AS d, airlines AS l,flights AS f LEFT JOIN planes AS p ON f.plid=p.plid WHERE f.uid=" . $uid . " AND f.src_apid=s.apid AND f.dst_apid=d.apid AND f.alid=l.alid";

// ...filtered by airport (optional)
if($apid && $apid != 0) {
  $sql = $sql . " AND (s.apid=" . mysql_real_escape_string($apid) . " OR d.apid=" . mysql_real_escape_string($apid) . ")";
}

// Add filters, if any
if($trid && $trid != "0") {
  $sql = $sql . " AND trid= " . mysql_real_escape_string($trid);
}
if($alid && $alid != "0") {
  $sql = $sql . " AND l.alid= " . mysql_real_escape_string($alid);
}
if($fid && $fid != "0") {
  $sql = $sql . " AND fid= " . mysql_real_escape_string($fid);
}
if($year && $year != "0") {
  $sql = $sql . " AND YEAR(src_time)='" . mysql_real_escape_string($year) . "'";
}

// And sort order
$sql = $sql . " ORDER BY src_date";

// Execute!
$result = mysql_query($sql, $db);
$first = true;

if($export) {
  printf("Date,From,To,Flight_Number,Airline,Distance,Duration,Seat,Seat_Type,Class,Reason,Plane,Registration,Trip,Note,From_Code,To_Code,Airline_Code,Plane_Code\r\n");
}
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  if($first) {
    $first = false;
  } else {
    if($export) {
      printf("\r\n");
    } else {
      printf("\n");
    }
  }
  $src_apid = $row["src_apid"];
  $src_code = format_apcode2($row["src_iata"], $row["src_icao"]);

  $dst_apid = $row["dst_apid"];
  $dst_code = format_apcode2($row["dst_iata"], $row["dst_icao"]);

  $al_code = format_alcode($row["al_iata"], $row["al_icao"]);

  if($row["opp"] == 'Y') {
    $tmp = $src_apid;
    $src_apid = $dst_apid;
    $dst_apid = $tmp;

    $tmp = $src_code;
    $src_code = $dst_code;
    $dst_code = $tmp;
  }

  if($export) {
    $note = $row["note"];
    if(strpos($note, ",") !== false) {
      $note = "\"" . $note . "\"";
    }
    printf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s",
	   $row["src_date"], $src_code, $dst_code, $row["code"], $row["al_name"],
	   $row["distance"], $row["duration"], $row["seat"], $row["seat_type"], $row["class"], $row["reason"],
	   $row["name"], $row["registration"], $row["trid"], $note,
	   $src_apid, $dst_apid, $row["alid"], $row["plid"]);
  } else {
    printf ("%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s", $src_code, $src_apid, $dst_code, $dst_apid, $row["code"], $row["src_date"], $row["distance"], $row["duration"], $row["seat"], $row["seat_type"], $row["class"], $row["reason"], $row["fid"], $row["name"], $row["registration"], $row["alid"], $row["note"], $row["trid"], $row["plid"], $al_code);
  }
}
?>
