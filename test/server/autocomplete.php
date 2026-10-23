<?php

include_once dirname(__FILE__) . '/config.php';

// Testing the tests: curl -v http://localhost:8007/php/autocomplete.php -d searchType=multisearch -d searchText=STRING

/**
 * Search for string found in both airport and airline name
 */
class MultiSearchSharedLongStringTest extends WebTestCase {
    public function test() {
        global $webroot, $qs_string;

        $params = array("searchType" => "multisearch", "searchText" => "Singapore");
        $msg = $this->post($webroot . "php/autocomplete.php", $params);
        $this->assertText("Singapore Changi");
        $this->assertText("(SIN)");
        $this->assertText("Singapore Airlines");
        $this->assertText("(SQ)");
        $this->assertNoText("(Priv)");
    }
}

/**
 * Search for string found only in airport name
 */
class MultiSearchAirportOnlyLongStringTest extends WebTestCase {
    public function test() {
        global $webroot;

        $params = array("searchType" => "multisearch", "searchText" => "Ayers");
        $msg = $this->post($webroot . "php/autocomplete.php", $params);
        $this->assertText("Uluru");
        $this->assertText("(AYQ)");
        $this->assertNoText("(Priv)");
    }
}

/**
 * Search for string found in only airline name
 */
class MultiSearchAirlineOnlyLongStringTest extends WebTestCase {
    public function test() {
        global $webroot;

        $params = array("searchType" => "multisearch", "searchText" => "Qantas");
        $msg = $this->post($webroot . "php/autocomplete.php", $params);
        $this->assertText("(QF)");
        $this->assertText("Qantas");
        $this->assertNoText("(Priv)");
    }
}

/**
 * Search for airport by IATA
 */
class MultiSearchAirportIATATest extends WebTestCase {
    public function test() {
        global $webroot, $airport;

        $params = array("searchType" => "multisearch", "searchText" => "SIN");
        $msg = $this->post($webroot . "php/autocomplete.php", $params);
        $this->assertText("Singapore Changi");
        $this->assertText("(SIN)");
        $this->assertNoText("(Priv)");
    }
}

/**
 * Search for airline by IATA
 */
class MultiSearchAirlineIATATest extends WebTestCase {
    public function test() {
        global $webroot;

        $params = array("searchType" => "multisearch", "searchText" => "SQ");
        $msg = $this->post($webroot . "php/autocomplete.php", $params);
        $this->assertText("Singapore Airlines");
        $this->assertText("(SQ)");
        $this->assertNoText("(Priv)");
    }
}

/**
 * Single airport search by short city name
 */
class SingleAirportShortCityCompleteTest extends WebTestCase {
    public function test() {
        global $webroot;

        $params = array("searchType" => "airport", "searchText" => "Hong", "quick" => "true");
        $msg = $this->post($webroot . "php/autocomplete.php", $params);
        $this->assertText("HKG:");
    }
}

/**
 * Single airport search by long city name
 */
class SingleAirportCityLongCompleteTest extends WebTestCase {
    public function test() {
        global $webroot;

        $params = array("searchType" => "airport", "searchText" => "Hamad", "quick" => "true");
        $msg = $this->post($webroot . "php/autocomplete.php", $params);
        $this->assertText("DOH:");
    }
}

/**
 * Single airport search by IATA code
 */
class SingleAirportIATACompleteTest extends WebTestCase {
    public function test() {
        global $webroot, $airport;

        $params = array("searchType" => "airport", "searchText" => "SIN", "quick" => "true");
        $msg = $this->post($webroot . "php/autocomplete.php", $params);
        $this->assertText("SIN:");
    }
}

/**
 * Single airport search by ICAO code
 */
class SingleAirportICAOCompleteTest extends WebTestCase {
    public function test() {
        global $webroot;

        $params = array("searchType" => "airport", "searchText" => "WSSS", "quick" => "true");
        $msg = $this->post($webroot . "php/autocomplete.php", $params);
        $this->assertText("SIN:");
    }
}

/**
 * Ensure that autocompleted entries still match after minor edits
 */
class SingleAirportQueryTrimTest extends WebTestCase {
    public function test() {
        global $webroot;

        $params = array("searchType" => "airport", "searchText" => "Singapore Changi (SIN) Blah Blah", "quick" => "true");
        $msg = $this->post($webroot . "php/autocomplete.php", $params);
        $this->assertText("SIN:");

        $params = array("searchType" => "airport", "searchText" => "Singapore C. (SIN) Blah Blah", "quick" => "true");
        $msg = $this->post($webroot . "php/autocomplete.php", $params);
        $this->assertText("SIN:");

        $params = array("searchType" => "airport", "searchText" => "Hong Kong International (HKG) Blah Blah", "quick" => "true");
        $msg = $this->post($webroot . "php/autocomplete.php", $params);
        $this->assertText("HKG:");
    }
}

/**
 * Single airline search by name
 */
class SingleAirlineNameCompleteTest extends WebTestCase {
    public function test() {
        global $webroot;

        $params = array("searchType" => "airline", "searchText" => "Qantas", "quick" => "true");
        $msg = $this->post($webroot . "php/autocomplete.php", $params);
        $this->assertText("(QF)");
        $this->assertText("Qantas");
    }
}

/**
 * Single airline search by alias
 */
class SingleAirlineAliasCompleteTest extends WebTestCase {
    public function test() {
        global $webroot;

        $params = array("searchType" => "airline", "searchText" => "JAL Japan", "quick" => "true");
        $msg = $this->post($webroot . "php/autocomplete.php", $params);
        $this->assertText("(JL)");
        $this->assertText("Japan");
    }
}

/**
 * Single airline search by IATA code
 */
class SingleAirlineIATACompleteTest extends WebTestCase {
    public function test() {
        global $webroot;

        $params = array("searchType" => "airline", "searchText" => "SQ", "quick" => "true");
        $msg = $this->post($webroot . "php/autocomplete.php", $params);
        $this->assertText("(SQ");
        $this->assertText("Singapore");
    }
}

/**
 * Single airline search by ICAO code
 */
class SingleAirlineICAOCompleteTest extends WebTestCase {
    public function test() {
        global $webroot;

        $params = array("searchType" => "airline", "searchText" => "SIA", "quick" => "true");
        $msg = $this->post($webroot . "php/autocomplete.php", $params);
        $this->assertText("(SQ)");
        $this->assertText("Singapore");
    }
}

class SinglePlaneMajorNameCompleteTest extends WebTestCase {
    public function test() {
        global $webroot;

        $params = array("searchType" => "plane", "searchText" => "737", "quick" => "true");
        $msg = $this->post($webroot . "php/autocomplete.php", $params);
        $this->assertText("Boeing 737");
    }
}

class SinglePlaneMajorNameMinorVariantCompleteTest extends WebTestCase {
    public function test() {
        global $webroot;

        $params = array("searchType" => "plane", "searchText" => "737-9", "quick" => "true");
        $msg = $this->post($webroot . "php/autocomplete.php", $params);
        $this->assertText("Boeing 737-900");
    }
}

class SinglePlaneMinorNameCompleteTest extends WebTestCase {
    public function test() {
        global $webroot;

        $params = array("searchType" => "plane", "searchText" => "Beluga", "quick" => "true");
        $msg = $this->post($webroot . "php/autocomplete.php", $params);
        $this->assertText("Beluga XL");
    }
}

class SinglePlaneIATACompleteTest extends WebTestCase {
    public function test() {
        global $webroot;

        $params = array("searchType" => "plane", "searchText" => "734", "quick" => "true");
        $msg = $this->post($webroot . "php/autocomplete.php", $params);
        $this->assertText("Boeing 737-400");
    }
}
