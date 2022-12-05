\! echo Importing airlines...

LOAD DATA LOCAL INFILE 'data/airlines.dat'
REPLACE INTO TABLE airlines
FIELDS TERMINATED BY ','
OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\n'
(alid, name, alias, iata, icao, callsign, country, active);

\! echo Importing airports...

LOAD DATA LOCAL INFILE 'data/airports.dat'
REPLACE INTO TABLE airports
FIELDS TERMINATED BY ','
OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\n'
(apid, name, city, country, iata, icao, y, x, elevation, timezone, dst, tz_id);

\! echo Importing routes...

LOAD DATA LOCAL INFILE 'data/routes.dat'
REPLACE INTO TABLE routes
FIELDS TERMINATED BY ','
OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\n'
(airline, alid, src_ap, src_apid, dst_ap, dst_apid, codeshare, stops, equipment);

\! echo Importing countries...

LOAD DATA LOCAL INFILE 'data/countries.dat'
REPLACE INTO TABLE countries
FIELDS TERMINATED BY ','
OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\n'
(name, code, oa_code);

\! echo Importing locales...

LOAD DATA LOCAL INFILE 'locale/locales.dat'
REPLACE INTO TABLE locales
CHARACTER SET utf8
FIELDS TERMINATED BY ','
OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\n'
(locale, name);

\! echo Done.
