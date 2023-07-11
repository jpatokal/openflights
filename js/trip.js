/*
 * Create new trips and modify existing ones
 */
var URL_TRIP = "/php/trip.php";
var trid = 0;
var type = "NEW";

window.onload = function init() {
  gt = new Gettext({ domain: "messages" });
};

function xmlhttpPost(strURL, type) {
  var self = this;
  // Mozilla/Safari
  if (window.XMLHttpRequest) {
    self.xmlHttpReq = new XMLHttpRequest();
  }
  // IE
  else if (window.ActiveXObject) {
    self.xmlHttpReq = new ActiveXObject("Microsoft.XMLHTTP");
  }
  self.xmlHttpReq.open("POST", strURL, true);
  self.xmlHttpReq.setRequestHeader(
    "Content-Type",
    "application/x-www-form-urlencoded"
  );
  self.xmlHttpReq.onreadystatechange = function () {
    if (self.xmlHttpReq.readyState == 4 && strURL == URL_TRIP) {
      editTrip(self.xmlHttpReq.responseText);
    }
  };
  var query = "";
  if (strURL == URL_TRIP) {
    var form = document.forms["tripform"],
      privacy;

    for (var r = 0; r < tripform.privacy.length; r++) {
      if (tripform.privacy[r].checked) {
        privacy = tripform.privacy[r].value;
      }
    }
    query =
      "type=" +
      type +
      "&" +
      "name=" +
      encodeURIComponent(form.name.value) +
      "&" +
      "url=" +
      encodeURIComponent(form.url.value) +
      "&" +
      "privacy=" +
      encodeURIComponent(privacy);
    if (type == "EDIT" || type == "DELETE") {
      query += "&trid=" + form.trid.value;
    }
  }
  self.xmlHttpReq.send(query);
}

// Validate form
function validate(type) {
  var form = document.forms["tripform"];
  if (form.name.value == "") {
    showError("Please enter a name for this trip.");
    return;
  }

  document.getElementById("miniresultbox").innerHTML = "<i>Processing...</i>";
  xmlhttpPost(URL_TRIP, type);
}

// Delete trip?
function deleteTrip() {
  if (
    confirm(
      "Are you sure you want to delete this trip? (Flights in this trip will NOT be deleted.)"
    )
  ) {
    xmlhttpPost(URL_TRIP, "DELETE");
  } else {
    document.getElementById("miniresultbox").innerHTML =
      "<i>Deleting trip cancelled.</i>";
  }
}

// Check if trip creation/editing/deletion succeeded
function editTrip(str) {
  var code = str.split(";")[0],
    trid = str.split(";")[1],
    message = str.split(";")[2];

  // Operation successful
  if (code != "0") {
    document.getElementById("miniresultbox").innerHTML = message;
    var form = document.forms["tripform"];
    parent.opener.newTrip(code, trid, form.name.value, form.url.value);
    window.close();
  } else {
    showError(trid);
  }
}

function showError(err) {
  document.getElementById("miniresultbox").innerHTML =
    "<font color=red>" + err + "</font>";
}
