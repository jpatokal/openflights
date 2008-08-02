BEGIN { FS = ":" }
{
  split($3,n," ")
  name = ""
  for(i in n) {
    name = name " " substr(n[i],1,1) tolower(substr(n[i],2))
  }
  name = substr(name,2)

  split($4,n," ")
  city = ""
  for(i in n) {
    city = city " " substr(n[i],1,1) tolower(substr(n[i],2))
  }
  city = substr(city,2)

  split($5,n," ")
  country = ""
  for(i in n) {
    country = country " " substr(n[i],1,1) tolower(substr(n[i],2))
  }
  country = substr(country,2)

  icao = $4
  if(icao == "KZ") icao = $5
  faa = $5
  if(faa == "N") faa = ""

  y = $6 + ($7/60) + ($8/3600);
  if ($9 == "S") y = -y;
  x = $10 + ($11/60) + ($12/3600);
  if ($13 == "U") x = -x;

  # ICAO, IATA, Name, City, Country, Longitude (X), Latitude (Y), Elevation (ft)
  printf("%s,%s,%s,%s,%s,%s,%s,%s\n",$1,$2,name,city,country,x,y,$14);
}

