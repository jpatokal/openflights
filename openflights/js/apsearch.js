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
  xmlhttpPost(URL_APSEARCH, offset);
}

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

      if(strURL == URL_COUNTRIES) {
	loadCountries(self.xmlHttpReq.responseText);
      }

      if(strURL == URL_APSEARCH) {
	searchResult(self.xmlHttpReq.responseText);
      }
    }
  }
  var query = "";
  if(strURL == URL_APSEARCH) {
    var form = document.forms['searchform'];
    var db = form.db.value;
    var city = form.city.value;
    var code = form.country.value;
    var iata = form.iata.value;
    var icao = form.icao.value;

    if(iata != "" && iata.length != 3) {
      alert("IATA/FAA codes must be exactly three letters.");
      return;
    }
    if(icao != "" && icao.length != 4) {
      alert("ICAO codes must be exactly four letters.");
      return;
    }

    if(db == DB_DAFIF) {
      if(city != "") {
	alert("Sorry, the DAFIF database does not contain city information.");
	return;
      }
      if(code != "US" && iata != "") {
	alert("Sorry, the DAFIF database does not contain IATA codes for cities outside the United States.");
	return;
      }
    }

    query = 'airport=' + escape(form.airport.value) + '&' +
      'iata=' + escape(iata) + '&' +
      'icao=' + escape(icao) + '&' +
      'city=' + escape(city) + '&' +
      'country=' + escape(form.country[form.country.selectedIndex].text) + '&' +
      'code=' + escape(code) + '&' +
      'db=' + escape(db) + '&' +
      'offset=' + offset + '&' +
      'iatafilter=' + form.iatafilter.checked;
    document.getElementById("miniresultbox").innerHTML = "<I>Searching...</I>";
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
	table += "<tr><td><i>No matches found.</i></td></td>";
	break;
      }
      table += "<tr><td><b>Results " + (offset+1) + " to " + Math.min(offset+10, max) + " of " + max + "</b><br></td>";

      if(max > 10) {
	table += "<td style=\"float: right\">";
	if(offset - 10 >= 0) {
	  table += "<INPUT type=\"button\" value=\"<\" onClick=\"doSearch(" + (offset-10) + ")\">";
	} else {
	  table += "<INPUT type=\"button\" value=\"<\" disabled>";
	}
	if(offset + 10 < max) {
	  table += "<INPUT type=\"button\" value=\">\" onClick=\"doSearch(" + (offset+10) + ")\">";
	} else {
	  table += "<INPUT type=\"button\" value=\">\" disabled>";
	}
	table += "</td>";
      }
      table += "</tr>";
      continue;
    }

    // Meat of the table
    table += "<tr><td>" + col[1] + "</td>";
    if(db == DB_OPENFLIGHTS) {
      table += "<td style='float: right'><INPUT type='button' value='Select' onClick='selectAirport(\"" + col[0] + "\",\"" + escape(col[1]) + "\")'></td>";
    }
    table += "</tr>";
  }
  table += "</table><br>";
  document.getElementById("miniresultbox").innerHTML = table;
}

// Clear form
function clearSearch() {
  var form = document.forms['searchform'];
  form.airport.value = "";
  form.city.value = "";
  form.country.selectedIndex = 0;
  form.iata.value = "";
  form.icao.value = "";
  form.db.selectedIndex = 0;
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
