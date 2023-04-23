<?php
include '../php/db_pdo.php';
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>OpenFlights: Reset password</title>
    <link rel="stylesheet" href="/css/style_reset.css" type="text/css">
    <link rel="stylesheet" href="/openflights.css" type="text/css">
  </head>

  <body>
    <div id="contexthelp">
      <h1>OpenFlights: Reset password</h1>

<?php
if(isSet($_GET["challenge"])) {
  $user = $_GET["user"];
  $challenge = $_GET["challenge"];
  $sth = $dbh->prepare("SELECT md5(password) AS challenge FROM users WHERE name = ?");
  $sth->execute([$user]);
  if ($myrow = $sth->fetch()) {
    if($challenge == $myrow['challenge']) {
      $newpw = substr(uniqid(), 0, 8);
      $pwstring = md5($newpw . strtolower($user));
      $sth = $dbh->prepare("UPDATE users SET password = ? WHERE name=?");
      if (!$sth->execute([$pwstring, $user])) die ('Resetting password for user ' . $name . ' failed');
      echo "Your new password is <b>$newpw</b>. Please log in and change it from Settings.\n\n<input type='button' value='Login' onClick='javascript:window.location=\"/\"'>";
    } else {
      echo "Invalid challenge.";
    }
  } else {
    echo "No such user.";
  }
} elseif(isSet($_POST["email"])) {
  $email = $_POST["email"];
  $sth = $dbh->prepare("SELECT name, md5(password) AS challenge FROM users WHERE email = ?");
  $sth->execute([$email]);
  if ($myrow = $sth->fetch()) {
    $name = $myrow['name'];
    $link = "https://openflights.org/help/resetpw?user=" . $name
      . "&challenge=" . $myrow['challenge'];
    $subject = "OpenFlights: Reset password";
    $body = "Somebody has requested a password reset for your OpenFlights.org account '$name'.  To proceed, please click on the link below:

  " . $link . "

If you do not want to change your password, simply ignore this mail and nothing will happen.

Cheers,
OpenFlights.org";
    $headers = "From: support@openflights.org";
    if(isSet($_POST["unittest"])) {
      echo $link . "***" .  $myrow['challenge'];
      exit(0);
    }
    if (mail($email, $subject, $body, $headers)) {
      echo("<p>A password reset link has been mailed to <b>$email</b>.</p>");
    } else {
      echo("<p>Message delivery failed, please contact <a href='/about'>support</a>.</p>");
    }
  } else {
    echo "<p>Sorry, that e-mail address is not registered for any OpenFlights user.</p>";
  }
} else {
?>

      <p>Can't log in? One tip before you panic: make sure you have entered your password <b>using the same case</b> as you did when signing up: "Secret" is not the same as "secret". If you signed up before January 15, 2009, your <b>username</b> ("Joe" vs "joe") also has to match.</p>

      <p>If that doesn't help, enter your <b>e-mail address</b> below, and
      we'll send you a link you can use to reset your password.</p>

      <form name="reset" action="/help/resetpw" enctype="multipart/form-data" method="post">
        <input type="text" name="email" align="top" size="10" tabindex="1">
        <input type="submit" name="action" value="Reset password" tabindex="2">
      </form>

      <p>If you didn't register an e-mail address, then sorry, we don't know that you're you, so there's really nothing we can do.</p>
<?php
}
if(! isSet($_GET["challenge"])) {
?>
      <input type="button" id="close" value="Return" onClick="javascript:window.close()">
<?php
}
?>

    </div>

  </body>
</html>
