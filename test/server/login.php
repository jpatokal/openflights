<?php
include_once(dirname(__FILE__) . '/config.php');

//
// Test cases for php/login.php
//

// Standard log in
class SuccessfulLoginTest extends WebTestCase {
  function test() {
    global $webroot, $settings;
    $result = login($this);
    $this->assertEqual($result->status, "1");
    $this->assertEqual($result->name, $settings['name']);
  }
}

// Legacy login test (where name hash was built using uppercase chars)
class LegacyLoginTest extends WebTestCase {
  function test() {
    global $webroot, $settings;

    $name = "LegacyUser";
    $password = "foobar";
    $hash = md5($password . $name);

    $dbh = db_connect();
    $sth = $dbh->prepare("INSERT INTO users(name,password) VALUES(?,?)");
    $sth->execute([$name, $hash]);
    $this->assertTrue($sth->rowCount() == 1, "Legacy user added");

    $result = login($this, $name, $password);
    $this->assertEqual($result->status, "1");

    $sth = $dbh->prepare("DELETE FROM users WHERE name=?");
    $sth->execute([$name]);
    $this->assertTrue($sth->rowCount() == 1, "Legacy user deleted");
  }
}

// Wrong password
class WrongPasswordLoginTest extends WebTestCase {
  function test() {
    global $webroot, $settings;
    $result = login($this, $settings["name"], "incorrect");
    $this->assertEqual($result->status, "0");
  }
}

// Login attempt with expired session
class ExpiredSessionTest extends WebTestCase {
  function test() {
    global $webroot, $settings;
    $result = login($this, $settings["name"], $settings["password"], "DEADBEEF");
    $this->assertEqual($result->status, "0");
    $this->assertText("Session expired");
  }
}
