<?php

header("Content-type: text/html");
require_once "../php/locale.php";
require_once "../php/db_pdo.php";
require_once '../php/helper.php';

$uid = $_SESSION["uid"] ?? null;
$logged_in = $uid && !empty($uid);
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title><?php echo sprintf(_('OpenFlights: %s'), _('Airline search')); ?></title>
    <link rel="stylesheet" href="/css/style_reset.min.css" type="text/css">
    <link rel="stylesheet" href="/openflights.css" type="text/css">
    <link rel="gettext" type="application/x-po" href="/locale/<?php echo $locale; ?>/LC_MESSAGES/messages.po" />
    <link rel="icon" type="image/png" href="/img/icon_favicon.png"/>
    <script type="text/javascript" src="/js/functions.js"></script>
    <script type="text/javascript" src="/js/Gettext.min.js"></script>
    <script type="text/javascript" src="/js/alsearch.js"></script>
  </head>

  <body>
    <div id="contexthelp">
    <span style="float: right"><?php echo _("Language"); ?><br>
<?php locale_pulldown($dbh, $locale); ?>
  </span>

  <form name="searchform">
    <h1><?php echo _("Airline, railway, road transport and shipping search"); ?></h1>
    <?php echo _("Fill one or more fields below to search for matching airlines and other transport operators."); ?>
    <table>
        <tr>
          <td><label for="name"><?php echo _("Name"); ?></label></td>
          <td title="<?php echo _('International Air Transport Association'); ?>">
            <label for="iata"><?php echo _('IATA'); ?></label>
          </td>
          <td title="<?php echo _('International Civil Aviation Organization'); ?>">
            <label for="icao"><?php echo _('ICAO'); ?></label>
          </td>
          <td><label for="mode"><?php echo _("Mode"); ?></label></td>
          <td><label for="active"><?php echo _("Active?"); ?></label></td>
          <td><label for="alid"><?php echo _("Airline ID"); ?></label></td>
        </tr>
        <tr>
          <td><input type="text" id="name" name="name"></td>
          <td><input type="text" id="iata" name="iata" size="3"></td>
          <td><input type="text" id="icao" name="icao" size="4"></td>
          <td>
            <select id="mode" name="mode" onChange="JavaScript:changeMode()">
              <option value="F" selected><?php echo _("Airline"); ?></option>
              <option value="T"><?php echo _("Railway"); ?></option>
              <option value="R"><?php echo _("Road transport"); ?></option>
              <option value="S"><?php echo _("Shipping"); ?></option>
            </select>
          </td>
          <td>
            <select id="active" name="active">
              <option value="">-</option>
              <option value="Y"><?php echo _("Yes"); ?></option>
              <option value="N"><?php echo _("No"); ?></option>
            </select>
          </td>
          <td><input type="text" id="alid" name="alid" size="5" value="" style="border: 0px" READONLY></td>
        </tr>
        <tr>
          <td><label for="alias"><?php echo _("Alternative name"); ?></label></td>
          <td colspan=4><label for="country"><?php echo _("Country"); ?></label></td>
        </tr>
        <tr>
          <td><input type="text" id="alias" name="alias"></td>
          <td colspan=4>
            <select id="country" name="country">
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
          <td><label for="callsign"><?php echo _("Callsign"); ?></label></td>
        </tr>
        <tr>
          <td><input type="text" id="callsign" name="callsign"></td>
          <td colspan=3>
            <label>
              <input type="checkbox" id="iatafilter" name="iatafilter" value="yes" checked><?php
                echo _("Show only major (IATA) airlines?"); ?>
            </label>
          </td>
        </tr>
    </table><br>

    <table width="95%">
      <tr>
        <td>
          <input type='button' value='<?php echo _("Search"); ?>' onClick='doSearch(0)'>
          <input type='button' value='<?php echo _("Clear"); ?>' onClick='clearSearch()'>
          <input type='button' value='<?php echo _("Cancel"); ?>' onClick='window.close()'>
        </td>
        <td style="text-align: right">
<?php
if (!$logged_in) {
    echo "<small>" . _("Please log in to enable editing.") . "</small><br>";
}
?>
          <input id="b_add" type="button" title='<?php
            echo _("Record the current data as a new airline."); ?>' value='<?php echo _("Add as new"); ?>' <?php
            condOut(!$logged_in, "DISABLED"); ?> onClick="doRecord()">
          <input id="b_edit" type="button" title='<?php
            echo _("Record changes to this airline."); ?>' value='<?php echo _("Save changes"); ?>' <?php
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
