<?php
require_once(dirname(__FILE__) . '/simpletest/autorun.php');
require_once(dirname(__FILE__) . '/simpletest/web_tester.php');
include_once(dirname(__FILE__) . '/config.php');

// Test cases for php/import.php
// NB: Assumes the test user exists

$fid = null; // global for newly-added flight

class ImportUnknownFiletypeTest extends WebTestCase {
  function test() {
    cleanup();

    login($this);
    $this->assertText("1;");

    upload_fixture($this, "fm-standard.html", "XYZ");
    $this->assertText("Unknown file type XYZ");
  }
}

// Import a normal set of flights
class ImportFlightMemoryStandardTest extends WebTestCase {
  function test() {
    cleanup();

    login($this);
    $this->assertText("1;");

    upload_fixture($this, "fm-standard.html", "FM");
    $this->assertText("Flights successfully imported");
  }
}

function upload_fixture($context, $fixture, $filetype) {
  global $webroot, $uploaddir;

    $context->assertTrue(copy("./fixtures/" . $fixture, $uploaddir . $fixture));
    $opts = array('action'=>'Import', 'tmpfile'=>$fixture, 'fileType' => $filetype);
    return $context->post($webroot . "php/import.php", $opts);
}
