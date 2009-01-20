#!/usr/bin/php
<?php
require_once 'php/facebook.php';
require_once 'keys.php';

// Defined in keys.php, see http://www.facebook.com/topic.php?uid=2205007948&topic=5350 to generate your own
$facebook = new Facebook($appapikey, $appsecret);
$facebook->api_client->session_key = $infinitesessionkey;

$one_line_story_templates = array();
$one_line_story_templates[] = '{*actor*} added a flight from <b>{*src*}</b> to <b>{*dst*}</b> and {*count*} more to <fb:pronoun uid=\"$fbuid\" useyou=\"false\" possessive=\"true\"> <a href="http://openflights.org/user/{*ofname*}">OpenFlights</a>!';
$one_line_story_templates[] = '{*actor*} added a flight from <b>{*src*}</b> to <b>{*dst*}</b> to <fb:pronoun uid=\"$fbuid\" useyou=\"false\" possessive=\"true\"> <a href="http://openflights.org/user/{*ofname*}">OpenFlights</a>!';

$short_story_templates = array();
$short_story_templates[] = array('template_title' => '<fb:name uid=\"$fbuid\" useyou=\"false\" /> added flights to <fb:pronoun uid=\"$fbuid\" useyou=\"false\" possessive=\"true\"> <a href="http://openflights.org/user/{*ofname*}">OpenFlights</a>!',
				 'template_body' => 'From <b>{*src*}</b> to <b>{*dst*}</b>');

$full_story_template = array('template_title' => '<fb:name uid=\"$fbuid\" useyou=\"false\" /> added flights to <fb:pronoun uid=\"$fbuid\" useyou=\"false\" possessive=\"true\"> <a href="http://openflights.org/user/{*ofname*}">OpenFlights</a>!',
				 'template_body' => 'From <b>{*src*}</b> to <b>{*dst*}</b>');

$action_links = array();
$action_links[] = array('text' => 'See OpenFlights map',
			'href' => 'http://openflights.org/user/{*ofname*}');

try{
  echo "Requesting...";
  $result = $facebook->api_client->feed_registerTemplateBundle($one_line_story_templates,
							       $short_story_templates,
							       $full_story_template,
							       $action_links);
  echo $result;

}catch(FacebookRestClientException $e){
  echo "Exception: " . $e;
}
