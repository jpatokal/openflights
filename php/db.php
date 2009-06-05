<?php
$host = "localhost";
$dbname = "flightdb2";
$user = "openflights";
$password = "";

$db = mysql_connect($host,$user,$password);
mysql_select_db($dbname, $db);
mysql_query("SET NAMES 'utf8'");
?>
