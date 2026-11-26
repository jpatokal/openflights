/**
 * Shared parser for the JSON responses of /php/map.php and /php/routes.php.
 *
 * Both the legacy desktop app (openflights.js) and the mobile app
 * (mobile/app.js) load this file and use OpenFlights.parseMap() to turn the
 * endpoint JSON into a common map object:
 *
 *   stats      { segments, distanceStr, durationMin, public, elite, loggedIn,
 *                editor, challenge, apid, count, description }
 *   flightPairs [{ srcApid, srcLon, srcLat, dstApid, dstLon, dstLat, count,
 *                  distance, future, mode }]
 *   airports   [{ code, apid, lon, lat, timezone, dst, name, city,
 *                 country, count, formatted, future }]
 *   trips      [{ trid, name, url }]
 *   airlines   [{ alid, name }]
 *   airlinesAll boolean (false for airline route maps, which have no "All")
 *   years      [{ year, label }]
 *   modes      [{ code, label }]
 *   classes    [{ code, label }]
 */
(function (global) {
  "use strict";

  var OpenFlights = global.OpenFlights || (global.OpenFlights = {});

  function asText(value) {
    return value === null || value === undefined ? "" : String(value);
  }

  function asNumber(value) {
    var n = parseFloat(value);
    return isNaN(n) ? 0 : n;
  }

  function asInt(value) {
    var n = parseInt(value, 10);
    return isNaN(n) ? 0 : n;
  }

  function emptyMap() {
    return {
      stats: {
        segments: "0",
        distanceStr: "",
        durationMin: "0",
        public: "",
        elite: "",
        loggedIn: "demo",
        editor: "",
        challenge: "",
        apid: "",
        count: "0",
        description: "",
      },
      flightPairs: [],
      airports: [],
      trips: [],
      airlines: [],
      airlinesAll: true,
      years: [],
      modes: [],
      classes: [],
    };
  }

  function parseFlightPair(raw) {
    raw = raw || {};
    return {
      srcApid: asText(raw.src_apid),
      srcLon: asNumber(raw.src_lon),
      srcLat: asNumber(raw.src_lat),
      dstApid: asText(raw.dst_apid),
      dstLon: asNumber(raw.dst_lon),
      dstLat: asNumber(raw.dst_lat),
      count: asInt(raw.count),
      distance: asText(raw.distance),
      future: asText(raw.future),
      mode: asText(raw.mode) || "F",
    };
  }

  function parseAirport(raw) {
    raw = raw || {};
    return {
      code: asText(raw.code),
      apid: asText(raw.apid),
      lon: asNumber(raw.lon),
      lat: asNumber(raw.lat),
      timezone: asText(raw.timezone),
      dst: asText(raw.dst),
      name: asText(raw.name),
      city: asText(raw.city),
      country: asText(raw.country),
      count: asInt(raw.count),
      formatted: asText(raw.formatted),
      future: asText(raw.future),
    };
  }

  /**
   * Parse a /php/map.php or /php/routes.php response body.
   *
   * Empty bodies and non-JSON responses are treated as an empty map.
   */
  function parseMap(responseText) {
    if (!responseText) {
      return emptyMap();
    }

    var data;
    try {
      data = JSON.parse(responseText);
    } catch (e) {
      return emptyMap();
    }
    if (!data || typeof data !== "object" || Array.isArray(data)) {
      return emptyMap();
    }

    var stats = data.stats || {};
    return {
      stats: {
        segments: asText(stats.count),
        distanceStr: asText(stats.distance),
        durationMin: asText(stats.duration),
        public: asText(stats.public),
        elite: asText(stats.elite),
        loggedIn: asText(stats.logged_in) || "demo",
        editor: asText(stats.editor),
        challenge: asText(stats.challenge),
        apid: asText(stats.apid),
        count: asText(stats.count),
        description: asText(stats.description),
      },
      flightPairs: (data.flights || []).map(parseFlightPair),
      airports: (data.airports || []).map(parseAirport),
      trips: (data.trips || []).map(function (trip) {
        return {
          trid: asText(trip.trid),
          name: asText(trip.name),
          url: asText(trip.url),
        };
      }),
      airlines: (data.airlines || []).map(function (airline) {
        return {
          alid: asText(airline.alid),
          name: asText(airline.name),
        };
      }),
      airlinesAll: data.airlines_all !== false,
      years: (data.years || []).map(function (year) {
        return {
          year: asText(year.year),
          label: asText(year.label),
        };
      }),
      modes: (data.modes || []).map(function (mode) {
        return {
          code: asText(mode.code),
          label: asText(mode.label),
        };
      }),
      classes: (data.classes || []).map(function (clazz) {
        return {
          code: asText(clazz.code),
          label: asText(clazz.label),
        };
      }),
    };
  }

  OpenFlights.parseMap = parseMap;
})(typeof window !== "undefined" ? window : this);
