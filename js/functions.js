// These can't live in openflights.js, because then we also have to load OpenLayers.min.js...
// Which is potentially unnecessary!

/**
 * Validate 24-hr time ([0]0:00-23:59)
 * @type {RegExp}
 */
const RE_TIME = /(^0?[0-9]|1[0-9]|2[0-3]):?([0-5][0-9])$/;

var gt;

var eliteicons;

window.onload = function init() {
  gt = new Gettext({ domain: "messages" });

  eliteicons = {
    S: [gt.gettext("Silver Elite"), "/img/silver-star.png"],
    G: [gt.gettext("Gold Elite"), "/img/gold-star.png"],
    P: [gt.gettext("Platinum Elite"), "/img/platinum-star.png"],
    X: [
      gt.gettext("Thank you for using OpenFlights &mdash; please donate!"),
      "/img/icon-warning.png",
    ],
  };
};

/**
 * User has changed locale, reload this page with new lang attribute
 * (preserve any other attributes, but nuke anchors and overwrite existing lang if any)
 */
function changeLocale() {
  var locale = "lang=" + document.getElementById("locale").value,
    re_lang = /lang=...../,
    url = location.origin + location.pathname + location.search; // omit #anchor
  if (re_lang.test(url)) {
    url = url.replace(re_lang, locale);
  } else if (url.indexOf("?") == -1) {
    url += "?" + locale;
  } else {
    url += "&" + locale;
  }
  location.href = url;
}

/**
 * Check if DST is active
 * @param type
 * @param date
 * @param year
 * @returns {boolean}
 */
function checkDST(type, date, year) {
  switch (type) {
    case "E":
      // Europe: Last Sunday in Mar to last Sunday in Oct
      if (date >= getLastDay(year, 3, 0) && date < getLastDay(year, 10, 0)) {
        return true;
      }
      break;

    case "A":
      // US/Canada: 2nd Sunday in Mar to 1st Sunday in Nov
      if (
        date >= getNthDay(year, 3, 2, 0) &&
        date < getNthDay(year, 11, 1, 0)
      ) {
        return true;
      }
      break;

    case "S":
      // South America: Until 3rd Sunday in Mar or after 3nd Sunday in Oct
      if (
        date < getNthDay(year, 3, 3, 0) ||
        date >= getNthDay(year, 10, 3, 0)
      ) {
        return true;
      }
      break;

    case "O":
      // Australia: Until 1st Sunday in April or after 1st Sunday in Oct
      if (
        date < getNthDay(year, 4, 1, 0) ||
        date >= getNthDay(year, 10, 1, 0)
      ) {
        return true;
      }
      break;

    case "Z":
      // New Zealand: Until 1st Sunday in April or after last Sunday in Sep
      if (date < getNthDay(year, 4, 1, 0) || date >= getLastDay(year, 9, 0)) {
        return true;
      }
      break;

    default:
    // cases U, N -- do nothing
  }
  return false;
}

/**
 * Get Nth day of type X in a given month (e.g., third Sunday in March 2009)
 * @param year
 * @param month
 * @param nth
 * @param type 0 for Sun, 1 for Mon, etc
 * @returns {Date}
 */
function getNthDay(year, month, nth, type) {
  var date = new Date();
  date.setFullYear(year, month - 1, 1); // Date object months start from 0
  var day = date.getDay();
  if (type >= day) {
    nth--;
  }
  date.setDate(date.getDate() + (7 - (day - type)) + (nth - 1) * 7);
  return date;
}

/**
 * Get the last day of type X in a given month (e.g., last Sunday in March 2009)
 * @param year
 * @param month
 * @param type
 * @returns {Date}
 */
function getLastDay(year, month, type) {
  var date = new Date();
  date.setFullYear(year, month, 1); // Date object months start from 0, so this is +1
  date.setDate(date.getDate() - 1); // last day of the previous month
  date.setDate(date.getDate() - (date.getDay() - type));
  return date;
}

/**
 * Parse a time string into a float
 * @param time_str string
 * @returns {number}
 */
function parseTimeString(time_str) {
  var chunks = time_str.match(RE_TIME);
  return parseFloat(chunks[1]) + parseFloat(chunks[2] / 60);
}

/**
 * @param element code:apid:x:y:tz:dst
 * @returns {*}
 */
function getApid(element) {
  return $(element + "id").value.split(":")[1];
}

/**
 * @param element code:apid:x:y:tz:dst
 * @returns {*}
 */
function getX(element) {
  return $(element + "id").value.split(":")[2];
}

/**
 * @param element code:apid:x:y:tz:dst
 * @returns {*}
 */
function getY(element) {
  return $(element + "id").value.split(":")[3];
}

/**
 * @param element code:apid:x:y:tz:dst
 * @returns {*}
 */
function getTZ(element) {
  var tz = $(element + "id").value.split(":")[4];
  return !tz || tz == "" ? 0 : parseFloat(tz);
}

/**
 * @param element code:apid:x:y:tz:dst
 * @returns {*}
 */
function getDST(element) {
  var dst = $(element + "id").value.split(":")[5];
  return !dst || dst == "" ? "N" : dst;
}

/**
 * Return HTML string representing user's elite status icon.
 * If validity is not null, also return text description and validity period.
 * @param e {string}
 * @param validity {string|null}
 * @returns {string}
 */
function getEliteIcon(e, validity = "") {
  if (
    !e ||
    e === "" ||
    eliteicons === undefined ||
    eliteicons[e] === undefined
  ) {
    return "";
  }

  const icon = eliteicons[e];
  if (validity) {
    return (
      // TODO: Add alt tags
      "<center><img src='" +
      icon[1] +
      "' title='" +
      icon[0] +
      "' height=34 width=34 /><br><b>" +
      icon[0] +
      "</b><br><small>" +
      gt.gettext("Valid until") +
      "<br>" +
      validity +
      "</small></center>"
    );
  }
  return (
    // TODO: Add alt tags
    "<span style='float: right'><a href='/donate' target='_blank'><img src='" +
    icon[1] +
    "' title='" +
    icon[0] +
    "' height=34 width=34></a></span>"
  );
}

/**
 * Given element "select"; select option matching "value" or #0 if not found
 * @param select
 * @param value
 */
function selectInSelect(select, value) {
  if (!select) {
    return;
  }
  select.selectedIndex = 0; // default element to not be selected
  for (var index = 0; index < select.length; index++) {
    if (select[index].value == value) {
      select.selectedIndex = index;
    }
  }
}
