#!/bin/sh
rm AirlineRouteMapper.zip
wget http://arm.64hosts.com/AirlineRouteMapper.zip
cd arm
unzip -o ../AirlineRouteMapper.zip routes.dat
cd ..
mysql -u openflights --local-infile flightdb2 <../sql/import-arm.sql

