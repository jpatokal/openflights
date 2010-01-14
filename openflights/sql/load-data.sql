\! echo Importing airlines...

LOAD DATA LOCAL INFILE 'data/airlines.dat'
INTO TABLE airlines
FIELDS TERMINATED BY ','
LINES TERMINATED BY '\n'
(alid, name, alias, iata, icao, callsign, country, active);

\! echo Importing airports...

LOAD DATA LOCAL INFILE 'data/airports.dat'
INTO TABLE airports
FIELDS TERMINATED BY ','
LINES TERMINATED BY '\n'
(apid, name, city, country, iata, icao, y, x, elevation, timezone, dst);

\! echo Importing routes...

LOAD DATA LOCAL INFILE 'data/routes.dat'
INTO TABLE routes
FIELDS TERMINATED BY ','
LINES TERMINATED BY '\n'
(airline, alid, src_ap, src_apid, dst_ap, dst_apid, codeshare, stops, equipment);

\! echo Importing countries...

LOAD DATA LOCAL INFILE 'data/countries.dat'
INTO TABLE countries
FIELDS TERMINATED BY ','
LINES TERMINATED BY '\n'
(name, code, oa_code, dst);

\! echo Done.
