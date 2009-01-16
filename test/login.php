<?php
require_once(dirname(__FILE__) . '/simpletest/autorun.php');
require_once(dirname(__FILE__) . '/simpletest/web_tester.php');
include_once(dirname(__FILE__) . '/config.php');

//
// Test cases for php/login.php
//

// Standard log in
class SuccessfulLoginTest extends WebTestCase {
  function testHomepage() {
    global $webroot, $settings;
    login($this);
    $this->assertText('1;');
  }
}

// Wrong password
class WrongPasswordLoginTest extends WebTestCase {
  function testHomepage() {
    global $webroot, $settings;
    login($this, $settings["username"], "incorrect");
    $this->assertText('0;');
  }
}
?>
