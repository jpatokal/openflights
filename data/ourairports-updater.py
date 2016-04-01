import mysql.connector
import csv

class DatabaseConnector(object):
  DEFAULT_DB = 'flightdb2'

  def __init__(self, db=None):
    if not db:
      db = self.DEFAULT_DB
    self.cnx = mysql.connector.connect(user='openflights', database=db, unix_socket='/tmp/mysql.sock')
    self.cnx.raise_on_warnings = True
    self.cursor = self.cnx.cursor(dictionary=True)

def find_airport_sql():
  return """
SELECT * FROM airports WHERE icao=%s
"""

dbc = DatabaseConnector()
with open('ourairports.csv', 'rb') as csvfile:
  reader = csv.DictReader(csvfile)
  for oa in reader:
    if oa['type'] in ['medium_airport', 'large_airport']:
      dbc.cursor.execute(find_airport_sql(), (oa['ident'], ))
      of = dbc.cursor.fetchone()
      if of:
        if of['iata'] != oa['iata_code']:
          print "IATA mismatch %s != %s" % (of['iata'], oa['iata_code'])
      else:
        print "NOMATCH"
