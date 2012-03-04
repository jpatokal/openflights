<?php
require_once("locale.php");
require_once("db_pdo.php");
require_once("tripit_common.php");

$uid = $_SESSION["uid"];
if (!$uid or empty($uid)) {
  print _("Not logged in, aborting");
  exit();
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
  <title>OpenFlights: <?php echo _("TripIt") ?></title>
    <link rel="stylesheet" href="/css/style_reset.css" type="text/css">
    <link rel="stylesheet" href="/openflights.css" type="text/css">
  </head>

  <body>
    <div id="contexthelp">
      <h1>OpenFlights: <?php echo _("Link your TripIt Account") ?></h1>

      <p><?php echo _("To import flights via TripIt, you'll need to authorize OpenFlights to access your TripIt account.  OpenFlights will only be able to look at (but not modify) your TripIt data.");?></p>

      <p><a href="/php/tripit_rendezvous_start.php"><?php echo _("Sounds good, take me to TripIt for authorization.");?></a></p>

    </div>

  </body>
</html>
