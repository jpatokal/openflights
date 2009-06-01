<?php
header("Content-type: text/html");
require_once("../php/locale.php");
require_once("../php/db.php");
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>OpenFlights: <?php echo _("Airport search") ?></title>
    <link rel="stylesheet" href="/css/style_reset.css" type="text/css">
    <link rel="stylesheet" href="/openflights.css" type="text/css">
    <link rel="gettext" type="application/x-po" href="/locale/<?php echo $locale?>/LC_MESSAGES/messages.po" />

    <script type="text/javascript" src="/js/utilities.js"></script>
    <script type="text/javascript" src="/js/apsearch.js"></script>
    <script type="text/javascript" src="/js/Gettext.js"></script>
  </head>

  <body>
    <div id="contexthelp">
    <span style="float: right"><?php echo _("Language") ?><br>
    <select id="locale" onChange="JavaScript:changeLocale()">
<?php
  $sql = "SELECT * FROM locales ORDER BY name ASC";
  $result = mysql_query($sql, $db);
  while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
    $selected = ($row["locale"] . ".utf8" == $locale ? "SELECTED" : "");
    printf("<option value='%s' %s>%s (%s)</option>\n", $row["locale"], $selected, $row["name"], $row["locale"]);
  }
?>
    </select>
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
	      <td><INPUT type="text" name="airport"></td>
	      <td><INPUT type="text" name="iata" size="3"></td>
	      <td><INPUT type="text" name="icao" size="4"></td>
	      <td><INPUT type="text" name="apid" size="5" value="" style="border: 0px" READONLY></td>
	    </tr><tr>
              <td><?php echo _("City name") ?></td>
              <td colspan=3><?php echo _("Country name") ?></td>
	    </tr><tr>
	      <td><INPUT type="text" name="city"></td>
	      <td colspan=3>
                <select name="country">
                  <option value="">ALL</option>
<?php
  $sql = "SELECT code, name FROM countries ORDER BY name";
  $result = mysql_query($sql, $db);
  while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
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
	      <td><INPUT type="text" name="y" size="12"></td>
	      <td><INPUT type="text" name="x" size="12"></td>
	      <td><INPUT type="text" name="elevation" size="6"></td>
	      <td><INPUT type="text" name="tz" size="3"></td>
	      <td><select name="dst">
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
		<INPUT id="b_add" type="button" title='<?php echo _("Record the current data as a new airport.") ?>' value='<?php echo _("Add as new") ?>' onClick="doRecord()">
		<INPUT id="b_edit" type="button" title='<?php echo _("Record changes to this airport.") ?>' value='<?php echo _("Save changes") ?>' onClick="doRecord()" style="display: none">
		</td>
	    </tr>
	</table>

      </FORM>

    </div>

    <div id="miniresultbox">
    </div>

  </body>
</html>
