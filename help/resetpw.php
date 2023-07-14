<?php
include_once '../php/db_pdo.php';
require_once "./php/locale.php";
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>OpenFlights: <?php echo _('Reset password'); ?></title>
    <link rel="stylesheet" href="/css/style_reset.min.css" type="text/css">
    <link rel="stylesheet" href="/openflights.css" type="text/css">
    <link rel="gettext" type="application/x-po" href="/locale/<?php echo $locale; ?>/LC_MESSAGES/messages.po" />
  </head>

  <body>
    <div id="contexthelp">
      <h1>OpenFlights: Reset password</h1>

<?php
if (isset($_GET["challenge"])) {
    $user = $_GET["user"];
    $challenge = $_GET["challenge"];
    $sth = $dbh->prepare("SELECT md5(password) AS challenge FROM users WHERE name = ?");
    $sth->execute([$user]);
    $row = $sth->fetch();
    if ($row) {
        if ($challenge == $row['challenge']) {
            $newpw = substr(uniqid(), 0, 8);
            $pwstring = md5($newpw . strtolower($user));
            $sth = $dbh->prepare("UPDATE users SET password = ? WHERE name = ?");
            if (!$sth->execute([$pwstring, $user])) {
                die('Resetting password for user ' . $user . ' failed');
            }
            printf(_("Your new password is <b>%s</b>. Please log in and change it from Settings."), $newpw)
                . "\n\n";
            echo "<input type='button' value='" . _('Login') . "' onClick='javascript:window.location=\"/\"'>";
        } else {
            echo _("Invalid challenge.");
        }
    } else {
        echo "No such user.";
    }
} elseif (isset($_POST["email"])) {
    $email = $_POST["email"];
    $sth = $dbh->prepare("SELECT name, md5(password) AS challenge FROM users WHERE email = ?");
    $sth->execute([$email]);
    $row = $sth->fetch();
    // TODO: Assumes an email address can only be assigned to one user...
    // Which isn't true. So this will only work for the first user row returned...
    if ($row) {
        $name = $row['name'];
        $link = "https://openflights.org/help/resetpw?user=" . $name
            . "&challenge=" . $row['challenge'];
        $subject = "OpenFlights: Reset password";
        $body = "Somebody has requested a password reset for your OpenFlights.org account '$name'. To proceed, please click on the link below:

  " . $link . "

If you do not want to change your password, simply ignore this mail and nothing will happen.

Cheers,
OpenFlights.org";

        if (isset($_POST["unittest"])) {
            echo $link . "***" .  $row['challenge'];
            exit(0);
        }
        $headers = "From: support@openflights.org";
        if (mail($email, $subject, $body, $headers)) {
              echo "<p>" . sprintf(_("A password reset link has been mailed to <b>%s</b>."), $email) . "</p>";
        } else {
            echo "<p>" . _("Message delivery failed, please contact <a href='/about'>support</a>.") . "</p>";
        }
    } else {
        echo "<p>" . _("Sorry, that e-mail address is not registered for any OpenFlights user.") . "</p>";
    }
} else {
    echo "<p>" .
        _("Can't log in? One tip before you panic: make sure you have entered your password <b>using the same case</b> as you did when signing up: \"Secret\" is not the same as \"secret\". If you signed up before January 15, 2009, your <b>username</b> (\"Joe\" vs \"joe\") also has to match. ")
        . "</p>";

    echo "<p>" .
        _("If that doesn't help, enter your <b>e-mail address</b> below, and we'll send you a link you can use to reset your password.")
        . "</p>";
?>
      <form name="reset" action="/help/resetpw" enctype="multipart/form-data" method="post">
        <input type="text" name="email" align="top" size="10" tabindex="1">
        <input type="submit" name="action" value="<?php _("Reset password"); ?>" tabindex="2">
      </form>
    <?php
    echo "<p>" .
        _("If you didn't register an e-mail address, then sorry, we don't know that you're you, so there's really nothing we can do.")
        . "</p>";
}
if (!isset($_GET["challenge"])) {
    echo "<input type=\"button\" id=\"close\" value=\"" . _("Return") . "\" onClick=\"javascript:window.close()\">";
}
?>

    </div>

  </body>
</html>
