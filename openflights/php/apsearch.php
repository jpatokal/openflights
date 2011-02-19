<?php
require_once("../php/locale.php");
require_once("../php/db.php");

header("Content-type: text/html");

include 'helper.php';

$airport = $_POST["name"];
$iata = $_POST["iata"];
$icao = $_POST["icao"];
$city = $_POST["city"];
$country = $_POST["country"];
$code = $_POST["code"];
$myX = $_POST["x"];
$myY = $_POST["y"];
$elevation = $_POST["elevation"];
$tz = $_POST["timezone"];
$dst = $_POST["dst"];
$dbname = $_POST["db"];
$iatafilter = $_POST["iatafilter"];
$offset = $_POST["offset"];
$action = $_POST["action"];
$apid = $_POST["apid"];

$uid = $_SESSION["uid"];

if($action == "RECORD") {
  if(!$uid or empty($uid)) {
    printf("0;" . _("Your session has timed out, please log in again."));
    exit;
  }

  // Check for potential duplicates (unless admin)
  $duplicates = array();
  if($uid != $OF_ADMIN_UID) {
    $filters = array();
    if($apid && $apid != "") {
      $filters[] = "apid=$apid";
    } 
    if($iata != "") {
      $filters[] = " iata='$iata'";
    }
    if($icao != "") {
      $filters[] = " icao='$icao'";
    }

    $sql = "SELECT * FROM airports WHERE " . implode(" OR ", $filters);
    $result = mysql_query($sql, $db);
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
      if($row['uid'] != $uid || $row['apid'] != $apid) {
        $duplicates[] = print_r($row, true);
      }
    }
  }

  if(! $apid || $apid == "") {    
    $sql = sprintf("INSERT INTO airports(name,city,country,iata,icao,x,y,elevation,timezone,dst,uid) VALUES('%s', '%s', '%s', '%s', %s, %s, %s, %s, %s, '%s', %s)",
		   mysql_real_escape_string($airport), 
		   mysql_real_escape_string($city),
		   mysql_real_escape_string($country),
		   mysql_real_escape_string($iata),
		   $icao == "" ? "NULL" : "'" . mysql_real_escape_string($icao) . "'",
		   mysql_real_escape_string($myX),
		   mysql_real_escape_string($myY),
		   mysql_real_escape_string($elevation),
		   mysql_real_escape_string($tz),
		   mysql_real_escape_string($dst),
		   $uid);
  } else {
    // Editing an existing airport
    $sql = sprintf("UPDATE airports SET name='%s', city='%s', country='%s', iata='%s', icao=%s, x=%s, y=%s, elevation=%s, timezone=%s, dst='%s' WHERE apid=%s",
		   mysql_real_escape_string($airport), 
		   mysql_real_escape_string($city),
		   mysql_real_escape_string($country),
		   mysql_real_escape_string($iata),
		   $icao == "" ? "NULL" : "'" . mysql_real_escape_string($icao) . "'",
		   mysql_real_escape_string($myX),
		   mysql_real_escape_string($myY),
		   mysql_real_escape_string($elevation),
		   mysql_real_escape_string($tz),
		   mysql_real_escape_string($dst),
		   mysql_real_escape_string($apid));
  }
  if(empty($duplicates)) {
    mysql_query($sql, $db) or die('0;' . _("Adding new airport failed:") . ' ' . $sql);
    if(! $apid || $apid == "") {
      printf('1;' . mysql_insert_id() . ";" . _("New airport successfully added."));
    } else {
      if(mysql_affected_rows() == 1) {
        printf('1;' . $apid . ';' . _("Airport successfully edited."));
      } else {
        printf('0;' . _("Editing airport failed:") . ' ' . $sql);
      }
    }
  } else {
    $subject = "OpenFlights: Airport edit";
    $body = "New edit submitted by " . $_SESSION['name'] . ":\n$sql\n\nExisting airport information:\n" . implode("\n", $duplicates);
    $headers = "From: info@openflights.org\r\nTo: info@openflights.org\r\nReply-To: " . $_SESSION['email'];
    if(isSet($_POST["unittest"])) {
      echo $headers . "\n\n" . $body;
      exit;
    }
    if (mail($email, $subject, $body, $headers)) {
      printf('1;' . $apid . ";" . _("Edit submitted for review.  If you have registered an e-mail address, you will be notified when the edit is reviewed."));
    } else {
      printf('0;' . _("Could not submit edit for review, please contact <a href='/about'>support</a>."));
    }
  }
  exit;
}

if(! $dbname) {
  $dbname = "airports";
}
$sql = "SELECT * FROM " . mysql_real_escape_string($dbname) . " WHERE ";

if($action == "LOAD") {
  // Single-airport fetch
  $sql .= " apid=" . mysql_real_escape_string($apid);
  $offset = 0;

 } else {
  // Real search, build filter
  if($airport) {
    $sql .= " name LIKE '%" . mysql_real_escape_string($airport) . "%' AND";
  }
  if($iata) {
    $sql .= " iata='" . mysql_real_escape_string($iata) . "' AND";
  }
  if($icao) {
    $sql .= " icao='" . mysql_real_escape_string($icao) . "' AND";
  }
  if($city) {
    $sql .= " city LIKE '" . mysql_real_escape_string($city) . "%' AND";
  }
  if($country != "ALL") {
    if($dbname == "airports_dafif" || $dbname == "airports_oa") {
      if($code) {
	$sql .= " code='" . mysql_real_escape_string($code) . "' AND";
      }
    } else {
      if($country) {
	$sql .= " country='" . mysql_real_escape_string($country) . "' AND";
      }
    }
  }
  
  // Disable this filter for DAFIF (no IATA data)
  if($iatafilter == "false" || $dbname == "airports_dafif") {
    $sql .= " 1=1"; // dummy
  } else {
    $sql .= " iata != '' AND iata != 'N/A'";
  }
}
if(! $offset) {
  $offset = 0;
}

$sql2 = str_replace("*", "COUNT(*)", $sql);
$result2 = mysql_query($sql2, $db) or die ('0;Operation ' . $param . ' failed: ' . $sql2);
if($row = mysql_fetch_array($result2, MYSQL_NUM)) {
  $max = $row[0];
}
printf("%s;%s", $offset, $max);

$sql .= " ORDER BY name LIMIT 10 OFFSET " . $offset;
$result = mysql_query($sql, $db) or die ('0;Operation ' . $param . ' failed: ' . $sql);
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  if($dbname == "airports_dafif" || $dbname == "airports_oa") {
    $row["country"] = $row["code"];
  }
 
  if($row["uid"] || $uid == $OF_ADMIN_UID ) {
    if($row["uid"] == $uid || $uid == $OF_ADMIN_UID) {
      $row["ap_uid"] = "own"; // editable
    } else {
      $row["ap_uid"] = "user"; // added by another user
    }
  } else {
    $row["ap_uid"] = null; // in DB
  }
  $row["ap_name"] = format_airport($row);
  unset($row["uid"]);
  print "\n" . json_encode($row);
}

?>
