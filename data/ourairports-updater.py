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
  DEFAULT_DB = 'flightdb2'

  def __init__(self, db=None):
    if not db:
      db = self.DEFAULT_DB
    self.read_cnx = mysql.connector.connect(user='openflights', database=db, unix_socket='/tmp/mysql.sock')
    self.read_cnx.raise_on_warnings = True
    self.cursor = self.read_cnx.cursor(dictionary=True)
    self.of_iata = {}
    self.of_icao = {}

    self.write_cnx = mysql.connector.connect(user='openflights', database=db, unix_socket='/tmp/mysql.sock')
    self.write_cnx.raise_on_warnings = True
    self.write_cursor = self.write_cnx.cursor(dictionary=True)

  def load_all_airports(self):
    self.cursor.execute('SELECT * FROM airports')
    for row in dbc.cursor:
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

  def move_iata_to_new_airport(self, iata, old_id, new_id, live_run):
    self.safe_execute('UPDATE flights SET src_apid=%s WHERE src_apid=%s;', (new_id, old_id, ), live_run)
    self.safe_execute('UPDATE flights SET dst_apid=%s WHERE dst_apid=%s;', (new_id, old_id, ), live_run)
    self.safe_execute('UPDATE airports SET iata=NULL WHERE apid=%s;', (old_id, ), live_run)
    self.safe_execute('UPDATE airports SET iata=%s WHERE apid=%s;', (iata, new_id, ), live_run)

  def add_icao_to_old_airport(self, icao, old_id, live_run):
    self.safe_execute('UPDATE airports SET icao=%s WHERE apid=%s;', (icao, old_id, ), live_run)

# Needed to allow piping UTF-8 (srsly Python wtf)
sys.stdout = codecs.getwriter('utf8')(sys.stdout)

parser = argparse.ArgumentParser()
parser.add_argument('--live_run', default=False, action='store_true')
args = parser.parse_args()

dbc = DatabaseConnector()
dbc.load_all_airports()
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

    else:
      # We only care about larger airports with ICAO identifiers
      if oa['type'] in ['medium_airport', 'large_airport'] and re.match(r'[A-Z]{4}', oa['ident']):
          print 'NEW %s (%s): %s' % (oa['ident'], oa['name'], oa['iata_code'])
          # TODO create here
          of = {'apid': 0}
          if oa['iata_code'] != '':
            dupe = dbc.find_by_iata(oa['iata_code'])
            if dupe:
              print '. DUPE %s (%s)' % (dupe['iata'], dupe['name'])
              dbc.add_icao_to_old_airport(oa['ident'], dupe['apid'], args.live_run)
