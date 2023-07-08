<?php

require_once "locale.php";
require_once "db_pdo.php";
require_once "tripit_common.php";

$uid = $_SESSION["uid"];
if (!$uid || empty($uid)) {
    print _("Not logged in, aborting");
    exit();
}

$oauth_credential = new OAuthConsumerCredential($tripit_app_id, $tripit_app_secret);

$tripit = new TripIt($oauth_credential, $tripit_api_url);

$tokens = null;
try {
    $tokens = $tripit->get_request_token();
} catch (Exception $e) {
    error_log("Could not get rendezvous tokens: " . $e);
    die(_("Could not connect to TripIt. Please try again later."));
}
if (!is_array($tokens)) {
    error_log("Could not get rendezvous tokens: not an array");
    die(_("Could not connect to TripIt. Please try again later."));
}
$_SESSION["tripit_rendezvous"] = array(
  "token" => $tokens["oauth_token"],
  "secret" => $tokens["oauth_token_secret"]
);

header(
    "Location: https://www.tripit.com/oauth/authorize?oauth_token=" . $tokens["oauth_token"] .
    "&oauth_token_secret=" . $tokens["oauth_token_secret"] .
    "&oauth_callback=" . urlencode("https://" . $_SERVER["SERVER_NAME"] . "/php/tripit_link.php")
);
