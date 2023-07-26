#!/bin/bash
STATUS=$1
USER=$2
EMAIL=$3
HOST=104.197.15.255
PW=`cat ../sql/db.pw`

if [ "$STATUS" != "S" -a "$STATUS" != "G" -a "$STATUS" != "P" ]; then
  echo Status $STATUS must be one of S, G or P
  exit
fi

VERIFY=`echo "SELECT name FROM users WHERE name = '$USER';" | mysql -h $HOST -u openflights --password=$PW --skip-column-names flightdb2`
if [ -z "$VERIFY" ]; then
  echo No user called $USER found
  exit
fi

echo "SET sql_safe_updates=0; UPDATE users SET elite = '$STATUS', validity = DATE_ADD(NOW(), INTERVAL 1 YEAR) WHERE name = '$USER';" | mysql -h $HOST -u openflights --password=$PW --skip-column-names flightdb2

if [ -z "$EMAIL" ]; then
  EMAIL=`echo "SELECT email FROM users WHERE name = '$USER';" | mysql -h $HOST -u openflights --password=$PW --skip-column-names flightdb2`
  if [ -z "$EMAIL" ]; then
    echo User $USER set to $STATUS, but no email found for user $USER
    exit
  fi
fi

echo User $USER set to $STATUS, sending mail to $EMAIL
cat $STATUS-message.txt | sed "s/%EMAIL%/$EMAIL/" | sendmail $EMAIL

