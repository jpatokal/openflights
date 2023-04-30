<?php

require_once(dirname(__FILE__) . '/../../vendor/simpletest/simpletest/autorun.php');
require_once(dirname(__FILE__) . '/../../vendor/simpletest/simpletest/web_tester.php');

// Address of OpenFlights install
$webroot = 'http://localhost:8080/';

// Path to OpenFlights upload directory
$uploaddir = '../../import/';

// Database configuration
$dbhost = "localhost";
$dbuser = "openflights";
$dbpass = "";
$dbname = "flightdb2";

//
// TEST CASES
//

// Settings for automated test user
$settings = array(
    'name' => 'autotest',
    'password' => 'autotest',
    'guestpw' => 'guesttest',
    'email' => 'test@openflights.example',
    'privacy' => 'Y', // should be Y/O for public profile tests
    'editor' => 'B',
    'fbuid' => null,
    'locale' => 'en_US',
    'units' => 'M',
    'elite' => '', // keep blank, elite users cannot be created by normal means
    'sessionkey' => null,
);

// Test airline, airport data
$qs_string = 'AutoTest'; // unique string in both airport and airline names, but nowhere else

$airline = array(
    'name' => 'AutoTest Airways',
    'alias' => 'AutoTestAir',
    'iata' => 'ZZ',
    'icao' => 'ZZZ',
    'country' => 'Austria',
    'callsign' => 'AUTOTEST',
    'mode' => 'F',
    'active' => 'Y',
);

$railway = array(
    'name' => 'AutoTest Railways',
    'alias' => 'AutoTestRail',
    'country' => 'Austria',
    'mode' => 'T',
);

$airport = array(
    'name' => 'AutoTest Airport',
    'city' => 'Testville',
    'country' => 'Afghanistan',
    'iata' => 'ZZZ',
    'icao' => 'ZZZZ',
    'x' => '42.424',
    'y' => '69.696',
    'elevation' => '123',
    'timezone' => '-5.5',
    'dst' => 'Z',
);

$flight = array(
    'param' => 'ADD',
    'src_date' => '2009-02-03',
    'src_time' => '2359',
    'src_time_formatted' => '23:59:00',
    'src_apid' => '1000',
    'dst_apid' => '1001',
    'alid' => '1000',
    'duration' => '1:00',
    'distance' => '500',
    'number' => 'AB123',
    'seat' => '12A',
    'type' => 'W',
    'class' => 'Y',
    'reason' => 'B',
    'registration' => 'AB-123',
    'trid' => 'NULL',
    'plane' => 'Boeing 737',
    'note' => 'AddSingleFlightTest,;:\'"Chars',
);

$flight2 = array(
    'param' => 'EDIT',
    'src_date' => '2010-04-05',
    'src_time' => '10:02',
    'src_apid' => '2000',
    'dst_apid' => '2001',
    'alid' => '2000',
    'duration' => '02:00',
    'distance' => '1000',
    'number' => 'CD456',
    'seat' => '34B',
    'type' => 'A',
    'class' => 'F',
    'reason' => 'L',
    'registration' => 'CD-456',
    'trid' => 'NULL',
    'plane' => 'Airbus A2380',
    'note' => 'EditFlightTest,;:\'"Chars',
);

$multiflight = array(
    'param' => 'ADD',
    'multi' => '4',
    'src_date1' => '2009-01-01',
    'src_apid1' => '1010',
    'dst_apid1' => '1011',
    'alid1' => '2010',
    'src_date2' => '2009-02-02',
    'src_apid2' => '1020',
    'dst_apid2' => '1021',
    'alid2' => '2020',
    'src_date3' => '2009-03-03',
    'src_apid3' => '1030',
    'dst_apid3' => '1031',
    'alid3' => '2030',
    'src_date4' => '2009-04-04',
    'src_apid4' => '1040',
    'dst_apid4' => '1041',
    'alid4' => '2040',
    'duration' => '',
    'distance' => '',
    'number' => '',
    'seat' => '',
    'type' => '',
    'class' => '',
    'reason' => '',
    'registration' => '',
    'trid' => 'NULL',
    'plane' => '',
    'mode' => 'F',
    'note' => 'AddMultiFlightTest,;:\'"Chars',
);

$loopflight = array(
    'param' => 'ADD',
    'src_date' => '2009-02-03',
    'src_apid' => '1100',
    'dst_apid' => '1100',
    'alid' => '2100',
    'duration' => '',
    'distance' => '',
    'number' => '',
    'seat' => '',
    'type' => '',
    'class' => '',
    'reason' => '',
    'registration' => '',
    'trid' => 'NULL',
    'plane' => '',
    'mode' => 'F',
    'note' => 'AddLoopFlightTest,;:\'"Chars',
);

$trip = array(
    'name' => 'AutoTest Trip',
    'url' => 'http://autotrip.example',
    'privacy' => 'Y',
); // should default to Y/O

$route = array(
    'core_ap_iata' => 'ISG',
    'core_ap_filter_iata' => 'NH', // airline that flies to the airport above
    'noroute_ap_iata' => 'SQQ',
    'invalid_apid' => '99999',
    'core_al_iata' => 'SQ',
    'noroute_al_iata' => 'PA',
    'invalid_alid' => '99999',
);

// *** END OF CONFIGURATION ***

// Login
// Use default settings unless name/password are given as arguments

function login($case, $name = null, $password = null, $challenge = null) {
    global $webroot, $settings;

    if (!$password) {
        $password = $settings["password"];
    }
    if (!$name) {
        $name = $settings["name"];
    }

    $map = $case->post($webroot . "php/map.php");
    $cols = preg_split('/[;\n]/', $map);
    if ($challenge == null) {
        $challenge = $cols[7];
    }
    $hash = md5($challenge . md5($password . strtolower($name)));
    $legacyhash = md5($challenge . md5($password . $name));
    $params = array(
        "name" => $name,
        "pw" => $hash,
        "lpw" => $legacyhash,
        "challenge" => $challenge
    );
    return json_decode($case->post($webroot . "php/login.php", $params));
}

function assert_login($case) {
    $json = login($case);
    $case->assertEqual($json->status, "1");
}

/**
 * Get a connection to the database
 *
 * @return PDO OpenFlights test suite DB handler
 */
function db_connect() {
    global $dbhost, $dbuser, $dbpass, $dbname;

    $dbh = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass, array(
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"
    ));
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);

    return $dbh;
}

/**
 * Get test user's UID
 *
 * @param $dbh PDO OpenFlights test suite DB handler
 * @return string UID of test user
 */
function db_uid($dbh) {
    global $settings;

    $sth = $dbh->prepare("SELECT uid FROM users WHERE name=?");
    $sth->execute([$settings["name"]]);
    return $sth->fetchColumn(0);
}

/**
 * Get apid of test airport
 *
 * @param $dbh PDO OpenFlights test suite DB handler
 * @return string APID of test airport
 */
function db_apid($dbh) {
    global $airport;

    $sth = $dbh->prepare("SELECT apid FROM airports WHERE iata=?");
    $sth->execute([$airport["iata"]]);
    return $sth->fetchColumn(0);
}

function cleanup() {
    global $settings;

    $dbh = db_connect();
    $sth = $dbh->prepare("DELETE FROM flights WHERE uid IN (SELECT uid FROM users WHERE name=?)");
    $sth->execute([$settings["name"]]);
    $sth = $dbh->prepare("DELETE FROM airports WHERE uid IN (SELECT uid FROM users WHERE name=?)");
    $sth->execute([$settings["name"]]);
    $sth = $dbh->prepare("DELETE FROM airlines WHERE uid IN (SELECT uid FROM users WHERE name=?)");
    $sth->execute([$settings["name"]]);
}
