<?php

include_once(dirname(__FILE__) . '/config.php');

//
// Test cases for php/settings.php
// NB: Assumes there is no existing test user (run cleanup.php first!)
//

// Create new user
class SuccessfulNewUserTest extends WebTestCase {
    public function test() {
        global $webroot, $settings;

        $hash = md5($settings["password"] . strtolower($settings["name"]));
        $settings["type"] = "NEW";
        $settings["pw"] = $hash;
        $this->post($webroot . "php/settings.php", $settings);
        $this->assertText('1;');
    }
}

// Duplicate name
class DuplicateUserTest extends WebTestCase {
    public function test() {
        global $webroot, $settings;

        $hash = md5($settings["password"] . strtolower($settings["name"]));
        $settings["type"] = "NEW";
        $settings["pw"] = $hash;
        $this->post($webroot . "php/settings.php", $settings);
        $this->assertText('0;');
    }
}

// Try to manipulate without session
class LoadEditResetWithoutSessionTest extends WebTestCase {
    public function test() {
        global $webroot;

        $params = array("type" => "EDIT");
        $this->post($webroot . "php/settings.php", $params);
        $this->assertText('0;');

        $params = array("type" => "RESET");
        $this->post($webroot . "php/settings.php", $params);
        $this->assertText('0;');
    }
}

// Try to change pw, but give wrong password
class ChangePasswordWithWrongOldPasswordTest extends WebTestCase {
    public function test() {
        global $webroot, $settings;

        login($this);
        $params = array(
            "type" => "EDIT",
            "pw" => "newpw",
            "oldpw" => "incorrect"
        );
        $msg = $this->post($webroot . "php/settings.php", $params);
        $this->assertText('0;');

        // This should fail
        $result = login($this, $settings["name"], "newpw");
        $this->assertEqual($result->status, "0");
    }
}

// Change password
class ChangePasswordTest extends WebTestCase {
    public function test() {
        global $webroot, $settings;

        login($this);
        $oldhash = md5($settings["password"] . strtolower($settings["name"]));
        $newhash = md5("newpw" . strtolower($settings["name"]));
        $params = array(
            "type" => "EDIT",
            "oldpw" => $oldhash,
            "pw" => $newhash
        );
        $msg = $this->post($webroot . "php/settings.php", $params);
        $this->assertText('2;');


        // Log out and validate new password
        $msg = $this->post($webroot . "php/logout.php");
        $result = login($this, $settings["name"], "newpw");
        $this->assertEqual($result->status, "1");

        // Change it back
        $params = array("type" => "EDIT",
        "oldpw" => $newhash,
        "pw" => $oldhash);
        $msg = $this->post($webroot . "php/settings.php", $params);
        $this->assertText('2;');
    }
}

// Change all other settings
class ChangeSettingsTest extends WebTestCase {
    public function test() {
        global $webroot, $settings;

        login($this);
        $params = array(
            "type" => "EDIT",
            "email" => "new@email.example",
            "privacy" => "N",
            "locale" => "fi_FI",
            "units" => "M",
            "editor" => "D"
        );
        $msg = $this->post($webroot . "php/settings.php", $params);
        $this->assertText('2;');

        // Validate changes
        $dbh = db_connect();
        $sth = $dbh->prepare("SELECT * FROM users WHERE name=?");
        $sth->execute([$settings["name"]]);
        $row = $sth->fetch();
        $this->assertTrue($row["public"] == $params["privacy"], "Public");
        $this->assertTrue($row["email"] == $params["email"], "Email");
        $this->assertTrue($row["editor"] == $params["editor"], "Editor");
        $this->assertTrue($row["units"] == $params["units"], "Units");
        $this->assertTrue($row["locale"] == $params["locale"], "Locale");
    }
}

// Restore original settings
class RestoreSettingsTest extends WebTestCase {
    public function test() {
        global $webroot, $settings;

        login($this);
        $settings["type"] = "EDIT";
        $msg = $this->post($webroot . "php/settings.php", $settings);
        $this->assertText('2;');
    }
}

// Reset (delete) all flights
class ResetFlightsTest extends WebTestCase {
    public function test() {
        global $webroot;

        login($this);
        $params = array("type" => "RESET");
        $msg = $this->post($webroot . "php/settings.php", $params);
        $this->assertText('10;');

        // Validate reset
        $map = $this->post($webroot . "php/map.php");
        $cols = preg_split('/[;\n]/', $map);
        $this->assertTrue($cols[0] == "0", "No flights recorded");
    }
}
