BEGIN { FS = "\t" }
{
  split($2,n," ")
  name = ""
  for(i in n) {
    name = name " " substr(n[i],1,1) tolower(substr(n[i],2))
  }
  name = substr(name,2)
  icao = $4
  if(icao == "KZ") icao = $5
  faa = $5
  if(faa == "N") faa = ""
  # Country code, Name, ICAO, FAA, Longitude (X), Latitude (Y), Elevation (ft)
  printf("%2.2s,%s,%s,%s,%s,%s,%s\n",$1,name,icao,faa,$11,$9,$12);
}

