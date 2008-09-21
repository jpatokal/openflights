--
-- Refresh the "demo" flights shown when logging in
--
delete from flights where uid=1;
-- Note: uid forced to 0 (demo), trid forced to NULL (not in trip)
insert into flights(uid,src_apid,src_time,dst_apid,distance,code,seat,seat_type,class,reason,plid,alid,trid,duration,registration,note,upd_time,opp) select 1,src_apid,src_time,dst_apid,distance,code,seat,seat_type,class,reason,plid,alid,null,duration,registration,note,upd_time,opp from flights where date_sub(now(), interval 3 day) < upd_time order by upd_time desc limit 500;
select now(), count(*) from flights where uid=1;
