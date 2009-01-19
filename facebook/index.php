<?php
// Copyright 2007 Facebook Corp.  All Rights Reserved. 
// 
// Application: OpenFlights
// File: 'index.php' 
//   This is a sample skeleton for your application. 
// 

require_once 'php/facebook.php';
require_once 'keys.php';

// appapikey,appsecret must be defined in keys.php
$facebook = new Facebook($appapikey, $appsecret);
$user_id = $facebook->require_login();

// Greet the currently logged-in user!
echo "<p>Hello, <fb:name uid=\"$user_id\" useyou=\"false\" />!</p>";

if($_REQUEST["ofname"]){
  $ofname = $_REQUEST["ofname"];
 } else {
  echo "<form requirelogin=\"1\">";
  echo "<h2>Configuration</h2>";
  echo "<p>Please enter your username on OpenFlights: <input type='text' name='ofname' value='$ofname' /></p>";
  echo "<input type='submit' value='Submit' />";
  echo "</form>";
 }

$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);
$sql = "SELECT public, uid FROM users WHERE name='" . $ofname . "'";
$result = mysql_query($sql, $db);
if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  if($row["public"] == "N") {
    die("Sorry, $ofname's profile is set to Private.  Please go to 'Settings', change it to Public or Open, and try again.");
  }
} else {
  die("Sorry, couldn't find $ofname at OpenFlights.  Please hit 'Back' and try again.");
}
$uid = $row["uid"];

// Statistics
// Number of flights, total distance (mi), total duration (minutes), public/open
$sql = "SELECT COUNT(*) AS count, SUM(distance) AS distance, SUM(TIME_TO_SEC(duration))/60 AS duration FROM flights AS f WHERE uid=" . $uid;
$result = mysql_query($sql, $db);
if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  printf("<p><b>%s</b> has flown <b>%s</b> times, for a total distance of <b>%s</b> miles and a total duration of <b>%s</b> minutes!</p>", $ofname, $row["count"], $row["distance"], $row["duration"]);
}
