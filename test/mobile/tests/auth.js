"use strict";

/**
 * Tests that require a signed-in user. The runner creates and logs into a
 * disposable test account before these run and (by default) resets its flights
 * afterwards. If login is unavailable these tests are skipped.
 */

const h = require("../lib/harness");

module.exports = [
  {
    name: "fab: visible on map and flights, hidden on overview",
    async run({ page }) {
      await h.click(page, '.tabbar__tab[data-view="map"]');
      await page.waitForSelector("#view-map.is-active");
      h.assert(
        !(await page.$eval("#add-flight", (el) => el.hidden)),
        "FAB visible on map"
      );

      await h.click(page, '.tabbar__tab[data-view="flights"]');
      h.assert(
        !(await page.$eval("#add-flight", (el) => el.hidden)),
        "FAB visible on flights"
      );

      await h.click(page, '.tabbar__tab[data-view="overview"]');
      h.assert(
        await page.$eval("#add-flight", (el) => el.hidden),
        "FAB hidden on overview"
      );

      await h.click(page, '.tabbar__tab[data-view="flights"]');
    },
  },
  {
    name: "editor: opens and requires airports",
    async run({ page }) {
      await h.openAddEditor(page);
      await h.click(page, "#flight-editor-form button[type=submit]");
      await page.waitForFunction(
        () => {
          const error = document.getElementById("editor-error");
          return !error.hidden && /airport/i.test(error.textContent);
        },
        { timeout: 10000 }
      );
      await h.closeEditor(page);
    },
  },
  {
    name: "editor: airport autocomplete fills route and distance",
    async run({ page }) {
      await h.openAddEditor(page);
      await h.selectAirport(page, "#editor-from", "#from-suggestions", "SIN");
      await h.selectAirport(page, "#editor-to", "#to-suggestions", "JFK");

      await page.waitForFunction(
        () =>
          document.getElementById("editor-route").textContent.includes("SIN"),
        { timeout: 10000 }
      );
      await page.waitForFunction(
        () => {
          const distance = document.getElementById("editor-distance").value;
          const duration = document.getElementById("editor-duration").value;
          return Number(distance) > 0 && /^\d{2}:\d{2}$/.test(duration);
        },
        { timeout: 10000 }
      );

      const route = await page.$eval("#editor-route", (el) =>
        el.textContent.trim()
      );
      h.assertIncludes(route, "SIN", "route origin");
      h.assertIncludes(route, "JFK", "route destination");

      await h.closeEditor(page);
    },
  },
  {
    name: "editor: flight number fills the airline",
    async run({ page }) {
      await h.openAddEditor(page);
      await h.typeInto(page, "#editor-number", "JQ123");
      await page.waitForFunction(
        () => /Jetstar/.test(document.getElementById("editor-airline").value),
        { timeout: 20000 }
      );
      const airline = await page.$eval("#editor-airline", (el) => el.value);
      h.assertIncludes(
        airline,
        "Jetstar",
        "airline inferred from flight number"
      );
      await h.closeEditor(page);
    },
  },
  {
    name: "editor: unknown airline is flagged and blocks save",
    async run({ page }) {
      await h.openAddEditor(page);
      await h.selectAirport(page, "#editor-from", "#from-suggestions", "SIN");
      await h.selectAirport(page, "#editor-to", "#to-suggestions", "JFK");

      await h.typeInto(page, "#editor-airline", "Definitely Not An Airline");
      // Dismiss the suggestion dropdown before clicking Save.
      await h.click(page, "#editor-note");
      await h.click(page, "#flight-editor-form button[type=submit]");

      await page.waitForFunction(
        () => {
          const error = document.getElementById("editor-error");
          return !error.hidden && /unknown airline/i.test(error.textContent);
        },
        { timeout: 10000 }
      );
      h.assert(
        await page.$eval("#editor-airline", (el) =>
          el.classList.contains("input--invalid")
        ),
        "airline field flagged red"
      );
      h.assert(
        !(await page.$eval("#flight-editor", (el) => el.hidden)),
        "editor stays open after validation error"
      );

      await h.closeEditor(page);
    },
  },
  {
    name: "add flight: saves and appears in the list",
    async run({ page, state }) {
      const marker = "e2e-" + Date.now();
      state.marker = marker;

      await h.openAddEditor(page);
      await h.selectAirport(page, "#editor-from", "#from-suggestions", "SIN");
      await h.selectAirport(page, "#editor-to", "#to-suggestions", "JFK");
      await h.typeInto(page, "#editor-number", "JQ123");
      await page.waitForFunction(
        () => /Jetstar/.test(document.getElementById("editor-airline").value),
        { timeout: 20000 }
      );

      await h.click(page, "#editor-note");
      await h.typeInto(page, "#editor-note", marker);
      await h.click(page, "#flight-editor-form button[type=submit]");

      await page.waitForFunction(
        () => document.getElementById("flight-editor").hidden,
        { timeout: 20000 }
      );
      await h.waitForCardWith(page, marker);
      await h.waitForCardWith(page, "SIN → JFK");
    },
  },
  {
    name: "edit flight: prefills and saves changes",
    async run({ page, state }) {
      h.assert(state.marker, "the add-flight test must run first");

      const opened = await page.evaluate((marker) => {
        const card = Array.from(
          document.querySelectorAll("#flight-list .flight-card")
        ).find((candidate) => candidate.textContent.includes(marker));
        if (!card) return false;
        card.click();
        return true;
      }, state.marker);
      h.assert(opened, "added flight card found");

      await page.waitForSelector("#flight-editor:not([hidden])", {
        timeout: 10000,
      });
      await page.waitForFunction(
        () =>
          document.getElementById("flight-editor-title").textContent ===
          "Edit flight",
        { timeout: 10000 }
      );

      h.assertIncludes(
        await page.$eval("#editor-number", (el) => el.value),
        "JQ123",
        "prefilled flight number"
      );
      h.assertIncludes(
        await page.$eval("#editor-airline", (el) => el.value),
        "Jetstar",
        "prefilled airline"
      );
      h.assertIncludes(
        await page.$eval("#editor-from", (el) => el.value),
        "SIN",
        "prefilled origin"
      );
      h.assertIncludes(
        await page.$eval("#editor-to", (el) => el.value),
        "JFK",
        "prefilled destination"
      );

      await page.$eval("#editor-seat", (el) => (el.value = ""));
      await h.typeInto(page, "#editor-seat", "1A");
      await h.click(page, "#flight-editor-form button[type=submit]");

      await page.waitForFunction(
        (marker) =>
          Array.from(
            document.querySelectorAll("#flight-list .flight-card")
          ).some(
            (card) =>
              card.textContent.includes(marker) &&
              card.textContent.includes("Seat 1A")
          ),
        { timeout: 20000 },
        state.marker
      );
    },
  },
  {
    name: "cleanup: resets the disposable test account",
    skip: (ctx) => !ctx.canReset,
    async run({ page }) {
      const response = await h.resetFlights(page);
      h.assertMatch(response, /^10;/, "reset response");
    },
  },
  {
    name: "auth sheet: shows only log out when signed in",
    async run({ page }) {
      await h.click(page, "#user-chip");
      await page.waitForSelector("#user-sheet:not([hidden])");
      h.assert(
        await page.$eval("#login-form", (el) => el.hidden),
        "login form hidden when signed in"
      );
      h.assert(
        !(await page.$eval("#logout-btn", (el) => el.hidden)),
        "logout button visible when signed in"
      );
      await h.click(page, "#sheet-close");
    },
  },
  {
    name: "logout: returns to demo mode",
    async run({ page }) {
      await h.logout(page);
      h.assertEqual(
        await page.$eval("#user-chip", (el) => el.textContent.trim()),
        "Login",
        "user chip after logout"
      );
      h.assert(
        await page.$eval("#add-flight", (el) => el.hidden),
        "FAB hidden after logout"
      );
    },
  },
];
