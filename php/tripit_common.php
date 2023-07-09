<?php

require_once "tripit_api.php";
require_once "db_pdo.php";
require_once "secrets.php";

$tripit_api_url = "https://api.tripit.com";

/**
 * Validates that a user has a valid linked TripIt account.  If not, redirect the user to
 * rendezvous start.
 * @param $dbh PDO Database handle
 * @param $uid int User ID
 * @return array|null Tokens if linked, redirection if not.
 */
function require_tripit_link($dbh, $uid) {
    $tripit_tokens = get_request_tokens($dbh, $uid);
    if ($tripit_tokens == null) {
        header("Location: /php/tripit_rendezvous.php");
        exit();
    } else {
        return $tripit_tokens;
    }
}

/**
 * @param $dbh PDO Database handle
 * @param $uid int User ID
 * @return array|null Tokens if linked, null if not.
 */
function get_request_tokens($dbh, $uid) {
    try {
        $sql = "SELECT auth_token, auth_token_secret FROM tripit_tokens WHERE uid = ? AND active = 'Y'";
        $sth = $dbh->prepare($sql);
        $sth->execute(array($uid));
    } catch (PDOException $e) {
        die("Internal error.");
    }

    if ($sth->rowCount()) {
        // User has a token.
        $row = $sth->fetch();
        return array("token" => $row["auth_token"], "secret" => $row["auth_token_secret"]);
    } else {
        return null;
    }
}

/**
 * Handle TripIt error responses in potentially a user-friendly way.  Does nothing if it can't handle it.
 *
 * @param $response string Error response body from TripIt
 */
function handle_tripit_response($response) {
    global $dbh;

    if (strpos($response, "<detailed_error_code>106.1</detailed_error_code>") !== false) {
        # 106.1 - Token invalid.  Ask user to rendezvous again.

        # This shouldn't happen, but let's be paranoid.
        $uid = $_SESSION["uid"];
        if (!$uid || empty($uid)) {
            print _("Not logged in, aborting");
            exit();
        }

        # Disable the old tokens.
        $sth = $dbh->prepare("UPDATE tripit_tokens SET active='N' WHERE uid=?");
        $sth->execute(array($uid));

        header("Location: /php/tripit_rendezvous.php");
        exit();
    }
}
