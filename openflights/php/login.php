<?php
session_start();
$name = $HTTP_POST_VARS["name"];
$pw = $HTTP_POST_VARS["pw"];

// Log in user
if($name) {
  $db = mysql_connect("localhost", "openflights");
  mysql_select_db("flightdb",$db);

  $sql = "SELECT * FROM users WHERE name='" . mysql_real_escape_string($name) . "' AND password=MD5(CONCAT('" . mysql_real_escape_string($pw) . "','" . mysql_real_escape_string($name) . "'));";
  $result = mysql_query($sql, $db);
  if ($myrow = mysql_fetch_array($result)) {
    $uid = $myrow["uid"];
    $_SESSION['uid'] = $uid;
    $_SESSION['name'] = $name;
    $_SESSION['access'] = $access;
    printf("1;%s", $name);
  } else {
    printf("0;Login failed" . $sql);
  }
}
?>


