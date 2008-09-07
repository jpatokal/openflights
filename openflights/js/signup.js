/*
 * Create new user accounts
 */
var URL_SIGNUP = "/php/signup.php";

function xmlhttpPost(strURL, offset) {
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
    query = 'name=' + escape(form.username.value) + '&' +
      'pw=' + escape(form.pw1.value) + '&' +
      'email=' + escape(form.email.value) + '&' +
      'privacy=' + escape(privacy);
    document.getElementById("resultbox").innerHTML = "<I>Creating account...</I>";
  }
  self.xmlHttpReq.send(query);
}

// Validate form
function validate() {
  var form = document.forms['signupform'];
  var name = form.username.value;
  var pw1 = form.pw1.value;
  var pw2 = form.pw2.value;
  var email = form.email.value;

  if(name == "") {
    showError("Please enter a username.");
    return;
  }
  if(pw1 == "") {
    showError("Please enter a password.");
    return;
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
  xmlhttpPost(URL_SIGNUP);
}


function signup(str) {
  var code = str.split(";")[0];
  var message = str.split(";")[1];
  // Creation successful
  if(code == "1") {
    document.getElementById("resultbox").innerHTML = message;
    var form = document.forms['signupform'];
    var name = form.username.value;
    var pw = form.pw1.value;
    parent.opener.newUserLogin(name, pw);
    window.close();
  } else {
    showError(message);
  }
}

function showError(err) {
  document.getElementById("resultbox").innerHTML = "<font color=red>" + err + "</font>";
}
