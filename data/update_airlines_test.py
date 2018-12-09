import update_airlines

import argparse
import mock
import unittest

class UpdateAirlinesTest(unittest.TestCase):

  def setUp(self):
    self.fake_aldb = mock.Mock(spec=update_airlines.AirlineDB)
    self.ofa = update_airlines.OpenFlightsAirlines()
    self.sample = {'icao': 'ABC', 'iata': 'AB', 'name': 'Aland Airlines', 'callsign': 'ALAXA', 'country': 'AX'}
    self.ofa.of_iata['AB'] = [self.sample]
    self.ofa.of_icao['ABC'] = [self.sample]

    self.wpa = update_airlines.WikipediaArticle()

  def testWikiSyntaxClean(self):
    for input, output in [
      [""                 , None],
      ["Foo"              , "Foo"],
      ["[[Foo|Bar]]"      , "Bar"],
      ["| ''[[Foo|Bar]]''", "Bar"]      
    ]:
      self.assertEquals(self.wpa.clean(input), output)

  def testExactMatch(self):
    wp = {'icao': 'ABC', 'iata': 'AB', 'name': 'Aland Airlines', 'callsign': 'ALAXA', 'country': 'AX'}
    self.assertEquals(self.ofa.match(wp), self.sample)

if __name__ == '__main__':
    unittest.main()
