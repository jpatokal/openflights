<?php
include_once(dirname(__FILE__) . '/config.php');

//
// Test cases for php/submit.php and php/flights.php
// NB: Assumes the test user exists and there are no flights entered yet (run settings.php first!)

$fid = null; // global for newly-added flight

// Try to add a flight when not logged in
class AddSingleFlightWithoutLoggingInTest extends WebTestCase {
  function test() {
    global $webroot, $settings, $flight, $fid;

    $msg = $this->post($webroot . "php/submit.php", $flight);
    $this->assertText('Not logged in');
  }
}

// Add loop flight (src==dest)
class AddLoopFlightTest extends WebTestCase {
  function test() {
    global $webroot, $settings, $loopflight;

    assert_login($this);

    $msg = $this->post($webroot . "php/submit.php", $loopflight);
    $this->assertText('1;');

    // Check that one flight was added
    $map = $this->post($webroot . "php/map.php");
    $cols = preg_split('/[;\n]/', $map);
    $this->assertTrue($cols[0] == "1", "Flight count should be 1");
  }
}

// Add multiple flights at once
class AddMultiFlightTest extends WebTestCase {
  function test() {
    global $webroot, $settings, $multiflight;

    assert_login($this);

    $msg = $this->post($webroot . "php/submit.php", $multiflight);
    $this->assertText('1;');

    // Check that flights were added
    $map = $this->post($webroot . "php/map.php");
    $cols = preg_split('/[;\n]/', $map);
    $this->assertTrue($cols[0] == 1 + $multiflight["multi"], "Flight count should be " . $multiflight["multi"] + 1);
  }
}

// Not an actual test, just cleaning up
class DeleteExtraFlightsTest extends WebTestCase {
  function test() {
    global $settings;

    $dbh = db_connect();
    $sth = $dbh->prepare("DELETE FROM flights WHERE uid IN (SELECT uid FROM users WHERE name=?)");
    $sth->execute([$settings["name"]]);
    $this->assertTrue($sth->rowCount() >= 1, "Flights deleted");
  }
}

// Add a single flight
class AddSingleFlightTest extends WebTestCase {
  function test() {
    global $webroot, $settings, $flight, $fid;

    assert_login($this);

    $msg = $this->post($webroot . "php/submit.php", $flight);
    $this->assertText('1;');

    // Check that one flight was added
    $map = $this->post($webroot . "php/map.php");
    $cols = preg_split('/[;\n]/', $map);
    $this->assertTrue($cols[0] == "1", "One flight recorded");

    // Get the ID of the newly-added flight
    $dbh = db_connect();
    $sth = $dbh->prepare("SELECT fid FROM flights WHERE note=?");
    $sth->execute([$flight["note"]]);
    $row = $sth->fetch();
    $fid = $row["fid"];
    $this->assertTrue($fid != null && $fid != "");
  }
}

// Fetch and validate newly-added flight
class FetchAddSingleFlightTest extends WebTestCase {
  function test() {
    global $webroot, $settings, $flight, $fid;

    assert_login($this);

    $params = array("fid" => $fid);
    $msg = $this->post($webroot . "php/flights.php", $params);
    $this->assertText($flight["src_date"]);
    $this->assertText($flight["src_apid"]);
    $this->assertText($flight["dst_apid"]);
    $this->assertText($flight["alid"]);
    $this->assertText($flight["duration"]);
    $this->assertText($flight["distance"]);
    $this->assertText($flight["number"]);
    $this->assertText($flight["plane"]);
    $this->assertText($flight["seat"]);
    $this->assertText($flight["type"]);
    $this->assertText($flight["class"]);
    $this->assertText($flight["reason"]);
    $this->assertText($flight["registration"]);
    $this->assertText($flight["note"]);
    $this->assertText($flight["src_time_formatted"]);
  }
}

// Edit new flight, altering all fields into flight2
class EditFlightTest extends WebTestCase {
  function test() {
    global $webroot, $settings, $flight2, $fid;

    assert_login($this);

    $params = $flight2;
    $params["fid"] = $fid;
    $msg = $this->post($webroot . "php/submit.php", $params);
    $this->assertText('2;');
  }
}

// Fetch and validate newly-added flight
class FetchEditedFlightTest extends WebTestCase {
  function test() {
    global $webroot, $settings, $flight2, $fid;

    assert_login($this);

    $params = array("fid" => $fid);
    $msg = $this->post($webroot . "php/flights.php", $params);
    $this->assertText($flight2["src_date"]);
    $this->assertText($flight2["src_apid"]);
    $this->assertText($flight2["dst_apid"]);
    $this->assertText($flight2["alid"]);
    $this->assertText($flight2["duration"]);
    $this->assertText($flight2["distance"]);
    $this->assertText($flight2["number"]);
    $this->assertText($flight2["plane"]);
    $this->assertText($flight2["seat"]);
    $this->assertText($flight2["type"]);
    $this->assertText($flight2["class"]);
    $this->assertText($flight2["reason"]);
    $this->assertText($flight2["registration"]);
    $this->assertText($flight2["note"]);
    $this->assertText($flight2["src_time"]);
  }
}

// CSV export and validate edited flight
class CSVExportFlightTest extends WebTestCase {
  function test() {
    global $webroot, $settings, $flight2, $fid;

    assert_login($this);

    $params = array("export" => "export");
    $msg = $this->get($webroot . "php/flights.php", $params);
    $this->assertText($flight2["src_date"] . " " . $flight2["src_time"] . ":00,");
    $this->assertText($flight2["alid"] . ",");
    $this->assertText($flight2["duration"] . ",");
    $this->assertText($flight2["distance"] . ",");
    $this->assertText($flight2["number"] . ",");
    $this->assertText($flight2["plane"] . ",");
    $this->assertText($flight2["seat"] . ",");
    $this->assertText($flight2["type"] . ",");
    $this->assertText($flight2["class"] . ",");
    $this->assertText($flight2["reason"] . ",");
    $this->assertText($flight2["registration"] . ",");
    $this->assertText($flight2["note"]); // may or may not be quote-wrapped
    $this->assertText($flight2["src_apid"] . ",");
    $this->assertText($flight2["dst_apid"] . ",");
    $this->assertText($flight2["alid"] . ",");
  }
}
