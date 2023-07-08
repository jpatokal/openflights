<?php

require_once "locale.php";
require_once "db_pdo.php";
require_once "tripit_common.php";

$uid = $_SESSION["uid"];
if (!$uid || empty($uid)) {
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
    <link rel="stylesheet" href="/css/tripit.css" type="text/css">
    <link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.18/themes/ui-lightness/jquery-ui.css" type="text/css">
    <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
    <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.18/jquery-ui.min.js"></script>
    <script type="text/javascript" src="/js/tripit.js"></script>
  </head>

  <body onload="rendezvousPageInit()">
  <div id="contexthelp">
    <h1>OpenFlights: <?php echo _("Link your TripIt Account") ?></h1>

    <p><?php echo _("To import flights via TripIt, you'll need to authorize OpenFlights to access your TripIt account. OpenFlights will only be able to look at (but not modify) your TripIt data.");?></p>

    <div id="loginPathSelection">
      <h2 style="border-bottom: none; font-weight: 600"><?php echo _("How do you login to your TripIt account?") ?></h2>
      <button class="ui-button ui-widget ui-state-default ui-corner-all loginSelector" id="loginPathNative">
      <span class="ui-button-text">
        <?php echo _("I use my") ?>
        <img style="margin-bottom: -2px; width: 43px; height:20px;" src="/img/tripit-logo-43x20.png" alt="TripIt">
        <?php echo _("email and password to login.") ?>
      </span>
      </button>

      <button class="ui-button ui-widget ui-state-default ui-corner-all loginSelector" id="loginPathPartner">
      <span class="ui-button-text">
          <?php echo _("I use") ?><br>
          <span class="partnerLoginList">
          <span class="tripit_sprites_soc tripit_sprites_soc_google">Google</span><br>
          <span class="tripit_sprites_soc tripit_sprites_soc_yahoo">Yahoo</span><br>
          <span class="tripit_sprites_soc tripit_sprites_soc_fb">Facebook</span><br>
          </span><br>
          <?php echo _("to login.") ?>
      </span>
      </button>
    </div>
    <div id="loginPathPartnerHelp">
      <?php echo _("Logging in with a partner is a")?> <b><?php echo _("2 step process") ?></b>.
      <ol>
        <li>
          <a href="javascript:openTripItLogin()"><?php echo _("Click here to login to your TripIt account.")?></a>
          <?php echo _("You can skip this step if you're already logged in.")?>
          <?php echo _("After you've logged in, close the popup window to come back here.")?>
        </li>
        <li>
          <a href="tripit_rendezvous_start.php">
            <?php echo _("Click here to connect your TripIt account to OpenFlights.")?>
          </a>
        </li>
      </ol>
    </div>
  </div>

  </body>
</html>
