<?php
session_start();
$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);
$uid = 1;
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>OpenFlights input</title>

<script language="Javascript">
function xmlhttpPost(strURL) {
    var xmlHttpReq = false;
    var self = this;
    // Mozilla/Safari
    if (window.XMLHttpRequest) {
        self.xmlHttpReq = new XMLHttpRequest();
    }
    // IE
    else if (window.ActiveXObject) {
        self.xmlHttpReq = new ActiveXObject("Microsoft.XMLHTTP");
    }
    self.xmlHttpReq.open('POST', strURL, true);
    self.xmlHttpReq.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    self.xmlHttpReq.onreadystatechange = function() {
        if (self.xmlHttpReq.readyState == 4) {
            updatepage(self.xmlHttpReq.responseText);
        }
    }
    self.xmlHttpReq.send(getquerystring());
}

function getquerystring() {
    var form = document.forms['f1'];
    qstr =
      'src_time=' + escape(form.src_time.value) + '&' +
      'src_iata=' + escape(form.src_iata.value) + '&' +
      'src_ap=' + escape(form.src_ap.value) + '&' +
      'dst_iata=' + escape(form.dst_iata.value) + '&' +
      'dst_ap=' + escape(form.dst_ap.value) + '&' +
      'number=' + escape(form.number.value) + '&' +
      'airline=' + escape(form.airline.value) + '&' +
      'plane=' + escape(form.plane.value) + '&' +
      'newplane=' + escape(form.newplane.value);
    return qstr;
}

function updatepage(str){
    document.getElementById("result").innerHTML = str;
}
</script>

  </head>

  <body onload="init()">
    <h1>OpenFlights input</h1>
<p>All fields except those in <font color="red">red</font> are optional.</p>

<form id='f1'>
<table>
<tr>
  <td><font color="red">Date</font></td>
  <td><input type="text" name="src_time" size="40"
<?php
  print 'value="' . date("Y-m-d") . '"/>'
?>
  </td>
</tr><tr>
  <td><font color="red">Source</font></td>
  <td><select name="src_ap" size="1">
<?php
$sql = "SELECT DISTINCT a.name,a.iata,a.city,a.country FROM flights AS f,airports AS a WHERE uid=" . $uid . " AND (a.apid=f.src_apid OR a.apid=f.dst_apid) ORDER BY a.name";
$result = mysql_query($sql, $db);
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  $str = sprintf("<option value=\"%s\">%s, %s, %s (%s)</option><br>", $row["iata"], $row["name"], $row["city"], $row["country"], $row["iata"]);
  print $str;
  $copy = $copy . $str;
}
?>
  </select></td>
  <td><b>or</b> enter IATA code: <input type="text" name="src_iata" size="3" value=""/></td>
</tr><tr>
  <td><font color="red">Destination</font></td>
  <td><select name="dst_ap" size="1">
<?php
  print $copy;
?>
  </select></td>
  <td><b>or</b> enter IATA code: <input type="text" name="dst_iata" size="3" value=""/></td>
</tr><tr>
  <td><font color="red">Flight number <i>or</i><br>airline code (IATA)</font></td>
  <td><input type="text" name="number" size="10" value=""/></td>
</tr><tr>
  <td>Airline</td>
  <td><select name="airline" size="1">
      <option value="">-</option>
<?php
$sql = "SELECT DISTINCT a.name,a.iata FROM flights AS f,airlines AS a WHERE uid=" . $uid . " AND a.alid=f.alid ORDER BY a.name";
$result = mysql_query($sql, $db);
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  printf ("<option value=\"%s\">%s (%s)</option><br>", $row["iata"], $row["name"], $row["iata"]);
}
?>
    </select></td>
</tr><tr>
  <td>Plane</td>
  <td><select name="plane" size="1">
      <option value="">-</option>
<?php
$sql = "SELECT DISTINCT p.plid, p.name FROM flights AS f,planes AS p WHERE uid=" . $uid . " AND p.plid=f.plid ORDER BY p.name";
$result = mysql_query($sql, $db);
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  printf ("<option value=\"%s\">%s</option><br>", $row["plid"], $row["name"]);
}
?>
</select></td>
  <td><b>or</b> enter new: <input type="text" name="newplane" size="20" value=""/>
</tr>
</table>

<input value="Submit" type="button" onclick='JavaScript:xmlhttpPost("/php/input.php")'>

<div id="result"></div>

</form>
  </body>
</html>
