### RStudio: -*- coding: utf-8 -*-
##
## Copyright (C) 2017 David Beauchemin, Samuel Cabral Cruz, Vincent Goulet
##
## This file is part of the project 
## "Introduction a R - Atelier du colloque R a Quebec 2017"
## http://github.com/vigou3/raquebec-atelier-introduction-r
##
## The creation is made available according to the license
## Attribution-Sharing in the same conditions 4.0
## of Creative Commons International
## http://creativecommons.org/licenses/by-sa/4.0/

(path <- paste(getwd(),"../..",sep = "/"))

# Extraction of airports.dat 
airports <- read.csv("https://raw.githubusercontent.com/jpatokal/openflights/master/data/airports.dat",
                     header = FALSE, na.strings=c("\\N",""))
colnames(airports) <- c("airportID", "name", "city", "country", "IATA", "ICAO",
                        "latitude", "longitude", "altitude", "timezone", "DST",
                        "tzFormat","typeAirport","Source")


# Initial Proportion of missing tzFormat
length(airports$tzFormat[is.na(airports$tzFormat)])/length(airports$tzFormat)

# We use ShapeFile of TZ_world to fill the missing values
# install.packages("sp")		
# install.packages("rgdal")		
library(sp)		
library(rgdal)		
obs <- subset(airports,select = c("airportID","name","longitude","latitude"))
sppts <- SpatialPoints(subset(obs,select = c("longitude","latitude")))		
proj4string(sppts) <- CRS("+proj=longlat")		

tz_world_1.shape <- readOGR(dsn=paste(path,"/ref/tz_world_1",sep=""),layer="tz_world")	
sppts <- spTransform(sppts, proj4string(tz_world_1.shape))
merged_tz_1 <- cbind(obs,over(sppts,tz_world_1.shape))
sum(merged_tz_1$TZID == "uninhabited",na.rm = TRUE)
subset(merged_tz_1,TZID == "uninhabited")
is.na(merged_tz_1) <- merged_tz_1 == "uninhabited"
sum(merged_tz_1$TZID == "uninhabited",na.rm = TRUE)

tz_world_2.shape <- readOGR(dsn=paste(path,"/ref/tz_world_2",sep=""),layer="combined_shapefile")	
sppts <- spTransform(sppts, proj4string(tz_world_2.shape))
merged_tz_2 <- cbind(obs,over(sppts,tz_world_2.shape))

# install.packages("sqldf")		
library(sqldf)		
airports <- sqldf("select 		
                   a.*,
                   b.TZID as tzMerged_1,
                   c.TZID as tzMerged_2
                   from airports a 		
                   left join merged_tz_1 b
                   on a.airportID = b.airportID
                   left join merged_tz_2 c
                   on a.airportID = c.airportID
                   order by a.airportID")
airports <- as.data.frame(as.matrix(airports))
summary(airports)
names(airports)

# Verification with available time zones 
# Test 1
test1 <- subset(airports, !is.na(tzFormat) & !is.na(tzMerged_1))
sum(paste(test1$tzFormat) == paste(test1$tzMerged_1))/length(test1$tzFormat)
# Test 2
test2 <- subset(airports, !is.na(tzFormat) & !is.na(tzMerged_2))
sum(paste(test2$tzFormat) == paste(test2$tzMerged_2))/length(test2$tzFormat)
# Test 3
test3 <- subset(airports, !is.na(tzMerged_1) & !is.na(tzMerged_2))
sum(paste(test3$tzMerged_1) == paste(test3$tzMerged_2))/length(test3$tzFormat)

errors1 <- subset(airports, (paste(tzFormat) != paste(tzMerged_1) & !is.na(tzFormat) & !is.na(tzMerged_1)))
errors2 <- subset(airports, (paste(tzFormat) != paste(tzMerged_2) & !is.na(tzFormat) & !is.na(tzMerged_2)))
errorsTot <- subset(airports, (paste(tzFormat) != paste(tzMerged_1) & !is.na(tzFormat) & !is.na(tzMerged_1)) |  (paste(tzFormat) != paste(tzMerged_2) & !is.na(tzFormat) & !is.na(tzMerged_2)))

# Export of the errors into a report
# install.packages("knitr")
library(knitr)
mdErrorsTable <- kable(subset(errorsTot,select = c("airportID","name","IATA","tzFormat","tzMerged_1","tzMerged_2")),format = "markdown")
knit("errors",text = mdErrorsTable,"../valid/errors.md")

# install.packages("lubridate")
library(lubridate)
x <- Sys.time()
mean(totaldiff1 <- sapply(1:length(errors1$tzFormat), function(i) difftime(force_tz(x,paste(errors1$tzMerged_1)[i]),force_tz(x,paste(errors1$tzFormat)[i]))))
mean(totaldiff2 <- sapply(1:length(errors2$tzFormat), function(i) difftime(force_tz(x,paste(errors2$tzMerged_2)[i]),force_tz(x,paste(errors2$tzFormat)[i]))))

couple <- unique(cbind(paste(errorsTot$tzFormat),paste(errorsTot$tzMerged_1),paste(errorsTot$tzMerged_2)))
mean(couplediff1 <- sapply(1:nrow(couple), function(i) difftime(force_tz(x,couple[i,1]),force_tz(x,couple[i,2]))))
mean(couplediff2 <- sapply(1:nrow(couple), function(i) difftime(force_tz(x,couple[i,1]),force_tz(x,couple[i,3]))))
couplediffTot <- cbind(couplediff1,couplediff2)

toValid <- subset(airports,paste(tzMerged_1) != paste(tzMerged_2) & !is.na(tzMerged_1) & !is.na(tzMerged_2))
mdValidTable <- kable(subset(toValid,select = c("airportID","name","IATA","tzFormat","tzMerged_1","tzMerged_2")),format = "markdown")
knit("valid",text = mdValidTable,"../valid/valid.md")

# install.packages("rmarkdown")
# library(rmarkdown)
# render(input = mdErrorsTable,output_file = "file",output_dir = ".")
# install.packages("markdown")
library(markdown)
markdownToHTML("../valid/errors.md","../valid/errors.html",encoding = "utf8")
markdownToHTML("../valid/valid.md","../valid/valid.html",encoding = "utf8")

airports <- subset(airports, select = -c(tzFormat,tzMerged_1))
summary(airports)

# install.packages("dplyr")
library(plyr)
airports <- plyr::rename(airports, c("tzMerged_2" = "tzFormat"))

# Final Proportion of missing tzFormat
length(airports$tzFormat[is.na(airports$tzFormat)])/length(airports$tzFormat)

# Export final database
summary(airports)
write.table(airports,file = "../data/airports_Updated.dat",row.names = FALSE,col.names = FALSE)