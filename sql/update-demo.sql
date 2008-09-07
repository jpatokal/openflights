--
-- Refresh the "demo" flights shown when logging in
--
delete from flights where uid=1;
insert into flights(uid,src_apid,src_time,dst_apid,distance,code,seat,seat_type,class,reason,plid,alid,trid,duration,registration,note,upd_time) select 1,src_apid,src_time,dst_apid,distance,code,seat,seat_type,class,reason,plid,alid,trid,duration,registration,note,upd_time from flights where date_sub(now(), interval 7 day) < upd_time;
