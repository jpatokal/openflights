# Process route data from http://www.virtualradarserver.co.uk/Files/FlightNumbers.csv
# Recreates 'schedules' and 'routes' tables, outputting list of unknown codes to stdout.

from collections import defaultdict
import csv
import mysql.connector
import operator
import sys

class DatabaseConnector(object):
  DEFAULT_DB = 'flightdb2'

  def __init__(self, db=None):
    if not db:
      db = self.DEFAULT_DB
    self.cnx = mysql.connector.connect(user='openflights', database=db, unix_socket='/tmp/mysql.sock')
    self.cnx.raise_on_warnings = True
    self.cursor = self.cnx.cursor()

class VRSParser(DatabaseConnector):
  def cache(self):
    self.airports = {}
    select_query = 'SELECT icao, iata, apid FROM airports'
    self.cursor.execute(select_query)
    for row in self.cursor:
      self.airports[row[0]] = {'iata': row[1], 'apid': row[2]}

    self.airlines = {}
    self.airlines_by_iata = {}
    select_query = 'SELECT icao, iata, alid FROM airlines'
    self.cursor.execute(select_query)
    for row in self.cursor:
      self.airlines[row[0]] = {'iata': row[1], 'alid': row[2]}
      self.airlines_by_iata[row[1]] = {'icao': row[0], 'alid': row[2]}

  def parse_schedules(self, filename):
    self.cursor.execute('TRUNCATE TABLE schedules')
    insert_query = 'INSERT INTO schedules(airline_iata, airline_icao, alid, flight, src_iata, src_icao, src_apid, dst_iata, dst_icao, dst_apid) VALUES(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)'
    unknown_codes = defaultdict(int)
    with open(filename, 'rb') as csvfile:
      reader = csv.reader(csvfile, delimiter=',')
      for row in reader:
        airports = row[2].split('-')
        for i in range(0, len(airports) - 1):
          if airports[i] != airports[i+1]:
            airline, flight, src_icao, dst_icao = row[0], row[1], airports[i], airports[i+1]
            try:
              if len(airline) == 3:
                airline_icao = airline
                airline_iata, alid = self.airlines[airline]['iata'], self.airlines[airline_icao]['alid']
              else:
                airline_iata = airline
                airline_icao, alid = self.airlines_by_iata[airline]['icao'], self.airlines_by_iata[airline]['alid']
              src_iata, src_apid = self.airports[src_icao]['iata'], self.airports[src_icao]['apid']
              dst_iata, dst_apid = self.airports[dst_icao]['iata'], self.airports[dst_icao]['apid']
              values = (airline_iata, airline_icao, alid, flight, src_iata, src_icao, src_apid, dst_iata, dst_icao, dst_apid)
              self.cursor.execute(insert_query, values)
            except KeyError, e:
              key = e.args[0]
              unknown_codes[key] += 1
    for code in sorted(unknown_codes.items(), key=operator.itemgetter(1)):
      print code

  def create_routes_table(self):
    self.cursor.execute('TRUNCATE TABLE routes')
    create_routes_query = """
    INSERT INTO
      routes(alid,airline_iata,airline_icao,
        src_apid,src_iata,src_icao,
        dst_apid,dst_iata,dst_icao,
        frequency)
    SELECT
      alid,airline_iata,airline_icao,
      src_apid,src_iata,src_icao,
      dst_apid,dst_iata,dst_icao,
      COUNT(*) AS frequency
    FROM schedules
    GROUP BY
      alid,airline_iata,airline_icao,
      src_apid,src_iata,src_icao,
      dst_apid,dst_iata,dst_icao;
    """
    self.cursor.execute(create_routes_query)

def main():
  parser = VRSParser()
  print >> sys.stderr, "Cache..."
  parser.cache()
  print >> sys.stderr, "Parse..."
  parser.parse_schedules('FlightNumbers.csv')
  print >> sys.stderr, "Map..."
  parser.create_routes_table()
  print >> sys.stderr, "Done!"

if __name__ == "__main__":
  main()
