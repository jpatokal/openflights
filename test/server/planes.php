<?php
include_once(dirname(__FILE__) . '/config.php');

//
// Test cases for php/submit.php plane related functionality
// NB: Assumes the test user exists and there are no flights entered yet (run settings.php first!)

$plid = null; // global for newly-added flight

// Add a new plane
class AddNewPlaneTest extends WebTestCase {
  function test() {
    global $webroot, $settings, $flight, $plid;

    assert_login($this);

    $flight2 = $flight; // this creates a new array copy because PHP is cray-cray
    $flight2["plane"] = "Boeingbus XYZ";
    $flight2["note"] = "Firstplane";
    $msg = $this->post($webroot . "php/submit.php", $flight2);
    $this->assertText('1;');

    // Check the PLID of the newly-added flight
    $db = db_connect();
    $sql = "SELECT plid FROM flights WHERE note='" . addslashes($flight2["note"]) . "'";
    $result = mysql_query($sql, $db);
    $row = mysql_fetch_assoc($result);
    $plid = $row["plid"];
    $this->assertTrue($plid != null && $plid != "");

    // Add a new flights with the same plane, but with random whitespace
    $flight3 = $flight2;
    $flight3["plane"] = "   Boeingbus XYZ   ";
    $flight3["note"] = "Secondplane";
    $msg = $this->post($webroot . "php/submit.php", $flight3);
    $this->assertText('1;');

    // Check that new plid was reused
    $db = db_connect();
    $sql = "SELECT plid FROM flights WHERE note='" . addslashes($flight3["note"]) . "'";
    $result = mysql_query($sql, $db);
    $row = mysql_fetch_assoc($result);
    $plid2 = $row["plid"];
    $this->assertEqual($plid, $plid2);
  }
}

// Not an actual test, just cleaning up
class DeleteExtraPlanesTest extends WebTestCase {
  function test() {
    global $settings, $plid;

    $db = db_connect();
    $sql = "DELETE FROM flights WHERE plid = " . $plid;
    $result = mysql_query($sql, $db);
    $this->assertTrue(mysql_affected_rows() >= 1, "Flight deleted");

    $db = db_connect();
    $sql = "DELETE FROM planes WHERE name = 'Boeingbus XYZ'";
    $result = mysql_query($sql, $db);
    $this->assertTrue(mysql_affected_rows() >= 1, "Plane deleted");
  }
}


?>
