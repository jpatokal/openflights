<?php
require_once("locale.php");
require_once("db_pdo.php");
require_once("tripit_common.php");

$uid = $_SESSION["uid"];
if (!$uid or empty($uid)) {
  print _("Session expired.  Please try re-linking after logging back in.");
  exit();
}

if (!isset($_SESSION["tripit_rendezvous"])) {
  print _("Invalid token, giving up.");
  error_log("$uid attempted to rendezvous, but no rendezvous token was found in the session.");
  exit();
}

$rendezvous = $_SESSION["tripit_rendezvous"];

if (!$_GET["oauth_token"] or empty($_GET["oauth_token"]) or $_GET["oauth_token"] !== $rendezvous["token"]) {
  print _("Invalid token, giving up.");
  error_log("$uid attempted to rendezvous, but no token was passed in.");
  exit();
}

// We have the token and secret; attempt to get a request token.
$oauth_credential = new OAuthConsumerCredential($tripit_app_id, $tripit_app_secret, $rendezvous["token"], $rendezvous["secret"]);
$tripit = new TripIt($oauth_credential, $tripit_api_url);
try {
  $access_token = $tripit->get_access_token();
} catch (Exception $e) {
  error_log("Could not get access token: " . $e);
  die(_("Could not connect to TripIt.  Please try again later."));
}

if ($access_token == null || !is_array($access_token)) {
  print _("Invalid token, giving up.");
  error_log("$uid attempted to rendezvous, but TripIt said the token was not authorized: " . $access_token);
  exit();
}

// Make sure it's not the same token as what we already have in the db.
$existing_tripit_tokens = get_request_tokens($dbh, $uid);
if ($existing_tripit_tokens == null or $existing_tripit_tokens["token"] !== $access_token["oauth_token"]) {
  // No tokens or different token; add a new one.

  // Disable any existing TripIt links for this user.
  try {
    $sth = $dbh->prepare("update tripit_tokens set active='N' where uid=?");
    $sth->execute(array($uid));
  } catch (PDOException $e) {
    die(_("Failed to disable old TripIt links."));
  }

  // Add the new link.
  try {
    $sth = $dbh->prepare("insert into tripit_tokens (uid, auth_token, auth_token_secret, active) values(?, ?, ?, 'Y')");
    $sth->execute(array($uid, $access_token["oauth_token"], $access_token["oauth_token_secret"]));
  } catch (PDOException $e) {
    die(_("Failed to link new TripIt account."));
  }
}

// All good, redirect back to listing
header("Location: /php/tripit_list_trips.php");
