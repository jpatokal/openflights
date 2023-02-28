<?php
include_once(dirname(__FILE__) . '/config.php');

//
// Test cases for php/flights.php

// Check demo user map
class BlockAnonExportCase extends WebTestCase {
  public function test() {
    global $webroot;

    $params = array("export" => "true");
    $this->get($webroot . "php/flights.php", $params);
    $this->assertText("You must be logged in to export.");
  }
}

class ExportAirlineToCSVCase extends WebTestCase {
  public function test() {
    global $webroot, $route;

    // First figure out the correct results
    $dbh = db_connect();
    $sth = $dbh->prepare("SELECT alid FROM airlines WHERE iata=?");
    $sth->execute([$route["core_al_iata"]]) or die($sth->errorInfo());
    if($row = $sth->fetch()) {
      $alid = $row["alid"];
    }

    $sth = $dbh->prepare("SELECT COUNT(*) AS count FROM routes WHERE alid=?");
    $sth->execute([$alid]) or die($sth->errorInfo());
    if($row = $sth->fetch()) {
      $route_count = intval($row["count"]);
    }

    // Then test
    assert_login($this);
    $params = array("export" => "export", "id" => "L" . $alid);
    $csv = $this->get($webroot . "php/flights.php", $params);
    $rows = explode("\n", $csv);
    $this->assertEqual(count($rows), $route_count + 1);
  }
}
