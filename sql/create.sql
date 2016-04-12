CREATE DATABASE flightdb2;

CREATE USER openflights@localhost;
GRANT ALL PRIVILEGES ON flightdb2.* TO openflights@localhost;

CONNECT flightdb2;

DROP TABLE IF EXISTS `airlines`;
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
  `active` varchar(1) default 'N',
  PRIMARY KEY  (`alid`),
  KEY `iata` (`iata`),
  KEY `icao` (`icao`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `airports`;
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
  `dst` char(1) default NULL,
  `tz_id` text,
  PRIMARY KEY  (`apid`),
  KEY `x` (`x`),
  KEY `y` (`y`),
  KEY `iata` (`iata`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE UNIQUE INDEX `iata_idx` ON airports(iata);
CREATE UNIQUE INDEX `icao_idx` ON airports(icao);

DROP TABLE IF EXISTS `airports_dafif`;
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `airports_gad`;
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `airports_oa`;
CREATE TABLE `airports_oa` (
  `oaid` int(11) NOT NULL,
  `ident` text,
  `type` text,
  `name` text NOT NULL,
  `x` double NOT NULL,
  `y` double NOT NULL,
  `elevation` int(11) default NULL,
  `continent` text,
  `country` text,
  `region` text,
  `city` text,
  `service` text,
  `icao` varchar(4) default NULL,
  `iata` varchar(3) default NULL,
  `local` varchar(3) default NULL,
  `home_link` text,
  `wp_link` text,
  `keywords` text,
  PRIMARY KEY  (`oaid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `countries`;
CREATE TABLE `countries` (
  `junk` text,
  `code` varchar(2) NOT NULL,
  `name` text,
  `oa_code` varchar(2) default NULL,
  `dst` char(1) default NULL,
  PRIMARY KEY  (`code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `countries_oa`;
CREATE TABLE `countries_oa` (
  `oacode` text,
  `country` text,
  `continent` text
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `facebook`;
CREATE TABLE `facebook` (
  `uid` int(11) default NULL,
  `fbuid` int(11) NOT NULL,
  `updated` datetime default NULL,
  `sessionkey` text,
  `pref_onfly` varchar(1) default 'Y',
  `pref_onnew` varchar(1) default 'Y',
  PRIMARY KEY  (`fbuid`),
  KEY `uid` (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `flights`;
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `locales`;
CREATE TABLE `locales` (
  `locale` varchar(5) NOT NULL,
  `name` text,
  PRIMARY KEY  (`locale`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `planes`;
CREATE TABLE `planes` (
  `name` text,
  `abbr` text,
  `speed` double default NULL,
  `plid` int(11) NOT NULL auto_increment,
  `public` char(1) default NULL,
  PRIMARY KEY  (`plid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `routes`;
CREATE TABLE `routes` (
  `airline` varchar(3) default NULL,
  `alid` int(11) default NULL,
  `src_ap` varchar(4) default NULL,
  `src_apid` int(11) default NULL,
  `dst_ap` varchar(4) default NULL,
  `dst_apid` int(11) default NULL,
  `codeshare` text,
  `stops` text,
  `equipment` text,
  `added` varchar(1) default NULL,
  `rid` int(11) NOT NULL auto_increment,
  PRIMARY KEY  (`rid`),
  UNIQUE KEY `alid` (`alid`,`src_apid`,`dst_apid`),
  KEY `src_apid` (`src_apid`),
  KEY `dst_apid` (`dst_apid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `trips`;
CREATE TABLE `trips` (
  `name` text,
  `url` text,
  `uid` int(11) default NULL,
  `trid` int(11) NOT NULL auto_increment,
  `public` text,
  PRIMARY KEY  (`trid`),
  KEY `uid` (`uid`)
) ENGINE=MyISAM AUTO_INCREMENT=84 DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `users`;
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
  `units` varchar(1) default 'M',
  PRIMARY KEY  (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `tripit_tokens`;
CREATE TABLE `tripit_tokens` (
  `uid` int(11) NOT NULL,
  `auth_token` char(40) NOT NULL,
  `auth_token_secret` char(40) NOT NULL,
  `active` enum('N','Y') NOT NULL DEFAULT 'Y',
  KEY `uid_idx` (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

\! echo Done, next run sql/load-data.sql

