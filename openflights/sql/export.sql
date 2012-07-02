-- Execute from "data" directory
-- Must be run as a user with write access

\! echo Airports...

SELECT apid,name,city,country,iata,icao,y,x,elevation,timezone,dst INTO OUTFILE '/tmp/airports.dat'
FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\n'
FROM airports;

\! echo Airlines...

SELECT alid,name,alias,iata,icao,callsign,country,active INTO OUTFILE '/tmp/airlines.dat'
FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\n'
FROM airlines
WHERE mode='F' AND ((iata!="" AND iata IS NOT NULL) OR (icao!="" AND icao IS NOT NULL));

\! echo Routes...

SELECT airline,alid,src_ap,src_apid,dst_ap,dst_apid,codeshare,stops,equipment INTO OUTFILE '/tmp/routes.dat'
FIELDS TERMINATED BY ','
LINES TERMINATED BY '\n'
FROM routes;

\! echo Countries

SELECT name,code,oa_code,dst INTO OUTFILE '/tmp/countries.dat'
FIELDS TERMINATED BY ','
LINES TERMINATED BY '\n'
FROM countries;

\! echo Locales 

SELECT * INTO OUTFILE '/tmp/locales.dat'
FIELDS TERMINATED BY ','
LINES TERMINATED BY '\n'
FROM locales;

\! echo Waiting for dump to complete...
\! sleep 5
\! ls -l /tmp/*.dat
\! cp /tmp/*.dat .

