-- The About page reads this output verbatim. Aggregate the flights table once
-- into temporary tables, then build every list from those instead of scanning
-- flights again for each list.

SET @now := NOW();
SET @week := @now - INTERVAL 7 DAY;
SET @day := @now - INTERVAL 1 DAY;

SELECT @now AS 'Generated on';

-- One pass: per-user totals for all three time windows.
DROP TEMPORARY TABLE IF EXISTS user_totals;
CREATE TEMPORARY TABLE user_totals AS
SELECT uid,
    COUNT(*) AS cnt_all,
    SUM(distance) AS dist_all,
    SUM(upd_time > @week) AS cnt_week,
    SUM(CASE WHEN upd_time > @week THEN distance END) AS dist_week,
    SUM(upd_time > @day) AS cnt_day,
    SUM(CASE WHEN upd_time > @day THEN distance END) AS dist_day
    FROM flights
    WHERE uid != 1
    GROUP BY uid;

-- One pass: per-route counts. cnt_all includes every flight because the
-- airport list includes the demo flights; cnt_user excludes uid 1 for the
-- route list.
DROP TEMPORARY TABLE IF EXISTS route_counts;
CREATE TEMPORARY TABLE route_counts AS
SELECT src_apid,
    dst_apid,
    COUNT(*) AS cnt_all,
    SUM(uid != 1) AS cnt_user
    FROM flights
    GROUP BY src_apid, dst_apid;

-- MySQL cannot reference a temporary table twice in one query, so derive the
-- source and destination totals separately, then combine them.
DROP TEMPORARY TABLE IF EXISTS src_airport_totals;
CREATE TEMPORARY TABLE src_airport_totals AS
SELECT src_apid AS apid, SUM(cnt_all) AS ct
    FROM route_counts
    GROUP BY src_apid;

DROP TEMPORARY TABLE IF EXISTS dst_airport_totals;
CREATE TEMPORARY TABLE dst_airport_totals AS
SELECT dst_apid AS apid, SUM(cnt_all) AS ct
    FROM route_counts
    GROUP BY dst_apid;

DROP TEMPORARY TABLE IF EXISTS airport_totals;
CREATE TEMPORARY TABLE airport_totals AS
SELECT apid, SUM(ct) AS ct
    FROM (
        SELECT apid, ct FROM src_airport_totals
        UNION ALL
        SELECT apid, ct FROM dst_airport_totals
    ) AS x
    GROUP BY apid;

SELECT COUNT(*) AS '<br><b>Registered users</b>'
    FROM users
    WHERE uid != 1;
SELECT COALESCE(SUM(cnt_all), 0) AS '<b>Total flights</b>'
    FROM user_totals;
SELECT COALESCE(SUM(cnt_day), 0) AS '<b>Flights added yesterday</b>'
    FROM user_totals;

SELECT LPAD(t.cnt_all, 8, ' ') AS '<table width=100%><tr><td style="vertical-align: top;"><pre><b>Top 10 users by flights<br>All time</b>', IF(u.public = 'N', u.name, CONCAT('<a href="https://openflights.org/user/', u.name, '">', u.name, '</a>')) AS ''
    FROM user_totals AS t
    JOIN users AS u ON u.uid = t.uid
    ORDER BY t.cnt_all DESC
    LIMIT 10;

SELECT LPAD(t.cnt_week, 8, ' ') AS '</pre></td><td style="vertical-align: top;"><pre><b><br>Last week</b>', IF(u.public = 'N', u.name, CONCAT('<a href="https://openflights.org/user/', u.name, '">', u.name, '</a>')) AS ''
    FROM user_totals AS t
    JOIN users AS u ON u.uid = t.uid
    WHERE t.cnt_week > 0
    ORDER BY t.cnt_week DESC
    LIMIT 10;

SELECT LPAD(t.cnt_day, 8, ' ') AS '</pre></td><td style="vertical-align: top;"><pre><b><br>Yesterday</b>', IF(u.public = 'N', u.name, CONCAT('<a href="https://openflights.org/user/', u.name, '">', u.name, '</a>')) AS ''
    FROM user_totals AS t
    JOIN users AS u ON u.uid = t.uid
    WHERE t.cnt_day > 0
    ORDER BY t.cnt_day DESC
    LIMIT 10;

SELECT LPAD(t.dist_all, 8, ' ') AS '</pre></td></tr><tr><td style="vertical-align: top;"><pre><b>Top 10 users by miles<br>All time</b>', IF(u.public = 'N', u.name, CONCAT('<a href="https://openflights.org/user/', u.name, '">', u.name, '</a>')) AS ''
    FROM user_totals AS t
    JOIN users AS u ON u.uid = t.uid
    ORDER BY t.dist_all DESC
    LIMIT 10;

SELECT LPAD(t.dist_week, 8, ' ') AS '</pre></td><td style="vertical-align: top;"><pre><b><br>Last week</b>', IF(u.public = 'N', u.name, CONCAT('<a href="https://openflights.org/user/', u.name, '">', u.name, '</a>')) AS ''
    FROM user_totals AS t
    JOIN users AS u ON u.uid = t.uid
    WHERE t.cnt_week > 0
    ORDER BY t.dist_week DESC
    LIMIT 10;

SELECT LPAD(t.dist_day, 8, ' ') AS '</pre></td><td style="vertical-align: top;"><pre><b><br>YesterDAY</b>', IF(u.public = 'N', u.name, CONCAT('<a href="https://openflights.org/user/', u.name, '">', u.name, '</a>')) AS ''
    FROM user_totals AS t
    JOIN users AS u ON u.uid = t.uid
    WHERE t.cnt_day > 0
    ORDER BY t.dist_day DESC
    LIMIT 10;

SELECT LPAD(count, 8, ' ') AS '</pre></td></tr><tr><td style="vertical-align: top;"><pre><b>Top 10 users by popularity<br>All time</b>', IF(u.public = 'N', u.name, CONCAT('<a href="https://openflights.org/user/', u.name, '">', u.name, '</a>')) AS ''
    FROM users AS u
    WHERE u.uid != 1
    ORDER BY count DESC
    LIMIT 10;

-- Overfetch before the airport join: if a top route references an airport that
-- was deleted, the join drops that row and the outer LIMIT still fills.
SELECT CONCAT(s.iata, ' &harr;') AS '</pre></td></tr><tr><td style="vertical-align: top;"><pre><b>Top 10 routes</b>', d.iata AS '', LPAD(t.cnt_user, 6, ' ') AS ''
    FROM (
        SELECT src_apid, dst_apid, cnt_user
        FROM route_counts
        WHERE cnt_user > 0
        ORDER BY cnt_user DESC
        LIMIT 20
    ) AS t
    JOIN airports AS s ON s.apid = t.src_apid
    JOIN airports AS d ON d.apid = t.dst_apid
    ORDER BY t.cnt_user DESC
    LIMIT 10;

-- Same overfetch rationale as the routes list.
SELECT rpad(a.name, 20, ' ') AS '</pre></td><td style="vertical-align: top;"><pre><b>Top 10 airlines</b>', LPAD(t.cnt, 6, ' ') AS ''
    FROM (
        SELECT alid, COUNT(*) AS cnt
        FROM flights
        WHERE uid != 1 AND alid > 1
        GROUP BY alid
        ORDER BY cnt DESC
        LIMIT 20
    ) AS t
    JOIN airlines AS a ON a.alid = t.alid
    ORDER BY t.cnt DESC
    LIMIT 10;

SELECT rpad(a.name, 20, ' ') AS '</pre></td><td style="vertical-align: top;"><pre><b>Top 10 airports</b>', a.iata AS '', LPAD(x.ct, 6, ' ') AS ''
    FROM airport_totals AS x
    JOIN airports AS a ON a.apid = x.apid
    ORDER BY x.ct DESC
    LIMIT 10;

SELECT '</pre></td></tr></table>' AS '';
