#!/bin/sh
HOST=`cat sql/db.host`
PW=`cat sql/db.pw`
MYSQL="mysql -h $HOST -u openflights --password=$PW"
