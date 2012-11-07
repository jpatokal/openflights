--
-- Executed by data/merge-airports.sh
--
set names 'utf8';
set sql_safe_updates=1;

\! echo Flights from...
update flights set src_apid=@good where src_apid=@bad;
select row_count();
\! echo Flights to...
update flights set dst_apid=@good where dst_apid=@bad;
select row_count();
\! echo Deleting airport...
delete from airports where apid=@bad limit 1;
select row_count();
\! echo Done.
