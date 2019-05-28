#!/usr/bin/python
# Update IATA & ICAO code for planes from Wikipedia
#
# Prereqs:
# virtualenv env
# source env/bin/activate
# curl https://bootstrap.pypa.io/get-pip.py | python
# pip install mysql-connector unittest bs4

import argparse
import codecs
import difflib
import mysql.connector
import re
import sys
import urllib2
from bs4 import BeautifulSoup
from collections import defaultdict
from HTMLParser import HTMLParser
from pprint import pprint

import database_connector

# TODO:
# Fix creation (countries...)

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
    match = None
    dupe = None

    # Round 1: Find high-probability matches
    if icao and icao in self.of_icao:
      for airline in self.of_icao[icao]:
        if (iata and airline['iata'] == iata) or airline['callsign'] == callsign or airline['country'] == country:
          match = airline
          break
        # Special case: IATA data, accept on basis of ICAO match
        if not callsign and not country:
          match = airline
          break

    if not match and iata and iata in self.of_iata:
      for airline in self.of_iata[iata]:
        if airline['callsign'] == callsign or airline['country'] == country:
          match = airline
          break

    # Round 2: Find potential duplicates
    if match and 'iata' in match and match['iata']:
      for airline in self.of_iata[match['iata']]:
        if airline == match:
          continue
        # Different countries?  Not dupes.
        if airline['country'] != match['country']:
          continue
        # If non-null ICAO codes same, guaranteed dupe; if different, not dupe
        if airline['icao'] and match['icao']:
          if airline['icao'] == match['icao']:
            dupe = airline
          else:
            continue

        # If non-null callsigns same, guaranteed dupe; if different, not dupe
        if airline['callsign'] and match['callsign']:
          if airline['callsign'].upper() == match['callsign'].upper():
            dupe = airline
          else:
            continue

        # Are names very similar?
        if difflib.SequenceMatcher(None, airline['name'], match['name']).ratio() > 0.8:
          dupe = airline

    # If dupe is active and match is not, flip order!
    if dupe and 'active' in dupe and dupe['active'] == 'Y' and 'active' in match and match['active'] == 'N':
      dupe = match
      match = airline

    return match, dupe

  def diff(self, of, wp):
    fields = {}
    for field in ['name', 'callsign', 'icao', 'iata']:
      if wp[field] and wp[field] != of[field]:
        if not of[field] or wp[field].upper() != of[field].upper():
          if field != 'name' or len(wp[field]) > 3:
            fields[field] = wp[field]

    # Special case: Only override activeness if WP says airline is defunct
    if of['active'] == 'Y' and wp['active'] == 'N':
      fields['active'] = 'N'
    return fields

  def update_from_wp(self, of, wp, dupe):
    if dupe:
      self.aldb.deduplicate(of['alid'], dupe['alid'])

    fields = self.diff(of, wp)
    if fields:
      self.aldb.update_from_wp(of['alid'], fields)
      return 1
    else:
      return 0

class AirlineDB(database_connector.DatabaseConnector):
  def add_new(self, wp):
    self.safe_execute(
      'INSERT INTO airlines(name,iata,icao,callsign,country) VALUES(%s,%s,%s,%s,%s)',
      (wp['name'], wp['iata'], wp['icao'], wp['callsign'], wp['country']))

  def update_from_wp(self, alid, fields):
    field_string = ', '.join(map(lambda k: "%s='%s'" % (k, fields[k].replace("'", "''")), fields.keys()))
    self.safe_execute('UPDATE airlines SET ' + field_string + ' WHERE alid=%s', (alid, ))

  def deduplicate(self, main_id, dupe_id):
    self.safe_execute('UPDATE flights SET alid=%s WHERE alid=%s;', (main_id, dupe_id, ))
    self.safe_execute('DELETE airlines WHERE alid=%s;', (dupe_id, ))


class IATAAirlines(object):
  def __init__(self):
    self.translate_table = dict((ord(char), None) for char in "'[|]*?")

  def load(self):
    self.airlines = []
    iata_url = 'https://www.iata.org/about/members/Pages/airline-list.aspx?All=true'
    req = urllib2.Request(iata_url)
    req.add_header('Referer', 'https://www.iata.org')
    req.add_header('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/71.0.3578.98 Safari/537.36')
    response = urllib2.urlopen(req).read()
    soup = BeautifulSoup(response, 'html.parser')

    # There are multiple tables, find the 'main' one
    table = soup.find('table', attrs={'style': 'margin-top:0px;'})

    # Ignore headers, which are "table thead tr"
    for row in table.find_all('tr', recursive=False):
      cells = row.find_all('td')
      if len(cells) > 1:
        # Last field is country, but the contents are unusable (Independent State of Papua New Guinea etc)
        name, iata, _, icao = [self.clean(c.get_text()) for c in cells[0:4]]
        self.airlines.append({'icao': icao, 'iata': iata, 'name': name, 'callsign': None, 'country': None, 'active': 'Y', 'source': 'IATA'})

  def clean(self, x):
    if x:
      return x.translate(self.translate_table).strip()
    else:
      return None

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

    # Italicized name or 'defunct' in comments means airline is not active
    if 'defunct' in block[5].lower() or re.search("^''.*''$", block[2]):
      active = 'N'
    else:
      active = 'Y'

    iata, icao, name, callsign, country, comments = [self.clean(x) for x in block[0:6]]

    return {'icao': icao, 'iata': iata, 'name': name, 'callsign': callsign, 'country': country, 'active': active}

  def clean(self, x):
    # Remove HTML tags and entities
    self.cleaner = HTMLCleaner()
    self.cleaner.feed(x)
    x = self.cleaner.get_data()

    # | ''[[Foo|Bar]]'' -> Bar
    x = unicode(x.split('|')[-1].translate(None, "[|]*?").replace("''", ""), 'utf-8').split(',')[0].strip()
    if x == '':
      return None
    return x

def pp(airline):
  alid = airline['alid'] if 'alid' in airline else 'N/A'
  return ('%s (%s/%s, %s)' % (airline['name'], airline['iata'], airline['icao'], alid))

def process(airlines, ofa, aldb, stats):
  for airline in airlines:
    (of_airline, dupe) = ofa.match(airline)
    if of_airline:
      print "> MATCH %s == %s" % (pp(airline), pp(of_airline))
      stats['matched'] += 1
      if dupe:
        print ">> DUPE %s -> %s" % (pp(dupe), pp(of_airline))
        stats['deduped'] += 1
      stats['updated'] += ofa.update_from_wp(of_airline, airline, dupe)
    else:
      if airline['active'] == 'Y':
        print "= NEW %s" % pp(airline)
        aldb.add_new(airline)
        stats['added'] += 1
      else:
        print ". DEFUNCT %s" % pp(airline)
    stats['total'] += 1

if __name__ == "__main__":
  # Needed to allow piping UTF-8 (srsly Python wtf)
  sys.stdout = codecs.getwriter('utf8')(sys.stdout)

  parser = argparse.ArgumentParser()
  parser.add_argument('--live_run', default=False, action='store_true')
  parser.add_argument('--local', default=False, action='store_true')
  parser.add_argument('--source', default='wiki')
  args = parser.parse_args()

  aldb = AirlineDB(args)
  ofa = OpenFlightsAirlines(aldb)
  ofa.load_all_airlines()

  stats = defaultdict(int)
  print "Source %s" % args.source
  if args.source == 'wiki':
    wpa = WikipediaArticle()
    for c in xrange(ord('A'), ord('Z')+1):
      wpa.load(chr(c))
      print "### %s" % chr(c)
      process(wpa.airlines, ofa, aldb, stats)
  else:
    iatadb = IATAAirlines()
    iatadb.load()
    process(iatadb.airlines, ofa, aldb, stats)

  print "%s matched with %s updated and %s deduped, %s added, %s total" % (
    stats['matched'], stats['updated'], stats['deduped'], stats['added'], stats['total'])

