<?php
include_once(dirname(__FILE__) . '/config.php');

//
// Test cases for help/resetpw.php
//

$challenge = "";

class ResetPwInvalidRequestLinkTest extends WebTestCase {
  function test() {
    global $webroot, $settings;

    $reset = array('email' => $settings['email'] . "invalid",
		   'unittest' => 'true');
    $msg = $this->post($webroot . "help/resetpw.php", $reset);
    $this->assertText('Sorry, that e-mail address is not registered');
  }
}

class ResetPwValidRequestLinkTest extends WebTestCase {
  function test() {
    global $webroot, $settings, $challenge;

    $reset = array('email' => $settings['email'],
		   'unittest' => 'true');
    $msg = $this->post($webroot . "help/resetpw.php", $reset);
    $this->assertText('http://openflights.org/help/resetpw?user=' . $settings['name']);
    $chunks = explode("***", $msg);
    $challenge = $chunks[1];
  }
}

class ResetPwInvalidChallengeTest extends WebTestCase {
  function test() {
    global $webroot, $settings, $challenge;

    $reset = array('user' => $settings['name'],
		   'challenge' => $challenge . "XXX");
    $msg = $this->get($webroot . "help/resetpw.php", $reset);
    $this->assertText('Invalid challenge');
  }
}

class ResetPwValidChallengeTest extends WebTestCase {
  function test() {
    global $webroot, $settings, $challenge;

    $reset = array('user' => $settings['name'],
		   'challenge' => $challenge);
    $msg = $this->get($webroot . "help/resetpw.php", $reset);
    $this->assertText('Your new password');
    $chunks = preg_split("<b>", $msg);
    $pw = substr($chunks[1], 0, 8);

    $result = login($this, $settings["name"], $pw);
    $this->assertEqual($result->status, "0");
  }
}
?>
