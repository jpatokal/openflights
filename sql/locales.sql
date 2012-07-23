select locale,count(*) as count,(count(*)/(select count(*) from users))*100 as percent from users group by locale order by count desc;

