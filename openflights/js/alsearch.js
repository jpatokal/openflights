/*
 * Search for airline in database(s)
 */
URL_ALSEARCH = "/php/alsearch.php";
URL_COUNTRIES = "/php/countries.php";

var warning;

window.onload = function init(){
  xmlhttpPost(URL_COUNTRIES);
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

      if(strURL == URL_COUNTRIES) {
	loadCountries(self.xmlHttpReq.responseText);
      }

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
    var airline = form.airline.value;
    var country = form.country.value;
    var iata = form.iata.value;
    var icao = form.icao.value;
    var alias = form.alias.value;
    var callsign = form.callsign.value;
    var country = form.country[form.country.selectedIndex].text

    if(iata != "" && iata.length != 2) {
      alert("IATA codes must be exactly two letters.");
      form.iata.focus();
      return;
    } else {
      iata = iata.toUpperCase();
      form.iata.value = iata;
    }
    if(icao != "" && icao.length != 3) {
      alert("ICAO codes must be exactly three letters.");
      form.icao.focus();
      return;
    } else {
      icao = icao.toUpperCase();
      form.icao.value = icao;
    }
    /*
    var re_alphanum = /^[-.\'a-zA-Z0-9 ]*$/;
    if(! re_alphanum.test(airline) || ! re_alphanum.test(alias)) {
      alert("Only the unaccented letters A-Z, the numbers 0-9, the punctuation marks -.' (dash, period, apostrophe) and spaces can be used in airline names.");
      return;
    }
    */

    if(action == "RECORD") {
      if(! parent.opener.addNewAirline) {
	alert("Sorry, you have to be logged into OpenFlights to use this.");
	return;
      }
      if(airline == "") {
	alert("Please enter an airline name.");
	form.airline.focus();
	return;
      } else {
	airline = airline.substring(0,1).toUpperCase() + airline.substring(1);
	form.airline.value = airline;
      }

      if(country == "") {
	alert("Please select a country.");
	form.country.focus();
	return;
      }

      if(iata == "") {
	if(! confirm("You have not entered an IATA/FAA code. Are you sure the airline does not have one and you wish to proceed?")) {
	  return;
	}
      }
      if(icao == "") {
	if(! confirm("You have not entered an ICAO code. Are you sure the airline does not have one and you wish to proceed?")) {
	  return;
	}
      }

      desc = airline + ", " + country +
	" (IATA: " + (iata == "" ? "N/A" : iata)  + ", ICAO: " + (icao == "" ? "N/A" : icao) + ")";
      if(! confirm("Are you sure you want to add " + desc + " as a new airline?  Please double-check the name and airline codes before confirming.")) {
	document.getElementById("miniresultbox").innerHTML = "<I>Cancelled.</I>";
	return;
      }
    }

    query = 'airline=' + escape(airline) + '&' +
      'alias=' + escape(alias) + '&' +
      'iata=' + escape(iata) + '&' +
      'icao=' + escape(icao) + '&' +
      'country=' + escape(country) + '&' +
      'callsign=' + escape(callsign) + '&' +
      'offset=' + offset + '&' +
      'iatafilter=' + form.iatafilter.checked + '&' +
      'action=' + action;
    document.getElementById("miniresultbox").innerHTML = (action == "SEARCH" ? "<I>Searching...</I>" : "<I>Recording...</I>");
  }
  self.xmlHttpReq.send(query);
}

/*
 * Load up list of countries in DB
 */
function loadCountries(str) {
  var countries = str.split("\n");

  var select = "<select name=\"country\"";
  select += "><option value=\"\">ALL</option>";

  for (c in countries) {
    var col = countries[c].split(";");
    // code;country
    select += "<option value=\"" + col[0] + "\">" + col[1] + "</option>";
  }
  select += "</select>";

  document.getElementById("country_select").innerHTML = select;
}

/*
 * Display results of search
 */
function searchResult(str) {
  var airlines = str.split("\n");
  var table = "<table width=95% cellspacing=0>";
  var offset, sql;
  var disclaimer = "";

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
	table += "<tr><td><i>No matches found.</i></td></td>";
	break;
      }
      table += "<tr><td><b>Results " + (offset+1) + " to " + Math.min(offset+10, max) + " of " + max + "</b><br></td>";

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
    // 0 iata, 1 icao, 2 alid, 3 al-name, 4 alias, 5 country, 6 callsign, 7 printable-name, 8 uid
    if(a % 2 == 1) {
      bgcolor = "#fff";
    } else {
      bgcolor = "#ddd";
    }
    if(col[8] != "") {
      bgcolor = "#fdd"; // User-added
      disclaimer = "<br><b>Note</b>: Airlines in <span style='background-color: " + bgcolor + "'>pink</span> have been added by users of OpenFlights.";
    }
    table += "<tr><td style='background-color: " + bgcolor + "'>" + col[7] + "</td>";
    // id = alid
    table += "<td style='text-align: right; background-color: " + bgcolor + "'><INPUT type='button' value='Select' onClick='selectAirline(\"" + col[2] + "\",\"" + escape(col[7]) + "\")'></td>";
    table += "</tr>";
  }
  table += "</table>";
  table += disclaimer;
  document.getElementById("miniresultbox").innerHTML = table;
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
    var iata = form.iata.value;
    var code = (iata != "" ? iata : form.icao.value);
    name = form.airline.value + " (" + code + ")";
    selectAirline(col[1], name);
  }
}

// Clear form -- everything *except* database
function clearSearch() {
  var form = document.forms['searchform'];
  form.airline.value = "";
  form.country.selectedIndex = 0;
  form.iata.value = "";
  form.icao.value = "";
  form.alias.value = "";
  form.callsign.value = "";
  form.iatafilter.checked = true;
}

// Airline selected, kick it back to main window and close this
function selectAirline(data, name) {
  if(! parent.opener.addNewAirline) {
    alert("Sorry, you have to be logged into OpenFlights to do this.");
  }
  parent.opener.addNewAirline(data, unescape(name));
  window.close();
}

// A dupe from openflights.js...
function help(context) {
  window.open('/help/' + context + '.html', 'OpenFlights Help: ' + context, 'width=500,height=400,scrollbars=yes');
}
