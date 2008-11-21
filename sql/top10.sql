select now() as 'Generated on';

select count(*) as '<br><b>Registered users</b>' from users where uid != 1;
select count(*) as '<b>Total flights</b>' from flights where uid != 1;
select count(*) as '<b>Flights added yesterday</b>' from flights where uid != 1 and date_sub(now(), interval 1 day) < upd_time;

select count(*) as '', IF(u.public='N',u.name,concat('<a href="http://openflights.org/user/',u.name,'">',u.name,'</a>')) as '<br><b>Top 10 users (all time)</b>' from users as u,flights as f where u.uid != 1 and u.uid=f.uid group by f.uid order by count(*) desc limit 10;

select count(*) as '', IF(u.public='N',u.name,concat('<a href="http://openflights.org/user/',u.name,'">',u.name,'</a>')) as '<br><b>Top 10 users (last week)</b>' from users as u,flights as f where u.uid != 1 and u.uid=f.uid and date_sub(now(), interval 7 day) < upd_time group by f.uid order by count(*) desc limit 10;

select count(*) as '', IF(u.public='N',u.name,concat('<a href="http://openflights.org/user/',u.name,'">',u.name,'</a>')) as '<br><b>Top 10 users (yesterday)</b>' from users as u,flights as f where u.uid != 1 and u.uid=f.uid and date_sub(now(), interval 1 day) < upd_time group by f.uid order by count(*) desc limit 10;

select distinct CONCAT(s.iata,' &harr;') as '<br><b>Top 10 routes</b>',d.iata as '',count(fid) as '' from airports as s,airports as d,flights as f where f.uid != 1 and s.apid=f.src_apid and d.apid=f.dst_apid group by s.apid,d.apid order by count(fid) desc limit 10;

select rpad(a.name,30,' ') as '<br><b>Top 10 airports</b>',a.iata as '', count(*) as '' from airports as a,flights as f where f.uid != 1 and a.apid=f.src_apid or a.apid=f.dst_apid group by a.apid order by count(*) desc limit 10;

select rpad(a.name,30,' ') as '<br><b>Top 10 airlines</b>',count(*) as '' from airlines as a,flights as f where f.uid != 1 and a.alid=f.alid group by f.alid order by count(*) desc limit 10;
