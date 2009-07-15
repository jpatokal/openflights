--
-- Executed by data/merge-airport.sh
--
set names 'utf8';

\! echo Before
select * from airports where icao=@icao;

-- Find DAFIF and OA entries
-- NB: Set to NULL if not found
select @dafif := apid from airports where icao=@icao and iata='';
select @oa := apid from airports where icao=@icao and iata!='';

-- Insert coords and (if missing) city name from DAFIF data into OurAirports
update airports as d,airports as o set o.x=d.x, o.y=d.y where d.apid=@dafif and o.apid=@oa;
update airports as d,airports as o set o.city=d.city where d.apid=@dafif and o.apid=@oa and o.city = "";

-- Alter any flights to use OurAirports version
-- NB: No effect if either of @dafif or @or are null
update flights set src_apid = @oa where src_apid = @dafif and @oa is not null;
update flights set dst_apid = @oa where dst_apid = @dafif and @oa is not null;

-- Delete now obsoleted DAFIF entry
-- NB: No effect if either of @dafif or @or are null
delete from airports where apid = @dafif and @oa is not null;

\! echo After
select * from airports where icao=@icao;
