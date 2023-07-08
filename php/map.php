<?php

include 'locale.php';
include 'db_pdo.php';
include 'helper.php';
include 'filter.php';

// This applies only when viewing another users flights
$user = $_POST["user"] ?? ($_GET["user"] ?? null);
$trid = $_POST["trid"] ?? ($_GET["trid"] ?? null);

// Login via vBulletin cookies
$bb_uid = $_COOKIE["bb_userid"] ?? null;
if ($OF_VBULLETIN_LOGIN && ! empty($bb_uid)) {
    $sth = $dbh->prepare("SELECT uid, name, email, editor, elite, units, locale FROM users WHERE bb_uid = ?");
    $sth->execute([$bb_uid]);
    $row = $sth->fetch();
    if ($row) {
        $uid = $_SESSION['uid'] = $row["uid"];
        $_SESSION['name'] = $row["name"];
        $_SESSION['email'] = $row["email"];
        $_SESSION['editor'] = $row["editor"];
        $_SESSION['elite'] = $row["elite"];
        $_SESSION['units'] = $row["units"];
    } elseif (!$trid && !$user) {
        die("Signup;No username found for ID " . $bb_uid);
    }
}

$uid = $_SESSION["uid"] ?? null;
$challenge = $_SESSION["challenge"] ?? null;
if (!$uid || empty($uid)) {
    // If not logged in, default to demo mode and warn app that we're (no longer?) logged in
    $uid = 1;
    $logged_in = "demo";
    $elite = null;
    $editor = null;
    if (!$challenge || empty($challenge)) {
        $challenge = md5(rand(1, 100000));
        $_SESSION["challenge"] = $challenge;
    }
} else {
    $logged_in = $_SESSION["name"]; // username
    $elite = $_SESSION["elite"];
    $editor = $_SESSION["editor"];
}

$init = $_POST["param"] ?? ($_GET["init"] ?? null);
$guestpw = $_POST["guestpw"] ?? null;

// Verify that this trip and user are public
$public = "O"; // default to full access

if ($trid && $trid != "0" && $trid != "null") {
    // Verify that we're allowed to access this trip
    // NB: a "trid" filter can mean logged-in *and* filtered, or not logged in!
    $sth = $dbh->prepare('SELECT * FROM trips WHERE trid = ?');
    $sth->execute([$trid]);
    $row = $sth->fetch();
    if (!$row) {
        die('Error;' . _("No such trip."));
    }

    if ($row["uid"] != $uid and $row["public"] == "N") {
        die('Error;' . _("This trip is not public."));
    }

    // Check if we're viewing out own trip
    if ($uid != $row["uid"]) {
        // Nope, we are *not* this user
        $uid = $row["uid"];
        $public = $row["public"];
        $logged_in = "demo";
        if ($public == "O") {
            $_SESSION["openuid"] = $uid;
            $_SESSION["opentrid"] = $trid;
        }
        // Increment view counter
        $sth = $dbh->prepare('UPDATE users SET count = count + 1 WHERE uid = ?');
        $sth->execute([$uid]);
    }
}

if ($user && $user != "0") {
    // Verify that we're allowed to view this user's flights
    // if $user is set, we are never logged in
    $sth = $dbh->prepare("SELECT uid, public, elite, guestpw, IF(? = guestpw, 'Y', 'N') AS pwmatch FROM users WHERE name = ?");
    $sth->execute([$guestpw, $user]);
    $row = $sth->fetch();
    if (!$row) {
        die('Error;' . _("No such user."));
    }

    if ($row["public"] == "N" && $row["pwmatch"] == "N") {
        if (!$row["guestpw"]) {
            die('Error;' . _("This user's flights are not public."));
        }

        die(
            "Error;" . _("This user's flights are password-protected.") . "<br><br>" .
            _("Password") . ": <input type='password' id='guestpw' size='10'>" .
            "<input type='button' value='Submit' align='middle' onclick='JavaScript:refresh(true)'>"
        );
    }

    $uid = $row["uid"];
    $public = $row["public"];
    $elite = $row["elite"];
    $logged_in = "demo"; // we are *not* this user
    if ($public == "O") {
        $_SESSION["openuid"] = $uid;
        $_SESSION["opentrid"] = null;
    }
    // Increment view counter
    $sth = $dbh->prepare('UPDATE users SET count = count + 1 WHERE uid = ?');
    $sth->execute([$uid]);
}

// Load up all information needed by this user
$filter = getFilterString($dbh, $_POST);
$map = "";

// Statistics
// Number of flights, total distance (mi), total duration (minutes), public/open
$sql = "SELECT COUNT(*) AS count, SUM(distance) AS distance, SUM(TIME_TO_SEC(duration)) / 60 AS duration FROM flights AS f WHERE uid = ? $filter";
$sth = $dbh->prepare($sql);
if (!$sth->execute([$uid])) {
    die('Error;Database error. ' . $filter . ' ' . $sql);
}
$row = $sth->fetch();
if ($row) {
    if ($row["count"] == "0" && $user && $user != "0") {
        die('Error;' . _("This user has no flights."));
    }
    $distance = $row["distance"];
    if (!$distance) {
        $distance = "0";
    }
    if (($_SESSION["units"] ?? null) == "K") {
        $distance = round($distance * KM_PER_MILE) . " " . _("km");
    } else {
        $distance .= " " . _("miles");
    }
    $map .= sprintf(
        "%s;%s;%s;%s;%s;%s;%s;%s\n",
        $row["count"],
        $distance,
        $row["duration"],
        $public,
        $elite,
        $logged_in,
        $editor,
        $challenge
    );
}

// List of all flights (unique by airport pair)
$sql = "SELECT DISTINCT s.apid, s.x, s.y, d.apid, d.x, d.y, COUNT(fid) as visits, AVG(distance), IF(MIN(src_date) > NOW(), 'Y', 'N') AS future, f.mode
FROM flights AS f, airports AS s, airports AS d
WHERE f.src_apid = s.apid AND f.dst_apid = d.apid AND f.uid = ?
$filter
GROUP BY s.apid, s.x, s.y, d.apid, d.x, d.y, f.mode";
$sth = $dbh->prepare($sql);
if (!$sth->execute([$uid])) {
    die('Error;Database error.');
}

$first = true;
foreach ($sth as $row) {
    if ($first) {
        $first = false;
    } else {
        $map .= "\t";
    }
    $map .= sprintf(
        "%s;%s;%s;%s;%s;%s;%s;%s;%s;%s",
        $row[0],
        $row[1],
        $row[2],
        $row[3],
        $row[4],
        $row[5],
        $row[6],
        $row[7],
        $row[8],
        $row[9]
    );
}
$map .= "\n";

// List of all airports
$sql = "SELECT DISTINCT a.apid, x, y, name, iata, icao, city, country, timezone, dst, count(name) AS visits, IF(MIN(src_date) > NOW(), 'Y', 'N') AS future
FROM flights AS f, airports AS a
WHERE (f.src_apid = a.apid OR f.dst_apid = a.apid) AND f.uid = ?
$filter
GROUP BY a.apid, x, y, name, icao, city, country, timezone, dst
ORDER BY visits ASC";

$sth = $dbh->prepare($sql);
if (!$sth->execute([$uid])) {
    die('Error;Database error.');
}

$first = true;
foreach ($sth as $row) {
    if ($first) {
        $first = false;
    } else {
        $map .= "\t";
    }
    $map .= sprintf(
        "%s;%s;%s;%s;%s;%s;%s",
        format_apdata($row),
        $row["name"],
        $row["city"],
        $row["country"],
        $row["visits"],
        format_airport($row),
        $row["future"]
    );
}

print $map . "\n";

// When running for the first time, load up possible filter settings for this user
if ($init == "true") {
    loadFilter($dbh, $uid, $trid, $logged_in);
}
