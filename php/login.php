<?php
session_start();
$name = $HTTP_POST_VARS["name"];
// pw is hashed from lowercased username, legacypw is not
$pw = $HTTP_POST_VARS["pw"];
$legacypw = $HTTP_POST_VARS["lpw"];
$challenge = $_SESSION["challenge"];

// Log in user
if($name) {
  $db = mysql_connect("localhost", "openflights");
  mysql_select_db("flightdb",$db);

  // CHAP: Use random challenge key in addition to password
  // user_pw == MD5(challenge, db_pw)
  $sql = "SELECT * FROM users WHERE name='" . mysql_real_escape_string($name) .
    "' AND ('" . mysql_real_escape_string($pw) . "' = MD5(CONCAT('" .
    mysql_real_escape_string($challenge) . "',password)) OR " .
    " '" . mysql_real_escape_string($legacypw) . "' = MD5(CONCAT('" .
    mysql_real_escape_string($challenge) . "',password)))";
  $result = mysql_query($sql, $db);
  if ($myrow = mysql_fetch_array($result)) {
    $uid = $myrow["uid"];
    $_SESSION['uid'] = $uid;
    $_SESSION['name'] = $myrow["name"];
    $_SESSION['email'] = $myrow["email"];
    $_SESSION['editor'] = $myrow["editor"];
    $_SESSION['elite'] = $myrow["elite"];

    printf("1;%s;%s;%s", $myrow["name"], $myrow["editor"], $myrow["elite"]);
  } else {
    printf("0;Login failed. <a href='/html/signup.html'>Create account</a> or <a href='#' onclick='JavaScript:help(\"forgotpw\")'>reset password?</a>");
  }
}
?>


