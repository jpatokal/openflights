<?php
//
// Helper functions for filter handling
//

// Load up possible filter settings for this user
function loadFilter($db, $uid, $trid) {

  // Limit selections to a single trip?
  if($trid && $trid != "0") {
    $filter = " AND trid= " . mysql_real_escape_string($trid);
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
  $sql = "SELECT DISTINCT a.alid, name FROM airlines as a, flights as f WHERE f.uid=" . $uid . $filter . " AND a.alid=f.alid ORDER BY name";
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
