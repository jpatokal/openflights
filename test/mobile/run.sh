#!/usr/bin/env bash
#
# Convenience wrapper: install dependencies on first run, then execute the
# headless suite. Any arguments are forwarded to run-tests.js.
set -euo pipefail
cd "$(dirname "$0")"

if [ ! -d node_modules/puppeteer-core ]; then
  echo "Installing test dependencies (puppeteer-core)..."
  npm install --no-audit --no-fund
fi

exec node run-tests.js "$@"
