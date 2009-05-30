/*
 * Search for airport in database(s)
 */
URL_APSEARCH = "/php/apsearch.php";
URL_COUNTRIES = "/php/countries.php";

DB_OPENFLIGHTS = "airports";
DB_OURAIRPORTS = "airports_oa";
DB_DAFIF = "airports_dafif";

var warning;
var gt;

window.onload = function init(){
  gt = new Gettext({ 'domain' : 'messages' });
}

function doSearch(offset) {
  xmlhttpPost(URL_APSEARCH, offset, "SEARCH");
}

function doRecord(offset) {
  xmlhttpPost(URL_APSEARCH, offset, "RECORD");
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

      if(strURL == URL_APSEARCH) {
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
  if(strURL == URL_APSEARCH) {
    var form = document.forms['searchform'];
    var db = form.db.value;
    var airport = form.airport.value;
    var city = form.city.value;
    var code = form.country.value;
    var iata = form.iata.value;
    var icao = form.icao.value;
    var x = form.x.value;
    var y = form.y.value;
    var elevation = form.elevation.value;
    var tz = form.tz.value;
    var dst = form.dst.value;
    var country = form.country[form.country.selectedIndex].text
    var apid = form.apid.value;

    if(iata != "" && iata.length != 3) {
      alert(gt.gettext("IATA/FAA codes must be exactly three letters."));
      form.iata.focus();
      return;
    } else {
      iata = iata.toUpperCase();
      form.iata.value = iata;
    }
    if(icao != "" && icao.length != 4) {
      alert(gt.gettext("ICAO codes must be exactly four letters."));
      form.icao.focus();
      return;
    } else {
      icao = icao.toUpperCase();
      form.icao.value = icao;
    }
    var re_alphanum = /^[-.\'a-zA-Z0-9 ]*$/;
    if(! re_alphanum.test(airport) || ! re_alphanum.test(city)) {
      alert(gt.gettext("Only the unaccented letters A-Z, the numbers 0-9, the punctuation marks -.' (dash, period, apostrophe) and spaces can be used in airport and city names."));
      return;
    }

    if(action == "SEARCH" && db == DB_DAFIF) {
      if(city != "") {
	warning = Gettext.strargs(gt.gettext("Ignoring city '%1', since the DAFIF database does not contain city information."), [city]);
	city = "";
      }
      if(iata != "") {
	switch(code) {
	case "US":
	  // do nothing
	  break;
	  
	case "":
	  warning = Gettext.strargs(gt.gettext("Search for IATA/FAA code '%1' limited to United States airports, since DAFIF does not contain IATA codes for cities outside the US."), [iata]);
	  code = "US";
	  break;

	default:
	  warning = Gettext.strargs(gt.gettext("Ignoring IATA code '%1', since DAFIF does not contain IATA codes for cities outside the United States."), [iata]);
	  iata = "";
	  break;
	}
      }
    }

    if(action == "RECORD") {
      if(! parent.opener || ! parent.opener.addNewAirport) {
	alert(gt.gettext("Sorry, you have to be logged into OpenFlights to use this."));
	return;
      }
      if(airport == "") {
	alert(gt.gettext("Please enter an airport name."));
	form.airport.focus();
	return;
      } else {
	airport = airport.substring(0,1).toUpperCase() + airport.substring(1);
	form.airport.value = airport;
      }

      if(city == "") {
	alert(gt.gettext("Please enter a city name."));
	form.city.focus();
	return;
      } else {
	city = city.substring(0,1).toUpperCase() + city.substring(1);
	form.city.value = city;
      }
	
      if(code == "") {
	alert(gt.gettext("Please select a country."));
	form.country.focus();
	return;
      }

      if(x == "" || y == "" || elevation == "") {
	alert(gt.gettext("Please enter latitude, longitude and elevation. Tip: Check if the OurAirport database already contains your airport, and \"Load\" the data from there."));
	form.x.focus();
	return;
      }

      var re_dd = /^[-+]?\d*\.\d{3,}$/;
      if(! re_dd.test(x) || ! re_dd.test(y)) {
	alert(gt.gettext("Latitude and longitude must be given as decimal degrees, where negative numbers indicate 'south' and 'west' respectively, and with at least three digits of precision (after the decimal point).  For example, San Francisco (SFO) is at latitude 37.6189(N), longitude -122.3748(W)."));
	form.x.focus();
	return;
      }

      if(Math.abs(x) > 180) {
	alert(gt.gettext("Latitude must be in the range -180 (west) to 180 (east) degrees."));
	form.x.focus();
	return;
      }

      if(Math.abs(y) > 90) {
	alert(gt.gettext("Longitude must be in the range 90 (north) to -90 (south) degrees."));
	form.y.focus();
	return;
      }

      if(elevation < 0) {
	alert(gt.gettext("Please enter a positive number for elevation."));
	form.elevation.focus();
	return;
      }

      var re_tz = /^[-+]?\d*\.?\d*$/;
      if(tz == "" || ! re_tz.test(tz) || Math.abs(tz) > 14) {
	alert(gt.gettext("Please enter a timezone as an offset from UTC/GMT, eg. +8 for Singapore or -5 for New York.  Use decimals for fractional time zones, eg. +5.75 for Nepal."));
	form.tz.focus();
	return;
      }
      if(dst == "U") {
	if(! confirm(gt.gettext("You have not entered whether this airport follows Daylight Savings Time (DST).  Leave it as Unknown?"))) {
	  form.dst.focus();
	  return;
	}
      }
      if(iata == "") {
	if(! confirm(gt.gettext("You have not entered an IATA/FAA code. Are you sure the airport does not have one and you wish to proceed?"))) {
	  form.iata.focus();
	  return;
	}
      }
      if(icao == "") {
	if(! confirm(gt.gettext("You have not entered an ICAO code. Are you sure the airport does not have one and you wish to proceed?"))) {
	  form.icao.focus();
	  return;
	}
      }

      // Last check for new airports only
      if(apid == "") {
	desc = airport + ", " + city + ", " + country +
	  " (IATA: " + (iata == "" ? "N/A" : iata)  + ", ICAO: " + (icao == "" ? "N/A" : icao) + ")";
	quad = (parseFloat(y) < 0 ? "SOUTH" : "NORTH") + "-" + (parseFloat(x) < 0 ? "WEST" : "EAST");
	if(! confirm(Gettext.strargs(gt.gettext("Are you sure you want to add %1 as a new airport, located in the %2 quadrant of the world?  Please double-check the name, airport codes and exact coordinates before confirming."), [desc, quad]))) {
	  document.getElementById("miniresultbox").innerHTML = "<I>" + gt.gettext("Cancelled.") + "</I>";
	  return;
	}
      }
    }

    query = 'name=' + escape(airport) + '&' +
      'iata=' + escape(iata) + '&' +
      'icao=' + escape(icao) + '&' +
      'city=' + escape(city) + '&' +
      'country=' + escape(country) + '&' +
      'code=' + escape(code) + '&' +
      'x=' + x + '&' +
      'y=' + y + '&' +
      'elevation=' + elevation + '&' +
      'timezone=' + tz + '&' +
      'dst=' + dst + '&' +
      'db=' + escape(db) + '&' +
      'offset=' + offset + '&' +
      'iatafilter=' + form.iatafilter.checked + '&' +
      'apid=' + apid + '&' +
      'action=' + action;
    document.getElementById("miniresultbox").innerHTML = "<I>" + (action == "SEARCH" ? gt.gettext("Searching...") : gt.gettext("Recording...")) + "</I>";
  }
  self.xmlHttpReq.send(query);
}

/*
 * Display results of search
 */
function searchResult(str) {
  var airports = str.split("\n");
  var table = "<table width=95% cellspacing=0>";
  var offset, sql;
  var db = document.forms['searchform'].db.value;
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
  for(a in airports) {
    // First line contains header info
    if(a == 0) {
      var col = airports[a].split(";");
      offset = parseInt(col[0]);
      max = col[1];
      if(max == 0) {
	table += "<tr><td><i>" + gt.gettext("No matches found in this database.") + "<br><ul>";
	if(document.forms['searchform'].iatafilter.checked) {
	  table += "<li>" + gt.gettext("Try unchecking 'Show only major airports' and search again.");
	}
	if(document.forms['searchform'].db.value != DB_OURAIRPORTS) {
	  table += "<li>" + gt.gettext("Switch to the OurAirports database and search again.");
	}
	table += "</ul></td></tr>";
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
    var col = jsonParse(airports[a]);
    if(a % 2 == 1) {
      bgcolor = "#fff";
    } else {
      bgcolor = "#ddd";
    }
    switch(col["ap_uid"]) {
    case "user":
      bgcolor = "#fdd";
      disclaimer = "<br><span style='background-color: " + bgcolor + "'>" + gt.gettext("Airports in pink have been added by users of OpenFlights.") + "</span>";
      break;

    case "own":
      bgcolor = "#ddf";
      disclaimer = "<br><span style='background-color: " + bgcolor + "'>" + gt.gettext("Airports in blue have been added by you and can be edited.") + "<span>";
      break;
    }
    table += "<tr><td style='background-color: " + bgcolor + "'>" + col["ap_name"] + "</td>";
    if(db == DB_OPENFLIGHTS && !guest) {
      // code:apid:x:y:tz:dst
      id = (col["iata"] != "" ? col["iata"] : col["icao"]) + ":" + col["apid"] + ":" + col["x"] + ":" + col["y"] +
	":" + col["timezone"] + ":" + col["dst"];
      table += "<td style='text-align: right; background-color: " + bgcolor + "'><INPUT type='button' value='" + gt.gettext("Select") + "' onClick='selectAirport(\"" + id + "\",\"" + escape(col["ap_name"]) + "\")'></td>";
    }
    if(db != DB_OPENFLIGHTS || col["ap_uid"] == "own" || guest) {
      if(col["ap_uid"] == "own" && db == DB_OPENFLIGHTS) {
	label = gt.gettext("Edit");
      } else {
	label = gt.gettext("Load");
      }
      table += "<td style='text-align: right; background-color: " + bgcolor + "'><INPUT type='button' value='" + label + "' onClick='loadAirport(\"" + escape(airports[a]) + "\")'></td>";
    }
    table += "</tr>";
  }
  table += "</table>";
  table += disclaimer;
  document.getElementById("miniresultbox").innerHTML = table;
}

// Load data from search result into form
function loadAirport(data) {
  var col = jsonParse(unescape(data));

  var b_back = document.getElementById("b_back");
  var b_fwd = document.getElementById("b_fwd");
  if(b_back) b_back.disabled = true;
  if(b_fwd) b_fwd.disabled = true;

  var form = document.forms['searchform'];
  form.airport.value = col["name"];
  form.city.value = col["city"];
  form.iata.value = col["iata"];
  form.icao.value = col["icao"];
  form.x.value = col["x"];
  form.y.value = col["y"];
  form.elevation.value = col["elevation"];
  if(col["timezone"]) {
    form.tz.value = col["timezone"];
  }
  country = col["country"];
  var country_select = form.country;
  for(index = 0; index < country_select.length; index++) {
    if(country_select[index].value == country || country_select[index].text == country) {
      country_select.selectedIndex = index;
    }
  }
  var dst_select = form.dst;
  for(index = 0; index < dst_select.length; index++) {
    //alert(dst_select[index].value + "/" + col["dst"]);
    if(dst_select[index].value == col["dst"]) {
      dst_select.selectedIndex = index;
    }
  }

  if(col["apid"]) {
    form.apid.value = col["apid"];
    document.getElementById('b_add').style.display = "none";
    document.getElementById('b_edit').style.display = "inline";
  } else {
    form.apid.value = "";
    document.getElementById('b_add').style.display = "inline";
    document.getElementById('b_edit').style.display = "none";
  }
}

// Did we manage to record the airport?
function recordResult(str) {
  var col = str.split(";");
  // Error?
  if(col[0] != "1") {
    document.getElementById("miniresultbox").innerHTML = col[1];
  } else {
    document.getElementById("miniresultbox").innerHTML = col[2];

    // Select newly minted airport and return to main
    var form = document.forms['searchform'];
    var iata = form.iata.value;
    var country = form.country[form.country.selectedIndex].text

    // code:apid:x:y
    code = (iata != "" ? iata : form.icao.value);
    // city-airport (code), country
    data = code + ":" + col[1] + ":" + form.x.value + ":" + form.y.value;
    name = form.city.value + "-" + form.airport.value + " (" + code + "), " + country;
    selectAirport(data, name);
  }
}

// Clear form -- everything *except* database
function clearSearch() {
  var form = document.forms['searchform'];
  form.airport.value = "";
  form.city.value = "";
  form.country.selectedIndex = 0;
  form.iata.value = "";
  form.icao.value = "";
  form.x.value = "";
  form.y.value = "";
  form.elevation.value = "";
  form.tz.value = "";
  form.dst.selectedIndex = 0;
  form.apid.value = "";
  form.iatafilter.checked = true;
  document.getElementById('b_add').style.display = "inline";
  document.getElementById('b_edit').style.display = "none";
  document.getElementById("miniresultbox").innerHTML = "";
}

// User has changed locale, reload page with new URL
function changeLocale() {
  location.href = "http://" + location.host + "/html/apsearch.php?lang=" + document.getElementById("locale").value;
}

// Airport selected, kick it back to main window and close this
function selectAirport(data, name) {
  parent.opener.addNewAirport(data, unescape(name));
  window.close();
}

// A dupe from openflights.js...
function help(context) {
  window.open('/help/' + context + '.html', 'OpenFlights Help: ' + context, 'width=500,height=400,scrollbars=yes');
}