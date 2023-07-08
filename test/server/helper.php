<?php

include_once dirname(__FILE__) . '/config.php';

// Requires: none

//
// HELPER
//

require_once dirname(__FILE__) . '/../../php/helper.php';

// Airport helpers

class FormatApCodeTest extends UnitTestCase {
    public function test() {
        $row = array("iata" => "AAA", "icao" => "BBBB");
        $this->assertEqual(format_apcode($row), "AAA");

        $row = array("iata" => null, "icao" => "BBBB");
        $this->assertEqual(format_apcode($row), "BBBB");

        $row = array("iata" => null, "icao" => null);
        $this->assertEqual(format_apcode($row), "Priv");
    }
}

class FormatAirportTest extends UnitTestCase {
    public function test() {
        $row = array("name" => "Airport", "city" => "City", "country" => "Country", "iata" => "AAA", "icao" => "BBBB");
        $this->assertEqual(format_airport($row), "City-Airport (AAA), Country");

        $row = array("name" => "City Intl", "city" => "City", "country" => "Country", "iata" => "AAA", "icao" => "BBBB");
        $this->assertEqual(format_airport($row), "City Intl (AAA), Country");

        $row = array("name" => "Hong-kong Intl", "city" => "Hong Kong", "country" => "Country", "iata" => "AAA", "icao" => "BBBB");
        $this->assertEqual(format_airport($row), "Hong-kong Intl (AAA), Country");

        $row = array("name" => "Obscure Regional", "city" => "Inaka", "country" => "Country", "iata" => "", "icao" => "BBBB");
        $this->assertEqual(format_airport($row), "Inaka-Obscure Regional (BBBB), Country");

        $row = array("name" => "Really Long Airport Name", "city" => "Really Long City Name", "country" => "Country", "iata" => "AAA", "icao" => "BBBB");
        $this->assertEqual(format_airport($row), "Really Long City Name-Really Lon. (AAA), Country");
    }
}

// Airline helpers

class FormatAirlineTest extends UnitTestCase {
    public function test() {
        $row = array("name" => "Airline", "iata" => "AA", "icao" => "BBB", "mode" => "F");
        $this->assertEqual(format_airline($row), "Airline (AA)");

        $row = array("name" => "Airline", "iata" => null, "icao" => "BBB", "mode" => "F");
        $this->assertEqual(format_airline($row), "Airline (BBB)");

        $row = array("name" => "Non-Airline", "iata" => "XXX", "mode" => "X");
        $this->assertEqual(format_airline($row), "Non-Airline");
    }
}

class FormatAlCodeTest extends UnitTestCase {
    public function test() {
        $this->assertEqual(format_alcode("AA", "BBB", "X"), "");
        $this->assertEqual(format_alcode("AA", "BBB", "F"), "AA");
        $this->assertEqual(format_alcode("AA", "BBB", null), "AA");
        $this->assertEqual(format_alcode(null, "BBB", null), "BBB");
        $this->assertEqual(format_alcode(null, null, null), "Priv");
    }
}

// Great Circle helpers

class GcDurationTest extends UnitTestCase {
    public function test() {
        $this->assertEqual(gcDuration(0), "00:30");
        $this->assertEqual(gcDuration(250), "01:00");
        $this->assertEqual(gcDuration(500), "01:30");
    }
}

// ##TODO## Extend
class GcDistanceTest extends UnitTestCase {
    public function test() {
        $dbh = db_connect();
        $this->assertEqual(gcDistance($dbh, 1, 1), array(0, gcDuration(0)));
    }
}

class FileUrlWithDateTest extends UnitTestCase {
    public function test() {
        $docroot = $_SERVER["DOCUMENT_ROOT"];
        $this->assertNotNull($docroot);
        $this->assertNotEqual($docroot, "/");

        $desired_time = 1230657905;
        $expected_date = "20081230";

        $test_filename = "/import/date-test-" . time() . '-' . rand(100000, 999999);
        touch($docroot . $test_filename, $desired_time);

        $this->assertEqual("$test_filename?version=$expected_date", fileUrlWithDate($test_filename));
    }
}
