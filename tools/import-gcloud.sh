#!/bin/bash
# Export from prod database to local
set -e

BACKUP=flightdb.sql
gcloud sql export sql openflights-db gs://openflights-backup/$BACKUP.gz --database=flightdb2 --table airports,airlines,planes,countries
gsutil cp gs://openflights-backup/$BACKUP.gz /tmp/$BACKUP.gz
gunzip /tmp/$BACKUP.gz
mysql -u openflights flightdb2 </tmp/$BACKUP
