import csv
import sys

reader = csv.reader(sys.stdin, delimiter=',')
for row in reader:
  airports = row[2].split('-')
  for i in range(0, len(airports) - 1):
    if airports[i] != airports[i+1]:
      print '%s,%s,%s,%s' % (row[0], row[1], airports[i], airports[i+1])
