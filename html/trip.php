<?php
require_once("../php/locale.php");
require_once("../php/db_pdo.php");

if (isset($_GET["trid"])) {
    $trid = $_GET["trid"];
} else {
    $trid = null;
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>OpenFlights: <?php echo $trid ? _("Edit trip") : _("Add trip"); ?></title>
    <link rel="stylesheet" href="/css/style_reset.css" type="text/css">
    <link rel="stylesheet" href="/openflights.css" type="text/css">
    <link rel="gettext" type="application/x-po" href="/locale/<?php echo $locale?>/LC_MESSAGES/messages.po" />
    <script type="text/javascript" src="/js/Gettext.js"></script>
    <script type="text/javascript" src="/js/trip.js"></script>
  </head>

  <body>
    <div id="contexthelp">
      <FORM name="tripform">
    <div id="title"><h1>OpenFlights: <?php echo $trid ? _("Edit trip") : _("Add trip"); ?></h1></div>

<?php
$uid = $_SESSION["uid"];
if (!$uid || empty($uid)) {
    die(_("Your session has timed out, please log in again."));
}

if ($trid) {
    $sth = $dbh->prepare("SELECT * FROM trips WHERE trid=? AND uid=?");
    $sth->execute([$trid, $uid]);
    if (!$trip = $sth->fetch()) {
        // TODO: $sql is undefined
        die(_("Could not load trip data.") . $sql);
    }
} else {
    $trip = array(
        "name" => "",
        "url" => "",
        "public" => "Y"
    );
}
?>
    <div id="miniresultbox"></div>
    <table>
        <tr>
              <td><?php echo _("Name") ?></td>
          <td><INPUT type="text" name="name" size="40" value="<?php echo $trip["name"] ?>"></td>
        </tr><tr>
              <td><?php echo _("Web address <i>(optional)</i>") ?>&nbsp;</td>
          <td><INPUT type="text" name="url" size="40" value="<?php echo $trip["url"] ?>"></td>
        </tr><tr>
          <td style="vertical-align: top"><?php echo _("Trip privacy") ?></td>
          <td><input type="radio" name="privacy" value="N" <?php if ($trip["public"] == "N") { echo "CHECKED"; } echo ">" . _("Private (visible only to you)") ?><br>
          <input type="radio" name="privacy" value="Y" <?php if ($trip["public"] == "Y") { echo "CHECKED"; } echo ">" . _("Public (map and stats shared)") ?><br>
          <input type="radio" name="privacy" value="O" <?php if ($trip["public"] == "O") { echo "CHECKED"; } echo ">" . _("Open (all flight data shared)") ?></td>
        </tr><tr>
          <td><?php echo _("OpenFlights URL") ?></td>
          <td><input type="text" value="<?php echo $trid ? "https://openflights.org/trip/" . $trid : _("Not assigned yet");?>" name="puburl" style="border:none" size="40" readonly></td>
        </tr>
    </table><br>

<?php if ($trid) {
      echo "<INPUT type='button' value='" . _("Save") . "' onClick='validate(\"EDIT\")'>\n";
      echo "<INPUT type='hidden' name='trid' value='" . $trid . "'>\n";
      echo "<INPUT type='button' value='" . _("Delete") . "' onClick='deleteTrip()'>\n";
} else {
    echo "<INPUT type='button' value='" . _("Add") . "' onClick='validate(\"NEW\")'";
}
?>
    <INPUT type="button" value="<?php echo _("Cancel") ?>" onClick="window.close()">
      </FORM>

    </div>

  </body>
</html>
