<?php
// Generate content of Facebook profile box for given OF uid
function get_profile($db, $uid, $fbuid, $ofname, $type = "profile") {
  if($type == "profile") {
    $basemap = "fb-thumbnail.png";
    $mapwidth = 184;
    $mapheight = 92;
  } else {
    $basemap = "fb-largemap.png";
    $mapwidth = 512;
    $mapheight = 256;
  }

  $KMPERMILE = 1.609344;
  $sql = "SELECT COUNT(*) AS count, SUM(distance) AS distance, SUM(TIME_TO_SEC(duration))/60 AS duration, u.public, u.units FROM flights AS f, users AS u WHERE u.uid = f.uid AND f.uid=" . $uid . " GROUP BY f.uid";
  $result = mysql_query($sql, $db);
  if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
    if($row["public"] == "N") {
      $content = sprintf("<b>Oops!</b>  Looks like <b><a href='http://openflights.org/user/%s'>%s</a></b>'s map has become private.", $ofname, $ofname);
    } else {
      $distance = $row["distance"];
      if($row["units"] == "K") {
	$distance = round($distance * $KMPERMILE) . " km";
      } else {
	$distance .= " miles";
      }
      $duration = sprintf("%d days, %d hours and %d minutes",
			  floor($row["duration"] / (60*24)),
			  floor(($row["duration"] / 60 ) % 24),
			  $row["duration"] % 60);
      $content = sprintf("<b><a href='http://openflights.org/user/%s'>%s</a></b> (<fb:name uid=\"$fbuid\" useyou=\"false\" />) has flown <b>%s</b> times, for a total distance of <b>%s</b> and a total duration of <b>%s</b>!  <a href='http://openflights.org/user/%s'>Find out more.</a>", $ofname, $ofname, $row["count"], $distance, $duration, $ofname);
    }
  } else {
    $content = sprintf("<b><a href='http://openflights.org/user/%s'>%s</a></b> (<fb:name uid=\"$fbuid\" useyou=\"false\" />) doesn't seem to have flown anywhere yet.   <a href='http://openflights.org/'>Add some flights?</a>", $ofname);
  }
  $rand = rand();
  return "<a href='http://openflights.org/user/" . $ofname . "'><img src='http://openflights.org/facebook/map.php?uid=$uid&basemap=$basemap&rand=$rand' width='$mapwidth' height='$mapheight'/></a><br/><br/>" . $content . "<br/><p style='text-align: right'><a href='http://apps.facebook.com/openflights'>Refresh</a></p>";
}

?>
