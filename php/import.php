<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>OpenFlights: Import</title>
    <link rel="stylesheet" href="/css/style_reset.css" type="text/css">
    <link rel="stylesheet" href="/openflights.css" type="text/css">

    <script type="text/javascript" src="/js/apsearch.js"></script>
  </head>

  <body>
    <div id="contexthelp">
  
    <h1>OpenFlights: Parse imported data</h1>

<?php
include_once('simple_html_dom.php');
include_once('helper.php');


//
// Strip out surrounding FlightMemory "liste" <td> from value
//
// <td class="liste">value[</td> --> value
function fm_strip_liste($value) {
  $value = substr($value, 18);
  $value = str_replace('&nbsp;', '', $value);
  $value = trim(str_replace('</td>', '', $value));

  if(strlen($value) == 1) {
    return "";
  } else {
    return $value;
  }
}
  
function fm_check_airport($db, $code) {
  $sql = "select apid from airports where iata='" . mysql_real_escape_string($code) . "'";
  $result = mysql_query($sql, $db);
  switch(mysql_num_rows($result)) {

    // No match
  case "0":
    $color = "#faa";
    break;

    // Solitary match
  case "1":
    $apid = mysql_result($result, 0);
    $color="#fff";
    break;
    
    // Multiple matches
  default:
    $apid = mysql_result($result, 0);
    $color="#ddf";
  }
  return array($apid, $color);
}

$uploaddir = '/var/www/openflights/import/';
$uploadfile = $uploaddir . basename($_FILES['userfile']['name']);

if (move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadfile)) {
  echo "<b>Upload successful.  Parsing...</b><br><h4>Results</h4>";
  flush();
} else {
  echo "No file uploaded, using test file.<br>";
  $uploadfile = $uploaddir . "fm.html";
}

// Parse it
$html = file_get_html($uploadfile);
if(! $html) {
  echo "Sorry, this file does not appear to be HTML.";
  exit;
}

$title = $html->find('title', 0);
if($title->plaintext != "FlightMemory - FlightData") {
  echo "Sorry, this HTML file does not appear contain FlightMemory FlightData.";
  exit;
}

// 3nd table has the data
$status = "";
$table = $html->find('table', 2);

$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);

print "<table style='border-spacing: 3'><tr>";
print "<th>ID</th><th>Date</th><th>Flight</th><th>From</th><th>To</th><th>Miles</th><th>Time</th><th>Plane</th><th>Reg</th>";
print "<th>Seat</th><th>Class</th><th>Type</th><th>Reason</th><th>Comment</th></tr>";

// Rows with valign=top are data rows
$rows = $table->find('tr[valign=top]');
$count = 0;
foreach($rows as $row) {
  $cols = $row->find('td[class=liste],td[class=liste_gross]');

  $id = $cols[0]->plaintext;
  $dates = explode('<br>', $cols[1]);
  $src_date = substr($dates[0], 24, -7); // Eeew...  <td class="liste"><nobr>xx...xx</nobr>
  if(strlen($src_date) == 4) {
    $src_date = "01.01." . $src_date;
  }

  $src_iata = $cols[2]->plaintext;
  $dst_iata = $cols[4]->plaintext;
  list($src_apid, $src_bgcolor) = fm_check_airport($db, $src_iata);
  list($dst_apid, $dst_bgcolor) = fm_check_airport($db, $dst_iata);
  if(!$src_apid || !$dst_apid) $status = "disabled";

  $distance = substr($cols[6]->find('<td>[align=right]', 0)->plaintext, 0, -6); // peel off trailing &nbsp;
  $distance = str_replace(',', '', $distance);
  $duration = substr($cols[6]->find('<td>[align=right]', 1)->plaintext, 0, -6);

  $flight = explode('<br>', $cols[7]);
  $airline = fm_strip_liste($flight[0]);
  $number = str_replace('</td>', '', $flight[1]);
  $code = substr($number, 0, 2);
  // is alphanumeric, but not all numeric? then it's probably an airline code
  if(ereg("[a-zA-Z0-9]{2}", $code) && ! ereg("[0-9]{2}", $code)) {
    $sql = "select name,alid from airlines where iata='" . $code . "' order by name";
  } else {
    $code = null;
    $airlinepart = explode(' ', $airline);
    $sql = "select name,alid from airlines where name like '" . $airlinepart[0] . "%' and iata != '' order by name";
  }

  // validate the airline/code against the DB
  $result = mysql_query($sql, $db);
  switch(mysql_num_rows($result)) {

    // No match
  case "0":
    $airline_bgcolor = "#fdd";
    $alid = null;
    break;

    // Solitary match
  case "1":
    $dbrow = mysql_fetch_array($result, MYSQL_ASSOC);
    if(stristr($dbrow['name'], $airline)) {
      $airline_bgcolor = "#fff";
    } else {
      $airline_bgcolor = "#ddf";
    }
    $airline = $dbrow['name'];
    $alid = $dbrow['alid'];
    break;

    // Many matches
  default:
    $airline_bgcolor = "#fdd";
    while($dbrow = mysql_fetch_array($result, MYSQL_ASSOC)) {
      if(stristr($dbrow['name'], $airline)) {
	$airline_bgcolor = "#fff";
	$airline = $dbrow['name'];
	$alid = $dbrow['alid'];
	break;
      }
    }
  }

  // Load plane model (plid)
  $planedata = explode('<br>', $cols[8]);
  $plane = fm_strip_liste($planedata[0]);
  if($plane != "") {
    $sql = "select plid from planes where name='" . mysql_real_escape_string($plane) . "'";
    $result = mysql_query($sql, $db);
    if(mysql_num_rows($result) == 1) {
      $plid = mysql_result($result, 0);
      $plane_bgcolor = "#fff";
    } else {
      $plid = null;
      $plane_bgcolor = "#fdd";
    }
  } else {
    $plane_bgcolor = "#fff";
  }

  if($planedata[1]) {
    $reg = $planedata[1];
  } else {
    $reg = "";
  }


  // <td class="liste">12A/Window<br><small>Economy<br>Passenger<br>Business</small></td>
  $seatdata = explode('<br>', $cols[9]);
  $seatdata2 = explode('/', $seatdata[0]);

  $seatnumber = fm_strip_liste($seatdata2[0]);
  $seatpos = trim(str_replace('&nbsp;', '', $seatdata2[1]));
  if(strlen($seatpos) < 2) {
    $seatpos = "";
  }
  $seatclass = substr($seatdata[1], 7);
  $seattype = $seatdata[2];
  $seatreason = substr($seatdata[3], 0, strpos($seatdata[3], '<'));
  
  $span = $cols[10]->find('span', 0);
  if($span) {
    $comment = trim(substr($span->title, 9));
  } else {
    $comment = "";
  }
  printf ("<tr><td>%s</td><td>%s</td><td style='background-color: %s'>%s %s</td><td style='background-color: %s'>%s</td><td style='background-color: %s'>%s</td><td>%s</td><td>%s</td><td style='background-color: %s'>%s</td><td>%s</td><td>%s %s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>\n", $id, $src_date, $airline_bgcolor, $airline . $alid, $number,
	  $src_bgcolor, $src_iata . $src_apid, $dst_bgcolor, $dst_iata . $dst_apid, $distance, $duration, $plane_bgcolor, $plane . $plid, $reg,
	  $seatnumber, $seatpos, $seatclass, $seattype, $seatreason, $comment);

}

print "</table>";
?>

<h4>Key to results</h4>

<table style='border-spacing: 3'>
 <tr>
  <th>Color</th><th>Meaning</th>
 </tr><tr style='background-color: #fff'>
  <td>None</td><td>Exact match</td>
 </tr><tr style='background-color: #ddf'>
  <td>Info</td><td>Probable match, please verify
 </tr><tr style='background-color: #fdd'>
  <td>Warning</td><td>No matches, will be added as new</td>
 </tr><tr style='background-color: #faa'>
  <td>Error</td><td>No matches, please correct and reupload</td>
 </tr>
</table><br>

<?php
print "<INPUT type='submit' value='Confirm import' " . $status . ">";
?>

<INPUT type="button" value="Upload again" onClick="history.back(-1)">

<INPUT type="button" value="Cancel" onClick="window.close()">

  </body>
</html>
