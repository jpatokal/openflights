<?php
require_once(dirname(__FILE__) . '/simpletest/autorun.php');
require_once(dirname(__FILE__) . '/simpletest/web_tester.php');
include_once(dirname(__FILE__) . '/config.php');

//
// Test cases for php/settings.php
// NB: Assumes there is no existing test user (run cleanup.php first!)
//

// Create new user
class SuccessfulNewUserTest extends WebTestCase {
  function test() {
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
  function test() {
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
  function test() {
    global $webroot, $settings;

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
  function test() {
    global $webroot, $settings;

    login($this);
    $params = array("type" => "EDIT",
		    "pw" => "newpw",
		    "oldpw" => "incorrect");
    $msg = $this->post($webroot . "php/settings.php", $params);
    $this->assertText('0;');

    // This should fail
    login($this, $settings["name"], "newpw");
    $this->assertText('0;');
  }
}

// Change password
class ChangePasswordTest extends WebTestCase {
  function test() {
    global $webroot, $settings;

    login($this);
    $oldhash = md5($settings["password"] . strtolower($settings["name"]));
    $newhash = md5("newpw" . strtolower($settings["name"]));
    $params = array("type" => "EDIT",
		    "oldpw" => $oldhash,
		    "pw" => $newhash);
    $msg = $this->post($webroot . "php/settings.php", $params);
    $this->assertText('2;');

    // Validate new password
    login($this, $settings["name"], "newpw");
    $this->assertText('1;');

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
  function test() {
    global $webroot, $settings;

    login($this);
    $params = array("type" => "EDIT",
		    "email" => "new@email.example",
		    "privacy" => "N",
		    "locale" => "fi_FI",
		    "editor" => "D");
    $msg = $this->post($webroot . "php/settings.php", $params);
    $this->assertText('2;');

    // Validate changes
    $db = db_connect();
    $sql = "SELECT * FROM users WHERE name='" . $settings["name"] . "'";
    $result = mysql_query($sql, $db);
    $row = mysql_fetch_array($result);
    $this->assertTrue($row["public"] == "N", "Public");
    $this->assertTrue($row["email"] == "new@email.example", "Email");
    $this->assertTrue($row["editor"] == "D", "Editor");
    $this->assertTrue($row["locale"] == "fi_FI", "Locale");
  }
}

// Reset (delete) all flights
class ResetFlightsTest extends WebTestCase {
  function test() {
    global $webroot, $settings;

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

?>
