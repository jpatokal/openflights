SELECT NOW() AS 'Generated on';

SELECT COUNT(*) AS '<br><b>Registered users</b>'
    FROM users
    WHERE uid != 1;
SELECT COUNT(*) AS '<b>Total flights</b>'
    FROM flights
    WHERE uid != 1;
SELECT COUNT(*) AS '<b>Flights added yesterday</b>'
    FROM flights
    WHERE uid != 1 AND DATE_SUB(NOW(), INTERVAL 1 DAY) < upd_time;

SELECT LPAD(COUNT(*), 8, " ") AS '<table width=100%><tr><td style="vertical-align: top;"><pre><b>Top 10 users by flights<br>All time</b>', IF(u.public = 'N', u.name, CONCAT('<a href="https://openflights.org/user/', u.name, '">', u.name, '</a>')) AS ''
    FROM users AS u, flights AS f
    WHERE u.uid != 1 AND u.uid = f.uid
    GROUP BY f.uid
    ORDER BY COUNT(*) DESC
    LIMIT 10;

SELECT LPAD(COUNT(*), 8, " ") AS '</pre></td><td style="vertical-align: top;"><pre><b><br>Last week</b>', IF(u.public = 'N', u.name, CONCAT('<a href="https://openflights.org/user/', u.name, '">', u.name, '</a>')) AS ''
    FROM users AS u, flights AS f
    WHERE u.uid != 1 AND u.uid = f.uid AND DATE_SUB(NOW(), INTERVAL 7 DAY) < upd_time
    GROUP BY f.uid
    ORDER BY COUNT(*) DESC
    LIMIT 10;

SELECT LPAD(COUNT(*), 8, " ") AS '</pre></td><td style="vertical-align: top;"><pre><b><br>Yesterday</b>', IF(u.public= 'N', u.name, CONCAT('<a href="https://openflights.org/user/', u.name, '">', u.name, '</a>')) AS ''
    FROM users AS u, flights AS f
    WHERE u.uid != 1 AND u.uid = f.uid AND DATE_SUB(NOW(), INTERVAL 1 DAY) < upd_time
    GROUP BY f.uid
    ORDER BY COUNT(*) DESC
    LIMIT 10;

SELECT LPAD(SUM(distance), 8, " ") AS '</pre></td></tr><tr><td style="vertical-align: top;"><pre><b>Top 10 users by miles<br>All time</b>', IF(u.public = 'N', u.name, CONCAT('<a href="https://openflights.org/user/', u.name, '">', u.name, '</a>')) AS ''
    FROM users AS u, flights AS f
    WHERE u.uid != 1 AND u.uid = f.uid
    GROUP BY f.uid
    ORDER BY SUM(distance) DESC
    LIMIT 10;

SELECT LPAD(SUM(distance), 8, " ") AS '</pre></td><td style="vertical-align: top;"><pre><b><br>Last week</b>', IF(u.public = 'N', u.name, CONCAT('<a href="https://openflights.org/user/', u.name, '">', u.name, '</a>')) AS ''
    FROM users AS u, flights AS f
    WHERE u.uid != 1 AND u.uid = f.uid AND DATE_SUB(NOW(), INTERVAL 7 DAY) < upd_time
    GROUP BY f.uid
    ORDER BY SUM(distance) DESC
    LIMIT 10;

SELECT LPAD(SUM(distance), 8, " ") AS '</pre></td><td style="vertical-align: top;"><pre><b><br>YesterDAY</b>', IF(u.public = 'N', u.name, CONCAT('<a href="https://openflights.org/user/', u.name, '">', u.name, '</a>')) AS ''
    FROM users AS u, flights AS f
    WHERE u.uid != 1 AND u.uid = f.uid AND DATE_SUB(NOW(), INTERVAL 1 DAY) < upd_time
    GROUP BY f.uid
    ORDER BY SUM(distance) DESC
    LIMIT 10;

SELECT LPAD(count, 8, " ") AS '</pre></td></tr><tr><td style="vertical-align: top;"><pre><b>Top 10 users by popularity<br>All time</b>', IF(u.public = 'N', u.name, CONCAT('<a href="https://openflights.org/user/', u.name, '">', u.name, '</a>')) AS ''
    FROM users AS u
    WHERE u.uid != 1
    ORDER BY count DESC
    LIMIT 10;

SELECT distinct CONCAT(s.iata, ' &harr;') AS '</pre></td></tr><tr><td style="vertical-align: top;"><pre><b>Top 10 routes</b>', d.iata AS '', LPAD(COUNT(fid), 6, " ") AS ''
    FROM airports AS s, airports AS d, flights AS f
    WHERE f.uid != 1 AND s.apid = f.src_apid AND d.apid = f.dst_apid
    GROUP BY s.apid, d.apid
    ORDER BY COUNT(fid) DESC
    LIMIT 10;

SELECT rpad(a.name, 20, ' ') AS '</pre></td><td style="vertical-align: top;"><pre><b>Top 10 airlines</b>', LPAD(COUNT(*), 6, " ") AS ''
    FROM airlines AS a, flights AS f
    WHERE f.uid != 1 AND a.alid > 1 AND a.alid = f.alid
    GROUP BY f.alid
    ORDER BY COUNT(*) DESC
    LIMIT 10;

SELECT rpad(a.name, 20, ' ') AS '</pre></td><td style="vertical-align: top;"><pre><b>Top 10 airports</b>', a.iata AS '', LPAD(SUM(x.ct), 6, " ") AS '' FROM
   ( SELECT src_apid AS apid, COUNT(*) AS ct
     FROM flights
     GROUP BY src_apid
   UNION ALL
     SELECT dst_apid AS apid, COUNT(*) AS ct
     FROM flights
     GROUP BY dst_apid
   ) x, airports AS a
   WHERE a.apid = x.apid
   GROUP BY x.apid
   ORDER BY SUM(x.ct) DESC
   LIMIT 10;

SELECT "</pre></td></tr></table>" AS "";
