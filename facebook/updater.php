#!/usr/bin/php -q
<?php
require_once 'php/facebook.php';
require_once 'keys.php';
require_once 'profile.php';

$facebook = new Facebook($appapikey, $appsecret);
$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);
$fbupdates = 0;
$fbfail = 0;

// Check which FB users have added flights
$sql = "SELECT fb.uid,fbuid,u.name,COUNT(*) AS count,SUM(distance) AS distance,fb.updated FROM flights AS f,facebook AS fb, users AS u WHERE f.uid=fb.uid AND u.uid=fb.uid AND f.upd_time > fb.updated GROUP BY f.uid";
$result = mysql_query($sql, $db);
while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  $count = $row["count"];
  if($count > 0) {
    $updated = $row["updated"];
    $uid = $row["uid"];
    $ofname = $row["name"];
    $sql = "SELECT s.city AS src, d.city AS dst FROM flights AS f,airports AS s,airports AS d WHERE f.uid=$uid AND f.src_apid=s.apid AND f.dst_apid=d.apid AND f.upd_time > '$updated' ORDER BY f.upd_time LIMIT 1";
    $detailresult = mysql_query($sql, $db);
    if($detail = mysql_fetch_array($detailresult, MYSQL_ASSOC)) {
      $feed_title = '<fb:name uid=\"$fbuid\" useyou=\"false\" /> has added a flight from <b>' . $detail["src"] . '</b> to <b>' . $detail["dst"] . '</b>';
      if($count > 1) {
	$feed_title .= " and <b>$count</b> more";
      }
      $feed_title .= ', covering ' . $row["distance"] . ' miles, to <fb:pronoun uid=\"$fbuid\" useyou=\"false\" possessive=\"true\"> <a href="http://openflights.org/user/' . $ofname . '">OpenFlights!</a>';
      try{
	// Publish feed story
	$feed_body = 'Take a look at <a href="http://openflights.org/user/' . $ofname . '">the updated map</a>.';
	//$facebook->api_client->feed_publishActionOfUser($feed_title, $feed_body);
	
	echo $feed_title . "\n";
	echo $feed_body . "\n";

	// Update the user's profile box
	$profile_box = get_profile($db, $uid);
	$facebook->api_client->profile_setFBML(null, $user_id, null, null, null, $profile_box);

	echo $profile_box;

	// Mark user as updated
	$sql = "UPDATE facebook SET updated=NOW() WHERE uid=$uid";
	$result = mysql_query($sql, $db);
	$fbupdates++;
      }catch(FacebookRestClientException $e){
	echo "Exception: " . $e;
	$fbfail++;
      }
    }
  }
}
echo "Updating complete: $fbupdates successful, $fbfail failed\n";

?>

