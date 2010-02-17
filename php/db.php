<?php
$host = "localhost";
$dbname = "flightdb2";
$user = "openflights";
$password = "";

// OpenFlights UID for admin user, used only for special access to airport/airline DBs
$OF_ADMIN_UID = 3;

$db = mysql_connect($host,$user,$password);
if (!$db) {
  die('Error;Unable to connect to database: ' . mysql_error());
}
if(!mysql_select_db($dbname, $db)) {
  die('Error;Unable to select database: ' . mysql_error());
}
mysql_query("SET NAMES 'utf8'");
?>
