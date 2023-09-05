# -*- coding: utf-8 -*-

import update_airlines

import argparse
import unittest
import unittest.mock as mock

class UpdateAirlinesTest(unittest.TestCase):
  def setUp(self):
    self.fake_aldb = mock.Mock(spec=update_airlines.AirlineDB)
    self.ofa = update_airlines.OpenFlightsAirlines(self.fake_aldb)
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

  def testNameChange(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Ahvenanmaa Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikidata'}
    self.assertOnlyChange(wp, diff={'name': 'Ahvenanmaa Airlines'})

  def testCountryChange(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Ahvenanmaa', 'source': 'Wikidata'}
    self.assertOnlyChange(wp, diff={'country': 'Ahvenanmaa'})

  def testCountryCodeChange(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'country_code': 'FI', 'source': 'Wikidata'}
    self.assertOnlyChange(wp, diff={'country_code': 'FI'})

  def testActiveToInactiveChange(self):
    self.of['active'] = 'Y'
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Ahvenanmaa Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikidata', 'active': 'N', 'end_date': '2019-01-01'}
    self.assertOnlyChange(wp, diff={'name': 'Ahvenanmaa Airlines', 'active': 'N', 'end_date': '2019-01-01'})

  def testIgnoreInactiveToActiveChange(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Ahvenanmaa Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikidata', 'active': 'N', 'end_date': '2019-01-01'}
    self.assertOnlyChange(wp, diff={'name': 'Ahvenanmaa Airlines', 'end_date': '2019-01-01'})

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

if __name__ == '__main__':
    unittest.main()
