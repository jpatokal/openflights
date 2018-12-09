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
  def __init__(self):
    self.of_iata = defaultdict(list)
    self.of_icao = defaultdict(list)

  def load_all_airlines(self, dbc):
    dbc.cursor.execute('SELECT * FROM airlines')
    for row in dbc.cursor:
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

class AirlineDB(database_connector.DatabaseConnector):
  pass

class WikipediaArticle(object):
  def __init__(self):
    airlines = []

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
          parse_airline(block)
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
    iata, icao, name, callsign, country = [clean(x) for x in block[0:5]]
    airlines.append({'icao': icao, 'iata': iata, 'name': name, 'callsign': callsign, 'country': country})

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
  ofa = OpenFlightsAirlines()
  ofa.load_all_airlines(aldb)
  wpa = WikipediaArticle()
  wpa.load('A')

  count = 0
  updated = 0
  added = 0
  for airline in wpa.airlines:
    of_airline = ofa.match_airline(airline)
    if of_airline:
      if of_airline['name'] != airline['name']:
        print 'MATCH %s/%s: update name %s to %s' % (of_airline['iata'], of_airline['icao'], of_airline['name'], airline['name'])
        updated += 1
      if airline['callsign'] != None and of_airline['callsign'] != airline['callsign']:
        print 'MATCH %s/%s: update callsign %s to %s' % (of_airline['iata'], of_airline['icao'], of_airline['callsign'], airline['callsign'])
        updated += 1
    else:
      print 'NEW', airline
      added += 1
    count += 1

  print "%s new, %s updated, %s total" % (added, updated, count)
