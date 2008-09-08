/*
 * Create new user accounts and modify existing ones
 */
var URL_SIGNUP = "/php/signup.php";

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
	signup(self.xmlHttpReq.responseText);
      }
    }
  }
  var query = "";
  if(strURL == URL_SIGNUP) {
    var form = document.forms['signupform'];
    var privacy;

    for (r=0; r < signupform.privacy.length; r++){
      if (signupform.privacy[r].checked) {
	privacy = signupform.privacy[r].value;
      }
    }
    query = 'type=' + type + '&' +
      'pw=' + escape(form.pw1.value) + '&' +
      'email=' + escape(form.email.value) + '&' +
      'privacy=' + escape(privacy);
    if(type == 'NEW') {
      query += '&name=' + escape(form.username.value);
      document.getElementById("resultbox").innerHTML = "<I>Creating account...</I>";
    } else {
      query += '&oldpw=' + escape(form.oldpw.value);
      document.getElementById("resultbox").innerHTML = "<I>Saving changes...</I>";
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

  if(type == 'NEW') {
    var name = form.username.value;
    if(name == "") {
      showError("Please enter a username.");
      return;
    }
    if(pw1 == "") {
      showError("Please enter a password.");
      return;
    }
  }
  if(type == 'EDIT') {
    var oldpw = form.oldpw.value;
    if(pw1 != "" && oldpw == "") {
      showError("Please enter your current password.");
      return;
    }
  }

  if(pw1 != pw2) {
    showError("Your passwords don't match, please try again.");
    return;
  }

  if(email != "" && ! /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/.test(email)) {
    showError('Invalid e-mail address, it should be "user@example.domain"');
    return;
  }

  document.getElementById("resultbox").innerHTML = "<i>Processing...</i>";
  xmlhttpPost(URL_SIGNUP, type);
}


function signup(str) {
  var code = str.split(";")[0];
  var message = str.split(";")[1];
  // Operation successful
  if(code != "0") {
    document.getElementById("resultbox").innerHTML = message;
    if(code == "1") { // new
      var form = document.forms['signupform'];
      var name = form.username.value;
      var pw = form.pw1.value;
      parent.opener.newUserLogin(name, pw);
    }
    window.close();
  } else {
    showError(message);
  }
}

function showError(err) {
  document.getElementById("resultbox").innerHTML = "<font color=red>" + err + "</font>";
}
