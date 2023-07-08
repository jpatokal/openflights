#!/bin/bash
#
# Merge airports by APID: data/merge-airports.sh BAD GOOD
#
source sql/mysql.sh
bad=$1
good=$2
$MYSQL -e "select * from airports where apid=$bad or apid=$good" flightdb2
read -p "You're about to delete $bad and replace it with $good. Proceed (y/N)? " input
if [ $input = "y" ]; then
  $MYSQL -e "set @bad = \"$bad\"; set @good = \"$good\"; source sql/merge-airports.sql;" flightdb2
  exit
else
  echo "Aborted."
fi
