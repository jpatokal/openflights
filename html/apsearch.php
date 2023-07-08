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
    <title>OpenFlights: <?php echo _("Airport search") ?></title>
    <link rel="stylesheet" href="/css/style_reset.css" type="text/css">
    <link rel="stylesheet" href="/openflights.css" type="text/css">
    <link rel="gettext" type="application/x-po" href="/locale/<?php echo $locale;?>/LC_MESSAGES/messages.po" />
    <link rel="icon" type="image/png" href="/img/icon_favicon.png"/>

    <script type="text/javascript" src="/js/utilities.js"></script>
    <script type="text/javascript" src="/js/greatcircle.js"></script>
    <script type="text/javascript" src="/js/apsearch.js"></script>
    <script type="text/javascript" src="/js/Gettext.js"></script>
  </head>

  <body>
    <div id="contexthelp">
    <span style="float: right"><?php echo _("Language") ?><br>
<?php locale_pulldown($dbh, $locale); ?>
  </span>

      <FORM name="searchform">
  <h1><?php echo "OpenFlights: " . _("Airport search") ?></h1>

  <?php echo _("Fill one or more fields below to search for matching airports.") ?>
    <table>
        <tr>
              <td><?php echo _("Airport name") ?></td>
          <td>IATA/FAA</td>
          <td>ICAO</td>
        </tr><tr>
          <td><INPUT type="text" name="airport" onFocus="setEdited()"></td>
          <td><INPUT type="text" name="iata" size="3" onFocus="setEdited()"></td>
          <td><INPUT type="text" name="icao" size="4" onFocus="setEdited()"></td>
          <td><INPUT type="text" name="apid" size="5" value="" style="border: 0px" READONLY></td>
        </tr><tr>
              <td><?php echo _("City name") ?></td>
              <td colspan=3><?php echo _("Country name") ?></td>
        </tr><tr>
          <td><INPUT type="text" name="city" onFocus="setEdited()"></td>
          <td colspan=3>
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
        </tr><tr>
          <td><?php echo _("Latitude") ?>&nbsp;(&plusmn;dd)</td>
          <td><?php echo _("Longitude") ?>&nbsp;(&plusmn;dd)</td>
          <td><?php echo _("Elevation") ?>&nbsp;(ft)</td>
          <td><?php echo _("UTC") ?><sup><a href="#help" onclick='JavaScript:help("time")'>?</a></sup></td>
          <td><?php echo _("DST") ?><sup><a href="#help" onclick='JavaScript:help("time")'>?</a></sup></td>
        </tr><tr>
          <td><INPUT type="text" name="y" size="12" onFocus="setEdited()"></td>
          <td><INPUT type="text" name="x" size="12" onFocus="setEdited()"></td>
          <td><INPUT type="text" name="elevation" size="6" onFocus="setEdited()"></td>
          <td><INPUT type="text" name="tz" size="3" onFocus="setEdited()"></td>
          <td><select name="dst" onChange="setEdited()">
          <option value="U" selected><?php echo _("Unknown") ?></option>
          <option value="E"><?php echo _("European") ?></option>
          <option value="A"><?php echo _("US/Canada") ?></option>
          <option value="S"><?php echo _("S. American") ?></option>
          <option value="O"><?php echo _("Australia") ?></option>
          <option value="Z"><?php echo _("New Zealand") ?></option>
          <option value="N"><?php echo _("None") ?></option>
        </select>
          </td>
        </tr><tr>
          <td><?php echo _("Database") ?><sup><a href="#help" onclick='JavaScript:help("database")'>?</a></sup></td>
        </tr><tr>
          <td>
        <select name="db">
          <option value="airports" selected>OpenFlights</option>
          <option value="airports_oa">OurAirports</option>
          <option value="airports_dafif">DAFIF (Oct 2006)</option>
        </select>&nbsp;&nbsp;
          </td>
          <td colspan=3>
        <?php echo _("Show only major (IATA) airports?") ?> <input type="checkbox" name="iatafilter" value="yes" checked>
          </td>
        </tr>
    </table>
    <br>

    <table width="95%">
        <tr>
          <td>
        <INPUT type="button" value='<?php echo _("Search") ?>' onClick="doSearch(0)">
        <INPUT type="button" value='<?php echo _("Clear") ?>' onClick="clearSearch()">
        <INPUT type="button" value='<?php echo _("Cancel") ?>' onClick="window.close()">
        </td><td style="text-align: right">
<?php
if (!$logged_in) {
    echo "<small>" . _("Please log in to enable editing.") . "</small><br>";
}
?>
         <INPUT id="b_add" type="button" title='<?php echo _("Record the current data as a new airport.") ?>' value='<?php echo _("Add as new") ?>' DISABLED onClick="doRecord()">
        <INPUT id="b_edit" type="button" title='<?php echo _("Record changes to this airport.") ?>' value='<?php echo _("Save changes") ?>' DISABLED onClick="doRecord()" style="display: none">
        </td>
        </tr>
    </table>

      </FORM>

    </div>

    <div id="miniresultbox">
    </div>

  </body>
</html>
