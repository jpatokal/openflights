#!/usr/bin/python
# Update IATA & ICAO code for planes from Wikipedia
#
# Prereqs:
# virtualenv env
# source env/bin/activate
# curl https://bootstrap.pypa.io/get-pip.py | python
# pip install mysql-connector unittest

import argparse
import codecs
import mysql.connector
import sys
import urllib2
from collections import defaultdict

import database_connector

class OpenFlightsAirlines(object):
  def __init__(self, aldb):
    self.aldb = aldb
    self.of_iata = defaultdict(list)
    self.of_icao = defaultdict(list)

  def load_all_airlines(self):
    aldb.cursor.execute('SELECT * FROM airlines')
    for row in aldb.cursor:
      if row['iata'] == "":
        row['iata'] = None
      self.of_iata[row['iata']].append(row)
      self.of_icao[row['icao']].append(row)

  def match(self, wp):
    icao, iata, callsign, country = wp['icao'], wp['iata'], wp['callsign'], wp['country']
    if icao and icao in self.of_icao:
      for airline in self.of_icao[icao]:
        if (iata and airline['iata'] == iata) or airline['callsign'] == callsign or airline['country'] == country:
          return airline
    if iata and iata in self.of_iata:
      for airline in self.of_iata[iata]:
        if airline['callsign'] == callsign or airline['country'] == country:
          print "IATA MATCH %s, %s" % (airline, wp)
          return airline
    return None

  def diff(self, of, wp):
    fields = {}
    for field in ['name', 'callsign', 'icao', 'iata']:
      if wp[field] and wp[field] != of[field]:
        fields[field] = wp[field]
    return fields

  def update_from_wp(self, of, wp):
    fields = self.diff(of, wp)
    if fields:
      self.aldb.update_from_wp(of['apid'], fields)

class AirlineDB(database_connector.DatabaseConnector):
  def update_from_wp(self, of_apid, fields):
    field_string = ', '.join(map(lambda k: '%s=%s' % k, fields.items()))
    self.safe_execute(
      'UPDATE airports SET %s WHERE apid=%s',
      (', '.join(map(lambda k: '%s=%s' % k, fields.items())), of_apid))

class WikipediaArticle(object):
  def __init__(self):
    self.airlines = []

  def load(self, letter):
    airline_url = 'https://en.wikipedia.org/w/api.php?action=query&titles=List_of_airline_codes_(%s)&prop=revisions&rvprop=content&format=php'
    response = urllib2.urlopen(airline_url % letter).read()
    block = []
    header = 2
    for line in response.splitlines():
      if line.startswith('|-'):
        if header > 0:
          header -= 1
        else:
          self.airlines.append(self.parse_airline(block))
        block = []
      else:
        block.append(line)

  # |-
  # ! IATA
  # ! ICAO
  # ! Name
  # ! Call sign
  # ! Country
  # ! Comments
  def parse_airline(self, block):
    iata, icao, name, callsign, country = [self.clean(x) for x in block[0:5]]
    return {'icao': icao, 'iata': iata, 'name': name, 'callsign': callsign, 'country': country}

  def clean(self, x):
    # | ''[[Foo|Bar]]'' -> Bar
    x = unicode(x.split('|')[-1].translate(None, "[|]").replace("''", ""), 'utf-8')
    if x == '':
      return None
    return x

if __name__ == "__main__":
  # Needed to allow piping UTF-8 (srsly Python wtf)
  sys.stdout = codecs.getwriter('utf8')(sys.stdout)

  parser = argparse.ArgumentParser()
  parser.add_argument('--live_run', default=False, action='store_true')
  parser.add_argument('--local', default=False, action='store_true')
  args = parser.parse_args()

  aldb = AirlineDB(args)
  ofa = OpenFlightsAirlines(aldb)
  ofa.load_all_airlines()
  wpa = WikipediaArticle()
  wpa.load('A')

  count = 0
  updated = 0
  added = 0
  for airline in wpa.airlines:
    of_airline = ofa.match(airline)
    if of_airline:
      ofa.update_from_wp(of_airline, airline)
    else:
      print 'NEW', airline
      added += 1
    count += 1

  print "%s new, %s updated, %s total" % (added, updated, count)
