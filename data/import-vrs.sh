#!/bin/sh
RAW=/tmp/FlightNumbers.csv
PROCESSED=/tmp/vrs-routes.csv
#curl -o $RAW http://www.virtualradarserver.co.uk/Files/FlightNumbers.csv
python import-vrs.py <$RAW | sort | uniq >$PROCESSED
mysql -u openflights flightdb2 <../sql/import-vrs.sql

