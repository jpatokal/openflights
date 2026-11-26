# Mobile headless test suite

End-to-end tests for the OpenFlights mobile app (`/mobile/`), driven by a real
headless Chrome/Chromium through [`puppeteer-core`](https://pptr.dev/).

The suite talks to a running OpenFlights server over HTTP and exercises the app
the way a user would: tabs, filters, the map, and (when signed in) the add/edit
flight editor. It does not require Selenium, PHPUnit, or a browser driver binary.

## What is covered

**Demo (logged out) mode** — no account needed:

- App shell: title, logo, tabs, login chip, hidden add-flight button.
- Demo data loads (flight count and cards).
- Tab navigation toggles the active view and `aria-selected`.
- Overview: vertically stacked hero stats, "Distance in perspective"
  (round-the-world/moon multipliers and the Mars percentage), extreme
  coordinates (e.g. `78.2°N`), and four pie charts whose legends show the
  full label with mileage on a second line.
- Flights: list rendering, search filtering, and date sorting.
- Flight cards are read-only when logged out, and private notes are hidden
  on public/demo cards.
- Filters: toggle/open state, applying a year then a carrier (active-count
  badge), cross-filtering, mode/class filters, "Clear filters", and collapsing
  the pane whenever the active tab changes.
- Map (OpenLayers 10): viewport, zoom control, layer switcher, scale line and
  collapsed attribution button.
- Map rendering: flight lines and clustered airport pins are created, clusters
  collapse overlapping airports, airport features carry their style attributes,
  and the viewport stays inside the world.
- Map interaction: clicking an airport opens its popup, clicking a cluster opens
  the airport chooser with one row per member, and closing either clears the
  selection.
- Map layer switcher: toggling between the political and satellite base maps
  updates layer visibility.

The map assertions read the live OpenLayers objects through a read-only
`window.__openflightsMap` handle. The runner enables it by loading the app with
`?mapdebug=1`; it is not exposed in normal use.

**Signed-in mode** — runs when a test account can be created/logged into:

- Add-flight button visibility (map + flights, hidden on overview).
- Editor opens; save is blocked until both airports are chosen.
- Airport autocomplete fills the route, distance (mi) and duration (HH:MM).
- Typing a flight number with an airline code (`JQ123`) fills in the airline.
- An unknown airline is flagged red and blocks saving.
- Adding a flight creates a card in the list, including its note for the
  owner.
- Editing a flight prefills the form and persists changes.
- The account sheet shows only "Log out"; logging out returns to demo mode.

If the server or database is unavailable, or login fails, the signed-in tests
are reported as **skipped** and the demo suite still runs.

## Requirements

- Node.js 18+ (the runner itself has no other runtime dependencies).
- Google Chrome or Chromium installed locally.
- A running OpenFlights server with its database (e.g. the repository's
  `docker compose up -d`, which serves the app on `http://localhost:8007/`).

## Quick start

```bash
cd test/mobile
./run.sh
```

`run.sh` installs `puppeteer-core` on first run (into `test/mobile/node_modules`)
and then executes the suite. You can also install and run manually:

```bash
cd test/mobile
npm install
node run-tests.js
```

The runner exits non-zero if any test fails. A screenshot of the page at the
moment of each failure is written to `test/mobile/screenshots/`.

## Configuration

All settings are environment variables; nothing is hard-coded to a specific
machine.

| Variable            | Default                     | Purpose                                                          |
| ------------------- | --------------------------- | ---------------------------------------------------------------- |
| `OPENFLIGHTS_URL`   | `http://localhost:8007/`    | App root (the directory that contains `php/` and `mobile/`).     |
| `MOBILE_URL`        | `${OPENFLIGHTS_URL}mobile/` | Explicit URL of the mobile app, if it lives elsewhere.           |
| `CHROME_PATH`       | auto-detected               | Path to the Chrome/Chromium binary.                              |
| `MOBILE_AUTH`       | `1`                         | Set to `0` to skip all signed-in tests.                          |
| `MOBILE_TEST_USER`  | `mobiletest`                | Test account username.                                           |
| `MOBILE_TEST_PASS`  | `mobiletest`                | Test account password.                                           |
| `MOBILE_TEST_RESET` | see below                   | `1`/`0` to force-enable/disable resetting the account's flights. |
| `HEADLESS`          | `1`                         | Set to `0` to watch the browser run (needs a display).           |

Examples:

```bash
# Point at a container on another host and watch the run
OPENFLIGHTS_URL=http://192.168.1.20:8007/ HEADLESS=0 ./run.sh

# Demo-mode tests only
MOBILE_AUTH=0 ./run.sh

# Use your own existing account (resetting is off unless you opt in)
MOBILE_TEST_USER=alice MOBILE_TEST_PASS=secret ./run.sh
```

## How the test account works

By default the suite uses a disposable account named `mobiletest`. On startup it
tries to create that account through the public signup endpoint
(`php/settings.php`, `type=NEW`); if it already exists, it simply logs in. The
account is set to a public profile with miles as its unit.

To keep runs independent, the `cleanup` test **deletes all of that account's
flights** at the end of the signed-in run (`settings.php`, `type=RESET`). Each
run also tags the flight it creates with a unique marker, so flights left behind
by an interrupted run do not confuse later runs — the add/edit tests find the
card by marker rather than position.

For safety, this automatic reset only happens for the default `mobiletest`
account. If you override `MOBILE_TEST_USER`, reset is disabled unless you
explicitly set `MOBILE_TEST_RESET=1` — the README cannot warn you strongly
enough that this deletes every flight in that account.

## Running with Docker

From the repository root:

```bash
docker compose up -d
```

Wait until `http://localhost:8007/mobile/` responds, then run the suite as
above. No extra container is needed; the tests connect to the running app and
use the same database.

## Troubleshooting

- **`Cannot reach …`** — the server is not up yet, or `OPENFLIGHTS_URL` is
  wrong. In Docker, check `docker compose ps` and the port mapping.
- **`Chrome/Chromium not found`** — install Chrome/Chromium or set
  `CHROME_PATH` (for example `/usr/bin/chromium`).
- **Signed-in tests are skipped** — login failed. Confirm the database is
  reachable, or set `MOBILE_AUTH=0` to intentionally skip them.
- **Map tile requests fail** — the map base layers come from external tile
  providers. The app still initialises and the map tests only check the
  OpenLayers controls, so this does not fail the suite.
- **Flaky timings on slow machines** — waits are based on app state (flight
  count, DOM conditions) rather than fixed sleeps, but a very slow backend can
  exceed the built-in timeouts. Retry, or run against a local instance.

## Layout

```
test/mobile/
  README.md          this file
  package.json       puppeteer-core dev dependency
  run.sh             install deps + run
  run-tests.js       orchestrator (server check, login, reporting)
  lib/harness.js     browser launch, waits, assertions, account helpers
  tests/demo.js      logged-out test cases
  tests/auth.js      signed-in test cases
  screenshots/       failure screenshots (git-ignored)
```
