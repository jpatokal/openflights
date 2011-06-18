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
class RecordAirportNotLoggedInTest extends WebTestCase {
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
class RecordAirportDuplicateTest extends WebTestCase {
  function test() {
    global $webroot, $settings, $airport;

    login($this);
    $params = $airport;
    $params += array("action" => "RECORD", "unittest" => "true");
    $msg = $this->post($webroot . "php/apsearch.php", $params);

    $this->assertText('From: info+apsearch@openflights.org');
    $this->assertText('Reply-To: test@openflights.example');
    $this->assertText('New edit submitted by autotest (test@openflights.example):');
    $this->assertText("INSERT INTO airports(name,city,country,iata,icao,x,y,elevation,timezone,dst,uid) VALUES('AutoTest Airpor");
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
    $params += array("action" => "RECORD", "unittest" => "true");
    $msg = $this->post($webroot . "php/apsearch.php", $params);

    $this->assertText('From: info+apsearch@openflights.org');
    $this->assertText('Reply-To: test@openflights.example');
    $this->assertText('New edit submitted by autotest (test@openflights.example):');
    $this->assertText("UPDATE airports SET ");
    $this->assertText("icao='ZZZY'");
    $this->assertText("[icao] => " . $airports["icao"]);
  }
}

// Try to reuse an existing airport's code
class EditAirportDuplicateICAOTest extends WebTestCase {
  function test() {
    global $webroot, $settings, $airport, $new_apid;

    login($this);
    $params = $airport;
    $params["icao"] = "WSSS"; // existing airport (Singapore)
    $params["apid"] = $new_apid;
    $params += array("action" => "RECORD", "unittest" => "true");
    $msg = $this->post($webroot . "php/apsearch.php", $params);
    $this->assertText('From: info+apsearch@openflights.org');
    $this->assertText('Reply-To: test@openflights.example');
    $this->assertText('New edit submitted by autotest (test@openflights.example):');
    $this->assertText("UPDATE airports SET name='AutoTest Airport', city='Testville', country='Testland', iata='ZZZ', icao='WSSS'");
    $this->assertText("[name] => Changi Intl");
  }
}

// Try to edit to overwrite existing airport
class EditAirportSuccessfulTest extends WebTestCase {
  function test() {
    global $webroot, $settings, $airport, $new_apid;

    login($this);
    $params = $airport;
    $params["apid"] = $new_apid;
    $params["name"] = $airport["name"] . " Edited";
    $params += array("action" => "RECORD");
    $msg = $this->post($webroot . "php/apsearch.php", $params);
    $this->assertText('1;');
  }
}

// Load a single airport
class LoadAirportByApidTest extends WebTestCase {
  function test() {
    global $webroot, $airport, $new_apid;

    login($this);
    $params = array("apid" => $new_apid,
		    "action" => "LOAD");
    $msg = $this->post($webroot . "php/apsearch.php", $params);
    $this->assertText('0;1');
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

// Search OpenFlights DB by IATA (of just-added airport)
class SearchAirportOFDBByIATATest extends WebTestCase {
  function test() {
    global $webroot, $airport;

    login($this);
    $params = array("iata" => $airport["iata"]);
    $msg = $this->post($webroot . "php/apsearch.php", $params);
    $this->assertText('0;1');
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

?>
