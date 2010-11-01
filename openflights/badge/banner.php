<?php
include '../php/db.php';
include '../php/helper.php';

function endsWith( $str, $sub ) {
  return ( substr( $str, strlen( $str ) - strlen( $sub ) ) == $sub );
}

function rendererror($string) {
  $im = imagecreatefrompng("banner.png");
  $white = imagecolorallocate ($im, 0xFF, 0xFF, 0xFF);
  imagestring($im, 4, 220, 25, "Error:", $white);
  imagestring($im, 4, 220, 38, $string, $white);
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
$sql = "SELECT uid,public,units FROM users WHERE name='" . mysql_real_escape_string($user) . "'";
$result = mysql_query($sql, $db);
if(! $result) rendererror("Database error 1");
if(mysql_num_rows($result) == 0) rendererror("User $user not found");
$row = mysql_fetch_array($result, MYSQL_ASSOC);
if($row["public"] == "N") rendererror("User is not public");
$uid = $row["uid"];
$units = $row["units"];

$sql = "SELECT COUNT(*) AS count, SUM(distance) AS distance, SUM(TIME_TO_SEC(duration))/60 AS duration FROM flights WHERE uid=$uid";
$result = mysql_query($sql, $db);
if($result && $row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  $distance = $row["distance"];
  if($units == "K") {
    $distance *= $KMPERMILE;
    $units = "km";
  } else {
    $units = "miles";
  }
  $flights = sprintf("%s flights", $row["count"]);
  $miles = sprintf("%d,%03d %s", $distance / 1000, $distance % 1000, $units);
  $duration = sprintf("%d days, %2d:%02d hours", $row["duration"] / 1440, ($row["duration"] / 60) % 24, $row["duration"] % 60);
} else {
  rendererror("Database error 2");
}

$im = imagecreatefrompng("banner.png");
$white = imagecolorallocate ($im, 0xFF, 0xFF, 0xFF);
imagestring($im, 4, 220, 25, $flights, $white);
imagestring($im, 4, 220, 38, $miles, $white);
imagestring($im, 4, 220, 51, $duration, $white);

// Write a copy to the cache
imagepng($im, 'cache/' . $user);

// And output to the browser
imagepng($im);
imagedestroy($im);

?>
