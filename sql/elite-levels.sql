-- Remove elite level from users who have expired
update users set elite="" where validity < now();

-- Remove warning flag from users who have made their profiles public and have under 100 flights
-- (LEFT JOIN ensures that users with 0 flights are included)
update users LEFT JOIN (
       select uid from flights group by uid having count(*) < 100
) as nice_users
on (users.uid = nice_users.uid)
set elite="" where elite="X" and public != 'N' and users.uid!= 1;

-- Set warning flag for non-elite users with >=100 flights
update users JOIN (
       select uid from flights group by uid having count(*) >= 100
) as naughty_users
on (users.uid = naughty_users.uid)
set elite="X" where elite="" and users.uid!= 1;

-- Set warning flag for non-elite users with hidden profiles
update users set elite="X" where elite="" and uid!= 1 and public="N";

-- Summarize
select elite,public,count(*) from users group by elite,public;

