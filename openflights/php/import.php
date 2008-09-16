<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>OpenFlights: Import</title>
    <link rel="stylesheet" href="/css/style_reset.css" type="text/css">
    <link rel="stylesheet" href="/openflights.css" type="text/css">

    <script type="text/javascript" src="/js/apsearch.js"></script>
  </head>

  <body>
    <div id="contexthelp">
  
    <h1>OpenFlights: Parse imported data</h1>

<?php
include_once('simple_html_dom.php');
include_once('helper.php');

$uploaddir = '/var/www/openflights/import/';
$uploadfile = $uploaddir . basename($_FILES['userfile']['name']);

if (move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadfile)) {
  echo "<b>Upload successful. Parsing...</b><br>";
  flush();
} else {
  echo "No file uploaded, using test file.<br>";
  $uploadfile = $uploaddir . "fm.html";
}

// Parse it
$html = file_get_html($uploadfile);
if(! $html) {
  echo "Sorry, this file does not appear to be HTML.";
  exit;
}

$title = $html->find('title', 0);
if($title->plaintext != "FlightMemory - FlightData") {
  echo "Sorry, this HTML file does not appear contain FlightMemory FlightData.";
  exit;
}

// 3nd table has the data
$table = $html->find('table', 2);

print "<table style='border-spacing: 3'><tr>";
print "<th>ID</th><th>Date</th><th>Flight</th><th>Route</th><th>Miles</th><th>Time</th><th>Plane</th><th>Reg</th>";
print "<th>Seat</th><th>Class</th><th>Type</th><th>Reason</th><th>Comment</th></tr>";

// Rows with valign=top are data rows
$rows = $table->find('tr[valign=top]');
$count = 0;
foreach($rows as $row) {
  $count++;
  if($count % 2 == 1) {
    $bgcolor = "#fff";
  } else {
    $bgcolor = "#ddd";
  }

  $cols = $row->find('td[class=liste],td[class=liste_gross]');

  $id = $cols[0]->plaintext;
  $dates = explode('<br>', $cols[1]);
  $src_date = substr($dates[0], 24, -7); // Eeew...  <td class="liste"><nobr>xx...xx</nobr>
  if(strlen($src_date) == 4) {
    $src_date = "01.01." . $src_date;
  }

  $src_iata = $cols[2]->plaintext;
  $dst_iata = $cols[4]->plaintext;

  $distance = substr($cols[6]->find('<td>[align=right]', 0)->plaintext, 0, -6); // peel off trailing &nbsp;
  $distance = str_replace(',', '', $distance);
  $duration = substr($cols[6]->find('<td>[align=right]', 1)->plaintext, 0, -6);

  $flight = explode('<br>', $cols[7]);
  $airline = fm_strip_liste($flight[0]);
  $number = str_replace('</td>', '', $flight[1]);

  $planedata = explode('<br>', $cols[8]);
  $plane = fm_strip_liste($planedata[0]);
  if($planedata[1]) {
    $reg = $planedata[1];
  } else {
    $reg = "";
  }

  // <td class="liste">12A/Window<br><small>Economy<br>Passenger<br>Business</small></td>
  $seatdata = explode('<br>', $cols[9]);
  $seatdata2 = explode('/', $seatdata[0]);

  $seatnumber = fm_strip_liste($seatdata2[0]);
  $seatpos = $seatdata2[1];
  if(strlen($seatpos) < 2) {
    $seatpos = "";
  }
  $seatclass = substr($seatdata[1], 7);
  $seattype = $seatdata[2];
  $seatreason = substr($seatdata[3], 0, strpos($seatdata[3], '<'));
  
  $span = $cols[10]->find('span', 0);
  if($span) {
    $comment = trim(substr($span->title, 9));
  } else {
    $comment = "";
  }
  printf ("<tr style='background-color: %s'><td>%s</td><td>%s</td><td>%s %s</td><td>%s-%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s %s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>\n", $bgcolor, $id, $src_date, $airline, $number,
	  $src_iata, $dst_iata, $distance, $duration, $plane, $reg,
	  $seatnumber, $seatpos, $seatclass, $seattype, $seatreason, $comment);

}

print "</table>";

?>
  </body>
</html>
