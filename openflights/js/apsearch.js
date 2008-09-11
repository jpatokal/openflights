/*
 * Search for airport in database(s)
 */
URL_APSEARCH = "/php/apsearch.php";
URL_COUNTRIES = "/php/countries.php";

DB_OPENFLIGHTS = "airports";
DB_DAFIF = "airports_dafif";

window.onload = function init(){
  xmlhttpPost(URL_COUNTRIES);
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

      if(strURL == URL_COUNTRIES) {
	loadCountries(self.xmlHttpReq.responseText);
      }

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
    var country = form.country[form.country.selectedIndex].text

    if(iata != "" && iata.length != 3) {
      alert("IATA/FAA codes must be exactly three letters.");
      return;
    }
    if(icao != "" && icao.length != 4) {
      alert("ICAO codes must be exactly four letters.");
      return;
    }

    if(action == "SEARCH" && db == DB_DAFIF) {
      if(city != "") {
	alert("Sorry, the DAFIF database does not contain city information.");
	return;
      }
      if(code != "US" && iata != "") {
	alert("Sorry, the DAFIF database does not contain IATA codes for cities outside the United States.");
	return;
      }
    }

    if(action == "RECORD") {
      if(airport == "") {
	alert("You must enter an airport name.");
	return;
      }
      if(city == "") {
	alert("You must enter a city name.");
	return;
      }
      if(code == "") {
	alert("You must select a country.");
	return;
      }
      if(icao == "") {
	alert("You must enter an ICAO code.");
	return;
      }
      if(x == "" || y == "" || elevation == "") {
	alert("You must enter latitude, longitude and elevation.");
	return;
      }

      var re_dd = /^[-+]?\d*\.?\d*$/;
      if(! re_dd.test(x) || ! re_dd.test(y)) {
	alert("Latitude and longitude must be given as decimal degrees, where negative numbers indicate 'south' and 'west' respectively.  For example, San Francisco (SFO) is at latitude 37.618972(N), longitude -122.374889(W).");
	return;
      }

      desc = airport + ", " + city + ", " + country + " (IATA: " + (iata == "" ? "N/A" : iata)  + ", ICAO: " + icao + ")";
      quad = (parseFloat(y) < 0 ? "SOUTH" : "NORTH") + "-" + (parseFloat(x) < 0 ? "WEST" : "EAST");
      if(! confirm("Are you sure you want to add " + desc + " as a new airport, located in the " + quad + " quadrant of the world?  Please double-check the name, airport codes and exact coordinates before confirming.")) {
	document.getElementById("miniresultbox").innerHTML = "<I>Cancelled.</I>";
	return;
      }
    }

    query = 'airport=' + escape(airport) + '&' +
      'iata=' + escape(iata) + '&' +
      'icao=' + escape(icao) + '&' +
      'city=' + escape(city) + '&' +
      'country=' + escape(country) + '&' +
      'code=' + escape(code) + '&' +
      'x=' + x + '&' +
      'y=' + y + '&' +
      'elevation=' + elevation + '&' +
      'db=' + escape(db) + '&' +
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
  var airports = str.split("\n");
  var table = "<table width=100%>";
  var offset, sql;
  var db = document.forms['searchform'].db.value;
  for(a in airports) {
    var col = airports[a].split(";");

    // First line contains header info
    if(a == 0) {
      offset = parseInt(col[0]);
      max = col[1];
      sql = col[2];
      if(max == 0) {
	table += "<tr><td><i>No matches found in this database &mdash; try another?</i></td></td>";
	break;
      }
      table += "<tr><td><b>Results " + (offset+1) + " to " + Math.min(offset+10, max) + " of " + max + "</b><br></td>";

      if(max > 10) {
	table += "<td style=\"float: right\">";
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
	table += "</td>";
      }
      table += "</tr>";
      continue;
    }

    // Meat of the table
    // 0 iata, 1 icao, 2 apid, 3 x, 4 y, 5, elevation, 6 ap-name, 7 code, 8 printable-name
    if(a % 2 == 1) {
      bgcolor = "#fff";
    } else {
      bgcolor = "#ddd";
    }
    table += "<tr><td style='background-color: " + bgcolor + "'>" + col[8] + "</td>";
    if(db == DB_OPENFLIGHTS) {
      // code:apid:x:y
      id = (col[0] != "" ? col[0] : col[1]) + ":" + col[2] + ":" + col[3] + ":" + col[4];
      table += "<td style='text-align: right; background-color: " + bgcolor + "'><INPUT type='button' value='Select' onClick='selectAirport(\"" + id + "\",\"" + escape(col[7]) + "\")'></td>";
    }
    if(db == DB_DAFIF) {
      table += "<td style='text-align: right; background-color: " + bgcolor + "'><INPUT type='button' value='Load' onClick='loadDAFIFAirport(\"" + col[0] + "\",\"" + col[1] + "\",\"" + col[3] + "\",\"" + col[4] + "\",\"" + col[5] + "\",\"" + escape(col[6]) + "\",\"" + col[7] + "\")'></td>";
    }
    table += "</tr>";
  }
  table += "</table><br>";
  document.getElementById("miniresultbox").innerHTML = table;
}

// Load data from DAFIF search into form
function loadDAFIFAirport(iata, icao, x, y, elevation, name, country) {
  var b_back = document.getElementById("b_back");
  var b_fwd = document.getElementById("b_fwd");
  if(b_back) b_back.disabled = true;
  if(b_fwd) b_fwd.disabled = true;

  var form = document.forms['searchform'];
  form.airport.value = unescape(name);
  form.iata.value = iata;
  form.icao.value = icao;
  form.x.value = x;
  form.y.value = y;
  form.elevation.value = elevation;
  var country_select = form.country;
  for(index = 0; index < country_select.length; index++) {
    if(country_select[index].value == country) {
      country_select.selectedIndex = index;
    }
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
  form.iatafilter.checked = true;
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
