<?php
header("Content-type: text/html; charset=iso-8859-1");

include 'helper.php';

$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);

$code = $HTTP_POST_VARS["code"];
if(!$code) $code = $HTTP_GET_VARS["code"];
$type = $HTTP_POST_VARS["type"];
if(!$type) $type = $HTTP_GET_VARS["type"];

$error = false;
$len = strlen($code);

switch($type) {
 case "AIRLINE":
   //
   // Map an airline code (2-digit IATA or 3-digit ICAO) to an airline in the DB
   //
   switch($len) {
   case 2: // IATA
     $sql = "SELECT alid,name,iata,icao FROM airlines WHERE iata='" . mysql_real_escape_string($code) . "' LIMIT 1";
     break;
     
   case 3: // ICAO
     $sql = "SELECT alid,name,iata,icao FROM airlines WHERE icao='" . mysql_real_escape_string($code) . "' LIMIT 1";
     break;
     
   default:
     // This should never be called with anything other than an IATA/ICAO id
     die("0;0");
   }
   break;

 case "src_ap":
 case "dst_ap":
   //
   // Map an airport code (3-digit IATA or 4-digit ICAO) to an airline in the DB
   //
   $sql = "SELECT apid,x,y,name,city,country,iata,icao FROM airports WHERE ";
   switch($len) {
   case 3: // IATA
     $sql = $sql . "iata='" . mysql_real_escape_string($code) . "' LIMIT 1";
     break;
     
   case 4:
     $sql = $sql . "icao='" . mysql_real_escape_string($code) . "' LIMIT 1";
     break;
     
   default:
     // This should never be called...
     die("0;0");
   }
}

$result = mysql_query($sql, $db);
if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  switch($type) {
  case "AIRLINE":
    printf ("%s;%s", $row["alid"], format_airline($row));
    break;

  case "src_ap":
  case "dst_ap":
    printf ("%s:%s:%s:%s;%s", format_apcode($row), $row["apid"], $row["x"], $row["y"], format_airport($row));
    break;
  }
} else {
  printf ("0;0");
}

?>
