<?php
// Facebook app for OpenFlights

require_once 'php/facebook.php';
require_once 'keys.php';
require_once 'profile.php';

// appapikey,appsecret must be defined in keys.php
$facebook = new Facebook($appapikey, $appsecret);
$fbuid = $facebook->require_login();

// You need to set info or profile box in order for the button's below to show up.
// Don't set them every time.
$is_set = $facebook->api_client->data_getUserPreference(1);

if($_REQUEST["ofname"]){
  $ofname = $_REQUEST["ofname"];
} else {
  echo "<form requirelogin=\"1\">";
  echo "<h2>Configuration</h2>";
  echo "<p>Please enter your username on OpenFlights: <input type='text' name='ofname' value='$ofname' /></p>";
  echo "<input type='submit' value='Submit' />";

  echo "<p>If you don't have an account already, you can <a href='http://openflights.org/html/signup.html'>sign up</a> for one now.</p>";

  echo "</form>";
  return;
 }

$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);
$sql = "SELECT public, uid FROM users WHERE name='" . $ofname . "'";
$result = mysql_query($sql, $db);
if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  if($row["public"] == "N") {
    die("Sorry, $ofname's profile is set to 'Private'.  Please go to 'Settings', change it to 'Public' or 'Open', and try again.");
  }
} else {
  die("Sorry, couldn't find <b>$ofname</b> at OpenFlights.  Please hit 'Back' and try again.");
}
$uid = $row["uid"];

// Add this config into our table of Facebook users
$sql = sprintf("INSERT INTO facebook(uid,fbuid,updated) VALUES(%s,%s,NOW())", $uid, $fbuid);
$result = mysql_query($sql, $db);

echo 'Click below to add the OpenFlights box to your Facebook profile.';
echo '<div class="section_button"><fb:add-section-button section="profile"/></div>';

echo 'And click here if you want to post new flights to Facebook automatically.';
echo '<fb:prompt-permission perms="offline_access">Grant permission for automatic updates</fb:prompt-permission>'

// Update the user's profile box

$profile_box = get_profile($db, $uid, $fbuid, $ofname);
echo $profile_box;
$facebook->api_client->profile_setFBML(null, $fbuid, null, null, null, $profile_box);
