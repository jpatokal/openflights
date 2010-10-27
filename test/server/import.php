<?php
require_once(dirname(__FILE__) . '/simpletest/autorun.php');
require_once(dirname(__FILE__) . '/simpletest/web_tester.php');
include_once(dirname(__FILE__) . '/config.php');

// Test cases for php/import.php
// NB: Assumes the test user exists

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
    global $settings;
    cleanup();

    login($this);
    $this->assertText("1;");

    upload_fixture($this, "fm-standard.html", "FM");
    $this->assertText("Flights successfully imported");

    export_to_csv_and_validate($this, "fm-standard.csv");
  }
}

function upload_fixture($context, $fixture, $filetype) {
  global $webroot, $uploaddir;

    $context->assertTrue(copy("./fixtures/" . $fixture, $uploaddir . $fixture));
    $opts = array('action'=>'Import', 'tmpfile'=>$fixture, 'fileType' => $filetype);
    return $context->post($webroot . "php/import.php", $opts);
}

function export_to_csv_and_validate($context, $fixture) {
  global $webroot, $uploaddir;

  $expected_csv = file_get_contents("./fixtures/" . $fixture);
  $params = array("export" => "export");
  $csv = $context->get($webroot . "php/flights.php", $params);

  #Only for generating initial test fixtures...
  #file_put_contents("./fixtures/" . $fixture, $csv);

  $context->assertEqual($csv, $expected_csv);  
}
