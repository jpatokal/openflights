<?php
require_once(dirname(__FILE__) . '/simpletest/autorun.php');
require_once(dirname(__FILE__) . '/simpletest/web_tester.php');
include_once(dirname(__FILE__) . '/config.php');

// Fetch route map for core airport
class RouteMapCoreAirportTest extends WebTestCase {
  function test() {
    global $webroot, $route;

    // First figure out the correct results
    $db = db_connect();
    $sql = "SELECT * FROM routes WHERE src_ap='" . $route["core_iata"] . "'";
    $result = mysql_query($sql, $db);
    $rows = mysql_num_rows($result);
    $this->assertTrue($rows >= 1, "No routes found");
    $route["routes"] = $rows;
    if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
      $route["core_apid"] = $row["src_apid"];
    }

    // Then test
    $params = array("apid" => $route["core_apid"]);
    $map = $this->post($webroot . "php/routes.php", $params);
    $rows = preg_split('/\n/', $map);

    // N;Airport name...
    $this->assertPattern("/" . $route["routes"] . ';/', $rows[0]);

    // Routes (N)
    $rts = preg_split('/\t/', $rows[1]);
    $this->assertTrue(sizeof($rts) == $route["routes"], "Route count");

    // Airports (N+1)
    $aps = preg_split('/\t/', $rows[2]);
    $this->assertTrue(sizeof($aps) == $route["routes"] + 1, "Airport count");
  }
}

class RouteMapNoRouteAirportTest extends WebTestCase {
  function test() {
    global $webroot, $route;

    // First figure out the correct apid
    $db = db_connect();
    $sql = "SELECT * FROM airports WHERE iata='" . $route["noroute_iata"] . "'";
    $result = mysql_query($sql, $db);
    $route["routes"] = $rows;
    $this->assertTrue($row = mysql_fetch_array($result, MYSQL_ASSOC), "No-route airport not found");
    $route["noroute_apid"] = $row["apid"];

    // Then test
    $params = array("apid" => $route["noroute_apid"]);
    $map = $this->post($webroot . "php/routes.php", $params);
    $rows = preg_split('/\n/', $map);

    // N;Airport name...
    $this->assertPattern("/0;/", $rows[0]);

    // Routes (N)
    $this->assertTrue($rows[1] == "", "Route count");

    // Airports (N+1)
    $aps = preg_split('/\t/', $rows[2]);
    $this->assertTrue(sizeof($aps) == 1, "Airport count");
  }
}

class RouteMapInvalidAirportTest extends WebTestCase {
  function test() {
    global $webroot, $route;

    $params = array("apid" => $route["invalid_apid"]);
    $map = $this->post($webroot . "php/routes.php", $params);
    $this->assertText('Error');
  }
}

?>
