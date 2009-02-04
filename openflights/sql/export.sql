-- Must be run as a user with write access
SELECT name,city,country,iata,icao,x,y,elevation,timezone,dst INTO OUTFILE '/tmp/airports.dat'
FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\n'
FROM airports;

SELECT name,alias,iata,icao,callsign,country INTO OUTFILE '/tmp/airlines.dat'
FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\n'
FROM airlines;

