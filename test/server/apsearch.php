<?php
include_once(dirname(__FILE__) . '/config.php');

// Store temporary airport ID
$new_apid = null;

// Check that we can load up the country data
class LoadCountriesTest extends WebTestCase {
  public function test() {
    global $webroot;

    $params = array();
    $this->post($webroot . "php/countries.php", $params);
    $this->assertText('AF;Afghanistan');
    $this->assertText('ZI;Zimbabwe');
  }
}

// Try to add airport before logging in
class RecordAirportNotLoggedInTest extends WebTestCase {
  public function test() {
    global $webroot, $settings;

    $params = array("action" => "RECORD", "unittest" => "true");
    $json = json_decode($this->post($webroot . "php/apsearch.php", $params), true);
    $this->assertEqual($json['status'], '0');
  }
}

// Add new airport
class RecordNewAirportTest extends WebTestCase {
  public function test() {
    global $webroot, $settings, $airport, $new_apid;

    login($this);
    $params = $airport;
    $params["action"] = "RECORD";
    $json = json_decode($this->post($webroot . "php/apsearch.php", $params), true);
    $this->assertEqual($json['status'], '1');
    $new_apid = $json["apid"];
  }
}

// Try to add it again
class RecordAirportDuplicateTest extends WebTestCase {
  public function test() {
    global $webroot, $settings, $airport;

    login($this);
    $params = $airport;
    $params += array("action" => "RECORD", "unittest" => "true");
    $msg = $this->post($webroot . "php/apsearch.php", $params);

    $this->assertText('Update airport AutoTest Airport (ZZZ/ZZZZ)');
    $this->assertText('New airport edit suggestion submitted by autotest:');
    $this->assertText("[name] => " . $airport["name"]);
  }
}

// Try to edit an airport not belonging to us
class EditWrongAirportTest extends WebTestCase {
  public function test() {
    global $webroot, $settings, $airport, $new_apid;

    login($this);
    $params = $airport;
    $params["apid"] = 1;
    $params["icao"] = "ZZZY";
    $params += array("action" => "RECORD", "unittest" => "true");
    $msg = $this->post($webroot . "php/apsearch.php", $params);

    $this->assertText('Update airport AutoTest Airport (ZZZ/ZZZY)');
    $this->assertText('New airport edit suggestion submitted by autotest:');
    $this->assertText("[icao] => " . $params["icao"]);
    $this->assertText("[icao] => " . $airport["icao"]);
  }
}

// Try to reuse an existing airport's code
class EditAirportDuplicateICAOTest extends WebTestCase {
  public function test() {
    global $webroot, $settings, $airport, $new_apid;

    login($this);
    $params = $airport;
    $params["icao"] = "WSSS"; // existing airport (Singapore)
    $params["apid"] = $new_apid;
    $params += array("action" => "RECORD", "unittest" => "true");
    $msg = $this->post($webroot . "php/apsearch.php", $params);
    $this->assertText('Update airport AutoTest Airport (ZZZ/WSSS)');
    $this->assertText('New airport edit suggestion submitted by autotest:');
    $this->assertText("[name] => " . $airport["name"]);
    $this->assertText("[name] => Changi Intl");
  }
}

// Try to edit to overwrite existing airport
class EditAirportSuccessfulTest extends WebTestCase {
  public function test() {
    global $webroot, $settings, $airport, $new_apid;

    login($this);
    $params = $airport;
    $params["apid"] = $new_apid;
    $params["name"] = $airport["name"] . " Edited";
    $params += array("action" => "RECORD", "unittest" => "true");

    $json = json_decode($this->post($webroot . "php/apsearch.php", $params), true);
    $this->assertEqual($json['status'], '1');
  }
}

// Add new location with null codes
class RecordNewNullCodePlaceTest extends WebTestCase {
  public function test() {
    global $webroot, $settings, $airport, $new_apid;

    login($this);
    $params =  array('name' => 'AutoTest Nullport',
         'city' => 'Testville',
         'country' => 'Nullistan',
         'iata' => '',
         'icao' => '',
         'x' => '42.424',
         'y' => '69.696',
         'elevation' => '123',
         'timezone' => '-5.5',
         'dst' => 'Z');
    $params["action"] = "RECORD";
    $json = json_decode($this->post($webroot . "php/apsearch.php", $params), true);
    $this->assertEqual($json['status'], '1');
    $null_apid = $json["apid"];

    $params = array("apid" => $null_apid,
            "action" => "LOAD");
    $json = json_decode($this->post($webroot . "php/apsearch.php", $params), true);
    $this->assertEqual($json['status'], '1');
    $this->assertEqual($json['max'], 1);
    $this->assertText('"iata":null');
    $this->assertText('"icao":null');
  }
}

// Load a single airport
class LoadAirportByApidTest extends WebTestCase {
  public function test() {
    global $webroot, $airport, $new_apid;

    login($this);
    $params = array("apid" => $new_apid,
		    "action" => "LOAD");
    $json = json_decode($this->post($webroot . "php/apsearch.php", $params), true);
    $this->assertEqual($json['status'], '1');
    $this->assertEqual($json['max'], 1);
    $this->assertText('"name":"' . $airport["name"] . ' Edited"');
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

// Load a single airport
class LoadAirportByInvalidApidTest extends WebTestCase {
  public function test() {
    global $webroot, $airport;

    $params = array("apid" => "garbage",
		    "action" => "LOAD");
    $json = json_decode($this->post($webroot . "php/apsearch.php", $params), true);
    $this->assertEqual($json['status'], '0');
  }
}

// Search OpenFlights DB by IATA (of just-added airport)
class SearchAirportOFDBByIATATest extends WebTestCase {
  public function test() {
    global $webroot, $airport;

    login($this);
    $params = array("iata" => $airport["iata"]);
    $json = json_decode($this->post($webroot . "php/apsearch.php", $params), true);
    $this->assertEqual($json['status'], '1');
    $this->assertText('"name":"' . $airport["name"] . ' Edited"');
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
