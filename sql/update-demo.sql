--
-- Refresh the "demo" flights shown when logging in
--
delete from flights where uid = 1;

-- Select every other flight updated in the last three days.
-- The upd_time index lets MySQL walk backwards from the newest rows and stop
-- after 200 matches; without it this scans the whole flights table and sorts.
-- Note: uid forced to 1 (demo), trid forced to NULL (not in trip)
insert into flights(uid,src_apid,src_date,src_time,dst_apid,distance,code,seat,seat_type,class,reason,plid,alid,trid,duration,registration,note,upd_time,opp,mode)
    select 1,src_apid,src_date,src_time,dst_apid,distance,code,seat,seat_type,class,reason,plid,alid,null,duration,registration,note,upd_time,opp,mode
    from flights
    where upd_time > now() - interval 3 day and fid % 2 = 0
    order by upd_time desc
    limit 200;

select now(), count(*) from flights where uid=1;
