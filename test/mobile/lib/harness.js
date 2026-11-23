"use strict";

/**
 * Shared helpers for the OpenFlights mobile headless test suite.
 *
 * The suite drives a real Chrome/Chromium via puppeteer-core (no bundled
 * browser): set CHROME_PATH if it is not at the usual Linux location.
 */

const crypto = require("crypto");
const fs = require("fs");
const puppeteer = require("puppeteer-core");

const DEFAULT_CHROME = [
  process.env.CHROME_PATH,
  "/opt/google/chrome/chrome",
  "/usr/bin/google-chrome",
  "/usr/bin/chromium",
  "/usr/bin/chromium-browser",
].filter(Boolean);

class TestFailure extends Error {}

// ---------------------------------------------------------------------------
// Assertions
// ---------------------------------------------------------------------------

function assert(condition, message) {
  if (!condition) throw new TestFailure(message || "Assertion failed");
}

function assertEqual(actual, expected, message) {
  if (actual !== expected) {
    throw new TestFailure(
      `${message || "assertEqual"}: expected ${JSON.stringify(
        expected
      )}, got ${JSON.stringify(actual)}`
    );
  }
}

function assertIncludes(haystack, needle, message) {
  if (String(haystack).indexOf(needle) === -1) {
    throw new TestFailure(
      `${message || "assertIncludes"}: expected ${JSON.stringify(
        haystack
      )} to include ${JSON.stringify(needle)}`
    );
  }
}

function assertMatch(value, regex, message) {
  if (!regex.test(String(value))) {
    throw new TestFailure(
      `${message || "assertMatch"}: ${JSON.stringify(
        value
      )} does not match ${regex}`
    );
  }
}

// ---------------------------------------------------------------------------
// Browser
// ---------------------------------------------------------------------------

function resolveChrome() {
  const found = DEFAULT_CHROME.find((candidate) => fs.existsSync(candidate));
  if (!found) {
    throw new TestFailure(
      "Chrome/Chromium not found. Set CHROME_PATH to the browser binary."
    );
  }
  return found;
}

function launchBrowser() {
  return puppeteer.launch({
    executablePath: resolveChrome(),
    headless: process.env.HEADLESS === "0" ? false : true,
    args: [
      "--no-sandbox",
      "--disable-dev-shm-usage",
      "--disable-gpu",
      "--disable-extensions",
    ],
    defaultViewport: { width: 390, height: 844 },
  });
}

// ---------------------------------------------------------------------------
// Waiting
// ---------------------------------------------------------------------------

// The app shows the loading bar while fetching, then fills #flight-count with
// "N flights" (or "0 flights"). Waiting on that is a reliable "data is ready"
// signal that does not depend on external map tiles.
async function waitForAppReady(page, timeout = 30000) {
  await page.waitForFunction(
    () => {
      const count = document.getElementById("flight-count");
      const loading = document.getElementById("loading-bar");
      return (
        count &&
        /\d+\s+flights/.test(count.textContent.trim()) &&
        (!loading || loading.hidden)
      );
    },
    { timeout }
  );
}

async function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

// ---------------------------------------------------------------------------
// Test account / session
// ---------------------------------------------------------------------------

function md5(text) {
  return crypto.createHash("md5").update(text).digest("hex");
}

// Create the disposable test user through the public signup endpoint. Returns
// "created" or "exists". The suite only needs the account to exist; the
// browser logs in through the UI afterwards.
async function ensureTestUser(appUrl, username, password) {
  const body = new URLSearchParams({
    type: "NEW",
    name: username,
    pw: md5(password + username.toLowerCase()),
    email: "mobiletest@openflights.example",
    privacy: "O",
    editor: "B",
    units: "M",
    locale: "en_US",
  });
  const res = await fetch(appUrl + "php/settings.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: body.toString(),
  });
  const text = await res.text();
  if (text.startsWith("1;")) return "created";
  if (text.startsWith("0;") && /already taken/i.test(text)) return "exists";
  throw new TestFailure("Could not create test user: " + text);
}

async function login(page, username, password) {
  await click(page, "#user-chip");
  await page.waitForSelector("#user-sheet:not([hidden])", { timeout: 10000 });
  await setInputValue(page, "#login-username", "");
  await setInputValue(page, "#login-password", "");
  await typeInto(page, "#login-username", username);
  await typeInto(page, "#login-password", password);
  await click(page, "#login-form button[type=submit]");
  await page.waitForFunction(
    (name) => {
      const chip = document.getElementById("user-chip");
      return (
        chip &&
        chip.textContent.trim() === name &&
        chip.classList.contains("chip--self")
      );
    },
    { timeout: 20000 },
    username
  );
}

async function logout(page) {
  await click(page, "#user-chip");
  await page.waitForSelector("#user-sheet:not([hidden])", { timeout: 10000 });
  await click(page, "#logout-btn");
  await page.waitForFunction(
    () => {
      const chip = document.getElementById("user-chip");
      return chip && chip.textContent.trim() === "Login";
    },
    { timeout: 20000 }
  );
  await waitForAppReady(page);
}

// Delete every flight belonging to the signed-in test account (settings.php
// type=RESET). Safe only for the disposable account created by the suite.
async function resetFlights(page) {
  return page.evaluate(async () => {
    const res = await fetch("/php/settings.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "type=RESET",
    });
    return res.text();
  });
}

// ---------------------------------------------------------------------------
// Editor helpers
// ---------------------------------------------------------------------------

async function setInputValue(page, selector, value) {
  await page.$eval(
    selector,
    (el, next) => {
      el.value = next;
      el.dispatchEvent(new Event("input", { bubbles: true }));
    },
    value
  );
}

// Click through the DOM instead of synthetic mouse coordinates. The editor sheet
// is scrollable and Chrome drops synthetic mouse events whose coordinates fall
// outside the viewport, so coordinate clicks are unreliable for off-screen
// buttons. Element.click() still runs the app's click handlers.
async function click(page, selector) {
  const found = await page.evaluate((sel) => {
    const element = document.querySelector(sel);
    if (!element) return false;
    element.click();
    return true;
  }, selector);
  if (!found) throw new TestFailure("Element not found: " + selector);
}

// Type text into a field reliably. Headless Chrome in this environment drops
// synthetic keystrokes after modal sessions, so set the value and dispatch the
// input event the app listens for (equivalent to a user typing the whole value).
async function typeInto(page, selector, text) {
  await page.$eval(
    selector,
    (el, value) => {
      el.focus();
      el.value = value;
      el.dispatchEvent(new Event("input", { bubbles: true }));
    },
    text
  );
}

async function showFlightsView(page) {
  await click(page, '.tabbar__tab[data-view="flights"]');
  await page.waitForSelector("#view-flights.is-active", { timeout: 10000 });
}

async function pickSuggestion(page, listSelector, code) {
  const needle = "(" + code + ")";
  await page.waitForFunction(
    (selector, text) => {
      const list = document.querySelector(selector);
      if (!list || list.hidden) return false;
      return Array.from(list.querySelectorAll(".autocomplete__item")).some(
        (item) => item.textContent.includes(text)
      );
    },
    { timeout: 20000 },
    listSelector,
    needle
  );
  const clicked = await page.evaluate(
    (selector, text) => {
      const list = document.querySelector(selector);
      const item = Array.from(
        list.querySelectorAll(".autocomplete__item")
      ).find((candidate) => candidate.textContent.includes(text));
      if (!item) return false;
      item.click();
      return true;
    },
    listSelector,
    needle
  );
  if (!clicked) throw new TestFailure("Suggestion not found: " + code);
}

async function selectAirport(page, inputSelector, listSelector, code) {
  await click(page, inputSelector);
  await setInputValue(page, inputSelector, "");
  await typeInto(page, inputSelector, code);
  await pickSuggestion(page, listSelector, code);
}

async function openAddEditor(page) {
  await showFlightsView(page);
  await click(page, "#add-flight");
  await page.waitForSelector("#flight-editor:not([hidden])", {
    timeout: 10000,
  });
  await page.waitForFunction(
    () =>
      document.getElementById("flight-editor-title").textContent ===
      "Add flight",
    { timeout: 10000 }
  );
  // Let the sheet's open animation settle before interacting with it.
  await sleep(150);
}

async function closeEditor(page) {
  await click(page, "#editor-cancel");
  await page.waitForFunction(
    () => document.getElementById("flight-editor").hidden,
    { timeout: 10000 }
  );
}

async function waitForCardWith(page, text, timeout = 20000) {
  await page.waitForFunction(
    (needle) =>
      Array.from(document.querySelectorAll("#flight-list .flight-card")).some(
        (card) => card.textContent.includes(needle)
      ),
    { timeout },
    text
  );
}

module.exports = {
  TestFailure,
  assert,
  assertEqual,
  assertIncludes,
  assertMatch,
  launchBrowser,
  waitForAppReady,
  sleep,
  md5,
  ensureTestUser,
  login,
  logout,
  resetFlights,
  setInputValue,
  click,
  typeInto,
  showFlightsView,
  pickSuggestion,
  selectAirport,
  openAddEditor,
  closeEditor,
  waitForCardWith,
};
