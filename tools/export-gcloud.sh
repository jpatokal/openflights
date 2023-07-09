#!/bin/bash
# Export from database to CSV
set -e

QUERY="SELECT apid,name,city,country,iata,icao,y,x,elevation,timezone,dst,tz_id,type,source FROM airports" 
gcloud sql export csv openflights-db gs://openflights-backup/airports.dat --database=flightdb2 \
  --query="$QUERY WHERE source='OurAirports' AND type='airport'"
gcloud sql export csv openflights-db gs://openflights-backup/airports-extended.dat --database=flightdb2 \
  --query="$QUERY"

gcloud sql export csv openflights-db gs://openflights-backup/airlines.dat --database=flightdb2 \
  --query="SELECT alid,name,alias,iata,icao,callsign,country,active FROM airlines WHERE mode='F' AND ((iata!='' AND iata IS NOT NULL) OR (icao!='' AND icao IS NOT NULL))"

gcloud sql export csv openflights-db gs://openflights-backup/planes.dat --database=flightdb2 \
  --query="SELECT name,iata,icao FROM planes WHERE iata IS NOT NULL OR icao IS NOT NULL ORDER BY name"

gcloud sql export csv openflights-db gs://openflights-backup/countries.dat --database=flightdb2 \
  --query="SELECT name,iso_code,dafif_code FROM countries"

gcloud sql export csv openflights-db gs://openflights-backup/locales.dat --database=flightdb2 \
  --query="SELECT * FROM locales"

for FILE in airports.dat airports-extended.dat airlines.dat planes.dat countries.dat locales.dat
do
  gsutil cp gs://openflights-backup/$FILE $FILE
  sed -i "" s/\"N,/\\\\N,/g $FILE
  sed -i "" s/\"N$/\\\\N/ $FILE
done
