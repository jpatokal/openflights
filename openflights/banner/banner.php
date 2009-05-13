<?php

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
$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);
$sql = "SELECT COUNT(*) AS count, SUM(distance) AS distance, SUM(TIME_TO_SEC(duration))/60 AS duration FROM flights AS f,users AS u WHERE u.name='" . $user . "' and f.uid=u.uid";

$result = mysql_query($sql, $db);
if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  if(!$row["distance"] || $row["distance"] == "") {
    rendererror("User $user not found");
  } else {
    $flights = sprintf("%s flights", $row["count"]);
    $miles = sprintf("%s miles", $row["distance"]);
    $duration = sprintf("%d days, %2d:%02d hours", $row["duration"] / 1440, ($row["duration"] / 60) % 24, $row["duration"] % 60);
  }
} else {
  rendererror("Database error");
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