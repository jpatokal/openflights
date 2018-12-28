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
from HTMLParser import HTMLParser

import database_connector

class HTMLCleaner(HTMLParser):
  def __init__(self):
    self.reset()
    self.fed = []
    self.nuke = False

  # Special case: we remove contents of <ref>...</ref> tags
  def handle_starttag(self, tag, attrs):
    self.nuke = (tag == 'ref')

  def handle_endtag(self, tag):
    self.nuke = (tag == 'ref')

  def handle_data(self, d):
    if not self.nuke:
      self.fed.append(d)

  def get_data(self):
    return ''.join(self.fed)


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
      self.aldb.update_from_wp(of['alid'], fields)

class AirlineDB(database_connector.DatabaseConnector):
  def add_new(self, wp):
    self.safe_execute(
      'INSERT INTO airlines(name,iata,icao,callsign,country) VALUES(%s,%s,%s,%s,%s)',
      (wp['name'], wp['iata'], wp['icao'], wp['callsign'], wp['country']))

  def update_from_wp(self, alid, fields):
    field_string = ', '.join(map(lambda k: "%s='%s'" % (k, fields[k].replace("'", "''")), fields.keys()))
    self.safe_execute('UPDATE airlines SET ' + field_string + ' WHERE alid=%s', (alid, ))

class WikipediaArticle(object):
  def __init__(self):
    self.cleaner = HTMLCleaner()

  def load(self, letter):
    self.airlines = []
    airline_url = 'https://en.wikipedia.org/w/api.php?action=query&titles=List_of_airline_codes_(%s)&prop=revisions&rvprop=content&format=php'
    response = urllib2.urlopen(airline_url % letter).read()
    block = []
    header = 2
    for line in response.splitlines():
      if line.startswith('|-'):
        if header > 0:
          header -= 1
        else:
          airline = self.parse_airline(block)
          if airline:
            self.airlines.append(airline)
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
    if len(block) < 5:
      return None
    iata, icao, name, callsign, country = [self.clean(x) for x in block[0:5]]
    return {'icao': icao, 'iata': iata, 'name': name, 'callsign': callsign, 'country': country}

  def clean(self, x):
    # Remove HTML tags and entities
    self.cleaner = HTMLCleaner()
    self.cleaner.feed(x)
    x = self.cleaner.get_data()

    # | ''[[Foo|Bar]]'' -> Bar
    x = unicode(x.split('|')[-1].translate(None, "[|]*?").replace("''", ""), 'utf-8').strip()
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

  count = 0
  updated = 0
  added = 0
  wpa = WikipediaArticle()
  for c in xrange(ord('A'), ord('Z')+1):
    wpa.load(chr(c))
    print "### %s" % chr(c)
    for airline in wpa.airlines:
      of_airline = ofa.match(airline)
      if of_airline:
        print "> MATCH %s %s" % (airline, of_airline)
        ofa.update_from_wp(of_airline, airline)
        updated += 1
      else:
        print "= NEW %s" % airline
        aldb.add_new(airline)
        added += 1
      count += 1

  print "%s new, %s updated, %s total" % (added, updated, count)
