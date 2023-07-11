/*
 * Create new user accounts and modify existing ones
 */
var URL_SETTINGS = "/php/settings.php";

var privacyList = ["N", "Y", "O"];

var gt;

window.onload = function init() {
  gt = new Gettext({ domain: "messages" });
  if (window.location.href.indexOf("settings") != -1) {
    var form = document.forms["signupform"],
      elite = form.elite.value;
    document.getElementById("eliteicon").innerHTML = getEliteIcon(
      elite,
      form.validity.value
    );
    if (elite == "G" || elite == "P") {
      signupform.guestpw.disabled = false;
      for (var r = 0; r < signupform.startpane.length; r++) {
        signupform.startpane[r].disabled = false;
      }
    }
  } else {
    // TODO document.forms['signupform'].username.value = parent.opener.document.forms['login'].name.value;
  }
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
    if (self.xmlHttpReq.readyState == 4 && strURL == URL_SETTINGS) {
      signup(self.xmlHttpReq.responseText);
    }
  };
  var query = "";
  if (strURL == URL_SETTINGS) {
    var form = document.forms["signupform"],
      privacy,
      editor,
      units;

    for (var r = 0; r < signupform.privacy.length; r++) {
      if (signupform.privacy[r].checked) {
        privacy = signupform.privacy[r].value;
      }
    }
    for (r = 0; r < signupform.editor.length; r++) {
      if (signupform.editor[r].checked) {
        editor = signupform.editor[r].value;
      }
    }
    for (r = 0; r < signupform.units.length; r++) {
      if (signupform.units[r].checked) {
        units = signupform.units[r].value;
      }
    }
    query =
      "type=" +
      type +
      "&" +
      "pw=" +
      encodeURIComponent(
        MD5(form.pw1.value + form.username.value.toLowerCase())
      ) +
      "&" +
      "email=" +
      encodeURIComponent(form.email.value) +
      "&" +
      "privacy=" +
      encodeURIComponent(privacy) +
      "&" +
      "editor=" +
      encodeURIComponent(editor) +
      "&" +
      "units=" +
      encodeURIComponent(units) +
      "&" +
      "locale=" +
      encodeURIComponent(form.locale.value);
    switch (type) {
      case "NEW":
        query += "&name=" + encodeURIComponent(form.username.value);
        document.getElementById("miniresultbox").innerHTML =
          "<I>" + gt.gettext("Creating account...") + "</I>";
        break;

      case "EDIT":
        var startpane;
        for (r = 0; r < signupform.startpane.length; r++) {
          if (signupform.startpane[r].checked) {
            startpane = signupform.startpane[r].value;
          }
        }
        if (form.oldpw.value != "") {
          query +=
            "&oldpw=" +
            encodeURIComponent(
              MD5(form.oldpw.value + form.username.value.toLowerCase())
            );
          // Legacy password for case-sensitive days of yore
          query +=
            "&oldlpw=" +
            encodeURIComponent(MD5(form.oldpw.value + form.username.value));
        }
        if (form.guestpw.value != "") {
          query +=
            "&guestpw=" +
            encodeURIComponent(
              MD5(form.guestpw.value + form.username.value.toLowerCase())
            );
        }
        query += "&startpane=" + encodeURIComponent(startpane);
        document.getElementById("miniresultbox").innerHTML =
          "<I>" + gt.gettext("Saving changes...") + "</I>";
        break;

      case "RESET":
      case "LOAD":
        // do nothing
        break;
    }
  }
  self.xmlHttpReq.send(query);
}

// Validate form
function validate(type) {
  var form = document.forms["signupform"],
    pw1 = form.pw1.value,
    pw2 = form.pw2.value,
    email = form.email.value;

  if (
    type == "RESET" &&
    !confirm(
      gt.gettext(
        "This will PERMANENTLY delete ALL YOUR FLIGHTS. Have you exported a backup copy, and are you sure you want to do this?"
      )
    )
  ) {
    document.getElementById("miniresultbox").innerHTML =
      "<i>" + gt.gettext("Deletion cancelled.") + "</i>";
    return;
  }

  if (type == "NEW") {
    if (form.username.value == "") {
      showError(gt.gettext("Please enter a username."));
      form.username.focus();
      return;
    }
    if (pw1 == "") {
      showError(gt.gettext("Please enter a password."));
      form.pw1.focus();
      return;
    }
  }
  if (type == "EDIT") {
    var oldpw = form.oldpw.value;
    if (pw1 != "" && oldpw == "") {
      showError(
        gt.gettext(
          "Please enter your current password if you wish to change to a new password."
        )
      );
      form.oldpw.focus();
      return;
    }
    if (pw1 == "" && oldpw != "") {
      showError(
        gt.gettext(
          "Please enter a new password, or clear current password if you do not wish to change it."
        )
      );
      form.pw1.focus();
      return;
    }
  }

  if (pw1 != pw2) {
    showError(gt.gettext("Your passwords don't match, please try again."));
    form.pw1.focus();
    return;
  }

  if (
    email != "" &&
    !/^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,6})+$/.test(email)
  ) {
    showError(
      gt.gettext('Invalid e-mail address, it should be "user@example.domain"')
    );
    form.email.focus();
    return;
  }

  document.getElementById("miniresultbox").innerHTML =
    "<i>" + gt.gettext("Processing...") + "</i>";
  xmlhttpPost(URL_SETTINGS, type);
}

//
// Check if user creation succeeded
//
function signup(str) {
  var code = str.split(";")[0],
    message = str.split(";")[1];

  // Operation successful
  if (code.length == 1 && code != "0") {
    document.getElementById("miniresultbox").innerHTML = message;
    // Whether signup, edit or reset, or to go back to the main screen now
    location.href = "/";
  } else {
    showError(message);
  }
}

function changeName() {
  var name = document.forms["signupform"].username.value,
    url = location.origin + "/user/" + encodeURIComponent(name);
  document.getElementById("profileurl").innerHTML =
    gt.gettext("Profile address: ") + url;
}

// Swap privacy panes
function changePrivacy(type) {
  for (var p = 0; p < privacyList.length; p++) {
    document.getElementById("privacy" + privacyList[p]).style.display =
      type == privacyList[p] ? "inline" : "none";
  }
}

// Swap editor panes
function changeEditor(type) {
  var isBasic = type === "B";
  document.getElementById("detaileditor").style.display = isBasic
    ? "none"
    : "inline";
  document.getElementById("basiceditor").style.display = isBasic
    ? "inline"
    : "none";
}

function showError(err) {
  document.getElementById("miniresultbox").innerHTML =
    "<font color=red>" + err + "</font>";
  location.hash = "top";
}

// Need to duplicate this from openflights.js so that it opens in the Settings window,
// not the main window, and IE does not go nuts
function backupFlights() {
  location.href = location.origin + "/php/flights.php?export=backup";
}
