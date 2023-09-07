# -*- coding: utf-8 -*-

import update_airlines

import argparse
import unittest
import unittest.mock as mock
import unittest.mock as patch
from collections import defaultdict

class UpdateAirlinesTest(unittest.TestCase):
  def setUp(self):
    args = mock.Mock()
    args.interactive = False
    self.fake_aldb = mock.Mock(spec=update_airlines.AirlineDB)
    self.ofa = update_airlines.OpenFlightsAirlines(self.fake_aldb, args)

    self.of = {
      'icao': 'ABC',
      'iata': 'AB',
      'name': 'Åland Airlines',
      'callsign': 'ALAXA',
      'country': 'Åland',
      'country_code': 'AX',
      'active': 'N',
      'start_date': '1969-01-01',
      'end_date': '1999-01-01',
      'source': 'Wikidata'
    }
    self.addToIndex(self.of)
    self.wd = update_airlines.Wikidata()
    self.wd.airlines = []

  def addToIndex(self, airline):
    self.ofa.of_iata[airline['iata']].append(airline)
    self.ofa.of_icao[airline['icao']].append(airline)

  # Match against existing data and check diff
  def assertOnlyChange(self, new_entry, old_entry=None, diff={}):
    if not old_entry:
      old_entry = self.of
    self.assertEqual(self.ofa.match(new_entry), (old_entry, None))
    self.assertEqual(self.ofa.diff(old_entry, new_entry), diff)

  def assertUnchanged(self, new_entry, old_entry=None):
    self.assertOnlyChange(new_entry, old_entry, {})

  def assertNoMatches(self, new_entry):
    self.assertEqual(self.ofa.match(new_entry), (None, None))

  #
  # Tests start here
  #

  # ICAO and callsign or country matches
  def testExactMatch(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikidata', 'start_date': '1969-01-01'}
    self.assertUnchanged(wp)

  def testExactMatchNewSource(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'IATA'}
    self.assertOnlyChange(wp, diff={'source': 'IATA'})

  def testExactMatchLessReliableSource(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Lol Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'User'}
    self.assertUnchanged(wp)

  def testExactMatchLessReliableSourceUpdateNulls(self):
    self.of['callsign'] = ''
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Lol Airlines', 'callsign': 'NEWXA', 'country': 'Åland', 'source': 'User'}
    self.assertOnlyChange(wp, diff={'callsign': 'NEWXA'})

  def testNameChangeKeepingOldNameAsAlias(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Ahvenanmaa Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikidata'}
    self.assertOnlyChange(wp, diff={'name': 'Ahvenanmaa Airlines', 'alias': 'Åland Airlines'})

  def testCountryChange(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Ahvenanmaa', 'source': 'Wikidata'}
    self.assertOnlyChange(wp, diff={'country': 'Ahvenanmaa'})

  def testCountryCodeChange(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'country_code': 'FI', 'source': 'Wikidata'}
    self.assertOnlyChange(wp, diff={'country_code': 'FI'})

  def testActiveToInactiveChange(self):
    self.of['active'] = 'Y'
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikidata', 'active': 'N', 'end_date': '2019-01-01'}
    self.assertOnlyChange(wp, diff={'active': 'N', 'end_date': '2019-01-01'})

  def testIgnoreInactiveToActiveChange(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikidata', 'active': 'N', 'end_date': '2019-01-01'}
    self.assertOnlyChange(wp, diff={'end_date': '2019-01-01'})

  def testIgnoreCaseChange(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland AIRLINES', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikidata'}
    self.assertUnchanged(wp)

  def testIgnoreNameAbbreviation(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'ALA', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikidata'}
    self.assertUnchanged(wp)

  def testIcaoIataMatch(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ZZZZZ', 'country': 'Åland', 'source': 'Wikidata'}
    self.assertOnlyChange(wp, diff={'callsign': 'ZZZZZ'})

  def testIcaoCallsignMatch(self):
    wp = {'icao': 'ABC', 'iata': 'ZZ', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikidata'}
    self.assertOnlyChange(wp, diff={'iata': 'ZZ'})

  def testIcaoCountryMatch(self):
    wp = {'icao': 'ABC', 'iata': 'ZZ', 'name': 'Åland Airlines', 'callsign': 'ZZZZZ', 'country': 'Åland', 'source': 'Wikidata'}
    self.assertOnlyChange(wp, diff={'callsign': 'ZZZZZ', 'iata': 'ZZ'})

  def testIcaoMatchBothCallsignsEmpty(self):
    old = {'icao': 'FZA', 'iata': '', 'name': 'Fuzhou Airlines', 'callsign': '', 'country': 'China', 'active': 'Y', 'source': 'Wikidata'}
    self.addToIndex(old)
    new = {'icao': 'FZA', 'iata': 'FU', 'name': 'Fuzhou Airlines', 'callsign': '', 'country': '', 'active': 'Y', 'source': 'Wikidata'}
    self.assertOnlyChange(new, old, diff={'iata': 'FU'})

  def testIcaoMatchNewCallsignEmpty(self):
    old = {'icao': 'OKA', 'iata': '', 'name': 'Okay Airways', 'callsign': 'OKAYJET', 'country': 'China', 'active': 'Y', 'source': 'Wikidata'}
    self.addToIndex(old)
    new = {'icao': 'OKA', 'iata': 'BK', 'name': 'Okay Airways', 'callsign': '', 'country': '', 'active': 'Y', 'source': 'Wikidata'}
    self.assertOnlyChange(new, old, diff={'iata': 'BK'})

  def testIcaoNeitherCallsignNorCountryMatch(self):
    wp = {'icao': 'ABC', 'iata': 'ZZ', 'name': 'Åland Airlines', 'callsign': 'ZZZZZ', 'country': 'ZZ', 'source': 'Wikidata'}
    self.assertNoMatches(wp)

  # IATA and callsign matches
  def testIataCallsignMatch(self):
    new = {'icao': 'ZZZ', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikidata'}
    self.assertOnlyChange(new, diff={'icao': 'ZZZ'})

  def testIataCallsignNotMatch(self):
    wp = {'icao': 'ZZZ', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ZZZZZ', 'country': 'ZZ', 'source': 'Wikidata'}
    self.assertNoMatches(wp)

# Dupe matching code broken, commented out for now

#  def testExactMatchWithIcaoMatchDupe(self):
#    dupe = {'icao': 'ABC', 'iata': 'AB', 'name': 'Zork Airlines', 'callsign': 'ZORK', 'country': 'Åland', 'source': 'Wikidata'}
#    self.addToIndex(dupe)
#
#    new = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikidata'}
#    self.assertEqual(self.ofa.match(new), (self.of, dupe))
#    self.assertEqual(self.ofa.diff(self.of, new), {})

#  def testExactMatchWithIcaoMatchDefunctDupe(self):
#    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikidata'}
#    dupe = {'icao': 'ABC', 'iata': 'AB', 'name': 'Zork Airlines', 'callsign': 'ZORK', 'country': 'Åland', 'active': 'Y', 'source': 'Wikidata'}
#    self.addToIndex(dupe)
#    self.assertEqual(self.ofa.match(wp), (dupe, self.of))
#    self.assertEqual(self.ofa.diff(self.of, wp), {})

#  def testExactMatchWithCallsignDupe(self):
#    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikidata'}
#    dupe = {'icao': '', 'iata': 'AB', 'name': 'Zork Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikidata'}
#    self.addToIndex(dupe)
#    self.assertEqual(self.ofa.match(wp), (self.of, dupe))
#    self.assertEqual(self.ofa.diff(self.of, wp), {})
#
#  def testExactMatchWithNameDupe(self):
#    dupe = {'icao': '', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': '', 'country': 'Åland', 'source': 'Wikidata'}
#    self.addToIndex(dupe)
#
#    new = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikidata'}
#    self.assertEqual(self.ofa.match(wp), (self.of, dupe))
#    self.assertEqual(self.ofa.diff(self.of, wp), {})

  # Rejected near-dupes
  def testExactMatchWithDifferentCountryNonDupe(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikidata'}
    dupe = {'icao': '', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'XA', 'source': 'Wikidata'}
    self.addToIndex(dupe)
    self.assertEqual(self.ofa.match(wp), (self.of, None))
    self.assertEqual(self.ofa.diff(self.of, wp), {})

  def testExactMatchWithDifferentCallsignNonDupe(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikidata'}
    dupe = {'icao': '', 'iata': 'AB', 'name': 'Zork Airlines', 'callsign': 'ZORKA', 'country': 'Åland', 'source': 'Wikidata'}
    self.addToIndex(dupe)
    self.assertEqual(self.ofa.match(wp), (self.of, None))
    self.assertEqual(self.ofa.diff(self.of, wp), {})

  def testExactMatchWithDifferentIcaoNonDupe(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikidata'}
    dupe = {'icao': 'DEF', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikidata'}
    self.addToIndex(dupe)
    self.assertEqual(self.ofa.match(wp), (self.of, None))
    self.assertEqual(self.ofa.diff(self.of, wp), {})

  def testExactMatchWithNameNonDupe(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikidata'}
    dupe = {'icao': '', 'iata': 'AB', 'name': 'Irrelevant Airlines', 'callsign': '', 'country': 'Åland', 'source': 'Wikidata'}
    self.addToIndex(dupe)
    self.assertEqual(self.ofa.match(wp), (self.of, None))
    self.assertEqual(self.ofa.diff(self.of, wp), {})

  #
  # Wikidata-specific tests
  #

  def testWikidataCollapseIdenticalDuplicates(self):
    wiki =  {'entity': 'http://www.wikidata.org/entity/Q3487216', 'airlineLabel': 'Abba Airlines', 'iata': 'TF,TF,TF', 'icao': 'ABC,ABC,ABC', 
             'parentIata': 'XX,XX,XX', 'parentIcao': 'XXX,XXX,XXX', 'callsign': '', 'countryIso': 'SE,SE,SE', 'startDate': '', 'endDate': ''}
    self.wd.parse([wiki])
    self.assertEqual(self.wd.airlines,
      [{'icao': 'ABC', 'iata': 'TF', 'name': 'Abba Airlines', 'callsign': '', 'country': 'Sweden', 'country_code': 'SE', 'active': 'N', 'start_date': '', 'end_date': '', 'source': 'Wikidata'}])

  # Choose HQ location for multinational airlines
  def testWikidataHqCountryOverridesCountryIso(self):
    wiki =  {'entity': 'http://www.wikidata.org/entity/Q3487216', 'airlineLabel': 'Abba Airlines', 'iata': 'TF,TF,TF', 'icao': 'ABC,ABC,ABC', 
             'countryIso': 'DK,NO,SE', 'hqCountryIso': 'SE', 'callsign': '', 'startDate': '', 'endDate': ''}
    self.wd.parse([wiki])
    self.assertEqual(self.wd.airlines,
      [{'icao': 'ABC', 'iata': 'TF', 'name': 'Abba Airlines', 'callsign': '', 'country': 'Sweden', 'country_code': 'SE', 'active': 'N', 'start_date': '', 'end_date': '', 'source': 'Wikidata'}])

  # Prefer subnational entities like Hong Kong, Faroe Islands over their "parent" countries
  def testWikidataHqIsoOverridesHqCountryIso(self):
    wiki =  {'entity': 'http://www.wikidata.org/entity/Q3487216', 'airlineLabel': 'Abba Airlines', 'iata': 'TF,TF,TF', 'icao': 'ABC,ABC,ABC', 
             'countryIso': 'DK', 'hqIso': 'HK', 'hqCountryIso': 'SE', 'callsign': '', 'startDate': '', 'endDate': ''}
    self.wd.parse([wiki])
    self.assertEqual(self.wd.airlines,
      [{'icao': 'ABC', 'iata': 'TF', 'name': 'Abba Airlines', 'callsign': '', 'country': 'Hong Kong', 'country_code': 'HK', 'active': 'N', 'start_date': '', 'end_date': '', 'source': 'Wikidata'}])

  def testWikidataActiveAirline(self):
    wiki =  {'entity': 'http://www.wikidata.org/entity/Q3487216', 'airlineLabel': 'Abba Airlines', 'iata': 'TF', 'icao': '', 'callsign': '', 'countryIso': 'SE', 'startDate': '2023-01-01', 'endDate': ''}
    self.wd.parse([wiki])
    self.assertEqual(self.wd.airlines,
      [{'icao': '', 'iata': 'TF', 'name': 'Abba Airlines', 'callsign': '', 'country': 'Sweden', 'country_code': 'SE', 'active': 'Y', 'start_date': '2023-01-01', 'end_date': '', 'source': 'Wikidata'}])

  def testWikidataInactiveAirline(self):
    wiki =  {'entity': 'http://www.wikidata.org/entity/Q3487216', 'airlineLabel': 'Abba Airlines', 'iata': 'TF', 'icao': '', 'callsign': '', 'countryIso': 'SE', 'startDate': '', 'endDate': '2023-01-01'}
    self.wd.parse([wiki])
    self.assertEqual(self.wd.airlines,
      [{'icao': '', 'iata': 'TF', 'name': 'Abba Airlines', 'callsign': '', 'country': 'Sweden', 'country_code': 'SE', 'active': 'N', 'start_date': '', 'end_date': '2023-01-01', 'source': 'Wikidata'}])

  def testWikidataDefaultToInactiveAirline(self):
    wiki =  {'entity': 'http://www.wikidata.org/entity/Q3487216', 'airlineLabel': 'Abba Airlines', 'iata': 'TF', 'icao': '', 'callsign': '', 'countryIso': 'SE', 'startDate': '', 'endDate': ''}
    self.wd.parse([wiki])
    self.assertEqual(self.wd.airlines,
      [{'icao': '', 'iata': 'TF', 'name': 'Abba Airlines', 'callsign': '', 'country': 'Sweden', 'country_code': 'SE', 'active': 'N', 'start_date': '', 'end_date': '', 'source': 'Wikidata'}])

  def testWikidataIgnoreIfNameEqualsEntity(self):
    wiki =  {'entity': 'http://www.wikidata.org/entity/Q3487216', 'airlineLabel': 'Q3487216', 'iata': 'TF', 'icao': '', 'callsign': '', 'countryLabel': 'Sweden', 'countryIso': 'SE', 'startDate': '', 'endDate': ''}
    self.wd.parse([wiki])
    self.assertEqual(self.wd.airlines, [])

  def testWikidataIgnoreIfSameIATAAsParent(self):
    wiki =  {'entity': 'http://www.wikidata.org/entity/Q3487216', 'airlineLabel': 'Abba Airlines', 'iata': 'TF', 'icao': '', 'parentIata': 'TF', 'callsign': '', 'countryLabel': 'Sweden', 'countryIso': 'SE', 'startDate': '2023-01-01', 'endDate': ''}
    self.wd.parse([wiki])
    self.assertEqual(self.wd.airlines, [])

  #
  # Processing tests
  #

  def testProcessAddAirlineWithIataNameAndCountry(self):
    stats = defaultdict(int)
    airlines = [defaultdict(iata='ZZ', name='New Airline', country_code='ZZ', source='Wikidata')]
    update_airlines.process(airlines, self.ofa, stats)
    self.assertEqual(stats, {'added': 1, 'total': 1})

  def testProcessAddAirlineWithInferredCountryCode(self):
    stats = defaultdict(int)
    airlines = [defaultdict(iata='ZZ', name='New Airline', country='Japan', source='Wikidata')]
    update_airlines.process(airlines, self.ofa, stats)
    self.assertEqual(stats, {'added': 1, 'total': 1})

  def testProcessIgnoreAirlinesMissingMinimalData(self):
    stats = defaultdict(int)
    airlines = [
      defaultdict(name='New Airline', country_code='BL', source='Wikidata'), # no code
      defaultdict(iata='ZZ', country_code='BL', source='Wikidata'),          # no name
      defaultdict(iata='ZZ', name='New Airline', source='Wikidata'),         # no country code
      defaultdict(iata='ZZ', name='New Airline', country_code='BL')          # no source
    ]
    update_airlines.process(airlines, self.ofa, stats)
    self.assertEqual(stats, {'total': 4})

if __name__ == '__main__':
    unittest.main()
