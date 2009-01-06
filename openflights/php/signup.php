<?php
session_start();

$type = $HTTP_POST_VARS["type"];
$name = $HTTP_POST_VARS["name"];
$pw = $HTTP_POST_VARS["pw"];
$oldpw = $HTTP_POST_VARS["oldpw"];
$email = $HTTP_POST_VARS["email"];
$privacy = $HTTP_POST_VARS["privacy"];
$editor = $HTTP_POST_VARS["editor"];
$guestpw = $HTTP_POST_VARS["guestpw"];
$startpane = $HTTP_POST_VARS["startpane"];

// 0 error
// 1 new
// 2 edited
// 3 loaded
// 10 reset

// Create new user
$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);

if($type == "NEW") {
  $sql = "SELECT * FROM users WHERE name='" . mysql_real_escape_string($name) . "'";
  $result = mysql_query($sql, $db);
  if (mysql_fetch_array($result)) {
    printf("0;Sorry, that name is already taken, please try another.");
    exit;
  }

} else {
  // EDIT, LOAD or RESET
  $uid = $_SESSION["uid"];
  $name = $_SESSION["name"];
  if(!$uid or empty($uid)) {
    printf("0;Your session has timed out, please log in again.");
    exit;
  }

  if($type == "RESET") {
    $sql = "DELETE FROM flights WHERE uid=" . $uid;
    $result = mysql_query($sql, $db);
    printf("10;Account reset, " . mysql_affected_rows() . " flights deleted.");
    exit;
  }

  if($type == "LOAD") {
    $sql = "SELECT * FROM users WHERE uid=" . $uid;
    $result = mysql_query($sql, $db);
    if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
      printf("3;" . json_encode($row));
    } else {
      printf("0;Unknown error");
    }
    exit;
  }

  // EDIT
  if($oldpw && $oldpw != "") {
    $sql = "SELECT * FROM users WHERE name='" . mysql_real_escape_string($name) .
      "' AND password=MD5(CONCAT('" . mysql_real_escape_string($oldpw) . "','" . mysql_real_escape_string($name) . "'));";
    $result = mysql_query($sql, $db);
    if(! mysql_fetch_array($result)) {
      printf("0;Sorry, current password is not correct.");
      exit;
    }
  }
}

// Note: Password is stored as salted hash of pw and username
if($type == "NEW") {
  $sql = sprintf("INSERT INTO users(name,password,email,public,editor) VALUES('%s',MD5(CONCAT('%s','%s')),'%s','%s', '%s')",
		 mysql_real_escape_string($name),
		 mysql_real_escape_string($pw), mysql_real_escape_string($name),
		 mysql_real_escape_string($email),
		 mysql_real_escape_string($privacy),
		 mysql_real_escape_string($editor));
} else {
  // Only change password if a new one was given
  if($pw && $pw != "") {
    $pwsql = sprintf("password=MD5(CONCAT('%s','%s')), ",
		     mysql_real_escape_string($pw), mysql_real_escape_string($name));
  } else {
    $pwsql = "";
  }
  $sql = sprintf("UPDATE users SET %s email='%s', public='%s', editor='%s', guestpw=%s, startpane='%s' WHERE uid=%s",
		 $pwsql,
		 mysql_real_escape_string($email),
		 mysql_real_escape_string($privacy),
		 mysql_real_escape_string($editor),
		 $guestpw == "" ? "NULL" : "MD5(CONCAT('" . mysql_real_escape_string($guestpw) . "',name))",
		 mysql_real_escape_string($startpane),
		 $uid);
}
mysql_query($sql, $db) or die ('0;Operation on user ' . $name . ' failed: ' . $sql . ', error ' . mysql_error());

if($type == "NEW") {
  printf("1;Successfully signed up, now logging in...");

  // Log in the user
  $uid = mysql_insert_id();
  $_SESSION['uid'] = $uid;
  $_SESSION['name'] = $name;
  $_SESSION['editor'] = $editor;
  $_SESSION['elite'] = $elite;

} else {
  printf("2;Settings changed successfully.");
}
?>
