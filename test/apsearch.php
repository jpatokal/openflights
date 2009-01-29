<?php
require_once(dirname(__FILE__) . '/simpletest/autorun.php');
require_once(dirname(__FILE__) . '/simpletest/web_tester.php');
include_once(dirname(__FILE__) . '/config.php');

// Store temporary airport ID
$new_apid = null;

// Check that we can load up the country data
class LoadCountriesTest extends WebTestCase {
  function test() {
    global $webroot;

    $params = array();
    $this->post($webroot . "php/countries.php", $params);
    $this->assertText('AF;Afghanistan');
    $this->assertText('ZI;Zimbabwe');
  }
}

// Try to add airport before logging in
class RecordNotLoggedInTest extends WebTestCase {
  function test() {
    global $webroot, $settings;

    $params = array("action" => "RECORD");
    $msg = $this->post($webroot . "php/apsearch.php", $params);
    $this->assertText("0;");
  }
}

// Add new airport
class RecordNewAirportTest extends WebTestCase {
  function test() {
    global $webroot, $settings, $airport, $new_apid;

    login($this);
    $params = $airport;
    $params["action"] = "RECORD";
    $msg = $this->post($webroot . "php/apsearch.php", $params);
    $this->assertText('1;');

    $cols = preg_split('/[;\n]/', $msg);
    $new_apid = $cols[1];
  }
}

// Try to add it again
class RecordDuplicateTest extends WebTestCase {
  function test() {
    global $webroot, $settings, $airport;

    login($this);
    $params = $airport;
    $params["action"] = "RECORD";
    $msg = $this->post($webroot . "php/apsearch.php", $params);
    $this->assertText('0;');
  }
}

// Try to edit an airport not belonging to us
class EditWrongAirportTest extends WebTestCase {
  function test() {
    global $webroot, $settings, $airport, $new_apid;

    login($this);
    $params = $airport;
    $params["apid"] = 1;
    $params["icao"] = "ZZZY";
    $params += array("action" => "RECORD");
    $msg = $this->post($webroot . "php/apsearch.php", $params);
    $this->assertText('0;');
  }
}

// Try to reuse an existing airport's code
class EditDuplicateICAOTest extends WebTestCase {
  function test() {
    global $webroot, $settings, $airport, $new_apid;

    login($this);
    $params = $airport;
    $params["icao"] = "WSSS"; // existing airport (Singapore)
    $params["apid"] = $new_apid;
    $params += array("action" => "RECORD");
    $msg = $this->post($webroot . "php/apsearch.php", $params);
    $this->assertText('0;');
  }
}

// Try to edit to overwrite existing airport
class EditSuccessfulTest extends WebTestCase {
  function test() {
    global $webroot, $settings, $airport, $new_apid;

    login($this);
    $params = $airport;
    $params["apid"] = $new_apid;
    $params["name"] = "Edited Airport";
    $params += array("action" => "RECORD");
    $msg = $this->post($webroot . "php/apsearch.php", $params);
    $this->assertText('1;');
  }
}

// Search OpenFlights DB by IATA (of just-added airport)
class SearchOFDBByIATATest extends WebTestCase {
  function test() {
    global $webroot, $airport;

    login($this);
    $params = array("iata" => $airport["iata"]);
    $msg = $this->post($webroot . "php/apsearch.php", $params);
    $this->assertText('0;1');
    $this->assertText('"name":"Edited Airport"');
    $this->assertText('"city":"' . $airport["city"] . '"');
    $this->assertText('"country":"' . $airport["country"] . '"');
    $this->assertText('"iata":"' . $airport["iata"] . '"');
    $this->assertText('"icao":"'. $airport["icao"] . '"');
    $this->assertText('"x":"' . $airport["x"] . '"');
    $this->assertText('"y":"' . $airport["y"] . '"');
    $this->assertText('"elevation":"' . $airport["elevation"] . '"');
    $this->assertText('"timezone":"' . $airport["timezone"] . '"');
    $this->assertText('"dst":"' . $airport["dst"] . '"');
    $this->assertText('"ap_uid":"own"');
  }
}

// Search DAFIF by ICAO
class SearchDAFIFByICAOTest extends WebTestCase {
  function test() {
    global $webroot;

    $params = array("icao" => "WSSS",
		    "db" => "airports_dafif");
    $msg = $this->post($webroot . "php/apsearch.php", $params);
    $this->assertText('0;1');
    $this->assertText('"name":"Singapore Changi Intl"');
    $this->assertText('"country":"SN"');
    $this->assertText('"iata":""');
    $this->assertText('"icao":"WSSS"');
    $this->assertText('"x":"103.994433"');
    $this->assertText('"y":"1.350189"');
    $this->assertText('"elevation":"22"');
  }
}

// Search GAD by name
class SearchGADByNameTest extends WebTestCase {
  function test() {
    global $webroot;

    $params = array("name" => "Singapore Changi",
		    "db" => "airports_gad");
    $msg = $this->post($webroot . "php/apsearch.php", $params);
    $this->assertText('0;1');
    $this->assertText('"name":"Singapore Changi"');
    $this->assertText('"city":"Singapore"');
    $this->assertText('"country":"Singapore"');
    $this->assertText('"iata":"SIN"');
    $this->assertText('"icao":"WSSS"');
    $this->assertText('"x":"103.987"');
    $this->assertText('"y":"1.35556"');
    $this->assertText('"elevation":"22"');
  }
}

?>
