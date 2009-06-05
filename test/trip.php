<?php
require_once(dirname(__FILE__) . '/simpletest/autorun.php');
require_once(dirname(__FILE__) . '/simpletest/web_tester.php');
include_once(dirname(__FILE__) . '/config.php');

//
// Test cases for php/trip.php
//

$trid = null;

// Create new trip without logging in
class NewTripNotLoggedInTest extends WebTestCase {
  function test() {
    global $webroot, $trip;

    $trip["type"] = "NEW";
    $this->post($webroot . "php/trip.php", $trip);
    $this->assertText('0;');
  }
}

// Create new trip
class SuccessfulNewTripTest extends WebTestCase {
  function test() {
    global $webroot, $trip, $trid;

    login($this);
    $trip["type"] = "NEW";
    $msg = $this->post($webroot . "php/trip.php", $trip);
    $this->assertText('1;');

    $cols = preg_split('/[;\n]/', $msg);
    $trid = $cols[1];
  }
}

// Try to edit when not logged in
class EditTripWithoutLoggingInTest extends WebTestCase {
  function test() {
    global $webroot, $trip, $trid;

    $trip["type"] = "EDIT";
    $trip["trid"] = $trid;
    $this->post($webroot . "php/trip.php", $trip);
    $this->assertText('0;');
  }
}

// Try to manipulate wrong trip id
class EditWrongTridTripTest extends WebTestCase {
  function test() {
    global $webroot, $trip;

    login($this);
    $trip["type"] = "EDIT";
    $trip["trid"] = -1;
    $this->post($webroot . "php/trip.php", $trip);
    $this->assertText('0;');
  }
}

// Change trip settings
class SuccessfulEditTripTest extends WebTestCase {
  function test() {
    global $webroot, $trip, $trid;

    login($this);
    $params = array("trid" => $trid,
		    "type" => "EDIT",
		    "name" => "New AutoTest Trip",
		    "url" => "http://new.autotest.example",
		    "privacy" => "N");
    $msg = $this->post($webroot . "php/trip.php", $params);
    $this->assertText('2;');

    // Validate changes
    $db = db_connect();
    $sql = "SELECT * FROM trips WHERE trid=" . $trid;
    $result = mysql_query($sql, $db);
    $row = mysql_fetch_array($result);
    $this->assertTrue($row["name"] == "New AutoTest Trip", "Name");
    $this->assertTrue($row["public"] == "N", "Public");
    $this->assertTrue($row["url"] == "http://new.autotest.example", "URL");
  }
}

// Delete trip
class DeleteTripTest extends WebTestCase {
  function test() {
    global $webroot, $trip, $trid;

    login($this);
    $trip["type"] = "DELETE";
    $trip["trid"] = $trid;
    $msg = $this->post($webroot . "php/trip.php", $trip);
    $this->assertText('100;');

    // Verify
    $db = db_connect();
    $sql = "SELECT * FROM trips WHERE trid=" . $trid;
    $result = mysql_query($sql, $db);
    $this->assertFalse(mysql_fetch_array($result), "Deleting failed");
  }
}

?>
