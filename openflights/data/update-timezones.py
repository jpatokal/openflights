#!/usr/bin/python
# Use Google Time Zone API to update timezones for all airports.
# Note: Does not update DST status.
import api_key  # create api_key.py that contains API_KEY=[your Google developer key]
import json
import oursql
import time
import urllib2

def getTimeZone(lat, lng):
	timestamp = int(time.time())
	tz_api_url = 'https://maps.googleapis.com/maps/api/timezone/json?location=%s,%s&timestamp=%s&key=%s' % (
		  lat, lng, timestamp, API_KEY)
	response = json.loads(urllib2.urlopen(tz_api_url).read())
	if response["status"] == "OK":
		tz = response["rawOffset"] / 3600.0
		return tz
	else:
		print "Error! %s" % response

db = oursql.connect(host="localhost",
                    user="openflights",
                    passwd="",
                    db="flightdb2")

cur = db.cursor(oursql.DictCursor) 
cur.execute("SELECT iata,x,y FROM airports LIMIT 10")
for row in cur.fetchall():
	timezone = getTimeZone(row['y'], row['x'])
	print "%s (%s,%s) -> %s" % (row['iata'], row['lat'], row['lng'], timezone)
	time.sleep(35) # ensure we don't exceed 2500 req/day
