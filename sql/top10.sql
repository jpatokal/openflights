select now() as 'Generated on';

select count(*) as '<br><b>Registered users</b>' from users where uid != 1;
select count(*) as '<b>Total flights</b>' from flights where uid != 1;
select count(*) as '<b>Flights added yesterday</b>' from flights where uid != 1 and date_sub(now(), interval 1 day) < upd_time;

select lpad(count(*),8," ") as '<table width=100%><tr><td style="vertical-align: top;"><pre><b>Top 10 users by flights<br>All time</b>', IF(u.public='N',u.name,concat('<a href="http://openflights.org/user/',u.name,'">',u.name,'</a>')) as '' from users as u,flights as f where u.uid != 1 and u.uid=f.uid group by f.uid order by count(*) desc limit 10;

select lpad(count(*),8," ") as '</pre></td><td style="vertical-align: top;"><pre><b><br>Last week</b>', IF(u.public='N',u.name,concat('<a href="http://openflights.org/user/',u.name,'">',u.name,'</a>')) as '' from users as u,flights as f where u.uid != 1 and u.uid=f.uid and date_sub(now(), interval 7 day) < upd_time group by f.uid order by count(*) desc limit 10;

select lpad(count(*),8," ") as '</pre></td><td style="vertical-align: top;"><pre><b><br>Yesterday</b>', IF(u.public='N',u.name,concat('<a href="http://openflights.org/user/',u.name,'">',u.name,'</a>')) as '' from users as u,flights as f where u.uid != 1 and u.uid=f.uid and date_sub(now(), interval 1 day) < upd_time group by f.uid order by count(*) desc limit 10;

select lpad(sum(distance),8," ") as '</pre></td></tr><tr><td style="vertical-align: top;"><pre><b>Top 10 users by miles<br>All time</b>', IF(u.public='N',u.name,concat('<a href="http://openflights.org/user/',u.name,'">',u.name,'</a>')) as '' from users as u,flights as f where u.uid != 1 and u.uid=f.uid group by f.uid order by sum(distance) desc limit 10;

select lpad(sum(distance),8," ") as '</pre></td><td style="vertical-align: top;"><pre><b><br>Last week</b>', IF(u.public='N',u.name,concat('<a href="http://openflights.org/user/',u.name,'">',u.name,'</a>')) as '' from users as u,flights as f where u.uid != 1 and u.uid=f.uid and date_sub(now(), interval 7 day) < upd_time group by f.uid order by sum(distance) desc limit 10;

select lpad(sum(distance),8," ") as '</pre></td><td style="vertical-align: top;"><pre><b><br>Yesterday</b>', IF(u.public='N',u.name,concat('<a href="http://openflights.org/user/',u.name,'">',u.name,'</a>')) as '' from users as u,flights as f where u.uid != 1 and u.uid=f.uid and date_sub(now(), interval 1 day) < upd_time group by f.uid order by sum(distance) desc limit 10;

select lpad(count,8," ") as '</pre></td></tr><tr><td style="vertical-align: top;"><pre><b>Top 10 users by popularity<br>All time</b>', IF(u.public='N',u.name,concat('<a href="http://openflights.org/user/',u.name,'">',u.name,'</a>')) as '' from users as u where u.uid != 1 order by count desc limit 10;

select distinct CONCAT(s.iata,' &harr;') as '</pre></td></tr><tr><td style="vertical-align: top;"><pre><b>Top 10 routes</b>',d.iata as '',lpad(count(fid),6," ") as '' from airports as s,airports as d,flights as f where f.uid != 1 and s.apid=f.src_apid and d.apid=f.dst_apid group by s.apid,d.apid order by count(fid) desc limit 10;

select rpad(a.name,20,' ') as '</pre></td><td style="vertical-align: top;"><pre><b>Top 10 airlines</b>', lpad(count(*),6," ") as '' from airlines as a,flights as f where f.uid != 1 and a.alid=f.alid group by f.alid order by count(*) desc limit 10;

select rpad(a.name,20,' ') as '</pre></td><td style="vertical-align: top;"><pre><b>Top 10 airports</b>',a.iata as '', lpad(count(*),6," ") as '' from airports as a,flights as f where f.uid != 1 and a.apid=f.src_apid or a.apid=f.dst_apid group by a.apid order by count(*) desc limit 10;

select "</pre></td></tr></table>" AS "";
