<?php
require_once(dirname(__FILE__) . '/simpletest/autorun.php');
require_once(dirname(__FILE__) . '/simpletest/web_tester.php');
include_once(dirname(__FILE__) . '/config.php');

//
// Test cases for php/signup.php
// NB: Assumes there is no existing test user (run cleanup.php first!)
//

// Create new user
class SuccessfulNewUserTest extends WebTestCase {
  function test() {
    global $webroot, $settings;

    $hash = md5($settings["password"] . strtolower($settings["username"]));
    $params = array("type" => "NEW",
		    "name" => $settings["username"],
		    "pw" => $hash,
		    "email" => $settings["email"],
		    "privacy" => $settings["privacy"],
		    "editor" => $settings["editor"]);
    $this->post($webroot . "php/signup.php", $params);
    $this->assertText('1;');
  }
}

// Duplicate username
class DuplicateUserTest extends WebTestCase {
  function test() {
    global $webroot, $settings;

    $hash = md5($settings["password"] . strtolower($settings["username"]));
    $params = array("type" => "NEW",
		    "name" => $settings["username"],
		    "pw" => $hash,
		    "email" => $settings["email"],
		    "privacy" => $settings["privacy"],
		    "editor" => $settings["editor"]);
    $this->post($webroot . "php/signup.php", $params);
    $this->assertText('0;');
  }
}

// Load user data without session
class LoadUserWithoutSessionTest extends WebTestCase {
  function test() {
    global $webroot, $settings;

    $params = array("type" => "LOAD");
    $this->post($webroot . "php/signup.php", $params);
    $this->assertText('0;');
  }
}

// Load user data without session
class LoadUserWithSessionTest extends WebTestCase {
  function test() {
    global $webroot, $settings;

    login($this);
    $params = array("type" => "LOAD");
    $msg = $this->post($webroot . "php/signup.php", $params);
    $this->assertText('3;');
    $this->assertText('"count":"0"');
    $this->assertText('"name":"' . $settings["username"] . '"');
    $this->assertText('"public":"' . $settings["privacy"] . '"');
    $this->assertText('"email":"' . $settings["email"] . '"');
    $this->assertText('"editor":"' . $settings["editor"] . '"');
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
    $msg = $this->post($webroot . "php/signup.php", $params);
    $this->assertText('0;');

    // This should fail
    login($this, $settings["username"], "newpw");
    $this->assertText('0;');
  }
}

// Change password
class ChangePasswordTest extends WebTestCase {
  function test() {
    global $webroot, $settings;

    login($this);
    $oldhash = md5($settings["password"] . strtolower($settings["username"]));
    $newhash = md5("newpw" . strtolower($settings["username"]));
    $params = array("type" => "EDIT",
		    "oldpw" => $oldhash,
		    "pw" => $newhash);
    $msg = $this->post($webroot . "php/signup.php", $params);
    $this->assertText('2;');

    // Validate new password
    login($this, $settings["username"], "newpw");
    $this->assertText('1;');

    // Change it back
    $params = array("type" => "EDIT",
		    "oldpw" => $newhash,
		    "pw" => $oldhash);
    $msg = $this->post($webroot . "php/signup.php", $params);
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
		    "editor" => "D");
    $msg = $this->post($webroot . "php/signup.php", $params);
    $this->assertText('2;');

    // Validate changes
    $params = array("type" => "LOAD");
    $msg = $this->post($webroot . "php/signup.php", $params);
    $this->assertText('3;');
    $this->assertText('"public":"N"');
    $this->assertText('"email":"new@email.example"');
    $this->assertText('"editor":"D"');
  }
}

?>
