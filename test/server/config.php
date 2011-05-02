<?php

// Address of OpenFlights install
$webroot = 'http://openflights.local/';

// Path to OpenFlights upload directory
$uploaddir = '../../import/';

// Database configuration
$dbhost = "localhost";
$dbuser = "openflights";
$dbname = "flightdb2";

//
// TEST CASES
//

// Settings for automated test user
$settings = array('name' => 'autotest',
		  'password' => 'autotest',
		  'guestpw' => 'guesttest',
		  'email' => 'test@openflights.example',
		  'privacy' => 'Y', // should be Y/O for public profile tests
		  'editor' => 'B',
		  'fbuid' => null,
		  'locale' => 'en_US',
		  'units' => 'M',
		  'elite' => '', // keep blank, elite users cannot be created by normal means
		  'sessionkey' => null);

// Test airline, airport data
$qs_string = 'AutoTest'; // unique string in both airport and airline names, but nowhere else

$airline = array('name' => 'AutoTest Airways',
		 'alias' => 'AutoTestAir',
		 'iata' => 'ZZ',
		 'icao' => 'ZZZ',
		 'country' => 'Austria',
		 'callsign' => 'AUTOTEST',
		 'mode' => 'F',
		 'active' => 'Y');

$railway = array('name' => 'AutoTest Railways',
		 'alias' => 'AutoTestRail',
		 'country' => 'Austria',
		 'mode' => 'T');

$airport = array('name' => 'AutoTest Airport',
		 'city' => 'Testville',
		 'country' => 'Testland',
		 'iata' => 'ZZZ',
		 'icao' => 'ZZZZ',
		 'x' => '42.42',
		 'y' => '69.69',
		 'elevation' => '123',
		 'timezone' => '-5.5',
		 'dst' => 'Z');

$flight = array('param' => 'ADD',
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
		'note' => 'AddSingleFlightTest,;:\'"Chars');

$flight2 = array('param' => 'EDIT',
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
		'note' => 'EditFlightTest,;:\'"Chars');

$multiflight = array('param' => 'ADD',
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
		     'note' => 'AddMultiFlightTest,;:\'"Chars');

$loopflight = array('param' => 'ADD',
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
		    'note' => 'AddLoopFlightTest,;:\'"Chars');

$trip = array('name' => 'AutoTest Trip',
	      'url' => 'http://autotrip.example',
	      'privacy' => 'Y'); // should default to Y/O

$route = array('core_ap_iata' => 'ISG',
	       'core_ap_filter_iata' => 'NH', // airline that flies to the airport above
	       'noroute_ap_iata' => 'SQQ',
	       'invalid_apid' => '99999',
	       'core_al_iata' => 'SQ',
	       'noroute_al_iata' => 'PA',
	       'invalid_alid' => '99999');

// *** END OF CONFIGURATION ***

// Login
// Use default settings unless name/password are given as arguments

function login($case, $name = NULL, $password = NULL) {
  global $webroot, $settings;
  
  if(! $password) {
    $password = $settings["password"];
  }
  if(! $name) {
    $name = $settings["name"];
  }
  
  $map = $case->post($webroot . "php/map.php");
  $cols = preg_split('/[;\n]/', $map);
  $challenge = $cols[7];
  $hash = md5($challenge . md5($password . strtolower($name)));
  $legacyhash = md5($challenge . md5($password . $name));
  $params = array("name" => $name,
		  "pw" => $hash,
		  "lpw" => $legacyhash);
  return json_decode($case->post($webroot . "php/login.php", $params));
}

// Get a connection to the database
function db_connect() {
  global $dbhost, $dbuser, $dbname;

  $db = mysql_connect($dbhost, $dbuser);
  mysql_select_db($dbname,$db);
  return $db;
}

// Get test user's UID
function db_uid($db) {
  global $settings;

  $result = mysql_query("SELECT uid FROM users WHERE name='" . $settings["name"] . "'", $db);
  return mysql_result($result, 0);
}

function cleanup() {
  global $settings;

  $db = db_connect();
  $sql = "DELETE FROM flights WHERE uid IN (SELECT uid FROM users WHERE name='" . $settings["name"] . "')";
  $result = mysql_query($sql, $db);
  $sql = "DELETE FROM airports WHERE uid IN (SELECT uid FROM users WHERE name='" . $settings["name"] . "')";
  $result = mysql_query($sql, $db);
  $sql = "DELETE FROM airlines WHERE uid IN (SELECT uid FROM users WHERE name='" . $settings["name"] . "')";
  $result = mysql_query($sql, $db);
}
?>
