<?php
$host = "localhost";
$dbname = "flightdb";
$user = "openflights";
$password = "";

$db = mysql_connect($host,$user,$password);
mysql_select_db($dbname, $db);
?>
