#!/usr/bin/php
<?php
require_once 'php/facebook.php';
require_once 'keys.php';

// Defined in keys.php, see http://www.facebook.com/topic.php?uid=2205007948&topic=5350 to generate your own
$facebook = new Facebook($appapikey, $appsecret);
$facebook->api_client->session_key = $infinitesessionkey;

$one_line_story_templates = array();
$one_line_story_templates[] = '{*actor*} added <b>{*count*}</b> new flights, including a flight from <b>{*src*}</b> to <b>{*dst*}</b>, to <fb:pronoun uid=\"actor\" useyou=\"false\" possessive=\"true\"/> <a href="http://openflights.org/user/{*ofname*}">OpenFlights</a>!';
$one_line_story_templates[] = '{*actor*} added a flight from <b>{*src*}</b> to <b>{*dst*}</b> to <fb:pronoun uid=\"actor\" useyou=\"false\" possessive=\"true\"/> <a href="http://openflights.org/user/{*ofname*}">OpenFlights</a>!';

$short_story_templates = array();
$short_story_templates[] = array('template_title' => '{*actor*} added <b>{*count*}</b> new flights to <fb:pronoun uid=\"actor\" useyou=\"false\" possessive=\"true\"/> <a href="http://openflights.org/user/{*ofname*}">OpenFlights</a>!',
				 'template_body' => 'Including the flight from <b>{*src*}</b> to <b>{*dst*}</b>, the flights cover a distance of <b>{*distance*}</b> miles.');

$full_story_template = array('template_title' => '{*actor*} added <b>{*count*}</b> new flights to <fb:pronoun uid=\"actor\" useyou=\"false\" possessive=\"true\"/> <a href="http://openflights.org/user/{*ofname*}">OpenFlights</a>!',
				 'template_body' => 'Including the flight from <b>{*src*}</b> to <b>{*dst*}</b>, the flights cover a distance of <b>{*distance*}</b> miles.');

$action_links = array();
$action_links[] = array('text' => 'See OpenFlights map',
			'href' => 'http://openflights.org/user/{*ofname*}');

try{
  echo "Requesting...\n";
  $result = $facebook->api_client->feed_registerTemplateBundle($one_line_story_templates,
							       $short_story_templates,
							       $full_story_template,
							       $action_links);
  echo "Bundle ID: " . $result;

}catch(FacebookRestClientException $e){
  echo "Exception: " . $e;
}
