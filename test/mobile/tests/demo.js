"use strict";

/**
 * Tests that exercise the app in demo (logged out) mode. These do not touch
 * the database and run before the signed-in suite.
 */

const h = require("../lib/harness");

module.exports = [
  {
    name: "boot: app shell renders in demo mode",
    async run({ page }) {
      h.assertEqual(await page.title(), "OpenFlights", "document title");

      const logo = await page.$eval(".brand__img", (el) =>
        el.getAttribute("src")
      );
      h.assertIncludes(logo, "img/openflights-logo.png", "brand logo");

      const tabs = await page.$$eval(".tabbar__tab", (els) => els.length);
      h.assertEqual(tabs, 3, "tab count");

      const chip = await page.$eval("#user-chip", (el) =>
        el.textContent.trim()
      );
      h.assertEqual(chip, "Login", "user chip");

      h.assert(
        await page.$eval("#add-flight", (el) => el.hidden),
        "add-flight FAB is hidden when logged out"
      );
      h.assert(
        await page.$eval("#filter-panel", (el) => el.hidden),
        "filter panel starts closed"
      );
    },
  },
  {
    name: "boot: demo flight data loads",
    async run({ page }) {
      const count = await page.$eval("#flight-count", (el) =>
        el.textContent.trim()
      );
      h.assertMatch(count, /^\d+\s+flights$/, "flight count");

      const cards = await page.$$eval(
        "#flight-list .flight-card",
        (els) => els.length
      );
      h.assert(cards > 0, "expected at least one demo flight card");
    },
  },
  {
    name: "navigation: tabs switch the active view",
    async run({ page }) {
      await h.click(page, '.tabbar__tab[data-view="overview"]');
      h.assert(
        await page.$eval("#view-overview", (el) =>
          el.classList.contains("is-active")
        ),
        "overview view active"
      );
      h.assertEqual(
        await page.$eval('.tabbar__tab[data-view="overview"]', (el) =>
          el.getAttribute("aria-selected")
        ),
        "true",
        "overview tab selected"
      );

      await h.click(page, '.tabbar__tab[data-view="flights"]');
      h.assert(
        await page.$eval("#view-flights", (el) =>
          el.classList.contains("is-active")
        ),
        "flights view active"
      );

      await h.click(page, '.tabbar__tab[data-view="map"]');
      h.assert(
        await page.$eval("#view-map", (el) =>
          el.classList.contains("is-active")
        ),
        "map view active"
      );
      h.assert(
        !(await page.$eval("#view-overview", (el) =>
          el.classList.contains("is-active")
        )),
        "overview view inactive"
      );
    },
  },
  {
    name: "overview: hero, perspective, extremes and pies render",
    async run({ page }) {
      await h.click(page, '.tabbar__tab[data-view="overview"]');
      await page.waitForSelector("#view-overview.is-active");

      const titles = await page.$$eval("#view-overview .card__title", (els) =>
        els.map((el) => el.textContent.trim())
      );
      [
        "Distance in perspective",
        "Places",
        "Record flights",
        "Extremes",
        "By mode",
        "By class",
        "By reason",
        "By seat",
      ].forEach((title) =>
        h.assert(titles.includes(title), "missing card: " + title)
      );

      const hero = await page.$$eval(
        "#view-overview .hero__item",
        (els) => els.length
      );
      h.assertEqual(hero, 3, "hero stats");

      const perspective = await page.evaluate(() => {
        const card = Array.from(
          document.querySelectorAll("#view-overview .card")
        ).find(
          (candidate) =>
            candidate.querySelector(".card__title") &&
            candidate.querySelector(".card__title").textContent.trim() ===
              "Distance in perspective"
        );
        if (!card) return null;
        return {
          values: Array.from(card.querySelectorAll(".tile__value")).map((el) =>
            el.textContent.trim()
          ),
          labels: Array.from(card.querySelectorAll(".tile__label")).map((el) =>
            el.textContent.trim()
          ),
        };
      });
      h.assert(perspective, "distance in perspective card");
      h.assertEqual(perspective.values.length, 3, "perspective values");
      perspective.values.forEach((value) =>
        h.assertMatch(value, /^[\d.]+x$/, "perspective value")
      );
      h.assert(
        perspective.labels.includes("Round the world"),
        "round-the-world label"
      );
      h.assert(perspective.labels.includes("To the Moon"), "moon label");
      h.assert(perspective.labels.includes("To Mars"), "mars label");

      const coords = await page.$$eval(
        "#view-overview .extreme__coord",
        (els) => els.map((el) => el.textContent.trim())
      );
      h.assertEqual(coords.length, 4, "extreme coordinates");
      coords.forEach((coord) =>
        h.assertMatch(coord, /^\d+(\.\d+)?°[NSEW]$/, "extreme coordinate")
      );

      const pies = await page.$$eval(
        "#view-overview svg.pie",
        (els) => els.length
      );
      h.assertEqual(pies, 4, "pie charts");
      const slices = await page.$$eval(
        "#view-overview svg.pie path, #view-overview svg.pie circle",
        (els) => els.length
      );
      h.assert(slices >= 4, "pie slices rendered");
    },
  },
  {
    name: "flights: list, search and sort",
    async run({ page }) {
      await h.click(page, '.tabbar__tab[data-view="flights"]');
      await page.waitForSelector("#view-flights.is-active");

      const before = await page.$$eval(
        "#flight-list .flight-card",
        (els) => els.length
      );
      h.assert(before > 0, "flight cards rendered");

      // Derive a search term from the first card so at least one match exists.
      const firstRoute = await page.$eval(".flight-card__route", (el) =>
        el.textContent.trim()
      );
      const match = /([A-Z]{3})\s*→\s*([A-Z]{3})/.exec(firstRoute);
      h.assert(match, "first card shows a route");
      const code = match[2];

      await h.setInputValue(page, "#flight-search", code);
      await page.waitForFunction(
        () => document.querySelectorAll("#flight-list .flight-card").length > 0,
        { timeout: 10000 }
      );

      const after = await page.$$eval(
        "#flight-list .flight-card",
        (els) => els.length
      );
      h.assert(after > 0 && after <= before, "search narrows the list");

      await h.setInputValue(page, "#flight-search", "");
      await page.waitForFunction(
        (expected) =>
          document.querySelectorAll("#flight-list .flight-card").length >=
          expected,
        { timeout: 10000 },
        before
      );

      await page.select("#flight-sort", "date-asc");
      await page.waitForFunction(() => {
        const dates = Array.from(
          document.querySelectorAll(".flight-card__date")
        ).map((el) => el.textContent.trim().slice(0, 10));
        if (dates.length === 0) return false;
        for (let i = 1; i < dates.length; i++) {
          if (dates[i] < dates[i - 1]) return false;
        }
        return true;
      });

      await page.select("#flight-sort", "date-desc");
      await page.waitForFunction(() => {
        const dates = Array.from(
          document.querySelectorAll(".flight-card__date")
        ).map((el) => el.textContent.trim().slice(0, 10));
        if (dates.length === 0) return false;
        for (let i = 1; i < dates.length; i++) {
          if (dates[i] > dates[i - 1]) return false;
        }
        return true;
      });
    },
  },
  {
    name: "flights: cards are read-only in demo mode",
    async run({ page }) {
      await h.click(page, '.tabbar__tab[data-view="flights"]');
      await page.waitForSelector("#view-flights.is-active");
      await h.click(page, "#flight-list .flight-card");
      await h.sleep(300);
      h.assert(
        await page.$eval("#flight-editor", (el) => el.hidden),
        "editor must not open for demo data"
      );
    },
  },
  {
    name: "filters: toggle, apply, cross-count and clear",
    async run({ page }) {
      await h.click(page, '.tabbar__tab[data-view="flights"]');

      await h.click(page, "#filter-toggle");
      await page.waitForSelector("#filter-panel:not([hidden])");
      h.assertEqual(
        await page.$eval("#filter-toggle", (el) =>
          el.getAttribute("aria-expanded")
        ),
        "true",
        "filter expanded"
      );

      const year = await page.$$eval("#year-select option", (options) =>
        options.map((option) => option.value).find((value) => value !== "0")
      );
      h.assert(year, "a year option exists");
      await page.select("#year-select", year);
      await page.waitForFunction(() =>
        /^Filter \(\d+\)$/.test(
          document.getElementById("filter-toggle").textContent.trim()
        )
      );
      h.assertEqual(
        await page.$eval("#filter-toggle", (el) => el.textContent.trim()),
        "Filter (1)",
        "one filter active"
      );

      // The carrier list is reloaded for the chosen year; pick one of its
      // remaining options and confirm it counts as a second filter.
      await page.waitForFunction(() => {
        const select = document.getElementById("carrier-select");
        return (
          select &&
          Array.from(select.options).some((option) => option.value !== "0")
        );
      });
      const carrier = await page.$$eval("#carrier-select option", (options) =>
        options.map((option) => option.value).find((value) => value !== "0")
      );
      h.assert(carrier, "a carrier option exists for the chosen year");
      await page.select("#carrier-select", carrier);
      await page.waitForFunction(
        () =>
          document.getElementById("filter-toggle").textContent.trim() ===
          "Filter (2)"
      );

      await h.click(page, "#clear-filters");
      await page.waitForFunction(
        () => document.getElementById("filter-panel").hidden
      );
      // Clearing triggers a reload that rebuilds the selects; wait for it to
      // settle before asserting their values.
      await page.waitForFunction(
        () =>
          document.getElementById("year-select").value === "0" &&
          document.getElementById("carrier-select").value === "0"
      );
      h.assertEqual(
        await page.$eval("#filter-toggle", (el) => el.textContent.trim()),
        "Filter",
        "filters cleared"
      );
      h.assertEqual(
        await page.$eval("#year-select", (el) => el.value),
        "0",
        "year reset"
      );
      h.assertEqual(
        await page.$eval("#carrier-select", (el) => el.value),
        "0",
        "carrier reset"
      );
    },
  },
  {
    name: "filters: mode and class constrain results",
    async run({ page }) {
      await h.click(page, '.tabbar__tab[data-view="flights"]');

      await h.click(page, "#filter-toggle");
      await page.waitForSelector("#filter-panel:not([hidden])");
      const mode = await page.$$eval("#mode-select option", (options) =>
        options.map((option) => option.value).find((value) => value !== "0")
      );
      h.assert(mode, "a mode option exists");
      await page.select("#mode-select", mode);
      await page.waitForFunction(
        () =>
          document.getElementById("filter-toggle").textContent.trim() ===
          "Filter (1)"
      );
      await h.click(page, "#clear-filters");
      await page.waitForFunction(
        () => document.getElementById("filter-panel").hidden
      );

      await h.click(page, "#filter-toggle");
      await page.waitForSelector("#filter-panel:not([hidden])");
      await page.waitForFunction(() => {
        const select = document.getElementById("class-select");
        return (
          select &&
          Array.from(select.options).some((option) => option.value !== "0")
        );
      });
      const clazz = await page.$$eval("#class-select option", (options) =>
        options.map((option) => option.value).find((value) => value !== "0")
      );
      h.assert(clazz, "a class option exists");
      await page.select("#class-select", clazz);
      await page.waitForFunction(
        () =>
          document.getElementById("filter-toggle").textContent.trim() ===
          "Filter (1)"
      );
      await h.click(page, "#clear-filters");
      await page.waitForFunction(
        () => document.getElementById("filter-panel").hidden
      );
    },
  },
  {
    name: "map: OpenLayers initialises with controls",
    async run({ page }) {
      await h.click(page, '.tabbar__tab[data-view="map"]');
      await page.waitForSelector("#view-map.is-active");
      await page.waitForSelector("#map .olMapViewport", { timeout: 15000 });

      for (const control of [
        ".olControlPanZoom",
        ".olControlLayerSwitcher",
        ".olControlScaleLine",
      ]) {
        const found = await page.$$(`#map ${control}`);
        h.assert(found.length > 0, "missing map control " + control);
      }
    },
  },
];
