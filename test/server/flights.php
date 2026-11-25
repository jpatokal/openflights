<?php

include_once dirname(__FILE__) . '/config.php';

// Test cases for php/flights.php

/**
 * Assert that a decoded flight object has the expected JSON schema/types.
 *
 * @param WebTestCase $case
 * @param array $flight
 */
function assert_flight_shape($case, array $flight) {
    $keys = [
        "src_code",
        "src_apid",
        "dst_code",
        "dst_apid",
        "code",
        "date",
        "distance",
        "duration",
        "seat",
        "seat_type",
        "class",
        "reason",
        "fid",
        "plane",
        "registration",
        "alid",
        "note",
        "trid",
        "plid",
        "al_code",
        "src_time",
        "mode",
    ];
    foreach ($keys as $key) {
        $case->assertArrayHasKey($key, $flight, "Missing key: " . $key);
    }

    $case->assertIsString($flight["src_code"]);
    $case->assertNotSame("", $flight["src_code"], "src_code is set");
    $case->assertIsString($flight["dst_code"]);
    $case->assertNotSame("", $flight["dst_code"], "dst_code is set");
    $case->assertIsInt($flight["src_apid"]);
    $case->assertIsInt($flight["dst_apid"]);
    $case->assertIsInt($flight["fid"]);
    $case->assertIsInt($flight["alid"]);
    $case->assertIsInt($flight["distance"]);
    $case->assertIsString($flight["mode"]);
    $case->assertContains($flight["mode"], ["F", "T", "R", "S"], "valid mode");
    $case->assertTrue(
        $flight["trid"] === null || is_int($flight["trid"]),
        "trid is null or an integer"
    );
    $case->assertTrue(
        $flight["plid"] === null || is_int($flight["plid"]),
        "plid is null or an integer"
    );
}

/**
 * Check demo user map
 */
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
        $sth = $dbh->prepare("SELECT alid FROM airlines WHERE iata = ?");
        if (!$sth->execute([$route["core_al_iata"]])) {
            die($sth->errorInfo());
        }
        $row = $sth->fetch();
        if ($row) {
            $alid = $row["alid"];
        }

        $sth = $dbh->prepare("SELECT COUNT(*) AS count FROM routes WHERE alid = ?");
        if (!$sth->execute([$alid])) {
            die($sth->errorInfo());
        }
        $row = $sth->fetch();
        if ($row) {
            $rowCount = intval($row["count"]);
        }

        // Then test
        assert_login($this);
        $params = array("export" => "export", "id" => "L" . $alid);
        $csv = $this->get($webroot . "php/flights.php", $params);
        $rows = explode("\n", $csv);
        $this->assertEqual(count($rows), $rowCount + 1);
    }
}

/**
 * The default flights response is JSON, not tab-separated text.
 */
class FlightsJsonDemoCase extends WebTestCase {
    public function test() {
        global $webroot;

        $json = $this->post($webroot . "php/flights.php", []);
        $this->assertStringStartsWith(
            "application/json",
            (string) $this->lastHeader("Content-Type")
        );

        $flights = json_decode($json, true);
        $this->assertIsArray($flights, "Flights response is a JSON array");
        $this->assertGreaterThan(0, count($flights), "Demo flights returned");
        assert_flight_shape($this, $flights[0]);
    }
}

/**
 * Route search results are JSON and describe the operating airline.
 */
class FlightsJsonRouteCase extends WebTestCase {
    public function test() {
        global $webroot, $route;

        $dbh = db_connect();
        $sth = $dbh->prepare("SELECT alid FROM airlines WHERE iata = ?");
        $sth->execute([$route["core_al_iata"]]);
        $alid = $sth->fetchColumn();
        $this->assertNotFalse($alid, "Route airline exists");

        $json = $this->post($webroot . "php/flights.php", [
            "id" => "L" . $alid,
        ]);
        $flights = json_decode($json, true);
        $this->assertIsArray($flights, "Routes response is a JSON array");
        $this->assertGreaterThan(0, count($flights), "Routes returned");

        $flight = $flights[0];
        assert_flight_shape($this, $flight);
        $this->assertStringContainsString(
            "(",
            $flight["code"],
            "Route code includes the airline code"
        );
        $this->assertSame("-", $flight["date"], "Routes have no date");
        $this->assertNotSame("", $flight["note"], "Route carries a note");
    }
}

/**
 * A logged-in user's flight is returned in the JSON list with all fields.
 */
class FlightsJsonLoggedInCase extends WebTestCase {
    public function test() {
        global $webroot;

        assert_login($this);

        $dbh = db_connect();
        $sth = $dbh->prepare("SELECT apid FROM airports WHERE iata = ?");
        $sth->execute(["SIN"]);
        $src = $sth->fetchColumn();
        $sth->execute(["JFK"]);
        $dst = $sth->fetchColumn();
        $this->assertNotFalse($src, "SIN airport exists");
        $this->assertNotFalse($dst, "JFK airport exists");

        $note = "FlightsJsonCase" . uniqid();
        $params = [
            "param" => "ADD",
            "src_apid" => $src,
            "dst_apid" => $dst,
            "src_date" => "2024-01-02",
            "src_time" => "12:34",
            "mode" => "F",
            "number" => "JSON123",
            "seat" => "1A",
            "type" => "W",
            "class" => "Y",
            "reason" => "B",
            "registration" => "JSON-1",
            "trid" => "NULL",
            "plane" => "",
            "note" => $note,
            "distance" => "",
            "duration" => "",
        ];
        $this->post($webroot . "php/submit.php", $params);
        $this->assertText("1;");

        $json = $this->post($webroot . "php/flights.php", []);
        $flights = json_decode($json, true);
        $this->assertIsArray($flights, "Flights response is a JSON array");

        $found = null;
        foreach ($flights as $flight) {
            if (($flight["note"] ?? null) === $note) {
                $found = $flight;
                break;
            }
        }
        $this->assertNotNull($found, "Added flight appears in the JSON list");
        assert_flight_shape($this, $found);

        $codes = [$found["src_code"], $found["dst_code"]];
        sort($codes);
        $this->assertSame(["JFK", "SIN"], $codes);
        $this->assertSame("JSON123", $found["code"]);
        $this->assertSame("Y", $found["class"]);
        $this->assertSame("B", $found["reason"]);
        $this->assertSame("W", $found["seat_type"]);
        $this->assertSame("2024-01-02", $found["date"]);
        $this->assertSame("F", $found["mode"]);
        $this->assertGreaterThan(
            0,
            $found["distance"],
            "Distance was auto-calculated"
        );
        $this->assertMatchesRegularExpression(
            "/^\d{2}:\d{2}$/",
            $found["duration"],
            "Duration was auto-calculated"
        );

        // Clean up the flight we added.
        $this->post($webroot . "php/submit.php", [
            "param" => "DELETE",
            "fid" => $found["fid"],
        ]);
        $this->assertText("100;");
    }
}
