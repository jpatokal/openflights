<?php
require_once(dirname(__FILE__) . '/simpletest/autorun.php');
require_once(dirname(__FILE__) . '/simpletest/web_tester.php');
include_once(dirname(__FILE__) . '/config.php');

//
// Test cases for php/flights.php

// Check demo user map
class BlockAnonExportCase extends WebTestCase {
  function test() {
    global $webroot;

    $params = array("export" => "true");
    $this->get($webroot . "php/flights.php", $params);
    $this->assertText("You must be logged in to export.");
  }
}

?>
