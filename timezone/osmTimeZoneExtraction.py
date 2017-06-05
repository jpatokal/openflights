import pandas as pd
import os
from timezonefinder import TimezoneFinder

# Extraction of the data from OpenFlights
url = 'https://raw.githubusercontent.com/jpatokal/openflights/master/data/airports.dat'
airports = pd.read_csv(url,na_values=['\\N'])

airports.columns = ["AirportID","Name","City","Country","IATA","ICA","Latitude","Longitude",
                    "Altitude","Timezone","DST","Tz","Type","Source"]

# Extraction of Canadian airports
# print(airports.loc[(airports.Country == "Canada")])
# airportsCanada = airports.loc[(airports.Country == "Canada")]
# print(pd.DataFrame(airports,columns=["Latitude","Longitude"]))

# Using timezonefinder package that is using OSM as background reference
tf = TimezoneFinder()
mergedTz = list()
for i in pd.DataFrame(airports,columns=["Latitude","Longitude"]).iterrows():
    lng = i[1][1]
    lat = i[1][0]
    try:
        timezone_name = tf.timezone_at(lng=lng, lat=lat)
        if timezone_name is None:
            timezone_name = tf.closest_timezone_at(lng=lng, lat=lat)
    except ValueError:
        print("No timezone identified for " + lng + lat)
        timezone_name = None
    mergedTz.append(timezone_name)

airports = airports.assign(MergedTz=mergedTz)
divergence = airports.loc[(airports.MergedTz != airports.Tz)]
path = os.getcwd() + "\\..\\data\\divergence.csv"
divergence = divergence.dropna(subset=["Tz", "MergedTz"])
divergence.to_csv(path)

