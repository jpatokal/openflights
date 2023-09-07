#!/usr/bin/python
# Update IATA & ICAO code for planes
#
# Prereqs:
# virtualenv env
# source env/bin/activate
# curl https://bootstrap.pypa.io/get-pip.py | python
# pip3 install mysql-connector bs4 country_converter unicodecsv
#
# Run tests:
# python3 update_airlines_test.py
#
# Run script:
# python3 tools/update_airlines.py --source acuk --file data/avcodes.csv --local --interactive=True

import argparse
import codecs
import country_converter as coco
import difflib
import mysql.connector
import re
import sys
import urllib.request, urllib.error, urllib.parse
import unicodecsv
from bs4 import BeautifulSoup
from collections import defaultdict
from html.parser import HTMLParser
from pprint import pprint

import database_connector

class HTMLCleaner(HTMLParser):
  def __init__(self):
    HTMLParser.__init__(self, convert_charrefs=False)
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
  def __init__(self, aldb, args=None):
    self.aldb = aldb
    self.of_iata = defaultdict(list)
    self.of_icao = defaultdict(list)
    self.args = args

  def load_all_airlines(self):
    # Match preferably against active ('Y') and frequency (more flights good)
    aldb.cursor.execute('SELECT * FROM airlines WHERE name != "" ORDER BY active DESC, frequency DESC')
    for row in aldb.cursor:
      self.add_airline(row)

  def add_airline(self, row):
    if row['iata'] == "":
      row['iata'] = None
    if 'iata' in row:
      self.of_iata[row['iata']].append(row)
    if 'icao' in row:
      self.of_icao[row['icao']].append(row)

  def update_airline(self, original, fields):
    for key, value in fields.items():
      original[key] = value

  def match(self, wp):
    name, icao, iata, callsign, country = wp.get('name'), wp.get('icao'), wp.get('iata'), wp.get('callsign'), wp.get('country')
    match = None
    dupe = None

    # Round 1: Find high-probability matches
    if icao and icao in self.of_icao:
      for airline in self.of_icao[icao]:
        if (iata and airline['iata'] == iata) or airline['callsign'] == callsign or airline['country'] == country:
          match = airline

    if not match and iata and iata in self.of_iata:
      for airline in self.of_iata[iata]:
        if (callsign and airline.get('callsign') == callsign) or (country and airline.get('country') == country):
          match = airline
          break

    # Round 2: Filter out dodgy matches
    # TODO: This should be a filter inside Round 1 so it can try again after a failing match...

    # Name does not match, might be sus
    if match and airline['name'].upper() != name.upper():

      # Do not match cargo subsidiaries against parents, or vice versa
      if ("Cargo" in name and "Cargo" not in airline['name']) or ("Cargo" not in name and "Cargo" in airline['name']):
        return None, None

      # Do not match an inactive airline with a different name against a currently active one
      if wp.get('active') == 'N' and airline['active'] == 'Y':
        return None, None

      if self.args.interactive:
        print(">> PROPOSED RENAME: from %s to %s" % (airline['name'], wp['name']))
        answer = input("Allow? (y/N)")
        if answer != 'y':
          print("Ignored.")
          return None, None
        print("Matched.")
      else:
        print(">> MATCH WITH RENAME: from %s to %s" % (airline['name'], wp['name']))

    # Deduping is really a completely different problem, leaving this here for historical purposes

    # Round 3: Find potential duplicates
    #if match and 'iata' in match and match['iata']:
    #  for airline in self.of_iata[match['iata']]:
    #    if airline == match:
    #      continue
    #    # Different countries?  Not dupes.
    #    if airline['country'] != match['country']:
    #      continue
    #    # If non-null ICAO codes same, guaranteed dupe; if different, not dupe
    #    if airline['icao'] and match['icao']:
    #      if airline['icao'] == match['icao']:
    #        dupe = airline
    #      else:
    #        continue
    #
    #    # If non-null callsigns same, guaranteed dupe; if different, not dupe
    #    if airline['callsign'] and match['callsign']:
    #      if airline['callsign'].upper() == match['callsign'].upper():
    #        dupe = airline
    #      else:
    #        continue
    #
    #    # Are names very similar?
    #    if difflib.SequenceMatcher(None, airline['name'], match['name']).ratio() > 0.8:
    #      dupe = airline

    # If dupe is active and match is not, flip order!
    #if dupe and 'active' in dupe and dupe['active'] == 'Y' and 'active' in match and match['active'] == 'N':
    #  dupe = match
    #  match = airline

    return match, dupe

  # Diff existing and new entry, return hash of changed entries
  def diff(self, of, wp):
    # The order in which we trust sources (lower is better)
    reliable = True
    source_reliability = ['IATA', 'ACUK', 'Wikidata', 'Legacy', 'User']
    old_source_idx = source_reliability.index(of['source'])
    new_source_idx = source_reliability.index(wp['source'])
    if old_source_idx < new_source_idx:
      reliable = False
      print('! New source %s less reliable than %s, only adding missing values' % (wp['source'], of['source']))

    fields = {}
    for field in ['name', 'callsign', 'icao', 'iata', 'source', 'country', 'country_code', 'start_date', 'end_date', 'duplicate']:
      # Do we have a new value?
      if field in wp and wp[field] and wp[field] != of[field]:

        # Is the change more than just case?
        if not of[field] or str(wp[field]).upper() != str(of[field]).upper():

          # Only alter main name if it's not an abbreviation
          if field != 'name' or len(wp[field]) > 3:

            # Reliable sources can overwrite, unreliable ones can only append
            if reliable or not of[field]:
              if field not in ['country_code', 'callsign', 'start_date', 'end_date', 'source']:
                if self.args.interactive:
                  print("current", of)
                  print("incoming", wp)
                  print(">> PROPOSED UPDATE: field %s from %s to %s" % (field, of[field], wp[field]))
                  answer = input("Allow? (y/N)")
                  if answer != 'y':
                    print("No change made.")
                    continue
                  print("Updated.")
                else:
                  print(">> UPDATED: field %s from %s to %s" % (field, of[field], wp[field]))
              fields[field] = wp[field]

              # Special case: If it's a new name, retain old name as alias
              if field == 'name':
                fields['alias'] = of['name']

    # Special case: Only override activeness if new source says airline is defunct
    if of['active'] == 'Y' and wp['active'] == 'N':
      fields['active'] = 'N'
    return fields

  def add_new(self, wp):
    wp['alid'] = AirlineDB.add_new(self.aldb, wp)
    self.add_airline(wp)

  def update_from_src(self, of, wp, dupe):
    if dupe:
      self.aldb.deduplicate(of['alid'], dupe['alid'])

    fields = self.diff(of, wp)
    if fields:
      self.aldb.update_from_src(of['alid'], fields)
      self.update_airline(of, fields)
      return 1
    else:
      return 0

class AirlineDB(database_connector.DatabaseConnector):
  def add_new(self, wp):
    return self.safe_execute(
      'INSERT INTO airlines(name,iata,icao,callsign,country,country_code,start_date,end_date,active,source) VALUES(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)',
      (wp.get('name'), wp.get('iata'), wp.get('icao'), wp.get('callsign'), wp.get('country'),
        wp.get('country_code'), wp.get('start_date'), wp.get('end_date'), wp.get('active'), wp.get('source')))

  def update_from_src(self, alid, fields):
    field_string = ', '.join(["%s='%s'" % (k, fields[k].replace("'", "''")) for k in list(fields.keys())])
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
    req = urllib.request.Request(iata_url)
    req.add_header('Referer', 'https://www.iata.org')
    req.add_header('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/71.0.3578.98 Safari/537.36')
    response = urllib.request.urlopen(req).read()
    soup = BeautifulSoup(response, 'html.parser')

    # There are multiple tables, find the 'main' one
    table = soup.find('table', attrs={'style': 'margin-top:0px;'})

    # Ignore headers, which are "table thead tr"
    for row in table.find_all('tr', recursive=False):
      cells = row.find_all('td')
      if len(cells) > 1:
        name, iata, _, icao, country = [self.clean(c.get_text()) for c in cells[0:5]]
        country, country_code = cc_clean(country)
        self.airlines.append({
          'icao': icao,
          'iata': iata,
          'name': name,
          'callsign': None,
          'country': country,
          'country_code': country_code,
          'active': 'Y',
          'source': 'IATA'})
    return self.airlines

  def clean(self, x):
    if x:
      return x.translate(self.translate_table).strip()
    else:
      return None

class AirlineCodesUK(object):
  def load(self, filename):
    self.airlines = []
    with open(filename, 'rb') as csvfile:
      reader = unicodecsv.DictReader(csvfile, delimiter=';', encoding='latin1')
      # IATA_Code;ICAO_Code;Known_as;Airline_Name;Country;Callsign;Remarks;StartDate;Start_YR;EndDate;End_YR;IATASeqNo;ICAOSeqNo;Status
      for airline in reader:
        if not airline['Country']:
          continue
        country, country_code = cc_clean(airline['Country'])
        if airline['IATA_Code'].endswith('*'):
          duplicate = 'Y'
          iata = airline['IATA_Code'][0:2]
        else:
          duplicate = 'N'
          iata = airline['IATA_Code']
        start_date, end_date, active = None, None, airline['Status']
        if airline['Start_YR']:
          # Sometimes the year actually contains a MM/DD/YYYY date
          start_date = int(airline['Start_YR'].split('/')[-1])
        if airline['End_YR']:
          end_date = int(airline['End_YR'].split('/')[-1])
          active = 'N'
        self.airlines.append({
          'icao': airline['ICAO_Code'],
          'iata': iata,
          'name': airline['Known_as'], # common name
          'alias': airline['Airline_Name'], # formal name
          'callsign': airline['Callsign'],
          'country': country,
          'country_code': country_code,
          'active': active,
          'start_date': airline['Start_YR'],
          'end_date': airline['End_YR'],
          'duplicate': duplicate,
          'source': 'ACUK'})

class Wikidata(object):
  airlines = []

  def load(self, filename):
    with open(filename, 'rb') as csvfile:
      reader = unicodecsv.DictReader(csvfile, delimiter=',')
      # entity,airlineLabel,iata,icao,parentIata,parentIcao,callsign,countryLabel,countryIso,startDate,endDate
      self.parse(reader)
    return self.airlines

  def parse(self, rows):
    for airline in rows:
      # Ignore airlines where the name is just the Wikidata entity code
      if airline['entity'].rsplit('/', 1)[1] == airline['airlineLabel']:
        print(". FILTER[WIKIDATA:NONAME] %s" % airline)
        continue

      # Collapse GROUP_CONCAT fields that may contain comma-separated duplicates (for not, just take 1st entry)
      for field in ['iata', 'icao', 'parentIata', 'parentIcao', 'countryIso']:
        if field in airline:
          airline[field] = set(airline[field].split(',')).pop()

      # Ignore subsidiaries where the IATA code is identical to parent (commuter brands, cargo operations etc)
      if airline['iata'] == airline.get('parentIata'):
        print(". FILTER[WIKIDATA:SUBSIDIARY] %s" % airline)
        continue

      start_date, end_date = airline['startDate'], airline['endDate']
      if start_date and not end_date:
        active = 'Y'
      else:
        active = 'N'

      # Preference: ISO code of HQ > code of HQ's country > code of airline (there may be several)
      # This handles multinational airlines and subnational entities like Hong Kong
      if 'hqIso' in airline:
        airline['countryIso'] = airline['hqIso']
      elif 'hqCountryIso' in airline:
        airline['countryIso'] = airline['hqCountryIso']
      (country, country_code) = cc_clean(airline['countryIso'])

      self.airlines.append({
        'icao': airline['icao'],
        'iata': airline['iata'],
        'name': airline['airlineLabel'], # common name
        'callsign': airline['callsign'],
        'country': country,
        'country_code': country_code,
        'active': active,
        'start_date': start_date,
        'end_date': end_date,
        'source': 'Wikidata'})

def pp(airline):
  alid = airline['alid'] if 'alid' in airline else airline.get('source')
  return ('%s (%s/%s, %s)' % (airline.get('name'), airline.get('iata'), airline.get('icao'), alid))

# Slow to initialize so we make this global
cc = coco.CountryConverter()

def cc_clean(raw_name):
  if not raw_name:
    return (raw_name, None)
  name = cc.convert(names=[raw_name], to='name_short')
  if name == 'not found':
    return (raw_name, None)
  code = cc.convert(names=[raw_name], to='ISO2')
  return (name, code)

def process(airlines, ofa, stats):
  for airline in airlines:
    (of_airline, dupe) = ofa.match(airline)
    if of_airline:
      print("> MATCH %s == %s" % (pp(airline), pp(of_airline)))
      stats['matched'] += 1
      if dupe:
        print(">> DUPE %s -> %s" % (pp(dupe), pp(of_airline)))
        stats['deduped'] += 1
      stats['updated'] += ofa.update_from_src(of_airline, airline, dupe)
    else:
      if airline.get('country') and not 'country_code' in airline:
        airline['country'], airline['country_code'] = cc_clean(airline['country'])
      if (airline.get('iata') or airline.get('icao')) and airline.get('name') and airline.get('country_code') and airline.get('source'):
        print("= NEW %s" % pp(airline))
        ofa.add_new(airline)
        stats['added'] += 1
      else:
        print(". IGNORE %s" % pp(airline))
    stats['total'] += 1

if __name__ == "__main__":
  parser = argparse.ArgumentParser()
  parser.add_argument('--live_run', default=False, action='store_true')
  parser.add_argument('--local', default=False, action='store_true')
  parser.add_argument('--source', default='wiki')
  parser.add_argument('--file', default=None)
  parser.add_argument('--interactive', default=False, action='store_true')
  args = parser.parse_args()

  aldb = AirlineDB(args)
  ofa = OpenFlightsAirlines(aldb, args)
  ofa.load_all_airlines()

  stats = defaultdict(int)
  print("Source %s" % args.source)
  airlines = None
  if args.source == 'wiki':
    if not args.file:
      exit("--file mandatory if source is wiki")
    wiki = Wikidata()
    airlines = wiki.load(args.file)
  elif args.source == 'acuk':
    if not args.file:
      exit("--file mandatory if source is acuk")
    acuk = AirlineCodesUK()
    airlines = acuk.load(args.file)
  else:
    iatadb = IATAAirlines()
    airlines = iatadb.load()
  process(airlines, ofa, stats)

  print("%s matched with %s updated and %s deduped, %s added, %s total" % (
    stats['matched'], stats['updated'], stats['deduped'], stats['added'], stats['total']))

