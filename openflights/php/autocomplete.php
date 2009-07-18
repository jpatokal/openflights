<?php
include 'helper.php';
include 'db.php';

// If quick, then return only one row, with no UL tags
if($_POST['quick']) {
  $limit = 1;
} else {
  $limit = 6;
}
// If multi, then search airports and airlines
$multi = $_POST["qs"];

// Autocompletion for airports
// 3 chars: match on IATA or name (major airports only)
// 4 chars: match on ICAO or name (major airports only)
// >4 chars: match on name or city 

$airports = array("qs", "src_ap", "dst_ap", "src_ap1", "dst_ap1", "src_ap2", "dst_ap2", "src_ap3", "dst_ap3", "src_ap4", "dst_ap4");
foreach($airports as $ap) {
  if($_POST[$ap]) {
    $query = mysql_real_escape_string($_POST[$ap]);
    // Limit the number of rows returned in multiinput, where space is at a premium
    if($limit > 1) {
      $idx = substr($ap, -1);
      switch($idx) {
      case "4":
      case "3":
      case "2":
      case "1":
	$limit = 7 - $idx;
      }
    }
    break;
  }
}
if($query) {
  if(strlen($query) <= 3) {
    $ext = "iata!='' AND iata != '" . $query . "' AND";
  } else {
    $ext = "";
  }
  $sql = sprintf("SELECT 2 as sort_col,apid,name,city,country,iata,icao,x,y,timezone,dst FROM airports WHERE %s (city LIKE '%s%%'", $ext, $query);

  switch(strlen($query)) {
  case 3: // IATA
    $sql = sprintf("SELECT 1 as sort_col,apid,name,city,country,iata,icao,x,y,timezone,dst FROM airports WHERE iata='%s' UNION (%s)) ORDER BY sort_col,city,name LIMIT %s", $query, $sql, $limit);
    break;

  case 4: // ICAO
    $sql = sprintf("SELECT 1 as sort_col,apid,name,city,country,iata,icao,x,y,timezone,dst FROM airports WHERE icao='%s' UNION (%s)) ORDER BY sort_col,city,name LIMIT %s", $query, $sql, $limit);
    break;

  default:
    if(strlen($query) > 4) {
      $sql = sprintf("%s OR name LIKE '%s%%') ORDER BY city,name LIMIT %s", $sql, $query, $limit);
    } else {
      $sql = sprintf("%s) ORDER BY city,name LIMIT %s", $sql, $limit);
    }
    break;
  }

  if($limit > 1) print ("<ul class='autocomplete'>");
  $rs = mysql_query($sql);
  if(mysql_num_rows($rs) > 0) {
    while($row = mysql_fetch_assoc($rs)) {
      if($limit > 1) {
	printf ("<li class='autocomplete' origin='%s' id='%s'>%s</li>\n", $ap, format_apdata($row), format_airport($row));
      } else {
	printf ("%s;%s", format_apdata($row), format_airport($row));
      }
    }
  }
}

if(! $query || $multi) {

// Autocompletion for airlines
// 2 chars: match on IATA or name (major airlines only)
// 3 chars: match on ICAO or name (major airlines only)
// >3 chars: match on name (any airline)
  
  $airlines = array("qs", "airline", "airline1", "airline2", "airline3", "airline4");
  foreach($airlines as $al) {
    if($_POST[$al]) {
      $query = mysql_real_escape_string($_POST[$al]);
      // Limit(/expand) the number of rows returned in multiinput, where space is at a premium
      if($limit != 1) {
	$idx = substr($al, -1);
	switch($idx) {
	case "4":
	case "3":
	case "2":
	case "1":
	  $limit = 7 - $idx;
	  break;
	default:
	  $limit = 3;
	}
      }
      break;
    }
  }
  if($query) {
    $mode = mysql_real_escape_string($_POST["mode"]);
    if(! $mode) $mode = "F";
    if(strlen($query) <= 3 && $mode == 'F') {
      $ext = "iata!='' AND icao!='" . $query . "' AND";
    } else {
      $ext = ""; // anything goes!
    }
    if($multi) {
      $ext = "iata!='' AND active='Y' AND"; // quick search only for active, IATA-coded airlines
    }
    $sql = sprintf("SELECT 2 as sort_col,alid,name,iata,icao,mode FROM airlines WHERE mode='%s' AND %s (name LIKE '%s%%' OR alias LIKE '%s%%')",
		   $mode, $ext, $query, $query);

    // IATA/ICAO only apply to flights
    if($mode == 'F') {
      switch(strlen($query)) {
      case 2: // IATA
	$sql = sprintf("SELECT 1 as sort_col,alid,name,iata,icao,mode FROM airlines WHERE iata='%s' UNION (%s) ORDER BY sort_col, name LIMIT %s", $query, $sql, $limit);
	break;
	
      case 3: // ICAO
	if(! $multi) {
	  $sql = sprintf("SELECT 1 as sort_col,alid,name,iata,icao,mode FROM airlines WHERE icao='%s' UNION (%s) ORDER BY sort_col, name LIMIT %s", $query, $sql, $limit);
	  break;
	} // else fallthru

      default: // sort non-IATA airlines last
	$sql = sprintf("%s ORDER BY LENGTH(iata) DESC, name LIMIT %s", $sql, $limit);
	break;
      }
    } else {
      $sql = sprintf("%s ORDER BY name LIMIT %s", $sql, $limit);
    }
    
    if($limit > 1 && ! $multi) print ("<ul class='autocomplete'>");
    $rs = mysql_query($sql) or die($sql);
    if(mysql_num_rows($rs) > 0) {
      while($row = mysql_fetch_assoc($rs)) {
	if($limit > 1) {
	  printf ("<li class='autocomplete' id='%s'>%s</li>", $row["alid"], format_airline($row));
	} else {
	  printf ("%s;%s", $row["alid"], format_airline($row));
	}
      }
    }

  } else if($_POST['plane']) {

    // Autocompletion for plane types
    $query = mysql_real_escape_string($_POST['plane']);
    if(strstr($query, '-')) {
      $dashes = " ";
    } else {
      $dashes = "AND name NOT LIKE 'Boeing %-%' AND name NOT LIKE 'Airbus %-%'";
    }
    
    $sql = "SELECT name,plid FROM planes WHERE public='Y' AND name LIKE '%" . $query . "%' ". $dashes . "ORDER BY name LIMIT 6";
    $rs = mysql_query($sql);
    
    // If no or only one result found, then try again
    if(mysql_num_rows($rs) == 1 && $dashes != " ") {
      $sql = "SELECT name,plid FROM planes WHERE public='Y' AND name LIKE '%" . $query . "%' ORDER BY name LIMIT 6";
      $rs = mysql_query($sql);
    } else {
      if(mysql_num_rows($rs) == 0) {
	$sql = "SELECT name,plid FROM planes WHERE name LIKE '%" . $query . "%' ORDER BY name LIMIT 6";
	$rs = mysql_query($sql);
      }
    }
    print ("<ul class='autocomplete2'>");
    while($data = mysql_fetch_assoc($rs)) {
      $item = stripslashes($data['name']);
      if(strlen($item) > 23) {
	$item = substr($item, 0, 10) . "..." . substr($item, -10, 10);
      }
      echo "<li class='autocomplete' id='" . $data['plid'] . "'>" . $item . "</li>";
    }
  }
 }

if($limit > 1) printf("</ul>");
?>

