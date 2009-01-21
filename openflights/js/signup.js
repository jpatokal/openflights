/*
 * Create new user accounts and modify existing ones
 */
var URL_SIGNUP = "/php/signup.php";

var privacyList = [ 'N', 'Y', 'O' ];

window.onload = function init(){
  if(window.location.href.indexOf("settings") != -1) {
    xmlhttpPost(URL_SIGNUP, "LOAD");
  } else {
    // TODO document.forms['signupform'].username.value = parent.opener.document.forms['login'].name.value;
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

      if(strURL == URL_SIGNUP) {
	if(type == "LOAD") {
	  loadUser(self.xmlHttpReq.responseText);
	} else {
	  signup(self.xmlHttpReq.responseText);
	}
      }
    }
  }
  var query = "";
  if(strURL == URL_SIGNUP) {
    var form = document.forms['signupform'];
    var privacy, editor;

    for (r=0; r < signupform.privacy.length; r++){
      if (signupform.privacy[r].checked) {
	privacy = signupform.privacy[r].value;
      }
    }
    for (r=0; r < signupform.editor.length; r++){
      if (signupform.editor[r].checked) {
	editor = signupform.editor[r].value;
      }
    }
    query = 'type=' + type + '&' +
      'pw=' + escape(hex_md5(form.pw1.value + form.username.value.toLowerCase())) + '&' +
      'email=' + escape(form.email.value) + '&' +
      'privacy=' + escape(privacy) + '&' +
      'editor=' + escape(editor);
    switch(type) {
    case 'NEW':
      query += '&name=' + escape(form.username.value);
      document.getElementById("miniresultbox").innerHTML = "<I>Creating account...</I>";
      break;

    case 'EDIT':
      for (r=0; r < signupform.startpane.length; r++){
	if (signupform.startpane[r].checked) {
	  startpane = signupform.startpane[r].value;
	}
      }
      if(form.oldpw.value != "") {
	query += '&oldpw=' + escape(hex_md5(form.oldpw.value + form.username.value.toLowerCase()));
	// Legacy password for case-sensitive days of yore
	query += '&oldlpw=' + escape(hex_md5(form.oldpw.value + form.username.value));
      }
      if(form.guestpw.value != "") {
	query += '&guestpw=' + escape(hex_md5(form.guestpw.value + form.username.value.toLowerCase()));
      }
      query += '&startpane=' + escape(startpane);
      document.getElementById("miniresultbox").innerHTML = "<I>Saving changes...</I>";
      break;

    case 'RESET':
    case 'LOAD':
      // do nothing
      break;
    }
  }
  self.xmlHttpReq.send(query);
}

// Validate form
function validate(type) {
  var form = document.forms['signupform'];
  var pw1 = form.pw1.value;
  var pw2 = form.pw2.value;
  var email = form.email.value;

  if(type == 'RESET') {
    if(! confirm("This will PERMANENTLY delete ALL YOUR FLIGHTS.  Have you exported a backup copy, and are you sure you want to do this?")) {
      document.getElementById("miniresultbox").innerHTML = "<i>Deletion cancelled.</i>";
      return;
    }
  }

  if(type == 'NEW') {
    var name = form.username.value;
    if(name == "") {
      showError("Please enter a username.");
      form.username.focus();
      return;
    }
    if(pw1 == "") {
      showError("Please enter a password.");
      form.pw1.focus();
      return;
    }
  }
  if(type == 'EDIT') {
    var oldpw = form.oldpw.value;
    if(pw1 != "" && oldpw == "") {
      showError("Please enter your current password if you wish to change to a new password.");
      form.oldpw.focus();
      return;
    }
    if(pw1 == "" && oldpw != "") {
      showError("Please enter a new password, or clear current password if you do not wish to change it.");
      form.pw1.focus();
      return;
    }
  }

  if(pw1 != pw2) {
    showError("Your passwords don't match, please try again.");
    form.pw1.focus();
    return;
  }

  if(email != "" && ! /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/.test(email)) {
    showError('Invalid e-mail address, it should be "user@example.domain"');
    form.email.focus();
    return;
  }

  document.getElementById("miniresultbox").innerHTML = "<i>Processing...</i>";
  xmlhttpPost(URL_SIGNUP, type);
}

// Load up user data
function loadUser(str) {
  var cols = str.split(";");
  if(cols[0] == "3") {
    var settings = jsonParse(cols[1]);
    var elite = settings["elite"];
    var form = document.forms['signupform'];
    document.getElementById('eliteicon').innerHTML = getEliteIcon(elite, settings["validity"]);
    if(elite == "G" || elite == "P") {
      signupform.guestpw.disabled = false;
      for (r=0; r < signupform.startpane.length; r++){
	signupform.startpane[r].disabled = false;
      }
    }
    signupform.email.value = settings["email"];
    signupform.username.value = settings["name"];
    signupform.myurl.value = "http://openflights.org/user/" + settings["name"];
    signupform.count.value = "Viewed " + settings["count"] + " times";


    if(settings["fbuid"]) {
      if(settings["sessionkey"]) {
	fbstring = "Linked, automatic updates";
      } else {
	fbstring = "Linked, manual updates only<br><small><a target='_blank' href='http://apps.facebook.com/openflights'>Automate</a></small>";
      }
    } else {
      fbstring = "Not active<br><small><a target='_blank' href='http://apps.facebook.com/openflights?ofname=" +
	settings["name"] + "'>Add link</a></small>";
    }
    document.getElementById('facebook').innerHTML = fbstring;
    signupform.guestpw.value = settings["guestpw"];
    for (r=0; r < signupform.privacy.length; r++){
      if (signupform.privacy[r].value == settings["public"]) {
	signupform.privacy[r].checked = true;
      } else {
	signupform.privacy[r].checked = false;
      }
    }
    changePrivacy(settings["public"]);
    for (r=0; r < signupform.editor.length; r++){
      if (signupform.editor[r].value == settings["editor"]) {
	signupform.editor[r].checked = true;
      } else {
	signupform.editor[r].checked = false;
      }
    }
    changeEditor(settings["editor"]);
    for (r=0; r < signupform.startpane.length; r++){
      if (signupform.startpane[r].value == settings["startpane"]) {
	signupform.startpane[r].checked = true;
      } else {
	signupform.startpane[r].checked = false;
      }
    }
  } else {
    showError(str.split(";")[1]);
  }
}


function signup(str) {
  var code = str.split(";")[0];
  var message = str.split(";")[1];

  // Operation successful
  if(code.length == 1 && code != "0") {
    document.getElementById("miniresultbox").innerHTML = message;
    // Whether signup, edit or reset, go back to main screen now
    location.href = '/';
    return;
  } else {
    showError(message);
  }
}

function changeName() {
  var name = document.forms['signupform'].username.value;
  var url = "http://" + location.host + "/user/" + escape(name);
  $('profileurl').innerHTML = "Profile address: " + url;
}

function changePrivacy(type) {
  for(p = 0; p < privacyList.length; p++) {
    if(type == privacyList[p]) {
      style = "inline";
    } else {
      style = "none";
    }
    $('privacy' + privacyList[p]).style.display = style;
  }
}

function changeEditor(type) {
  switch(type) {
  case "B":
    $('detaileditor').style.display = "none";
    $('basiceditor').style.display = "inline";
    break;
  case "D":
    $('basiceditor').style.display = "none";
    $('detaileditor').style.display = "inline";
    break;
  }
}

function showError(err) {
  document.getElementById("miniresultbox").innerHTML = "<font color=red>" + err + "</font>";
  location.hash = "top";
}

// Need to duplicate this from openflights.js so that it opens in Settings window, not main, and
// IE does not go nuts
function backupFlights() {
  location.href="http://" + location.host + "/php/flights.php?export=backup";
}
