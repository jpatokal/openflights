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

aircraft_url = 'https://en.wikipedia.org/w/api.php?action=query&titles=List_of_ICAO_aircraft_type_designators&prop=revisions&rvprop=content&format=php'
response = urllib2.urlopen(aircraft_url).read()
planes = {}
for line in response.splitlines():
  if line.startswith('| '):
    col = line.split('||')
    icao = col[0].translate(None, '|').strip()
    iata = col[1].strip()
    if iata == '{{n/a}}' or iata == '':
        iata = None
    # [[Foo|Bar]] / [[Baz|Boq]] --> Bar
    name = col[2].split(' / ')[0].split('|')[-1].translate(None, '[]').strip()
    print name, '<<<', col[2]
    planes[name] = {'iata': iata, 'icao': icao}

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
