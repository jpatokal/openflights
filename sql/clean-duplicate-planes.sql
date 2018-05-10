-- Remove surrounding whitespace & all tabs
update planes
set name=TRIM(REPLACE(name, '\t', ''));

-- Show dupes
select name, min(plid), max(plid), count(*) as dupes from planes GROUP BY name having count(*) > 1 order by dupes;

-- Find exact dupes
drop table if exists tmp_planes_duplicates;
create table tmp_planes_duplicates
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
