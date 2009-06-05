<?php
include 'locale.php';
include 'db.php';

$type = $HTTP_POST_VARS["type"];
$name = $HTTP_POST_VARS["name"];
$pw = $HTTP_POST_VARS["pw"];
$oldpw = $HTTP_POST_VARS["oldpw"];
$oldlpw = $HTTP_POST_VARS["oldlpw"];
$email = $HTTP_POST_VARS["email"];
$privacy = $HTTP_POST_VARS["privacy"];
$editor = $HTTP_POST_VARS["editor"];
$guestpw = $HTTP_POST_VARS["guestpw"];
$startpane = $HTTP_POST_VARS["startpane"];
$locale = $HTTP_POST_VARS["locale"]; // override any value in URL/session

// 0 error
// 1 new
// 2 edited
// 10 reset

// Create new user
switch($type) {
 case "NEW":
   $sql = "SELECT * FROM users WHERE name='" . mysql_real_escape_string($name) . "'";
   $result = mysql_query($sql, $db);
   if (mysql_fetch_array($result)) {
     die("0;" . _("Sorry, that name is already taken, please try another."));
   }
   break;
   
 case "EDIT":
 case "RESET":
  $uid = $_SESSION["uid"];
  $name = $_SESSION["name"];
  if(!$uid or empty($uid)) {
    die("0;" . _("Your session has timed out, please log in again."));
  }

  if($type == "RESET") {
    $sql = "DELETE FROM flights WHERE uid=" . $uid;
    $result = mysql_query($sql, $db);
    printf("10;" . _("Account reset, %s flights deleted."), mysql_affected_rows());
    exit;
  }

  // EDIT
  if($oldpw && $oldpw != "") {
    $sql = "SELECT * FROM users WHERE name='" . mysql_real_escape_string($name) .
      "' AND (password='" . mysql_real_escape_string($oldpw) . "' OR " .
      "password='" . mysql_real_escape_string($oldlpw) . "')";
    $result = mysql_query($sql, $db);
    if(! mysql_fetch_array($result)) {
      die("0;" . _("Sorry, current password is not correct."));
    }
  }
  break;

 default:
   die("0;Unknown action $type");
}

// Note: Password is actually an MD5 hash of pw and username
if($type == "NEW") {
  $sql = sprintf("INSERT INTO users(name,password,email,public,editor,locale) VALUES('%s','%s','%s','%s', '%s', '%s')",
		 mysql_real_escape_string($name),
		 mysql_real_escape_string($pw),
		 mysql_real_escape_string($email),
		 mysql_real_escape_string($privacy),
		 mysql_real_escape_string($editor),
		 mysql_real_escape_string($locale));
} else {
  // Only change password if old password matched and a new one was given
  if($oldpw && $oldpw != "" && $pw && $pw != "") {
    $pwsql = sprintf("password='%s', ", mysql_real_escape_string($pw));
  } else {
    $pwsql = "";
  }
  if(! $guestpw) $guestpw = "";
  $sql = sprintf("UPDATE users SET %s email='%s', public='%s', editor='%s', guestpw=%s, startpane='%s', locale='%s' WHERE uid=%s",
		 $pwsql,
		 mysql_real_escape_string($email),
		 mysql_real_escape_string($privacy),
		 mysql_real_escape_string($editor),
		 $guestpw == "" ? "NULL" : "'" . mysql_real_escape_string($guestpw) . "'",
		 mysql_real_escape_string($startpane),
		 mysql_real_escape_string($locale),
		 $uid);
}
mysql_query($sql, $db) or die ('0;Operation on user ' . $name . ' failed: ' . $sql . ', error ' . mysql_error());

// In all cases change locale to user selection
$_SESSION['locale'] = $locale;

if($type == "NEW") {
  printf("1;" . _("Successfully signed up, now logging in..."));

  // Log in the user
  $uid = mysql_insert_id();
  $_SESSION['uid'] = $uid;
  $_SESSION['name'] = $name;
  $_SESSION['editor'] = $editor;
  $_SESSION['elite'] = $elite;
} else {
  printf("2;" . _("Settings changed successfully, returning..."));
}
?>
