<?php

include_once dirname(__FILE__) . '/config.php';

// Test cases for php/import.php
// NB: Assumes the test user exists

class ImportUnknownFiletypeTest extends WebTestCase {
    public function test() {
        cleanup();

        $result = login($this);
        $this->assertEqual($result->status, "1");

        upload_fixture($this, "fm-standard.html", "XYZ");
        $this->assertText("Unknown file type XYZ");
    }
}

class ImportCSVRoundtripTest extends WebTestCase {
    public function test() {
        cleanup();

        $result = login($this);
        $this->assertEqual($result->status, "1");

        upload_fixture($this, "fm-standard.csv", "CSV");
        $this->assertText("Flights successfully imported");

        export_to_csv_and_validate($this, "fm-standard.csv");
    }
}

class ImportCSVWithVariantsTest extends WebTestCase {
    public function test() {
        cleanup();

        $result = login($this);
        $this->assertEqual($result->status, "1");

        upload_fixture($this, "of-variants.csv", "CSV");
        $this->assertText("Flights successfully imported");

        export_to_csv_and_validate($this, "of-variants-parsed.csv");
    }
}

class ImportCSVWithCROnlyTerminators extends WebTestCase {
    public function test() {
        cleanup();

        $result = login($this);
        $this->assertEqual($result->status, "1");

        upload_fixture($this, "cr-only.csv", "CSV");
        $this->assertText("Flights successfully imported");
    }
}

// Import a normal set of FM flights
class ImportFlightMemoryStandardTest extends WebTestCase {
    public function test() {
        cleanup();

        $result = login($this);
        $this->assertEqual($result->status, "1");

        upload_fixture($this, "fm-standard.html", "FM");
        $this->assertText("Flights successfully imported");

        export_to_csv_and_validate($this, "fm-standard.csv");
    }
}

// Import a set of FM flights with data in km
class ImportFlightMemoryKilometerTest extends WebTestCase {
    public function test() {
        cleanup();

        $result = login($this);
        $this->assertEqual($result->status, "1");

        upload_fixture($this, "fm-kilometer.html", "FM");
        $this->assertText("Flights successfully imported");

        export_to_csv_and_validate($this, "fm-kilometer.csv");
    }
}

// Import a set of FM flights with Latin1 data
class ImportFlightMemoryLatin1Test extends WebTestCase {
    public function test() {
        cleanup();

        $result = login($this);
        $this->assertEqual($result->status, "1");

        upload_fixture($this, "fm-latin1.html", "FM");
        $this->assertText("Flights successfully imported");

        export_to_csv_and_validate($this, "fm-latin1.csv");
    }
}

// Import a set of FM flights with new airlines
class ImportFlightMemoryNewAirlinesTest extends WebTestCase {
    public function test() {
        cleanup();

        $result = login($this);
        $this->assertEqual($result->status, "1");

        upload_fixture($this, "fm-newairlines.html", "FM");
        $this->assertText("Flights successfully imported");

        export_to_csv_and_validate($this, "fm-newairlines.csv");
    }
}

// Import a set of FM flights using slightly tweaked 2015 format
class ImportFlightMemoryNewStyleTest extends WebTestCase {
    public function test() {
        cleanup();

        $result = login($this);
        $this->assertEqual($result->status, "1");

        upload_fixture($this, "fm-newstyle.html", "FM");
        $this->assertText("Flights successfully imported");

        export_to_csv_and_validate($this, "fm-newstyle.csv");
    }
}

function upload_fixture($context, $fixture, $filetype) {
    global $webroot, $uploadDir;

    $context->assertTrue(copy("./fixtures/" . $fixture, $uploadDir . $fixture));
    $opts = array('action' => 'Import', 'tmpfile' => $fixture, 'fileType' => $filetype);
    return $context->post($webroot . "php/import.php", $opts);
}

function export_to_csv_and_validate($context, $fixture) {
    global $webroot, $uploadDir;

    $expected_csv = sort_string(file_get_contents("./fixtures/" . $fixture));
    $params = array("export" => "export");
    $csv = $context->get($webroot . "php/flights.php", $params);
    $csv = sort_string($csv);
    #file_put_contents("gen-" . $fixture, $csv); # DEBUG
    #file_put_contents("expected-" . $fixture, $expected_csv); # DEBUG

    $context->assertEqual($csv, $expected_csv);
}

function sort_string($string) {
    $array = preg_split("/\r\n|\n/", $string);
    sort($array);
    return implode("\n", $array);
}
