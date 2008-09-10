/*
 * Create new trips and modify existing ones
 */
var URL_TRIP = "/php/trip.php";
var trid = 0;
var type = "NEW";

window.onload = function init(){
  // ...?trid=X
  var urlbits = window.location.href.split('?');
  if(urlbits[1]) {
    type = "EDIT";
    trid = urlbits[1].split('=')[1];
    document.getElementById("title").innerHTML = "<h1>Edit trip</h1>";
    document.getElementById("miniresultbox").innerHTML = "<i>Loading...</i>";
    xmlhttpPost(URL_TRIP, "LOAD");
  } else {
    document.getElementById("title").innerHTML = "<h1>Add new trip</h1>";
    document.getElementById("miniresultbox").innerHTML = "<i>Enter your new trip's details here.</i>";
  }
}

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

      if(strURL == URL_TRIP) {
	if(type == "LOAD") {
	  loadTrip(self.xmlHttpReq.responseText);
	} else {
	  editTrip(self.xmlHttpReq.responseText);
	}
      }
    }
  }
  var query = "";
  if(strURL == URL_TRIP) {
    var form = document.forms['tripform'];
    var privacy;

    for (r=0; r < tripform.privacy.length; r++){
      if (tripform.privacy[r].checked) {
	privacy = tripform.privacy[r].value;
      }
    }
    query = 'type=' + type + '&' +
      'name=' + escape(form.name.value) + '&' +
      'url=' + escape(form.url.value) + '&' +
      'privacy=' + escape(privacy);
    if(type == 'EDIT' || type == 'LOAD') {
      query += '&trid=' + trid;
    }
  }
  self.xmlHttpReq.send(query);
}

// Validate form
function validate() {
  var form = document.forms['tripform'];
  if(form.name.value == "") {
    showError("Please enter a name for this trip.");
    return;
  }

  document.getElementById("miniresultbox").innerHTML = "<i>Processing...</i>";
  xmlhttpPost(URL_TRIP, type);
}

// Load up trip data
function loadTrip(str) {
  var code = str.split(";")[0];
  if(code == "1") {
    var name = str.split(";")[2];
    var url = str.split(";")[3];
    var privacy = str.split(";")[4];

    var form = document.forms['tripform'];
    tripform.name.value = name;
    tripform.url.value = url;
    tripform.puburl.value = "http://openflights.org/trip/" + trid;
    for (r=0; r < tripform.privacy.length; r++){
      if (tripform.privacy[r].value == privacy) {
	tripform.privacy[r].checked = true;
      } else {
	tripform.privacy[r].checked = false;
      }
    }
    document.getElementById("miniresultbox").innerHTML = "<i>Edit your trip details here.</i>";
  } else {
    showError(str.split(";")[1]);
  }
}

// Check if trip creation/editing succeeded
function editTrip(str) {
  var code = str.split(";")[0];
  var trid = str.split(";")[1];
  var message = str.split(";")[2];
  // Operation successful
  if(code != "0") {
    document.getElementById("miniresultbox").innerHTML = message;
    var form = document.forms['tripform'];
    parent.opener.newTrip(code, trid, form.name.value, form.url.value);
    window.close();
  } else {
    showError(message);
  }
}

function showError(err) {
  document.getElementById("miniresultbox").innerHTML = "<font color=red>" + err + "</font>";
}
