-- Show dupes
select name, min(alid), max(alid), count(*) as dupes from airlines GROUP BY name having count(*) > 1 order by dupes;

-- Find exact dupes
drop table if exists tmp_airlines_duplicates;
create table tmp_airlines_duplicates (alid int, duplicate_of int);
insert into tmp_airlines_duplicates
    select p1.alid, min(p2.alid) as duplicate_of
    from airlines p1, airlines p2
    where p1.name = p2.name AND p1.alid > p2.alid
    group by p1.alid;

-- Update flights
update flights f
join tmp_airlines_duplicates t using(alid)
set f.alid = t.duplicate_of;

-- Delete duplicates
delete p
from airlines p
join tmp_airlines_duplicates using(alid);

-- Ensure no future duplicates
update airlines set name=substring(name, 1, 80);
alter table airlines modify name varchar(80);
alter table airlines add constraint no_duplicates UNIQUE (name);

-- Add a frequency column
alter table airlines add column iata text;
alter table airlines add column icao text;
alter table airlines add column frequency int default 0;
update airlines p, (select alid, count(*) cnt from flights group by alid) as f set p.frequency=f.cnt where p.alid=f.alid;
