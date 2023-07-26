set sql_safe_updates=0;

TRUNCATE TABLE routes;

\! echo Filtering out duplicates...

\! uniq -w 10 <data/routes.dat >routes2.dat

\! echo Importing...

LOAD DATA LOCAL INFILE 'routes2.dat'
INTO TABLE routes
FIELDS TERMINATED BY '\t' OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 2 LINES
(airline, src_ap, dst_ap, codeshare, stops, equipment);

UPDATE routes SET codeshare='Y' WHERE codeshare='*';

\! echo Adding src airport IDs...

UPDATE routes AS r,airports as a SET r.src_apid=a.apid WHERE a.iata=r.src_ap;

\! echo Adding dst airport IDs...

UPDATE routes AS r,airports as a SET r.dst_apid=a.apid WHERE a.iata=r.dst_ap;

\! echo Adding IATA airline IDs...

UPDATE IGNORE routes AS r,airlines as l SET r.alid=l.alid WHERE l.iata=r.airline;

\! echo Adding IATA airline IDs, round 2...

UPDATE IGNORE routes AS r,airlines as l SET r.alid=l.alid WHERE l.iata=r.airline AND l.active='Y';

\! echo Adding ICAO airline IDs...

UPDATE IGNORE routes AS r,airlines as l SET r.alid=l.alid WHERE r.alid IS NULL AND l.icao=r.airline;

\! rm routes2.dat
\! echo Done.

set sql_safe_updates=1;
