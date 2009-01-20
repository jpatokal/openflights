<?php

// Generate content of Facebook profile box for given OF uid
function get_profile($db, $uid) {
  // Statistics
  // Number of flights, total distance (mi), total duration (minutes), public/open
  $sql = "SELECT COUNT(*) AS count, SUM(distance) AS distance, SUM(TIME_TO_SEC(duration))/60 AS duration FROM flights AS f WHERE uid=" . $uid;
  $result = mysql_query($sql, $db);
  if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
    $duration = sprintf("%d days, %d hours and %d minutes",
			floor($row["duration"] / 3600),
			floor(($row["duration"] % 3600)/60),
			$row["duration"] % 60);
    return sprintf("<p><img src='http://openflights.org/img/fb-thumbnail.png' width='184' height='101'/><br/><br/><a href='http://openflights.org/user/%s'><b>%s</b></a> (<fb:name uid=\"$user_id\" useyou=\"false\" />) has flown <b>%s</b> times, for a total distance of <b>%s miles</b> and a total duration of <b>%s</b>!</p>", $ofname, $ofname, $row["count"], $row["distance"], $duration);
  } else {
    return "Error, no data found";
  }
}

?>
