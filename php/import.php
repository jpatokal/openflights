<?php
session_start();
?>
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
  
    <h1>OpenFlights: Import</h1>
<?php
$uid = $_SESSION["uid"];
if(!$uid or empty($uid)) {
  printf("Not logged in, aborting");
  exit;
}

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
  $sql = "select apid,city,country from airports where iata='" . mysql_real_escape_string($code) . "'";
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
    $dbrow = mysql_fetch_array($result, MYSQL_ASSOC);
    $apid = $dbrow["apid"];
    $code = $code . "<br><small>" . $dbrow["city"] . "," . $dbrow["country"] . "</small>";
    $color="#ddf";
  }
  return array($apid, $code, $color);
}

$uploaddir = '/var/www/openflights/import/';

$action = $HTTP_POST_VARS["action"];
switch($action) {
 case "Upload":
  $uploadfile = $uploaddir . basename($_FILES['userfile']['tmp_name']);
  if (move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadfile)) {
    echo "<b>Upload successful.  Parsing...</b><br><h4>Results</h4>";
    flush();
    print "Tmpfile " . basename($_FILES['userfile']['tmp_name']) . "<br>"; // DEBUG
  } else {
    echo "<b>Upload failed!</b>";
    exit;
  }
  break;

 case "Import":
  $remove_these = array(' ','`','"','\'','\\','/');
  $filename = $HTTP_POST_VARS["tmpfile"];
  $uploadfile = $uploaddir . str_replace($remove_these,'', $filename);
  print "<H4>Importing...</H4>";
  print "Tmpfile " . $filename . "<br>"; // DEBUG
  flush();
  break;

 default:
   exit;
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

if($action == "Upload") {
  print "<table style='border-spacing: 3'><tr>";
  print "<th>ID</th><th>Date</th><th>Flight</th><th>From</th><th>To</th><th>Miles</th><th>Time</th><th>Plane</th><th>Reg</th>";
  print "<th>Seat</th><th>Class</th><th>Type</th><th>Reason</th><th>Comment</th></tr>";
}

// Rows with valign=top are data rows
$rows = $table->find('tr[valign=top]');
$count = 0;
foreach($rows as $row) {
  $cols = $row->find('td[class=liste],td[class=liste_gross]');

  $id = $cols[0]->plaintext;

  // Read and validate date field
  $dates = explode('<br>', $cols[1]);
  $src_date = strip_tags($dates[0]); // <td class="liste"><nobr>xx...xx</nobr>
  if(strlen($src_date) == 4) {
    $src_date = "01.01." . $src_date;
  }
  if(strstr($src_date, "-")) {
    $dateFormat = "%m-%d-%Y";
  } else {
    $dateFormat = "%d.%m.%Y";
  }
  $sql = sprintf("SELECT STR_TO_DATE('%s', '%s')", $src_date, $dateFormat);
  $result = mysql_query($sql, $db);
  $db_date = mysql_result($result, 0); 
  if($db_date == "") {
    $date_bgcolor = "#faa";
    $status = "disabled";
    $fatal = "date";
  } else {
    $date_bgcolor = "#fff";
    $src_date = $db_date;
  }

  $src_iata = $cols[2]->plaintext;
  $dst_iata = $cols[4]->plaintext;
  list($src_apid, $src_iata, $src_bgcolor) = fm_check_airport($db, $src_iata);
  list($dst_apid, $dst_iata, $dst_bgcolor) = fm_check_airport($db, $dst_iata);
  if(!$src_apid || !$dst_apid) {
    $status = "disabled";
    $fatal = "airport";
  }

  $distance = substr($cols[6]->find('td[align=right]', 0)->plaintext, 0, -6); // peel off trailing &nbsp;
  $distance = str_replace(',', '', $distance);
  $dist_unit = $cols[6]->find('tr', 0)->find('td', 1)->plaintext;
  if($dist_unit == "km") {
    $distance = round($distance/1.609344); // km to mi
  }
  $duration = substr($cols[6]->find('td[align=right]', 1)->plaintext, 0, -6);

  $flight = explode('<br>', $cols[7]);
  $airline = fm_strip_liste($flight[0]);
  if($airline == "") {
    $airline = "Private flight<br><small>(was: No airline)</small>";
    $airline_bgcolor = "#ddf";
    $alid = 1;
  } else {
    $number = str_replace('</td>', '', $flight[1]);
    $code = substr($number, 0, 2);
    // is alphanumeric, but not all numeric? then it's probably an airline code
    if(ereg("[a-zA-Z0-9]{2}", $code) && ! ereg("[0-9]{2}", $code)) {
      $sql = "select name,alid from airlines where iata='" . $code . "' order by name";
    } else {
      $code = null;
      $airlinepart = explode(' ', $airline);
      $sql = sprintf("select name,alid from airlines where name like '%s%%' and (iata != '' or uid = %s) order by name",
		     mysql_real_escape_string($airlinepart[0]), $uid);
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
	$airline = $dbrow['name'];
      } else {
	$airline_bgcolor = "#ddf";
	$airline = $dbrow['name'] . "<br><small>(was: " . $airline . ")</small>";
      }
      $alid = $dbrow['alid'];
      break;
      
      // Many matches
    default:
      $airline_bgcolor = "#fdd";
      $alid = null;
      while($dbrow = mysql_fetch_array($result, MYSQL_ASSOC)) {
	if(stristr($dbrow['name'], $airline)) {
	  $airline_bgcolor = "#fff";
	  $airline = $dbrow['name'];
	  $alid = $dbrow['alid'];
	  break;
	}
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
      $plid = "-1"; // new plane
      $plane_bgcolor = "#fdd";
    }
  } else {
    $plid = null;
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

  switch($action) {
  case "Upload":
    printf ("<tr><td>%s</td><td style='background-color: %s'>%s</td><td style='background-color: %s'>%s %s</td><td style='background-color: %s'>%s</td><td style='background-color: %s'>%s</td><td>%s</td><td>%s</td><td style='background-color: %s'>%s</td><td>%s</td><td>%s %s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>\n", $id, $date_bgcolor, $src_date, $airline_bgcolor, $airline, $number,
	  $src_bgcolor, $src_iata, $dst_bgcolor, $dst_iata, $distance, $duration, $plane_bgcolor, $plane, $reg,
	  $seatnumber, $seatpos, $seatclass, $seattype, $seatreason, $comment);
    break;

  case "Import":
    $classMap = array("Economy"=>"Y", "Business"=>"C", "First"=>"F");
    $reasonMap = array("Business"=>"B", "Personal"=>"L", "Crew"=>"C");

    // Do we need a new plane?
    if($plid == -1) {
      $sql = "INSERT INTO planes(name) VALUES('" . mysql_real_escape_string($plane) . "')";
      mysql_query($sql, $db) or die ('0;Adding new plane failed: ' . $sql . ', error ' . mysql_error());
      $plid = mysql_insert_id();
      print "Plane:" . $plane . " ";
    }

    // Do we need a new airline?
    if(! $alid) {
      $sql = sprintf("INSERT INTO airlines(name, uid) VALUES('%s', %s)",
		     mysql_real_escape_string($airline), $uid);
      mysql_query($sql, $db) or mysql_query($sql, $db) or die ('0;Adding new airline failed: ' . $sql . ', error ' . mysql_error());
      $alid = mysql_insert_id();
      print "Airline:" . $airline . " ";
    }

    // Hack to record X-Y and Y-X flights as same in DB
    if($src_apid > $dst_apid) {
      $tmp = $src_apid;
      $src_apid = $dst_apid;
      $dst_apid = $tmp;
      $opp = "Y";
    } else {
      $opp = "N";
    }

    // And now the flight 
    $sql = sprintf("INSERT INTO flights(uid, src_apid, src_time, dst_apid, duration, distance, registration, code, seat, seat_type, class, reason, note, plid, alid, trid, upd_time, opp) VALUES (%s, %s, '%s', %s, '%s', %s, '%s', '%s', '%s', '%s', '%s', '%s', '%s', %s, %s, NULL, NOW(), '%s')",
		   $uid, $src_apid, mysql_real_escape_string($src_date),
		   $dst_apid, mysql_real_escape_string($duration),
		   mysql_real_escape_string($distance), mysql_real_escape_string($reg), mysql_real_escape_string($number),
		   mysql_real_escape_string($seatnumber), substr($seatpos, 0, 1), $classMap[$seatclass],
		   $reasonMap[$seatreason], mysql_real_escape_string($comment),
		   ($plid ? $plid : "NULL"), $alid, $opp);
    mysql_query($sql, $db) or die('0;Importing flight failed ' . $sql . ', error ' . mysql_error());
    print $id . " ";
    break;
  }
}

if($action == "Upload") {
?>
</table>

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

<form name="importform" action="/php/import.php" method="post">

<?php
if($status == "disabled") {
  print "<font color=red>Error: ";
  switch($fatal) {
  case "airport":
    print "Your flight data includes unrecognized airports.  Please add them to the database and try again. ";
    print "<INPUT type='button' value='Add new airport' onClick='javascript:window.open(\"/html/apsearch.html\", \"Airport\", \"width=500,height=580,scrollbars=yes\")'>";
    break;
  case "date":
    print "Some date fields could not be parsed.  Please change them to use any of these three formats: MM-DD-YYYY, DD.MM.YYYY, or YYYY only.";
    break;
  }
  print "</font><br><br>";
} else {
  print "<b>Parsing completed successfully.</b> You are now ready to import these flights into your OpenFlights.  (Minor issues can be corrected afterwards in the flight editor.)<br><br>";
}
print "<INPUT type='hidden' name='tmpfile' value='". basename($_FILES['userfile']['tmp_name']) . "'>";
print "<INPUT type='submit' name='action' title='Add these flights to your OpenFlights' value='Import' " . $status . ">";
?>

<INPUT type="button" value="Upload again" title="Cancel this import and return to file upload page" onClick="history.back(-1)">

<INPUT type="button" value="Cancel" onClick="window.close()">

<?php
}
if($action == "Import") {
  print "<BR><H4>Flights successfully imported.</H4><BR>";
  print "<INPUT type='button' value='Import more flights' onClick='javascript:window.location=\"/html/import.html\"'>";
  print "<INPUT type='button' value='Close' onClick='javascript:parent.opener.refresh(true); window.close();'>";
}

?>
  </form>

  </body>
</html>
