<?php
header("Content-type: text/html; charset=iso-8859-1");
include 'helper.php';
?>

<?php
$host = "localhost";
$database = "flightdb";
$user = "openflights";
$password = "";

mysql_connect($host,$user,$password);
mysql_select_db($database);

// If quick, then return only one row, with no UL tags
if($_POST['quick']) {
  $limit = 1;
} else {
  $limit = 6;
}

// Autocompletion for airports
// 3 chars: match on IATA or name (major airports only)
// 4 chars: match on ICAO or name (major airports only)
// >4 chars: match on name or city 

if($_POST['src_ap']) {
  $query = mysql_real_escape_string($_POST['src_ap']);
} else if($_POST['dst_ap']) {
  $query = mysql_real_escape_string($_POST['dst_ap']);
}
if($query) {
  if(strlen($query) <= 3) {
    $ext = "iata!='' AND";
  } else {
    $ext = "";
  }
  $sql = sprintf("SELECT 2 as sort_col,apid,name,city,country,iata,icao,x,y FROM airports WHERE %s iata != '%s' AND (city LIKE '%s%%'", $ext, $query, $query);

  switch(strlen($query)) {
  case 3: // IATA
    $sql = sprintf("SELECT 1 as sort_col,apid,name,city,country,iata,icao,x,y FROM airports WHERE iata='%s' UNION (%s)) ORDER BY sort_col,city,name LIMIT %s", $query, $sql, $limit);
    break;

  case 4: // ICAO
    $sql = sprintf("SELECT 1 as sort_col,apid,name,city,country,iata,icao,x,y FROM airports WHERE icao='%s' UNION (%s)) ORDER BY sort_col,city,name LIMIT %s", $query, $sql, $limit);
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
      $code = $row["iata"];
      if($code == "") {
	$code = $row["icao"];
      }
      if($limit > 1) {
	printf ("<li class='autocomplete' id='%s:%s:%s:%s'>%s</li>\n", $code, $row["apid"], $row["x"], $row["y"], format_airport($row));
      } else {
	printf ("%s:%s:%s:%s;%s", format_apcode($row), $row["apid"], $row["x"], $row["y"], format_airport($row));
      }
    }
  }

// Autocompletion for airlines
// 2 chars: match on IATA or name (major airlines only)
// 3 chars: match on ICAO or name (major airlines only)
// >3 chars: match on name (any airline)

} else if($_POST['airline']) {
  if($limit > 1) $limit = 3;
  $query = mysql_real_escape_string($_POST['airline']);
  if(strlen($query) <= 3) {
    $ext = "iata!='' AND";
  } else {
    $ext = "";
  }
  $sql = sprintf("SELECT 2 as sort_col,alid,name,iata,icao FROM airlines WHERE %s name LIKE '%s%%' OR alias LIKE '%s%%'",
		 $ext, $query, $query);
  switch(strlen($query)) {
  case 2: // IATA
    $sql = sprintf("SELECT 1 as sort_col,alid,name,iata,icao FROM airlines WHERE iata='%s' UNION (%s) ORDER BY sort_col, name LIMIT %s", $query, $sql, $limit);
    break;

  case 3: // ICAO
    $sql = sprintf("SELECT 1 as sort_col,alid,name,iata,icao FROM airlines WHERE icao='%s' UNION (%s) ORDER BY sort_col, name LIMIT %s", $query, $sql, $limit);
    break;

  default:
    $sql = sprintf("%s ORDER BY name LIMIT %s", $sql, $limit);
    break;
  }

  if($limit > 1) print ("<ul class='autocomplete'>");
  $rs = mysql_query($sql);
  if(mysql_num_rows($rs) > 0) {
    while($row = mysql_fetch_assoc($rs)) {
      $code = $row["iata"];
      if($code == "") {
	$code = $row["icao"];
      }
      if($limit > 1) {
	printf ("<li class='autocomplete' id='%s'>%s</li>", $row["alid"], format_airline($row));
      } else {
	printf ("%s;%s", $row["alid"], format_airline($row));
      }
    }
  }

  // Autocompletion for plane types
} else if($_POST['plane']) {
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

if($limit > 1) printf("</ul>");
?>

