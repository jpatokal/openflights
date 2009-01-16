<?php

// Address of OpenFlights install
$webroot = 'http://192.168.1.4:8888/';

// Settings for automated test user
$settings = array('username' => 'autotest',
		  'password' => 'autotest',
		  'email' => 'test@openflights.example',
		  'privacy' => 'Y',
		  'editor' => 'B');

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
  $hash = md5($challenge . md5($password . $username));
  $params = array("name" => $username,
		  "pw" => $hash,
		  "lpw" => "bar");
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
