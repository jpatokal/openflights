CREATE TABLE routes_arm
(
alid VARCHAR(2),
src VARCHAR(3),
dst VARCHAR(3),
codeshare TEXT,
stops TEXT,
equipment TEXT
);

LOAD DATA LOCAL INFILE 'arm/routes.dat'
INTO TABLE routes_arm
FIELDS TERMINATED BY '\t' OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 2 LINES
(alid, src, dst, codeshare, stops, equipment);

CREATE TABLE routes
(
alid INT, 
src_apid INT,
dst_apid INT,
codeshare TEXT,
stops TEXT,
equipment TEXT,
rid INT AUTO_INCREMENT PRIMARY KEY,
FOREIGN KEY (src_apid) REFERENCES airports (apid),
FOREIGN KEY (dst_apid) REFERENCES airports (apid),
FOREIGN KEY (alid) REFERENCES airlines (alid)
);

INSERT INTO routes(alid,src_apid,dst_apid,codeshare,stops,equipment) SELECT a.alid,s.apid,d.apid,r.codeshare,r.stops,r.equipment from airlines as a,airports as s,airports as d,routes_arm as r where a.iata=r.alid and s.iata=r.src and d.iata=r.dst;
