This test suite validates client-side (browser) behavior.

Requirements:

- Selenium Server (2.0+ required for Firefox 4)
  Install into directory '/openflights/test/selenium'

- A functional OpenFlights server and its test suite
  Set up /openflights/test/server first (see its [README](../server/README.md))

Testing:

Run [`./run-suite.sh`](run-suite.sh) and hope for the best! Test bootstrap logs into log/bootstrap.log, Selenium logs go into log/selenium.log.
