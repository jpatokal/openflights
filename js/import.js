/*
 * Import trips into database
 */
function xmlhttpPost(strURL, type) {
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

      if(strURL == URL_IMPORT) {
	document.getElementById("miniresultbox").innerHTML = self.xmlHttpReq.responseText;
      }
    }
  }
  var query = "";
  if(strURL == URL_IMPORT) {
    var form = document.forms['importform'];
    query = 'rawdata=' + escape(form.rawdata.value);
  }
  self.xmlHttpReq.send(query);
}

// Validate form
function doImport() {
  var rawdata = document.forms['importform'].rawdata.value;
  var flights = rawdata.split(/EDIT\nDEL\n?/);
  var output = "";
  for(f in flights) {
    // Skip last line (it's empty)
    if(f == flights.length - 1) break;
    output += "{" + flights[f] + "}<br>";
    var lines = flights[f].split('\n');
    var src_date = lines[1].split('\t')[1];
    var src_time = lines[2];
    var dst_time = lines[3].split('\t')[0];
    var src_iata = lines[3].split('\t')[1];
    var dst_iata = lines[6].split('\t')[1];
    var distance = lines[10].split('\t')[0];
    var duration = lines[11].split('\t')[0];
    var airline = lines[12].split('\t')[1];
    var number = lines[13].split('\t')[0];
    var plane = lines[13].split('\t')[1];
    if(lines[13].split('\t').length < 3) {
      var reg = lines[14];
      var seat = lines[15].split('\t')[1];
      offset = 15;
    } else {
      var seat = lines[13].split('\t')[2];
      var reg = "";
      offset = 13;
    }
    var comment = lines[offset].split('\t')[0] + lines[offset+3].split('\t')[1];
    var seattype = lines[15].split('\t')[2];
    var myClass = lines[16];
    var type = lines[17];
    var reason = lines[18].split('\t')[0];
    output += "SRC_IATA " + src_iata + " SRC_DATE " + src_date + " SRC_TIME " + src_time + "<br>" +
      "DST_IATA " + dst_iata + " DST_TIME " + dst_time + "<br>" +
      "DIST " + distance + " DURA " + duration + " AIRLINE " + airline + " NUMBER " + number +
      " PLANE " + plane + " REG " + reg + "<br>" +
      " SEAT " + seat + " SEATTYPE " + seattype + " CLASS " + myClass + " TYPE " + type +
      " REASON" + reason + " COM " + comment + "<br><br>";
  }
  document.getElementById("miniresultbox").innerHTML = output;  
}

function showError(err) {
  document.getElementById("miniresultbox").innerHTML = "<font color=red>" + err + "</font>";
}
