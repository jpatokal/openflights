<?php
require_once(dirname(__FILE__) . '/simpletest/autorun.php');
require_once(dirname(__FILE__) . '/simpletest/web_tester.php');
include_once(dirname(__FILE__) . '/config.php');

//
// Test cases for php/login.php
//

// Standard log in
class SuccessfulLoginTest extends WebTestCase {
  function test() {
    global $webroot, $settings;
    login($this);
    $this->assertText('1;');
  }
}

// Legacy login test (where username hash was built using uppercase chars)
class LegacyLoginTest extends WebTestCase {
  function test() {
    global $webroot, $settings;

    $username = "LegacyUser";
    $password = "foobar";
    $hash = md5($password . $username);

    $db = db_connect();
    $sql = "INSERT INTO users(name,password) VALUES('$username','$hash')";
    $result = mysql_query($sql, $db);
    $this->assertTrue(mysql_affected_rows() == 1, "Legacy user added");

    login($this, $username, $password);
    $this->assertText('1;');

    $sql = "DELETE FROM users WHERE name='$username'";
    $result = mysql_query($sql, $db);
    $this->assertTrue(mysql_affected_rows() == 1, "Legacy user deleted");
  }
}

    $db = db_connect();

// Wrong password
class WrongPasswordLoginTest extends WebTestCase {
  function test() {
    global $webroot, $settings;
    login($this, $settings["username"], "incorrect");
    $this->assertText('0;');
  }
}
?>
