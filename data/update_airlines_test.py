import update_airlines

import argparse
import mock
import unittest

class UpdateAirlinesTest(unittest.TestCase):

  def setUp(self):
    self.fake_aldb = mock.Mock(spec=update_airlines.AirlineDB)
    self.ofa = update_airlines.OpenFlightsAirlines(self.fake_aldb)
    self.of = {'icao': 'ABC', 'iata': 'AB', 'name': 'Aland Airlines', 'callsign': 'ALAXA', 'country': 'AX'}
    self.ofa.of_iata['AB'] = [self.of]
    self.ofa.of_icao['ABC'] = [self.of]
    self.wpa = update_airlines.WikipediaArticle()

  def testClean(self):
    for input, output in [
      [""                 , None],
      ["Foo"              , "Foo"],
      ["[[Foo|Bar]]"      , "Bar"],
      ["| ''[[Foo|Bar]]''", "Bar"]      
    ]:
      self.assertEquals(self.wpa.clean(input), output)

  # ICAO and callsign or country matches
  def testExactMatch(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Aland Airlines', 'callsign': 'ALAXA', 'country': 'AX'}
    self.assertEquals(self.ofa.match(wp), self.of)
    self.assertEquals(self.ofa.diff(self.of, wp), {})
    self.fake_aldb.update_from_wp.assert_not_called()

  def testNameChange(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Ahvenanmaa Airlines', 'callsign': 'ALAXA', 'country': 'AX'}
    self.assertEquals(self.ofa.match(wp), self.of)
    self.assertEquals(self.ofa.diff(self.of, wp), {'name': 'Ahvenanmaa Airlines'})
    self.fake_aldb.update_from_wp.assert_called()

  def testIcaoIataMatch(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Aland Airlines', 'callsign': 'ZZZZZ', 'country': 'ZZ'}
    self.assertEquals(self.ofa.match(wp), self.of)
    self.assertEquals(self.ofa.diff(self.of, wp), {'callsign': 'ZZZZZ'})

  def testIcaoCallsignMatch(self):
    wp = {'icao': 'ABC', 'iata': 'ZZ', 'name': 'Aland Airlines', 'callsign': 'ALAXA', 'country': 'ZZ'}
    self.assertEquals(self.ofa.match(wp), self.of)
    self.assertEquals(self.ofa.diff(self.of, wp), {'iata': 'ZZ'})

  def testIcaoCountryMatch(self):
    wp = {'icao': 'ABC', 'iata': 'ZZ', 'name': 'Aland Airlines', 'callsign': 'ZZZZZ', 'country': 'AX'}
    self.assertEquals(self.ofa.match(wp), self.of)
    self.assertEquals(self.ofa.diff(self.of, wp), {'callsign': 'ZZZZZ', 'iata': 'ZZ'})

  def testIcaoNotMatch(self):
    wp = {'icao': 'ABC', 'iata': 'ZZ', 'name': 'Aland Airlines', 'callsign': 'ZZZZZ', 'country': 'ZZ'}
    self.assertEquals(self.ofa.match(wp), None)

  # IATA and callsign matches
  def testIataCallsignMatch(self):
    wp = {'icao': 'ZZZ', 'iata': 'AB', 'name': 'Aland Airlines', 'callsign': 'ALAXA', 'country': 'ZZ'}
    self.assertEquals(self.ofa.match(wp), self.of)
    self.assertEquals(self.ofa.diff(self.of, wp), {'icao': 'ZZZ'})

  def testIataNotMatch(self):
    wp = {'icao': 'ZZZ', 'iata': 'AB', 'name': 'Aland Airlines', 'callsign': 'ZZZZZ', 'country': 'ZZ'}
    self.assertEquals(self.ofa.match(wp), None)

if __name__ == '__main__':
    unittest.main()

