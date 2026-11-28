#!/bin/bash
#
# Nightly OpenFlights maintenance:
#   1. elite/expire.sh     - expire accounts and send notices
#   2. sql/top10.sql       - regenerate data/top10.dat for the About page
#   3. sql/update-demo.sql - refresh the demo user's flights
#
# Run from cron; see tools/crontab.example. Any failing step stops the run.
# Cronitor telemetry is optional: put the monitor's telemetry URL
# (https://cronitor.link/p/...) in sql/cronitor.key.
#
set -euo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)

CRONITOR_URL=""
if [ -r "$ROOT/sql/cronitor.key" ]; then
  CRONITOR_URL=$(cat "$ROOT/sql/cronitor.key")
  CRONITOR_URL=${CRONITOR_URL%/}
fi

ping() {
  if [ -n "$CRONITOR_URL" ]; then
    local SEP='?'
    case "$CRONITOR_URL" in
      *\?*) SEP='&' ;;
    esac
    curl -fsS -m 10 "${CRONITOR_URL}${SEP}state=$1" >/dev/null ||
      echo "Cronitor ping failed: $1" >&2
  fi
}

TMP=""
trap 'RC=$?; if [ -n "$TMP" ]; then rm -f "$TMP"; fi; if [ "$RC" -ne 0 ]; then ping fail; fi; exit "$RC"' EXIT
ping run

for FILE in sql/db.host sql/db.pw; do
  if [ ! -r "$ROOT/$FILE" ]; then
    echo "Missing $FILE; copy $FILE.example and fill it in (see INSTALL)." >&2
    exit 1
  fi
done

HOST=$(cat "$ROOT/sql/db.host")
PW=$(cat "$ROOT/sql/db.pw")
MYSQL=(mysql -h "$HOST" -u openflights --password="$PW" flightdb2)

echo "=== Expiring elite accounts: $(date) ==="
/bin/bash "$ROOT/elite/expire.sh"

echo "=== Regenerating data/top10.dat: $(date) ==="
TMP=$(mktemp "$ROOT/data/top10.dat.XXXXXX")
# top10.sql's AS aliases are the column headers, so don't add --skip-column-names.
"${MYSQL[@]}" < "$ROOT/sql/top10.sql" > "$TMP"
chmod 644 "$TMP"
mv "$TMP" "$ROOT/data/top10.dat"
TMP=""

echo "=== Refreshing demo flights: $(date) ==="
"${MYSQL[@]}" < "$ROOT/sql/update-demo.sql"

trap - EXIT
ping complete

echo "=== Done: $(date) ==="
