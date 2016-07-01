set sql_safe_updates=0;

TRUNCATE TABLE routes;

\! echo Importing...

LOAD DATA LOCAL INFILE '/tmp/vrs-routes.csv'
INTO TABLE routes
FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\n'
(airline_icao, flight, src_icao, dst_icao);

\! echo Adding src airport IDs...

UPDATE routes AS r,airports as a SET r.src_apid=a.apid,r.src_iata=a.iata WHERE a.icao=r.src_icao;

\! echo Adding dst airport IDs...

UPDATE routes AS r,airports as a SET r.dst_apid=a.apid,r.dst_iata=a.iata WHERE a.icao=r.dst_icao;

\! echo Adding airline IDs...

UPDATE routes AS r,airlines as l SET r.alid=l.alid,r.airline_iata=l.iata WHERE l.icao=r.airline_icao;

\! echo Done.

set sql_safe_updates=1;
