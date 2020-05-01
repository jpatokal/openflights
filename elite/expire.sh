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

EXPIRED=$(echo "select email from users where validity is not null and validity < NOW() and elite != 'X' and elite != '' and email != '' order by validity desc;" | $MYSQL)
for EMAIL in $EXPIRED; do
  echo Expired user $EMAIL
  cat expiry-message.txt | sed "s/%EMAIL%/$EMAIL/" | sendmail $EMAIL
done

echo "set sql_safe_updates=0; update users set validity=NOW(), elite='X' where validity is not null and validity < NOW() and elite != 'X' and elite != '';" | $MYSQL

curl https://cronitor.link/WTBCby/complete -m 10
