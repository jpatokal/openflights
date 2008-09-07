CREATE DATABASE flightdb;

CREATE USER openflights;
GRANT ALL PRIVILEGES ON flightdb.* TO openflights;

CONNECT flightdb;

CREATE TABLE users
(
name TEXT UNIQUE KEY,
password TEXT,
email TEXT,
public TEXT,
uid INT AUTO_INCREMENT PRIMARY KEY
);

CREATE TABLE airports_gad
(
name TEXT NOT NULL,
city TEXT,
country TEXT,
iata VARCHAR(3),
icao VARCHAR(4),
x DOUBLE NOT NULL,
y DOUBLE NOT NULL,
elevation INT,
apid INT AUTO_INCREMENT PRIMARY KEY
);

CREATE TABLE planes
(
name TEXT,
abbr TEXT,
speed DOUBLE,
plid INT AUTO_INCREMENT PRIMARY KEY
);

CREATE TABLE airlines
(
name TEXT,
iata VARCHAR(2),
icao VARCHAR(3),
callsign TEXT,
country TEXT,
alid INT AUTO_INCREMENT PRIMARY KEY
);

CREATE TABLE trips
(
name TEXT,
url TEXT,
uid INT,
public TEXT,
trid INT AUTO_INCREMENT PRIMARY KEY,
FOREIGN KEY (uid) REFERENCES users (uid)
);

CREATE TABLE flights
(
uid INT,
src_apid INT,
dst_apid INT,
src_time DATETIME,
duration TIME,
distance INT,
code TEXT,
seat TEXT,
seat_type TEXT,
class TEXT,
reason TEXT,
registration TEXT,
note TEXT,
upd_time DATETIME,
plid INT,
alid INT,
trid INT,
fid INT AUTO_INCREMENT PRIMARY KEY,
FOREIGN KEY (uid) REFERENCES users (uid),
FOREIGN KEY (src_apid) REFERENCES airports (apid),
FOREIGN KEY (dst_apid) REFERENCES airports (apid),
FOREIGN KEY (plid) REFERENCES planes (plid),
FOREIGN KEY (alid) REFERENCES airlines (alid),
FOREIGN KEY (trid) REFERENCES trips (trid)
);

LOAD DATA LOCAL INFILE 'airlines.dat'
INTO TABLE airlines
FIELDS TERMINATED BY ','
LINES TERMINATED BY '\n'
(iata, icao, name, callsign, country);

LOAD DATA LOCAL INFILE 'airports.dat'
INTO TABLE airports
FIELDS TERMINATED BY ','
LINES TERMINATED BY '\n'
(icao, iata, name, city, country, x, y, elevation);
