<?php
//
// Helper functions for filter handling
//

// Build a flight filter string for SQL SELECT
function getFilterString($vars) {
  $filter = "";
  $trid = $vars["trid"];
  $alid = $vars["alid"];
  $year = $vars["year"];
  $xkey = $vars["xkey"];
  $xvalue = $vars["xvalue"];

  if($trid && $trid != "0") {
    if($trid == "null") {
      $filter = $filter . " AND f.trid IS NULL";
    } else {
      $filter = $filter . " AND f.trid= " . mysql_real_escape_string($trid);
    }
  }
  if($alid && $alid != "0") {
    $filter = $filter . " AND f.alid=" . mysql_real_escape_string($alid);
  }
  if($year && $year != "0") {
    $filter = $filter . " AND YEAR(f.src_date)='" . mysql_real_escape_string($year) . "'";
  }
  if($xvalue && $xvalue != "") {
    switch($xkey) {
    case null:
    case "":
      break;
      
    case "class":
      $filter = $filter . " AND f.class='" . $xvalue . "'";
      break;
      
    case "distgt":
      $filter = $filter . " AND f.distance > " . $xvalue;
      break;
      
    case "distlt":
      $filter = $filter . " AND f.distance < " . $xvalue;
      break;

    case "mode":
      $filter = $filter . " AND f.mode='" . $xvalue . "'";
      break;
      
    case "note":
      $filter = $filter . " AND f.note LIKE '%" . $xvalue . "%'";
      break;
      
    case "reason":
      $filter = $filter . " AND f.reason='" . $xvalue . "'";
      break;
      
    case "reg":
      $filter = $filter . " AND f.registration LIKE '" . $xvalue . "%'";
      break;
    }
  }
  return $filter;
}

// Load up possible filter settings for this user
function loadFilter($db, $uid, $trid, $logged_in) {

  // Limit selections to a single trip?
  if($trid && $trid != "0") {
    if($trid == "null") {
      $filter = " AND trid IS NULL";
    } else {
      $filter = " AND trid=" . mysql_real_escape_string($trid);
    }
  } else {
    $filter = "";
  }

  // List of all trips
  if($logged_in == "demo") {
    $privacy = " AND public!='N'"; // filter out private trips
  } else {
    $privacy = "";
  }
  $sql = "SELECT * FROM trips WHERE uid=" . $uid . $privacy . " ORDER BY name";
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
  $sql = "SELECT DISTINCT a.alid, iata, icao, name FROM airlines as a, flights as f WHERE f.uid=" . $uid . $filter . " AND a.alid=f.alid ORDER BY name";
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
  printf ("\n");
  
  // List of all years
  $sql = "SELECT DISTINCT YEAR(src_date) AS year FROM flights WHERE uid=" . $uid . $filter . " AND YEAR(src_date) != '0' ORDER BY YEAR DESC";
  $result = mysql_query($sql, $db);
  $first = true;
  while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
    if($first) {
      $first = false;
    } else {
      printf("\t");
    }  
    printf ("%s;%s", $row["year"], $row["year"]);
  }
}
?>
