<?php
require_once "../php/locale.php";
require_once "../php/db_pdo.php";
require_once "../php/helper.php";

$trid = $_GET["trid"] ?? null;
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title><?php echo sprintf(_('OpenFlights: %s'), $trid ? _("Edit trip") : _("Add trip")); ?></title>
    <link rel="stylesheet" href="/css/style_reset.min.css" type="text/css">
    <link rel="stylesheet" href="/openflights.css" type="text/css">
    <link rel="gettext" type="application/x-po" href="/locale/<?php echo $locale; ?>/LC_MESSAGES/messages.po" />
    <script type="text/javascript" src="/js/Gettext.min.js"></script>
    <script type="text/javascript" src="/js/trip.js"></script>
  </head>

  <body>
    <div id="contexthelp">
      <form name="tripform">
    <div id="title"><h1><?php echo $trid ? _("Edit trip") : _("Add trip"); ?></h1></div>

<?php
$uid = $_SESSION["uid"];
if (!$uid || empty($uid)) {
    die(_("Your session has timed out, please log in again."));
}

if ($trid) {
    $sth = $dbh->prepare("SELECT * FROM trips WHERE trid = ? AND uid = ?");
    $sth->execute([$trid, $uid]);
    $trip = $sth->fetch();
    if (!$trip) {
        die(_("Could not load trip data."));
    }
} else {
    $trip = [
        "name" => "",
        "url" => "",
        "public" => "Y"
    ];
}
?>
    <div id="miniresultbox"></div>
    <table>
        <tr>
          <td><?php echo _("Name"); ?></td>
          <td><input type="text" name="name" size="40" value="<?php echo $trip["name"]; ?>"></td>
        </tr>
        <tr>
          <td><?php echo _("Web address <i>(optional)</i>"); ?>&nbsp;</td>
          <td><input type="text" name="url" size="40" value="<?php echo $trip["url"]; ?>"></td>
        </tr><tr>
          <td style="vertical-align: top"><?php echo _("Trip privacy"); ?></td>
          <td><input type="radio" name="privacy" value="N"<?php condArrOut($trip, "public", "N", "CHECKED"); echo ">"
                    . _("Private (visible only to you)"); ?><br>
          <input type="radio" name="privacy" value="Y" <?php condArrOut($trip, "public", "Y", "CHECKED"); echo ">"
                    . _("Public (map and stats shared)"); ?><br>
          <input type="radio" name="privacy" value="O" <?php condArrOut($trip, "public", "0", "CHECKED"); echo ">"
                    . _("Open (all flight data shared)"); ?></td>
        </tr>
        <tr>
          <td><?php echo _("OpenFlights URL"); ?></td>
          <td><input type="text" value="<?php echo $trid ? "https://openflights.org/trip/$trid" : _("Not assigned yet"); ?>" name="puburl" style="border:none" size="40" readonly></td>
        </tr>
    </table><br>

<?php
if ($trid) {
      echo "<input type='button' value='" . _("Save") . "' onClick='validate(\"EDIT\")'>\n";
      echo "<input type='hidden' name='trid' value='$trid'>\n";
      echo "<input type='button' value='" . _("Delete") . "' onClick='deleteTrip()'>\n";
} else {
    echo "<input type='button' value='" . _("Add") . "' onClick='validate(\"NEW\")'";
}
?>
    <input type="button" value="<?php echo _("Cancel"); ?>" onClick="window.close()">
      </form>

    </div>

  </body>
</html>
