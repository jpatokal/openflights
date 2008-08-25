<?php

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
  $iata = $row["iata"];
  if(! $iata || $iata == "N/A") {
    $iata = $row["icao"];
  }
  $apid = $row["apid"];

  // Format name only for OpenFlights, GAD DBs
  if(! $code) {
    // Foobar-Foobar Intl into Foobar Intl
    if(substr($name, 0, strlen($city)) == $city) {
      $name = " " . substr($name, strlen($city) + 1);
    } else {
      $city = $city . "-";
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

  return $name . " (" . $iata . ")";
}

?>
