CREATE TABLE countries
(
junk TEXT,
code VARCHAR(2) PRIMARY KEY,
name TEXT
);

LOAD DATA LOCAL INFILE 'data/countries.dat'
INTO TABLE countries
FIELDS TERMINATED BY ','
LINES TERMINATED BY '\n'
(junk, code, name);

CREATE TABLE airports_dafif
(
name TEXT NOT NULL,
city TEXT,
code TEXT,
iata VARCHAR(3),
icao VARCHAR(4) PRIMARY KEY,
x DOUBLE NOT NULL,
y DOUBLE NOT NULL,
elevation INT
);

LOAD DATA LOCAL INFILE 'data/airports-dafif.dat'
INTO TABLE airports_dafif
FIELDS TERMINATED BY ','
LINES TERMINATED BY '\n'
(code, name, icao, iata, x, y, elevation);

CREATE TABLE airports_gad
(
name TEXT NOT NULL,
city TEXT,
country TEXT,
iata VARCHAR(3),
icao VARCHAR(4) PRIMARY KEY,
x DOUBLE NOT NULL,
y DOUBLE NOT NULL,
elevation INT
);

LOAD DATA LOCAL INFILE 'data/airports-gad.dat'
INTO TABLE airports_gad
FIELDS TERMINATED BY ','
LINES TERMINATED BY '\n'
(icao, iata, name, city, country, x, y, elevation);

insert into airports(name,country,city,iata,icao,x,y,elevation)
select d.name,c.name,g.city,g.iata,d.icao,d.x,d.y,d.elevation from countries as c,airports_dafif as d,airports_gad as g where d.icao=g.icao and c.code=d.code and c.code!='US';

insert into airports(name,country,city,iata,icao,x,y,elevation)
select d.name,c.name,g.city,d.iata,d.icao,d.x,d.y,d.elevation from countries as c,airports_dafif as d,airports_gad as g where d.icao=g.icao and c.code=d.code and c.code='US';

select d.name,c.name,g.city,g.iata,d.icao,d.x,d.y,d.elevation from airports_dafif as d,airports_gad as g where d.icao!=g.icao limit 10;

