<?php

header("Content-type: text/html");
require_once "../php/locale.php";
require_once "../php/db_pdo.php";

$uid = $_SESSION["uid"] ?? null;
$logged_in = $uid && !empty($uid);
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>OpenFlights: <?php echo _("Airline search"); ?></title>
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
          <td><?php echo _("Name"); ?></td>
          <td title="<?php echo _('International Air Transport Association'); ?>"><?php echo _('IATA'); ?></td>
          <td title="<?php echo _('International Civil Aviation Organization'); ?>"><?php echo _('ICAO'); ?></td>
          <td><?php echo _("Mode"); ?></td>
          <td><?php echo _("Active?"); ?></td>
        </tr><tr>
          <td><input type="text" name="name"></td>
          <td><input type="text" name="iata" size="3"></td>
          <td><input type="text" name="icao" size="4"></td>
          <td>
            <select name="mode" onChange="JavaScript:changeMode()">
              <option value="F" selected><?php echo _("Airline"); ?></option>
              <option value="T"><?php echo _("Railway"); ?></option>
              <option value="R"><?php echo _("Road transport"); ?></option>
              <option value="S"><?php echo _("Shipping"); ?></option>
            </select>
          </td>
          <td>
            <select name="active">
              <option value="">-</option>
              <option value="Y"><?php echo _("Yes"); ?></option>
              <option value="N"><?php echo _("No"); ?></option>
            </select>
          </td>
          <td><input type="text" name="alid" size="5" value="" style="border: 0px" READONLY></td>
        </tr>
        <tr>
          <td><?php echo _("Alternative name"); ?></td>
          <td colspan=4><?php echo _("Country"); ?></td>
        </tr>
        <tr>
          <td><input type="text" name="alias"></td>
          <td colspan=4>
                <select name="country">
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
          <td><?php echo _("Callsign"); ?></td>
        </tr>
        <tr>
          <td><input type="text" name="callsign"></td>
          <td colspan=3>
    <nobr><?php echo _("Show only major (IATA) airlines?"); ?></nobr><input type="checkbox" name="iatafilter" value="yes" checked>
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
         <input id="b_add" type="button" title='<?php echo _("Record the current data as a new airline."); ?>' value='<?php echo _("Add as new"); ?>' <?php
            condOut(!$logged_in, "DISABLED");
?> onClick="doRecord()">
        <input id="b_edit" type="button" title='<?php echo _("Record changes to this airline."); ?>' value='<?php echo _("Save changes"); ?>' <?php
        condOut(!$logged_in, "DISABLED");
?> onClick="doRecord()" style="display: none">
        </td>
      </tr>
    </table>

  </form>

    </div>

    <div id="miniresultbox">
    </div>

  </body>
</html>
