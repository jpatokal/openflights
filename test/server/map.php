<?php

include_once(dirname(__FILE__) . '/config.php');

//
// Test cases for php/map.php
// NB 1: Assumes the test user exists and flights.php has been run, so that $flight2[] is already in DB
// NB 2: Trip map tests found under trip.php

// ##TODO## filters

// Check demo user map
class CheckDemoFullUserMap extends WebTestCase {
    public function test() {
        global $webroot, $settings, $flight2;

        $params = array("param" => "true");
        $map = $this->post($webroot . "php/map.php", $params);
        $rows = preg_split('/\n/', $map);
        $this->assertTrue(sizeof($rows) == 6, "Number of rows");

        // Statistics
        $stats = preg_split('/;/', $rows[0]);
        $this->assertTrue($stats[0] > 0, "No demo flights found -- did you add some flights and run sql/update-demo.sql?");
        $this->assertTrue($stats[3] == "O", "Public"); // demo flights always full access
        $this->assertTrue($stats[5] == "demo", "Username");
    }
}

// Check public profile for user
class CheckPublicFullUserMap extends WebTestCase {
    public function test() {
        global $webroot, $settings, $flight2;

        $params = array(
            "param" => "true",
            "user" => $settings["name"]
        );
        $map = $this->post($webroot . "php/map.php", $params);
        $rows = preg_split('/\n/', $map);
        $this->assertTrue(sizeof($rows) == 6, "Number of rows");

        // Statistics
        $stats = preg_split('/;/', $rows[0]);
        $this->assertTrue($stats[0] == 1, "Flight count");
        $this->assertTrue(strstr($stats[1], $flight2["distance"]), "Distance");
        $this->assertTrue($stats[3] == $settings["privacy"], "Public");
        $this->assertTrue($stats[4] == $settings["elite"], "Elite");
        $this->assertTrue($stats[5] == "demo", "Username"); // we are not this user!
    }
}

// Attempt to view private profile for user (fails)
class CheckPrivateNoPasswordFullUserMap extends WebTestCase {
    public function test() {
        global $webroot, $settings, $flight2;

        $dbh = db_connect();
        $sth = $dbh->prepare("UPDATE users SET public='N', guestpw=? WHERE name=?");
        $sth->execute([$settings["guestpw"], $settings["name"]]);
        $this->assertTrue($sth->rowCount() == 1, "Set profile to private");

        $params = array(
            "param" => "true",
            "guestpw" => "incorrect",
            "user" => $settings["name"]
        );
        $map = $this->post($webroot . "php/map.php", $params);
        $rows = preg_split('/\n/', $map);
        $stats = preg_split('/;/', $rows[0]);
        $this->assertTrue($stats[0] == "Error", "Private profile blocked");
    }
}

// View private profile with correct password
class CheckPrivateGuestPasswordFullUserMap extends WebTestCase {
    public function test() {
        global $webroot, $settings, $flight2;

        $params = array("param" => "true",
        "guestpw" => $settings["guestpw"],
        "user" => $settings["name"]);
        $map = $this->post($webroot . "php/map.php", $params);
        $rows = preg_split('/\n/', $map);

        // Statistics
        $stats = preg_split('/;/', $rows[0]);
        $this->assertTrue($stats[0] == 1, "Flight count");
        $this->assertTrue(strstr($stats[1], $flight2["distance"]), "Distance");
        $this->assertTrue($stats[3] == "N", "Public");
        $this->assertTrue($stats[4] == $settings["elite"], "Elite");
        $this->assertTrue($stats[5] == "demo", "Username"); // we are not this user!
    }
}

// Check logged in user map
class CheckLoggedInFullUserMap extends WebTestCase {
    public function test() {
        global $webroot, $settings, $flight2;

        assert_login($this);

        $params = array("param" => "true");
        $map = $this->post($webroot . "php/map.php", $params);
        $rows = preg_split('/\n/', $map);
        $this->assertTrue(sizeof($rows) == 6, "Number of rows");

        // Statistics
        $stats = preg_split('/;/', $rows[0]);
        $this->assertTrue($stats[0] == 1, "Flight count");
        $this->assertTrue(strstr($stats[1], $flight2["distance"]), "Distance");
        $this->assertTrue($stats[3] == "O", "Public"); // own flights always full access
        $this->assertTrue($stats[4] == $settings["elite"], "Elite");
        $this->assertTrue($stats[5] == $settings["name"], "Username");
        $this->assertTrue($stats[6] == $settings["editor"], "Editor");
    }
}
