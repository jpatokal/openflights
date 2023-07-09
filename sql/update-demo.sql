--
-- Refresh the "demo" flights shown when logging in
--
delete from flights where uid = 1;

-- Select every other flight
-- Note: uid forced to 0 (demo), trid forced to NULL (not in trip)
insert into flights(uid,src_apid,src_date,src_time,dst_apid,distance,code,seat,seat_type,class,reason,plid,alid,trid,duration,registration,note,upd_time,opp,mode)
    select 1,src_apid,src_date,src_time,dst_apid,distance,code,seat,seat_type,class,reason,plid,alid,null,duration,registration,note,upd_time,opp,mode
    from flights
    where fid % 2 = 0 and date_sub(now(), interval 3 day) < upd_time
    order by upd_time desc
    limit 200;

select now(), count(*) from flights where uid=1;
