#!/bin/sh
rm AirlineRouteMapper.zip
wget http://arm.64hosts.com/AirlineRouteMapper.zip
mkdir -p arm
unzip -o -j AirlineRouteMapper.zip routes.dat -d arm/
mysql -u openflights --local-infile flightdb2 <../sql/import-arm.sql
