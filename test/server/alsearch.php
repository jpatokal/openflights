<?php
include_once(dirname(__FILE__) . '/config.php');

// Store temporary airline, railway ID
$new_alid = null;
$new_rlid = null;

// Try to add airline before logging in
class RecordAirlineNotLoggedInTest extends WebTestCase {
  public function test() {
    global $webroot, $settings;

    $params = array("action" => "RECORD");
    $msg = $this->post($webroot . "php/alsearch.php", $params);
    $this->assertText("0;");
  }
}

// Try to reuse an existing airline's code
class RecordAirlineDuplicateICAOTest extends WebTestCase {
  public function test() {
    global $webroot, $settings, $airline, $new_alid;

    login($this);
    $params = $airline;
    $params["icao"] = "SIA"; // existing airline (Singapore Airlines)
    $params += array("action" => "RECORD");
    $msg = $this->post($webroot . "php/alsearch.php", $params);
    $this->assertText('0;');
  }
}

// Add new airline
class RecordNewAirlineTest extends WebTestCase {
  public function test() {
    global $webroot, $settings, $airline, $new_alid;

    login($this);
    $params = $airline;
    $params["action"] = "RECORD";
    $msg = $this->post($webroot . "php/alsearch.php", $params);
    $this->assertText('1;');

    $cols = preg_split('/[;\n]/', $msg);
    if($cols[0] == "1") {
      $new_alid = $cols[1];
    }
  }
}

// Try to record it again
class RecordAirlineDuplicateTest extends WebTestCase {
  public function test() {
    global $webroot, $settings, $airline, $new_alid;

    login($this);
    $params = $airline;
    $params += array("action" => "RECORD");
    $msg = $this->post($webroot . "php/alsearch.php", $params);
    $this->assertText('0;');
  }
}

// Add new railway
class RecordNewRailwayTest extends WebTestCase {
  public function test() {
    global $webroot, $settings, $railway, $new_rlid;

    login($this);
    $params = $railway;
    $params["action"] = "RECORD";
    $msg = $this->post($webroot . "php/alsearch.php", $params);
    $this->assertText('1;');

    $cols = preg_split('/[;\n]/', $msg);
    $new_rlid = $cols[1];
  }
}

// Try to add the railway again
class RecordRailwayDuplicateTest extends WebTestCase {
  public function test() {
    global $webroot, $settings, $railway;

    login($this);
    $params = $railway;
    $params["action"] = "RECORD";
    $msg = $this->post($webroot . "php/alsearch.php", $params);
    $this->assertText('0;');
  }
}

// Try to edit an airline not belonging to us
class EditWrongAirlineTest extends WebTestCase {
  public function test() {
    global $webroot, $settings, $airline;

    login($this);
    $params = $airline;
    $params["alid"] = 1; // this is presumably not owned by us
    $params["icao"] = "ZZZY";
    $params += array("action" => "RECORD");
    $msg = $this->post($webroot . "php/alsearch.php", $params);
    $this->assertText('0;');
  }
}

// Try to reuse an existing airline's code
class EditAirlineDuplicateICAOTest extends WebTestCase {
  public function test() {
    global $webroot, $settings, $airline, $new_alid;

    login($this);
    $params = $airline;
    $params["icao"] = "SIA"; // existing airline (Singapore Airlines)
    $params["alid"] = $new_alid;
    $params += array("action" => "RECORD");
    $msg = $this->post($webroot . "php/alsearch.php", $params);
    $this->assertText('0;');
  }
}

// Try to edit to overwrite existing airline
class EditAirlineSuccessfulTest extends WebTestCase {
  public function test() {
    global $webroot, $settings, $airline, $new_alid;

    login($this);
    $params = $airline;
    $params["alid"] = $new_alid;
    $params["name"] = $airline["name"] . " Edited";
    $params += array("action" => "RECORD");
    $msg = $this->post($webroot . "php/alsearch.php", $params);
    $this->assertText('1;');
  }
}


// Search by IATA
class SearchAirlineByIATATest extends WebTestCase {
  public function test() {
    global $webroot, $airline;

    login($this);
    $params = array("iata" => $airline["iata"]);
    $msg = $this->post($webroot . "php/alsearch.php", $params);
    $this->assertText('0;1');
    $this->assertText('"name":"' . $airline["name"] . ' Edited"');
    $this->assertText('"alias":"' . $airline["alias"] . '"');
    $this->assertText('"iata":"' . $airline["iata"] . '"');
    $this->assertText('"icao":"'. $airline["icao"] . '"');
    $this->assertText('"mode":"'. $airline["mode"] . '"');
    $this->assertText('"active":"'. $airline["active"] . '"');
    $this->assertText('"callsign":"'. $airline["callsign"] . '"');
  }
}

// Search by ICAO
class SearchAirlineByICAOTest extends WebTestCase {
  public function test() {
    global $webroot, $airline;

    login($this);
    $params = array("icao" => $airline["icao"]);
    $msg = $this->post($webroot . "php/alsearch.php", $params);
    $this->assertText('0;1');
    $this->assertText('"name":"' . $airline["name"] . ' Edited"');
    $this->assertText('"alias":"' . $airline["alias"] . '"');
    $this->assertText('"iata":"' . $airline["iata"] . '"');
    $this->assertText('"icao":"'. $airline["icao"] . '"');
  }
}

// Search railway by name
class SearchRailwayByNameTest extends WebTestCase {
  public function test() {
    global $webroot, $railway;

    login($this);
    $params = array("name" => $railway["name"],
      "mode" => "T");
    $msg = $this->post($webroot . "php/alsearch.php", $params);
    $this->assertText('0;1');
    $this->assertText('"name":"' . $railway["name"] . '"');
    $this->assertText('"alias":"' . $railway["alias"] . '"');
  }
}
