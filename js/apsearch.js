/*
 * Search for airport in database(s)
 */

const URL_APSEARCH = "/php/apsearch.php";

const DB_OPENFLIGHTS = "airports";
const DB_OURAIRPORTS = "airports_oa";
const DB_DAFIF = "airports_dafif";

var warning;
var gt;
var query;

window.onload = function init() {
  gt = new Gettext({ domain: "messages" });
  // ...?apid=code:apid:...
  var args = window.location.href.split("?");
  if (args[1] && args[1].split("=")[0] == "apid") {
    apid = args[1].split("=")[1];
    xmlhttpPost(URL_APSEARCH, apid, "LOAD");
  }
};

function doSearch(offset) {
  xmlhttpPost(URL_APSEARCH, offset, "SEARCH");
}

function doRecord(offset) {
  xmlhttpPost(URL_APSEARCH, offset, "RECORD");
}

function doLoad(apid) {
  xmlhttpPost(URL_APSEARCH, apid, "LOAD");
}

function xmlhttpPost(strURL, offset, action) {
  var self = this;
  // Mozilla/Safari
  if (window.XMLHttpRequest) {
    self.xmlHttpReq = new XMLHttpRequest();
  }
  // IE
  else if (window.ActiveXObject) {
    self.xmlHttpReq = new ActiveXObject("Microsoft.XMLHTTP");
  }
  self.xmlHttpReq.open("POST", strURL, true);
  self.xmlHttpReq.setRequestHeader(
    "Content-Type",
    "application/x-www-form-urlencoded"
  );
  self.xmlHttpReq.onreadystatechange = function () {
    if (self.xmlHttpReq.readyState == 4) {
      if (self.xmlHttpReq.status != 200) {
        document.getElementById("miniresultbox").innerHTML =
          self.xmlHttpReq.statusText;
        return;
      }
      if (strURL == URL_APSEARCH) {
        if (action == "SEARCH") {
          searchResult(self.xmlHttpReq.responseText);
        } else if (action == "RECORD") {
          recordResult(self.xmlHttpReq.responseText);
        } else if (action == "LOAD") {
          loadAirport(self.xmlHttpReq.responseText);
        }
      }
    }
  };
  if (strURL == URL_APSEARCH) {
    var form = document.forms["searchform"];
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
    var country = form.country[form.country.selectedIndex].text;
    var apid = form.apid.value;

    if (iata != "" && iata.length != 3) {
      alert(gt.gettext("IATA/FAA codes must be exactly three letters."));
      form.iata.focus();
      return;
    } else {
      iata = iata.toUpperCase();
      form.iata.value = iata;
    }
    if (["XXX", "YYY", "ZZZ"].indexOf(iata) > -1) {
      alert(gt.gettext("Invalid IATA code."));
      form.iata.focus();
      return;
    }
    if (icao != "" && icao.length != 4) {
      alert(gt.gettext("ICAO codes must be exactly four letters."));
      form.icao.focus();
      return;
    } else {
      icao = icao.toUpperCase();
      form.icao.value = icao;
    }
    var re_alphanum = /^[-.\'a-zA-Z0-9 ]*$/;
    if (!re_alphanum.test(airport) || !re_alphanum.test(city)) {
      alert(
        gt.gettext(
          "Only the unaccented letters A-Z, the numbers 0-9, the punctuation marks -.' (dash, period, apostrophe) and spaces can be used in airport and city names."
        )
      );
      return;
    }

    if (action == "SEARCH" && db == DB_DAFIF) {
      if (city != "") {
        warning = gt.strargs(
          gt.gettext(
            "Ignoring city '%1', since the DAFIF database does not contain city information."
          ),
          [city]
        );
        city = "";
      }
      if (iata != "") {
        switch (code) {
          case "US":
            // do nothing
            break;

          case "":
            warning = gt.strargs(
              gt.gettext(
                "Search for IATA/FAA code '%1' limited to United States airports, since DAFIF does not contain IATA codes for cities outside the US."
              ),
              [iata]
            );
            code = "US";
            break;

          default:
            warning = gt.strargs(
              gt.gettext(
                "Ignoring IATA code '%1', since DAFIF does not contain IATA codes for cities outside the United States."
              ),
              [iata]
            );
            iata = "";
            break;
        }
      }
    } else if (action == "LOAD") {
      apid = offset; // ugly hack!
    } else if (action == "RECORD") {
      if (airport == "") {
        alert(gt.gettext("Please enter an airport name."));
        form.airport.focus();
        return;
      } else {
        airport = airport.substring(0, 1).toUpperCase() + airport.substring(1);
        form.airport.value = airport;
      }

      if (city == "") {
        alert(gt.gettext("Please enter a city name."));
        form.city.focus();
        return;
      } else {
        city = city.substring(0, 1).toUpperCase() + city.substring(1);
        form.city.value = city;
      }

      if (code == "") {
        alert(gt.gettext("Please select a country."));
        form.country.focus();
        return;
      }

      if (x == "" || y == "" || elevation == "") {
        alert(
          gt.gettext(
            "Please enter latitude, longitude and elevation. Tip: Check if the OurAirport database already contains your airport, and 'Load' the data from there."
          )
        );
        form.x.focus();
        return;
      }

      var re_dd = /^[-+]?\d*\.\d{3,}$/;
      if (!re_dd.test(x) || !re_dd.test(y)) {
        alert(
          gt.gettext(
            "Latitude and longitude must be given as decimal degrees, where negative numbers indicate 'south' and 'west' respectively, and with at least three digits of precision (after the decimal point). For example, San Francisco (SFO) is at latitude 37.6189(N), longitude -122.3748(W)."
          )
        );
        form.x.focus();
        return;
      }

      if (Math.abs(x) > 180) {
        alert(
          gt.gettext(
            "Longitude must be in the range -180 (west) to 180 (east) degrees."
          )
        );
        form.x.focus();
        return;
      }

      if (Math.abs(y) > 90) {
        alert(
          gt.gettext(
            "Latitude must be in the range 90 (north) to -90 (south) degrees."
          )
        );
        form.y.focus();
        return;
      }

      if (elevation < 0) {
        alert(gt.gettext("Please enter a positive number for elevation."));
        form.elevation.focus();
        return;
      }

      var re_tz = /^[-+]?\d*\.?\d*$/;
      if (tz == "" || !re_tz.test(tz) || Math.abs(tz) > 14) {
        alert(
          gt.gettext(
            "Please enter a timezone as an offset from UTC/GMT, eg. +8 for Singapore or -5 for New York. Use decimals for fractional timezones, eg. +5.75 for Nepal."
          )
        );
        form.tz.focus();
        return;
      }
      if (dst == "U") {
        if (
          !confirm(
            gt.gettext(
              "You have not entered whether this airport follows Daylight Savings Time (DST). Leave it as Unknown?"
            )
          )
        ) {
          form.dst.focus();
          return;
        }
      }
      if (iata == "") {
        if (
          !confirm(
            gt.gettext(
              "You have not entered an IATA/FAA code. Are you sure the airport does not have one and you wish to proceed?"
            )
          )
        ) {
          form.iata.focus();
          return;
        }
      }
      if (icao == "") {
        if (
          !confirm(
            gt.gettext(
              "You have not entered an ICAO code. Are you sure the airport does not have one and you wish to proceed?"
            )
          )
        ) {
          form.icao.focus();
          return;
        }
      }

      // Last check for new airports only
      if (apid == "") {
        var desc =
          airport +
          ", " +
          city +
          ", " +
          country +
          " (" +
          gt.gettext("IATA") +
          ": " +
          (iata == "" ? gt.gettext("N/A") : iata) +
          ", " +
          gt.gettext("ICAO") +
          ": " +
          (icao == "" ? gt.gettext("N/A") : icao) +
          ")";
        var quad =
          (parseFloat(y) < 0 ? gt.gettext("SOUTH") : gt.gettext("NORTH")) +
          "-" +
          (parseFloat(x) < 0 ? gt.gettext("WEST") : gt.gettext("EAST"));
        if (
          !confirm(
            gt.strargs(
              gt.gettext(
                "Are you sure you want to add %1 as a new airport, located in the %2 quadrant of the world? Please double-check the name, airport codes and exact coordinates before confirming."
              ),
              [desc, quad]
            )
          )
        ) {
          var box = getElement("miniresultbox");
          box.style.color = "FF0000";
          box.innerHTML = "<p><i>" + gt.gettext("Cancelled.") + "</i></p>";
          return;
        }
      }
    }

    // Build a new query
    if (action != "SEARCH" || (action == "SEARCH" && offset == 0)) {
      query =
        "name=" +
        encodeURIComponent(airport) +
        "&" +
        "iata=" +
        encodeURIComponent(iata) +
        "&" +
        "icao=" +
        encodeURIComponent(icao) +
        "&" +
        "city=" +
        encodeURIComponent(city) +
        "&" +
        "country=" +
        encodeURIComponent(country) +
        "&" +
        "code=" +
        encodeURIComponent(code) +
        "&" +
        "x=" +
        x +
        "&" +
        "y=" +
        y +
        "&" +
        "elevation=" +
        elevation +
        "&" +
        "timezone=" +
        tz +
        "&" +
        "dst=" +
        dst +
        "&" +
        "db=" +
        encodeURIComponent(db) +
        "&" +
        "iatafilter=" +
        form.iatafilter.checked +
        "&" +
        "apid=" +
        apid +
        "&" +
        "action=" +
        action;
    }
  }
  var box = getElement("miniresultbox");
  box.style.color = "#222";
  box.innerHTML = "<p><i>" + describe(action) + "</i></p>";
  self.xmlHttpReq.send(query + "&offset=" + offset);
}

function describe(action) {
  switch (action) {
    case "SEARCH":
      return gt.gettext("Searching...");
    case "LOAD":
      return gt.gettext("Loading...");
    case "RECORD":
      return gt.gettext("Recording...");
  }
}

/**
 * Display results of search
 */
function searchResult(str) {
  var json = JSON.parse(str);
  var airports = json["airports"];
  var table = "<table width=95% cellspacing=0>";
  var offset = json["offset"];
  var db = document.forms["searchform"].db.value;
  var disclaimer = "";

  if (warning) {
    table +=
      "<tr><td colspan=2><i><font color='red'>" +
      warning +
      "</font></i></td></tr>";
    warning = null;
  }

  if (json.hasOwnProperty("status") && json["status"] === 0) {
    // TODO: We're ignoring json["message"]... Do we care?
    table +=
      "<tr><td><i>" +
      gt.gettext("No matches found in this database.") +
      "<br><ul>";
    if (document.forms["searchform"].iatafilter.checked) {
      table +=
        "<li>" +
        gt.gettext(
          "Try unchecking 'Show only major airports' and search again."
        ) +
        "</li>";
    }
    if (document.forms["searchform"].db.value != DB_OURAIRPORTS) {
      table +=
        "<li>" +
        gt.gettext("Switch to the OurAirports database and search again.") +
        "</li>";
    }
    table += "</ul></td></tr>";
  } else {
    var max = json["max"];
    table +=
      "<tr><td><b>" +
      gt.strargs(gt.gettext("Results %1 to %2 of %3"), [
        offset + 1,
        Math.min(offset + 10, max),
        max,
      ]) +
      "</b><br></td>";
    if (max > 10) {
      table += '<td style="text-align: right"><nobr>';
      if (offset - 10 >= 0) {
        table +=
          '<input id="b_back" type="button" value="<" onClick="doSearch(' +
          (offset - 10) +
          ')">';
      } else {
        table += '<input type="button" value="<" disabled>';
      }
      if (offset + 10 < max) {
        table +=
          '<input id="b_fwd" type="button" value=">" onClick="doSearch(' +
          (offset + 10) +
          ')">';
      } else {
        table += '<input type="button" value=">" disabled>';
      }
      table += "</nobr></td>";
    }
    table += "</tr>";

    for (var a in airports) {
      // Meat of the table

      var col = airports[a];
      var bgcolor = a % 2 == 1 ? "#fff" : "#ddd";
      switch (col["ap_uid"]) {
        case "user":
          bgcolor = "#fdd";
          disclaimer =
            "<br><span style='background-color: " +
            bgcolor +
            "'>" +
            gt.gettext(
              "Airports in pink have been added by users of OpenFlights."
            ) +
            "</span>";
          break;

        case "own":
          bgcolor = "#ddf";
          disclaimer =
            "<br><span style='background-color: " +
            bgcolor +
            "'>" +
            // TODO: Or you're an admin...
            gt.gettext(
              "Airports in blue have been added by you and can be edited."
            ) +
            "<span>";
          break;
      }

      table +=
        "<tr><td style='background-color: " +
        bgcolor +
        "'>" +
        col["ap_name"] +
        "</td>";
      if (db == DB_OPENFLIGHTS && isEditMode()) {
        // code:apid:x:y:tz:dst
        var id =
          (col["iata"] != "" ? col["iata"] : col["icao"]) +
          ":" +
          col["apid"] +
          ":" +
          col["x"] +
          ":" +
          col["y"] +
          ":" +
          col["timezone"] +
          ":" +
          col["dst"];
        table +=
          "<td style='text-align: right; background-color: " +
          bgcolor +
          "'><input type='button' value='" +
          gt.gettext("Select") +
          "' onClick='selectAirport(\"" +
          id +
          '","' +
          encodeURIComponent(col["ap_name"]) +
          "\")'></td>";
      }
      if (db != DB_OPENFLIGHTS || col["ap_uid"] == "own" || !isEditMode()) {
        var label =
          col["ap_uid"] == "own" && db == DB_OPENFLIGHTS
            ? gt.gettext("Edit")
            : gt.gettext("Load");
        table +=
          "<td style='text-align: right; background-color: " +
          bgcolor +
          "'><input type='button' value='" +
          label +
          "' onClick='doLoad(\"" +
          col["apid"] +
          "\")'></td>";
      }
      table += "</tr>";
    }
  }
  table += "</table>";
  table += disclaimer;
  var box = getElement("miniresultbox");
  box.style.color = "#222";
  box.innerHTML = table;
}

/**
 * Load data from the search results into the form
 * @param data
 */
function loadAirport(data) {
  var json = JSON.parse(data);
  // status != 1 is an error
  if (json["status"] != 1 || json["max"] == 0) {
    var box = getElement("miniresultbox");
    box.style.color = "#FF0000";
    box.innerHTML =
      "<p>" + gt.gettext("No matches found in this database.") + "</p>";
    return;
  }
  var col = json["airports"][0];

  var form = document.forms["searchform"];
  form.airport.value = col["name"];
  form.city.value = col["city"];
  form.iata.value = col["iata"];
  form.icao.value = col["icao"];
  form.x.value = col["x"];
  form.y.value = col["y"];
  form.elevation.value = col["elevation"];
  if (col["timezone"] != null) {
    form.tz.value = col["timezone"];
  }
  var country = col["country"];
  var country_select = form.country;
  for (var index = 0; index < country_select.length; index++) {
    if (
      country_select[index].value == country ||
      country_select[index].text == country
    ) {
      country_select.selectedIndex = index;
    }
  }
  var dst_select = form.dst;
  for (index = 0; index < dst_select.length; index++) {
    if (dst_select[index].value == col["dst"]) {
      dst_select.selectedIndex = index;
    }
  }

  form.apid.value = col["apid"];
  getElement("b_add").style.display = "none";
  getElement("b_edit").style.display = "inline";
  getElement("b_edit").disabled = true;
  var box = getElement("miniresultbox");
  box.innerHTML = "";
  box.style.color = "#222";
}

/**
 * Did we manage to record the airport?
 * @param str {string}
 */
function recordResult(str) {
  var json = JSON.parse(str);
  var box = getElement("miniresultbox");
  // Success
  if (json["status"] == "1") {
    alert(json["message"]);
    // Select newly minted airport and return to main
    var form = document.forms["searchform"];
    var iata = form.iata.value;
    var country = form.country[form.country.selectedIndex].text;

    // code:apid:x:y
    var code = iata != "" ? iata : form.icao.value;
    // city-airport (code), country
    var data =
      code + ":" + json["apid"] + ":" + form.x.value + ":" + form.y.value;
    name =
      form.city.value +
      "-" +
      form.airport.value +
      " (" +
      code +
      "), " +
      country;
    // TODO: The alert is annoying... Change the save button for select instead?
    // https://github.com/jpatokal/openflights/issues/1352
    selectAirport(data, name);
    // Probably should be green/blue for some sort of success
    box.style.color = "#222";
  } else {
    // Red for error
    box.style.color = "#FF0000";
  }
  box.innerHTML = "<p>" + json["message"] + "</p>";
  getElement("b_edit").disabled = true;
}

function setEdited() {
  if (isLoggedIn()) {
    if (getElement("b_edit").style.display == "inline") {
      getElement("b_edit").disabled = false;
    } else {
      getElement("b_add").disabled = false;
    }
  }
}

/**
 * Clear form -- everything *except* the database
 */
function clearSearch() {
  var form = document.forms["searchform"];
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
  getElement("b_add").style.display = "inline";
  getElement("b_add").disabled = true;
  getElement("b_edit").style.display = "none";
  var box = getElement("miniresultbox");
  box.style.color = "#222";
  box.innerHTML = "";
}

function isLoggedIn() {
  if (!parent.opener || !parent.opener.addNewAirport) {
    // If airport search was loaded without OpenFlights, we're not in edit mode
    return false;
  } else {
    return parent.opener.logged_in;
  }
}

function isEditMode() {
  return isLoggedIn() && parent.opener.isEditMode();
}

/**
 * Airport selected, kick it back to the main window and close this
 * @param data
 * @param name
 */
function selectAirport(data, name) {
  parent.opener.addNewAirport(data, unescape(name));
  window.close();
}

function getElement(id) {
  return document.getElementById(id);
}

// A dupe from openflights.js...
function help(context) {
  window.open(
    "/help/" + context + ".php",
    "OpenFlights Help: " + context,
    "width=500,height=400,scrollbars=yes"
  );
}
