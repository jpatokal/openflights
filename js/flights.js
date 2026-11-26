/**
 * Shared parser for the JSON response of /php/flights.php.
 *
 * Both the legacy desktop app (openflights.js) and the mobile app
 * (mobile/app.js) load this file and use OpenFlights.parseFlights() to turn the
 * endpoint's JSON into a common flight object. Every field is exposed as a
 * string so callers can keep treating values uniformly; null/undefined fields
 * become "".
 *
 * Each object has these keys:
 *   srcCode, srcApid, dstCode, dstApid, code, date, distance, duration, seat,
 *   seatType, class, reason, fid, plane, registration, alid, note, trid, plid,
 *   alCode, srcTime, mode
 */
(function (global) {
  "use strict";

  // camelCase property -> key in the server's JSON objects.
  var FIELDS = {
    srcCode: "src_code",
    srcApid: "src_apid",
    dstCode: "dst_code",
    dstApid: "dst_apid",
    code: "code",
    date: "date",
    distance: "distance",
    duration: "duration",
    seat: "seat",
    seatType: "seat_type",
    class: "class",
    reason: "reason",
    fid: "fid",
    plane: "plane",
    registration: "registration",
    alid: "alid",
    note: "note",
    trid: "trid",
    plid: "plid",
    alCode: "al_code",
    srcTime: "src_time",
    mode: "mode",
  };

  function asText(value) {
    return value === null || value === undefined ? "" : String(value);
  }

  function normalizeFlight(raw) {
    raw = raw || {};
    var flight = {};
    Object.keys(FIELDS).forEach(function (key) {
      flight[key] = asText(raw[FIELDS[key]]);
    });
    if (flight.mode === "") {
      flight.mode = "F";
    }
    return flight;
  }

  /**
   * Parse a /php/flights.php response body.
   *
   * Returns an array of normalized flight objects. Empty bodies and non-JSON
   * responses (e.g. an error page) are treated as "no flights".
   */
  function parseFlights(responseText) {
    if (!responseText) {
      return [];
    }
    var data;
    try {
      data = JSON.parse(responseText);
    } catch (e) {
      return [];
    }
    if (!Array.isArray(data)) {
      return [];
    }
    return data.map(normalizeFlight);
  }

  global.OpenFlights = {
    parseFlights: parseFlights,
    normalizeFlight: normalizeFlight,
    fields: FIELDS,
  };
})(typeof window !== "undefined" ? window : this);
