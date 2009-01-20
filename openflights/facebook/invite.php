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
	<fb:tab-item href="http://apps.facebook.com/openflights/invite.php" title="Invite Friends" selected="true"/>;
</fb:tabs>

<?php
// Retrieve array of friends who've already added the app.
$fql = 'SELECT uid FROM user WHERE uid IN (SELECT uid2 FROM friend WHERE uid1='.$fbuid.') AND has_added_app = 1';
$_friends = $facebook->api_client->fql_query($fql);

// Extract the user ID's returned in the FQL request into a new array.
$friends = array();
if (is_array($_friends) && count($_friends)) {
	foreach ($_friends as $friend) {
		$friends[] = $friend['uid'];
	}
}

// Convert the array of friends into a comma-delimeted string.
$friends = implode(',', $friends);

// Prepare the invitation text that all invited users will receive.
$content = <<<FBML
<fb:name uid="{$fbuid}" firstnameonly="true" shownetwork="false"/> wants to see your OpenFlights map!
<fb:req-choice url="{$facebook->get_add_url()}" label="Add OpenFlights to your profile!"/>
FBML;
?>
<fb:request-form action="http://apps.facebook.com/openflights/" method="POST" invite="true" type="OpenFlights" content="<?php echo htmlentities($content);?>">
	<fb:multi-friend-selector max="20" actiontext="Here are your friends who haven't added OpenFlights to their profile. Invite them to share their OpenFlights today!" showborder="true" rows="5" exclude_ids="<?php echo $friends;?>"></fb:request-form>
