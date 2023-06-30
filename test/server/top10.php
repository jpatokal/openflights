<?php

include_once(dirname(__FILE__) . '/config.php');

//
// Test cases for php/top10.php
// NB: Assumes the test user exists and flights.php has been run, so that $flight2[] is already in DB
//

$fid = null; // global for newly-added flight

// Return first value matching $pred or null if no matches
function array_find($array, $pred) {
    foreach ($array as $value) {
        if ($pred($value)) {
            return $value;
        }
    }
    return null;
}

// Check default (flight count) Top 10 stats
class CheckTop10FlightCountStats extends WebTestCase {
    public function test() {
        global $webroot, $settings, $flight2;

        assert_login($this);

        $top10 = $this->post($webroot . "php/top10.php");
        $json = json_decode($top10, true);

        $this->assertTrue(array_key_exists("routes", $json));
        $this->assertTrue(array_key_exists("airlines", $json));
        $this->assertTrue(array_key_exists("airports", $json));
        $this->assertTrue(array_key_exists("planes", $json));

        // Top 10 routes
        $route_src = array_find($json["routes"], function($val) use ($flight2) {
            return $val["src_apid"] == $flight2["src_apid"];
        });
        $this->assertNotEqual($route_src, null);
        $this->assertEqual($route_src["count"], 1);
        $route_dst = array_find($json["routes"], function($val) use ($flight2) {
            return $val["dst_apid"] == $flight2["dst_apid"];
        });
        $this->assertNotEqual($route_dst, null);
        $this->assertEqual($route_dst["count"], 1);

        // Top 10 airports
        $airport_src = array_find($json["airports"], function($val) use ($flight2) {
            return $val["apid"] == $flight2["src_apid"];
        });
        $this->assertNotEqual($airport_src, null);
        $this->assertEqual($airport_src["count"], 1);
        $airport_dst = array_find($json["airports"], function($val) use ($flight2) {
            return $val["apid"] == $flight2["dst_apid"];
        });
        $this->assertNotEqual($airport_dst, null);
        $this->assertEqual($airport_dst["count"], 1);

        // Top 10 airlines
        $airline = array_find($json["airlines"], function($val) use ($flight2) {
            return $val["alid"] == $flight2["alid"];
        });
        $this->assertNotEqual($airline, null);
        $this->assertEqual($airline["count"], 1);

        // Top 10 planes
        $plane = array_find($json["planes"], function($val) use ($flight2) {
            return $val["name"] == $flight2["plane"];
        });
        $this->assertNotEqual($plane, null);
        $this->assertEqual($plane["count"], 1);
    }
}

// Check flight count Top 10 stats with airline filtering
class CheckTop10AirlineFilteredFlightCountStats extends WebTestCase {
    public function test() {
        global $webroot, $settings, $flight2;

        assert_login($this);

        $filter = array("alid" => $flight2["alid"]);

        $top10 = $this->post($webroot . "php/top10.php", $filter);
        $json = json_decode($top10, true);

        $this->assertTrue(array_key_exists("routes", $json));
        $this->assertTrue(array_key_exists("airlines", $json));
        $this->assertTrue(array_key_exists("airports", $json));
        $this->assertTrue(array_key_exists("planes", $json));

        // Top 10 routes
        $route_src = array_find($json["routes"], function($val) use ($flight2) {
            return $val["src_apid"] == $flight2["src_apid"];
        });
        $this->assertNotEqual($route_src, null);
        $this->assertEqual($route_src["count"], 1);
        $route_dst = array_find($json["routes"], function($val) use ($flight2) {
            return $val["dst_apid"] == $flight2["dst_apid"];
        });
        $this->assertNotEqual($route_dst, null);
        $this->assertEqual($route_dst["count"], 1);

        // Top 10 airports
        $airport_src = array_find($json["airports"], function($val) use ($flight2) {
            return $val["apid"] == $flight2["src_apid"];
        });
        $this->assertNotEqual($airport_src, null);
        $this->assertEqual($airport_src["count"], 1);
        $airport_dst = array_find($json["airports"], function($val) use ($flight2) {
            return $val["apid"] == $flight2["dst_apid"];
        });
        $this->assertNotEqual($airport_dst, null);
        $this->assertEqual($airport_dst["count"], 1);

        // Top 10 airlines
        $airline = array_find($json["airlines"], function($val) use ($flight2) {
            return $val["alid"] == $flight2["alid"];
        });
        $this->assertNotEqual($airline, null);
        $this->assertEqual($airline["count"], 1);

        // Top 10 planes
        $plane = array_find($json["planes"], function($val) use ($flight2) {
            return $val["name"] == $flight2["plane"];
        });
        $this->assertNotEqual($plane, null);
        $this->assertEqual($plane["count"], 1);
    }
}

// Check by-distance Top 10 stats
class CheckTop10DistanceStats extends WebTestCase {
    public function test() {
        global $webroot, $settings, $flight2;

        assert_login($this);

        $params = array("mode" => "D");
        $top10 = $this->post($webroot . "php/top10.php", $params);

        $distance = $flight2["distance"];

        $json = json_decode($top10, true);

        $this->assertTrue(array_key_exists("routes", $json));
        $this->assertTrue(array_key_exists("airlines", $json));
        $this->assertTrue(array_key_exists("airports", $json));
        $this->assertTrue(array_key_exists("planes", $json));

        // Top 10 routes
        $route_src = array_find($json["routes"], function($val) use ($flight2) {
            return $val["src_apid"] == $flight2["src_apid"];
        });
        $this->assertNotEqual($route_src, null);
        $this->assertEqual($route_src["count"], $distance);
        $route_dst = array_find($json["routes"], function($val) use ($flight2) {
            return $val["dst_apid"] == $flight2["dst_apid"];
        });
        $this->assertNotEqual($route_dst, null);
        $this->assertEqual($route_dst["count"], $distance);

        // Top 10 airports
        $airport_src = array_find($json["airports"], function($val) use ($flight2) {
            return $val["apid"] == $flight2["src_apid"];
        });
        $this->assertNotEqual($airport_src, null);
        $this->assertEqual($airport_src["count"], $distance);
        $airport_dst = array_find($json["airports"], function($val) use ($flight2) {
            return $val["apid"] == $flight2["dst_apid"];
        });
        $this->assertNotEqual($airport_dst, null);
        $this->assertEqual($airport_dst["count"], $distance);

        // Top 10 airlines
        $airline = array_find($json["airlines"], function($val) use ($flight2) {
            return $val["alid"] == $flight2["alid"];
        });
        $this->assertNotEqual($airline, null);
        $this->assertEqual($airline["count"], $distance);

        // Top 10 planes
        $plane = array_find($json["planes"], function($val) use ($flight2) {
            return $val["name"] == $flight2["plane"];
        });
        $this->assertNotEqual($plane, null);
        $this->assertEqual($plane["count"], $distance);
    }
}
