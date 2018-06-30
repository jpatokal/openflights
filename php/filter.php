<?php
//
// Helper functions for filter handling
//

// Build a flight filter string for SQL SELECT
function getFilterString($dbh, $vars) {
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
      $filter = $filter . " AND f.trid= " . $dbh->quote($trid);
    }
  }
  if($alid && $alid != "0") {
    $filter = $filter . " AND f.alid=" . $dbh->quote($alid);
  }
  if($year && $year != "0") {
    $filter = $filter . " AND YEAR(f.src_date)=" . $dbh->quote($year);
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

/**
 * Load up possible filter settings for this user
 *
 * @param $dbh PDO OpenFlights DB handler
 * @param $uid string User ID
 * @param $trid string Trip ID
 * @param $logged_in string Username if signed in
 */
function loadFilter($dbh, $uid, $trid, $logged_in) {

  // Limit selections to a single trip?
  $params = [$uid];
  if($trid && $trid != "0") {
    if($trid == "null") {
      $filter = " AND trid IS NULL";
    } else {
      $filter = " AND trid=?";
      $params[] = $trid;
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
  $sth = $dbh->prepare("SELECT * FROM trips WHERE uid=?" . $privacy . " ORDER BY name");
  $sth->execute([$uid]);
  $first = true;
  while ($row = $sth->fetch()) {
    if($first) {
      $first = false;
    } else {
      printf("\t");
    }  
    printf ("%s;%s;%s", $row["trid"], $row["name"], $row["url"]);
  }
  printf ("\n");
  
  // List of all airlines
  $sth = $dbh->prepare("SELECT DISTINCT a.alid, iata, icao, name FROM airlines as a, flights as f WHERE f.uid=?" . $filter . " AND a.alid=f.alid ORDER BY name");
  $sth->execute($params);
  $first = true;
  while ($row = $sth->fetch()) {
    if($first) {
      $first = false;
    } else {
      printf("\t");
    }  
    printf ("%s;%s", $row["alid"], $row["name"]);
  }
  printf ("\n");
  
  // List of all years
  $sth = $dbh->prepare("SELECT DISTINCT YEAR(src_date) AS year FROM flights WHERE uid=?" . $filter . " AND YEAR(src_date) != '0' ORDER BY YEAR DESC");
  $sth->execute($params);
  $first = true;
  while ($row = $sth->fetch()) {
    if($first) {
      $first = false;
    } else {
      printf("\t");
    }  
    printf ("%s;%s", $row["year"], $row["year"]);
  }
}
?>
