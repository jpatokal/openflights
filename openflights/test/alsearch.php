<?php
require_once(dirname(__FILE__) . '/simpletest/autorun.php');
require_once(dirname(__FILE__) . '/simpletest/web_tester.php');
include_once(dirname(__FILE__) . '/config.php');

// Store temporary airline, railway ID
$new_alid = null;
$new_rlid = null;

// Try to add airline before logging in
class RecordAirlineNotLoggedInTest extends WebTestCase {
  function test() {
    global $webroot, $settings;

    $params = array("action" => "RECORD");
    $msg = $this->post($webroot . "php/alsearch.php", $params);
    $this->assertText("0;");
  }
}

// Add new airline
class RecordNewAirlineTest extends WebTestCase {
  function test() {
    global $webroot, $settings, $airline, $new_alid;

    login($this);
    $params = $airline;
    $params["action"] = "RECORD";
    $msg = $this->post($webroot . "php/alsearch.php", $params);
    $this->assertText('1;');

    $cols = preg_split('/[;\n]/', $msg);
    $new_alid = $cols[1];
  }
}

// Add new railway
class RecordNewRailwayTest extends WebTestCase {
  function test() {
    global $webroot, $settings, $railway, $new_rlid;

    login($this);
    $params = $railway;
    $params["action"] = "RECORD";
    $msg = $this->post($webroot . "php/alsearch.php", $params);
    $this->assertText('1;');

    $cols = preg_split('/[;\n]/', $msg);
    $new_rlid = $cols[1];
  }
}

// Try to add the railway again
class RecordRailwayDuplicateTest extends WebTestCase {
  function test() {
    global $webroot, $settings, $railway;

    login($this);
    $params = $railway;
    $params["action"] = "RECORD";
    $msg = $this->post($webroot . "php/alsearch.php", $params);
    $this->assertText('0;');
  }
}

// Try to edit an airline not belonging to us
class EditWrongAirlineTest extends WebTestCase {
  function test() {
    global $webroot, $settings, $airline;

    login($this);
    $params = $airline;
    $params["alid"] = 1;
    $params["icao"] = "ZZZY";
    $params += array("action" => "RECORD");
    $msg = $this->post($webroot . "php/alsearch.php", $params);
    $this->assertText('0;');
  }
}

// Try to reuse an existing airline's code
class AddAirlineDuplicateICAOTest extends WebTestCase {
  function test() {
    global $webroot, $settings, $airline, $new_alid;

    login($this);
    $params = $airline;
    $params["icao"] = "SIA"; // existing airline (Singapore Airlines)
    $params += array("action" => "RECORD");
    $msg = $this->post($webroot . "php/alsearch.php", $params);
    $this->assertText('0;');
  }
}

// Search by IATA
class SearchAirlineByIATATest extends WebTestCase {
  function test() {
    global $webroot, $airline;

    login($this);
    $params = array("iata" => $airline["iata"]);
    $msg = $this->post($webroot . "php/alsearch.php", $params);
    $this->assertText('0;1');
    $this->assertText('"name":"' . $airline["name"] . '"');
    $this->assertText('"alias":"' . $airline["alias"] . '"');
    $this->assertText('"iata":"' . $airline["iata"] . '"');
    $this->assertText('"icao":"'. $airline["icao"] . '"');
  }
}

// Search by ICAO
class SearchAirlineByICAOTest extends WebTestCase {
  function test() {
    global $webroot, $airline;

    login($this);
    $params = array("icao" => $airline["icao"]);
    $msg = $this->post($webroot . "php/alsearch.php", $params);
    $this->assertText('0;1');
    $this->assertText('"name":"' . $airline["name"] . '"');
    $this->assertText('"alias":"' . $airline["alias"] . '"');
    $this->assertText('"iata":"' . $airline["iata"] . '"');
    $this->assertText('"icao":"'. $airline["icao"] . '"');
  }
}

// Search railway by name
class SearchRailwayByNameTest extends WebTestCase {
  function test() {
    global $webroot, $railway;

    login($this);
    $params = array("name" => $railway["name"],
		    "mode" => "T");
    $msg = $this->post($webroot . "php/alsearch.php", $params);
    $this->assertText('0;1');
    $this->assertText('"name":"' . $railway["name"] . '"');
    $this->assertText('"alias":"' . $railway["alias"] . '"');
  }
}

?>
