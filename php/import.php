<?php
require_once("locale.php");
require_once("db.php");
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>OpenFlights: <?php echo _("Import") ?></title>
    <link rel="stylesheet" href="/css/style_reset.css" type="text/css">
    <link rel="stylesheet" href="/openflights.css" type="text/css">

    <script type="text/javascript" src="/js/apsearch.js"></script>
  </head>

  <body>
    <div id="contexthelp">
  
  <h1>OpenFlights: <?php echo _("Import") ?></h1>
<?php
$uid = $_SESSION["uid"];
if(!$uid or empty($uid)) {
  die_nicely(_("Not logged in, aborting"));
}

include_once('simple_html_dom.php');
include_once('helper.php');

$posMap = array("Window"=>"W", "Middle"=>"Y", "Aisle"=>"A");
$classMap = array("Economy"=>"Y", "Prem.Eco"=>"P", "Business"=>"C", "First"=>"F");
$reasonMap = array("Business"=>"B", "Personal"=>"L", "Crew"=>"C", "Other"=>"O");

//
// Strip out surrounding FlightMemory "liste" <td> from value
//
// <td class="liste">value[</td> --> value
function fm_strip_liste($value) {
  $value = strip_tags($value);
  $value = str_replace('&nbsp;', '', $value);

  if(strlen($value) == 1) {
    return "";
  } else {
    return $value;
  }
}

// Validate date field
// Must be one of YYYY, MM-DD-YYYY (FM only), YYYY-MM-DD (CSV only), MM/DD/YYYY or DD.MM.YYYY
function check_date($db, $type, $date) {
  if(strlen($date) == 4) {
    $date = "01.01." . $date;
  }
  if(strstr($date, "-")) {
    if($type == "FM") {
      $dateFormat = "%m-%d-%Y";
    } else {
      $dateFormat = "%Y-%m-%d";
    }
  } else if(strstr($date, "/")) {
    $dateFormat = "%m/%d/%Y";
  } else {
    $dateFormat = "%d.%m.%Y";
  }
  $sql = sprintf("SELECT STR_TO_DATE('%s', '%s')", $date, $dateFormat);
  $result = mysql_query($sql, $db);
  $db_date = mysql_result($result, 0); 
  if($db_date == "") {
    $date = null;
    $color = "#faa";
  } else {
    $color = "#fff";
    $date = $db_date;
  }
  return array($date, $color);
}

// Validate that this code/name match an airport 
function check_airport($db, $code, $name) {
  switch(strlen($code)) {
  case 3:
    $sql = "select apid,city,country from airports where iata='" . mysql_real_escape_string($code) . "'";
    break;

  case 4:
    $sql = "select apid,city,country from airports where icao='" . mysql_real_escape_string($code) . "'";
    break;

  default:
    $sql = "select apid,city,country from airports where name like '" . mysql_real_escape_string($name) . "%'";
    break;
  }
  $result = mysql_query($sql, $db);
  switch(@mysql_num_rows($result)) {
    // No match
  case "0":
    $apid = null;
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

// Validate that this flight number/airline name are found in DB
// If flight number starts with an IATA code, match that (and double-check it against name)
// Else match first word of airline name
// If $history == "yes", ignore codes and ignore errors
function check_airline($db, $number, $airline, $uid, $history) {
  $code = substr($number, 0, 2);
  $isAlpha = ereg("[a-zA-Z0-9]{2}", $code) && ! ereg("[0-9]{2}", $code);
  if($airline == "" && ! $isAlpha) {
    $airline = _("Unknown") . "<br><small>(" . _("was:") . " " . _("No airline") . ")</small>";
    $color = "#ddf";
    $alid = -1;
  } else {
    // is alphanumeric, but not all numeric? then it's probably an airline code
    if($isAlpha && $history != "yes") {
      $sql = sprintf("select name,alias,alid from airlines where iata='%s' order by name",
		     $code, $uid);
    } else {
      $airlinepart = explode(' ', $airline);
      if($airlinepart[0] == 'Air') {
	$part = 'Air ' . $airlinepart[1];
      } else {
	$part = $airlinepart[0];
      }
      $sql = sprintf("select name,alias,alid from airlines where (name like '%s%%' or alias like '%s%%') and (iata != '' or uid = %s) order by name",
		     mysql_real_escape_string($part), mysql_real_escape_string($part), $uid);
    }
    
    // validate the airline/code against the DB
    $result = mysql_query($sql, $db);
    switch(@mysql_num_rows($result)) {
      
      // No match, add as new if we have a name for it, else return error
    case "0":
      if($airline != "") {
	$color = "#fdd";
	$alid = -2;
      } else {
	$color = "#faa";
	$alid = null;
      }
      break;
      
      // Solitary match
    case "1":
      $dbrow = mysql_fetch_array($result, MYSQL_ASSOC);
      if($airline != "" && (strcasecmp($dbrow['name'], $airline) == 0 || strcasecmp($dbrow['alias'], $airline) == 0)) {
	// Exact match
	$color = "#fff";
	$airline = $dbrow['name'];
	$alid = $dbrow['alid'];
      } else {
	// Not an exact match
	if($history == "yes") {
	  $color = "#fdd";
	  $alid = -2;
	} else {
	  $color = "#ddf";
	  $airline = $dbrow['name'] . "<br><small>(" . _("was:") . " " . $airline . ")</small>";
	  $alid = $dbrow['alid'];
	}
      }
      break;
      
      // Many matches, default to first with a warning if we can't find an exact match
    default:
      $color = "#ddf";
      $first = true;
      while($dbrow = mysql_fetch_array($result, MYSQL_ASSOC)) {
	$isMatch = $airline != "" && ((strcasecmp($dbrow['name'], $airline) == 0) ||
				      (strcasecmp($dbrow['alias'], $airline) == 0));
	if($first || $isMatch) {
	  if($isMatch) $color = "#fff";
	  if($first) $first = false;
	  $new_airline = $dbrow['name'];
	  $alid = $dbrow['alid'];
	}
      }
      // No match and in historical mode? Add it as new
      if($history == "yes" && $color == "#ddf") {
	$color = "#fdd";
	$alid = -2;
      } else {
	$airline = $new_airline;
      }
    }
  }
  return array($alid, $airline, $color);
}

// Validate that this plane is in DB
function check_plane($db, $plane) {
  // If no plane set, return OK
  if(!$plane || $plane == "") {
    return array(null, "#fff");
  }

  $sql = "select plid from planes where name='" . mysql_real_escape_string($plane) . "'";
  $result = mysql_query($sql, $db);
  if(@mysql_num_rows($result) == 1) {
    $plid = mysql_result($result, 0);
    $color = "#fff";
  } else {
    $plid = "-1"; // new plane
    $color = "#fdd";
  }
  return array($plid, $color);
}

// Validate that the importing user owns this trip
function check_trip($db, $uid, $trid) {
  // If no trip set, return OK
  if(!$trid || $trid == "") {
    return array(null, "#fff");
  }

  $sql = "select uid from trips where trid=" . mysql_real_escape_string($trid);
  $result = mysql_query($sql, $db);
  if(@mysql_num_rows($result) == 1) {
    if($uid == mysql_result($result, 0)) {
      $color = "#fff";
    } else {
      $color = "#faa";
    }
  } else {
    $color = "#faa";
  }
  return array($trid, $color);
}

function die_nicely($msg) {
  print $msg . "<br><br>";
  print "<INPUT type='button' value='" . _("Upload again") . "' title='" . _("Cancel this import and return to file upload page") . "' onClick='history.back(-1)'>";
  exit;
}

$uploaddir = '/var/www/openflights/import/';

$action = $_POST["action"];
switch($action) {
 case _("Upload"):
  $uploadfile = $uploaddir . basename($_FILES['userfile']['tmp_name']);
  if (move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadfile)) {
    echo "<b>" . _("Upload successful.  Parsing...") . "</b><br><h4>" . _("Results") . "</h4>";
    flush();
    print "Tmpfile " . basename($_FILES['userfile']['tmp_name']) . "<br>"; // DEBUG
  } else {
    die_nicely("<b>" . _("Upload failed!") . "</b>");
  }
  break;

 case _("Import"):
  $remove_these = array(' ','`','"','\'','\\','/');
  $filename = $_POST["tmpfile"];
  $uploadfile = $uploaddir . str_replace($remove_these,'', $filename);
  print "<H4>" . _("Importing...") . "</H4>";
  print "Tmpfile " . $filename . "<br>"; // DEBUG
  flush();
  break;

 default:
   die_nicely("Unknown action $action");
}

$fileType = $_POST["fileType"];
$history = $_POST["historyMode"];
$status = "";
$id_note = false;

switch($fileType) {
 case "FM":
   // Parse it
   $html = file_get_html($uploadfile);
   if(! $html) {
     die_nicely(_("Sorry, this file does not appear to be FlightMemory HTML."));
   }
   
   $title = $html->find('title', 0);
   if($title->plaintext != "FlightMemory - FlightData") {
     die_nicely(_("Sorry, this HTML file does not appear contain FlightMemory FlightData."));
   }
   
   // 3nd table has the data
   $table = $html->find('table', 2);
   $rows = $table->find('tr[valign=top]');
   break;

 case "CSV":
   if($action == _("Upload") && substr($_FILES["userfile"]["name"], -4) != ".csv") {
     die_nicely(_("Sorry, the filename must end in '.csv'."));
   }

   $csvfile = fopen($uploadfile, "r");
   if(! $csvfile) {
     die_nicely(_("Unable to open CSV file."));
   }

   // Convert whole file into giant array
   // (stupid workaround for PHP 5.2 because str_getcsv() is not standard yet)
   $rows = array();
   while (($data = fgetcsv($csvfile, 1000, ",")) !== FALSE) {
     $rows[] = $data;
   }
   fclose($csvfile);

   break;
 }

if($action == _("Upload")) {
  print "<table style='border-spacing: 3'><tr>";
  print "<th>ID</th><th colspan=2>" . _("Date") . "</th><th>" . _("Flight") . "</th><th>" . _("From") . "</th><th>" . _("To") . "</th><th>" . _("Miles") . "</th><th>" . _("Time") . "</th><th>" . _("Plane") . "</th><th>" . _("Reg") . "</th>";
  print "<th>" . _("Seat") . "</th><th>" . _("Class") . "</th><th>" . _("Type") . "</th><th>" . _("Reason") . "</th><th>" . _("Trip") . "</th><th>" . _("Comment") . "</th></tr>";
}

$count = 0;
foreach($rows as $row) {
  switch($fileType) {
  case "FM":
    $cols = $row->find('td[class=liste],td[class=liste_gross],td[class=liste_gross_rot],td[class=liste_rot],th[class=liste_gross],th[class=liste_gross_rot]');
    
    $id = $cols[0]->plaintext;
    
    // Read and validate date field
    $dates = explode('<br>', $cols[1]);
    $src_date = strip_tags($dates[0]); // <td class="liste"><nobr>xx...xx</nobr>
    $src_time = trim(strip_tags($dates[1]));
    list($src_date, $date_bgcolor) = check_date($db, $fileType, $src_date);
    
    $src_iata = $cols[2]->plaintext;
    $dst_iata = $cols[4]->plaintext;
    
    // <td class="liste"><b>Country</b><br>Town<br>Airport Blah Blah</td>
    //                                             ^^^^^^^ target
    $src_names = end(explode('<br>', $cols[3]));
    $src_name = reset(preg_split('/[ \/<]/', $src_names));
    $dst_names = end(explode('<br>', $cols[5]));
    $dst_name = reset(preg_split('/[ \/<]/', $dst_names));
    
    list($src_apid, $src_iata, $src_bgcolor) = check_airport($db, $src_iata, $src_name);
    list($dst_apid, $dst_iata, $dst_bgcolor) = check_airport($db, $dst_iata, $dst_name);
    
    $distance = substr($cols[6]->find('td[align=right]', 0)->plaintext, 0, -6); // peel off trailing &nbsp;
    $distance = str_replace(',', '', $distance);
    $dist_unit = $cols[6]->find('tr', 0)->find('td', 1)->plaintext;
    if($dist_unit == "km") {
      $distance = round($distance/1.609344); // km to mi
    }
    $duration = substr($cols[6]->find('td[align=right]', 1)->plaintext, 0, -6);
    
    $flight = explode('<br>', $cols[7]);
    $airline = fm_strip_liste($flight[0]);
    $number = str_replace('</td>', '', $flight[1]);
    list($alid, $airline, $airline_bgcolor) = check_airline($db, $number, $airline, $uid, $history);
    
    // Load plane model (plid)
    // <TD class=liste>Boeing 737-600<BR>LN-RCW<BR>Yngvar Viking</TD>
    $planedata = explode('<br>', $cols[8]);
    $plane = fm_strip_liste($planedata[0]);
    if($plane != "") {
      list($plid, $plane_bgcolor) = check_plane($db, $plane);
    } else {
      $plid = null;
      $plane_bgcolor = "#fff";
    }
    
    if($planedata[1]) {
      $reg = strip_tags($planedata[1]);
      if($planedata[2]) {
	$reg .= " " . strip_tags($planedata[2]);
      }
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
    if($seatclass == "") {
      $seatclass = "Economy";
    }
    $seattype = $seatdata[2];
    $seatreason = substr($seatdata[3], 0, strpos($seatdata[3], '<'));
    
    $comment = "";
    if($cols[10]) {
      $span = $cols[10]->find('span', 0);
      if($span) {
	$comment = trim(substr($span->title, 9));
      }
    }
    break; // case FM

  case "CSV":
    $count++;
    if($count == 1) {
      // Skip header row
      continue;
    }
    $id = $count - 1;
    // 0 Date Time, 1 From, 2 To,3 Flight_Number, 4 Airline_Code, 5 Distance, 6 Duration, 7 Seat, 8 Seat_Type, 9 Class
    // 10 Reason, 11 Plane, 12 Registration, 13 Trip, 14 Note, 15 From_Code, 16 To_Code, 17 Airline_Code, 18 Plane_Code

    $datetime = explode(' ', $row[0]);
    list($src_date, $date_bgcolor) = check_date($db, $fileType, $datetime[0]);
    $src_time = $datetime[1];
    if(! $src_time) $src_time = "";

    $src_iata = $row[1];
    $src_apid = $row[15];
    if($src_apid) {
      $src_iata = "<small>ID $src_apid</small>";
      $src_bgcolor="#fff";
      $id_note = true;
    } else {
      list($src_apid, $src_iata, $src_bgcolor) = check_airport($db, $src_iata, $src_iata);
    }
    $dst_iata = $row[2];
    $dst_apid = $row[16];
    if($dst_apid) {
      $dst_iata = "<small>ID $dst_apid</small>";
      $dst_bgcolor="#fff";
      $id_note = true;
    } else {
      list($dst_apid, $dst_iata, $dst_bgcolor) = check_airport($db, $dst_iata, $dst_iata);
    }
    $number = $row[3];
    $airline = $row[4];
    $alid = $row[17];
    if($alid) {
      $airline = "<small>ID $alid</small>";
      $airline_bgcolor="#fff";
      $id_note = true;
    } else {
      list($alid, $airline, $airline_bgcolor) = check_airline($db, $number, $airline, $uid, $history);
    }
    $plane = $row[11];
    $plid = $row[18];
    if($plid) {
      $plane = "<small>ID $plid</small>";
      $plane_bgcolor="#fff";
      $id_note = true;
    } else {
      list($plid, $plane_bgcolor) = check_plane($db, $plane);
    }

    $distance = $row[5];
    $duration = $row[6];
    $seatnumber = $row[7];
    $seatpos = array_search($row[8], $posMap);
    $seatclass = array_search($row[9], $classMap);
    if($row[9] == "B") $seatclass = "Business"; // fix for typo in pre-0.3 versions of spec
    $seatreason = array_search($row[10], $reasonMap);
    $reg = $row[12];
    list($trid, $trip_bgcolor) = check_trip($db, $uid, $row[13]);
    $comment = $row[14];
    break;
  }
  
  // Skip first row for CSV
  if($fileType == "CSV" && $count == 1) continue;

  //Check if parsing succeeded and tag fatal errors if not
  if(!$src_date) {
    $status = "disabled";
    $fatal = "date";
  }
  if(!$src_apid || !$dst_apid) {
    $status = "disabled";
    $fatal = "airport";
  } else {
    if($duration == "" || $distance == "") {
      list($distance, $duration) = gcDistance($db, $src_apid, $dst_apid);
      $dist_bgcolor = "#ddf";
    } else {
      $dist_bgcolor = "#fff";
    }
  }
  if(!$alid) {
    $status = "disabled";
    $fatal = "airline";
  }
  if($trid && $trip_bgcolor != "#fff") {
    $status = "disabled";
    $fatal = "trip";
  }

  switch($action) {
  case _("Upload"):
    printf ("<tr><td>%s</td><td style='background-color: %s'>%s</td><td>%s</td><td style='background-color: %s'>%s %s</td><td style='background-color: %s'>%s</td><td style='background-color: %s'>%s</td><td style='background-color: %s'>%s</td><td style='background-color: %s'>%s</td><td style='background-color: %s'>%s</td><td>%s</td><td>%s %s</td><td>%s</td><td>%s</td><td>%s</td><td style='background-color: %s'>%s</td><td>%s</td></tr>\n", $id, $date_bgcolor, $src_date, $src_time, $airline_bgcolor, $airline, $number,
	    $src_bgcolor, $src_iata, $dst_bgcolor, $dst_iata, $dist_bgcolor, $distance, $dist_bgcolor, $duration, $plane_bgcolor, $plane, $reg,
	    $seatnumber, $seatpos, $seatclass, $seattype, $seatreason, $trip_bgcolor, $trid, $comment);
    break;

  case _("Import"):
    // Do we need a new plane?
    if($plid == -1) {
      $sql = "INSERT INTO planes(name) VALUES('" . mysql_real_escape_string($plane) . "')";
      mysql_query($sql, $db) or die ('0;Adding new plane failed: ' . $sql . ', error ' . mysql_error());
      $plid = mysql_insert_id();
      print "Plane:" . $plane . " ";
    }

    // Do we need a new airline?
    if($alid == -2) {
      // Last-ditch effort to check through non-IATA airlines
      $sql = sprintf("SELECT alid FROM airlines WHERE name='%s' OR alias='%s'");
      $result = mysql_query($sql, $db);
      if($dbrow = mysql_fetch_array($result, MYSQL_ASSOC)) {
	// Found it
	$alid = $dbrow["alid"];
      } else {
	$sql = sprintf("INSERT INTO airlines(name, uid) VALUES('%s', %s)",
		       mysql_real_escape_string($airline), $uid);
	mysql_query($sql, $db) or mysql_query($sql, $db) or die ('0;Adding new airline failed: ' . $sql . ', error ' . mysql_error());
	$alid = mysql_insert_id();
	print "Airline:" . $airline . " ";
      }
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
    $sql = sprintf("INSERT INTO flights(uid, src_apid, src_date, src_time, dst_apid, duration, distance, registration, code, seat, seat_type, class, reason, note, plid, alid, trid, upd_time, opp) VALUES (%s, %s, '%s', %s, %s, '%s', %s, '%s', '%s', '%s', '%s', '%s', '%s', '%s', %s, %s, %s, NOW(), '%s')",
		   $uid, $src_apid, mysql_real_escape_string($src_date),
		   ($src_time != "" ? "'" . $src_time . "'" : "NULL"), 
		   $dst_apid, mysql_real_escape_string($duration),
		   mysql_real_escape_string($distance), mysql_real_escape_string($reg), mysql_real_escape_string($number),
		   mysql_real_escape_string($seatnumber), substr($seatpos, 0, 1), $classMap[$seatclass],
		   $reasonMap[$seatreason], mysql_real_escape_string($comment),
		   ($plid ? $plid : "NULL"), $alid, ($trid ? $trid : "NULL"), $opp);
    mysql_query($sql, $db) or die('0;Importing flight failed ' . $sql . ', error ' . mysql_error());
    print $id . " ";
    break;
  }
}

if($action == _("Upload")) {
?>
</table>

    <h4><?php echo _("Key to results") ?></h4>

<table style='border-spacing: 3'>
 <tr>
    <th><?php echo _("Color") ?></th><th><?php echo _("Meaning") ?></th>
 </tr><tr style='background-color: #fff'>
    <td><?php echo _("None") ?></td><td><?php echo _("Exact match") ?></td>
 </tr><tr style='background-color: #ddf'>
    <td><?php echo _("Info") ?></td><td><?php echo _("Probable match, please verify") ?></tr><tr style='background-color: #fdd'>
    <td><?php echo _("Warning") ?></td><td><?php echo _("No matches, will be added as new") ?></td>
 </tr><tr style='background-color: #faa'>
    <td><?php echo _("Error") ?></td><td><?php echo _("No matches, please correct and reupload") ?></td>
 </tr>
</table><br>

<form name="importform" action="/php/import.php" method="post">

<?php
if($id_note == true) {
  print "<font color=blue>" . _("Note: This CSV file contains OpenFlights IDs in columns 15-18.  These IDs will override the values of any manual changes made to the airport, airline and/or plane columns.") . "</font><br>";
}
if($history == "yes") {
  print "<font color=blue>" . ("Note: You have selected historical airline mode.  All airline names have been preserved exactly as is.") . "</font><br>";
}

if($status == "disabled") {
  print "<font color=red>" . _("Error") . ": ";
  switch($fatal) {
  case "airport":
    print _("Your flight data includes unrecognized airports.  Please add them to the database and try again. ");
    print "<INPUT type='button' value='" . _("Add new airport") . "' onClick='javascript:window.open(\"/html/apsearch\", \"Airport\", \"width=500,height=580,scrollbars=yes\")'>";
    break;
  case "airline":
    print _("Your flight data includes unrecognized airlines.  This usually means that the airline code in the flight number was not found, and an airline name was not specified.  Please fix or remove the airline code and try again. ");
    break;
  case "date":
    print _("Some date fields could not be parsed.  Please change them to use any of these formats: YYYY-MM-DD, DD.MM.YYYY, MM/DD/YYYY, or YYYY only.  Note that DD/MM/YYYY is <b>not</b> accepted.");
    break;
  case "trip":
    print _("Your flight data includes trip IDs which are either undefined or do not belong to you.  Please check the trip IDs.");
    break;
  }
  print "</font><br><br>";
} else {
  print _("<b>Parsing completed successfully.</b> You are now ready to import these flights into your OpenFlights.  (Minor issues can be corrected afterwards in the flight editor.)") . "<br><br>";
}
print "<INPUT type='hidden' name='tmpfile' value='". basename($_FILES['userfile']['tmp_name']) . "'>";
print "<INPUT type='hidden' name='fileType' value='$fileType'>";
print "<INPUT type='hidden' name='historyMode' value='$history'>";
 print "<INPUT type='submit' name='action' title='" . _("Add these flights to your OpenFlights") . "' value='" . _("Import") . "' " . $status . ">";
?>

<INPUT type="button" value="<?php echo _("Upload again") ?>" title="<?php _("Cancel this import and return to file upload page") ?>" onClick="JavaScript:history.go(-1)">

<INPUT type="button" value="<?php echo _("Cancel") ?>" onClick="window.close()">

<?php
}
if($action == _("Import")) {
  print "<BR><H4>" . _("Flights successfully imported.") . "</H4><BR>";
  print "<INPUT type='button' value='" . _("Import more flights") . "' onClick='javascript:window.location=\"/html/import\"'>";
  print "<INPUT type='button' value='" . _("Close") . "' onClick='javascript:parent.opener.refresh(true); window.close();'>";
}

?>
      </form>
    </div>
  </body>
</html>
