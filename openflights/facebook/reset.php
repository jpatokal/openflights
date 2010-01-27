<?php
// Facebook app for OpenFlights

require_once 'php/facebook.php';
require_once 'keys.php';

// appapikey,appsecret must be defined in keys.php
$facebook = new Facebook($appapikey, $appsecret);
$fbuid = $facebook->require_login();
?>

<fb:tabs>
	<fb:tab-item href="http://apps.facebook.com/openflights/index.php" title="Home"/>;
	<fb:tab-item href="http://apps.facebook.com/openflights/invite.php" title="Invite Friends"/>;
	<fb:tab-item href="http://apps.facebook.com/openflights/reset.php" title="Reset" selected="true"/>;
</fb:tabs>

<form action="http://apps.facebook.com/openflights/index.php" requirelogin="1">

<h2>Reset application?</h2>

<p>You can reset the OpenFlights Facebook application if you want to change to a different user's map.  Resetting will remove your application preferences and any session data stored in the database, returning you to the initial configuration page.  It will <i>not</i> remove the application from your Facebook profile; to do that, please go to "<a href="http://www.facebook.com/editapps.php">Edit applications</a>" instead.</p>

<p><input type='submit' name='reset' value='Reset' />
<input type='submit' name='cancel' value='Cancel' /></p>

</form>
