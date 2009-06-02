<?php
session_start();
include 'db.php';

// List of all countries
$sql = "SELECT code, name FROM countries ORDER BY name";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  if($first) {
    $first = false;
  } else {
    printf("\n");
  }
  printf ("%s;%s", $row["code"], $row["name"]);
}
?>
