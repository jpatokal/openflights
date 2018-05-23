#!/usr/bin/python
# Update IATA & ICAO code for planes from Wikipedia
#
# Prereqs:
# virtualenv env
# source env/bin/activate
# curl https://bootstrap.pypa.io/get-pip.py | python
# pip install mysql-connector
import argparse
import mysql.connector
import urllib2

DB = 'flightdb2'
parser = argparse.ArgumentParser()
parser.add_argument('--local', default=False, action='store_true')
args = parser.parse_args()

if args.local:
  host ='localhost'
  pw = None
else:
  host = '104.197.15.255'
  with open('../sql/db.pw','r') as f:
    pw = f.read().strip()

airline_url = 'https://en.wikipedia.org/w/api.php?action=query&titles=List_of_airline_codes_(%s)&prop=revisions&rvprop=content&format=php'
response = urllib2.urlopen(airline_url % 'A').read()
airlines = {}
lines = response.splitlines()

# |-
# ! IATA
# ! ICAO
# ! Name
# ! Call sign
# ! Country
# ! Comments

def parse_airline(block):
  iata, icao, name, callsign, country = [clean(x) for x in block[0:5]]
  print iata, icao, name, callsign, country
  airlines[name] = {'iata': iata, 'icao': icao}

def clean(x):
  # | [[Foo|Bar]] -> Bar
  x = x.split('|')[-1].translate(None, "[|]'")
  if x == '':
    return None
  return x

block = []
header = 2
for line in lines:
  if line.startswith('|-'):
    if header > 0:
      header -= 1
    else:
      parse_airline(block)
    block = []
  else:
    block.append(line)

exit()

cnx = mysql.connector.connect(user='openflights', database=DB, host=host, password=pw)
cnx.raise_on_warnings = True
cur = cnx.cursor(dictionary=True, buffered=True)
cur.execute('SELECT name,iata,icao,plid FROM planes ORDER BY plid ASC')
count = 0
updated = 0

for row in cur.fetchall():
  name = row['name']
  if name in planes and row['iata'] == None:
    plane = planes[name]
    cur.execute('UPDATE planes SET iata=%s, icao=%s WHERE plid=%s', (plane['iata'], plane['icao'], row['plid']))
    print 'Updated %d rows for %s, %s, %s (%s)' % (cur.rowcount, name, plane['iata'], plane['icao'], row['plid'])
    updated += 1
  count += 1

print "%s of %s planes updated" % (updated, count)
cnx.commit()
