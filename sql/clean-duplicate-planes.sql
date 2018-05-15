-- Remove surrounding whitespace & all tabs
update planes
set name=TRIM(REPLACE(name, '\t', ''));

-- Show dupes
select name, min(plid), max(plid), count(*) as dupes from planes GROUP BY name having count(*) > 1 order by dupes;

-- Find exact dupes
drop table if exists tmp_planes_duplicates;
create table tmp_planes_duplicates (plid int, duplicate_of int)
insert into tmp_planes_duplicates
    select p1.plid, min(p2.plid) as duplicate_of
    from planes p1, planes p2
    where p1.name = p2.name AND p1.plid > p2.plid
    group by p1.plid;

-- Update flights
update flights f
join tmp_planes_duplicates t using(plid)
set f.plid = t.duplicate_of;

-- Delete duplicates
delete p
from planes p
join tmp_planes_duplicates using(plid);

-- Ensure no future duplicates
update planes set name=substring(name, 1, 80);
alter table planes modify name varchar(80);
alter table planes add constraint no_duplicates UNIQUE (name);

-- Add a frequency column
alter table planes add column iata text;
alter table planes add column icao text;
alter table planes add column frequency int default 0;
update planes p, (select plid, count(*) cnt from flights group by plid) as f set p.frequency=f.cnt where p.plid=f.plid;

-- Set some basic types manually
update planes set iata='146' where name='BAe 146';
update planes set iata='330' where name='Airbus A330';
update planes set iata='340' where name='Airbus A340';
update planes set iata='350' where name='Airbus A350';
update planes set iata='380' where name='Airbus A380';
update planes set iata='727' where name='Boeing 727';
update planes set iata='737' where name='Boeing 737';
update planes set iata='747' where name='Boeing 747';
update planes set iata='757' where name='Boeing 757';
update planes set iata='767' where name='Boeing 767';
update planes set iata='777' where name='Boeing 777';
update planes set iata='787' where name='Boeing 787';
