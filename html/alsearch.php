<?php
header("Content-type: text/html");
require_once("../php/locale.php");
require_once("../php/db.php");
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
<title>OpenFlights:  <?php echo _("Airline search") ?></title>
    <link rel="stylesheet" href="/css/style_reset.css" type="text/css">
    <link rel="stylesheet" href="/openflights.css" type="text/css">

    <script type="text/javascript" src="/js/utilities.js"></script>
    <script type="text/javascript" src="/js/greatcircle.js"></script>
    <script type="text/javascript" src="/js/Gettext.js"></script>
    <script type="text/javascript" src="/js/alsearch.js"></script>
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
	<h1><?php echo _("Airline, railway, road transport and shipping search") ?></h1>
	<?php echo _("Fill one or more fields below to search for matching airlines and other transport operators.") ?>
	<table>
	    <tr>
	      <td><?php echo _("Name") ?></td>
	      <td>IATA</td>
	      <td>ICAO</td>
	      <td><?php echo _("Mode") ?></td>
	    </tr><tr>
	      <td><INPUT type="text" name="name"></td>
	      <td><INPUT type="text" name="iata" size="3"></td>
	      <td><INPUT type="text" name="icao" size="4"></td>
	      <td>
		<select name="mode" onChange="JavaScript:changeMode()">
		  <option value="F" selected><?php echo _("Airline") ?></option>
		  <option value="T"><?php echo _("Railway") ?></option>
		  <option value="R"><?php echo _("Road transport") ?></option>
		  <option value="S"><?php echo _("Shipping") ?></option>
	      </td>
	    </tr><tr>
	      <td><?php echo _("Alternative name") ?></td>
	      <td colspan=3><?php echo _("Country") ?></td>
	    </tr><tr>
	      <td><INPUT type="text" name="alias"></td>
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
	      <td><?php echo _("Callsign") ?></td>
	    </tr><tr>
	      <td><INPUT type="text" name="callsign"></td>
	      </td>
	      <td colspan=3>
		&nbsp;<?php echo _("Show only major (IATA) airlines?") ?><input type="checkbox" name="iatafilter" value="yes" checked>
	      </td>
	    </tr>
	</table><br>

	<table width="95%">
	    <tr>
	      <td>
		<INPUT type='button' value='<?php echo _("Search") ?>' onClick='doSearch(0)'>
		<INPUT type='button' value='<?php echo _("Clear") ?>' onClick='clearSearch()'>
		<INPUT type='button' value='<?php echo _("Cancel") ?>' onClick='window.close()'>
		</td><td style="text-align: right">
		<INPUT type='button' title='<?php echo _("Record the current data as a new airline.") ?>' value='<?php echo _("Add as new") ?>' onClick='doRecord()'>
		</td>
	    </tr>
	</table>

      </FORM>

    </div>

    <div id="miniresultbox">
    </div>

  </body>
</html>
