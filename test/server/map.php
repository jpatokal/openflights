<?php

include_once dirname(__FILE__) . '/config.php';

// Test cases for php/map.php
// NB 1: Assumes the test user exists and flights.php has been run, so that $flight2[] is already in DB
// NB 2: Trip map tests found under trip.php

/**
 * Assert that the map response has the shared JSON structure.
 *
 * @param WebTestCase $case
 * @param array $data
 */
function assert_map_shape($case, array $data) {
    foreach ([
        "stats",
        "flights",
        "airports",
        "trips",
        "airlines",
        "years",
        "modes",
        "classes",
    ] as $key) {
        $case->assertArrayHasKey($key, $data, "Missing key: " . $key);
    }
    $case->assertIsArray($data["flights"]);
    $case->assertIsArray($data["airports"]);
    $case->assertIsArray($data["stats"]);

    foreach ($data["airports"] as $airport) {
        foreach ([
            "code",
            "apid",
            "lon",
            "lat",
            "timezone",
            "dst",
            "name",
            "city",
            "country",
            "count",
            "formatted",
            "future",
        ] as $key) {
            $case->assertArrayHasKey($key, $airport, "Missing airport key: " . $key);
        }
        $case->assertArrayNotHasKey(
            "apdata",
            $airport,
            "Airports must use structured fields, not the legacy apdata string"
        );
    }
}

/**
 * Check demo user map
 */
class CheckDemoFullUserMap extends WebTestCase {
    public function test() {
        global $webroot;

        $params = array("param" => "true");
        $map = $this->post($webroot . "php/map.php", $params);
        $this->assertStringStartsWith(
            "application/json",
            (string) $this->lastHeader("Content-Type")
        );

        $data = json_decode($map, true);
        $this->assertIsArray($data, "Map response is JSON");
        assert_map_shape($this, $data);

        $stats = $data["stats"];
        $this->assertGreaterThan(
            0,
            $stats["count"],
            "No demo flights found -- did you add some flights and run sql/update-demo.sql?"
        );
        $this->assertSame("O", $stats["public"], "Public"); // demo flights always full access
        $this->assertSame("demo", $stats["logged_in"], "Username");
    }
}

/**
 * Check public profile for user
 */
class CheckPublicFullUserMap extends WebTestCase {
    public function test() {
        global $webroot, $settings, $flight2;

        $params = array(
            "param" => "true",
            "user" => $settings["name"]
        );
        $map = $this->post($webroot . "php/map.php", $params);
        $data = json_decode($map, true);
        $this->assertIsArray($data, "Map response is JSON");
        assert_map_shape($this, $data);

        $stats = $data["stats"];
        $this->assertSame(1, $stats["count"], "Flight count");
        $this->assertStringContainsString(
            number_format($flight2["distance"]),
            $stats["distance"],
            "Distance"
        );
        $this->assertSame($settings["privacy"], $stats["public"], "Public");
        $this->assertSame($settings["elite"], $stats["elite"], "Elite");
        $this->assertSame("demo", $stats["logged_in"], "Username"); // we are not this user!
    }
}

/**
 * Attempt to view private profile for user (fails)
 */
class CheckPrivateNoPasswordFullUserMap extends WebTestCase {
    public function test() {
        global $webroot, $settings;

        $dbh = db_connect();
        $sth = $dbh->prepare("UPDATE users SET public='N', guestpw = ? WHERE name = ?");
        $sth->execute([$settings["guestpw"], $settings["name"]]);
        $this->assertTrue($sth->rowCount() == 1, "Set profile to private");

        $params = array(
            "param" => "true",
            "guestpw" => "incorrect",
            "user" => $settings["name"]
        );
        $map = $this->post($webroot . "php/map.php", $params);
        $this->assertStringStartsWith("Error", $map, "Private profile blocked");
    }
}

/**
 * View private profile with correct password
 */
class CheckPrivateGuestPasswordFullUserMap extends WebTestCase {
    public function test() {
        global $webroot, $settings, $flight2;

        $params = array(
            "param" => "true",
            "guestpw" => $settings["guestpw"],
            "user" => $settings["name"]
        );
        $map = $this->post($webroot . "php/map.php", $params);
        $data = json_decode($map, true);
        $this->assertIsArray($data, "Map response is JSON");

        $stats = $data["stats"];
        $this->assertSame(1, $stats["count"], "Flight count");
        $this->assertStringContainsString(
            number_format($flight2["distance"]),
            $stats["distance"],
            "Distance"
        );
        $this->assertSame("N", $stats["public"], "Public");
        $this->assertSame($settings["elite"], $stats["elite"], "Elite");
        $this->assertSame("demo", $stats["logged_in"], "Username"); // we are not this user!
    }
}

/**
 * Check logged in user map
 */
class CheckLoggedInFullUserMap extends WebTestCase {
    public function test() {
        global $webroot, $settings, $flight2;

        assert_login($this);

        $params = array("param" => "true");
        $map = $this->post($webroot . "php/map.php", $params);
        $data = json_decode($map, true);
        $this->assertIsArray($data, "Map response is JSON");
        assert_map_shape($this, $data);

        $stats = $data["stats"];
        $this->assertSame(1, $stats["count"], "Flight count");
        $this->assertStringContainsString(
            number_format($flight2["distance"]),
            $stats["distance"],
            "Distance"
        );
        $this->assertSame("O", $stats["public"], "Public"); // own flights always full access
        $this->assertSame($settings["elite"], $stats["elite"], "Elite");
        $this->assertSame($settings["name"], $stats["logged_in"], "Username");
        $this->assertSame($settings["editor"], $stats["editor"], "Editor");
    }
}

/**
 * Filtering: year, airline, mode, class, trip and advanced filters must all
 * narrow the map stats, flight pairs and airports, and each filter must
 * constrain the options offered for the *other* filters.
 */
class FilteredMapTest extends WebTestCase {
    /**
     * POST a map request as the signed-in test user and decode the JSON.
     *
     * @return array
     */
    private function mapData(array $params) {
        global $webroot;

        $params += array("param" => "true");
        $raw = $this->post($webroot . "php/map.php", $params);
        $data = json_decode($raw, true);
        $this->assertIsArray($data, "Map response is JSON");
        return $data;
    }

    /**
     * Sorted airport codes in a map response.
     */
    private function airportCodes(array $data) {
        $codes = array_column($data["airports"], "code");
        sort($codes);
        return $codes;
    }

    /**
     * Values of one key from a filter option list.
     */
    private function optionValues(array $options, $key) {
        return array_map(function ($option) use ($key) {
            return $option[$key];
        }, $options);
    }

    public function test() {
        global $webroot;

        assert_login($this);

        $dbh = db_connect();

        // Resolve the airports and airlines used by the disposable flights.
        $sth = $dbh->prepare("SELECT apid FROM airports WHERE iata = ?");
        $airports = array();
        foreach (array("SIN", "JFK", "LHR") as $iata) {
            $sth->execute(array($iata));
            $apid = $sth->fetchColumn();
            $this->assertNotFalse($apid, "$iata airport exists");
            $airports[$iata] = (int) $apid;
        }

        $sth = $dbh->prepare(
            "SELECT alid FROM airlines WHERE iata = ? ORDER BY alid LIMIT 1"
        );
        $sth->execute(array("SQ"));
        $alA = (int) $sth->fetchColumn();
        $sth->execute(array("NH"));
        $alB = (int) $sth->fetchColumn();
        $this->assertGreaterThan(0, $alA, "SQ airline exists");
        $this->assertGreaterThan(0, $alB, "NH airline exists");

        // A disposable trip, so the trid filter can be exercised too.
        $raw = $this->post($webroot . "php/trip.php", array(
            "type" => "NEW",
            "name" => "MapFilterTrip " . uniqid(),
            "url" => "http://mapfilter.example",
            "privacy" => "O",
        ));
        $this->assertText("1;");
        $parts = explode(";", $raw);
        $trid = (int) ($parts[1] ?? 0);
        $this->assertGreaterThan(0, $trid, "Trip created");

        // Three disposable flights, each unique along one filtered dimension.
        // flight2 (2010, First, mode F, 1000 mi) from submit.php is also present.
        $flights = array(
            // 2024, SQ, Economy, flight, 100 mi, reason B
            array(
                "src_apid" => $airports["SIN"],
                "dst_apid" => $airports["JFK"],
                "src_date" => "2024-01-15",
                "alid" => $alA,
                "class" => "Y",
                "mode" => "F",
                "distance" => "100",
                "reason" => "B",
                "registration" => "MAPF1",
                "trid" => "NULL",
            ),
            // 2024, SQ, Business, flight, 500 mi, reason P
            array(
                "src_apid" => $airports["SIN"],
                "dst_apid" => $airports["LHR"],
                "src_date" => "2024-06-20",
                "alid" => $alA,
                "class" => "C",
                "mode" => "F",
                "distance" => "500",
                "reason" => "P",
                "registration" => "MAPF2",
                "trid" => "NULL",
            ),
            // 2023, NH, Economy, train, 2000 mi, reason B, on the trip
            array(
                "src_apid" => $airports["JFK"],
                "dst_apid" => $airports["LHR"],
                "src_date" => "2023-03-10",
                "alid" => $alB,
                "class" => "Y",
                "mode" => "T",
                "distance" => "2000",
                "reason" => "B",
                "registration" => "MAPF3",
                "trid" => (string) $trid,
            ),
        );
        foreach ($flights as $i => $flight) {
            $flight["param"] = "ADD";
            $flight["src_time"] = "12:00";
            $flight["duration"] = "01:00";
            $flight["number"] = "MF" . ($i + 1);
            $flight["seat"] = "1A";
            $flight["type"] = "W";
            $flight["plane"] = "";
            $flight["note"] = "MapFilterNote" . ($i + 1);
            $this->post($webroot . "php/submit.php", $flight);
            $this->assertText("1;");
        }

        // Baseline: four flights (three disposable + flight2), five airports.
        $data = $this->mapData(array());
        assert_map_shape($this, $data);
        $this->assertSame(4, $data["stats"]["count"], "Unfiltered flight count");
        $this->assertCount(4, $data["flights"], "Unfiltered flight pairs");
        $this->assertCount(5, $data["airports"], "Unfiltered airports");
        $this->assertSame(
            array(2024, 2023, 2010),
            $this->optionValues($data["years"], "year"),
            "Years are listed newest first"
        );
        $this->assertSame(
            array("F", "T"),
            $this->optionValues($data["modes"], "code"),
            "Modes are listed"
        );
        $this->assertSame(
            array("C", "F", "Y"),
            $this->optionValues($data["classes"], "code"),
            "Classes are listed"
        );
        $this->assertCount(3, $data["airlines"], "Airlines are listed");
        $this->assertContains(
            $trid,
            $this->optionValues($data["trips"], "trid"),
            "Trip is listed"
        );

        // Year filter.
        $data = $this->mapData(array("year" => "2024"));
        $this->assertSame(2, $data["stats"]["count"], "Year filter: flight count");
        $this->assertCount(2, $data["flights"], "Year filter: flight pairs");
        $this->assertSame(
            array("JFK", "LHR", "SIN"),
            $this->airportCodes($data),
            "Year filter: airports"
        );
        $this->assertSame(
            array(2024, 2023, 2010),
            $this->optionValues($data["years"], "year"),
            "Year list is not self-constrained"
        );
        $this->assertSame(
            array($alA),
            array_column($data["airlines"], "alid"),
            "Year filter constrains airlines"
        );
        $this->assertSame(
            array("F"),
            $this->optionValues($data["modes"], "code"),
            "Year filter constrains modes"
        );
        $this->assertSame(
            array("C", "Y"),
            $this->optionValues($data["classes"], "code"),
            "Year filter constrains classes"
        );

        // Airline filter.
        $data = $this->mapData(array("alid" => (string) $alA));
        $this->assertSame(2, $data["stats"]["count"], "Airline filter: flight count");
        $this->assertSame(
            array("JFK", "LHR", "SIN"),
            $this->airportCodes($data),
            "Airline filter: airports"
        );
        $this->assertSame(
            array(2024),
            $this->optionValues($data["years"], "year"),
            "Airline filter constrains years"
        );
        $this->assertSame(
            array("F"),
            $this->optionValues($data["modes"], "code"),
            "Airline filter constrains modes"
        );
        $this->assertSame(
            array("C", "Y"),
            $this->optionValues($data["classes"], "code"),
            "Airline filter constrains classes"
        );
        $this->assertCount(3, $data["airlines"], "Airline list is not self-constrained");

        // Mode filter.
        $data = $this->mapData(array("fmode" => "T"));
        $this->assertSame(1, $data["stats"]["count"], "Mode filter: flight count");
        $this->assertSame(
            array("JFK", "LHR"),
            $this->airportCodes($data),
            "Mode filter: airports"
        );
        $this->assertSame("T", $data["flights"][0]["mode"], "Mode filter: train flight");
        $this->assertSame(
            array(2023),
            $this->optionValues($data["years"], "year"),
            "Mode filter constrains years"
        );
        $this->assertSame(
            array($alB),
            array_column($data["airlines"], "alid"),
            "Mode filter constrains airlines"
        );
        $this->assertSame(
            array("F", "T"),
            $this->optionValues($data["modes"], "code"),
            "Mode list is not self-constrained"
        );
        $this->assertSame(
            array("Y"),
            $this->optionValues($data["classes"], "code"),
            "Mode filter constrains classes"
        );

        // Class filter.
        $data = $this->mapData(array("class" => "C"));
        $this->assertSame(1, $data["stats"]["count"], "Class filter: flight count");
        $this->assertSame(
            array("LHR", "SIN"),
            $this->airportCodes($data),
            "Class filter: airports"
        );
        $this->assertSame(
            array(2024),
            $this->optionValues($data["years"], "year"),
            "Class filter constrains years"
        );
        $this->assertSame(
            array($alA),
            array_column($data["airlines"], "alid"),
            "Class filter constrains airlines"
        );
        $this->assertSame(
            array("F"),
            $this->optionValues($data["modes"], "code"),
            "Class filter constrains modes"
        );
        $this->assertSame(
            array("C", "F", "Y"),
            $this->optionValues($data["classes"], "code"),
            "Class list is not self-constrained"
        );

        // Combined filters.
        $data = $this->mapData(array("year" => "2024", "class" => "C"));
        $this->assertSame(1, $data["stats"]["count"], "Combined filters: flight count");

        // Trip filter.
        $data = $this->mapData(array("trid" => (string) $trid));
        $this->assertSame(1, $data["stats"]["count"], "Trip filter: flight count");
        $this->assertSame(
            array("JFK", "LHR"),
            $this->airportCodes($data),
            "Trip filter: airports"
        );
        $this->assertSame(
            array(2023),
            $this->optionValues($data["years"], "year"),
            "Trip filter constrains years"
        );
        $this->assertContains(
            $trid,
            $this->optionValues($data["trips"], "trid"),
            "Trip list is not self-constrained"
        );

        $data = $this->mapData(array("trid" => "null"));
        $this->assertSame(3, $data["stats"]["count"], "Null trip filter: flight count");

        // Advanced (xkey/xvalue) filters.
        $data = $this->mapData(array("xkey" => "reason", "xvalue" => "B"));
        $this->assertSame(2, $data["stats"]["count"], "Reason filter: flight count");

        $data = $this->mapData(array(
            "year" => "2024",
            "xkey" => "distgt",
            "xvalue" => "400",
        ));
        $this->assertSame(1, $data["stats"]["count"], "Distance > filter: flight count");
        $this->assertSame(
            array("LHR", "SIN"),
            $this->airportCodes($data),
            "Distance > filter: airports"
        );

        $data = $this->mapData(array(
            "year" => "2024",
            "xkey" => "distlt",
            "xvalue" => "400",
        ));
        $this->assertSame(1, $data["stats"]["count"], "Distance < filter: flight count");
        $this->assertSame(
            array("JFK", "SIN"),
            $this->airportCodes($data),
            "Distance < filter: airports"
        );

        $data = $this->mapData(array("xkey" => "reg", "xvalue" => "MAPF"));
        $this->assertSame(3, $data["stats"]["count"], "Registration filter: flight count");

        $data = $this->mapData(array(
            "xkey" => "note",
            "xvalue" => "MapFilterNote",
        ));
        $this->assertSame(3, $data["stats"]["count"], "Note filter: flight count");

        $data = $this->mapData(array("xkey" => "class", "xvalue" => "C"));
        $this->assertSame(1, $data["stats"]["count"], "xkey class filter: flight count");

        $data = $this->mapData(array("xkey" => "mode", "xvalue" => "T"));
        $this->assertSame(1, $data["stats"]["count"], "xkey mode filter: flight count");

        $data = $this->mapData(array(
            "year" => "2024",
            "xkey" => "unknown",
            "xvalue" => "x",
        ));
        $this->assertSame(2, $data["stats"]["count"], "Unknown xkey is ignored");

        $data = $this->mapData(array("year" => "1999"));
        $this->assertSame(0, $data["stats"]["count"], "Empty year filter: flight count");
        $this->assertCount(0, $data["flights"], "Empty year filter: flights");
        $this->assertCount(0, $data["airports"], "Empty year filter: airports");

        // Clean up the disposable data.
        $sth = $dbh->prepare("DELETE FROM flights WHERE note LIKE 'MapFilterNote%'");
        $sth->execute();
        $this->assertSame(3, $sth->rowCount(), "Filter test flights removed");
        $sth = $dbh->prepare("DELETE FROM trips WHERE name LIKE 'MapFilterTrip%'");
        $sth->execute();
        $this->assertSame(1, $sth->rowCount(), "Filter test trip removed");
    }
}

/**
 * Remove disposable data if FilteredMapTest failed before its own cleanup.
 */
class CleanupMapFilterDataTest extends WebTestCase {
    public function test() {
        $dbh = db_connect();
        $dbh->prepare("DELETE FROM flights WHERE note LIKE 'MapFilterNote%'")->execute();
        $dbh->prepare("DELETE FROM trips WHERE name LIKE 'MapFilterTrip%'")->execute();

        $sth = $dbh->prepare(
            "SELECT COUNT(*) FROM flights WHERE note LIKE 'MapFilterNote%'"
        );
        $sth->execute();
        $this->assertSame(0, (int) $sth->fetchColumn(), "Filter flights cleaned up");

        $sth = $dbh->prepare("SELECT COUNT(*) FROM trips WHERE name LIKE 'MapFilterTrip%'");
        $sth->execute();
        $this->assertSame(0, (int) $sth->fetchColumn(), "Filter trip cleaned up");
    }
}
