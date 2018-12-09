import update_airports

import argparse
import mock
import unittest

class UpdateAirportsTest(unittest.TestCase):

  def setUp(self):
    self.fake_dbc = mock.Mock(spec=update_airports.DatabaseConnector)
    self.ofd = update_airports.OpenFlightsData(countries={'AX': 'Aland'})
    self.ofd.icao['ABCD'] = {'iata': 'ABC', 'apid': 42}

  # Test cases that do nothing

  def testClosed(self):
    oa = {'type': 'closed'}
    self.assertEquals(self.ofd.match(None, oa), False)

  def testNewSmallAirport(self):
    oa = {'type': 'small_airport', 'ident': 'NEWA'}
    self.assertEquals(self.ofd.match(None, oa), False)

  # Test cases that update OpenFlights from OurAirports

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

  def testIcaoMatchDifferentIataNoDupe(self):
    oa = {'type': 'medium_airport', 'iata_code': 'DUP', 'ident': 'ABCD', 'iso_country': 'AX', 'name': 'Ayebeesee Intl Airport'}
    self.assertEquals(self.ofd.match(self.fake_dbc, oa), True)
    self.fake_dbc.update_all_from_oa.assert_called()
    self.fake_dbc.move_iata_to_new_airport.assert_not_called()

  def testNewMatchingDupeIataHasIcao(self):
    oa = {'type': 'medium_airport', 'ident': 'NEWA', 'iata_code': 'DUP', 'local_code': '', 'iso_country': 'AX', 'name': 'Ayebeesee Intl Airport'}
    self.ofd.iata['DUP'] = {'apid': 69, 'iata': 'DUP', 'icao': 'NEWB', 'name': 'Ayebeesee Intl Airport'}
    self.assertEquals(self.ofd.match(self.fake_dbc, oa), True)
    self.fake_dbc.dealloc_iata.assert_not_called()
    self.fake_dbc.update_all_from_oa.assert_called_with(69, oa)

  def testNewNonMatchingDupeIataNoIcao(self):
    oa = {'type': 'medium_airport', 'ident': 'NEWA', 'iata_code': 'DUP', 'local_code': '', 'iso_country': 'AX', 'name': 'Ayebeesee Intl Airport'}
    self.ofd.iata['DUP'] = {'apid': 69, 'iata': 'DUP', 'icao': '', 'name': 'Ayebeesee Intl Airport'}
    self.assertEquals(self.ofd.match(self.fake_dbc, oa), True)
    self.fake_dbc.update_all_from_oa.assert_called_with(69, oa)

  # Test cases that merge dupes and update

  def testIcaoMatchDifferentIataHasDupe(self):
    self.ofd.iata['DUP'] = {'apid': 69, 'iata': 'DUP', 'name': 'Ayebeesee Intl Airport'}
    oa = {'type': 'medium_airport', 'iata_code': 'DUP', 'ident': 'ABCD', 'iso_country': 'AX', 'name': 'Ayebeesee Intl Airport'}
    self.assertEquals(self.ofd.match(self.fake_dbc, oa), True)
    self.fake_dbc.update_all_from_oa.assert_called()
    self.fake_dbc.move_iata_to_new_airport.assert_called_with('DUP', 69, 42)

  # Test cases that create new airports

  def testNewIcaoOnly(self):
    oa = {'type': 'medium_airport', 'ident': 'NEWA', 'iata_code': '', 'local_code': '', 'iso_country': 'AX', 'name': 'Ayebeesee Intl Airport'}
    self.assertEquals(self.ofd.match(self.fake_dbc, oa), True)
    self.fake_dbc.create_new_from_oa.assert_called_with(oa)

  def testNewNonMatchingDupeFAA(self):
    oa = {'type': 'medium_airport', 'ident': 'NEWA', 'iata_code': 'DUP', 'local_code': 'DUP', 'iso_country': 'AX', 'name': 'Ayebeesee Intl Airport'}
    self.ofd.iata['DUP'] = {'apid': 69, 'iata': 'DUP', 'icao': 'OLDA', 'name': 'Ayebeesee Intl Airport'}
    self.assertEquals(self.ofd.match(self.fake_dbc, oa), True)
    self.fake_dbc.dealloc_iata.assert_not_called()
    self.fake_dbc.create_new_from_oa.assert_called_with(oa)

  # Test cases that deallocate an existing IATA and move it to a new entry

  def testNewNonMatchingDupeIata(self):
    oa = {'type': 'medium_airport', 'ident': 'NEWA', 'iata_code': 'DUP', 'local_code': '', 'iso_country': 'AX', 'name': 'Ayebeesee Intl Airport'}
    self.ofd.iata['DUP'] = {'apid': 69, 'iata': 'DUP', 'icao': 'OLDA', 'name': 'Ayebeesee Intl Airport'}
    self.assertEquals(self.ofd.match(self.fake_dbc, oa), True)
    self.fake_dbc.dealloc_iata.assert_called_with(69)
    self.fake_dbc.create_new_from_oa.assert_called_with(oa)

if __name__ == '__main__':
    unittest.main()
