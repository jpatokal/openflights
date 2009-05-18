<?php
// Facebook app for OpenFlights

require_once 'php/facebook.php';
require_once 'keys.php';
require_once 'profile.php';

// Print FB-style information box
function fb_infobox($info) {
  print "<br/><div style='background-color: #eceff6; border: 1px solid #d4dae8; color: #333333; padding: 10px; font-size: 13px; width: 500px;'>$info</div><br/>";
}

// Print FB-style error box and die
function fb_die($error) {
  die("<br/><div style='background-color: #ffebe8; border: 1px solid #dd3c10; color: #333333; padding: 10px; font-size: 13px; width: 500px'>$error</div>");
}

// appapikey,appsecret must be defined in keys.php
$facebook = new Facebook($appapikey, $appsecret);
$fbuid = $facebook->require_login();
$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);

// Clear prefs, DB if user passes in reset=true, so they can reconfig
if($_REQUEST["reset"] == "true") {
  $facebook->api_client->data_setUserPreference(1, 0);
  $sql = "DELETE FROM facebook WHERE fbuid=$fbuid";
  $result = mysql_query($sql, $db);
  fb_infobox("<b>Account reset.</b>");
}

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
	fb_die("Sorry, $ofname's profile is set to 'Private'.  Please go to <a href='http://openflights.org/html/settings.html' target='_blank'>Settings</a>, change <b>Privacy</b> to 'Public' or 'Open', save and try again.");
      }
    } else {
      fb_die("Sorry, couldn't find <b>$ofname</b> at OpenFlights.  Please check the spelling, hit 'Back' and try again.");
    }

    fb_infobox("<b>Account found!</b>  Generating profile preview...");

    // Looking good, save to Facebook and our internal table
    $facebook->api_client->data_setUserPreference(1, $ofname);

    $uid = $row["uid"];
    $sql = sprintf("INSERT INTO facebook(uid,fbuid,updated) VALUES(%s,%s,DATE_SUB(NOW(), INTERVAL 1 DAY))", $uid, $fbuid);
    $result = mysql_query($sql, $db);
    if(! $result || mysql_affected_rows() != 1) {
      fb_die('<b>Uh-oh, an error occurred</b>.  Please send the following message to <i>support@openflights.org</i>:<br/>' . $sql);
    }
    
  } else {
    // No, ask for it
    echo "<form requirelogin=\"1\">";
    echo "<h2>Configuration</h2>";
    echo "<p>Thanks for trying out the OpenFlights Facebook application!  Hooking it up to your OpenFlights account is an easy three-step process.</p>";
    echo "<p>To start, please enter your username on OpenFlights: <input type='text' name='ofname' value='$ofname' /></p>";
    echo "<input type='submit' value='Submit' />";

    echo "<p>This application requires an <a target='_blank' href='http://openflights.org'>OpenFlights</a> account.</b>  If you don't have one already, you can <a target='_blank' href='http://openflights.org/html/signup.html'>sign up</a> for one now.</p>";

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

// Fetch their session key and other info from database
$sql = "SELECT uid, sessionkey, pref_onnew, pref_onfly FROM facebook WHERE fbuid=" . $fbuid;
$result = mysql_query($sql, $db);
if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  $uid = $row["uid"];
  $session = $row["sessionkey"];
  $onnew = $row["pref_onnew"];
  $onfly = $row["pref_onfly"];
} else {
  fb_die('<b>Uh-oh, an error occurred.</b>  Please send the following message to <i>support@openflights.org</i>:<br/>' . $sql);
}

$session_key = $_POST["fb_sig_session_key"];
$session_expiry = $_POST["fb_sig_expires"];
// print "Live session key [" . $session_key . "], expiry [" . $session_expiry . "], DB session [" . $session . "]<br>";
// Do we now have a new infinite key?
if(! $session && $session_expiry == "0") {
  $sql = "UPDATE facebook SET sessionkey='" . $session_key . "' WHERE fbuid=" . $fbuid;
  if($result = mysql_query($sql, $db)) {
    $session = $session_key;
    fb_infobox("<b>Thank you!</b> OpenFlights will now send notifications to your Facebook Wall and refresh your profile automatically when you add new flights.");
  } else {
    fb_die('<b>Uh-oh, an error occurred</b>.  Please send the following message to <i>support@openflights.org</i>:<br/>' . $sql);
  }
}

// Has user submitted preferences?
if($_REQUEST["prefupdate"] == "Y") {
  $reqfly = $_REQUEST["onfly"];
  $reqnew = $_REQUEST["onnew"];
  if($reqfly != "Y") $reqfly = "N";
  if($reqnew != "Y") $reqnew = "N";
  if($reqfly != $onfly || $reqnew != $onnew) {
    // User has changed their preferences
    $sql = "UPDATE facebook SET pref_onfly='" . $reqfly . "', pref_onnew='" . $reqnew . "' WHERE fbuid=" . $fbuid;
    if($result = mysql_query($sql, $db)) {
      fb_infobox("Wall preferences successfully updated.");
      $onfly = $reqfly;
      $onnew = $reqnew;
    } else {
      fb_die('<b>Uh-oh, an error occurred.</b>  Please send the following message to <i>support@openflights.org</i>:<br/>' . $sql);
    }
  }
}

// Update the user's profile box
$profile_box = get_profile($db, $uid, $fbuid, $ofname);
echo "<br/><div style='background-color: #f7f7f7;border: 1px solid #cccccc;color: #333333;padding: 10px; width: 184px;'>$profile_box</div><br/>";
$facebook->api_client->profile_setFBML(null, $fbuid, null, null, null, $profile_box);

// Wall preferences and session generation
print "<form requirelogin='1'>";
if(! $session) {
?>

  <p><b>Step 1</b>: Click the link below to allow OpenFlights to send notifications to your Facebook Wall and refresh your stats automatically when you add new flights.  This is <i>optional but recommended</i>; otherwise, you will have to manually refresh your stats.</p> 
  <fb:prompt-permission perms="offline_access"> Grant permission for offline updates </fb:prompt-permission>

  <p><b>Step 2</b>: <i>After</i> granting permission, set your preferences and click Activate below to activate automatic updating.</p>
<?php
}

print "<h3>Wall preferences</h3>";
print "<p>Post updates to my Wall:<br/>";
print "<input type='checkbox' name='onnew' value='Y' " . ($onnew == "Y" ? "CHECKED" : "") . "> when I add a new flight to OpenFlights<br/>";
print "<input type='checkbox' name='onfly' value='Y' " . ($onfly == "Y" ? "CHECKED" : "") . "> on the day I fly<br/></p>";
print "<input type='hidden' name='prefupdate' value='Y'>";
if(! $session) {
  print "<input type='submit' value='Activate' /><br/>";
} else {
  print "<input type='submit' value='Update preferences' /><br/>";
  print "<p><i>Automatic refreshing active: any new flights will be updated to your profile and Wall hourly.</i></p>";
}
?>
</form>

<fb:if-section-not-added section="profile">
<p><b>Step 3</b>: Click the button below to add the OpenFlights box to your Facebook profile.</p>
<div class="section_button"><fb:add-section-button section="profile"/></div>
<br/>
</fb:if-section-not-added>

