<?php
require_once 'php/facebook.php';
require_once 'keys.php';
require_once 'profile.php';
require_once 'db.php';

$facebook = new Facebook($appapikey, $appsecret);
$fbtoday = 0;
$fbupdates = 0;
$fbfail = 0;

$CRONHOUR = 1; // Hour of day (0-23) when to check for today's flights

// Check which FB users have valid infinite session keys and new flights since last update or flying today
// Note: Assumes that script is run hourly
$sql = "SELECT fb.uid,fb.sessionkey,fb.fbuid,u.name,COUNT(*) AS count,SUM(distance) AS distance,fb.updated,IF(HOUR(NOW()) = $CRONHOUR AND f.src_date = DATE(NOW()) AND fb.pref_onfly = 'Y','Y','N') AS today FROM flights AS f,facebook AS fb, users AS u WHERE fb.sessionkey IS NOT NULL AND f.uid=fb.uid AND u.uid=fb.uid AND ((fb.pref_onnew = 'Y' AND f.upd_time > fb.updated) OR (fb.pref_onfly = 'Y' AND f.src_date = DATE(NOW()) AND HOUR(NOW()) = $CRONHOUR)) GROUP BY f.uid";
$result = mysql_query($sql, $db);
while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  $count = $row["count"];

  // This guy has
  if($count > 0) {
    $updated = $row["updated"];
    $uid = $row["uid"];
    $fbuid = $row["fbuid"];
    $ofname = $row["name"];
    $today = $row["today"];
    $infinitesessionkey = $row["sessionkey"];

    if($today == "Y") {
      // Get details of all of today's flights
      $sql = "SELECT s.city AS src, d.city AS dst, opp FROM flights AS f,airports AS s,airports AS d WHERE f.uid=$uid AND f.src_apid=s.apid AND f.dst_apid=d.apid AND f.src_date = DATE(NOW()) ORDER BY f.upd_time"; // no limit!
    } else {
      // Get details of last flight entered
      $sql = "SELECT s.city AS src, d.city AS dst, opp FROM flights AS f,airports AS s,airports AS d WHERE f.uid=$uid AND f.src_apid=s.apid AND f.dst_apid=d.apid AND f.upd_time > '$updated' ORDER BY f.upd_time LIMIT 1";
    }
    $detailresult = mysql_query($sql, $db);
    if(mysql_num_rows($detailresult) == 0) {
      print "Error: No matching flights: $sql\n";
      continue;
    }
    while($detail = mysql_fetch_array($detailresult, MYSQL_ASSOC)) {
      if($detail["opp"] == "Y") {
	$src = $detail["dst"];
	$dst = $detail["src"];
      } else {
	$src = $detail["src"];
	$dst = $detail["dst"];
      }
      $distance = $row["distance"];
      try{
	// Use this user's session key
	$facebook->api_client->session_key = $infinitesessionkey;

	// Publish feed story
        if($today == 'Y') {
          $message = "is flying from $src to $dst today!";
        } else {
          $message = "added $count new flights covering $distance miles, including a flight from $src to $dst, to their OpenFlights!";
        }
	$attachment = array( 'name' => 'OpenFlights',
			   'caption' => "Flight logging, mapping, stats and sharing",
			   'href' => 'http://openflights/user/' . $ofname );
	$attachment = json_encode($attachment);
        $action_links = array(array('text' => 'View map',
	                            'href' => 'http://openflights/user/' . $ofname ));
	$action_links = json_encode($action_links);
	$facebook->api_client->stream_publish($message, $attachment, $action_links);

	// Update the user's profile box
	$profile_box = get_profile($db, $uid, $fbuid, $ofname);
	$facebook->api_client->profile_setFBML(null, $fbuid, null, null, null, $profile_box);
        $facebook->api_client->fbml_refreshImgSrc("http://openflights.org/facebook/map.php?uid=$uid");

	// Mark user as updated
	$sql = "UPDATE facebook SET updated=NOW() WHERE uid=$uid";
	mysql_query($sql, $db);
	if($today == "Y") {
	  $fbtoday++;
	} else {
	  $fbupdates++;
	}
      }catch(Exception $e){
	switch($e->getCode()) {
	case FacebookAPIErrorCodes::API_EC_EDIT_FEED_TOO_MANY_USER_ACTION_CALLS:
	  // Too many updates, try again later
          echo "Exception: Feed limit exceeded for user $uid (FB $fbuid), will keep trying\n";
	  break;
	  
	case FacebookAPIErrorCodes::API_EC_PERMISSION:
          echo "Exception: User $uid (FB $fbuid) has not granted permission to stream publish\n";
          break;

	case FacebookAPIErrorCodes::API_EC_PARAM_SESSION_KEY:
          // Clear out if session key expired or access is denied
	  $sql = "UPDATE facebook SET sessionkey=NULL WHERE uid=$uid";
	  mysql_query($sql, $db);
          echo "Exception " . $e->getCode() . ": Session ID cleared for user $uid (FB $fbuid)\n";
	  break;

	default:
	  echo "Exception " . $e->getCode() . " for user $uid (FB $fbuid) and session $infinitesessionkey: $e\n";
	  break;
        }
	$fbfail++;
      }
    }
  }
}
if($fbtoday > 0) {
  echo date(DATE_RFC822) . ": $fbtoday flights today";
}
if($fbupdates > 0 || $fbfail > 0) {
  echo date(DATE_RFC822) . ": $fbupdates new flights, $fbfail failed";
}
?>
