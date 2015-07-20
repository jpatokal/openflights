<?php
include_once(dirname(__FILE__) . '/config.php');

// Requires: settings, apsearch, alsearch, to insert test airport, airline data

//
// MULTISEARCH
//

// Search for string found in both airport and airline name
class MultiSearchSharedLongStringTest extends WebTestCase {
  function test() {
    global $webroot, $airport, $airline, $qs_string;

    $params = array("qs" => $qs_string);
    $msg = $this->post($webroot . "php/autocomplete.php", $params);
    $this->assertText($airport["city"] . "-");
    $this->assertText("(" . $airport["iata"] . ")");
    $this->assertText("(" . $airline["iata"] . ")");
    $this->assertText($airline["name"]);
  }
}

// Search for string found only in airport name
class MultiSearchAirportOnlyLongStringTest extends WebTestCase {
  function test() {
    global $webroot, $airport;

    $params = array("qs" => $airport["name"]);
    $msg = $this->post($webroot . "php/autocomplete.php", $params);
    $this->assertText($airport["city"] . "-");
    $this->assertText("(" . $airport["iata"] . ")");
  }
}

// Search for string found in only airline name
class MultiSearchAirlineOnlyLongStringTest extends WebTestCase {
  function test() {
    global $webroot, $airline;

    $params = array("qs" => $airline["name"]);
    $msg = $this->post($webroot . "php/autocomplete.php", $params);
    $this->assertText("(" . $airline["iata"] . ")");
    $this->assertText($airline["name"]);
  }
}

// Search for airport by IATA
class MultiSearchAirportIATATest extends WebTestCase {
  function test() {
    global $webroot, $airport;

    $params = array("qs" => $airport["iata"]);
    $msg = $this->post($webroot . "php/autocomplete.php", $params);
    $this->assertText($airport["city"] . "-");
    $this->assertText("(" . $airport["iata"] . ")");
  }
}

// Search for airline by IATA
class MultiSearchAirlineIATATest extends WebTestCase {
  function test() {
    global $webroot, $airline;

    $params = array("qs" => $airline["iata"]);
    $msg = $this->post($webroot . "php/autocomplete.php", $params);
    $this->assertText("(" . $airline["iata"] . ")");
    $this->assertText($airline["name"]);
  }
}

//
// AIRPORTS
//

// Single airport search by city name
class SingleAirportCityCompleteTest extends WebTestCase {
  function test() {
    global $webroot, $airport;

    $params = array("quick" => "true",
		    "src_ap" => $airport["city"]);
    $msg = $this->post($webroot . "php/autocomplete.php", $params);
    $this->assertText($airport["iata"] . ":");
    $this->assertText(":" . $airport["x"] . ":");
    $this->assertText(":" . $airport["y"]);
  }
}

// Single airport search by IATA code
class SingleAirportIATACompleteTest extends WebTestCase {
  function test() {
    global $webroot, $airport;

    $params = array("quick" => "true",
		    "src_ap" => $airport["iata"]);
    $msg = $this->post($webroot . "php/autocomplete.php", $params);
    $this->assertText($airport["iata"] . ":");
    $this->assertText(":" . $airport["x"] . ":");
    $this->assertText(":" . $airport["y"]);
  }
}

// Single airport search by ICAO code
class SingleAirportICAOCompleteTest extends WebTestCase {
  function test() {
    global $webroot, $airport;

    $params = array("quick" => "true",
		    "src_ap" => $airport["icao"]);
    $msg = $this->post($webroot . "php/autocomplete.php", $params);
    $this->assertText($airport["iata"] . ":");
    $this->assertText(":" . $airport["x"] . ":");
    $this->assertText(":" . $airport["y"]);
  }
}

//
// AIRLINES
//

// Single airline search by name
class SingleAirlineNameCompleteTest extends WebTestCase {
  function test() {
    global $webroot, $airline;

    $params = array("quick" => "true",
		    "airline" => $airline["name"]);
    $msg = $this->post($webroot . "php/autocomplete.php", $params);
    $this->assertText("(" . $airline["iata"] . ")");
    $this->assertText(";" . $airline["name"]);
  }
}

// Single airline search by alias
class SingleAirlineAliasCompleteTest extends WebTestCase {
  function test() {
    global $webroot, $airline;

    $params = array("quick" => "true",
		    "airline" => $airline["alias"]);
    $msg = $this->post($webroot . "php/autocomplete.php", $params);
    $this->assertText("(" . $airline["iata"] . ")");
    $this->assertText(";" . $airline["name"]);
  }
}

// Single airline search by IATA code
class SingleAirlineIATACompleteTest extends WebTestCase {
  function test() {
    global $webroot, $airline;

    $params = array("quick" => "true",
		    "airline" => $airline["iata"]);
    $msg = $this->post($webroot . "php/autocomplete.php", $params);
    $this->assertText("(" . $airline["iata"] . ")");
    $this->assertText(";" . $airline["name"]);
  }
}

// Single airline search by ICAO code
class SingleAirlineICAOCompleteTest extends WebTestCase {
  function test() {
    global $webroot, $airline;

    $params = array("quick" => "true",
		    "airline" => $airline["icao"]);
    $msg = $this->post($webroot . "php/autocomplete.php", $params);
    $this->assertText("(" . $airline["iata"] . ")");
    $this->assertText(";" . $airline["name"]);
  }
}

?>
