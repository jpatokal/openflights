<?php

//
// Standard formatting of airport names
// row: associative array containing name, city, country and iata
//
function format_airport($row) {
  $name = $row["name"];
  $city = $row["city"];
  $country = $row["country"];
  $iata = $row["iata"];

  // Foobar-Foobar Intl into Foobar Intl
  if(substr($name, 0, strlen($city)) == $city) {
    $name = " " . substr($name, strlen($city) + 1);
  } else {
    $city = $city . "-";
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
