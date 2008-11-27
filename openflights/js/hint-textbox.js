// Script written by Drew Noakes -- http://drewnoakes.com
// 14 Dec 2006

var HintClass = "hintTextbox";
var HintActiveClass = "hintTextboxActive";

// define a custom method on the string class to trim leading and training spaces
String.prototype.trim = function() { return this.replace(/^\s+|\s+$/g, ''); };

function initHintTextboxes() {
  var inputs = document.getElementsByTagName('input');
  for (i=0; i<inputs.length; i++) {
    var input = inputs[i];
    if (input.type!="text")
      continue;

    if (input.className.indexOf(HintClass)!=-1) {
      input.hintText = input.value;
      input.className = HintClass;
      input.onfocus = onHintTextboxFocus;
      input.onblur = onHintTextboxBlur;
    }
  }
}


function resetHintTextboxes() {
  var inputs = document.getElementsByTagName('input');
  for (i=0; i<inputs.length; i++) {
    var input = inputs[i];
    if (input.type!="text")
      continue;

    // Matches active and non-active
    if (input.className.indexOf(HintClass)!=-1) {
      input.className = HintClass;
      input.value = input.hintText;
      input.style.color = "#888";
    }
  }
}

function onHintTextboxFocus() {
  var input = this;
  if (input.value.trim()==input.hintText) {
    input.value = "";
    input.className = HintActiveClass;
  }
  input.style.color = "#000";
}

function onHintTextboxBlur() {
  var input = this;
  if (input.value.trim().length==0) {
    input.style.color = "#888";
    input.value = input.hintText;
    input.className = HintClass;
  }
}
