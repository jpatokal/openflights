<?php
require_once(dirname(__FILE__) . '/simpletest/autorun.php');
require_once(dirname(__FILE__) . '/simpletest/web_tester.php');
include_once(dirname(__FILE__) . '/config.php');

// ID missing entirely
class RouteMapNoIDTest extends WebTestCase {
  function test() {
    global $webroot, $route;

    $map = $this->post($webroot . "php/routes.php");
    $this->assertText('Error');
  }
}

// Fetch route map for core airport
class RouteMapCoreAirportTest extends WebTestCase {
  function test() {
    global $webroot, $route;

    // First figure out the correct results
    $db = db_connect();
    $sql = "SELECT DISTINCT src_apid,dst_apid FROM routes WHERE src_ap='" . $route["core_ap_iata"] . "'";
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
    $this->assertTrue(sizeof($rts) == $route["routes"], "Route count" . sizeof($rts) . " vs " . $route["routes"]);
  }
}

// Fetch route map for core airport with airline filter
class RouteMapCoreAirportFilteredTest extends WebTestCase {
  function test() {
    global $webroot, $route;

    // First figure out the correct results
    $db = db_connect();
    $sql = "SELECT DISTINCT src_apid,dst_apid,alid FROM routes WHERE src_ap='" . $route["core_ap_iata"] . "' AND airline='" . $route["core_ap_filter_iata"] . "'";
    $result = mysql_query($sql, $db);
    $rows = mysql_num_rows($result);
    $this->assertTrue($rows >= 1, "No routes found");
    $route["routes"] = $rows;
    if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
      $route["core_apid"] = $row["src_apid"];
      $route["filter_alid"] = $row["alid"];
    }

    // Then test
    $params = array("apid" => $route["core_apid"],
		    "alid" => $route["filter_alid"]);
    $map = $this->post($webroot . "php/routes.php", $params);
    $rows = preg_split('/\n/', $map);

    // N;Airport name...
    $this->assertPattern("/" . $route["routes"] . ';/', $rows[0]);

    // Routes (N)
    $rts = preg_split('/\t/', $rows[1]);
    $this->assertTrue(sizeof($rts) == $route["routes"], "Route count" . sizeof($rts) . " vs " . $route["routes"]);
  }
}

// Airport with no routes -- should still display itself!
class RouteMapNoRouteAirportTest extends WebTestCase {
  function test() {
    global $webroot, $route;

    // First figure out the correct apid
    $db = db_connect();
    $sql = "SELECT * FROM airports WHERE iata='" . $route["noroute_ap_iata"] . "'";
    $result = mysql_query($sql, $db);
    $this->assertTrue($row = mysql_fetch_array($result, MYSQL_ASSOC), "No-route airport not found");
    $route["noroute_apid"] = $row["apid"];

    // Then test
    $params = array("apid" => $route["noroute_apid"]);
    $map = $this->post($webroot . "php/routes.php", $params);
    $rows = preg_split('/\n/', $map);

    // N;Airport name...
    $this->assertPattern("/0;/", $rows[0]);

    // No routes
    $this->assertTrue($rows[1] == "", "Route count");

    // One airport, with details
    $aps = preg_split('/\t/', $rows[2]);
    $this->assertTrue(sizeof($aps) == 1, "Airport count");
    $this->assertText(":" . $row["apid"] . ":" . $row["x"] . ":" . $row["y"]);
  }
}

// Invalid airport ID
class RouteMapInvalidAirportTest extends WebTestCase {
  function test() {
    global $webroot, $route;

    $params = array("apid" => $route["invalid_apid"]);
    $map = $this->post($webroot . "php/routes.php", $params);
    $this->assertText('Error');
  }
}

// Fetch route map for core airline
class RouteMapCoreAirlineTest extends WebTestCase {
  function test() {
    global $webroot, $route;

    // First figure out the correct results
    $db = db_connect();
    $sql = "SELECT DISTINCT src_apid,dst_apid,alid FROM routes WHERE airline='" . $route["core_al_iata"] . "' AND codeshare=''";
    $result = mysql_query($sql, $db);
    $rows = mysql_num_rows($result);
    $this->assertTrue($rows >= 1, "No routes found");
    $route["routes"] = $rows;
    if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
      $route["core_alid"] = $row["alid"];
    }

    // Then test
    $params = array("apid" => "L" . $route["core_alid"]);
    $map = $this->post($webroot . "php/routes.php", $params);
    $rows = preg_split('/\n/', $map);

    // N;Airline name...
    $this->assertPattern("/" . $route["routes"] . ';/', $rows[0]);

    // Routes (N)
    $rts = preg_split('/\t/', $rows[1]);
    $this->assertTrue(sizeof($rts) == $route["routes"], "Route count");
  }
}

// Fetch route map for core airline
class RouteMapCoreAirlineWithCodesharesTest extends WebTestCase {
  function test() {
    global $webroot, $route;

    // First figure out the correct results
    $db = db_connect();
    $sql = "SELECT DISTINCT src_apid,dst_apid,alid FROM routes WHERE airline='" . $route["core_al_iata"] . "'";
    $result = mysql_query($sql, $db);
    $rows = mysql_num_rows($result);
    $this->assertTrue($rows >= 1, "No routes found");
    $route["routes"] = $rows;
    if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
      $route["core_alid"] = $row["alid"];
    }

    // Then test
    $params = array("apid" => "L" . $route["core_alid"],
		    "alid" => "1"); // any non-zero alid triggers codeshares
    $map = $this->post($webroot . "php/routes.php", $params);
    $rows = preg_split('/\n/', $map);

    // N;Airline name...
    $this->assertPattern("/" . $route["routes"] . ';/', $rows[0]);

    // Routes (N)
    $rts = preg_split('/\t/', $rows[1]);
    $this->assertTrue(sizeof($rts) == $route["routes"], "Route count");
  }
}

// Airline with no routes
class RouteMapNoRouteAirlineTest extends WebTestCase {
  function test() {
    global $webroot, $route;

    // First figure out the correct apid
    $db = db_connect();
    $sql = "SELECT * FROM airlines WHERE iata='" . $route["noroute_al_iata"] . "'";
    $result = mysql_query($sql, $db);
    $this->assertTrue($row = mysql_fetch_array($result, MYSQL_ASSOC), "No-route airline not found");
    $route["noroute_alid"] = $row["alid"];

    // Then test
    $params = array("apid" => "L" . $route["noroute_apid"]);
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

// Invalid airline ID
class RouteMapInvalidAirlineTest extends WebTestCase {
  function test() {
    global $webroot, $route;

    $params = array("apid" => "L" . $route["invalid_alid"]);
    $map = $this->post($webroot . "php/routes.php", $params);
    $this->assertText('Error');
  }
}

?>
