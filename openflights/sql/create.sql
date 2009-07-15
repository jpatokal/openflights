CREATE DATABASE flightdb2;

CREATE USER openflights;
GRANT ALL PRIVILEGES ON flightdb2.* TO openflights;

CONNECT flightdb2;

CREATE TABLE `locales` (
  `locale` varchar(5) NOT NULL,
  `name` mediumtext,
  PRIMARY KEY  (`locale`)
) DEFAULT CHARSET=utf8;

CREATE TABLE `users` (
  `name` text,
  `password` text,
  `uid` int(11) NOT NULL auto_increment,
  `public` text,
  `email` text,
  `count` int(11) default '0',
  `editor` varchar(1) default 'B',
  `elite` varchar(1) default '',
  `validity` date default NULL,
  `guestpw` text,
  `startpane` varchar(1) default 'H',
  `locale` varchar(5) default 'en_US',
  PRIMARY KEY  (`uid`)
) DEFAULT CHARSET=utf8;

CREATE TABLE `airports` (
  `name` text NOT NULL,
  `city` text,
  `country` text,
  `iata` varchar(3) default NULL,
  `icao` varchar(4) default NULL,
  `x` double NOT NULL,
  `y` double NOT NULL,
  `elevation` int(11) default NULL,
  `apid` int(11) NOT NULL auto_increment,
  `uid` int(11) default NULL,
  `timezone` float default NULL,
  `dst` varchar(1) default NULL,
  PRIMARY KEY  (`apid`),
  KEY `y` (`y`),
  KEY `x` (`x`),
  KEY `iata` (`iata`)
) DEFAULT CHARSET=utf8;

CREATE TABLE `airports_dafif` (
  `name` text NOT NULL,
  `city` text,
  `code` text,
  `iata` varchar(3) default NULL,
  `icao` varchar(4) NOT NULL,
  `x` double NOT NULL,
  `y` double NOT NULL,
  `elevation` int(11) default NULL,
  PRIMARY KEY  (`icao`)
) DEFAULT CHARSET=utf8;

CREATE TABLE `airports_gad` (
  `name` text NOT NULL,
  `city` text,
  `country` text,
  `iata` varchar(3) default NULL,
  `icao` varchar(4) NOT NULL,
  `x` double NOT NULL,
  `y` double NOT NULL,
  `elevation` int(11) default NULL,
  PRIMARY KEY  (`icao`)
) DEFAULT CHARSET=utf8;

CREATE TABLE `airports_oa` (
  `name` text NOT NULL,
  `city` text,
  `country` text,
  `continent` text,
  `iata` varchar(3) default NULL,
  `icao` varchar(4) default NULL,
  `x` double NOT NULL,
  `y` double NOT NULL,
  `elevation` int(11) default NULL,
  `ident` text,
  `type` text,
  `region` text,
  `service` text,
  `gps` text,
  `keywords` text,
  `code` varchar(2) default NULL,
  KEY `iata` (`iata`)
) DEFAULT CHARSET=utf8;

CREATE TABLE `planes` (
  `name` text,
  `abbr` text,
  `speed` double default NULL,
  `plid` int(11) NOT NULL auto_increment,
  `public` char(1) default 'N',
  PRIMARY KEY  (`plid`)
) DEFAULT CHARSET=utf8;

CREATE TABLE `airlines` (
  `name` text,
  `iata` varchar(2) default NULL,
  `icao` varchar(3) default NULL,
  `callsign` text,
  `country` text,
  `alid` int(11) NOT NULL auto_increment,
  `uid` int(11) default NULL,
  `alias` text,
  `mode` char(1) default 'F',
  `active` char(1) default 'N',
  PRIMARY KEY  (`alid`),
  KEY `iata` (`iata`)
) DEFAULT CHARSET=utf8;

CREATE TABLE `routes` (
  `airline` varchar(2) default NULL,
  `alid` int(11) default NULL,
  `src_ap` varchar(3) default NULL,
  `src_apid` int(11) default NULL,
  `dst_ap` varchar(3) default NULL,
  `dst_apid` int(11) default NULL,
  `codeshare` text,
  `stops` text,
  `equipment` text,
  `rid` int(11) NOT NULL auto_increment,
  PRIMARY KEY  (`rid`),
  KEY `src_apid` (`src_apid`),
  KEY `dst_apid` (`dst_apid`),
  KEY `alid` (`alid`)
) DEFAULT CHARSET=utf8;

CREATE TABLE `trips` (
  `name` text,
  `url` text,
  `uid` int(11) default NULL,
  `trid` int(11) NOT NULL auto_increment,
  `public` text,
  PRIMARY KEY  (`trid`),
  KEY `uid` (`uid`)
) DEFAULT CHARSET=utf8;

CREATE TABLE `flights` (
  `uid` int(11) default NULL,
  `src_apid` int(11) default NULL,
  `src_time` time default NULL,
  `dst_apid` int(11) default NULL,
  `distance` int(11) default NULL,
  `code` text,
  `seat` text,
  `seat_type` text,
  `class` text,
  `reason` text,
  `plid` int(11) default NULL,
  `alid` int(11) default NULL,
  `trid` int(11) default NULL,
  `fid` int(11) NOT NULL auto_increment,
  `duration` time default NULL,
  `registration` text,
  `note` text,
  `upd_time` datetime default NULL,
  `opp` char(1) default 'N',
  `src_date` date default NULL,
  `mode` char(1) default 'F',
  PRIMARY KEY  (`fid`),
  KEY `src_apid` (`src_apid`),
  KEY `dst_apid` (`dst_apid`),
  KEY `plid` (`plid`),
  KEY `alid` (`alid`),
  KEY `trid` (`trid`),
  KEY `uid` (`uid`)
) DEFAULT CHARSET=utf8;

CREATE TABLE `facebook` (
  `uid` int(11) default NULL,
  `fbuid` int(11) NOT NULL,
  `updated` datetime default NULL,
  `sessionkey` text,
  `pref_onfly` varchar(1) default 'Y',
  `pref_onnew` varchar(1) default 'Y',
  PRIMARY KEY  (`fbuid`),
  KEY `uid` (`uid`)
) DEFAULT CHARSET=utf8;

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
