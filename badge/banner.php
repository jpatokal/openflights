<?php
include '../php/db_pdo.php';
include '../php/helper.php';

putenv('GDFONTPATH=' . realpath('.'));

function endsWith( $str, $sub ) {
  return ( substr( $str, strlen( $str ) - strlen( $sub ) ) == $sub );
}

function rendererror($string) {
  $font = "OpenSans-Regular";
  $im = imagecreatefrompng("banner.png");
  $white = imagecolorallocate ($im, 0xFF, 0xFF, 0xFF);
  imagettftext($im, 10.5, 0, 220, 37, $white, $font, "Error:");
  imagettftext($im, 10.5, 0, 220, 52, $white, $font, $string);
  imagepng($im);
  imagedestroy($im);
  exit(0);
}

header ("Content-type: image/png");
$user = $_GET["user"];
if(! $user || $user == "") {
  rendererror("User name missing");
}

// Trim off any trailing ".png"
if(endsWith($user, ".png")) {
  $user = substr($user, 0, strlen($user)-strlen(".png"));
}

// Do we have a recent (< 1 hr) cached copy?
$cache = "cache/" . $user;
if(file_exists($cache) && (time() - filemtime($cache) < 3600)) {
  $im = imagecreatefrompng($cache);
  imagepng($im);
  imagedestroy($im);
  return;
}

// New banner or cache out of date, so regenerate
$sth = $dbh->prepare("SELECT uid, public, units FROM users WHERE name=?");
$result = $sth->execute([$user]);
if(! $result) rendererror("Database error 1");
if($sth->rowCount() == 0) rendererror("User $user not found");
$row = $sth->fetch();
if($row["public"] == "N") rendererror("User is not public");
$uid = $row["uid"];
$units = $row["units"];

$sth = $dbh->prepare("SELECT COUNT(*) AS count, SUM(distance) AS distance, SUM(TIME_TO_SEC(duration))/60 AS duration FROM flights WHERE uid=?");
$result = $sth->execute([$uid]);
if($result && $row = $sth->fetch()) {
  $distance = $row["distance"];
  if($units == "K") {
    $distance *= $KMPERMILE;
    $units = "km";
  } else {
    $units = "miles";
  }
  $flights = sprintf("%s flights", $row["count"]);
  $miles = sprintf("%d,%03d %s", $distance / 1000, $distance % 1000, $units);
  $duration = sprintf("%d days, %d:%02d hours", $row["duration"] / 1440, ($row["duration"] / 60) % 24, $row["duration"] % 60);
} else {
  rendererror("Database error 2");
}

$font = "OpenSans-Regular";
$im = imagecreatefrompng("banner.png");
$white = imagecolorallocate ($im, 0xFF, 0xFF, 0xFF);
imagettftext($im, 10.5, 0, 220, 37, $white, $font, $flights);
imagettftext($im, 10.5, 0, 220, 52, $white, $font, $miles);
imagettftext($im, 10.5, 0, 220, 65, $white, $font, $duration);

// Write a copy to the cache
imagepng($im, 'cache/' . $user);

// And output to the browser
imagepng($im);
imagedestroy($im);

?>
