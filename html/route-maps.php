<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>OpenFlights: Route maps</title>
    <link rel="stylesheet" href="/css/style_reset.css" type="text/css">
    <link rel="stylesheet" href="/openflights.css" type="text/css">
    <link rel="icon" type="image/png" href="/img/icon_favicon.png"/>
    <?php include("./html/analytics.html") ?>
  </head>

  <body>
    <div id="mainContainer">
      <div id="sideBarContentWrapper">

	<div id="contentContainer">
	  <div id="nonmap">

   This is an automatically generated list of all airline and airport
   route maps on OpenFlights.  See <a href="/data.html">Data</a> for
   data downloads and more information.

<?php
include '../php/db_pdo.php';
include '../php/helper.php';

print "<h2>Airline route maps (by IATA code)</h2>";

$sql = "SELECT * FROM airlines WHERE alid IN (SELECT DISTINCT alid FROM routes) ORDER BY iata";
foreach ($dbh->query($sql) as $row) {
    $iata = $row['iata'];
    if (ctype_alnum($iata)) {
        $label = format_airline($row);
        print "<a href='/airline/$iata'>$label</a>, ";
    }
}

print "<h2>Airport route maps (by IATA code)</h2>";

$sql = "SELECT * FROM airports WHERE apid IN (SELECT DISTINCT src_apid FROM routes) ORDER BY iata";
foreach ($dbh->query($sql) as $row) {
    $iata = $row['iata'];
    if (ctype_alnum($iata)) {
        $label = format_airport($row);
        print "<a href='/airport/$iata'>$label</a>, ";
    }
}

print "<h2>Airport route maps (by ICAO code)</h2>";

$sql = "SELECT * FROM airports WHERE apid IN (SELECT DISTINCT src_apid FROM routes) ORDER BY iata";
foreach ($dbh->query($sql) as $row) {
    $icao = $row['icao'];
    if (ctype_alnum($icao)) {
        $label = format_airport($row);
        print "<a href='/airport/$icao'>$label</a>, ";
    }
}

?>
	  </div>
	</div>
	<div id="sideBar">
<?php
        include ("../sidebar.html");
        include ("ad-sidebar.html");
?>
	</div>

      </div> <!-- end sidebarwrapper -->
    </div> <!-- end mainContainer -->

  </body>
</html>
