#!/bin/bash
curl https://cronitor.link/WTBCby/run -m 10
date
set -x
set -e

# Set directory and set up MySQL (must be in $PATH)
cd "$(dirname "${BASH_SOURCE[0]}")"
HOST=104.197.15.255
PW=`cat ../sql/db.pw`
MYSQL="mysql -h $HOST -u openflights --password=$PW --skip-column-names flightdb2"

EXPIRED=$(echo "SELECT email FROM users WHERE validity IS NOT null AND validity < NOW() AND elite != 'X' AND elite != '' AND email != '' ORDER BY validity DESC;" | $MYSQL)
for EMAIL in $EXPIRED; do
  echo Expired user $EMAIL
  cat expiry-message.txt | sed "s/%EMAIL%/$EMAIL/" | sendmail $EMAIL
done

echo "SET sql_safe_updates=0; UPDATE users SET validity = NOW(), elite = 'X' WHERE validity IS NOT null AND validity < NOW() AND elite != 'X' AND elite != '';" | $MYSQL

curl https://cronitor.link/WTBCby/complete -m 10
