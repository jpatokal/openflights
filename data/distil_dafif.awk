BEGIN { FS = "\t" }
{
  split($2,n," ")
  name = ""
  for(i in n) {
    name = name " " substr(n[i],1,1) tolower(substr(n[i],2))
  }
  name = substr(name,2)
  icao = $4
  if(length(icao) == 2 && $5 != "N") {
    icao = $5
    faa = ""
  } else {
    faa = $5
    if(faa == "N") faa = ""
  }
  if(length(faa) > 3) faa = substr(faa,2)
  # Country code, Name, ICAO, FAA, Longitude (X), Latitude (Y), Elevation (ft)
  printf("%2.2s,%s,%s,%s,%s,%s,%s\n",$1,name,icao,faa,$11,$9,$12);
}

