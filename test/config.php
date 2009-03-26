<?php

// Address of OpenFlights install
$webroot = 'http://192.168.1.4:8888/';

// Settings for automated test user
$settings = array('username' => 'autotest',
		  'password' => 'autotest',
		  'email' => 'test@openflights.example',
		  'privacy' => 'Y',
		  'editor' => 'B',
		  'fbuid' => null,
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

// Database configuration
$dbhost = "localhost";
$dbuser = "openflights";
$dbname = "flightdb";

// *** END OF CONFIGURATION ***

// Login
// Use default settings unless username/password are given as arguments

function login($case, $username = NULL, $password = NULL) {
  global $webroot, $settings;
  
  if(! $password) {
    $password = $settings["password"];
  }
  if(! $username) {
    $username = $settings["username"];
  }
  
  $map = $case->post($webroot . "php/map.php");
  $cols = preg_split('/[;\n]/', $map);
  $challenge = $cols[7];
  $hash = md5($challenge . md5($password . strtolower($username)));
  $legacyhash = md5($challenge . md5($password . $username));
  $params = array("name" => $username,
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
