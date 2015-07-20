<?php
include_once(dirname(__FILE__) . '/config.php');

//
// Test cases for php/top10.php
// NB: Assumes the test user exists and flights.php has been run, so that $flight2[] is already in DB
//

$fid = null; // global for newly-added flight

// Check default (flight count) Top 10 stats
class CheckTop10FlightCountStats extends WebTestCase {
  function test() {
    global $webroot, $settings, $flight2;

    assert_login($this);

    $top10 = $this->post($webroot . "php/top10.php");
    $rows = preg_split('/\n/', $top10);
    $this->assertTrue(sizeof($rows) == 4);

    // Top 10 routes
    $routes = $rows[0];
    $this->assertPattern("/," . $flight2["src_apid"] . ",/", $routes);
    $this->assertPattern("/," . $flight2["dst_apid"] . ",1/", $routes);

    // Top 10 airports
    $airports = $rows[1];
    $this->assertPattern("/,1," . $flight2["src_apid"] . "/", $airports);
    $this->assertPattern("/,1," . $flight2["dst_apid"] . "/", $airports);

    // Top 10 airlines
    $airlines = $rows[2];
    $this->assertPattern("/,1," . $flight2["alid"] . "/", $airlines);

    // Top 10 planes 
    $planes = $rows[3];
    $this->assertPattern("/" . $flight2["plane"] . ",1/", $planes);
  }
}

// Check flight count Top 10 stats with airline filtering
class CheckTop10AirlineFilteredFlightCountStats extends WebTestCase {
  function test() {
    global $webroot, $settings, $flight2;

    assert_login($this);

    $filter = array("alid" => $flight2["alid"]);
    $top10 = $this->post($webroot . "php/top10.php", $filter);
    $rows = preg_split('/\n/', $top10);
    $this->assertTrue(sizeof($rows) == 4);

    // Top 10 routes
    $routes = $rows[0];
    $this->assertPattern("/," . $flight2["src_apid"] . ",/", $routes);
    $this->assertPattern("/," . $flight2["dst_apid"] . ",1/", $routes);

    // Top 10 airports
    $airports = $rows[1];
    $this->assertPattern("/,1," . $flight2["src_apid"] . "/", $airports);
    $this->assertPattern("/,1," . $flight2["dst_apid"] . "/", $airports);

    // Top 10 airlines
    $airlines = $rows[2];
    $this->assertPattern("/,1," . $flight2["alid"] . "/", $airlines);

    // Top 10 planes 
    $planes = $rows[3];
    $this->assertPattern("/" . $flight2["plane"] . ",1/", $planes);
  }
}

// Check by-distance Top 10 stats
class CheckTop10DistanceStats extends WebTestCase {
  function test() {
    global $webroot, $settings, $flight2;

    assert_login($this);

    $params = array("mode" => "D");
    $top10 = $this->post($webroot . "php/top10.php", $params);
    $rows = preg_split('/\n/', $top10);
    $this->assertTrue(sizeof($rows) == 4);

    $distance = $flight2["distance"];

    // Top 10 routes
    $routes = $rows[0];
    $this->assertPattern("/," . $flight2["src_apid"] . ",/", $routes);
    $this->assertPattern("/," . $flight2["dst_apid"] . ",$distance/", $routes);

    // Top 10 airports
    $airports = $rows[1];
    $this->assertPattern("/,$distance," . $flight2["src_apid"] . "/", $airports);
    $this->assertPattern("/,$distance," . $flight2["dst_apid"] . "/", $airports);

    // Top 10 airlines
    $airlines = $rows[2];
    $this->assertPattern("/,$distance," . $flight2["alid"] . "/", $airlines);

    // Top 10 planes 
    $planes = $rows[3];
    $this->assertPattern("/" . $flight2["plane"] . ",$distance/", $planes);
  }
}

?>
