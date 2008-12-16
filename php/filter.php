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
    $filter = $filter . " AND YEAR(f.src_time)='" . mysql_real_escape_string($year) . "'";
  }

  return $filter;
}

// Load up possible filter settings for this user
function loadFilter($db, $uid, $trid) {

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
  $sql = "SELECT * FROM trips WHERE uid=" . $uid . " ORDER BY name";
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
  $sql = "SELECT DISTINCT YEAR(src_time) AS year FROM flights WHERE uid=" . $uid . $filter . " AND YEAR(src_time) != '0' ORDER BY YEAR DESC";
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
