<?php
require_once(dirname(__FILE__) . '/simpletest/autorun.php');
require_once(dirname(__FILE__) . '/simpletest/web_tester.php');
include_once(dirname(__FILE__) . '/config.php');

// Not an actual test, just cleaning up
class DeleteFlightsTest extends WebTestCase {
  function test() {
    global $settings;

    $db = db_connect();
    $sql = "DELETE FROM flights WHERE uid IN (SELECT uid FROM users WHERE name='" . $settings["name"] . "')";
    $result = mysql_query($sql, $db);
    $this->assertTrue(mysql_affected_rows() >= 1, "Flights deleted");
  }
}

// Not an actual test, just cleaning up
class DeleteAirportTest extends WebTestCase {
  function test() {
    global $settings;

    $db = db_connect();
    $sql = "DELETE FROM airports WHERE uid IN (SELECT uid FROM users WHERE name='" . $settings["name"] . "')";
    $result = mysql_query($sql, $db);
    $this->assertTrue(mysql_affected_rows() >= 1, "Airport(s) deleted");
  }
}

// Not an actual test, just cleaning up
class DeleteAirlinesTest extends WebTestCase {
  function test() {
    global $settings;

    $db = db_connect();
    $sql = "DELETE FROM airlines WHERE uid IN (SELECT uid FROM users WHERE name='" . $settings["name"] . "')";
    $result = mysql_query($sql, $db);
    $this->assertTrue(mysql_affected_rows() >= 1, "Airline(s) deleted");
  }
}

// Not an actual test, just cleaning up
class DeleteUserTest extends WebTestCase {
  function test() {
    global $settings;

    $db = db_connect();
    $sql = "DELETE FROM users WHERE name='" . $settings["name"] . "'";
    $result = mysql_query($sql, $db);
    $this->assertTrue(mysql_affected_rows() == 1, "User deleted");
  }
}

?>
