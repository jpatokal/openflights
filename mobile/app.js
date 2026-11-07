/**
 * OpenFlights Mobile — a read-only, mobile-first viewer for flight logs.
 *
 * This app is a completely independent client. It reads user data through the
 * existing PHP endpoints under /php/ and never writes to them (no submit/edit).
 *
 * Dependencies (loaded by index.html, all read-only existing assets):
 *   - /js/OpenLayers.min.js  -> OpenLayers 2 global
 *   - /js/greatcircle.js     -> gcPath() for great-circle route geometry
 *   - /js/md5.min.js         -> MD5() for login/guest-password hashing
 */
(function () {
  "use strict";

  // --------------------------------------------------------------------------
  // Constants
  // --------------------------------------------------------------------------
  const API = {
    MAP: "/php/map.php",
    FLIGHTS: "/php/flights.php",
    STATS: "/php/stats.php",
    LOGIN: "/php/login.php",
    LOGOUT: "/php/logout.php",
  };

  // Same palette and semantics used by openflights.js.
  const MODE_COLORS = {
    F: "#ee9900", // flight
    T: "#ee5555", // train
    R: "#9f6500", // road
    S: "#00ccff", // ship
  };
  const MODE_NAMES = { F: "Flight", T: "Train", S: "Ship", R: "Road" };
  // Material Design icons, in the same order as the mode keys above.
  const MODE_ICON_PATHS = {
    F: "M21 16v-2l-8-5V3.5a1.5 1.5 0 0 0-3 0V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5L21 16z",
    T: "M12 2c-4 0-8 .5-8 4v9.5C4 17.43 5.57 19 7.5 19L6 20.5v.5h2.23l2-2H14l2 2H18v-.5L16.5 19c1.93 0 3.5-1.57 3.5-3.5V6c0-3.5-3.58-4-8-4zM7.5 17a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm3.5-7H6V6h5v4zm5 7a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm1-7h-5V6h5v4z",
    R: "M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z",
    S: "M20 21c-1.39 0-2.78-.47-4-1.32-2.44 1.71-5.56 1.71-8 0C6.78 20.53 5.39 21 4 21H2v2h2c1.38 0 2.74-.35 4-.99 2.52 1.29 5.48 1.29 8 0 1.26.65 2.62.99 4 .99h2v-2h-2zM3.95 19H4c1.6 0 3.02-.88 4-2 .98 1.12 2.4 2 4 2s3.02-.88 4-2c.98 1.12 2.4 2 4 2h.05l1.89-6.68c.08-.26.06-.54-.06-.78s-.34-.42-.6-.5L20 10.62V6c0-1.1-.9-2-2-2h-3V1H9v3H6c-1.1 0-2 .9-2 2v4.62l-1.29.42c-.26.08-.48.26-.6.5s-.14.52-.06.78L3.95 19zM6 6h12v3.97L12 8 6 9.97V6z",
  };
  const SVG_NS = "http://www.w3.org/2000/svg";
  const CLASS_NAMES = {
    Y: "Economy",
    P: "Prem. Eco",
    C: "Business",
    F: "First",
    "": "—",
  };
  const REASON_NAMES = {
    B: "Work",
    L: "Leisure",
    C: "Crew",
    O: "Other",
    "": "—",
  };
  const SEAT_NAMES = { W: "Window", A: "Aisle", M: "Middle", "": "—" };
  const AIRPORT_COLOR = "#e8890c";

  // --------------------------------------------------------------------------
  // State
  // --------------------------------------------------------------------------
  const state = {
    mode: "demo", // 'demo' | 'user' | 'self'
    username: "",
    guestpw: "",
    challenge: "",
    year: "0",
    carrier: "0",
    currentView: "overview",
    flightsQuery: "",
    flightsSort: "date-desc",
    airportFilter: null, // { apid, code, name }
    mapInitialized: false,
    loading: false,
    canViewFlights: true,
    statsError: "",
    airlineNames: new Map(), // alid -> name
    tripNames: new Map(), // trid -> name
    data: {
      mapStats: null,
      flightPairs: [],
      airports: [],
      airlines: [],
      years: [],
      flights: [],
      stats: null,
    },
  };

  // --------------------------------------------------------------------------
  // DOM references
  // --------------------------------------------------------------------------
  const els = {
    overview: document.getElementById("view-overview"),
    flights: document.getElementById("view-flights"),
    flightList: document.getElementById("flight-list"),
    flightCount: document.getElementById("flight-count"),
    flightSearch: document.getElementById("flight-search"),
    flightSort: document.getElementById("flight-sort"),
    airportFilter: document.getElementById("airport-filter"),
    airportFilterText: document.getElementById("airport-filter-text"),
    airportFilterClear: document.getElementById("airport-filter-clear"),
    flightsToolbar: document.querySelector(".flights-toolbar"),
    yearSelect: document.getElementById("year-select"),
    carrierSelect: document.getElementById("carrier-select"),
    userChip: document.getElementById("user-chip"),
    sheet: document.getElementById("user-sheet"),
    sheetBackdrop: document.getElementById("sheet-backdrop"),
    sheetStatus: document.getElementById("sheet-status"),
    sheetClose: document.getElementById("sheet-close"),
    chooser: document.getElementById("airport-chooser"),
    chooserBackdrop: document.getElementById("chooser-backdrop"),
    chooserClose: document.getElementById("chooser-close"),
    chooserSubtitle: document.getElementById("chooser-subtitle"),
    chooserList: document.getElementById("chooser-list"),
    chooserZoom: document.getElementById("chooser-zoom"),
    loginForm: document.getElementById("login-form"),
    loginUsername: document.getElementById("login-username"),
    loginPassword: document.getElementById("login-password"),
    logoutBtn: document.getElementById("logout-btn"),
    loadingBar: document.getElementById("loading-bar"),
    toast: document.getElementById("toast"),
  };

  // --------------------------------------------------------------------------
  // Small DOM / string helpers
  // --------------------------------------------------------------------------
  function el(tag, attrs, children) {
    const node = document.createElement(tag);
    if (attrs) {
      for (const key of Object.keys(attrs)) {
        const v = attrs[key];
        if (key === "class") node.className = v;
        else if (key === "html") node.innerHTML = v;
        else if (key === "text") node.textContent = v == null ? "" : String(v);
        else if (key.startsWith("on") && typeof v === "function")
          node.addEventListener(key.slice(2), v);
        else if (v !== false && v != null && v !== "")
          node.setAttribute(key, v === true ? "" : v);
      }
    }
    if (children != null) {
      const kids = Array.isArray(children) ? children : [children];
      for (const c of kids) {
        if (c == null || c === false) continue;
        node.appendChild(
          typeof c === "string" || typeof c === "number"
            ? document.createTextNode(String(c))
            : c
        );
      }
    }
    return node;
  }

  function num(n) {
    const v = parseFloat(n);
    return isNaN(v) ? 0 : v;
  }

  function formatNumber(n) {
    return new Intl.NumberFormat("en-US").format(Math.round(num(n)));
  }

  function stripHtml(str) {
    const d = document.createElement("div");
    d.innerHTML = str || "";
    return d.textContent || "";
  }

  function escapeHtml(str) {
    return String(str == null ? "" : str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function unitSuffix() {
    const u = state.data.stats && state.data.stats.distance_unit;
    return u === "km" ? "km" : "mi";
  }

  function formatDurationMin(min) {
    const m = Math.max(0, Math.floor(num(min)));
    const days = Math.floor(m / 1440);
    const hours = Math.floor((m % 1440) / 60);
    const mins = m % 60;
    const parts = [];
    if (days) parts.push(days + "d");
    if (hours || days) parts.push(hours + "h");
    parts.push(mins + "m");
    return parts.join(" ");
  }

  async function post(url, params) {
    const res = await fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },
      credentials: "same-origin",
      body: new URLSearchParams(params).toString(),
    });
    if (!res.ok) throw new Error("HTTP " + res.status);
    return res.text();
  }

  // --------------------------------------------------------------------------
  // Response parsers
  // --------------------------------------------------------------------------
  function parseTabRows(line) {
    if (!line) return [];
    return line.split("\t").filter(function (s) {
      return s.trim() !== "";
    });
  }

  function parseMap(text) {
    const lines = text.split("\n");
    const statsLine = (lines[0] || "").trim();
    const cols = statsLine ? statsLine.split(";") : [];

    const flightPairs = parseTabRows(lines[1]).map(function (row) {
      const c = row.split(";");
      return {
        srcApid: c[0] || "",
        srcLon: num(c[1]),
        srcLat: num(c[2]),
        dstApid: c[3] || "",
        dstLon: num(c[4]),
        dstLat: num(c[5]),
        count: parseInt(c[6], 10) || 0,
        distance: c[7] || "",
        future: c[8] || "",
        mode: c[9] || "F",
      };
    });

    const airports = parseTabRows(lines[2]).map(function (row) {
      const c = row.split(";");
      const apdata = (c[0] || "").split(":");
      return {
        code: apdata[0] || "",
        apid: apdata[1] || "",
        lon: num(apdata[2]),
        lat: num(apdata[3]),
        name: c[1] || "",
        city: c[2] || "",
        country: c[3] || "",
        count: parseInt(c[4], 10) || 0,
        formatted: c[5] || "",
        future: c[6] || "",
      };
    });

    const trips = parseTabRows(lines[3]).map(function (row) {
      const c = row.split(";");
      return { trid: c[0] || "", name: c[1] || "", url: c[2] || "" };
    });

    const airlines = parseTabRows(lines[4]).map(function (row) {
      const c = row.split(";");
      return { alid: c[0] || "", name: c[1] || "" };
    });

    const years = parseTabRows(lines[5]).map(function (row) {
      const c = row.split(";");
      return { year: c[0] || "", label: c[1] || "" };
    });

    return {
      stats: {
        segments: cols[0] || "0",
        distanceStr: cols[1] || "",
        durationMin: cols[2] || "0",
        public: cols[3] || "",
        elite: cols[4] || "",
        loggedIn: cols[5] || "demo",
        editor: cols[6] || "",
        challenge: cols[7] || "",
      },
      flightPairs: flightPairs,
      airports: airports,
      trips: trips,
      airlines: airlines,
      years: years,
    };
  }

  function parseFlights(text) {
    if (!text) return [];
    return text
      .split("\n")
      .filter(function (l) {
        return l.trim() !== "";
      })
      .map(function (line) {
        const c = line.split("\t");
        return {
          srcCode: c[0] || "",
          srcApid: c[1] || "",
          dstCode: c[2] || "",
          dstApid: c[3] || "",
          code: c[4] || "",
          date: c[5] || "",
          distance: c[6] || "0",
          duration: c[7] || "",
          seat: c[8] || "",
          seatType: c[9] || "",
          class: c[10] || "",
          reason: c[11] || "",
          fid: c[12] || "",
          plane: c[13] || "",
          registration: c[14] || "",
          alid: c[15] || "",
          note: c[16] || "",
          trid: c[17] || "",
          plid: c[18] || "",
          alCode: c[19] || "",
          srcTime: c[20] || "",
          mode: c[21] || "F",
        };
      });
  }

  function parseStats(text) {
    try {
      return JSON.parse(text);
    } catch (e) {
      return null;
    }
  }

  function parseError(text) {
    if (text.indexOf("Error;") === 0) {
      return stripHtml(text.slice("Error;".length));
    }
    return stripHtml(text);
  }

  // --------------------------------------------------------------------------
  // Data loading
  // --------------------------------------------------------------------------
  function buildBaseParams() {
    const p = {};
    if (state.mode === "user" && state.username) {
      p.user = state.username;
      if (state.guestpw) p.guestpw = state.guestpw;
    } else {
      p.user = "0";
    }
    p.trid = "0";
    p.alid = state.carrier || "0";
    if (state.year && state.year !== "0") p.year = state.year;
    return p;
  }

  async function loadAll() {
    if (state.loading) return;
    state.loading = true;
    setLoading(true);
    try {
      // map.php must run first: when viewing a public user it seeds the
      // openuid session that flights.php relies on.
      const mapParams = Object.assign({}, buildBaseParams(), { param: "true" });
      const mapText = await post(API.MAP, mapParams);
      if (mapText.indexOf("Error;") === 0 || mapText.indexOf("Signup") === 0) {
        throw new Error(parseError(mapText));
      }

      const mapData = parseMap(mapText);
      state.data.mapStats = mapData.stats;
      state.data.flightPairs = mapData.flightPairs;
      state.data.airports = mapData.airports;
      state.data.airlines = mapData.airlines;
      state.data.years = mapData.years;
      state.challenge = mapData.stats.challenge;

      state.airlineNames = new Map();
      mapData.airlines.forEach(function (a) {
        state.airlineNames.set(a.alid, a.name);
      });
      state.tripNames = new Map();
      mapData.trips.forEach(function (t) {
        state.tripNames.set(t.trid, t.name);
      });

      // Reflect session login state.
      const loggedIn = mapData.stats.loggedIn;
      if (loggedIn && loggedIn !== "demo") {
        state.mode = "self";
        state.username = loggedIn;
      } else if (state.mode === "self") {
        // Session expired: fall back to demo.
        state.mode = "demo";
        state.username = "";
        state.guestpw = "";
        state.carrier = "0";
        state.airportFilter = null;
      }

      // Detailed flights are only revealed for "Open" profiles (or when we
      // own the data). For "Public"/"Private" profiles the API does not
      // expose the flight list, so skip it rather than showing demo data.
      state.canViewFlights =
        state.mode !== "user" || mapData.stats.public === "O";

      const [flightsText, statsText] = await Promise.all([
        state.canViewFlights
          ? post(API.FLIGHTS, buildBaseParams())
          : Promise.resolve(""),
        post(API.STATS, buildBaseParams()),
      ]);

      state.data.flights = state.canViewFlights
        ? parseFlights(flightsText)
        : [];

      const statsParsed = parseStats(statsText);
      if (statsParsed && statsParsed.error) {
        // Some profiles (e.g. guest-password protected) expose the map but not
        // the stats endpoint. Degrade gracefully instead of failing the load.
        state.data.stats = null;
        state.statsError = stripHtml(statsParsed.error);
      } else {
        state.data.stats = statsParsed;
        state.statsError = "";
      }
    } catch (err) {
      state.loading = false;
      setLoading(false);
      showError(err && err.message ? err.message : String(err));
      return;
    }
    state.loading = false;
    setLoading(false);

    renderUserChip();
    renderYearSelect();
    const carrierValid = renderCarrierSelect();
    renderOverview();
    renderFlightList();
    if (state.mapInitialized) drawMap();

    if (!carrierValid) {
      // Reload now that the stale carrier filter has been cleared.
      loadAll();
    }
  }

  function setLoading(on) {
    els.loadingBar.hidden = !on;
  }

  function showError(msg) {
    const clean = stripHtml(msg || "Something went wrong.");
    toast(clean);
    els.overview.textContent = "";
    els.overview.appendChild(
      el("div", { class: "error-card" }, [
        el("div", { text: clean }),
        el("div", { class: "error-card__actions" }, [
          el("button", {
            class: "btn",
            type: "button",
            text: "Retry",
            onclick: function () {
              loadAll();
            },
          }),
        ]),
      ])
    );
    els.flightCount.textContent = "";
    els.flightList.textContent = "";
  }

  // --------------------------------------------------------------------------
  // Overview rendering
  // --------------------------------------------------------------------------
  function heroItem(value, label) {
    return el("div", { class: "hero__item" }, [
      el("div", { class: "hero__value", text: value }),
      el("div", { class: "hero__label", text: label }),
    ]);
  }

  function card(title, content) {
    return el("section", { class: "card" }, [
      el("h2", { class: "card__title", text: title }),
      content,
    ]);
  }

  function tile(value, label) {
    return el("div", { class: "tile" }, [
      el("div", {
        class: "tile__value",
        text: value == null ? "—" : String(value),
      }),
      el("div", { class: "tile__label", text: label }),
    ]);
  }

  function recordRow(label, rec, unit) {
    const distance = formatNumber(rec.distance) + " " + unit;
    const route = (rec.src_code || "?") + " → " + (rec.dst_code || "?");
    return el("div", { class: "stat-row" }, [
      el("div", { class: "stat-row__label" }, [
        el("div", { class: "stat-row__name", text: label }),
        el("div", { class: "stat-row__sub", text: route }),
      ]),
      el("div", { class: "stat-row__value" }, [
        el("strong", { text: distance }),
        rec.duration
          ? el("div", { class: "stat-row__sub", text: rec.duration })
          : null,
      ]),
    ]);
  }

  function breakdownCard(title, data, key, labels, unit) {
    const max =
      data.reduce(function (m, d) {
        return Math.max(m, num(d.segments));
      }, 0) || 1;
    const rows = data.map(function (d) {
      const segments = num(d.segments);
      const distance = num(d.distance);
      const name = (labels && labels[d[key]]) || d[key] || "—";
      return el("div", { class: "bar" }, [
        el("div", { class: "bar__label", text: name }),
        el("div", { class: "bar__track" }, [
          el("div", {
            class: "bar__fill",
            style: "width:" + (100 * segments) / max + "%",
          }),
        ]),
        el("div", {
          class: "bar__value",
          text: segments + " · " + formatNumber(distance) + " " + unit,
        }),
      ]);
    });
    return card(title, el("div", { class: "bars" }, rows));
  }

  function renderOverview() {
    const view = els.overview;
    view.textContent = "";

    const mapStats = state.data.mapStats;
    if (!mapStats) {
      view.appendChild(el("div", { class: "empty", text: "Loading…" }));
      return;
    }

    const stats = state.data.stats;
    const unit = unitSuffix();

    const segments = parseInt(mapStats.segments, 10) || 0;

    let distanceValue;
    let distanceLabel;
    if (stats && stats.total && stats.total.distance != null) {
      distanceValue = formatNumber(stats.total.distance);
      distanceLabel = unit;
    } else if (mapStats.distanceStr) {
      // Fall back to the pre-formatted total when stats aren't available.
      distanceValue = mapStats.distanceStr;
      distanceLabel = "Total";
    } else {
      distanceValue = "0";
      distanceLabel = unit;
    }
    const durationMin = mapStats.durationMin || 0;

    view.appendChild(
      el("section", { class: "card" }, [
        el("div", { class: "hero" }, [
          heroItem(String(segments), "Flights"),
          heroItem(distanceValue, distanceLabel),
          heroItem(formatDurationMin(durationMin), "Time"),
        ]),
      ])
    );

    if (!stats) {
      view.appendChild(
        el("div", {
          class: "empty",
          text: state.statsError || "Detailed statistics are unavailable.",
        })
      );
    }

    if (stats && stats.unique) {
      const u = stats.unique;
      view.appendChild(
        card(
          "Places",
          el("div", { class: "grid-tiles" }, [
            tile(u.airports, "Airports"),
            tile(u.countries, "Countries"),
            tile(u.carriers, "Carriers"),
            tile(u.vehicles, "Vehicles"),
          ])
        )
      );
    }

    if (stats && stats.longshort) {
      const ls = stats.longshort;
      const rows = [];
      if (ls.longest) rows.push(recordRow("Longest", ls.longest, unit));
      if (ls.shortest) rows.push(recordRow("Shortest", ls.shortest, unit));
      if (rows.length) {
        view.appendChild(
          card("Record flights", el("div", { class: "stat-list" }, rows))
        );
      }
    }

    if (stats && stats.extreme) {
      const dirs = ["N", "S", "E", "W"];
      const labels = { N: "North", S: "South", E: "East", W: "West" };
      const items = dirs.map(function (d) {
        const ex = stats.extreme[d];
        return el("div", { class: "extreme" }, [
          el("div", { class: "extreme__dir", text: labels[d] }),
          el("div", { class: "extreme__code", text: ex ? ex.code : "—" }),
        ]);
      });
      view.appendChild(
        card("Extremes", el("div", { class: "extreme-grid" }, items))
      );
    }

    const breakdowns = [
      {
        title: "By mode",
        data: stats && stats.by_mode,
        key: "mode",
        labels: MODE_NAMES,
      },
      {
        title: "By class",
        data: stats && stats.by_class,
        key: "class",
        labels: CLASS_NAMES,
      },
      {
        title: "By reason",
        data: stats && stats.by_reason,
        key: "reason",
        labels: REASON_NAMES,
      },
      {
        title: "By seat",
        data: stats && stats.by_seattype,
        key: "seattype",
        labels: SEAT_NAMES,
      },
    ];
    breakdowns.forEach(function (b) {
      if (b.data && b.data.length) {
        view.appendChild(breakdownCard(b.title, b.data, b.key, b.labels, unit));
      }
    });
  }

  // --------------------------------------------------------------------------
  // Flights rendering
  // --------------------------------------------------------------------------
  function isFuture(date) {
    if (!date) return false;
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(date);
    if (!m) return false;
    return Date.UTC(+m[1], +m[2] - 1, +m[3]) > Date.now();
  }

  function airlineName(f) {
    if (state.airlineNames.has(f.alid)) return state.airlineNames.get(f.alid);
    return f.alCode || "";
  }

  function tripName(f) {
    if (f.trid && state.tripNames.has(f.trid)) {
      return state.tripNames.get(f.trid);
    }
    return "";
  }

  function displayFlightNo(f) {
    let code = f.code || "";
    if (!code) return "—";
    if (/^[0-9]+$/.test(code)) code = (f.alCode || "") + code;
    return code;
  }

  function vehicleLabel(f) {
    let v = f.plane || "";
    if (f.registration)
      v = v ? v + " (" + f.registration + ")" : f.registration;
    return v;
  }

  function flightSearchKey(f) {
    return [
      f.srcCode,
      f.dstCode,
      f.code,
      f.alCode,
      f.plane,
      f.registration,
      f.note,
      f.date,
      f.srcTime,
      airlineName(f),
      tripName(f),
      CLASS_NAMES[f.class] || "",
      REASON_NAMES[f.reason] || "",
      SEAT_NAMES[f.seatType] || "",
    ]
      .join(" ")
      .toLowerCase();
  }

  function dateKey(f) {
    return (f.date || "") + " " + (f.srcTime || "00:00");
  }

  function flightComparator(sort) {
    switch (sort) {
      case "date-asc":
        return function (a, b) {
          return dateKey(a).localeCompare(dateKey(b));
        };
      case "distance-desc":
        return function (a, b) {
          return num(b.distance) - num(a.distance);
        };
      case "distance-asc":
        return function (a, b) {
          return num(a.distance) - num(b.distance);
        };
      case "date-desc":
      default:
        return function (a, b) {
          return dateKey(b).localeCompare(dateKey(a));
        };
    }
  }

  function getFilteredFlights() {
    let list = state.data.flights.slice();
    if (state.airportFilter) {
      list = list.filter(function (f) {
        if (state.airportFilter.apid) {
          return (
            f.srcApid === state.airportFilter.apid ||
            f.dstApid === state.airportFilter.apid
          );
        }
        return (
          f.srcCode === state.airportFilter.code ||
          f.dstCode === state.airportFilter.code
        );
      });
    }
    const q = state.flightsQuery.trim().toLowerCase();
    if (q) {
      list = list.filter(function (f) {
        return flightSearchKey(f).indexOf(q) !== -1;
      });
    }
    list.sort(flightComparator(state.flightsSort));
    return list;
  }

  function modeIcon(mode) {
    const key = MODE_ICON_PATHS[mode] ? mode : "F";
    const svg = document.createElementNS(SVG_NS, "svg");
    svg.setAttribute("viewBox", "0 0 24 24");
    svg.setAttribute("width", "15");
    svg.setAttribute("height", "15");
    svg.setAttribute("class", "mode-icon");
    svg.setAttribute("role", "img");
    svg.setAttribute("aria-label", MODE_NAMES[key] || "Flight");
    svg.style.color = MODE_COLORS[key] || MODE_COLORS.F;

    const path = document.createElementNS(SVG_NS, "path");
    path.setAttribute("d", MODE_ICON_PATHS[key]);
    path.setAttribute("fill", "currentColor");
    svg.appendChild(path);
    return svg;
  }

  function flightCard(f) {
    const future = isFuture(f.date);
    const cardNode = el("article", { class: "flight-card" });

    const route = el("div", { class: "flight-card__route" }, [
      modeIcon(f.mode),
      el("span", { text: f.srcCode + " → " + f.dstCode }),
    ]);
    const date = el("div", {
      class: "flight-card__date" + (future ? " is-future" : ""),
      text: f.date + (f.srcTime ? " " + f.srcTime : ""),
    });
    cardNode.appendChild(
      el("div", { class: "flight-card__top" }, [route, date])
    );

    const metaParts = [];
    metaParts.push(displayFlightNo(f));
    const carrier = airlineName(f);
    if (carrier) metaParts.push(carrier);
    const vehicle = vehicleLabel(f);
    if (vehicle) metaParts.push(vehicle);
    metaParts.push(formatNumber(f.distance) + " " + unitSuffix());
    if (f.duration) metaParts.push(f.duration + " h");
    cardNode.appendChild(
      el("div", {
        class: "flight-card__meta",
        text: metaParts.join(" · "),
      })
    );

    const badges = [];
    if (f.class)
      badges.push(
        el("span", { class: "badge", text: CLASS_NAMES[f.class] || f.class })
      );
    if (f.seatType)
      badges.push(
        el("span", {
          class: "badge",
          text: SEAT_NAMES[f.seatType] || f.seatType,
        })
      );
    if (f.seat)
      badges.push(el("span", { class: "badge", text: "Seat " + f.seat }));
    if (f.reason)
      badges.push(
        el("span", { class: "badge", text: REASON_NAMES[f.reason] || f.reason })
      );
    const trip = tripName(f);
    if (trip)
      badges.push(el("span", { class: "badge badge--accent", text: trip }));
    if (future) badges.push(el("span", { class: "badge", text: "Upcoming" }));
    if (badges.length)
      cardNode.appendChild(el("div", { class: "badges" }, badges));

    if (f.note)
      cardNode.appendChild(
        el("div", { class: "flight-card__note", text: f.note })
      );

    return cardNode;
  }

  function renderFlightList() {
    if (!state.canViewFlights) {
      els.flightsToolbar.hidden = true;
      els.airportFilter.hidden = true;
      els.flightCount.textContent = "";
      els.flightList.textContent = "";
      els.flightList.appendChild(
        el("div", {
          class: "empty",
          text: "Flight details are not public for this user.",
        })
      );
      return;
    }
    els.flightsToolbar.hidden = false;

    if (state.airportFilter) {
      els.airportFilter.hidden = false;
      els.airportFilterText.textContent =
        state.airportFilter.code || state.airportFilter.name || "Airport";
      els.airportFilter.title = state.airportFilter.name || "";
    } else {
      els.airportFilter.hidden = true;
    }

    const list = getFilteredFlights();
    els.flightCount.textContent =
      list.length === state.data.flights.length
        ? formatNumber(list.length) + " flights"
        : formatNumber(list.length) +
          " of " +
          formatNumber(state.data.flights.length) +
          " flights";
    els.flightList.textContent = "";

    if (!list.length) {
      els.flightList.appendChild(
        el("div", {
          class: "empty",
          text: state.airportFilter
            ? "No flights for this airport."
            : state.data.flights.length
            ? "No flights match your search."
            : "No flights found.",
        })
      );
      return;
    }

    const frag = document.createDocumentFragment();
    list.forEach(function (f) {
      frag.appendChild(flightCard(f));
    });
    els.flightList.appendChild(frag);
  }

  // --------------------------------------------------------------------------
  // Map rendering (OpenLayers 2, same base layers as openflights.js)
  // --------------------------------------------------------------------------
  let map = null;
  let proj = null;
  let lineLayer = null;
  let airportLayer = null;
  let airportStyle = null;
  let satelliteLayer = null;
  let selectControl = null;
  let airportPopup = null;

  function buildMapControls() {
    const touch = "ontouchstart" in window || navigator.maxTouchPoints > 0;
    const nav = touch
      ? new OpenLayers.Control.TouchNavigation()
      : new OpenLayers.Control.Navigation({ zoomWheelEnabled: true });
    return [
      new OpenLayers.Control.PanZoom(),
      nav,
      new OpenLayers.Control.LayerSwitcher(),
      new OpenLayers.Control.ScaleLine(),
      new OpenLayers.Control.Attribution(),
    ];
  }

  // The XYZ base layers are spherical Mercator, so OpenLayers reports map
  // extents in EPSG:900913 metres: a square world roughly +/-20037508 m wide.
  // A portrait phone is taller than that square at low zoom levels, which
  // makes OpenLayers request tile rows above the north pole and below the
  // south pole. Those requests 404 and render as blank bands at the top and
  // bottom of the view. This keeps the viewport inside the world instead.
  let clampingMapView = false;
  function clampMapToWorld() {
    if (clampingMapView || !map || !state.mapInitialized) return;

    const size = map.getSize();
    const worldExtent = map.getMaxExtent();
    const baseLayer = map.baseLayer;
    if (
      !size ||
      !size.h ||
      !worldExtent ||
      !baseLayer ||
      !baseLayer.resolutions ||
      !map.center
    ) {
      return;
    }

    clampingMapView = true;
    try {
      // Zoom in until the whole viewport fits vertically inside the world.
      const minZoom = 2; // as in openflights.js
      const maxZoom = Math.max(minZoom, (map.getNumZoomLevels() || 19) - 1);
      const current = map.getZoom() == null ? minZoom : map.getZoom();
      let zoom = Math.max(minZoom, Math.min(current, maxZoom));
      for (; zoom < maxZoom; zoom++) {
        const resolution = map.getResolutionForZoom(zoom);
        if (
          resolution == null ||
          size.h * resolution <= worldExtent.getHeight()
        ) {
          break;
        }
      }
      if (zoom !== current) {
        map.zoomTo(zoom);
      }

      // At that zoom the view is at most as tall as the world, so panning can
      // still overshoot the poles; nudge the centre back inside.
      const extent = map.getExtent();
      const center = map.getCenter();
      if (!extent || !center) return;
      const halfHeight = extent.getHeight() / 2;
      const northLimit = worldExtent.top - halfHeight;
      const southLimit = worldExtent.bottom + halfHeight;
      let lat = center.lat;
      if (lat > northLimit) lat = northLimit;
      else if (lat < southLimit) lat = southLimit;
      if (lat !== center.lat) {
        map.setCenter(new OpenLayers.LonLat(center.lon, lat));
      }
    } finally {
      clampingMapView = false;
    }
  }

  function initMap() {
    if (state.mapInitialized || typeof OpenLayers === "undefined") return;

    // OpenLayers looks for its control images in /js/img by default; this app
    // ships violet-tinted copies of the ones it uses in mobile/img so the
    // zoom/pan controls match the OpenFlights accent colour. Also stop
    // OpenLayers from painting broken tiles for areas outside the world.
    OpenLayers.ImgPath = "img/";
    OpenLayers.IMAGE_RELOAD_ATTEMPTS = 3;
    OpenLayers.Util.onImageLoadErrorColor = "transparent";

    proj = new OpenLayers.Projection("EPSG:4326");

    map = new OpenLayers.Map("map", {
      controls: buildMapControls(),
      // The app ships its own control styling; don't fetch the OL theme CSS.
      theme: null,
    });

    // Don't let the map zoom out past a world view, and keep it inside the
    // world vertically so no out-of-range tiles are requested.
    map.events.register("zoomend", map, clampMapToWorld);
    map.events.register("moveend", map, clampMapToWorld);

    // The keys below are domain-restricted to openflights.org (copied from
    // openflights.js); substitute your own if hosting elsewhere.
    const political = new OpenLayers.Layer.XYZ(
      "Political",
      [
        "https://basemaps.cartocdn.com/rastertiles/light_nolabels/${z}/${x}/${y}.png?key=cb1_2a0y_1_fe421b625bae1ce78e571f5a",
      ],
      {
        attribution:
          "Map tiles © <a href='https://carto.com/' target='_blank' rel='noopener'>CARTO</a> (CC BY 3.0), data © <a href='https://www.openstreetmap.org' target='_blank' rel='noopener'>OSM</a> (ODbL)",
        sphericalMercator: true,
        transitionEffect: "resize",
        wrapDateLine: true,
      }
    );

    satelliteLayer = new OpenLayers.Layer.XYZ(
      "Satellite",
      [
        "https://api.tiles.mapbox.com/v4/mapbox.satellite/${z}/${x}/${y}.png?access_token=pk.eyJ1IjoianBhdG9rYWwiLCJhIjoiY2lyNmFyZThqMDBiNWcybTFlOWdkZGk1MiJ9.6_VWU3skRwM68ASapMLIQg",
      ],
      {
        attribution:
          "Map tiles © <a href='https://www.mapbox.com/maps/satellite' target='_blank' rel='noopener'>Mapbox</a>",
        sphericalMercator: true,
        transitionEffect: "resize",
        wrapDateLine: true,
      }
    );
    satelliteLayer.setVisibility(false);

    lineLayer = new OpenLayers.Layer.Vector("Flights", {
      projection: "EPSG:4326",
      styleMap: new OpenLayers.StyleMap({
        strokeColor: "${color}",
        strokeOpacity: 0.9,
        strokeWidth: "${width}",
        strokeDashstyle: "${dash}",
      }),
    });

    airportStyle = new OpenLayers.Style(
      {
        graphicName: "circle",
        pointRadius: "${radius}",
        fillColor: "${color}",
        fillOpacity: 0.85,
        strokeColor: "#ffffff",
        strokeWidth: 1.5,
        label: "${labelText}",
        // Make airport codes and cluster labels clickable/tappable.
        labelSelect: true,
        fontColor: "${labelColor}",
        fontSize: "10px",
        fontWeight: "bold",
        fontFamily:
          "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
        labelAlign: "lb",
        labelXOffset: 5,
        labelYOffset: -2,
        labelOutlineColor: "${labelOutlineColor}",
        labelOutlineWidth: 2,
      },
      {
        context: {
          radius: function (feature) {
            if (feature.cluster) {
              return airportClusterRadius();
            }
            return feature.attributes.radius;
          },
          color: function (feature) {
            return feature.cluster ? AIRPORT_COLOR : feature.attributes.color;
          },
          labelText: function (feature) {
            if (feature.cluster) {
              const code = largestAirportCode(feature);
              return code ? code + "+" : "";
            }
            return feature.attributes.code;
          },
          labelColor: function () {
            return satelliteLayer && satelliteLayer.visibility
              ? "#ffffff"
              : "#1a1c1e";
          },
          labelOutlineColor: function () {
            return satelliteLayer && satelliteLayer.visibility
              ? "#000000"
              : "#ffffff";
          },
        },
      }
    );

    airportLayer = new OpenLayers.Layer.Vector("Airports", {
      projection: "EPSG:4326",
      strategies: [
        new OpenLayers.Strategy.Cluster({ distance: 16, threshold: 2 }),
      ],
      styleMap: new OpenLayers.StyleMap({ default: airportStyle }),
    });

    map.addLayers([political, satelliteLayer, lineLayer, airportLayer]);

    selectControl = new OpenLayers.Control.SelectFeature(airportLayer, {
      onSelect: onAirportSelect,
      onUnselect: onAirportUnselect,
    });
    map.addControl(selectControl);
    selectControl.activate();

    // Label colours adapt to the (dark) satellite basemap via the style context.
    map.events.on({
      changelayer: function () {
        airportLayer.redraw();
      },
    });

    state.mapInitialized = true;
  }

  function projectedPoint(x, y) {
    const p = new OpenLayers.Geometry.Point(x, y);
    p.transform(proj, map.getProjectionObject());
    return p;
  }

  function projectedLine(points) {
    const line = new OpenLayers.Geometry.LineString(points);
    line.transform(proj, map.getProjectionObject());
    return line;
  }

  function makeLineFeatures(x1, y1, x2, y2, count, color, future) {
    const width = Math.floor(Math.sqrt(count) + 0.5);
    const dash = future === "Y" ? "dash" : "solid";

    const paths = [
      gcPath(
        new OpenLayers.Geometry.Point(x1, y1),
        new OpenLayers.Geometry.Point(x2, y2)
      ),
    ];
    // Wrap copies so routes crossing the date line render correctly.
    if (x1 > 0 || x2 > 0) {
      paths.push(
        gcPath(
          new OpenLayers.Geometry.Point(x1 - 360, y1),
          new OpenLayers.Geometry.Point(x2 - 360, y2)
        )
      );
    }
    if (x1 < 0 || x2 < 0) {
      paths.push(
        gcPath(
          new OpenLayers.Geometry.Point(x1 + 360, y1),
          new OpenLayers.Geometry.Point(x2 + 360, y2)
        )
      );
    }

    const features = [];
    paths.forEach(function (pts) {
      features.push(
        new OpenLayers.Feature.Vector(projectedLine(pts), {
          color: color,
          width: width,
          dash: dash,
        })
      );
    });
    return features;
  }

  function airportRadius(count, max) {
    if (max <= 0) return 4;
    return 3 + Math.round((count / max) * 5);
  }

  function airportClusterRadius() {
    // Match the footprint of a selected cluster: OpenLayers' "select" style
    // uses pointRadius 6, so clusters don't grow with their member count.
    return 6;
  }

  // The airport with the most flights in a cluster, for the "SIN+" label.
  function largestAirportCode(clusterFeature) {
    let best = null;
    clusterFeature.cluster.forEach(function (feature) {
      const a = feature.attributes || {};
      const aCount = parseInt(a.count, 10) || 0;
      const bCount = best ? parseInt(best.count, 10) || 0 : -1;
      if (
        !best ||
        aCount > bCount ||
        (aCount === bCount && (a.code || "") < (best.code || ""))
      ) {
        best = a;
      }
    });
    return (best && best.code) || "";
  }

  function drawMap() {
    if (!state.mapInitialized) return;
    // The features are about to be rebuilt; any popup anchored to them is stale.
    closeAirportPopup(true);
    closeAirportChooser(false);
    lineLayer.destroyFeatures();
    airportLayer.destroyFeatures();

    const lineFeatures = [];
    state.data.flightPairs.forEach(function (p) {
      const color = MODE_COLORS[p.mode] || MODE_COLORS.F;
      lineFeatures.push.apply(
        lineFeatures,
        makeLineFeatures(
          p.srcLon,
          p.srcLat,
          p.dstLon,
          p.dstLat,
          p.count,
          color,
          p.future
        )
      );
    });
    lineLayer.addFeatures(lineFeatures);

    const maxCount =
      state.data.airports.reduce(function (m, a) {
        return Math.max(m, a.count);
      }, 0) || 1;

    const airportFeatures = state.data.airports.map(function (a) {
      return new OpenLayers.Feature.Vector(projectedPoint(a.lon, a.lat), {
        radius: airportRadius(a.count, maxCount),
        color: AIRPORT_COLOR,
        code: a.code,
        name: a.name,
        city: a.city,
        country: a.country,
        count: a.count,
        apid: a.apid,
        lon: a.lon,
        lat: a.lat,
      });
    });
    airportLayer.addFeatures(airportFeatures);

    fitMap();
  }

  function fitMap() {
    const extent = airportLayer.getDataExtent();
    if (extent && state.data.airports.length > 0) {
      map.zoomToExtent(extent);
    } else {
      map.zoomTo(2);
    }
  }

  // --------------------------------------------------------------------------
  // Airport popup
  // --------------------------------------------------------------------------
  // Number of flights to or from this airport in the data currently shown on
  // the map: the logged-in user's own log, or the demo data when logged out.
  function airportFlightCount(apid) {
    let count = 0;
    state.data.flights.forEach(function (f) {
      if (f.srcApid === apid || f.dstApid === apid) count++;
    });
    return count;
  }

  function closeAirportPopup(unselect) {
    if (airportPopup) {
      airportPopup.destroy();
      airportPopup = null;
    }
    if (unselect && selectControl) {
      selectControl.unselectAll();
    }
  }

  function onAirportSelect(feature) {
    if (feature.cluster && feature.cluster.length > 1) {
      openAirportChooser(feature);
    } else {
      showAirportPopup(feature.cluster ? feature.cluster[0] : feature);
    }
  }

  function onAirportUnselect() {
    closeAirportPopup(false);
    closeAirportChooser(false);
  }

  function showAirportPopup(feature) {
    if (!map) return;
    closeAirportPopup(false);

    const attrs = feature.attributes || {};
    const apid = attrs.apid || "";
    const code = attrs.code || "";
    const name = attrs.name || code || "Airport";
    const place = [attrs.city, attrs.country].filter(Boolean).join(", ");
    const meta = [code, place].filter(Boolean).join(" · ");

    // Count the flights currently shown on the map: the signed-in user's own
    // log, or the demo data when nobody is logged in.
    let extraHtml = "";
    if (state.canViewFlights) {
      const flightCount = airportFlightCount(apid);
      const countText =
        formatNumber(flightCount) +
        (flightCount === 1 ? " flight" : " flights");
      extraHtml =
        '<button type="button" class="btn btn--primary airport-popup__btn" ' +
        'id="airport-popup-flights">' +
        escapeHtml(countText) +
        "</button>";
    }

    const html =
      '<div class="airport-popup">' +
      '<button type="button" class="airport-popup__close" ' +
      'id="airport-popup-close" aria-label="Close">✕</button>' +
      '<div class="airport-popup__name">' +
      escapeHtml(name) +
      "</div>" +
      (meta
        ? '<div class="airport-popup__meta">' + escapeHtml(meta) + "</div>"
        : "") +
      extraHtml +
      "</div>";

    // Plain anchored popup: no frame images, and autoSize makes it grow to fit.
    const popup = new OpenLayers.Popup.Anchored(
      "airport-popup",
      feature.geometry.getBounds().getCenterLonLat(),
      new OpenLayers.Size(200, 100),
      html,
      null,
      false,
      null
    );
    popup.autoSize = true;
    popup.keepInMap = true;
    popup.panMapIfOutOfView = true;
    popup.minSize = new OpenLayers.Size(180, 60);
    popup.maxSize = new OpenLayers.Size(300, 240);

    map.addPopup(popup);
    airportPopup = popup;

    // Keep clicks inside the popup away from the map, otherwise they would
    // deselect the airport and immediately close the popup again.
    ["mousedown", "click", "dblclick", "touchstart", "touchend"].forEach(
      function (type) {
        popup.div.addEventListener(
          type,
          function (evt) {
            evt.stopPropagation();
          },
          false
        );
      }
    );

    const closeBtn = document.getElementById("airport-popup-close");
    if (closeBtn) {
      closeBtn.addEventListener("click", function () {
        closeAirportPopup(true);
      });
    }
    const flightsBtn = document.getElementById("airport-popup-flights");
    if (flightsBtn) {
      flightsBtn.addEventListener("click", function () {
        applyAirportFilter(attrs);
      });
    }
  }

  // --------------------------------------------------------------------------
  // Airport chooser (for overlapping airports)
  // --------------------------------------------------------------------------
  let chooserCluster = null;

  function airportFlightCounts() {
    const counts = new Map();
    state.data.flights.forEach(function (f) {
      if (f.srcApid) counts.set(f.srcApid, (counts.get(f.srcApid) || 0) + 1);
      if (f.dstApid && f.dstApid !== f.srcApid) {
        counts.set(f.dstApid, (counts.get(f.dstApid) || 0) + 1);
      }
    });
    return counts;
  }

  function openAirportChooser(clusterFeature) {
    closeAirportPopup(false);
    closeAirportChooser(false);
    chooserCluster = clusterFeature;

    const counts = airportFlightCounts();
    const members = clusterFeature.cluster.slice().sort(function (a, b) {
      const ca = counts.get(a.attributes.apid) || 0;
      const cb = counts.get(b.attributes.apid) || 0;
      if (cb !== ca) return cb - ca;
      return (a.attributes.code || "").localeCompare(b.attributes.code || "");
    });

    els.chooserList.textContent = "";
    members.forEach(function (feature) {
      const a = feature.attributes || {};
      const code = a.code || "";
      const name = a.name || code || "Airport";
      const place = [a.city, a.country].filter(Boolean).join(", ");
      els.chooserList.appendChild(
        el(
          "button",
          {
            class: "chooser-row",
            type: "button",
            onclick: function () {
              chooseAirport(feature);
            },
          },
          [
            el("span", { class: "chooser-row__main" }, [
              el("span", {
                class: "chooser-row__name",
                text: code ? code + " — " + name : name,
              }),
              place
                ? el("span", { class: "chooser-row__sub", text: place })
                : null,
            ]),
            el("span", {
              class: "chooser-row__count",
              text: formatNumber(counts.get(a.apid) || 0),
            }),
          ]
        )
      );
    });

    els.chooserSubtitle.textContent =
      formatNumber(members.length) + " airports in this area";
    els.chooserZoom.hidden = members.length <= 8;
    els.chooserBackdrop.hidden = false;
    els.chooser.hidden = false;
  }

  function closeAirportChooser(unselect) {
    if (els.chooser) {
      els.chooser.hidden = true;
      els.chooserBackdrop.hidden = true;
      els.chooserList.textContent = "";
    }
    chooserCluster = null;
    if (unselect && selectControl) {
      selectControl.unselectAll();
    }
  }

  function chooseAirport(feature) {
    // Keep the cluster selected so a click elsewhere still closes the popup.
    closeAirportChooser(false);
    showAirportPopup(feature);
  }

  function zoomToCluster() {
    if (!chooserCluster || !map) return;
    const center = chooserCluster.geometry.getBounds().getCenterLonLat();
    const maxZoom = (map.getNumZoomLevels() || 19) - 1;
    const nextZoom = Math.min((map.getZoom() || 0) + 1, maxZoom);
    closeAirportChooser(true);
    map.setCenter(center, nextZoom);
  }

  function applyAirportFilter(attrs) {
    state.airportFilter = {
      apid: attrs.apid || "",
      code: attrs.code || "",
      name: attrs.name || "",
    };
    closeAirportPopup(true);
    showView("flights");
    // Show the airport's flights, not whatever search was previously typed.
    state.flightsQuery = "";
    els.flightSearch.value = "";
    els.flights.scrollTop = 0;
    renderFlightList();
  }

  // --------------------------------------------------------------------------
  // Navigation
  // --------------------------------------------------------------------------
  function showView(view) {
    document.querySelectorAll(".view").forEach(function (v) {
      v.classList.toggle("is-active", v.id === "view-" + view);
    });
    document.querySelectorAll(".tabbar__tab").forEach(function (t) {
      const active = t.dataset.view === view;
      t.classList.toggle("is-active", active);
      t.setAttribute("aria-selected", active ? "true" : "false");
    });
    state.currentView = view;

    // The popup/chooser live on the map, so they make no sense once it is hidden.
    if (view !== "map") {
      closeAirportPopup(true);
      closeAirportChooser(false);
    }

    if (view === "map") {
      if (!state.mapInitialized && typeof OpenLayers !== "undefined") {
        initMap();
        map.updateSize();
        drawMap();
      } else if (typeof OpenLayers === "undefined") {
        const container = document.getElementById("map");
        if (container) {
          container.innerHTML = "";
          container.appendChild(
            el("div", {
              class: "empty",
              text: "The map library could not be loaded.",
            })
          );
        }
      }
      requestAnimationFrame(function () {
        if (map) map.updateSize();
      });
    }
  }

  // --------------------------------------------------------------------------
  // User context (demo / login)
  // --------------------------------------------------------------------------
  function renderUserChip() {
    if (state.mode === "self") {
      els.userChip.textContent = state.username || "Me";
      els.userChip.classList.add("chip--self");
    } else {
      els.userChip.textContent = "Login";
      els.userChip.classList.remove("chip--self");
    }
  }

  function renderYearSelect() {
    const sel = els.yearSelect;
    sel.textContent = "";
    sel.appendChild(new Option("All years", "0"));
    state.data.years.forEach(function (y) {
      sel.appendChild(new Option(y.label, y.year));
    });
    sel.value = state.year || "0";
  }

  function renderCarrierSelect() {
    const sel = els.carrierSelect;
    sel.textContent = "";
    sel.appendChild(new Option("All carriers", "0"));
    let found = !state.carrier || state.carrier === "0";
    state.data.airlines.forEach(function (a) {
      sel.appendChild(new Option(a.name || a.alid, a.alid));
      if (a.alid === state.carrier) found = true;
    });
    if (!found) {
      // The chosen carrier didn't fly during the selected year; clear it so
      // the map and flights aren't filtered by a carrier missing from the list.
      state.carrier = "0";
      sel.value = "0";
      return false;
    }
    sel.value = state.carrier || "0";
    return true;
  }

  function openSheet() {
    renderSheetStatus();
    els.sheet.hidden = false;
    els.sheetBackdrop.hidden = false;
  }

  function closeSheet() {
    els.sheet.hidden = true;
    els.sheetBackdrop.hidden = true;
  }

  function renderSheetStatus() {
    let msg;
    if (state.mode === "self") msg = "Logged in as " + state.username + ".";
    else msg = "Not logged in. Showing demo data.";
    els.sheetStatus.textContent = msg;
    els.logoutBtn.hidden = state.mode !== "self";
  }

  function toast(msg) {
    els.toast.textContent = msg;
    els.toast.classList.add("is-visible");
    clearTimeout(toast._timer);
    toast._timer = setTimeout(function () {
      els.toast.classList.remove("is-visible");
    }, 3200);
  }

  async function login(username, password) {
    const name = (username || "").trim();
    const pw = password || "";
    if (!name || !pw) {
      toast("Enter username and password.");
      return;
    }
    if (!state.challenge) {
      // Establish a session and obtain a challenge string first.
      await loadAll();
    }
    if (!state.challenge) {
      toast("Could not start a session.");
      return;
    }

    const hash = MD5(state.challenge + MD5(pw + name.toLowerCase()));
    const legacyHash = MD5(state.challenge + MD5(pw + name));

    let resp = null;
    try {
      const text = await post(API.LOGIN, {
        name: name,
        pw: hash,
        lpw: legacyHash,
        challenge: state.challenge,
      });
      resp = JSON.parse(text);
    } catch (e) {
      resp = null;
    }

    if (resp && (resp.status === 1 || resp.status === 2)) {
      state.mode = "self";
      state.username = resp.name || name;
      state.guestpw = "";
      state.year = "0";
      state.carrier = "0";
      state.airportFilter = null;
      els.yearSelect.value = "0";
      els.carrierSelect.value = "0";
      closeSheet();
      toast("Logged in as " + state.username + ".");
      await loadAll();
    } else {
      const msg =
        resp && resp.message ? stripHtml(resp.message) : "Login failed.";
      toast(msg);
    }
  }

  async function logout() {
    try {
      await post(API.LOGOUT, {});
    } catch (e) {
      // Ignore; still reset local state.
    }
    state.mode = "demo";
    state.username = "";
    state.guestpw = "";
    state.year = "0";
    state.carrier = "0";
    state.airportFilter = null;
    els.yearSelect.value = "0";
    els.carrierSelect.value = "0";
    closeSheet();
    toast("Logged out.");
    await loadAll();
  }

  // --------------------------------------------------------------------------
  // Wiring
  // --------------------------------------------------------------------------
  function wireEvents() {
    document.querySelectorAll(".tabbar__tab").forEach(function (t) {
      t.addEventListener("click", function () {
        showView(t.dataset.view);
      });
    });

    // OpenLayers 2 doesn't watch the window: keep the map sized correctly when
    // the browser is resized or a tablet is rotated.
    let resizePending = false;
    window.addEventListener("resize", function () {
      if (resizePending) return;
      resizePending = true;
      requestAnimationFrame(function () {
        resizePending = false;
        if (map && state.mapInitialized) {
          map.updateSize();
        }
      });
    });

    els.userChip.addEventListener("click", openSheet);
    els.sheetClose.addEventListener("click", closeSheet);
    els.sheetBackdrop.addEventListener("click", closeSheet);

    els.chooserClose.addEventListener("click", function () {
      closeAirportChooser(true);
    });
    els.chooserBackdrop.addEventListener("click", function () {
      closeAirportChooser(true);
    });
    els.chooserZoom.addEventListener("click", zoomToCluster);

    // Clicks inside the chooser must not reach the document-level handler that
    // dismisses the airport popup (the row click opens that popup).
    ["click", "mousedown", "touchstart", "touchend"].forEach(function (type) {
      els.chooser.addEventListener(
        type,
        function (evt) {
          evt.stopPropagation();
        },
        false
      );
    });

    els.loginForm.addEventListener("submit", function (e) {
      e.preventDefault();
      login(els.loginUsername.value, els.loginPassword.value);
    });
    els.logoutBtn.addEventListener("click", logout);

    els.yearSelect.addEventListener("change", function () {
      state.year = els.yearSelect.value;
      loadAll();
    });

    els.carrierSelect.addEventListener("change", function () {
      state.carrier = els.carrierSelect.value;
      loadAll();
    });

    els.airportFilterClear.addEventListener("click", function () {
      state.airportFilter = null;
      renderFlightList();
    });

    // Tapping outside the map or popup also dismisses the airport popup.
    document.addEventListener("click", function (evt) {
      if (!airportPopup) return;
      if (airportPopup.div.contains(evt.target)) return;
      const mapEl = document.getElementById("map");
      if (mapEl && mapEl.contains(evt.target)) return;
      closeAirportPopup(true);
    });

    els.flightSearch.addEventListener("input", function () {
      state.flightsQuery = els.flightSearch.value;
      renderFlightList();
    });
    els.flightSort.addEventListener("change", function () {
      state.flightsSort = els.flightSort.value;
      renderFlightList();
    });
  }

  async function init() {
    wireEvents();
    // The map is the default tab; show it first so it initialises against a
    // visible, correctly-sized container, then load data (which redraws it).
    showView("map");
    await loadAll();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
