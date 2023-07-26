<?php
header("Content-type: text/html");
require_once "../php/locale.php";
require_once "../php/db_pdo.php";

$uid = $_SESSION["uid"];
$logged_in = $uid && !empty($uid);
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title><?php echo sprintf(_('OpenFlights: %s'), _('Airport search')); ?></title>
    <link rel="stylesheet" href="/css/style_reset.min.css" type="text/css">
    <link rel="stylesheet" href="/openflights.css" type="text/css">
    <link rel="gettext" type="application/x-po" href="/locale/<?php echo $locale; ?>/LC_MESSAGES/messages.po" />
    <link rel="icon" type="image/png" href="/img/icon_favicon.png"/>
    <script type="text/javascript" src="/js/functions.js"></script>
    <script type="text/javascript" src="/js/apsearch.js"></script>
    <script type="text/javascript" src="/js/Gettext.min.js"></script>
  </head>

  <body>
    <div id="contexthelp">
    <span style="float: right"><?php echo _("Language"); ?><br>
<?php locale_pulldown($dbh, $locale); ?>
    </span>

      <form name="searchform">
  <h1><?php echo sprintf(_('OpenFlights: %s'), _('Airport search')); ?></h1>

  <?php echo _("Fill one or more fields below to search for matching airports."); ?>
    <table>
        <tr>
          <td><label for="airport"><?php echo _("Airport name"); ?></label></td>
          <td title="<?php
            echo _('International Air Transport Association') . '/' . _('Federal Aviation Administration');
            ?>"><label for="iata"><?php echo _('IATA'); ?>/<?php echo _('FAA'); ?></label>
          </td>
          <td title="<?php echo _('International Civil Aviation Organization');
            ?>"><label for="icao"><?php echo _('ICAO'); ?></label>
          </td>
          <td><label for="apid"><?php echo _("Airport ID"); ?></label></td>
        </tr>
        <tr>
          <td><input type="text" id="airport" name="airport" onFocus="setEdited()"></td>
          <td><input type="text" id="iata" name="iata" size="3" onFocus="setEdited()"></td>
          <td><input type="text" id="icao" name="icao" size="4" onFocus="setEdited()"></td>
          <td><input type="text" id="apid" name="apid" size="5" value="" style="border: 0px" READONLY></td>
        </tr>
        <tr>
          <td><label for="city"><?php echo _("City name"); ?></label></td>
          <td colspan=3><label for="country"><?php echo _("Country name"); ?></label></td>
        </tr>
        <tr>
          <td><input type="text" id="city" name="city" onFocus="setEdited()"></td>
          <td colspan=3>
            <select id="country" name="country" onFocus="setEdited()">
              <option value="">ALL</option>
<?php
$sql = "SELECT iso_code AS code, name FROM countries ORDER BY name";
foreach ($dbh->query($sql) as $row) {
    printf("<option value='%s'>%s</option>\n", $row["code"], $row["name"]);
}
?>
            </select>
          </td>
        </tr>
        <tr>
          <td><label for="y"><?php echo _("Latitude") . "&nbsp;" . _("(&plusmn;dd)"); ?></td>
          <td><label for="x"><?php echo _("Longitude") . "&nbsp;" . _("(&plusmn;dd)"); ?></label></td>
          <td><label for="elevation"><?php echo _("Elevation") . "&nbsp;" . _("(ft)"); ?></label></td>
          <td>
            <label for="tz"><?php echo _("UTC");
            ?></label><sup><a href="#help" onclick='JavaScript:help("time")'>?</a></sup>
          </td>
          <td>
            <label for="dst"><?php echo _("DST");
            ?></label><sup><a href="#help" onclick='JavaScript:help("time")'>?</a></sup>
          </td>
        </tr>
        <tr>
          <td><input type="text" id="y" name="y" size="12" onFocus="setEdited()"></td>
          <td><input type="text" id="x" name="x" size="12" onFocus="setEdited()"></td>
          <td><input type="text" id="elevation" name="elevation" size="6" onFocus="setEdited()"></td>
          <td><input type="text" id="tz" name="tz" size="3" onFocus="setEdited()"></td>
          <td>
            <select id="dst" name="dst" onChange="setEdited()">
              <option value="U" selected><?php echo _("Unknown"); ?></option>
              <option value="E"><?php echo _("European"); ?></option>
              <option value="A"><?php echo _("US/Canada"); ?></option>
              <option value="S"><?php echo _("S. American"); ?></option>
              <option value="O"><?php echo _("Australia"); ?></option>
              <option value="Z"><?php echo _("New Zealand"); ?></option>
              <option value="N"><?php echo _("None"); ?></option>
            </select>
          </td>
        </tr>
        <tr>
          <td><label for="db"><?php echo _("Database");
            ?></label><sup><a href="#help" onclick='JavaScript:help("database")'>?</a></sup>
          </td>
        </tr>
        <tr>
          <td>
            <select id="db" name="db">
              <option value="airports" selected>OpenFlights</option>
              <option value="airports_oa">OurAirports</option>
              <option value="airports_dafif">DAFIF (Oct 2006)</option>
            </select>
          </td>
          <td colspan=3>
            <label>
              <input type="checkbox" id="iatafilter" name="iatafilter" value="yes" checked><?php
                echo _("Show only major (IATA) airports?"); ?>
            </label>
          </td>
        </tr>
    </table>
    <br>

    <table width="95%">
        <tr>
            <td>
              <input type="button" value='<?php echo _("Search"); ?>' onClick="doSearch(0)">
              <input type="button" value='<?php echo _("Clear"); ?>' onClick="clearSearch()">
              <input type="button" value='<?php echo _("Cancel"); ?>' onClick="window.close()">
            </td>
            <td style="text-align: right">
<?php
if (!$logged_in) {
    echo "<small>" . _("Please log in to enable editing.") . "</small><br>";
}
?>
              <input id="b_add" type="button" title='<?php
                echo _("Record the current data as a new airport."); ?>' value='<?php echo _("Add as new"); ?>' <?php
                condOut(!$logged_in, "DISABLED"); ?> onClick="doRecord()"">
              <input id="b_edit" type="button" title='<?php
                echo _("Record changes to this airport."); ?>' value='<?php echo _("Save changes"); ?>' <?php
                condOut(!$logged_in, "DISABLED"); ?> onClick="doRecord()" style="display: none">
            </td>
        </tr>
    </table>

      </form>

    </div>

    <div id="miniresultbox">
    </div>

  </body>
</html>
