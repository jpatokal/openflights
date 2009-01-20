<?php
// Facebook app for OpenFlights

require_once 'php/facebook.php';
require_once 'keys.php';
require_once 'profile.php';

// appapikey,appsecret must be defined in keys.php
$facebook = new Facebook($appapikey, $appsecret);
$fbuid = $facebook->require_login();
$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);

// Has the user configured their OpenFlights name?
$ofname = $facebook->api_client->data_getUserPreference(1);
if(! $ofname || $ofname == "") {
  // Nope, did they just submit it?
  $ofname = $_REQUEST["ofname"];
  if($ofname) {
    // Yes, check it
    $sql = "SELECT public, uid FROM users WHERE name='" . $ofname . "'";
    $result = mysql_query($sql, $db);
    if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
      if($row["public"] == "N") {
	die("Sorry, $ofname's profile is set to 'Private'.  Please go to 'Settings', change it to 'Public' or 'Open', and try again.");
      }
    } else {
      die("Sorry, couldn't find <b>$ofname</b> at OpenFlights.  Please check the spelling, hit 'Back' and try again.");
    }

    echo("<p><b>Thank you!</b>  Setting up your profile...</p>");

    // Looking good, save to Facebook and our internal table
    $facebook->api_client->data_setUserPreference(1, $ofname);

    $uid = $row["uid"];
    $sql = sprintf("INSERT INTO facebook(uid,fbuid,updated) VALUES(%s,%s,NOW())", $uid, $fbuid);
    $result = mysql_query($sql, $db);

    echo("<p>Done!</p>");
    
  } else {
    // No, ask for it
    echo "<form requirelogin=\"1\">";
    echo "<h2>Configuration</h2>";
    echo "<p>Please enter your username on OpenFlights: <input type='text' name='ofname' value='$ofname' /></p>";
    echo "<input type='submit' value='Submit' />";

    echo "<p>If you don't have an account already, you can <a href='http://openflights.org/html/signup.html'>sign up</a> for one now.</p>";

    echo "</form>";
    return;
  }
}

?>

<fb:tabs>
	<fb:tab-item href="http://apps.facebook.com/openflights/index.php" title="Home" selected="true"/>;
	<fb:tab-item href="http://apps.facebook.com/openflights/invite.php" title="Invite Friends"/>;
</fb:tabs>

<?php
// Update the user's profile box
if(! $uid) {
  $sql = "SELECT public, uid FROM users WHERE name='" . $ofname . "'";
  $result = mysql_query($sql, $db);
  if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
    $uid = $row["uid"];
  }
}
$profile_box = get_profile($db, $uid, $fbuid, $ofname);
echo $profile_box;
$facebook->api_client->profile_setFBML(null, $fbuid, null, null, null, $profile_box);
?>
<form requirelogin="1">
  <input type='submit' value='Refresh' /><br/>
</form>

<p>Click the button below to add the OpenFlights box to your Facebook profile.</p>
<div class="section_button"><fb:add-section-button section="profile"/></div>
<p>(If there is no button, it was added already.)</p>
