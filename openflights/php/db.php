<?php
$host = "localhost";
$dbname = "flightdb2";
$user = "openflights";
$password = "";

// OpenFlights UID for admin user, used only for special access to airport/airline DBs
$OF_ADMIN_UID = 3;

$db = mysql_connect($host,$user,$password);
mysql_select_db($dbname, $db);
mysql_query("SET NAMES 'utf8'");
?>
