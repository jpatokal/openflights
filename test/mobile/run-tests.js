"use strict";

/**
 * OpenFlights mobile headless test runner.
 *
 * Usage:
 *   node run-tests.js               # run everything
 *   OPENFLIGHTS_URL=http://host:8007/ node run-tests.js
 *
 * See README.md for the full list of environment variables.
 */

const fs = require("fs");
const path = require("path");

const h = require("./lib/harness");
const demoTests = require("./tests/demo");
const mapTests = require("./tests/map");
const layoutTests = require("./tests/layout");
const authTests = require("./tests/auth");

const APP_URL = (
  process.env.OPENFLIGHTS_URL || "http://localhost:8007/"
).replace(/\/?$/, "/");
const MOBILE_URL = process.env.MOBILE_URL || APP_URL + "mobile/";
const TEST_URL = withMapDebug(MOBILE_URL);
const USER = process.env.MOBILE_TEST_USER || "mobiletest";
const PASS = process.env.MOBILE_TEST_PASS || "mobiletest";
const AUTH = process.env.MOBILE_AUTH !== "0";
const RESET = canReset();
const SCREENSHOT_DIR = path.join(__dirname, "screenshots");

// The map tests inspect OpenLayers through a handle the app only exposes in
// debug mode. Adding the flag to every run keeps the suite on one page load.
function withMapDebug(url) {
  if (url.indexOf("mapdebug=1") !== -1) return url;
  return url + (url.indexOf("?") === -1 ? "?" : "&") + "mapdebug=1";
}

function canReset() {
  if (process.env.MOBILE_TEST_RESET === "1") return true;
  if (process.env.MOBILE_TEST_RESET === "0") return false;
  // Only auto-reset the disposable account the suite creates itself.
  return !process.env.MOBILE_TEST_USER;
}

function color(code, text) {
  if (!process.stdout.isTTY) return text;
  return `\u001b[${code}m${text}\u001b[0m`;
}

async function runTest(test, ctx, summary) {
  const started = Date.now();
  try {
    await test.run(ctx);
    const ms = Date.now() - started;
    console.log(
      `  ${color(32, "PASS")} ${test.name} ${color(90, `(${ms}ms)`)}`
    );
    summary.pass += 1;
  } catch (err) {
    const ms = Date.now() - started;
    console.log(
      `  ${color(31, "FAIL")} ${test.name} ${color(90, `(${ms}ms)`)}`
    );
    console.log(
      color(31, "        " + (err && err.message ? err.message : String(err)))
    );
    if (ctx.page) {
      try {
        fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
        const file = path.join(SCREENSHOT_DIR, `failure-${Date.now()}.png`);
        await ctx.page.screenshot({ path: file, fullPage: true });
        console.log(color(90, "        screenshot: " + file));
      } catch (e) {
        // Screenshots are best-effort.
      }
    }
    summary.fail += 1;
  }
}

function skipTest(test, reason, summary) {
  console.log(
    color(33, "  SKIP") + " " + test.name + color(90, ` (${reason})`)
  );
  summary.skip += 1;
}

async function main() {
  console.log("OpenFlights mobile headless tests");
  console.log("  mobile url: " + MOBILE_URL);
  console.log("  test user:  " + (AUTH ? USER : "(auth disabled)"));

  let status = 0;
  try {
    const res = await fetch(TEST_URL);
    status = res.status;
  } catch (err) {
    status = 0;
  }
  if (status !== 200) {
    console.error(
      color(31, `\nCannot reach ${MOBILE_URL} (HTTP ${status}).`) +
        "\nStart the OpenFlights stack first (e.g. `docker compose up -d`)," +
        "\nthen set OPENFLIGHTS_URL if it is not on http://localhost:8007/."
    );
    process.exit(1);
  }

  const summary = { pass: 0, fail: 0, skip: 0 };
  let browser;

  try {
    browser = await h.launchBrowser();
    const page = await browser.newPage();

    const pageErrors = [];
    page.on("pageerror", (err) => pageErrors.push(err.message));

    await page.goto(TEST_URL, {
      waitUntil: "domcontentloaded",
      timeout: 30000,
    });
    try {
      await h.waitForAppReady(page);
    } catch (err) {
      const overview = await page
        .$eval("#view-overview", (el) => el.textContent.trim())
        .catch(() => "");
      throw new Error(
        "The app did not finish loading. Overview text: " +
          overview.slice(0, 200)
      );
    }

    const ctx = { page, pageErrors, state: {}, canReset: RESET };

    console.log("\nDemo mode");
    for (const test of demoTests) {
      await runTest(test, ctx, summary);
    }

    console.log("\nMap");
    for (const test of mapTests) {
      await runTest(test, ctx, summary);
    }

    console.log("\nLayout");
    for (const test of layoutTests) {
      await runTest(test, ctx, summary);
    }

    if (pageErrors.length) {
      summary.fail += 1;
      console.log(color(31, "\nUncaught page errors:"));
      pageErrors.forEach((message) => console.log(color(31, "  " + message)));
    }

    if (!AUTH) {
      console.log("\nSigned-in mode (disabled with MOBILE_AUTH=0)");
      authTests.forEach((test) => skipTest(test, "auth disabled", summary));
    } else {
      console.log("\nSigned-in mode");
      let loggedIn = false;
      try {
        const outcome = await h.ensureTestUser(APP_URL, USER, PASS);
        console.log(color(90, `  test account "${USER}": ${outcome}`));
        await h.login(page, USER, PASS);
        loggedIn = true;
      } catch (err) {
        console.log(color(33, `  login unavailable: ${err.message}`));
      }

      if (!loggedIn) {
        authTests.forEach((test) =>
          skipTest(test, "login unavailable", summary)
        );
      } else {
        // No page reload here: the suite uses a unique marker per run, and the
        // cleanup test resets the disposable account's flights. Reloading the
        // page mid-session can leave Chrome's input dispatch unreliable.
        for (const test of authTests) {
          if (test.skip && test.skip(ctx)) {
            skipTest(test, "not applicable", summary);
            continue;
          }
          await runTest(test, ctx, summary);
        }
      }
    }

    const total = summary.pass + summary.fail + summary.skip;
    console.log(
      "\nSummary: " +
        `${color(32, summary.pass + " passed")}, ` +
        `${summary.fail ? color(31, summary.fail + " failed") : "0 failed"}, ` +
        `${summary.skip} skipped (${total} total)`
    );
    process.exitCode = summary.fail ? 1 : 0;
  } finally {
    if (browser) await browser.close();
  }
}

main().catch((err) => {
  console.error(color(31, "Fatal: " + (err && err.stack ? err.stack : err)));
  process.exit(1);
});
