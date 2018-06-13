#!/usr/bin/python
# Update IATA & ICAO code for planes from Wikipedia
#
# Prereqs:
# virtualenv env
# source env/bin/activate
# curl https://bootstrap.pypa.io/get-pip.py | python
# pip install mysql-connector
import argparse
import codecs
import mysql.connector
import sys
import urllib2

# Needed to allow piping UTF-8 (srsly Python wtf)
sys.stdout = codecs.getwriter('utf8')(sys.stdout)

class DatabaseConnector(object):
  DB = 'flightdb2'

  def __init__(self):
    self.read_cnx = self.connect(host, pw)
    self.cursor = self.read_cnx.cursor(dictionary=True)
    self.write_cnx = self.connect(host, pw)
    self.write_cursor = self.write_cnx.cursor(dictionary=True)
    self.of_iata = {}
    self.of_icao = {}

  def connect(self, host, pw):
    cnx = mysql.connector.connect(user='openflights', database=self.DB, host=host, password=pw)
    cnx.raise_on_warnings = True
    return cnx

  def load_all_airlines(self):
    self.cursor.execute('SELECT * FROM airlines')
    for row in dbc.cursor:
      if row['iata'] == "":
        row['iata'] = None
      self.of_iata[row['iata']] = row
      self.of_icao[row['icao']] = row

  def find_by_iata(self, code):
    if code in self.of_iata:
      return self.of_iata[code]
    else:
      return None

  def find_by_icao(self, code):
    if code in self.of_icao:
      return self.of_icao[code]
    else:
      return None

  def safe_execute(self, sql, params, live_run):
    if live_run:
      self.write_cursor.execute(sql, params, )
      print ".. %s : %d rows updated" % (sql % params, self.write_cursor.rowcount)
      self.write_cnx.commit()
    else:
      print sql % params

DB = 'flightdb2'
parser = argparse.ArgumentParser()
parser.add_argument('--live_run', default=False, action='store_true')
parser.add_argument('--local', default=False, action='store_true')
args = parser.parse_args()

if args.local:
  host ='localhost'
  pw = None
else:
  host = '104.197.15.255'
  with open('../sql/db.pw','r') as f:
    pw = f.read().strip()
dbc = DatabaseConnector()
dbc.load_all_airlines()

# |-
# ! IATA
# ! ICAO
# ! Name
# ! Call sign
# ! Country
# ! Comments

def parse_airline(block):
  iata, icao, name, callsign, country = [clean(x) for x in block[0:5]]
  airlines[icao] = {'iata': iata, 'name': name, 'callsign': callsign, 'country': country}

def clean(x):
  # | [[Foo|Bar]] -> Bar
  x = unicode(x.split('|')[-1].translate(None, "[|]'"), 'utf-8')
  if x == '':
    return None
  return x

airlines = {}

airline_url = 'https://en.wikipedia.org/w/api.php?action=query&titles=List_of_airline_codes_(%s)&prop=revisions&rvprop=content&format=php'
response = urllib2.urlopen(airline_url % 'A').read()
block = []
header = 2
for line in response.splitlines():
  if line.startswith('|-'):
    if header > 0:
      header -= 1
    else:
      parse_airline(block)
    block = []
  else:
    block.append(line)

count = 0
updated = 0
added = 0
for icao, airline in airlines.iteritems():
  of_airline = dbc.find_by_icao(icao)
  if of_airline:
    # If ICAO matches and IATA *or* callsign are the same, the two are a match
    if of_airline['iata'] == airline['iata'] or of_airline['callsign'] == airline['callsign']:
      if of_airline['name'] != airline['name']:
        print 'MATCH %s: update name %s to %s' % (icao, of_airline['name'], airline['name'])
        updated += 1
      if airline['callsign'] != None and of_airline['callsign'] != airline['callsign']:
        print 'MATCH %s: update callsign %s to %s' % (icao, of_airline['callsign'], airline['callsign'])
        updated += 1
    else:
      print 'MISMATCH %s: %s, %s' % (icao, of_airline, airline)
  else:
    print 'NEW', icao, airline
    added += 1
  count += 1

print "%s new, %s updated, %s total" % (added, updated, count)
