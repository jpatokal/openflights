#!/bin/sh
HOST=104.197.15.255
PW=`cat sql/db.pw`
MYSQL="mysql -h $HOST -u openflights --password=$PW"
