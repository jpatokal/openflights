/**
 * OpenFlights Mobile — a mobile-first viewer for flight logs.
 *
 * This app is a completely independent client. It reads user data through the
 * existing PHP endpoints under /php/ and, for the signed-in user's own log,
 * reuses submit.php/autocomplete.php to add and edit flights.
 *
 * Dependencies (loaded by index.html):
 *   - /js/ol.min.js              -> OpenLayers 10 global
 *   - /js/ol-layerswitcher.min.js -> LayerSwitcher control
 *   - /js/greatcircle.js         -> gcPath() for great-circle route geometry
 *   - /js/md5.min.js             -> MD5() for login/guest-password hashing
 *   - /js/flights.js             -> OpenFlights.parseFlights() for flight JSON
 *   - /js/map.js                 -> OpenFlights.parseMap() for map JSON
 *   - /js/mapview.js             -> OpenFlights.Maps map/layer/overlay helpers
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
    AUTOCOMPLETE: "/php/autocomplete.php",
    SUBMIT: "/php/submit.php",
  };

  const KM_PER_MILE = 1.609344;
  // php/greatcircle.php defines its own truncated PI; match it so client-side
  // distance estimates equal what submit.php would calculate.
  const PHP_PI = 3.1415926;

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
    P: "Premium Economy",
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
  const PIE_COLORS = [
    "#656698",
    "#e8890c",
    "#458ca7",
    "#45a679",
    "#ba558f",
    "#dfa41f",
  ];
  // Reference distances for the "Distance in perspective" card.
  const DISTANCE_SCALES = {
    km: { rtw: 40075, moon: 384400, mars: 225000000 },
    mi: { rtw: 24901, moon: 238855, mars: 139800000 },
  };
  // Fallback colour when a cluster's busiest airport is unknown.
  const AIRPORT_COLOR = "#e8890c";
  // Six-tier pin colour scale by visit count, least to most: orange, coral,
  // plum, violet, blue, green. The top two keep the blue and green used by the
  // busiest airport icons in openflights.js.
  const AIRPORT_PIN_COLORS = [
    "#e8890c", // 0 · orange (least visited)
    "#dd643c", // 1 · coral
    "#ba558f", // 2 · plum
    "#666699", // 3 · violet
    "#458ca7", // 4 · blue
    "#45a679", // 5 · green (busiest)
  ];

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
    modeFilter: "0",
    class: "0",
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
      modes: [],
      classes: [],
      flights: [],
      stats: null,
    },
    editor: {
      fid: null,
      srcApid: "",
      srcCode: "",
      srcLon: null,
      srcLat: null,
      dstApid: "",
      dstCode: "",
      dstLon: null,
      dstLat: null,
      alid: "",
      airlineName: "",
      airlineAuto: false,
      mode: "F",
      lastNumberCode: "",
      numberTimer: null,
      distanceAuto: false,
      durationAuto: false,
      deleteArmed: false,
      deleteTimer: null,
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
    modeSelect: document.getElementById("mode-select"),
    classSelect: document.getElementById("class-select"),
    clearFilters: document.getElementById("clear-filters"),
    filterToggle: document.getElementById("filter-toggle"),
    filterPanel: document.getElementById("filter-panel"),
    userChip: document.getElementById("user-chip"),
    sheet: document.getElementById("user-sheet"),
    sheetBackdrop: document.getElementById("sheet-backdrop"),
    sheetStatus: document.getElementById("sheet-status"),
    sheetTitle: document.getElementById("user-sheet-title"),
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
    addFlight: document.getElementById("add-flight"),
    editor: document.getElementById("flight-editor"),
    editorBackdrop: document.getElementById("editor-backdrop"),
    editorTitle: document.getElementById("flight-editor-title"),
    editorClose: document.getElementById("editor-close"),
    editorCancel: document.getElementById("editor-cancel"),
    editorDelete: document.getElementById("editor-delete"),
    editorError: document.getElementById("editor-error"),
    editorRoute: document.getElementById("editor-route"),
    editorForm: document.getElementById("flight-editor-form"),
    editorFrom: document.getElementById("editor-from"),
    editorTo: document.getElementById("editor-to"),
    editorDate: document.getElementById("editor-date"),
    editorTime: document.getElementById("editor-time"),
    editorMode: document.getElementById("editor-mode"),
    editorNumber: document.getElementById("editor-number"),
    editorAirline: document.getElementById("editor-airline"),
    editorPlane: document.getElementById("editor-plane"),
    editorSeat: document.getElementById("editor-seat"),
    editorSeatType: document.getElementById("editor-seat-type"),
    editorClass: document.getElementById("editor-class"),
    editorReason: document.getElementById("editor-reason"),
    editorDistance: document.getElementById("editor-distance"),
    editorDuration: document.getElementById("editor-duration"),
    editorRegistration: document.getElementById("editor-registration"),
    editorNote: document.getElementById("editor-note"),
    fromSuggestions: document.getElementById("from-suggestions"),
    toSuggestions: document.getElementById("to-suggestions"),
    airlineSuggestions: document.getElementById("airline-suggestions"),
    planeSuggestions: document.getElementById("plane-suggestions"),
  };

  // Autocomplete fields. "from"/"to" map to the same airport searchType but
  // keep separate selections and suggestion lists.
  const AUTO = {
    from: {
      type: "airport",
      input: els.editorFrom,
      list: els.fromSuggestions,
      timer: null,
      seq: 0,
    },
    to: {
      type: "airport",
      input: els.editorTo,
      list: els.toSuggestions,
      timer: null,
      seq: 0,
    },
    airline: {
      type: "airline",
      input: els.editorAirline,
      list: els.airlineSuggestions,
      timer: null,
      seq: 0,
    },
    plane: {
      type: "plane",
      input: els.editorPlane,
      list: els.planeSuggestions,
      timer: null,
      seq: 0,
    },
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
    if (state.modeFilter && state.modeFilter !== "0") {
      p.fmode = state.modeFilter;
    }
    if (state.class && state.class !== "0") p.class = state.class;
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

      const mapData = OpenFlights.parseMap(mapText);
      state.data.mapStats = mapData.stats;
      state.data.flightPairs = mapData.flightPairs;
      state.data.airports = mapData.airports;
      state.data.airlines = mapData.airlines;
      state.data.years = mapData.years;
      state.data.modes = mapData.modes;
      state.data.classes = mapData.classes;
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
        state.modeFilter = "0";
        state.class = "0";
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
        ? OpenFlights.parseFlights(flightsText)
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
    const yearValid = renderYearSelect();
    const carrierValid = renderCarrierSelect();
    const modeValid = renderModeSelect();
    const classValid = renderClassSelect();
    updateFilterToggle();
    renderOverview();
    renderFlightList();
    if (state.mapInitialized) drawMap();

    if (!yearValid || !carrierValid || !modeValid || !classValid) {
      // Reload now that a stale filter has been cleared.
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
      el("div", { class: "hero__label", text: label }),
      el("div", { class: "hero__value", text: value }),
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

  function pieChart(data, key, labels) {
    const total = data.reduce(function (sum, d) {
      return sum + num(d.segments);
    }, 0);
    const size = 120;
    const center = size / 2;
    const radius = size / 2 - 2;
    const svg = document.createElementNS(SVG_NS, "svg");
    svg.setAttribute("viewBox", "0 0 " + size + " " + size);
    svg.setAttribute("class", "pie");
    svg.setAttribute("role", "img");
    svg.setAttribute("aria-label", "Share by " + key);

    if (!total) {
      const empty = document.createElementNS(SVG_NS, "circle");
      empty.setAttribute("cx", center);
      empty.setAttribute("cy", center);
      empty.setAttribute("r", radius);
      empty.setAttribute("fill", "#e4e7ec");
      svg.appendChild(empty);
      return svg;
    }

    let angle = -Math.PI / 2;
    data.forEach(function (d, i) {
      const value = num(d.segments);
      if (value <= 0) return;
      const color = PIE_COLORS[i % PIE_COLORS.length];
      const name = (labels && labels[d[key]]) || d[key] || "—";
      const sweep = (value / total) * Math.PI * 2;

      let shape;
      if (sweep >= Math.PI * 2 - 1e-6) {
        shape = document.createElementNS(SVG_NS, "circle");
        shape.setAttribute("cx", center);
        shape.setAttribute("cy", center);
        shape.setAttribute("r", radius);
      } else {
        const end = angle + sweep;
        const x1 = center + radius * Math.cos(angle);
        const y1 = center + radius * Math.sin(angle);
        const x2 = center + radius * Math.cos(end);
        const y2 = center + radius * Math.sin(end);
        const large = sweep > Math.PI ? 1 : 0;
        shape = document.createElementNS(SVG_NS, "path");
        shape.setAttribute(
          "d",
          "M" +
            center +
            "," +
            center +
            " L" +
            x1.toFixed(2) +
            "," +
            y1.toFixed(2) +
            " A" +
            radius +
            "," +
            radius +
            " 0 " +
            large +
            ",1 " +
            x2.toFixed(2) +
            "," +
            y2.toFixed(2) +
            " Z"
        );
        angle = end;
      }
      shape.setAttribute("fill", color);
      shape.setAttribute("stroke", "#ffffff");
      shape.setAttribute("stroke-width", "2");
      const tip = document.createElementNS(SVG_NS, "title");
      tip.textContent =
        name + ": " + value + " (" + Math.round((value / total) * 100) + "%)";
      shape.appendChild(tip);
      svg.appendChild(shape);
    });
    return svg;
  }

  function pieLegend(data, key, labels, unit) {
    return el(
      "div",
      { class: "pie-legend" },
      data.map(function (d, i) {
        const segments = num(d.segments);
        const distance = num(d.distance);
        const name = (labels && labels[d[key]]) || d[key] || "—";
        return el("div", { class: "pie-legend__row" }, [
          el("div", { class: "pie-legend__head" }, [
            el("span", {
              class: "pie-legend__dot",
              style: "background:" + PIE_COLORS[i % PIE_COLORS.length],
            }),
            el("span", { class: "pie-legend__label", text: name }),
          ]),
          el("div", {
            class: "pie-legend__value",
            text: segments + " · " + formatNumber(distance) + " " + unit,
          }),
        ]);
      })
    );
  }

  function breakdownCard(title, data, key, labels, unit) {
    // Show the biggest slice first, like a conventional pie chart.
    const ordered = data.slice().sort(function (a, b) {
      return num(b.segments) - num(a.segments);
    });
    return card(
      title,
      el("div", { class: "pie-wrap" }, [
        pieChart(ordered, key, labels),
        pieLegend(ordered, key, labels, unit),
      ])
    );
  }

  function formatTripCount(value) {
    if (!isFinite(value) || value <= 0) return "0";
    if (value >= 1000) return formatNumber(Math.round(value));
    if (value >= 100) return String(Math.round(value));
    if (value >= 10) return String(Math.round(value * 10) / 10);
    if (value >= 1) return String(Math.round(value * 100) / 100);
    return String(parseFloat(value.toPrecision(2)));
  }

  function totalDistanceValue() {
    const stats = state.data.stats;
    if (stats && stats.total && stats.total.distance != null) {
      return num(stats.total.distance);
    }
    const s = state.data.mapStats && state.data.mapStats.distanceStr;
    if (s) return num(String(s).replace(/,/g, ""));
    return 0;
  }

  // The extreme airport's latitude arrives in stats.php's `lon` field and its
  // longitude in `lat` (the same swap openflights.js handles). Show only the
  // coordinate that the direction refers to, to one decimal place.
  function extremeCoord(dir, ex) {
    if (!ex) return "";
    const value = Number(dir === "N" || dir === "S" ? ex.lon : ex.lat);
    if (!isFinite(value)) return "";
    return Math.abs(value).toFixed(1) + "°" + dir;
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

    const totalDistance = totalDistanceValue();
    if (totalDistance > 0) {
      const scale = DISTANCE_SCALES[unit] || DISTANCE_SCALES.mi;
      view.appendChild(
        card(
          "Distance in perspective",
          el("div", { class: "grid-tiles grid-tiles--3" }, [
            tile(
              formatTripCount(totalDistance / scale.rtw) + "x",
              "Round the world"
            ),
            tile(
              formatTripCount(totalDistance / scale.moon) + "x",
              "To the Moon"
            ),
            tile(
              formatTripCount((totalDistance / scale.mars) * 100) + "%",
              "To Mars"
            ),
          ])
        )
      );
    }

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
        const coord = extremeCoord(d, ex);
        return el("div", { class: "extreme" }, [
          el("div", { class: "extreme__dir", text: labels[d] }),
          el("div", { class: "extreme__code", text: ex ? ex.code : "—" }),
          coord ? el("div", { class: "extreme__coord", text: coord }) : null,
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
    const editable = state.mode === "self";
    const cardNode = el("article", {
      class: "flight-card" + (editable ? " is-editable" : ""),
    });
    if (editable) {
      cardNode.addEventListener("click", function () {
        openFlightEditor(f.fid);
      });
    }

    const route = el("div", { class: "flight-card__route" }, [
      modeIcon(f.mode),
      el("span", { text: f.srcCode + " → " + f.dstCode }),
    ]);
    const date = el("div", {
      class: "flight-card__date" + (future ? " is-future" : ""),
      text: f.date + (f.srcTime ? " " + f.srcTime : ""),
    });
    const right = el("div", { class: "flight-card__right" }, [date]);
    if (editable) {
      right.appendChild(
        el("span", {
          class: "flight-card__edit",
          "aria-label": "Edit flight",
          html: '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>',
        })
      );
    }
    cardNode.appendChild(
      el("div", { class: "flight-card__top" }, [route, right])
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

    // Notes are private annotations: only the owner sees them on cards.
    if (f.note && state.mode === "self")
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
  // Map rendering (OpenLayers 10 via OpenFlights.Maps)
  // --------------------------------------------------------------------------
  let map = null;
  let mapCtx = null;
  let airportOverlay = null;
  let airportPopupElement = null;
  let airportPopupVisible = false;

  function buildMapControls() {
    const controls = [
      new ol.control.Zoom(),
      new ol.control.ScaleLine(),
      new ol.control.Attribution(),
    ];
    if (typeof LayerSwitcher !== "undefined") {
      controls.push(new LayerSwitcher({ activationMode: "click" }));
    }
    return controls;
  }

  function initMap() {
    if (
      state.mapInitialized ||
      typeof ol === "undefined" ||
      typeof OpenFlights.Maps === "undefined"
    ) {
      return;
    }

    // The keys below are domain-restricted to openflights.org (copied from
    // openflights.js); substitute your own if hosting elsewhere.
    mapCtx = OpenFlights.Maps.createMap("map", {
      politicalUrl:
        "https://basemaps.cartocdn.com/rastertiles/light_nolabels/{z}/{x}/{y}.png?key=cb1_2a0y_1_fe421b625bae1ce78e571f5a",
      politicalAttribution:
        "Map tiles © <a href='https://carto.com/' target='_blank' rel='noopener'>CARTO</a> (CC BY 3.0), data © <a href='https://www.openstreetmap.org' target='_blank' rel='noopener'>OSM</a> (ODbL)",
      satelliteUrl:
        "https://api.tiles.mapbox.com/v4/mapbox.satellite/{z}/{x}/{y}.png?access_token=pk.eyJ1IjoianBhdG9rYWwiLCJhIjoiY2lyNmFyZThqMDBiNWcybTFlOWdkZGk1MiJ9.6_VWU3skRwM68ASapMLIQg",
      satelliteAttribution:
        "Map tiles © <a href='https://www.mapbox.com/maps/satellite' target='_blank' rel='noopener'>Mapbox</a>",
      minZoom: 2,
      maxZoom: 19,
      clusterDistance: 16,
      controls: buildMapControls(),
    });
    map = mapCtx.map;

    mapCtx.flightLayer.setStyle(flightStyle);
    mapCtx.airportLayer.setStyle(airportStyle);

    OpenFlights.Maps.createSelection(
      mapCtx,
      onAirportSelect,
      onAirportUnselect
    );

    airportPopupElement = document.createElement("div");
    airportPopupElement.className = "airport-popup-shell";
    airportOverlay = OpenFlights.Maps.createOverlay(
      mapCtx,
      airportPopupElement,
      {
        positioning: "bottom-center",
        offset: [0, -10],
      }
    );

    // Keep clicks inside the popup away from the map, otherwise they would
    // deselect the airport and immediately close the popup again.
    ["mousedown", "click", "dblclick", "touchstart", "touchend"].forEach(
      function (type) {
        airportPopupElement.addEventListener(
          type,
          function (evt) {
            evt.stopPropagation();
          },
          false
        );
      }
    );

    // Label colours adapt to the (dark) satellite basemap.
    mapCtx.satellite.on("change:visible", function () {
      styleCache.clear();
      mapCtx.airportLayer.changed();
    });

    state.mapInitialized = true;

    // Read-only handle for the headless test suite (test/mobile). Only exposed
    // when the app is loaded with ?mapdebug=1.
    if (/[?&]mapdebug=1\b/.test(window.location.search)) {
      window.__openflightsMap = mapCtx;
    }
  }

  function makeLineFeatures(x1, y1, x2, y2, count, color, future) {
    const width = Math.floor(Math.sqrt(count) + 0.5);
    const dash = future === "Y";

    const paths = [gcPath({ x: x1, y: y1 }, { x: x2, y: y2 })];
    // Wrap copies so routes crossing the date line render correctly.
    if (x1 > 0 || x2 > 0) {
      paths.push(gcPath({ x: x1 - 360, y: y1 }, { x: x2 - 360, y: y2 }));
    }
    if (x1 < 0 || x2 < 0) {
      paths.push(gcPath({ x: x1 + 360, y: y1 }, { x: x2 + 360, y: y2 }));
    }

    return paths
      .filter(function (points) {
        return points.length > 1;
      })
      .map(function (points) {
        const coordinates = points.map(function (point) {
          return ol.proj.fromLonLat([point.x, point.y]);
        });
        return new ol.Feature({
          geometry: new ol.geom.LineString(coordinates),
          color: color,
          width: width,
          dash: dash,
        });
      });
  }

  function flightStyle(feature) {
    return new ol.style.Style({
      stroke: new ol.style.Stroke({
        color: hexToRgba(feature.get("color"), 0.9),
        width: feature.get("width"),
        lineDash: feature.get("dash") ? [6, 4] : undefined,
      }),
    });
  }

  function airportRadius(count, max) {
    if (max <= 0) return 4;
    return 3 + Math.round((count / max) * 5);
  }

  // Tier airports by visit count, mirroring the six airport icon tiers in
  // openflights.js. Airports with two or fewer flights always take the lowest
  // tier; the busiest airport always takes the highest.
  function airportVisitColor(count, max) {
    if (max <= 0) return AIRPORT_PIN_COLORS[0];
    let tier = Math.floor((count / max) * 6) + 1;
    if (count <= 2) tier = 0;
    if (count > 2) tier = Math.max(1, tier);
    if (tier >= 6) tier = 5;
    return AIRPORT_PIN_COLORS[tier];
  }

  function airportClusterRadius() {
    // Match the footprint of a selected cluster: OL10 clusters don't grow with
    // their member count.
    return 6;
  }

  // The airport with the most flights in a cluster. Used for the "SIN+" label
  // and to tint the cluster with its busiest airport's pin colour.
  function largestAirportFeature(members) {
    let best = null;
    members.forEach(function (feature) {
      const aCount = parseInt(feature.get("count"), 10) || 0;
      const bCount = best ? parseInt(best.get("count"), 10) || 0 : -1;
      if (
        !best ||
        aCount > bCount ||
        (aCount === bCount &&
          (feature.get("code") || "") < (best.get("code") || ""))
      ) {
        best = feature;
      }
    });
    return best;
  }

  function hexToRgba(color, alpha) {
    if (
      typeof color !== "string" ||
      color.charAt(0) !== "#" ||
      color.length !== 7
    ) {
      return color;
    }
    const r = parseInt(color.slice(1, 3), 16);
    const g = parseInt(color.slice(3, 5), 16);
    const b = parseInt(color.slice(5, 7), 16);
    return "rgba(" + r + "," + g + "," + b + "," + alpha + ")";
  }

  const styleCache = new Map();

  function airportStyle(feature) {
    const members = OpenFlights.Maps.clusterMembers(feature);
    const clustered = members.length > 1;
    const top = clustered ? largestAirportFeature(members) : members[0];
    if (!top) return null;

    const dark = Boolean(mapCtx && mapCtx.satellite.getVisible());
    const radius = clustered ? airportClusterRadius() : top.get("radius");
    const color = top.get("color") || AIRPORT_COLOR;
    const code = top.get("code") || "";
    const label = clustered ? (code ? code + "+" : "") : code;
    const key = [clustered ? 1 : 0, radius, color, label, dark ? 1 : 0].join(
      "|"
    );

    let style = styleCache.get(key);
    if (!style) {
      style = new ol.style.Style({
        image: new ol.style.Circle({
          radius: radius,
          fill: new ol.style.Fill({ color: hexToRgba(color, 0.85) }),
          stroke: new ol.style.Stroke({ color: "#ffffff", width: 1.5 }),
        }),
        text: label
          ? new ol.style.Text({
              text: label,
              font: "bold 10px -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
              textAlign: "left",
              textBaseline: "bottom",
              offsetX: 5,
              offsetY: -2,
              fill: new ol.style.Fill({
                color: dark ? "#ffffff" : "#1a1c1e",
              }),
              stroke: new ol.style.Stroke({
                color: dark ? "#000000" : "#ffffff",
                width: 2,
              }),
              overflow: true,
            })
          : undefined,
      });
      styleCache.set(key, style);
    }
    return style;
  }

  function drawMap() {
    if (!state.mapInitialized || !mapCtx) return;
    // The features are about to be rebuilt; any popup anchored to them is stale.
    closeAirportPopup(true);
    closeAirportChooser(false);
    mapCtx.flightSource.clear();
    mapCtx.airportSource.clear();

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
    mapCtx.flightSource.addFeatures(lineFeatures);

    const maxCount =
      state.data.airports.reduce(function (m, a) {
        return Math.max(m, a.count);
      }, 0) || 1;

    const airportFeatures = state.data.airports.map(function (a) {
      return new ol.Feature({
        geometry: new ol.geom.Point(ol.proj.fromLonLat([a.lon, a.lat])),
        radius: airportRadius(a.count, maxCount),
        color: airportVisitColor(a.count, maxCount),
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
    mapCtx.airportSource.addFeatures(airportFeatures);
    styleCache.clear();
    fitMap();
  }

  function fitMap() {
    if (!mapCtx) return;
    OpenFlights.Maps.fitToSource(mapCtx, mapCtx.airportSource);
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
    airportPopupVisible = false;
    if (airportOverlay) airportOverlay.setPosition(undefined);
    if (airportPopupElement) airportPopupElement.innerHTML = "";
    if (unselect) OpenFlights.Maps.clearSelection(mapCtx);
  }

  function onAirportSelect(feature) {
    const members = OpenFlights.Maps.clusterMembers(feature);
    if (members.length > 1) {
      openAirportChooser(feature, members);
    } else {
      showAirportPopup(members[0] || feature);
    }
  }

  function onAirportUnselect() {
    closeAirportPopup(false);
    closeAirportChooser(false);
  }

  function showAirportPopup(feature) {
    if (!mapCtx || !airportOverlay || !airportPopupElement) return;
    closeAirportPopup(false);

    const attrs = {
      apid: feature.get("apid") || "",
      code: feature.get("code") || "",
      name: feature.get("name") || feature.get("code") || "Airport",
      city: feature.get("city") || "",
      country: feature.get("country") || "",
    };
    const apid = attrs.apid;
    const code = attrs.code;
    const name = attrs.name;
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

    airportPopupElement.innerHTML =
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

    airportOverlay.setPosition(feature.getGeometry().getCoordinates());
    airportPopupVisible = true;

    const closeBtn = airportPopupElement.querySelector("#airport-popup-close");
    if (closeBtn) {
      closeBtn.addEventListener("click", function () {
        closeAirportPopup(true);
      });
    }
    const flightsBtn = airportPopupElement.querySelector(
      "#airport-popup-flights"
    );
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

  function openAirportChooser(clusterFeature, clusterMembers) {
    closeAirportPopup(false);
    closeAirportChooser(false);
    chooserCluster = clusterFeature;

    const counts = airportFlightCounts();
    const members = clusterMembers.slice().sort(function (a, b) {
      const ca = counts.get(a.get("apid")) || 0;
      const cb = counts.get(b.get("apid")) || 0;
      if (cb !== ca) return cb - ca;
      return (a.get("code") || "").localeCompare(b.get("code") || "");
    });

    els.chooserList.textContent = "";
    members.forEach(function (feature) {
      const a = {
        apid: feature.get("apid") || "",
        code: feature.get("code") || "",
        name: feature.get("name") || feature.get("code") || "Airport",
        city: feature.get("city") || "",
        country: feature.get("country") || "",
      };
      const code = a.code;
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
    if (unselect) OpenFlights.Maps.clearSelection(mapCtx);
  }

  function chooseAirport(feature) {
    // Keep the cluster selected so a click elsewhere still closes the popup.
    closeAirportChooser(false);
    showAirportPopup(feature);
  }

  function zoomToCluster() {
    if (!chooserCluster || !mapCtx) return;
    const center = ol.extent.getCenter(
      chooserCluster.getGeometry().getExtent()
    );
    const view = mapCtx.view;
    const nextZoom = Math.min((view.getZoom() || 0) + 1, view.getMaxZoom());
    closeAirportChooser(true);
    view.setCenter(center);
    view.setZoom(nextZoom);
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
  // Flight editor (add / edit, signed-in users only)
  // --------------------------------------------------------------------------
  function todayISO() {
    const d = new Date();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return d.getFullYear() + "-" + m + "-" + day;
  }

  // Great-circle distance in miles, matching php/greatcircle.php gcPointDistance.
  function greatCircleMiles(lon1, lat1, lon2, lat2) {
    if (lon1 === lon2 && lat1 === lat2) return 0;
    const rad = PHP_PI / 180;
    const p1 = lat1 * rad;
    const p2 = lat2 * rad;
    const theta = (lon2 - lon1) * rad;
    let d = Math.acos(
      Math.sin(p1) * Math.sin(p2) +
        Math.cos(p1) * Math.cos(p2) * Math.cos(theta)
    );
    if (d < 0) d += PHP_PI;
    return Math.floor(d * 6371.2 * 0.621);
  }

  // Duration estimate as HH:MM, matching php/helper.php gcDuration().
  function gcDurationString(miles) {
    const rawtime = Math.floor(30 + (miles / 500) * 60);
    const h = Math.floor(rawtime / 60);
    const m = rawtime % 60;
    return String(h).padStart(2, "0") + ":" + String(m).padStart(2, "0");
  }

  // Coordinates of a known airport from the loaded map data. The airports on a
  // user's own flights are always present in state.data.airports.
  function airportCoords(apid) {
    if (!apid) return null;
    const a = state.data.airports.find(function (x) {
      return String(x.apid) === String(apid);
    });
    if (!a || isNaN(a.lon) || isNaN(a.lat)) return null;
    return { lon: a.lon, lat: a.lat };
  }

  // Fill distance/duration once both endpoints have known coordinates, without
  // clobbering values the user typed by hand.
  function maybeAutoFillDistance() {
    const e = state.editor;
    if (
      e.srcLon == null ||
      e.srcLat == null ||
      e.dstLon == null ||
      e.dstLat == null
    ) {
      return;
    }
    const miles = greatCircleMiles(e.srcLon, e.srcLat, e.dstLon, e.dstLat);
    if (!els.editorDistance.value.trim() || e.distanceAuto) {
      els.editorDistance.value = String(miles);
      e.distanceAuto = true;
    }
    if (!els.editorDuration.value.trim() || e.durationAuto) {
      els.editorDuration.value = gcDurationString(miles);
      e.durationAuto = true;
    }
  }

  // Leading airline code of a flight number, e.g. "JQ123" -> "JQ".
  function flightNumberAirlineCode(number) {
    const m = /^\s*([A-Za-z]{2,3})/.exec(number || "");
    return m ? m[1].toUpperCase() : "";
  }

  function handleFlightNumberInput() {
    clearTimeout(state.editor.numberTimer);
    state.editor.numberTimer = setTimeout(lookupAirlineFromNumber, 300);
  }

  // Resolve the airline from the flight-number prefix (e.g. JQ -> Jetstar) and
  // fill the airline field, unless the user picked one by hand.
  async function lookupAirlineFromNumber() {
    const e = state.editor;
    // Only flights use IATA/ICAO airline codes; other modes have no such code.
    if (e.mode !== "F") return;
    const code = flightNumberAirlineCode(els.editorNumber.value);
    if (code.length < 2) return;
    const manual = !e.airlineAuto && els.editorAirline.value.trim() !== "";
    if (manual) return;
    if (code === e.lastNumberCode && e.alid) return;

    let items = [];
    try {
      items = JSON.parse(
        await post(API.AUTOCOMPLETE, {
          searchType: "airline",
          searchText: code,
          mode: e.mode,
          quick: "true",
        })
      );
    } catch (err) {
      items = [];
    }
    // The number may have changed while the request was in flight.
    if (flightNumberAirlineCode(els.editorNumber.value) !== code) return;

    if (!items || !items.length) {
      if (e.airlineAuto) {
        e.alid = "";
        e.airlineName = "";
        els.editorAirline.value = "";
        e.airlineAuto = false;
      }
      return;
    }

    e.alid = String(items[0].value || "");
    e.airlineName = items[0].label || "";
    els.editorAirline.value = items[0].label || "";
    e.airlineAuto = true;
    e.lastNumberCode = code;
    clearAirlineError();
  }

  function markAirlineError() {
    els.editorAirline.classList.add("input--invalid");
  }

  function clearAirlineError() {
    els.editorAirline.classList.remove("input--invalid");
  }

  function showEditorError(msg) {
    els.editorError.textContent = msg;
    els.editorError.hidden = false;
  }

  function hideEditorError() {
    els.editorError.hidden = true;
    els.editorError.textContent = "";
  }

  function renderEditorRoute() {
    const src = state.editor.srcCode;
    const dst = state.editor.dstCode;
    if (src && dst) {
      els.editorRoute.textContent = "✈ " + src + " → " + dst;
    } else if (src || dst) {
      els.editorRoute.textContent = "✈ " + (src || "?") + " → " + (dst || "?");
    } else {
      els.editorRoute.textContent = "";
    }
  }

  function setEditorMode(mode) {
    if (state.editor.mode !== mode) {
      // Airlines/operators are mode-scoped, so a stored selection no longer applies.
      state.editor.alid = "";
      state.editor.airlineName = "";
      state.editor.airlineAuto = false;
      state.editor.lastNumberCode = "";
      els.editorAirline.value = "";
      clearAirlineError();
      hideSuggestions("airline");
    }
    state.editor.mode = mode;
    document.querySelectorAll(".segmented__btn").forEach(function (b) {
      b.classList.toggle("is-active", b.dataset.mode === mode);
    });
  }

  function openFlightEditor(fid) {
    const f = fid
      ? state.data.flights.find(function (x) {
          return String(x.fid) === String(fid);
        })
      : null;

    state.editor.fid = f ? String(f.fid) : null;
    state.editor.srcApid = f ? f.srcApid : "";
    state.editor.srcCode = f ? f.srcCode : "";
    state.editor.dstApid = f ? f.dstApid : "";
    state.editor.dstCode = f ? f.dstCode : "";
    state.editor.alid = f ? f.alid : "";
    state.editor.airlineName = f ? airlineName(f) : "";
    state.editor.airlineAuto = false;
    state.editor.lastNumberCode = "";
    state.editor.mode = f ? f.mode || "F" : "F";
    state.editor.distanceAuto = false;
    state.editor.durationAuto = false;
    clearTimeout(state.editor.numberTimer);

    // Keep coordinates for the prefilled endpoints so editing just one airport
    // can still recompute distance/duration.
    const srcCoords = f ? airportCoords(f.srcApid) : null;
    const dstCoords = f ? airportCoords(f.dstApid) : null;
    state.editor.srcLon = srcCoords ? srcCoords.lon : null;
    state.editor.srcLat = srcCoords ? srcCoords.lat : null;
    state.editor.dstLon = dstCoords ? dstCoords.lon : null;
    state.editor.dstLat = dstCoords ? dstCoords.lat : null;

    els.editorTitle.textContent = f ? "Edit flight" : "Add flight";
    els.editorFrom.value = f ? f.srcCode : "";
    els.editorTo.value = f ? f.dstCode : "";
    els.editorDate.value = f ? f.date : todayISO();
    els.editorTime.value = f && f.srcTime ? f.srcTime.slice(0, 5) : "";
    els.editorNumber.value = f ? f.code || "" : "";
    els.editorAirline.value = f ? airlineName(f) : "";
    els.editorPlane.value = f ? f.plane || "" : "";
    els.editorSeat.value = f ? f.seat || "" : "";
    els.editorSeatType.value = f ? f.seatType || "" : "";
    els.editorClass.value = f ? f.class || "" : "";
    els.editorReason.value = f ? f.reason || "" : "";

    // The API stores distance in miles; convert the read-back value if the
    // user's profile displays kilometres.
    const displayUnit =
      (state.data.stats && state.data.stats.distance_unit) || "mi";
    let distance = f ? num(f.distance) : 0;
    if (displayUnit === "km") distance = distance / KM_PER_MILE;
    els.editorDistance.value =
      f && distance ? String(Math.round(distance)) : "";
    els.editorDuration.value = f ? f.duration || "" : "";
    // Treat prefilled estimates as auto values, so changing an endpoint in an
    // edit recomputes them (a hand edit clears the flag again).
    state.editor.distanceAuto = !!(f && distance);
    state.editor.durationAuto = !!(f && f.duration);
    els.editorRegistration.value = f ? f.registration || "" : "";
    els.editorNote.value = f ? f.note || "" : "";

    setEditorMode(state.editor.mode);
    renderEditorRoute();
    hideAllSuggestions();
    clearAirlineError();
    hideEditorError();

    // Delete is only meaningful for an existing entry.
    els.editorDelete.hidden = !f;
    els.editorDelete.disabled = false;
    setDeleteArmed(false);

    els.editorBackdrop.hidden = false;
    els.editor.hidden = false;
    setTimeout(function () {
      els.editorFrom.focus();
    }, 60);
  }

  function closeFlightEditor() {
    els.editor.hidden = true;
    els.editorBackdrop.hidden = true;
    clearTimeout(state.editor.numberTimer);
    setDeleteArmed(false);
    hideAllSuggestions();
  }

  function hideSuggestions(field) {
    const cfg = AUTO[field];
    cfg.list.hidden = true;
    cfg.list.textContent = "";
  }

  function hideAllSuggestions() {
    Object.keys(AUTO).forEach(hideSuggestions);
  }

  function scheduleAutocomplete(field) {
    const cfg = AUTO[field];
    clearTimeout(cfg.timer);
    cfg.timer = setTimeout(function () {
      fetchAutocomplete(field);
    }, 250);
  }

  async function fetchAutocomplete(field) {
    const cfg = AUTO[field];
    const text = cfg.input.value.trim();
    if (text.length < 2) {
      hideSuggestions(field);
      return;
    }

    const req = ++cfg.seq;
    const params = {
      searchType: cfg.type,
      searchText: text,
      quick: "false",
    };
    // Airlines are scoped to the currently selected travel mode.
    if (cfg.type === "airline") params.mode = state.editor.mode;

    let items = [];
    try {
      items = JSON.parse(await post(API.AUTOCOMPLETE, params));
    } catch (e) {
      items = [];
    }
    if (req !== cfg.seq) return;
    renderSuggestions(field, items || []);
  }

  function renderSuggestions(field, items) {
    const cfg = AUTO[field];
    const list = cfg.list;
    list.textContent = "";

    if (!items.length) {
      list.appendChild(
        el("div", { class: "autocomplete__empty", text: "No matches" })
      );
      list.hidden = false;
      return;
    }

    items.forEach(function (item) {
      list.appendChild(
        el(
          "button",
          {
            class: "autocomplete__item",
            type: "button",
            onclick: function () {
              selectAutocomplete(field, item);
            },
          },
          el("span", {
            class: "autocomplete__item--main",
            text: item.label || item.value || "",
          })
        )
      );
    });
    list.hidden = false;
  }

  function selectAutocomplete(field, item) {
    const cfg = AUTO[field];
    if (field === "from" || field === "to") {
      // value format: CODE:apid:x:y:timezone:dst
      const parts = String(item.value || "").split(":");
      const code = parts[0] || "";
      const apid = parts[1] || "";
      const lon = num(parts[2]);
      const lat = num(parts[3]);
      if (field === "from") {
        state.editor.srcApid = apid;
        state.editor.srcCode = code;
        state.editor.srcLon = lon;
        state.editor.srcLat = lat;
      } else {
        state.editor.dstApid = apid;
        state.editor.dstCode = code;
        state.editor.dstLon = lon;
        state.editor.dstLat = lat;
      }
      cfg.input.value = item.label || code;
    } else if (field === "airline") {
      state.editor.alid = String(item.value || "");
      state.editor.airlineName = item.label || "";
      state.editor.airlineAuto = false;
      cfg.input.value = item.label || "";
      clearAirlineError();
    } else if (field === "plane") {
      // submit.php matches/creates planes by name, so the label is enough.
      cfg.input.value = item.label || item.value || "";
    }
    hideSuggestions(field);
    renderEditorRoute();
    if (field === "from" || field === "to") maybeAutoFillDistance();
  }

  function handleAutocompleteInput(field) {
    // Typing replaces any previously selected value with free text, which we
    // only accept once the user picks a suggestion (airports/airlines).
    if (field === "from") {
      state.editor.srcApid = "";
      state.editor.srcCode = "";
      state.editor.srcLon = null;
      state.editor.srcLat = null;
    } else if (field === "to") {
      state.editor.dstApid = "";
      state.editor.dstCode = "";
      state.editor.dstLon = null;
      state.editor.dstLat = null;
    } else if (field === "airline") {
      state.editor.alid = "";
      state.editor.airlineName = "";
      // A hand-typed airline should no longer be overwritten from the number.
      state.editor.airlineAuto = false;
      clearAirlineError();
    }
    renderEditorRoute();
    scheduleAutocomplete(field);
  }

  async function saveFlight() {
    hideEditorError();
    if (!state.editor.srcApid || !state.editor.dstApid) {
      showEditorError("Select both From and To airports.");
      return;
    }
    const date = els.editorDate.value.trim();
    if (!date) {
      showEditorError("Enter a date.");
      return;
    }

    // An airline typed but not selected from the suggestions is unknown; refuse
    // to save rather than silently logging it as "Unknown".
    const airlineText = els.editorAirline.value.trim();
    if (airlineText && (!state.editor.alid || state.editor.alid === "0")) {
      markAirlineError();
      showEditorError(
        "Unknown airline. Pick one from the suggestions or clear the field."
      );
      els.editorAirline.focus();
      return;
    }
    clearAirlineError();

    const params = {
      param: state.editor.fid ? "EDIT" : "ADD",
      fid: state.editor.fid || "",
      src_apid: state.editor.srcApid,
      dst_apid: state.editor.dstApid,
      src_date: date,
      src_time: els.editorTime.value.trim(),
      mode: state.editor.mode,
      number: els.editorNumber.value.trim(),
      alid: state.editor.alid || "",
      plane: els.editorPlane.value.trim(),
      seat: els.editorSeat.value.trim(),
      type: els.editorSeatType.value,
      class: els.editorClass.value,
      reason: els.editorReason.value,
      distance: els.editorDistance.value.trim(),
      duration: els.editorDuration.value.trim(),
      registration: els.editorRegistration.value.trim(),
      note: els.editorNote.value,
      trid: "NULL",
    };

    const submitBtn = document.querySelector(
      "#flight-editor-form .btn--primary"
    );
    if (submitBtn) submitBtn.disabled = true;

    let text;
    try {
      text = await post(API.SUBMIT, params);
    } catch (e) {
      if (submitBtn) submitBtn.disabled = false;
      showEditorError("Could not save. Try again.");
      return;
    }
    if (submitBtn) submitBtn.disabled = false;

    // submit.php returns "1;Added." / "2;Edited." on success and "0;…" or
    // "Error;…" on failure.
    if (/^[12];/.test(text || "")) {
      closeFlightEditor();
      toast(state.editor.fid ? "Flight updated." : "Flight added.");
      state.airportFilter = null;
      await loadAll();
    } else {
      showEditorError(parseError(text) || "Could not save.");
    }
  }

  // Deleting is a two-step action: the first tap arms it so a stray tap in the
  // editor footer cannot remove a flight, the second tap deletes.
  function setDeleteArmed(armed) {
    clearTimeout(state.editor.deleteTimer);
    state.editor.deleteArmed = armed;
    state.editor.deleteTimer = null;
    els.editorDelete.classList.toggle("is-armed", armed);
    els.editorDelete.textContent = armed ? "Confirm delete" : "Delete";
    if (armed) {
      state.editor.deleteTimer = setTimeout(function () {
        setDeleteArmed(false);
      }, 4000);
    }
  }

  async function deleteFlight() {
    if (!state.editor.fid) return;
    if (!state.editor.deleteArmed) {
      setDeleteArmed(true);
      return;
    }

    clearTimeout(state.editor.deleteTimer);
    state.editor.deleteTimer = null;
    state.editor.deleteArmed = false;
    els.editorDelete.disabled = true;
    hideEditorError();

    let text;
    try {
      text = await post(API.SUBMIT, {
        param: "DELETE",
        fid: state.editor.fid,
        mode: state.editor.mode,
      });
    } catch (e) {
      els.editorDelete.disabled = false;
      setDeleteArmed(false);
      showEditorError("Could not delete. Try again.");
      return;
    }
    els.editorDelete.disabled = false;

    // submit.php returns "100;…" for a successful delete, "0;…" or "Error;…"
    // otherwise.
    if (/^100;/.test(text || "")) {
      const label = MODE_NAMES[state.editor.mode] || "Entry";
      closeFlightEditor();
      toast(label + " deleted.");
      state.airportFilter = null;
      await loadAll();
    } else {
      setDeleteArmed(false);
      showEditorError(parseError(text) || "Could not delete.");
    }
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
    updateFab();

    // Switching tabs always collapses the filter pane.
    setFilterPanelOpen(false);
    updateFilterToggle();

    // The popup/chooser live on the map, so they make no sense once it is hidden.
    if (view !== "map") {
      closeAirportPopup(true);
      closeAirportChooser(false);
    }

    if (view === "map") {
      if (
        !state.mapInitialized &&
        typeof ol !== "undefined" &&
        typeof OpenFlights.Maps !== "undefined"
      ) {
        initMap();
        map.updateSize();
        drawMap();
      } else if (
        typeof ol === "undefined" ||
        typeof OpenFlights.Maps === "undefined"
      ) {
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
    const self = state.mode === "self";
    els.userChip.textContent = self ? state.username || "Me" : "Login";
    els.userChip.classList.toggle("chip--self", self);
    els.userChip.classList.toggle("chip--login", !self);
    updateFab();
  }

  // The add-flight FAB is available to signed-in users on the map and flights
  // views (not on the stats-heavy overview).
  function updateFab() {
    const onDataView =
      state.currentView === "map" || state.currentView === "flights";
    els.addFlight.hidden = !(state.mode === "self" && onDataView);
  }

  function renderYearSelect() {
    const sel = els.yearSelect;
    sel.textContent = "";
    sel.appendChild(new Option("All years", "0"));
    let found = !state.year || state.year === "0";
    state.data.years.forEach(function (y) {
      sel.appendChild(new Option(y.label, y.year));
      if (y.year === state.year) found = true;
    });
    if (!found) {
      // The chosen year isn't in the new list (e.g. a newly selected carrier
      // didn't fly then); clear it and reload with the stale filter removed.
      state.year = "0";
      sel.value = "0";
      return false;
    }
    sel.value = state.year || "0";
    return true;
  }

  function renderModeSelect() {
    const sel = els.modeSelect;
    sel.textContent = "";
    sel.appendChild(new Option("All modes", "0"));
    let found = !state.modeFilter || state.modeFilter === "0";
    state.data.modes.forEach(function (m) {
      sel.appendChild(
        new Option(m.label || MODE_NAMES[m.code] || m.code, m.code)
      );
      if (m.code === state.modeFilter) found = true;
    });
    if (!found) {
      state.modeFilter = "0";
      sel.value = "0";
      return false;
    }
    sel.value = state.modeFilter || "0";
    return true;
  }

  function renderClassSelect() {
    const sel = els.classSelect;
    sel.textContent = "";
    sel.appendChild(new Option("All classes", "0"));
    let found = !state.class || state.class === "0";
    state.data.classes.forEach(function (c) {
      sel.appendChild(
        new Option(c.label || CLASS_NAMES[c.code] || c.code, c.code)
      );
      if (c.code === state.class) found = true;
    });
    if (!found) {
      state.class = "0";
      sel.value = "0";
      return false;
    }
    sel.value = state.class || "0";
    return true;
  }

  function activeFilterCount() {
    let count = 0;
    if (state.year && state.year !== "0") count++;
    if (state.carrier && state.carrier !== "0") count++;
    if (state.modeFilter && state.modeFilter !== "0") count++;
    if (state.class && state.class !== "0") count++;
    return count;
  }

  function setFilterPanelOpen(open) {
    els.filterPanel.hidden = !open;
    els.filterPanel.style.display = open ? "grid" : "none";
  }

  function updateFilterToggle() {
    const count = activeFilterCount();
    els.filterToggle.textContent = count ? "Filter (" + count + ")" : "Filter";
    els.filterToggle.classList.toggle("is-active", count > 0);
    els.filterToggle.setAttribute(
      "aria-expanded",
      els.filterPanel.hidden ? "false" : "true"
    );
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
    const loggedIn = state.mode === "self";
    els.sheetTitle.textContent = loggedIn ? "Account" : "Login";
    els.sheetStatus.hidden = loggedIn;
    els.sheetStatus.textContent = loggedIn
      ? ""
      : "Not logged in. Showing demo data.";
    els.loginForm.hidden = loggedIn;
    els.logoutBtn.hidden = !loggedIn;
    const actions = els.logoutBtn.closest(".sheet__section--actions");
    if (actions) actions.hidden = !loggedIn;
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
      state.modeFilter = "0";
      state.class = "0";
      state.airportFilter = null;
      els.yearSelect.value = "0";
      els.carrierSelect.value = "0";
      els.modeSelect.value = "0";
      els.classSelect.value = "0";
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
    state.modeFilter = "0";
    state.class = "0";
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

    // Keep the map sized correctly when the browser is resized, a tablet is
    // rotated, or a hidden tab becomes visible again.
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

    els.filterToggle.addEventListener("click", function () {
      setFilterPanelOpen(els.filterPanel.hidden);
      updateFilterToggle();
    });

    els.clearFilters.addEventListener("click", function () {
      state.year = "0";
      state.carrier = "0";
      state.modeFilter = "0";
      state.class = "0";
      setFilterPanelOpen(false);
      updateFilterToggle();
      loadAll();
    });

    els.yearSelect.addEventListener("change", function () {
      state.year = els.yearSelect.value;
      loadAll();
    });

    els.carrierSelect.addEventListener("change", function () {
      state.carrier = els.carrierSelect.value;
      loadAll();
    });

    els.modeSelect.addEventListener("change", function () {
      state.modeFilter = els.modeSelect.value;
      loadAll();
    });

    els.classSelect.addEventListener("change", function () {
      state.class = els.classSelect.value;
      loadAll();
    });

    els.airportFilterClear.addEventListener("click", function () {
      state.airportFilter = null;
      renderFlightList();
    });

    // Tapping outside the map or popup also dismisses the airport popup.
    document.addEventListener("click", function (evt) {
      if (!airportPopupVisible || !airportPopupElement) return;
      if (airportPopupElement.contains(evt.target)) return;
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

    // Flight editor
    Object.keys(MODE_NAMES).forEach(function (m) {
      els.editorMode.appendChild(
        el("button", {
          class: "segmented__btn",
          type: "button",
          "data-mode": m,
          text: MODE_NAMES[m],
          onclick: function () {
            setEditorMode(m);
          },
        })
      );
    });

    els.addFlight.addEventListener("click", function () {
      openFlightEditor(null);
    });
    els.editorClose.addEventListener("click", closeFlightEditor);
    els.editorCancel.addEventListener("click", closeFlightEditor);
    els.editorDelete.addEventListener("click", deleteFlight);
    els.editorBackdrop.addEventListener("click", closeFlightEditor);
    els.editorForm.addEventListener("submit", function (e) {
      e.preventDefault();
      saveFlight();
    });

    Object.keys(AUTO).forEach(function (field) {
      AUTO[field].input.addEventListener("input", function () {
        handleAutocompleteInput(field);
      });
    });

    // Flight number drives the airline; distance/duration stop auto-filling
    // once the user edits them by hand.
    els.editorNumber.addEventListener("input", handleFlightNumberInput);
    els.editorDistance.addEventListener("input", function () {
      state.editor.distanceAuto = false;
    });
    els.editorDuration.addEventListener("input", function () {
      state.editor.durationAuto = false;
    });

    // Close any open suggestion list when tapping outside its field.
    document.addEventListener("click", function (evt) {
      Object.keys(AUTO).forEach(function (field) {
        const cfg = AUTO[field];
        if (
          !cfg.list.hidden &&
          !cfg.input.contains(evt.target) &&
          !cfg.list.contains(evt.target)
        ) {
          cfg.list.hidden = true;
        }
      });
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
