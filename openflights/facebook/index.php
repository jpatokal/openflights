<?php
// Copyright 2007 Facebook Corp.  All Rights Reserved. 
// 
// Application: OpenFlights
// File: 'index.php' 
//   This is a sample skeleton for your application. 
// 

require_once 'php/facebook.php';

$appapikey = '943d555bf52053c4736d68f6f33052fc';
$appsecret = '24cf8a342fa2c19a8bc4ded24a5fefb0';
$facebook = new Facebook($appapikey, $appsecret);
$user_id = $facebook->require_login();

// Greet the currently logged-in user!
echo "<p>Hello, <fb:name uid=\"$user_id\" useyou=\"false\" />!</p>";

// Print out at most 25 of the logged-in user's friends,
// using the friends.get API method
echo "<p>Friends:";
$friends = $facebook->api_client->friends_get();
$friends = array_slice($friends, 0, 25);
foreach ($friends as $friend) {
  echo "<br>$friend";
}
echo "</p>";
