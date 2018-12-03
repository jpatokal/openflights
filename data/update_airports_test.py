import update_airports

import argparse
import mock
import unittest

class UpdateAirportsTest(unittest.TestCase):

  def setUp(self):
    self.fake_dbc = mock.Mock(spec=update_airports.DatabaseConnector)
    self.ofd = update_airports.OpenFlightsData(countries={'AX': 'Aland'})
    self.ofd.icao['ABCD'] = {'iata': 'ABC', 'apid': 42}

  def testClosed(self):
    oa = {'type': 'closed'}
    self.assertEquals(self.ofd.match(None, oa), False)

  def testExactMatch(self):
    oa = {'type': 'medium_airport', 'iata_code': 'ABC', 'ident': 'ABCD', 'iso_country': 'AX', 'name': 'Ayebeesee Intl Airport'}
    self.assertEquals(self.ofd.match(self.fake_dbc, oa), True)
    self.fake_dbc.update_all_from_oa.assert_called()

  def testIcaoMatchEmptyIata(self):
    oa = {'type': 'medium_airport', 'iata_code': '', 'ident': 'ABCD', 'iso_country': 'AX', 'name': 'Ayebeesee Intl Airport'}
    self.assertEquals(self.ofd.match(self.fake_dbc, oa), True)
    self.fake_dbc.update_all_from_oa.assert_called()

  def testIcaoMatchJunkIata(self):
    oa = {'type': 'medium_airport', 'iata_code': '123', 'ident': 'ABCD', 'iso_country': 'AX', 'name': 'Ayebeesee Intl Airport'}
    self.assertEquals(self.ofd.match(self.fake_dbc, oa), True)
    self.fake_dbc.update_all_from_oa.assert_called()

  def testIcaoMatchDifferentIataHasDupe(self):
    self.ofd.iata['DUP'] = {'apid': 69, 'iata': 'DUP', 'name': 'Ayebeesee Intl Airport'}
    oa = {'type': 'medium_airport', 'iata_code': 'DUP', 'ident': 'ABCD', 'iso_country': 'AX', 'name': 'Ayebeesee Intl Airport'}
    self.assertEquals(self.ofd.match(self.fake_dbc, oa), True)
    self.fake_dbc.update_all_from_oa.assert_called()
    self.fake_dbc.move_iata_to_new_airport.assert_called_with('DUP', 69, 42)

  def testIcaoMatchDifferentIataNoDupe(self):
    oa = {'type': 'medium_airport', 'iata_code': 'DUP', 'ident': 'ABCD', 'iso_country': 'AX', 'name': 'Ayebeesee Intl Airport'}
    self.assertEquals(self.ofd.match(self.fake_dbc, oa), True)
    self.fake_dbc.update_all_from_oa.assert_called()
    self.fake_dbc.move_iata_to_new_airport.assert_not_called()


if __name__ == '__main__':
    unittest.main()
