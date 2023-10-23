<?php
include_once dirname(__FILE__) . '/config.php';

// Test cases for php/submit.php plane related functionality
// NB: Assumes the test user exists and there are no flights entered yet (run settings.php first!)

$plid = null; // global for newly added flight

/**
 * Add a new plane
 */
class AddNewPlaneTest extends WebTestCase {
    public function test() {
        global $webroot, $settings, $flight, $plid;

        assert_login($this);

        $dbh = db_connect();

        $flight2 = $flight; // this creates a new array copy because PHP is cray-cray
        $flight2["plane"] = "Boeingbus XYZ";
        $flight2["note"] = "Firstplane";
        $msg = $this->post($webroot . "php/submit.php", $flight2);
        $this->assertText('1;');

        // Check the PLID of the newly-added flight
        $sth = $dbh->prepare("SELECT plid FROM flights WHERE note = ?");
        $sth->execute([$flight2["note"]]);
        $row = $sth->fetch();
        $plid = $row["plid"];
        $this->assertTrue($plid != null && $plid != "");

        // Add a new flights with the same plane, but with random whitespace
        $flight3 = $flight2;
        $flight3["plane"] = "   Boeingbus XYZ   ";
        $flight3["note"] = "Secondplane";
        $msg = $this->post($webroot . "php/submit.php", $flight3);
        $this->assertText('1;');

        // Check that new plid was reused
        $sth = $dbh->prepare("SELECT plid FROM flights WHERE note = ?");
        $sth->execute([$flight3["note"]]);
        $row = $sth->fetch();
        $plid2 = $row["plid"];
        $this->assertEqual($plid, $plid2);
    }
}

/**
 * Not an actual test, just cleaning up
 */
class DeleteExtraPlanesTest extends WebTestCase {
    public function test() {
        global $settings, $plid;

        $dbh = db_connect();

        $sth = $dbh->prepare("DELETE FROM flights WHERE plid = ?");
        $sth->execute([$plid]);
        $this->assertTrue($sth->rowCount() >= 1, "Flight deleted");

        $sth = $dbh->prepare("DELETE FROM planes WHERE name = 'Boeingbus XYZ'");
        $sth->execute();
        $this->assertTrue($sth->rowCount() >= 1, "Plane deleted");
    }
}
