select locale,count(*) as count,(count(*)/12513)*100 as percent from users group by locale order by count desc;

