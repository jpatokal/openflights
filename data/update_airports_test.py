import update_airports

import unittest

class UpdateAirportsTest(unittest.TestCase):

  def testClosed(self):
    oa = {'type': 'closed'}
    self.assertEquals(update_airports.match(None, oa), None)

if __name__ == '__main__':
    unittest.main()
