-- Remove elite level from users who have expired
update users set elite="" where validity < now();

-- Remove warning flag from users who have made their profiles public and have under 100 flights
update users set elite="" where elite="X" and public != "N" and uid!= 1 and uid in
	(select uid from flights group by uid having count(*) < 100);

-- Set warning flag for non-elite users with >=100 flights
update users set elite="X" where elite="" and uid!= 1 and uid in
	(select uid from flights group by uid having count(*) >= 100);

-- Set warning flag for non-elite users with hidden profiles
update users set elite="X" where elite="" and uid!= 1 and public="N";

-- Summarize
select elite,public,count(*) from users group by elite,public;

