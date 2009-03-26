<?php
require_once(dirname(__FILE__) . '/simpletest/autorun.php');
require_once(dirname(__FILE__) . '/simpletest/web_tester.php');
include_once(dirname(__FILE__) . '/config.php');

//
// AIRPORTS
//

// Quick airport search by city name
class QuickAirportCityCompleteTest extends WebTestCase {
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

// Quick airport search by IATA code
class QuickAirportIATACompleteTest extends WebTestCase {
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

// Quick airport search by ICAO code
class QuickAirportICAOCompleteTest extends WebTestCase {
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

// Quick airline search by name
class QuickAirlineNameCompleteTest extends WebTestCase {
  function test() {
    global $webroot, $airline;

    $params = array("quick" => "true",
		    "airline" => $airline["name"]);
    $msg = $this->post($webroot . "php/autocomplete.php", $params);
    $this->assertText("(" . $airline["iata"] . ")");
    $this->assertText(";" . $airline["name"]);
  }
}

// Quick airline search by alias
class QuickAirlineAliasCompleteTest extends WebTestCase {
  function test() {
    global $webroot, $airline;

    $params = array("quick" => "true",
		    "airline" => $airline["alias"]);
    $msg = $this->post($webroot . "php/autocomplete.php", $params);
    $this->assertText("(" . $airline["iata"] . ")");
    $this->assertText(";" . $airline["name"]);
  }
}

// Quick airline search by IATA code
class QuickAirlineIATACompleteTest extends WebTestCase {
  function test() {
    global $webroot, $airline;

    $params = array("quick" => "true",
		    "airline" => $airline["iata"]);
    $msg = $this->post($webroot . "php/autocomplete.php", $params);
    $this->assertText("(" . $airline["iata"] . ")");
    $this->assertText(";" . $airline["name"]);
  }
}

// Quick airline search by ICAO code
class QuickAirlineICAOCompleteTest extends WebTestCase {
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
