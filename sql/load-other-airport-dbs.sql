\! echo DAFIF...

LOAD DATA LOCAL INFILE 'data/airports-dafif.dat'
INTO TABLE airports_dafif
FIELDS TERMINATED BY ','
LINES TERMINATED BY '\n'
(code, name, icao, iata, x, y, elevation);

\! echo OpenAirports...

LOAD DATA LOCAL INFILE 'airports.csv'
INTO TABLE airports_oa
FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\n'
(ident, type, name, y, x, elevation, continent, country, region, city, service, gps, i
ata, icao, keywords);

UPDATE airports_oa SET icao=ident WHERE LENGTH(ident)=4;
