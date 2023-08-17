/*
 * Create new user accounts and modify existing ones
 */
const URL_SETTINGS = "/php/settings.php";

const PRIVACY_LIST = ["N", "Y", "O"];

var gt;

window.onload = function init() {
  gt = new Gettext({ domain: "messages" });
  if (window.location.href.indexOf("settings") != -1) {
    var form = document.forms["signupform"],
      elite = "elite" in form ? form.elite.value : "";

    var eliteIcon = document.getElementById("eliteicon");

    if (eliteIcon != null) {
      eliteIcon.innerHTML = getEliteIcon(
        elite,
        "validity" in form ? form.validity.value : ""
      );
    }

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

function sendSettingsRequest(type) {
  const params = new URLSearchParams();
  let r = 0;
  var form = document.forms["signupform"],
    privacy,
    editor,
    units;

  for (r = 0; r < signupform.privacy.length; r++) {
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

  params.set("type", type);
  params.set("pw", MD5(form.pw1.value + form.username.value.toLowerCase()));
  params.set("email", form.email.value);
  params.set("privacy", privacy);
  params.set("editor", editor);
  params.set("units", units);
  params.set("locale", form.locale.value);

  switch (type) {
    case "NEW":
      params.set("name", form.username.value);
      document.getElementById("miniresultbox").innerHTML =
        "<i>" + gt.gettext("Creating account...") + "</i>";
      break;

    case "EDIT":
      var startpane;
      for (r = 0; r < signupform.startpane.length; r++) {
        if (signupform.startpane[r].checked) {
          startpane = signupform.startpane[r].value;
        }
      }
      if (form.oldpw.value != "") {
        params.set(
          "oldpw",
          MD5(form.oldpw.value + form.username.value.toLowerCase())
        );
        // Legacy password for case-sensitive days of yore
        params.set("oldlpw", MD5(form.oldpw.value + form.username.value));
      }
      if (form.guestpw.value != "") {
        params.set(
          "guestpw",
          MD5(form.guestpw.value + form.username.value.toLowerCase())
        );
      }
      params.set("startpane", startpane);
      document.getElementById("miniresultbox").innerHTML =
        "<i>" + gt.gettext("Saving changes...") + "</i>";
      break;

    case "RESET":
    case "LOAD":
      // do nothing
      break;
  }

  fetch(URL_SETTINGS, {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: params,
  })
    .then((response) => response.text())
    .then(handleResponse);
}

/**
 * Validate form
 * @param type
 */
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
  sendSettingsRequest(type);
}

/**
 * Check if server processing (user creation, edit, etc.) succeeded
 * @param str {string}
 */
function handleResponse(str) {
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

/**
 * Swap privacy panes
 * @param type
 */
function changePrivacy(type) {
  for (var p = 0; p < PRIVACY_LIST.length; p++) {
    document.getElementById("privacy" + PRIVACY_LIST[p]).style.display =
      type == PRIVACY_LIST[p] ? "inline" : "none";
  }
}

/**
 * Swap editor panes
 * @param type
 */
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
