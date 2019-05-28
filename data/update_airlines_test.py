# -*- coding: utf-8 -*-

import update_airlines

import argparse
import mock
import unittest

class UpdateAirlinesTest(unittest.TestCase):
  def setUp(self):
    self.fake_aldb = mock.Mock(spec=update_airlines.AirlineDB)
    self.ofa = update_airlines.OpenFlightsAirlines(self.fake_aldb)
    self.of = {'icao': 'ABC', 'iata': 'AB', 'name': 'Aland Airlines', 'callsign': 'ALAXA', 'country': 'AX', 'active': 'N'}
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
      self.assertEquals(self.wpa.clean(input), output)

  # ICAO and callsign or country matches
  def testExactMatch(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Aland Airlines', 'callsign': 'ALAXA', 'country': 'AX'}
    self.assertEquals(self.ofa.match(wp), (self.of, None))
    self.assertEquals(self.ofa.diff(self.of, wp), {})

  def testNameChange(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Ahvenanmaa Airlines', 'callsign': 'ALAXA', 'country': 'AX'}
    self.assertEquals(self.ofa.match(wp), (self.of, None))
    self.assertEquals(self.ofa.diff(self.of, wp), {'name': 'Ahvenanmaa Airlines'})

  def testIgnoreCaseChange(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'ALAND AIRLINES', 'callsign': 'ALAXA', 'country': 'AX'}
    self.assertEquals(self.ofa.match(wp), (self.of, None))
    self.assertEquals(self.ofa.diff(self.of, wp), {})

  def testIgnoreNameAbbreviation(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'ALA', 'callsign': 'ALAXA', 'country': 'AX'}
    self.assertEquals(self.ofa.match(wp), (self.of, None))
    self.assertEquals(self.ofa.diff(self.of, wp), {})

  def testIcaoIataMatch(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Aland Airlines', 'callsign': 'ZZZZZ', 'country': 'ZZ'}
    self.assertEquals(self.ofa.match(wp), (self.of, None))
    self.assertEquals(self.ofa.diff(self.of, wp), {'callsign': 'ZZZZZ'})

  def testIcaoCallsignMatch(self):
    wp = {'icao': 'ABC', 'iata': 'ZZ', 'name': 'Aland Airlines', 'callsign': 'ALAXA', 'country': 'ZZ'}
    self.assertEquals(self.ofa.match(wp), (self.of, None))
    self.assertEquals(self.ofa.diff(self.of, wp), {'iata': 'ZZ'})

  def testIcaoCountryMatch(self):
    wp = {'icao': 'ABC', 'iata': 'ZZ', 'name': 'Aland Airlines', 'callsign': 'ZZZZZ', 'country': 'AX'}
    self.assertEquals(self.ofa.match(wp), (self.of, None))
    self.assertEquals(self.ofa.diff(self.of, wp), {'callsign': 'ZZZZZ', 'iata': 'ZZ'})

  def testIcaoMatchBothCallsignsEmpty(self):
    match = {'icao': 'FZA', 'iata': '', 'name': 'Fuzhou Airlines', 'callsign': '', 'country': 'China'}
    self.indexAirline(match)
    iata = {'icao': 'FZA', 'iata': 'FU', 'name': 'Fuzhou Airlines', 'callsign': '', 'country': ''}
    self.assertEquals(self.ofa.match(iata), (match, None))
    self.assertEquals(self.ofa.diff(match, iata), {'iata': 'FU'})

  def testIcaoMatchNewCallsignEmpty(self):
    match = {'icao': 'OKA', 'iata': '', 'name': 'Okay Airways', 'callsign': 'OKAYJET', 'country': 'China'}
    self.indexAirline(match)
    iata = {'icao': 'OKA', 'iata': 'BK', 'name': 'Okay Airways', 'callsign': '', 'country': ''}
    self.assertEquals(self.ofa.match(iata), (match, None))
    self.assertEquals(self.ofa.diff(match, iata), {'iata': 'BK'})

  def testIcaoNotMatch(self):
    wp = {'icao': 'ABC', 'iata': 'ZZ', 'name': 'Aland Airlines', 'callsign': 'ZZZZZ', 'country': 'ZZ'}
    self.assertEquals(self.ofa.match(wp), (None, None))

  # IATA and callsign matches
  def testIataCallsignMatch(self):
    wp = {'icao': 'ZZZ', 'iata': 'AB', 'name': 'Aland Airlines', 'callsign': 'ALAXA', 'country': 'ZZ'}
    self.assertEquals(self.ofa.match(wp), (self.of, None))
    self.assertEquals(self.ofa.diff(self.of, wp), {'icao': 'ZZZ'})

  def testIataNotMatch(self):
    wp = {'icao': 'ZZZ', 'iata': 'AB', 'name': 'Aland Airlines', 'callsign': 'ZZZZZ', 'country': 'ZZ'}
    self.assertEquals(self.ofa.match(wp), (None, None))

  # Accepted duplicates
  def testExactMatchWithIcaoMatchDupe(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Aland Airlines', 'callsign': 'ALAXA', 'country': 'AX'}
    dupe = {'icao': 'ABC', 'iata': 'AB', 'name': 'Zork Airlines', 'callsign': 'ZORK', 'country': 'AX'}
    self.indexAirline(dupe)
    self.assertEquals(self.ofa.match(wp), (self.of, dupe))
    self.assertEquals(self.ofa.diff(self.of, wp), {})

  def testExactMatchWithIcaoMatchDefunctDupe(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Aland Airlines', 'callsign': 'ALAXA', 'country': 'AX'}
    dupe = {'icao': 'ABC', 'iata': 'AB', 'name': 'Zork Airlines', 'callsign': 'ZORK', 'country': 'AX', 'active': 'Y'}
    self.indexAirline(dupe)
    self.assertEquals(self.ofa.match(wp), (dupe, self.of))
    self.assertEquals(self.ofa.diff(self.of, wp), {})

  def testExactMatchWithCallsignDupe(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Aland Airlines', 'callsign': 'ALAXA', 'country': 'AX'}
    dupe = {'icao': '', 'iata': 'AB', 'name': 'Zork Airlines', 'callsign': 'ALAXA', 'country': 'AX'}
    self.indexAirline(dupe)
    self.assertEquals(self.ofa.match(wp), (self.of, dupe))
    self.assertEquals(self.ofa.diff(self.of, wp), {})

  def testExactMatchWithNameDupe(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Aland Airlines', 'callsign': 'ALAXA', 'country': 'AX'}
    dupe = {'icao': '', 'iata': 'AB', 'name': 'Åland Airlines', 'callsign': '', 'country': 'AX'}
    self.indexAirline(dupe)
    self.assertEquals(self.ofa.match(wp), (self.of, dupe))
    self.assertEquals(self.ofa.diff(self.of, wp), {})

  # Rejected near-dupes
  def testExactMatchWithDifferentCountryNonDupe(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Aland Airlines', 'callsign': 'ALAXA', 'country': 'AX'}
    dupe = {'icao': '', 'iata': 'AB', 'name': 'Aland Airlines', 'callsign': 'ALAXA', 'country': 'XA'}
    self.indexAirline(dupe)
    self.assertEquals(self.ofa.match(wp), (self.of, None))
    self.assertEquals(self.ofa.diff(self.of, wp), {})

  def testExactMatchWithDifferentCallsignNonDupe(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Aland Airlines', 'callsign': 'ALAXA', 'country': 'AX'}
    dupe = {'icao': '', 'iata': 'AB', 'name': 'Zork Airlines', 'callsign': 'ZORKA', 'country': 'AX'}
    self.indexAirline(dupe)
    self.assertEquals(self.ofa.match(wp), (self.of, None))
    self.assertEquals(self.ofa.diff(self.of, wp), {})

  def testExactMatchWithDifferentIcaoNonDupe(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Aland Airlines', 'callsign': 'ALAXA', 'country': 'AX'}
    dupe = {'icao': 'DEF', 'iata': 'AB', 'name': 'Aland Airlines', 'callsign': 'ALAXA', 'country': 'AX'}
    self.indexAirline(dupe)
    self.assertEquals(self.ofa.match(wp), (self.of, None))
    self.assertEquals(self.ofa.diff(self.of, wp), {})

  def testExactMatchWithNameNonDupe(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Aland Airlines', 'callsign': 'ALAXA', 'country': 'AX'}
    dupe = {'icao': '', 'iata': 'AB', 'name': 'Irrelevant Airlines', 'callsign': '', 'country': 'AX'}
    self.indexAirline(dupe)
    self.assertEquals(self.ofa.match(wp), (self.of, None))
    self.assertEquals(self.ofa.diff(self.of, wp), {})

if __name__ == '__main__':
    unittest.main()
