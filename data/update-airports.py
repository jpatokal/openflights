# One-way sync to update airports table from OurAirports.
# Run with --live_run to actually execute DB changes.

# Install
# virtualenv env
# source env/bin/activate
# pip install unicodecsv mysql-connector

# To fetch data:
# $ curl -o ourairports.csv http://ourairports.com/data/airports.csv
import argparse
import codecs
import mysql.connector
import re
import sys
import unicodecsv

class DatabaseConnector(object):
  DB = 'flightdb2'

  def __init__(self):
    self.read_cnx = self.connect(host, pw)
    self.cursor = self.read_cnx.cursor(dictionary=True)
    self.write_cnx = self.connect(host, pw)
    self.write_cursor = self.write_cnx.cursor(dictionary=True)
    self.of_iata = {}
    self.of_icao = {}
    self.countries = {}

  def connect(self, host, pw):
    cnx = mysql.connector.connect(user='openflights', database=self.DB, host=host, password=pw)
    cnx.raise_on_warnings = True
    return cnx

  def load_all_airports(self):
    self.cursor.execute('SELECT * FROM airports')
    for row in dbc.cursor:
      self.of_iata[row['iata']] = row
      self.of_icao[row['icao']] = row

  def load_all_countries(self):
    self.cursor.execute('SELECT * FROM countries')
    for row in dbc.cursor:
      self.countries[row['oa_code']] = row['name']

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

  def update_all_from_oa(self, of_apid, oa, live_run):
    self.safe_execute(
      'UPDATE airports SET icao=%s, name=%s, x=%s, y=%s, elevation=%s, type=%s, source=%s WHERE apid=%s',
      (oa['ident'], oa['name'], oa['longitude_deg'], oa['latitude_deg'], (oa['elevation_ft'] or 0),
        'airport', 'OurAirports', of_apid),
      live_run)

  def create_new_from_oa(self, oa, live_run):
    self.safe_execute(
      'INSERT INTO airports(name,city,country,iata,icao,x,y,elevation,type,source) VALUES(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)',
      (oa['name'], oa['municipality'], self.countries[oa['iso_country']], (oa['iata_code'] or None), oa['ident'],
        oa['longitude_deg'], oa['latitude_deg'], (oa['elevation_ft'] or 0), 'airport', 'OurAirports'),
      live_run)

  def move_iata_to_new_airport(self, iata, old_id, new_id, live_run):
    self.safe_execute('UPDATE flights SET src_apid=%s WHERE src_apid=%s;', (new_id, old_id, ), live_run)
    self.safe_execute('UPDATE flights SET dst_apid=%s WHERE dst_apid=%s;', (new_id, old_id, ), live_run)
    self.safe_execute('UPDATE airports SET iata=NULL WHERE apid=%s;', (old_id, ), live_run)
    self.safe_execute('UPDATE airports SET iata=%s WHERE apid=%s;', (iata, new_id, ), live_run)

# Needed to allow piping UTF-8 (srsly Python wtf)
sys.stdout = codecs.getwriter('utf8')(sys.stdout)

parser = argparse.ArgumentParser()
parser.add_argument('--live_run', default=False, action='store_true')
parser.add_argument('--local', default=False, action='store_true')
args = parser.parse_args()

if args.local:
  host = 'localhost'
  pw = None
else:
  host = '104.197.15.255'
  with open('../sql/db.pw','r') as f:
    pw = f.read().strip()

dbc = DatabaseConnector()
dbc.load_all_airports()
dbc.load_all_countries()
with open('ourairports.csv', 'rb') as csvfile:
  reader = unicodecsv.DictReader(csvfile, encoding='utf-8')
  for oa in reader:
    of = dbc.find_by_icao(oa['ident'])
    if of:
      if oa['iata_code'] != '' and of['iata'] != oa['iata_code']:
        print 'OLD %s (%s): IATA mismatch OF %s, OA %s' % (oa['ident'], oa['name'], of['iata'], oa['iata_code'])
        if of['iata'] != '':
          dupe = dbc.find_by_iata(oa['iata_code'])
          if dupe:
            print '. DUPE %s (%s)' % (dupe['iata'], dupe['name'])
            dbc.move_iata_to_new_airport(oa['iata_code'], dupe['apid'], of['apid'], args.live_run)
      dbc.update_all_from_oa(of['apid'], oa, args.live_run)

    else:
      # We only care about larger airports with ICAO identifiers
      if oa['type'] in ['medium_airport', 'large_airport'] and re.match(r'[A-Z]{4}', oa['ident']):
          # Horrible hack for matching FAA LIDs
          if not oa['iata_code'] and len(oa['local_code']) == 3:
            oa['iata_code'] = oa['local_code']
          print 'NEW %s (%s): %s' % (oa['ident'], oa['name'], oa['iata_code'])
          if oa['iata_code'] == '':
            dbc.create_new_from_oa(oa, args.live_run)
          else:
            if oa['iata_code'] != '':
              dupe = dbc.find_by_iata(oa['iata_code'])
              if dupe:
                print '. DUPE %s (%s)' % (dupe['iata'], dupe['name'])
                dbc.update_all_from_oa(dupe['apid'], oa, args.live_run)
              else:
                dbc.create_new_from_oa(oa, args.live_run)

