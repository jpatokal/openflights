<?php

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

  // Format name only for OpenFlights, GAD DBs
  if(! $code) {
    // Foobar-Foobar Intl into Foobar Intl
    // Foo-bar-Foo Bar Intl into Foo Bar Intl
    if(strncasecmp($name, strtr($city, "-", " "), strlen($city)) == 0) {
      $city = "";
    } else {
      $city = $city . "-";
    }
    if(strlen($city . $name) > 30) {
      $name = trim(substr($city . $name, 0, 29)) . ".";
      $city = "";
    }
  }

  return $city . $name . " (" . $iata . "), " . $country;
}

//
// Standard formatting of airline names
// row: associative array containing name and iata
//
function format_airline($row) {
  $name = $row["name"];
  $iata = $row["iata"];
  if(! $iata) {
    $iata = $row["icao"];
  }
  return $name . " (" . $iata . ")";
}

?>
