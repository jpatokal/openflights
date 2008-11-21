<?php

$host = "localhost";
$database = "flightdb";
$user = "openflights";
$password = "";

mysql_connect($host,$user,$password);
mysql_select_db($database);

$query = mysql_real_escape_string($_POST['plane']);
if(strstr($query, '-')) {
  $dashes = " ";
} else {
  $dashes = "AND name NOT LIKE 'Boeing %-%' AND name NOT LIKE 'Airbus %-%'";
}

$sql = "SELECT name,plid FROM planes WHERE public='Y' AND name LIKE '%" . $query . "%' ". $dashes . "ORDER BY name LIMIT 6";
$rs = mysql_query($sql);

// If no or only one result found, then try again
if(mysql_num_rows($rs) == 1 && $dashes != " ") {
  $sql = "SELECT name,plid FROM planes WHERE public='Y' AND name LIKE '%" . $query . "%' ORDER BY name LIMIT 6";
  $rs = mysql_query($sql);
 } else {
  if(mysql_num_rows($rs) == 0) {
    $sql = "SELECT name,plid FROM planes WHERE name LIKE '%" . $query . "%' ORDER BY name LIMIT 6";
    $rs = mysql_query($sql);
  }
 }

?>

<ul class='autocomplete'>

<?php
while($data = mysql_fetch_assoc($rs)) {
  $item = stripslashes($data['name']);
  if(strlen($item) > 23) {
    $item = substr($item, 0, 10) . "..." . substr($item, -10, 10);
  }
  echo "<li class='autocomplete' id='" . $data['plid'] . "'>" . $item . "</li>";
}
?>

</ul>
