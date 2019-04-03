\! echo Airports
update airports set uid=1 where uid=@uid;
select row_count();

\! echo Airlines
update airlines set uid=1 where uid=@uid;
select row_count();

set sql_safe_updates=1;

\! echo Flights
delete from flights where uid=@uid;
select row_count();

\! echo Trips
delete from trips where uid=@uid;
select row_count();

\! echo User
delete from tripit_tokens where uid=@uid;
delete from users where uid=@uid;
select row_count();

\! echo Done.

