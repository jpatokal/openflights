INSERT INTO users(name, password) VALUES('jpatokal', 'herb');

INSERT INTO planes(name, abbr, speed) VALUES('Boeing 737', 'B737', 485.0);

INSERT INTO flights(uid, src_apid, src_time, dst_apid, dst_time, distance, code, seat, seat_type, class, reason, plid, alid, trid) VALUES
                   (1,    3316, '2008-07-08',    1701, '2008-07-09', 5388, 'TK67', '',       'W',   'Y',    'P',    1, 4951, null), 
                   (1,    1701, '2008-07-10',     599, '2008-07-10', 1837, 'TK1975', '',     'W',   'Y',    'P',    1, 4951, null), 
                   (1,    599,  '2008-07-15',    1701, '2008-07-15', 1837, 'TK1978', '',     'W',   'Y',    'P',    1, 4951, null),
                   (1,    1701, '2008-07-15',    1128, '2008-07-15',  762, 'TK1142', '',     'W',   'Y',    'P',    1, 4951, null);

178
	2007

	NRT
	Tokyo
Japan
Narita 	SFO
	San Francisco
USA
San Francisco International 	
5,118 	mi
9:39 	h
	ANA All Nippon Airways
	  	 / 
Economy
Passenger
Personal 	
EDIT
DEL
177
	2007

	NTQ
	Wajima
Japan 	HND
	Tokyo
Japan
Haneda 	
198 	mi
0:51 	h
	ANA All Nippon Airways
	  	 / 
Economy
Passenger
Personal 	
EDIT
DEL
176
	2007

	FUK
	Fukuoka
Japan 	KMQ
	Kanazawa
Japan
Komatsu 	
390 	mi
1:12 	h
	ANA All Nippon Airways
	  	 / 
Economy
Passenger
Personal 	
EDIT
DEL
175
	2007

	BKK
	Bangkok
Thailand
Suvarnabhumi 	FUK
	Fukuoka
Japan 	
2,317 	mi
4:39 	h
	Thai Airways
	  	 / 
Economy
Passenger
Personal 	
EDIT
DEL
174
	2007

	SIN
	Singapore
Singapore
Changi Airport 	BKK
	Bangkok
Thailand
Suvarnabhumi

; Figure out all flight pairs
SELECT DISTINCT s.apid,s.x,s.y,d.apid,d.x,d.y,count(fid) AS times FROM flights AS f, airports AS s, airports AS d WHERE f.uid=1 AND f.src_apid=s.apid AND f.dst_apid=d.apid GROUP BY fid;

; Figure out all airports
SELECT DISTINCT a.apid,x,y,name,iata,icao,city,country,count(name) AS visits FROM flights AS f, airports AS a WHERE f.uid=1 AND f.src_apid=a.apid OR f.dst_apid=a.apid GROUP BY name;

; Figure out all flights originating from a given airport
SELECT f.code,src_date FROM flights AS f,airports AS a WHERE f.uid=1 AND f.src_apid=a.apid AND a.apid=1701;

; Basic stats
select count(*), sum(distance) from flights where uid=1;

    flights = [ [ 28.814167, 40.976111, -6.27, 53.421389,
                  'TK1975 IST-DUB<br>Boeing 737<br>1837 mi'] ,
                [ 28.814167, 40.976111, 31.405556, 30.121944,
                  'TK1142 IST-CAI<br>Boeing 737<br>762 mi' ] ,
                [ 29.696389, 30.917778, 55.364444, 25.252778,
                  'EK932 HBE-DXB<br>Airbus A330<br>1611 mi' ] ,
                [ 55.364444, 25.252778, 103.989444, 1.359167,
                  'SQ375 DXB-SIN<br>Boeing 777<br>3633 mi' ] ,
                [ -6.27, 53.421389, 28.814167, 40.976111,
                  'TK1978 DUB-IST<br>Boeing 737<br>1837 mi' ] ,
                [ 103.989444, 1.359167, 28.814167, 40.976111,
                  'TK67 SIN-IST<br>Airbus A340<br>5388 mi' ] ,
                [ -73.778889, 40.639722, 140.386389, 35.764722, 
                  'NW17 JFK-NRT<br>Boeing 747<br>6745 mi' ] ];

    airports = [ [ 28.814167, 40.976111, 'Istanbul Ataturk', 'IST', 'Istanbul', 'Turkey', 4 ],
                 [ -6.27, 53.421389, 'Dublin', 'DUB', 'Dublin', 'Ireland', 2 ],
                 [ -73.778889, 40.639722, 'John F Kennedy', 'JFK', 'New York', 'USA', 1 ],
                 [ 140.386389, 35.764722, 'Narita', 'NRT', 'Tokyo', 'Japan', 1 ],
                 [ 31.405556, 30.121944, 'Cairo', 'CAI', 'Cairo', 'Egypt', 1 ],
                 [ 103.989444, 1.359167, 'Singapore Changi', 'SIN', 'Singapore', 'Singapore', 10 ],
                 [ 29.696389, 30.917778, 'Borg el-Arab', 'HBE', 'Alexandria', 'Egypt', 1 ],
                 [ 55.364444, 25.252778, 'Dubai', 'DXB', 'Dubai', 'UAE', 2 ] ];

