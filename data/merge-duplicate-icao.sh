#!/bin/sh
#
# Find airports with duplicate ICAO codes
# Merge them if one has IATA code and the other does not
#
mysql -u root -p -e "select icao into outfile '/tmp/duplicate-airports' from airports where length(icao)=4 group by icao having count(*) > 1;" flightdb2
wc -l /tmp/duplicate-airports
for icao in `cat /tmp/duplicate-airports`; do
  echo $icao
  mysql -u openflights -e "set @icao = \"$icao\"; source ../sql/merge-duplicate-icao.sql;" flightdb2
  read -p "Press ENTER to continue or 'q' to abort: " input
  if [ $input = "q" ]; then
    sudo rm /tmp/duplicate-airports
    exit
  fi
done
sudo rm /tmp/duplicate-airports
