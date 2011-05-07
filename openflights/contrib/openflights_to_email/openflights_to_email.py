#    Openflights-to-email - A script to read flight data from 
#    openflights.org and send an email notice of todays flights. 
#
#    Copyright 2011 Niko Kotilainen - (http://kotilainen.eu)
#
#    This program is free software: you can redistribute it and/or modify
#    it under the terms of the GNU General Public License as published by
#    the Free Software Foundation, either version 3 of the License, or
#    (at your option) any later version.
#
#    This program is distributed in the hope that it will be useful,
#    but WITHOUT ANY WARRANTY; without even the implied warranty of
#    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#    GNU General Public License for more details.
#
#    You should have received a copy of the GNU General Public License
#    along with this program.  If not, see <http://www.gnu.org/licenses/>.
#

# Requires httplib2:
# http://code.google.com/p/httplib2/

import csv
import datetime
import httplib2
import urllib
from email.mime.text import MIMEText
from hashlib import md5
from smtplib import SMTP


openflights_username="<USERNAME>"
openflights_pass_hash = md5(openflights_username + "<PASSWORD>").hexdigest()

sender_address = "sender@example.com"
dest_address   = "destination@example.com"
smtp_server    = "some.smtp.server.example.com"


def send_email(text):
  msg = MIMEText(text, 'html')
  msg['Subject'] = 'Flight information.'
  s = SMTP(smtp_server)
  s.sendmail(sender_address, [dest_address], msg.as_string())
  s.quit()

def get_ap_string(iata):
  airports = csv.reader(open("../../data/airports.dat"))
  for line in airports:
    if line[4] == iata:
      return "<b>%s, %s</b>" % (line[2], line[3])

def get_openflights_csv(username, pass_hash):
  # Login to openflights.org
  http = httplib2.Http()
  headers = {'Content-type': 'application/x-www-form-urlencoded'}
  url = "http://openflights.org/php/"
  response, content = http.request(url+"map.php", 'POST', headers=headers)
  headers['Cookie'] = response['set-cookie']
  challenge = content.split("\n")[0].split(";")[-1]
  #print headers['Cookie'], "challenge", challenge
  hash = md5(challenge + pass_hash).hexdigest()
  #print "hash", hash
  body = {'name': username, 'pw': hash}
  response, content = http.request(url+"login.php", 'POST', headers=headers, body=urllib.urlencode(body))

  # Export flights in CSV format
  body = {'export': 'export'}
  response, content = http.request(url+"flights.php?export=export", 'GET', headers=headers)
  return csv.reader(content.split("\n"))

def construct_email(flights_csv):
  now = datetime.datetime.now()
  today = "%d-%02d-%02d" %(now.year, now.month, now.day)
  #today = "2010-12-28"

  # Pick todays flights  
  flights_today = []
  for line in flights_csv:
    #print line
    if line[0].startswith(today):
      frAp, toAp = line[1], line[2]
      airline = line[4]
      distance = int(line[5])
      flights_today = [get_ap_string(frAp) + " to " + get_ap_string(toAp) + " on " + airline] + flights_today

  # Construct message
  message = "Flying today from \n"
  if len(flights_today) == 1:
    message += flights_today[0]
    message += "."
    return message
  
  elif len(flights_today) > 1:
    message += "<br><ul><li>"
  #  flights = ["<li>%s</li" %  x for x in flights]
    message += ", and</li>\n<li>".join(flights_today)
    message += "</li></ul>"
    return message
  else:
    return None  

  
if __name__ == "__main__":
  flights_csv = get_openflights_csv(openflights_username, openflights_pass_hash)
  email = construct_email(flights_csv)
  if email:
    print email
    send_email(email)
