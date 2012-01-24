#!/bin/sh
cd ~/openflights/data
rm AirlineRouteMapper.zip
wget http://arm.64hosts.com/AirlineRouteMapper.zip
cd arm
unzip -f ../AirlineRouteMapper.zip routes.dat
cd ..
mysql -u openflights flightdb2 <~/openflights/sql/import-arm.sql

