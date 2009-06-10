<?php

// Address of OpenFlights install
$webroot = 'http://192.168.1.4:8888/';

// Settings for automated test user
$settings = array('name' => 'autotest',
		  'password' => 'autotest',
		  'email' => 'test@openflights.example',
		  'privacy' => 'Y',
		  'editor' => 'B',
		  'fbuid' => null,
		  'locale' => 'en_US',
		  'sessionkey' => null);

$airline = array('name' => 'AutoTest Airways',
		 'alias' => 'AutoTestAir',
		 'iata' => 'ZZ',
		 'icao' => 'ZZZ',
		 'country' => 'Austria',
		 'mode' => 'F');

$railway = array('name' => 'AutoTest Railways',
		 'alias' => 'AutoTestRail',
		 'country' => 'Austria',
		 'mode' => 'T');

$airport = array('name' => 'Test Airport',
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
		'src_time' => '23:59',
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
		'src_time' => '00:01',
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
	      'privacy' => 'Y');

// Database configuration
$dbhost = "localhost";
$dbuser = "openflights";
$dbname = "flightdb2";

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
  return $case->post($webroot . "php/login.php", $params);
}

// Get a connection to the database
function db_connect() {
  global $dbhost, $dbuser, $dbname;

  $db = mysql_connect($dbhost, $dbuser);
  mysql_select_db($dbname,$db);
  return $db;
}

?>
