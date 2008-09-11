select now() as 'Generated on';

select count(*) as '<br>Registered Users' from users;
select count(*) as 'Flights' from flights where uid != 1;

select count(*) as '', concat('<a href="http://openflights.org/user/',u.name,'">',u.name,'</a>') as '<br><b>Top 10 Users</b>' from users as u,flights as f where u.uid=f.uid group by f.uid order by count(*) desc limit 10;

select distinct CONCAT(s.iata,' -') as '<br><b>Top 10 Routes</b>',d.iata as '',count(fid) as '' from airports as s,airports as d,flights as f where s.apid=f.src_apid and d.apid=f.dst_apid group by s.apid,d.apid order by count(fid) desc limit 10;

select rpad(a.name,20,' ') as '<br><b>Top 10 Airports</b>',a.iata as '', count(*) as '' from airports as a,flights as f where a.apid=f.src_apid or a.apid=f.dst_apid group by a.apid order by count(*) desc limit 10;

select rpad(a.name,20,' ') as '<br><b>Top 10 Airlines</b>',count(*) as '' from airlines as a,flights as f where a.alid=f.alid group by f.alid order by count(*) desc limit 10;
