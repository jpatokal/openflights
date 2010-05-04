<?php
include 'locale.php';
include 'db.php';

$name = $_POST["name"];
// pw is hashed from lowercased username, legacypw is not
$pw = $_POST["pw"];
$legacypw = $_POST["lpw"];
$challenge = $_SESSION["challenge"];

// Log in user
if($name) {
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
    $_SESSION['units'] = $myrow["units"];
    if($myrow["locale"] != "en_US" && $_SESSION['locale'] != $myrow["locale"]) {
      $rc = 2; // force reload, so UI is changed into user's language
    } else {
      $rc = 1;
    }
    $_SESSION['locale'] = $myrow["locale"];
    printf("%s;%s;%s;%s", $rc, $myrow["name"], $myrow["editor"], $myrow["elite"]);
  } else {
    printf("0;" . _("Login failed. <%s>Create account</a> or <%s>reset password</a>?"),
	   "a href='/html/settings?new=yes'", "a href='#' onclick='JavaScript:help(\"resetpw\")'");
  }
}
?>


