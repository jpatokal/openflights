<?php
// Copyright 2007 Facebook Corp.  All Rights Reserved. 
// 
// Application: OpenFlights
// File: 'index.php' 
//   This is a sample skeleton for your application. 
// 

require_once 'php/facebook.php';

$appapikey = '943d555bf52053c4736d68f6f33052fc';
$appsecret = '24cf8a342fa2c19a8bc4ded24a5fefb0';
$facebook = new Facebook($appapikey, $appsecret);
$user_id = $facebook->require_login();

// Greet the currently logged-in user!
echo "<p>Hello, <fb:name uid=\"$user_id\" useyou=\"false\" />!</p>";

// Statistics
// Number of flights, total distance (mi), total duration (minutes), public/open
$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);
$sql = "SELECT COUNT(*) AS count, SUM(distance) AS distance, SUM(TIME_TO_SEC(duration))/60 AS duration FROM flights AS f WHERE uid=3";
$result = mysql_query($sql, $db);
if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  if($row["count"] == "0" && $user && $user != "0") {
    die('Error;This user has no flights.');
  }
  printf("<p>%s;%s;%s;%s;%s;%s;%s;%s</p>", $row["count"], $row["distance"], $row["duration"], $public, $elite,
	 $logged_in, $editor, $challenge);
}
