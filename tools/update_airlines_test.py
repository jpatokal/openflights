# -*- coding: utf-8 -*-

import update_airlines

import argparse
import mock
import unittest

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
      'source': 'Wikipedia'
    }
    self.indexAirline(self.of)
    self.wpa = update_airlines.WikipediaArticle()

  def indexAirline(self, airline):
    self.ofa.of_iata[airline['iata']].append(airline)
    self.ofa.of_icao[airline['icao']].append(airline)

  def testClean(self):
    for input, output in [
      [""                 , None],
      [" "                , None],
      ["&mdash;"          , None],
      ["Foo"              , "Foo"],
      ["Foo*"             , "Foo"],
      ["Foo?"             , "Foo"],
      [" Foo "            , "Foo"],
      ["<i>Foo</i>"       , "Foo"],
      ["Foo<ref>1</ref>"  , "Foo"],
      ["[[Foo|Bar]]"      , "Bar"],
      ["| ''[[Foo|Bar]]''", "Bar"],
      ["Foo, S.A. de C.V.", "Foo"]
    ]:
      self.assertEqual(self.wpa.clean(input), output)

  # ICAO and callsign or country matches
  def testExactMatch(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikipedia'}
    self.assertEqual(self.ofa.match(wp), (self.of, None))
    self.assertEqual(self.ofa.diff(self.of, wp), {})

  def testExactMatchNewSource(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'IATA'}
    self.assertEqual(self.ofa.match(wp), (self.of, None))
    self.assertEqual(self.ofa.diff(self.of, wp), {'source': 'IATA'})

  def testExactMatchLessReliableSource(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Lol Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'User'}
    self.assertEqual(self.ofa.match(wp), (self.of, None))
    self.assertEqual(self.ofa.diff(self.of, wp), {})

  def testExactMatchLessReliableSourceUpdateNulls(self):
    self.of['callsign'] = ''
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Lol Airlines', 'callsign': 'NEWXA', 'country': 'Åland', 'source': 'User'}
    self.assertEqual(self.ofa.match(wp), (self.of, None))
    self.assertEqual(self.ofa.diff(self.of, wp), {'callsign': 'NEWXA'})

  def testNameChange(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Ahvenanmaa Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikipedia'}
    self.assertEqual(self.ofa.match(wp), (self.of, None))
    self.assertEqual(self.ofa.diff(self.of, wp), {'name': 'Ahvenanmaa Airlines'})

  def testCountryChange(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Ahvenanmaa', 'source': 'Wikipedia'}
    self.assertEqual(self.ofa.match(wp), (self.of, None))
    self.assertEqual(self.ofa.diff(self.of, wp), {'country': 'Ahvenanmaa'})

  def testCountryCodeChange(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'country_code': 'FI', 'source': 'Wikipedia'}
    self.assertEqual(self.ofa.match(wp), (self.of, None))
    self.assertEqual(self.ofa.diff(self.of, wp), {'country_code': 'FI'})

  def testIgnoreCaseChange(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland AIRLINES', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikipedia'}
    self.assertEqual(self.ofa.match(wp), (self.of, None))
    self.assertEqual(self.ofa.diff(self.of, wp), {})

  def testIgnoreNameAbbreviation(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'ALA', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikipedia'}
    self.assertEqual(self.ofa.match(wp), (self.of, None))
    self.assertEqual(self.ofa.diff(self.of, wp), {})

  def testIcaoIataMatch(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ZZZZZ', 'country': 'Åland', 'source': 'Wikipedia'}
    self.assertEqual(self.ofa.match(wp), (self.of, None))
    self.assertEqual(self.ofa.diff(self.of, wp), {'callsign': 'ZZZZZ'})

  def testIcaoCallsignMatch(self):
    wp = {'icao': 'ABC', 'iata': 'ZZ', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikipedia'}
    self.assertEqual(self.ofa.match(wp), (self.of, None))
    self.assertEqual(self.ofa.diff(self.of, wp), {'iata': 'ZZ'})

  def testIcaoCountryMatch(self):
    wp = {'icao': 'ABC', 'iata': 'ZZ', 'name': 'Åland Airlines', 'callsign': 'ZZZZZ', 'country': 'Åland', 'source': 'Wikipedia'}
    self.assertEqual(self.ofa.match(wp), (self.of, None))
    self.assertEqual(self.ofa.diff(self.of, wp), {'callsign': 'ZZZZZ', 'iata': 'ZZ'})

  def testIcaoMatchBothCallsignsEmpty(self):
    match = {'icao': 'FZA', 'iata': '', 'name': 'Fuzhou Airlines', 'callsign': '', 'country': 'China', 'active': 'Y', 'source': 'Wikipedia'}
    self.indexAirline(match)
    iata = {'icao': 'FZA', 'iata': 'FU', 'name': 'Fuzhou Airlines', 'callsign': '', 'country': '', 'active': 'Y', 'source': 'Wikipedia'}
    self.assertEqual(self.ofa.match(iata), (match, None))
    self.assertEqual(self.ofa.diff(match, iata), {'iata': 'FU'})

  def testIcaoMatchNewCallsignEmpty(self):
    match = {'icao': 'OKA', 'iata': '', 'name': 'Okay Airways', 'callsign': 'OKAYJET', 'country': 'China', 'active': 'Y', 'source': 'Wikipedia'}
    self.indexAirline(match)
    iata = {'icao': 'OKA', 'iata': 'BK', 'name': 'Okay Airways', 'callsign': '', 'country': '', 'active': 'Y', 'source': 'Wikipedia'}
    self.assertEqual(self.ofa.match(iata), (match, None))
    self.assertEqual(self.ofa.diff(match, iata), {'iata': 'BK'})

  def testIcaoNeitherCallsignNorCountryMatch(self):
    wp = {'icao': 'ABC', 'iata': 'ZZ', 'name': 'Åland Airlines', 'callsign': 'ZZZZZ', 'country': 'ZZ', 'source': 'Wikipedia'}
    self.assertEqual(self.ofa.match(wp), (None, None))

  # IATA and callsign matches
  def testIataCallsignMatch(self):
    wp = {'icao': 'ZZZ', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikipedia'}
    self.assertEqual(self.ofa.match(wp), (self.of, None))
    self.assertEqual(self.ofa.diff(self.of, wp), {'icao': 'ZZZ'})

  def testIataCallsignNotMatch(self):
    wp = {'icao': 'ZZZ', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ZZZZZ', 'country': 'ZZ', 'source': 'Wikipedia'}
    self.assertEqual(self.ofa.match(wp), (None, None))

  # Accepted duplicates
  def testExactMatchWithIcaoMatchDupe(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikipedia'}
    dupe = {'icao': 'ABC', 'iata': 'AB', 'name': 'Zork Airlines', 'callsign': 'ZORK', 'country': 'Åland', 'source': 'Wikipedia'}
    self.indexAirline(dupe)
    self.assertEqual(self.ofa.match(wp), (self.of, dupe))
    self.assertEqual(self.ofa.diff(self.of, wp), {})

  def testExactMatchWithIcaoMatchDefunctDupe(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikipedia'}
    dupe = {'icao': 'ABC', 'iata': 'AB', 'name': 'Zork Airlines', 'callsign': 'ZORK', 'country': 'Åland', 'active': 'Y', 'source': 'Wikipedia'}
    self.indexAirline(dupe)
    self.assertEqual(self.ofa.match(wp), (dupe, self.of))
    self.assertEqual(self.ofa.diff(self.of, wp), {})

  def testExactMatchWithCallsignDupe(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikipedia'}
    dupe = {'icao': '', 'iata': 'AB', 'name': 'Zork Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikipedia'}
    self.indexAirline(dupe)
    self.assertEqual(self.ofa.match(wp), (self.of, dupe))
    self.assertEqual(self.ofa.diff(self.of, wp), {})

  def testExactMatchWithNameDupe(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikipedia'}
    dupe = {'icao': '', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': '', 'country': 'Åland', 'source': 'Wikipedia'}
    self.indexAirline(dupe)
    self.assertEqual(self.ofa.match(wp), (self.of, dupe))
    self.assertEqual(self.ofa.diff(self.of, wp), {})

  # Rejected near-dupes
  def testExactMatchWithDifferentCountryNonDupe(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikipedia'}
    dupe = {'icao': '', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'XA', 'source': 'Wikipedia'}
    self.indexAirline(dupe)
    self.assertEqual(self.ofa.match(wp), (self.of, None))
    self.assertEqual(self.ofa.diff(self.of, wp), {})

  def testExactMatchWithDifferentCallsignNonDupe(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikipedia'}
    dupe = {'icao': '', 'iata': 'AB', 'name': 'Zork Airlines', 'callsign': 'ZORKA', 'country': 'Åland', 'source': 'Wikipedia'}
    self.indexAirline(dupe)
    self.assertEqual(self.ofa.match(wp), (self.of, None))
    self.assertEqual(self.ofa.diff(self.of, wp), {})

  def testExactMatchWithDifferentIcaoNonDupe(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikipedia'}
    dupe = {'icao': 'DEF', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikipedia'}
    self.indexAirline(dupe)
    self.assertEqual(self.ofa.match(wp), (self.of, None))
    self.assertEqual(self.ofa.diff(self.of, wp), {})

  def testExactMatchWithNameNonDupe(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': 'ALAXA', 'country': 'Åland', 'source': 'Wikipedia'}
    dupe = {'icao': '', 'iata': 'AB', 'name': 'Irrelevant Airlines', 'callsign': '', 'country': 'Åland', 'source': 'Wikipedia'}
    self.indexAirline(dupe)
    self.assertEqual(self.ofa.match(wp), (self.of, None))
    self.assertEqual(self.ofa.diff(self.of, wp), {})

if __name__ == '__main__':
    unittest.main()
