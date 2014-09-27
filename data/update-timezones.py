#!/usr/bin/python
# Use Google Time Zone API to update timezones for all airports.
# Note: Does not update DST status.
import json
import oursql
import time
import urllib2

API_KEY='YOUR_GOOGLE_API_KEY_HERE'
FIRST_APID=0  # If you need to restart updating halfway through

def getTimeZone(lat, lng):
	timestamp = int(time.time())
	tz_api_url = 'https://maps.googleapis.com/maps/api/timezone/json?location=%s,%s&timestamp=%s&key=%s' % (
		  lat, lng, timestamp, API_KEY)
	response = json.loads(urllib2.urlopen(tz_api_url).read())
	if response["status"] == "OK":
		tz = response["rawOffset"] / 3600.0
		return (tz, response['timeZoneId'])
        if response["status"] == "ZERO_RESULTS":
                print "Zero results!"
                return (None, None)
	else:
		print "Error! %s" % response
                exit()

db = oursql.connect(host="localhost",
                    user="openflights",
                    passwd="",
                    db="flightdb2")

cur = db.cursor(oursql.DictCursor) 
cur.execute("SELECT icao,apid,x,y,timezone,tz_id FROM airports WHERE apid > %s ORDER BY apid ASC" % FIRST_APID)
count = 0
updated = 0
for row in cur.fetchall():
	new_timezone, tz_id = getTimeZone(row['y'], row['x'])
	print u"%s %s (%s,%s) -> new %s, old %s (%s)" % (row['icao'], row['apid'], row['y'], row['x'], new_timezone, row['timezone'], tz_id)
        if (new_timezone and new_timezone != row['timezone']) or (tz_id and tz_id != row['tz_id']):
          cur.execute('UPDATE airports SET timezone=?, tz_id=? WHERE apid=?', (new_timezone, tz_id, row['apid']))
          print 'Updated!'
          updated += 1
	count += 1
        time.sleep(1) # ensure we don't exceed 2500 req/day
print "%s of %s airports updated" % (updated, count)
