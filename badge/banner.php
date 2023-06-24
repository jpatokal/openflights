<?php
include '../php/db_pdo.php';
include '../php/helper.php';

putenv('GDFONTPATH=' . realpath('.'));

function renderError($string) {
    renderImage(array(
        37 => "Error:",
        52 => $string,
    ));
    exit(0);
}

/**
 * @param $textLines array [ $yIndex => $text ]
 * @param $user string|false Username
 */
function renderImage($textLines, $user = false) {
    $im = imagecreatefrompng("banner.png");
    $white = imagecolorallocate($im, 0xFF, 0xFF, 0xFF);
    foreach ($textLines as $y => $text) {
        imagettftext($im, 10.5, 0, 220, $y, $white, "OpenSans-Regular", $text);
    }
    // Only write to the cache if we have a user (ie, not in error cases)
    if ($user) {
        imagepng($im, 'cache/' . $user);
    }
    imagepng($im);
    imagedestroy($im);
}

header("Content-type: image/png");
$user = $_GET["user"];
if (!$user || $user == "") {
    renderError("User name missing");
}

// Trim off any trailing ".png"
if (strpos($user, ".png") !== false) {
    $user = pathinfo($user, PATHINFO_FILENAME);
}

// Do we have a recent (< 1 hr) cached copy?
$cache = "cache/" . $user;
if (file_exists($cache) && (time() - filemtime($cache) < 3600)) {
    $im = imagecreatefrompng($cache);
    imagepng($im);
    imagedestroy($im);
    return;
}

// New banner or cache out of date, so regenerate
$sth = $dbh->prepare("SELECT uid, public, units FROM users WHERE name=?");
$result = $sth->execute([$user]);
if (!$result) {
    renderError("Database error 1");
}
if ($sth->rowCount() == 0) {
    renderError("User $user not found");
}
$row = $sth->fetch();
if ($row["public"] == "N") {
    renderError("User is not public");
}
$uid = $row["uid"];
$units = $row["units"];

$sth = $dbh->prepare(
    "SELECT COUNT(*) AS count, SUM(distance) AS distance, SUM(TIME_TO_SEC(duration))/60 AS duration FROM flights WHERE uid=?"
);
$result = $sth->execute([$uid]);
if ($result && $row = $sth->fetch()) {
    $distance = $row["distance"];
    if ($units == "K") {
        $distance *= KM_PER_MILE;
        $units = "km";
    } else {
        $units = "miles";
    }
    $flights = sprintf("%s flights", $row["count"]);
    $miles = sprintf("%s %s", number_format($distance, 0, ".", ","), $units);
    $duration = sprintf(
        "%d days, %d:%02d hours",
        $row["duration"] / 1440,
        ($row["duration"] / 60) % 24,
        $row["duration"] % 60
    );
} else {
    renderError("Database error 2");
}

renderImage(
    array(
        37 => $flights,
        52 => $miles,
        65 => $duration,
    ),
    $user
);
