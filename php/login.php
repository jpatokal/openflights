<?php
session_start();
$postname = $HTTP_POST_VARS["name"];
$pw = $HTTP_POST_VARS["pw"];

// Log in user
if($postname) {
  $db = mysql_connect("localhost", "openflights");
  mysql_select_db("flightdb",$db);

  $name = $postname;
  $sql = "select * from users where name='$name' AND password='$pw';";
  $result = mysql_query($sql, $db);
  if ($myrow = mysql_fetch_array($result)) {
    $uid = $myrow["uid"];
    $info = "Welcome, <B>$name</B>!";
    $_SESSION['uid'] = $uid;
    $_SESSION['name'] = $name;
    $_SESSION['access'] = $access;
  } else {
    $info = "Invalid login, please try again.";
    $name = "";
  }
}

echo $info;
?>

<form action="login.php" method="post" name="loginform">

<TABLE>
<TR><TD>Username</TD><TD>
  <input type="Text" name="name" align="TOP" size="10">
</TD>
</TR><TR>
<TD>Password</TD><TD>
  <input type="password" name="pw" align="TOP" size="10">
</TD><TD>
<input type="Submit" value="Log in" align="MIDDLE"></form>
</TD>
</TR></TABLE>

