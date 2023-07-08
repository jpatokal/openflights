<?php

require_once "locale.php";
require_once "db_pdo.php";
require_once "tripit_common.php";

$uid = $_SESSION["uid"];
if (!$uid || empty($uid)) {
    print _("Session expired.  Please try re-linking after logging back in.");
    exit();
}

try {
    $sth = $dbh->prepare("UPDATE tripit_tokens SET active='N' WHERE uid=?");
    $sth->execute(array($uid));
} catch (PDOException $e) {
    die(_("Failed to disable old TripIt links."));
}

// All good, redirect back to listing
header("Location: /php/tripit_list_trips.php");
