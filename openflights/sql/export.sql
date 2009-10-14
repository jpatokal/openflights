-- Execute from "data" directory
-- Must be run as a user with write access

\! echo Airports...

SELECT name,city,country,iata,icao,y,x,elevation,timezone,dst INTO OUTFILE '/tmp/airports.dat'
FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\n'
FROM airports;

\! echo Airlines...

SELECT name,alias,iata,icao,callsign,country INTO OUTFILE '/tmp/airlines.dat'
FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\n'
FROM airlines;

\! echo Routes...

SELECT airline,alid,src_ap,src_apid,dst_ap,dst_apid,codeshare,stops,equipment INTO OUTFILE '/tmp/routes.dat'
FIELDS TERMINATED BY ','
LINES TERMINATED BY '\n'
FROM routes

\! ls -l /tmp/*.dat
\! cp /tmp/*.dat .

