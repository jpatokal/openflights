import mysql.connector

class DatabaseConnector(object):
  DB = 'flightdb2'

  def __init__(self, args=None):
    if args.local:
      host = 'localhost'
      pw = None
    else:
      host = '104.197.15.255'
      with open('../sql/db.pw','r') as f:
        pw = f.read().strip()

    self.read_cnx = self.connect(host, pw)
    self.cursor = self.read_cnx.cursor(dictionary=True)
    self.write_cnx = self.connect(host, pw)
    self.write_cursor = self.write_cnx.cursor(dictionary=True)
    self.args = args
    self.alid = 1000000  # dummy ID for in-memory testing

  def connect(self, host, pw):
    cnx = mysql.connector.connect(user='openflights', database=self.DB, host=host, password=pw)
    cnx.raise_on_warnings = True
    return cnx

  def safe_execute(self, sql, params):
    if self.args.live_run:
      self.write_cursor.execute(sql, params, )
      print(".. %s : %d rows updated" % (sql % params, self.write_cursor.rowcount))
      self.write_cnx.commit()
      return self.write_cursor.lastrowid
    else:
      try:
        print(sql % params)
      except TypeError as err:
        print('TypeError', err)
        print('SQL', sql)
        print('Params', params)
        exit()

      self.alid += 1
      return self.alid
