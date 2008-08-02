#!/bin/bash
for i in `seq 1 2500`; do
  echo $i
  mysql -u openflights flightdb <junkdata.sql
done
