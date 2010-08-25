<?php
require_once(dirname(__FILE__) . '/simpletest/autorun.php');
require_once(dirname(__FILE__) . '/simpletest/web_tester.php');
include_once(dirname(__FILE__) . '/config.php');

//
// Test cases for php/stats.php
// NB: Assumes the test user exists and flights.php has been run, so that $flight2[] is already in DB
//

$fid = null; // global for newly-added flight

// Check Analyse stats
class CheckAnalyseStats extends WebTestCase {
  function test() {
    global $webroot, $settings, $flight2, $fid;

    login($this);
    $this->assertText("1;");

    $stats = $this->post($webroot . "php/stats.php");

    // Uniques
    $this->assertText('"num_airports":"2"');
    $this->assertText('"num_countries":"1"');
    $this->assertText('"num_airlines":"1"');
    $this->assertText('"num_planes":"1"');
    $this->assertText('"distance":"' . $flight2["distance"] . '"');
    $this->assertText('"avg_distance":"' . $flight2["distance"]); // ignore localized bit at end
    $this->assertText('"avg_duration":"' . $flight2["duration"] . '"');
  }
}

?>
