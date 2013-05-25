<?php
session_start();
$uid = $_SESSION["uid"];
$export = $_GET["export"];
if($export) {
  if(!$uid or empty($uid)) {
    exit("You must be logged in to export.");
  }
  if($export == "export" || $export == "backup") {
    header("Content-type: text/csv; charset=utf-8");
    header("Content-disposition: attachment; filename=\"openflights-$export-" . date("Y-m-d").".csv\"");
  }
  if($export == "export" || $export == "gcmap") {
    $trid = $_GET["trid"];
    $alid = $_GET["alid"];
    $year = $_GET["year"];
    $apid = $_GET["id"];
  }
  // else export everything unfiltered
 } else {
  header("Content-type: text/html; charset=utf-8");

  $apid = $_POST["id"];
  if(! $apid) {
    $apid = $_GET["id"];
  }
  $trid = $_POST["trid"];
  $alid = $_POST["alid"];
  $fid = $_POST["fid"];
  $user = $_POST["user"];
  $year = $_POST["year"];
 }

include 'helper.php';
include 'filter.php';
include 'db.php';
include 'greatcircle.php';

$units = $_SESSION["units"];

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

// Special handling of "route" apids in form R<apid>,<coreid>
// <apid> is user selection, <coreid> is ID of airport map is centered around
$type = substr($apid, 0, 1);
if($type == "R" || $type == "L") {
  $route = true;
  $ids = explode(',', substr($apid, 1));
  $apid = $ids[0];
  $coreid = $ids[1];
  if($type == "L") {
    if($coreid == "") {
      $match = "r.alid=$apid"; // all routes on $alid
    } else {
      $match = "r.src_apid=$coreid AND r.alid=$apid"; // flight from $coreid on $alid only
    }
  } else {
    if($apid == $coreid) {
      $match = "r.src_apid=$apid"; // all flights from $apid
    } else {
      $match = "r.src_apid=$coreid AND r.dst_apid=$apid"; // flight from $coreid to $apid only
    }
    // Airline filter on top of airport
    if($alid) {
      $match .= " AND r.alid=$alid";
    }
  }
  $sql = "SELECT s.x AS sx,s.y AS sy,s.iata AS src_iata,s.icao AS src_icao,s.apid AS src_apid,d.x AS dx,d.y AS dy,d.iata AS dst_iata,d.icao AS dst_icao,d.apid AS dst_apid,l.iata as code, '-' as src_date, '-' as src_time, '-' as distance, '-:-' AS duration, '' as seat, '' as seat_type, '' as class, '' as reason, r.equipment AS name, '' as registration,rid AS fid,l.alid,'' AS note,NULL as trid,'N' AS opp,NULL as plid,l.iata AS al_iata,l.icao AS al_icao,l.name AS al_name,'F' AS mode,codeshare,stops FROM airports AS s,airports AS d, airlines AS l,routes AS r WHERE $match AND r.src_apid=s.apid AND r.dst_apid=d.apid AND r.alid=l.alid";

} else {
  // List of all this user's flights
  $sql = "SELECT s.iata AS src_iata,s.icao AS src_icao,s.apid AS src_apid,d.iata AS dst_iata,d.icao AS dst_icao,d.apid AS dst_apid,f.code,f.src_date,src_time,distance,DATE_FORMAT(duration, '%H:%i') AS duration,seat,seat_type,class,reason,p.name,registration,fid,l.alid,note,trid,opp,f.plid,l.iata AS al_iata,l.icao AS al_icao,l.name AS al_name,f.mode AS mode FROM airports AS s,airports AS d, airlines AS l,flights AS f LEFT JOIN planes AS p ON f.plid=p.plid WHERE f.uid=" . $uid . " AND f.src_apid=s.apid AND f.dst_apid=d.apid AND f.alid=l.alid";
  
  // ...filtered by airport (optional)
  if($apid && $apid != 0) {
    $sql = $sql . " AND (s.apid=" . mysql_real_escape_string($apid) . " OR d.apid=" . mysql_real_escape_string($apid) . ")";
  }
}

// Add filters, if any
switch($export) {
 case "export":
 case "gcmap":
   // Full filter only for user flight searches
   if(! $route) {
     $sql = $sql . getFilterString($_GET);
   }
   break;

 case "backup":
   // do nothing;
   break;

 default:
   // Full filter only for user flight searches
   if(! $route) {
     $sql = $sql . getFilterString($_POST);
   }
   break;
}
if($fid && $fid != "0") {
  $sql = $sql . " AND fid= " . mysql_real_escape_string($fid);
}

// And sort order
if($route) {
  if($type == "R") {
    $sql .= " ORDER BY d.iata ASC";
  } else {
    $sql .= " ORDER BY s.iata,d.iata ASC";
  }
} else {
  $sql .= " ORDER BY src_date DESC, src_time DESC";
}

// Execute!
$result = mysql_query($sql, $db) or die ('Error;Query ' . print_r($_GET, true) . ' caused database error ' . $sql . ', ' . mysql_error());
$first = true;

if($export == "export" || $export == "backup") {
  // Start with byte-order mark to try to clue Excel into realizing that this is UTF-8
  print "\xEF\xBB\xBFDate,From,To,Flight_Number,Airline,Distance,Duration,Seat,Seat_Type,Class,Reason,Plane,Registration,Trip,Note,From_OID,To_OID,Airline_OID,Plane_OID\r\n";
}
$gcmap_city_pairs = '';	// list of city pairs when doing gcmap export.
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  $note = $row["note"];

  if($route) {
    $row["distance"] = gcPointDistance(array("x" => $row["sx"], "y" => $row["sy"]),
				       array("x" => $row["dx"], "y" => $row["dy"]));
    $row["duration"] = gcDuration($row["distance"]);
    $row["code"] = $row["al_name"] . " (" . $row["code"] . ")";
    $note = "";
    if($row["stops"] == "0") {
      $note = "Direct";
    } else {
      $note = $row["stops"] . " stops";
    }
    if($row["codeshare"] == "Y") {
      $note = "Codeshare";
    }
  }

  if($first) {
    $first = false;
  } else {
    if($export == "export" || $export == "backup") {
      printf("\r\n");
    } else if ($export == "gcmap") {
    } else {
      printf("\n");
    }
  }
  $src_apid = $row["src_apid"];
  $src_code = format_apcode2($row["src_iata"], $row["src_icao"]);

  $dst_apid = $row["dst_apid"];
  $dst_code = format_apcode2($row["dst_iata"], $row["dst_icao"]);

  $al_code = format_alcode($row["al_iata"], $row["al_icao"], $row["mode"]);

  if($row["opp"] == 'Y') {
    $tmp = $src_apid;
    $src_apid = $dst_apid;
    $dst_apid = $tmp;

    $tmp = $src_code;
    $src_code = $dst_code;
    $dst_code = $tmp;
  }

  if($export == "export" || $export == "backup") {
    $note = "\"" . $note . "\"";
    $src_time = $row["src_time"];
    // Pad time with space if it's known
    if($src_time) {
      $src_time = " " . $src_time;
    } else {
      $src_time = "";
    }
    printf("%s%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s",
	   $row["src_date"], $src_time, $src_code, $dst_code, $row["code"], $row["al_name"],
	   $row["distance"], $row["duration"], $row["seat"], $row["seat_type"], $row["class"], $row["reason"],
	   $row["name"], $row["registration"], $row["trid"], $note,
	   $src_apid, $dst_apid, $row["alid"], $row["plid"]);
  } else if($export == "gcmap") {
    if(!empty($gcmap_city_pairs))
      $gcmap_city_pairs .= ',';
    $gcmap_city_pairs .= urlencode($src_code . '-' . $dst_code);
  } else {
    // Filter out any carriage returns or tabs
    $note = str_replace(array("\n", "\r", "\t"), "", $note);

    // Convert mi to km if units=K *and* we're not loading a single flight
    if($units == "K" && (!$fid || $fid == "0")) {
      $row["distance"] = round($row["distance"] * $KMPERMILE);
    }

    printf ("%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s", $src_code, $src_apid, $dst_code, $dst_apid, $row["code"], $row["src_date"], $row["distance"], $row["duration"], $row["seat"], $row["seat_type"], $row["class"], $row["reason"], $row["fid"], $row["name"], $row["registration"], $row["alid"], $note, $row["trid"], $row["plid"], $al_code, $row["src_time"], $row["mode"]);
  }
}

if($export == "gcmap") {
  // Output the redirect URL.
  header("Location: http://www.gcmap.com/mapui?P=" . $gcmap_city_pairs . "&MS=bm");
}
?>
