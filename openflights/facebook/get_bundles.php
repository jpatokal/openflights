#!/usr/bin/php
<?php
require_once 'php/facebook.php';
require_once 'keys.php';

// Defined in keys.php, see http://www.facebook.com/topic.php?uid=2205007948&topic=5350 to generate your own
$facebook = new Facebook($appapikey, $appsecret);
//$facebook->api_client->session_key = $infinitesessionkey;

echo "Requesting...\n";

try{
  $res = $facebook->api_client->admin_getAppProperties(array('application_name','callback_url','ip_list'));
  echo "* $res * \n";

  $res = $facebook->api_client->feed_getRegisteredTemplateBundles();
  echo "* $res * \n";

}catch(FacebookRestClientException $e){
  echo "Exception: " . $e;
}
