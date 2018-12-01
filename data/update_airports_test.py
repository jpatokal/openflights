import update_airports

import argparse
import mock
import unittest

class UpdateAirportsTest(unittest.TestCase):

  def setUp(self):
    self.fake_dbc = mock.Mock(spec=update_airports.DatabaseConnector)
    self.ofd = update_airports.OpenFlightsData(countries={'AX': 'Aland'})

  def testClosed(self):
    oa = {'type': 'closed'}
    self.assertEquals(self.ofd.match(None, oa), None)

  def testExactMatch(self):
    self.ofd.icao['ABCD'] = {'iata': 'ABC', 'apid': 42}
    oa = {'type': 'medium_airport', 'iata_code': 'ABC', 'ident': 'ABCD', 'iso_country': 'AX', 'name': 'Ayebeesee Intl Airport'}
    self.assertEquals(self.ofd.match(self.fake_dbc, oa), None)
    self.fake_dbc.update_all_from_oa.assert_called()

if __name__ == '__main__':
    unittest.main()
