<?php
require_once(dirname(__FILE__) . '/simpletest/autorun.php');
require_once(dirname(__FILE__) . '/simpletest/web_tester.php');
include_once(dirname(__FILE__) . '/config.php');

// Quick search by city name
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

// Quick search by IATA code
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

// Quick search by ICAO code
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

?>
