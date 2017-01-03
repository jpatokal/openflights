# One-way sync to update airports table from OurAirports.
# 

# Install
# virtualenv env
# source env/bin/activate
# pip install unicodecsv mysql-connector

# To fetch data:
# $ curl -o ourairports.csv http://ourairports.com/data/airports.csv
import mysql.connector
import unicodecsv
import re

import sys
import codecs
# Needed to allow piping UTF-8 (srsly Python wtf)
sys.stdout = codecs.getwriter('utf8')(sys.stdout)

class DatabaseConnector(object):
  DEFAULT_DB = 'flightdb2'

  def __init__(self, db=None):
    if not db:
      db = self.DEFAULT_DB
    self.cnx = mysql.connector.connect(user='openflights', database=db, unix_socket='/tmp/mysql.sock')
    self.cnx.raise_on_warnings = True
    self.cursor = self.cnx.cursor(dictionary=True)
    self.of_iata = {}
    self.of_icao = {}

  def load_all_airports(self):
    self.cursor.execute("SELECT * FROM airports")
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

dbc = DatabaseConnector()
dbc.load_all_airports()
with open('ourairports.csv', 'rb') as csvfile:
  reader = unicodecsv.DictReader(csvfile, encoding='utf-8')
  for oa in reader:
    if oa['type'] in ['medium_airport', 'large_airport']:
      of = dbc.find_by_icao(oa['ident'])
      if of:
        if oa['iata_code'] != '' and of['iata'] != oa['iata_code']:
          print "OLD %s (%s): IATA mismatch OF %s, OA %s" % (oa['ident'], oa['name'], of['iata'], oa['iata_code'])
          if of['iata'] != '':
            dupe = dbc.find_by_iata(oa['iata_code'])
            if dupe:
              print "  DUPE %s (%s)" % (dupe['iata'], dupe['name'])
      else:
        if re.match(r"[A-Z]{4}", oa['ident']): # looks like ICAO
          print "NEW %s (%s): %s" % (oa['ident'], oa['name'], oa['iata_code'])
