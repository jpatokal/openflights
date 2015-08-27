/*
 * Search for airline in database(s)
 */
URL_ALSEARCH = "/php/alsearch.php";

var warning;
var gt;

window.onload = function init(){
  gt = new Gettext({ 'domain' : 'messages' });

  // ...?name=x&mode=y
  // 0    1 2          3    4
  var args = window.location.href.split('?');
  if(args[1]) {
    keys = args[1].split('&');
    if(keys[0].split('=')[0] == "name") {
      form = document.forms['searchform'];
      form.name.value = unescape(keys[0].split('=')[1]);
      selectInSelect(form.mode, keys[1].split('=')[1]);
      selectInSelect(form.active, "Y");
      changeMode();
    }
  }
}

function doSearch(offset) {
  xmlhttpPost(URL_ALSEARCH, offset, "SEARCH");
}

function doRecord(offset) {
  xmlhttpPost(URL_ALSEARCH, offset, "RECORD");
}

function xmlhttpPost(strURL, offset, action) {
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

      if(strURL == URL_ALSEARCH) {
	if(action == "SEARCH") {
	  searchResult(self.xmlHttpReq.responseText);
	}
	if(action == "RECORD") {
	  recordResult(self.xmlHttpReq.responseText);
	}
      }
    }
  }
  var query = "";
  if(strURL == URL_ALSEARCH) {
    var form = document.forms['searchform'];
    var name = form.name.value;
    var country = form.country[form.country.selectedIndex].text;
    var iata = form.iata.value;
    var icao = form.icao.value;
    var alias = form.alias.value;
    var callsign = form.callsign.value;
    var mode = form.mode.value;
    var active = form.active.value;
    var alid = form.alid.value;

    if(iata != "" && iata.length != 2) {
      alert(gt.gettext("IATA codes must be exactly two letters."));
      form.iata.focus();
      return;
    } else {
      iata = iata.toUpperCase();
      form.iata.value = iata;
    }
    if(icao != "" && icao.length != 3) {
      alert(gt.gettext("ICAO codes must be exactly three letters."));
      form.icao.focus();
      return;
    } else {
      icao = icao.toUpperCase();
      form.icao.value = icao;
    }
    if(["XXX", "YYY", "ZZZ"].indexOf(icao) > -1) {
      alert(gt.gettext("Invalid ICAO code."));
      form.iata.focus();
      return;
    }

    if(action == "RECORD") {
      if(! parent.opener || ! parent.opener.addNewAirline) {
	alert(gt.gettext("Sorry, you have to be logged into OpenFlights to use this."));
	return;
      }
      if(name == "") {
	alert(gt.gettext("Please enter a name."));
	form.name.focus();
	return;
      } else {
	name = name.substring(0,1).toUpperCase() + name.substring(1);
	form.name.value = name;
      }

      if(country == "ALL") {
	alert(gt.gettext("Please select a country."));
	form.country.focus();
	return;
      }
      if(active == "") {
	alert(gt.gettext("Please select Yes for airlines that are still operating, or No for inactive airlines."));
	form.active.focus();
	return;
      }

      if(mode == "F") {
	if(iata == "") {
	  if(! confirm(gt.gettext("You have not entered an IATA/FAA code. Are you sure the airline does not have one and you wish to proceed?"))) {
	    return;
	  }
	}
	if(icao == "") {
	  if(! confirm(gt.gettext("You have not entered an ICAO code. Are you sure the airline does not have one and you wish to proceed?"))) {
	    return;
	  }
	}
      }

      // Last check for new airlines only
      if(alid == "") {
	desc = name + ", " + country +
	  " (IATA: " + (iata == "" ? "N/A" : iata)  + ", ICAO: " + (icao == "" ? "N/A" : icao) + ")";
	if(! confirm(Gettext.strargs(gt.gettext("Are you sure you want to add %1 as a new operator?  Please double-check the name and any airline codes before confirming."), [desc]))) {
	  document.getElementById("miniresultbox").innerHTML = "<I>" + gt.gettext("Cancelled.") + "</I>";
	  return;
	}
      }
    }

    query = 'name=' + encodeURIComponent(name) + '&' +
      'alias=' + encodeURIComponent(alias) + '&' +
      'iata=' + encodeURIComponent(iata) + '&' +
      'icao=' + encodeURIComponent(icao) + '&' +
      'country=' + encodeURIComponent(country) + '&' +
      'callsign=' + encodeURIComponent(callsign) + '&' +
      'mode=' + encodeURIComponent(mode) + '&' +
      'active=' + encodeURIComponent(active) + '&' +
      'offset=' + offset + '&' +
      'iatafilter=' + form.iatafilter.checked + '&' +
      'alid=' + alid + '&' +
      'action=' + action;
    document.getElementById("miniresultbox").innerHTML = "<I>" + (action == "SEARCH" ? gt.gettext("Searching...") : gt.gettext("Recording...")) + "</I>";
  }
  self.xmlHttpReq.send(query);
}

/*
 * Display results of search
 */
function searchResult(str) {
  var airlines = str.split("\n");
  var table = "<table width=95% cellspacing=0>";
  var offset, sql;
  var disclaimer = "";

  if(! parent.opener || ! parent.opener.addNewAirport) {
    guest = true;
  } else {
    guest = false;
  }
  if(warning) {
    table += "<tr><td colspan=2><i><font color='red'>" + warning + "</font></i></td></tr>";
    warning = null;
  }
  for(a in airlines) {
    var col = airlines[a].split(";");

    // First line contains header info
    if(a == 0) {
      offset = parseInt(col[0]);
      max = col[1];
      sql = col[2];
      if(max == 0) {
	table += "<tr><td><i>" + gt.gettext("No matches found.") + "</i></td></td>";
	break;
      }
      table += "<tr><td><b>" + Gettext.strargs(gt.gettext("Results %1 to %2 of %3"), [offset+1, Math.min(offset+10, max), max]) + "</b><br></td>";

      if(max > 10) {
	table += "<td style=\"text-align: right\"><nobr>";
	if(offset - 10 >= 0) {
	  table += "<INPUT id=\"b_back\" type=\"button\" value=\"<\" onClick=\"doSearch(" + (offset-10) + ")\">";
	} else {
	  table += "<INPUT type=\"button\" value=\"<\" disabled>";
	}
	if(offset + 10 < max) {
	  table += "<INPUT id=\"b_fwd\" type=\"button\" value=\">\" onClick=\"doSearch(" + (offset+10) + ")\">";
	} else {
	  table += "<INPUT type=\"button\" value=\">\" disabled>";
	}
	table += "</nobr></td>";
      }
      table += "</tr>";
      continue;
    }

    // Meat of the table
    var col = jsonParse(airlines[a]);
    if(a % 2 == 1) {
      bgcolor = "#fff";
    } else {
      bgcolor = "#ddd";
    }
    switch(col["al_uid"]) {
    case "user":
      bgcolor = "#fdd";
      disclaimer = "<br><span style='background-color: " + bgcolor + "'>" + gt.gettext("Operators in pink have been added by users of OpenFlights.") + "</span>";
      break;

    case "own":
      bgcolor = "#ddf";
      disclaimer = "<br><span style='background-color: " + bgcolor + "'>" + gt.gettext("Operators in blue have been added by you and can be edited.") + "<span>";
      break;
    }
    table += "<tr><td style='background-color: " + bgcolor + "'>" + col["al_name"] + "</td>";
    // id = alid
    table += "<td style='text-align: right; background-color: " + bgcolor + "'><INPUT type='button' value='" + gt.gettext("Select") + "' onClick='selectAirline(\"" + col["alid"] + "\",\"" + encodeURIComponent(col["al_name"]) + "\",\"" + col["mode"] + "\")'></td>";
    if(col["al_uid"] == "own" || guest) {
      if(col["al_uid"] == "own") {
	label = gt.gettext("Edit");
      } else {
	label = gt.gettext("Load");
      }
      table += "<td style='text-align: right; background-color: " + bgcolor + "'><INPUT type='button' value='" + label + "' onClick='loadAirline(\"" + encodeURIComponent(airlines[a]) + "\")'></td>";
    }

    table += "</tr>";
  }
  table += "</table>";
  table += disclaimer;
  document.getElementById("miniresultbox").innerHTML = table;
}

// Load data from search result into form
function loadAirline(data) {
  var col = jsonParse(unescape(data));

  var b_back = document.getElementById("b_back");
  var b_fwd = document.getElementById("b_fwd");
  if(b_back) b_back.disabled = true;
  if(b_fwd) b_fwd.disabled = true;

  var form = document.forms['searchform'];
  form.name.value = col["name"];
  if(col["alias"] != "null") form.alias.value = col["alias"];
  if(col["iata"] != "null") form.iata.value = col["iata"];
  if(col["icao"] != "null") form.icao.value = col["icao"];
  if(col["callsign"] != "null") form.callsign.value = col["callsign"];
  form.mode.value = col["mode"];
  country = col["country"];
  var country_select = form.country;
  for(index = 0; index < country_select.length; index++) {
    if(country_select[index].value == country || country_select[index].text == country) {
      country_select.selectedIndex = index;
    }
  }
  var active_select = form.active;
  for(index = 0; index < active_select.length; index++) {
    if(active_select[index].value == col["active"]) {
      active_select.selectedIndex = index;
    }
  }

  if(col["alid"]) {
    form.alid.value = col["alid"];
    document.getElementById('b_add').style.display = "none";
    document.getElementById('b_edit').style.display = "inline";
  } else {
    form.alid.value = "";
    document.getElementById('b_add').style.display = "inline";
    document.getElementById('b_edit').style.display = "none";
  }
}

// Did we manage to record the airline?
function recordResult(str) {
  var col = str.split(";");
  // Error?
  if(col[0] != "1") {
    document.getElementById("miniresultbox").innerHTML = col[1];
  } else {
    document.getElementById("miniresultbox").innerHTML = col[2];

    // Select newly minted airline and return to main
    // 1;alid
    var form = document.forms['searchform'];
    name = form.name.value;
    mode = form.mode.value;
    if(mode == "F") {
      var iata = form.iata.value;
      name += " (" + (iata != "" ? iata : form.icao.value) + ")";
    }
    selectAirline(col[1], name, mode);
  }
}

// Enable IATA,ICAO,callsign only for flights
function changeMode() {
  var form = document.forms['searchform'];
  var mode = form.mode.value;
  disabled = (mode != "F");
  form.iata.disabled = disabled;
  form.icao.disabled = disabled;
  form.callsign.disabled = disabled;
  form.iatafilter.disabled = disabled;
}

// Clear form -- everything *except* database
function clearSearch() {
  var form = document.forms['searchform'];
  form.name.value = "";
  form.country.selectedIndex = 0;
  form.active.selectedIndex = 0;
  form.mode.selectedIndex = 0;
  changeMode();
  form.iata.value = "";
  form.icao.value = "";
  form.alias.value = "";
  form.callsign.value = "";
  form.iatafilter.checked = true;
  form.alid.value = "";
}

// Airline selected, kick it back to main window and close this
function selectAirline(data, name, mode) {
  if(! parent.opener || ! parent.opener.addNewAirline) {
    alert(gt.gettext("Sorry, you have to be logged into OpenFlights to do this."));
  }
  parent.opener.addNewAirline(data, unescape(name), mode);
  window.close();
}

// A dupe from openflights.js...
function help(context) {
  window.open('/help/' + context + '.html', 'OpenFlights Help: ' + context, 'width=500,height=400,scrollbars=yes');
}
