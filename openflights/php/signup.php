<?php
session_start();
$name = $HTTP_POST_VARS["name"];
$pw = $HTTP_POST_VARS["pw"];
$email = $HTTP_POST_VARS["email"];
$privacy = $HTTP_POST_VARS["privacy"];

// Create new user
if($name) {
  $db = mysql_connect("localhost", "openflights");
  mysql_select_db("flightdb",$db);

  $sql = "SELECT * FROM users WHERE name='" . mysql_real_escape_string($name) . "'";
  $result = mysql_query($sql, $db);
  if (mysql_fetch_array($result)) {
    printf("0;Sorry, that user name is already taken.");
  } else {
    // Note: Password is stored as salted hash of pw and username
    $sql = sprintf("INSERT INTO users(name,password,email,public) VALUES('%s',MD5(CONCAT('%s','%s')),'%s','%s')",
		   mysql_real_escape_string($name),
		   mysql_real_escape_string($pw), mysql_real_escape_string($name),
		   mysql_real_escape_string($email),
		   mysql_real_escape_string($privacy));
    mysql_query($sql, $db) or die ('0;Creating user ' . $name . ' failed: ' . $sql . ', error ' . mysql_error());
    printf("1;Successfully created account.");
  }
}
?>
