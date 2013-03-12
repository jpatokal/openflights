<?php
include 'locale.php';
include 'db.php';
include 'helper.php';
include 'filter.php';

// This applies only when viewing another's flights
$user = $_POST["user"];
if(! $user) {
  $user = $_GET["user"];
}
$trid = $_POST["trid"];
if(! $trid) {
  $trid = $_GET["trid"];
}

// Login via vBulletin cookies
$bb_uid = $_COOKIE["bb_userid"];
if($OF_VBULLETIN_LOGIN && ! empty($bb_uid)) {
  $sql = "SELECT uid,name,email,editor,elite,units,locale FROM users WHERE bb_uid=" . $bb_uid;
  $result = mysql_query($sql, $db);
  if ($myrow = mysql_fetch_array($result, MYSQL_ASSOC)) {
    $uid = $_SESSION['uid'] = $myrow["uid"];
    $_SESSION['name'] = $myrow["name"];
    $_SESSION['email'] = $myrow["email"];
    $_SESSION['editor'] = $myrow["editor"];
    $_SESSION['elite'] = $myrow["elite"];
    $_SESSION['units'] = $myrow["units"];
  } else {
    if(! $trid && ! $user) {
      die("Signup;No username found for ID " . $bb_uid);
    }
  }
}

$uid = $_SESSION["uid"];
$challenge = $_SESSION["challenge"];
if(!$uid or empty($uid)) {
  // If not logged in, default to demo mode and warn app that we're (no longer?) logged in
  $uid = 1;
  $logged_in = "demo";
  if(!$challenge or empty($challenge)) {
    $challenge = md5(rand(1,100000));
    $_SESSION["challenge"] = $challenge;
  }
} else {
  $logged_in = $_SESSION["name"]; // username
  $elite = $_SESSION["elite"];
  $editor = $_SESSION["editor"];
}

$init = $_POST["param"];
if(! $init) {
  $init = $_GET["init"];
}
$guestpw = $_POST["guestpw"];

// Verify that this trip and user are public
$public = "O"; // default to full access

if($trid && $trid != "0" && $trid != "null") {
  // Verify that we're allowed to access this trip
  // NB: a "trid" filter can mean logged-in *and* filtered, or not logged in!
  $sql = "SELECT * FROM trips WHERE trid=" . mysql_real_escape_string($trid);
  $result = mysql_query($sql, $db);
  if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
    if($row["uid"] != $uid and $row["public"] == "N") {
      die('Error;' . _("This trip is not public."));
    } else {
      // Check if we're viewing out own trip
      if($uid != $row["uid"]) {
	// Nope, we are *not* this user
	$uid = $row["uid"];
	$public = $row["public"];
	$logged_in = "demo";
	if($public == "O") {
	  $_SESSION["openuid"] = $uid;
	  $_SESSION["opentrid"] = $trid;
	}
	// Increment view counter
	mysql_query("UPDATE users SET count=count+1 WHERE uid=$uid", $db);
      }
    }
  } else {
    die('Error;' . _("No such trip."));
  }
}

if($user && $user != "0") {
  // Verify that we're allowed to view this user's flights
  // if $user is set, we are never logged in
  $sql = "SELECT uid,public,elite,guestpw,IF('" . $guestpw . "'= guestpw,'Y','N') AS pwmatch FROM users WHERE name='" . mysql_real_escape_string($user) . "'";
  $result = mysql_query($sql, $db);
  if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
    if($row["public"] == "N" && $row["pwmatch"] == "N") {
      if($row["guestpw"]) {
	die("Error;" . _("This user's flights are password-protected.") . "<br><br>" .
	    _("Password") . ": <input type='password' id='guestpw' size='10'>" .
	    "<input type='button' value='Submit' align='middle' onclick='JavaScript:refresh(true)'>");
      } else {
	die('Error;' . _("This user's flights are not public."));
      }
    } else {
      $uid = $row["uid"];
      $public = $row["public"];
      $elite = $row["elite"];
      $logged_in = "demo"; // we are *not* this user
      if($public == "O") {
	$_SESSION["openuid"] = $uid;
	$_SESSION["opentrid"] = null;
      }
      // Increment view counter
      mysql_query("UPDATE users SET count=count+1 WHERE uid=$uid", $db);
    }
  } else {
    die('Error;' . _("No such user."));
  }
}


// Load up all information needed by this user
$filter = getFilterString($_POST);
$map = "";

// Statistics
// Number of flights, total distance (mi), total duration (minutes), public/open
$sql = "SELECT COUNT(*) AS count, SUM(distance) AS distance, SUM(TIME_TO_SEC(duration))/60 AS duration FROM flights AS f WHERE uid=" . $uid . " " . $filter;
$result = mysql_query($sql, $db) or die ('Error;Database error ' . $sql . ', ' . mysql_error());
if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  if($row["count"] == "0" && $user && $user != "0") {
    die('Error;' . _("This user has no flights."));
  }
  $distance = $row["distance"];
  if(!$distance) $distance = "0";
  if($_SESSION["units"] == "K") {
    $distance = round($distance * $KMPERMILE) . " " . _("km");
  } else {
    $distance = $distance . " " . _("miles");
  }
  $map .= sprintf("%s;%s;%s;%s;%s;%s;%s;%s\n", $row["count"], $distance, $row["duration"], $public, $elite,
	 $logged_in, $editor, $challenge);
 }

// List of all flights (unique by airport pair)
$sql = "SELECT DISTINCT s.apid,s.x,s.y,d.apid,d.x,d.y,count(fid),distance AS times,IF(MIN(src_date)>NOW(), 'Y', 'N') AS future,f.mode FROM flights AS f, airports AS s, airports AS d WHERE f.src_apid=s.apid AND f.dst_apid=d.apid AND f.uid=" . $uid . " " . $filter . " GROUP BY s.apid,d.apid";
$result = mysql_query($sql, $db) or die ('Error;Database error ' . $sql . ', ' . mysql_error());
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_NUM)) {
  if($first) {
    $first = false;
  } else {
    $map .= "\t";
  }  
  $map .= sprintf ("%s;%s;%s;%s;%s;%s;%s;%s;%s;%s", $row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8], $row[9]);
}
$map .= "\n";

// List of all airports
$sql = "SELECT DISTINCT a.apid,x,y,name,iata,icao,city,country,timezone,dst,count(name) AS visits,IF(MIN(src_date)>NOW(), 'Y', 'N') AS future FROM flights AS f, airports AS a WHERE (f.src_apid=a.apid OR f.dst_apid=a.apid) AND f.uid=" . $uid . $filter . " GROUP BY CONCAT(name,city) ORDER BY visits ASC";
$result = mysql_query($sql, $db) or die ('Error;Database error ' . $sql . ', ' . mysql_error());

$first = true;
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  if($first) {
    $first = false;
  } else {
    $map .= "\t";
  }
  $map .= sprintf ("%s;%s;%s;%s;%s;%s;%s", format_apdata($row), $row["name"], $row["city"], $row["country"], $row["visits"], format_airport($row), $row["future"]);
}

print $map . "\n";

// When running for the first time, load up possible filter settings for this user
if($init == "true") {
  loadFilter($db, $uid, $trid, $logged_in);
}
?>
