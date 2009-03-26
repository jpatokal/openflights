<?php
$modes = array ("F" => "Flight", "T" => "Train", "S" => "Ship", "R" => "Road trip");
$modeOperators = array ("F" => "airline", "T" => "railway", "S" => "shipping company", "R" => "road transport company");

//
// Standard formatting of airport data
// Input: row: associative array containing iata, icao
// Output: " code : apid : x : y : timezone : dstrule "
//
function format_apdata($row) {
  return sprintf ("%s:%s:%s:%s:%s:%s", format_apcode($row), $row["apid"], $row["x"], $row["y"], $row["timezone"], $row["dst"]);
}

//
// Standard formatting of airport codes
// row: associative array containing iata, icao
//
function format_apcode($row) {
  $code = $row["iata"];
  if(! $code || $code == "N/A") {
    $code = $row["icao"];
    if(! $code) {
      $code = "Priv";
    }
  }
  return $code;
}

function format_apcode2($iata, $icao) {
  $code = $iata;
  if(! $code || $code == "N/A") {
    $code = $icao;
    if(! $code) {
      $code = "Priv";
    }
  }
  return $code;
}

//
// Standard formatting of airport names
// row: associative array containing name, city, country/code and iata/icao
//
function format_airport($row) {
  $name = $row["name"];
  $city = $row["city"];
  $country = $row["country"];
  $code = $row["code"];
  if($code) {
    $country = $code; // hack for DAFIF
  }
  $iata = format_apcode($row);
  $apid = $row["apid"];

  // Foobar-Foobar Intl into Foobar Intl
  // Foo-bar-Foo Bar Intl into Foo Bar Intl
  if(strncasecmp($name, strtr($city, "-", " "), strlen($city)) == 0) {
    $city = "";
  } else {
    $city = $city . "-";
  }
  if(strlen($city . $name . $country) > 40) {
    $name = trim(substr($city . $name, 0, 39 - strlen($country))) . ".";
    $city = "";
  }

  return $city . $name . " (" . $iata . "), " . $country;
}

//
// Standard formatting of airline names
// row: associative array containing name, iata, icao and (optionally) mode
//
function format_airline($row) {
  $mode = $row["mode"];
  if($mode && $mode != "F") {
    // Not an airline
    return $row["name"];
  } else {
    return $row["name"] . " (" . format_alcode($row["iata"], $row["icao"], $row["mode"]) . ")";
  }
}

function format_alcode($iata, $icao, $mode) {
  if($mode && $mode != "F") return "";
  if($iata && $iata != "") {
    return $iata;
  } else {
    if($icao && $icao != "") {
      return $icao;
    } else {
      return "Priv";
    }
  }
}  

// Calculate (distance, duration) between two airport IDs
function gcDistance($db, $src_apid, $dst_apid) {
  $sql = "SELECT x,y FROM airports WHERE apid=$src_apid OR apid=$dst_apid";
  $rs = mysql_query($sql, $db);
  if(mysql_num_rows($rs) != 2) return array(null, null);
  $row = mysql_fetch_assoc($rs);
  $lon1 = $row["x"];
  $lat1 = $row["y"];
  $row = mysql_fetch_assoc($rs);
  $lon2 = $row["x"];
  $lat2 = $row["y"];

  $pi = 3.1415926;
  $rad = doubleval($pi/180.0);
  $lon1 = doubleval($lon1)*$rad; $lat1 = doubleval($lat1)*$rad;
  $lon2 = doubleval($lon2)*$rad; $lat2 = doubleval($lat2)*$rad;

  $theta = $lon2 - $lon1;
  $dist = acos(sin($lat1) * sin($lat2) + cos($lat1) * cos($lat2) * cos($theta));
  if ($dist < 0) { $dist += $pi; }
  $dist = floor($dist * 6371.2 * 0.621);

  $rawtime = floor(30 + ($dist / 500) * 60);
  $duration = sprintf("%02d:%02d",  floor($rawtime/60), $rawtime % 60);

  return array($dist, $duration);
}
?>