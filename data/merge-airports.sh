#!/bin/sh
#
# Merge airports by APID
#
bad=$1
good=$2
mysql -u openflights -e "select * from airports where apid=$bad or apid=$good" flightdb2
read -p "You're about to delete $bad and replace it with $good.  Proceed (y/N)? " input
if [ $input = "y" ]; then
  mysql -u openflights -e "set @bad = \"$bad\"; set @good = \"$good\"; source sql/merge-airports.sql;" flightdb2	
  exit
else
	echo "Aborted."
fi
